<?php
declare(strict_types=1);

final class OutfitController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$archived=(($_GET['status']??'')==='archived');$status=$archived?'archived':'active';
        $stmt=$this->db->prepare('SELECT o.*,EXISTS(SELECT 1 FROM outfit_favorites f WHERE f.outfit_id=o.id AND f.user_id=o.user_id) AS is_favorite,(SELECT COUNT(*) FROM outfit_items oi WHERE oi.outfit_id=o.id) AS item_count,(SELECT c.image_path FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id ORDER BY oi.position LIMIT 1) AS preview_path FROM outfits o WHERE o.user_id=:user_id AND o.status=:status ORDER BY o.created_at DESC');
        $stmt->execute(['user_id'=>$user['id'],'status'=>$status]);$outfits=$stmt->fetchAll();$images=$this->db->prepare('SELECT oi.outfit_id,c.name,c.image_path FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id JOIN clothes c ON c.id=oi.clothing_id AND c.user_id=o.user_id WHERE o.user_id=:user_id AND o.status=:status ORDER BY oi.outfit_id,oi.position,c.id');$images->execute(['user_id'=>$user['id'],'status'=>$status]);$piecesByOutfit=[];foreach($images->fetchAll() as $piece)$piecesByOutfit[(int)$piece['outfit_id']][]=$piece;render('outfits/index',['title'=>$archived?'Archives des tenues':'Mes tenues','outfits'=>$outfits,'piecesByOutfit'=>$piecesByOutfit,'archived'=>$archived]);
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $this->form($user, null);
    }

    public function edit(): void
    {
        $user = $this->requireAuth();
        $outfit = $this->findOwned($user['id'], (int)($_GET['id'] ?? 0));
        if (!$outfit || $outfit['status']!=='active') { http_response_code(404); render('errors/404',['title'=>'Tenue introuvable']); return; }
        $stmt = $this->db->prepare('SELECT clothing_id FROM outfit_items WHERE outfit_id=:id');
        $stmt->execute(['id'=>$outfit['id']]);
        $outfit['items'] = array_map('intval', array_column($stmt->fetchAll(),'clothing_id'));
        $this->form($user, $outfit);
    }

    private function form(array $user, ?array $outfit): void
    {
        $errors=[]; $values=$outfit ?? [];
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            $values=array_merge($values,$_POST);
            $name=trim((string)($_POST['name']??''));
            if ($name==='' || mb_strlen($name)>160) $errors[]='Le nom de la tenue est obligatoire (160 caractères maximum).';
            $coverPath=$outfit['cover_path']??null;
            $coverFile=$_FILES['cover']??[];
            if(($coverFile['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$coverPath=$this->storeCover($coverFile,$errors);
            $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['items']??[])),static fn($id)=>$id>0)));
            $eligible=$this->eligibleIds($user['id'],$ids);
            if (count($eligible)!==count($ids)) $errors[]='Une ou plusieurs pièces ne sont plus disponibles dans votre garde-robe.';
            if (!$errors) {
                $this->db->beginTransaction();
                try {
                    if ($outfit) {
                        $stmt=$this->db->prepare('UPDATE outfits SET name=:name,description=:description,cover_path=:cover,style=:style,occasion=:occasion,season=:season,notes=:notes WHERE id=:id AND user_id=:user_id');
                        $stmt->execute(['name'=>$name,'description'=>trim((string)($_POST['description']??'')),'cover'=>$coverPath,'style'=>trim((string)($_POST['style']??'')),'occasion'=>trim((string)($_POST['occasion']??'')),'season'=>trim((string)($_POST['season']??'')),'notes'=>trim((string)($_POST['notes']??'')),'id'=>$outfit['id'],'user_id'=>$user['id']]);
                        $id=(int)$outfit['id'];
                        $this->db->prepare('DELETE FROM outfit_items WHERE outfit_id=:id')->execute(['id'=>$id]);
                    } else {
                        $stmt=$this->db->prepare('INSERT INTO outfits (user_id,name,description,cover_path,style,occasion,season,notes) VALUES (:user_id,:name,:description,:cover,:style,:occasion,:season,:notes)');
                        $stmt->execute(['user_id'=>$user['id'],'name'=>$name,'description'=>trim((string)($_POST['description']??'')),'cover'=>$coverPath,'style'=>trim((string)($_POST['style']??'')),'occasion'=>trim((string)($_POST['occasion']??'')),'season'=>trim((string)($_POST['season']??'')),'notes'=>trim((string)($_POST['notes']??''))]);
                        $id=(int)$this->db->lastInsertId();
                    }
                    $insert=$this->db->prepare('INSERT INTO outfit_items (outfit_id,clothing_id,position) VALUES (:outfit_id,:clothing_id,:position)');
                    foreach ($ids as $position=>$clothingId) $insert->execute(['outfit_id'=>$id,'clothing_id'=>$clothingId,'position'=>$position]);
                    $this->db->commit();
                    flash('success',$outfit?'La tenue a été mise à jour.':'Votre tenue a été enregistrée.');
                    redirect('outfits-show',['id'=>$id]);
                } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
            }
        }
        $stmt=$this->db->prepare('SELECT c.id,c.name,c.image_path,c.status,c.label_id,l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status<>\'archived\' ORDER BY l.sort_order,c.name');
        $stmt->execute(['user_id'=>$user['id']]);
        render('outfits/form',['title'=>$outfit?'Modifier la tenue':'Créer une tenue','outfit'=>$outfit,'values'=>$values,'errors'=>$errors,'clothes'=>$stmt->fetchAll(),'selected'=>array_map('intval',(array)($_POST['items']??($outfit['items']??[])))]);
    }

    private function storeCover(array $file,array &$errors): ?string
    {
        if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024){$errors[]='La couverture doit faire 5 Mo maximum.';return null;}
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
        if(!$ext||@getimagesize($file['tmp_name'])===false){$errors[]='Format de couverture invalide. Utilisez JPG, PNG ou WEBP.';return null;}
        $dir=APP_ROOT.'/storage/uploads/outfits';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)){$errors[]='Le dossier de couverture ne peut pas etre cree.';return null;}
        $name=bin2hex(random_bytes(20)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)){$errors[]='La couverture ne peut pas etre enregistree.';return null;}return 'storage/uploads/outfits/'.$name;
    }

    public function show(): void
    {
        $user=$this->requireAuth(); $outfit=$this->findOwned($user['id'],(int)($_GET['id']??0));
        if (!$outfit) { http_response_code(404); render('errors/404',['title'=>'Tenue introuvable']); return; }
        $stmt=$this->db->prepare('SELECT c.id,c.name,c.image_path,c.status,l.name AS label_name FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id JOIN clothing_labels l ON l.id=c.label_id WHERE oi.outfit_id=:id AND c.user_id=:user_id ORDER BY oi.position,c.name');
        $stmt->execute(['id'=>$outfit['id'],'user_id'=>$user['id']]); $items=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT worn_at,note FROM outfit_history WHERE outfit_id=:id AND user_id=:user_id ORDER BY worn_at DESC LIMIT 8');
        $stmt->execute(['id'=>$outfit['id'],'user_id'=>$user['id']]);
        render('outfits/show',['title'=>$outfit['name'],'outfit'=>$outfit,'items'=>$items,'history'=>$stmt->fetchAll()]);
    }

    public function favorite(): void
    {
        $user=$this->requireAuth(); if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);return;} verify_csrf();
        $id=(int)($_POST['id']??0); $outfit=$this->findOwned($user['id'],$id); if (!$outfit||$outfit['status']!=='active') {http_response_code(404);return;}
        $stmt=$this->db->prepare('SELECT 1 FROM outfit_favorites WHERE user_id=:user_id AND outfit_id=:id'); $stmt->execute(['user_id'=>$user['id'],'id'=>$id]);
        $isFavorite=(bool)$stmt->fetchColumn();
        if ($isFavorite) $this->db->prepare('DELETE FROM outfit_favorites WHERE user_id=:user_id AND outfit_id=:id')->execute(['user_id'=>$user['id'],'id'=>$id]);
        else $this->db->prepare('INSERT INTO outfit_favorites (user_id,outfit_id) VALUES (:user_id,:id)')->execute(['user_id'=>$user['id'],'id'=>$id]);
        flash('success',$isFavorite?'Tenue retirée des favoris.':'Tenue ajoutée aux favoris.');
        redirect('outfits-show',['id'=>$id]);
    }

    public function rate(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();
        $id=(int)($_POST['id']??0);$outfit=$this->findOwned((int)$user['id'],$id);if(!$outfit){http_response_code(404);return;}
        $raw=(string)($_POST['rating']??'0');$rating=$raw==='0'||$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT);
        if($rating!==null&&($rating===false||$rating<1||$rating>5)){flash('error','La note doit être comprise entre 1 et 5 étoiles.');redirect('outfits-show',['id'=>$id]);}
        $this->db->prepare('UPDATE outfits SET rating=:rating WHERE id=:id AND user_id=:user_id')->execute(['rating'=>$rating,'id'=>$id,'user_id'=>$user['id']]);
        flash('success',$rating===null?'Note retirée.':'Note enregistrée.');redirect('outfits-show',['id'=>$id]);
    }

    public function delete(): void
    {
        $user=$this->requireAuth(); if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);return;} verify_csrf();
        $this->db->prepare('UPDATE outfits SET status=\'archived\' WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);
        flash('success','La tenue a été archivée.'); redirect('outfits');
    }

    public function restore(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);
        $stmt=$this->db->prepare("UPDATE outfits SET status='active' WHERE id=:id AND user_id=:user_id AND status='archived'");$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);
        flash($stmt->rowCount()?'success':'error',$stmt->rowCount()?'La tenue a ete restauree.':'Cette tenue archivee est introuvable.');redirect($stmt->rowCount()?'outfits-show':'outfits',$stmt->rowCount()?['id'=>$id]:[]);
    }

    public function duplicate(): void
    {
        $user=$this->requireAuth(); if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);return;} verify_csrf();
        $outfit=$this->findOwned($user['id'],(int)($_POST['id']??0)); if (!$outfit||$outfit['status']!=='active') {http_response_code(404);return;}
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('INSERT INTO outfits (user_id,name,description,cover_path,style,occasion,season,notes) VALUES (:user_id,:name,:description,:cover_path,:style,:occasion,:season,:notes)');
            $stmt->execute(['user_id'=>$user['id'],'name'=>$outfit['name'].' (copie)','description'=>$outfit['description'],'cover_path'=>$outfit['cover_path'],'style'=>$outfit['style'],'occasion'=>$outfit['occasion'],'season'=>$outfit['season'],'notes'=>$outfit['notes']]); $newId=(int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO outfit_items (outfit_id,clothing_id,position) SELECT :new_id,clothing_id,position FROM outfit_items WHERE outfit_id=:old_id')->execute(['new_id'=>$newId,'old_id'=>$outfit['id']]);
            $this->db->commit(); flash('success','Une copie de la tenue a été créée.'); redirect('outfits-show',['id'=>$newId]);
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
    }

    public function wear(): void
    {
        $user=$this->requireAuth(); if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);return;} verify_csrf();
        $id=(int)($_POST['id']??0); $outfit=$this->findOwned($user['id'],$id); if (!$outfit||$outfit['status']!=='active') {http_response_code(404);return;}
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO outfit_history (user_id,outfit_id,worn_at,note) VALUES (:user_id,:id,NOW(),:note)')->execute(['user_id'=>$user['id'],'id'=>$id,'note'=>trim((string)($_POST['note']??''))?:null]);
            $this->db->prepare('UPDATE outfits SET wear_count=wear_count+1,last_worn_at=NOW() WHERE id=:id AND user_id=:user_id')->execute(['id'=>$id,'user_id'=>$user['id']]);
            $this->db->commit(); flash('success','Tenue marquée comme portée.');
        } catch (Throwable $exception) { $this->db->rollBack(); throw $exception; }
        redirect('outfits-show',['id'=>$id]);
    }

    private function eligibleIds(int $userId,array $ids): array
    {
        if (!$ids) return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$this->db->prepare("SELECT id FROM clothes WHERE user_id=? AND status<>'archived' AND id IN ($marks)"); $stmt->execute(array_merge([$userId],$ids));
        return array_map('intval',array_column($stmt->fetchAll(),'id'));
    }

    private function findOwned(int $userId,int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT o.*,(SELECT COUNT(*) FROM outfit_items oi WHERE oi.outfit_id=o.id) AS item_count,EXISTS(SELECT 1 FROM outfit_favorites f WHERE f.outfit_id=o.id AND f.user_id=o.user_id) AS is_favorite FROM outfits o WHERE o.id=:id AND o.user_id=:user_id');
        $stmt->execute(['id'=>$id,'user_id'=>$userId]); return $stmt->fetch()?:null;
    }
}
