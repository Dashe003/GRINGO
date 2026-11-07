<?php
declare(strict_types=1);

final class WishlistController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$stmt=$this->db->prepare('SELECT w.*,l.name AS label_name FROM wishlist_items w LEFT JOIN clothing_labels l ON l.id=w.label_id WHERE w.user_id=:user_id ORDER BY FIELD(w.status,\'wanted\',\'pending\',\'purchased\',\'dismissed\'),FIELD(w.priority,\'high\',\'normal\',\'low\'),w.created_at DESC');$stmt->execute(['user_id'=>$user['id']]);$labels=$this->db->query('SELECT id,name FROM clothing_labels WHERE is_active=1 ORDER BY sort_order')->fetchAll();render('wishlist/index',['title'=>'Liste de souhaits','items'=>$stmt->fetchAll(),'labels'=>$labels]);
    }
    public function save(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$name=trim((string)($_POST['name']??''));$label=(int)($_POST['label_id']??0);$brand=trim((string)($_POST['brand']??''));$seller=trim((string)($_POST['seller']??''));$url=trim((string)($_POST['url']??''));$price=trim((string)($_POST['estimated_price']??''));$currency=strtoupper(substr(trim((string)($_POST['currency']??'USD')),0,3));$priority=(string)($_POST['priority']??'normal');$notes=trim((string)($_POST['notes']??''));$errors=[];
        if($name===''||mb_strlen($name)>160)$errors[]='Le nom est obligatoire.';if($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))$errors[]='Le lien doit être une URL complète.';if($price!==''&&(!is_numeric($price)||(float)$price<0))$errors[]='Le prix estimé doit être positif.';if(!preg_match('/^[A-Z]{3}$/',$currency))$currency='USD';if(!in_array($priority,['low','normal','high'],true))$priority='normal';
        if($label){$check=$this->db->prepare('SELECT id FROM clothing_labels WHERE id=:id AND is_active=1');$check->execute(['id'=>$label]);if(!$check->fetch())$errors[]='Libellé invalide.';}else $label=null;
        $photo=null;$file=$_FILES['photo']??[];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$photo=$this->storePhoto($file,$errors);}
        if($errors){foreach($errors as $error)flash('error',$error);redirect('wishlist');}
        $stmt=$this->db->prepare('INSERT INTO wishlist_items(user_id,name,label_id,photo_path,brand,seller,url,estimated_price,currency,priority,notes) VALUES(:user_id,:name,:label_id,:photo,:brand,:seller,:url,:price,:currency,:priority,:notes)');$stmt->execute(['user_id'=>$user['id'],'name'=>$name,'label_id'=>$label,'photo'=>$photo,'brand'=>$brand?:null,'seller'=>$seller?:null,'url'=>$url?:null,'price'=>$price?:null,'currency'=>$currency,'priority'=>$priority,'notes'=>$notes?:null]);flash('success','Article ajouté à la liste de souhaits.');redirect('wishlist');
    }
    private function storePhoto(array $file,array &$errors): ?string
    {
        if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024){$errors[]='La photo doit peser 5 Mo maximum.';return null;}$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext||@getimagesize($file['tmp_name'])===false){$errors[]='Photo JPG, PNG ou WEBP invalide.';return null;}$dir=APP_ROOT.'/storage/uploads/wishlist';if(!is_dir($dir))mkdir($dir,0755,true);$name=bin2hex(random_bytes(20)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)){$errors[]='Impossible d’enregistrer la photo.';return null;}return 'storage/uploads/wishlist/'.$name;
    }
    public function status(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$status=(string)($_POST['status']??'');if(!in_array($status,['wanted','pending','purchased','dismissed'],true)){http_response_code(422);return;}$this->db->prepare('UPDATE wishlist_items SET status=:status WHERE id=:id AND user_id=:user_id')->execute(['status'=>$status,'id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success','Statut de la liste de souhaits mis à jour.');redirect('wishlist');
    }
    public function delete(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$this->db->prepare('DELETE FROM wishlist_items WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success','Article retiré de la liste.');redirect('wishlist');
    }
    public function convert(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$stmt=$this->db->prepare('SELECT * FROM wishlist_items WHERE id=:id AND user_id=:user_id');$stmt->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);$item=$stmt->fetch();if(!$item){http_response_code(404);return;}$label=(int)($item['label_id']??1);$cat=$this->db->prepare('SELECT id FROM categories WHERE label_id=:label AND is_active=1 AND (user_id IS NULL OR user_id=:user_id) ORDER BY user_id IS NOT NULL DESC LIMIT 1');$cat->execute(['label'=>$label,'user_id'=>$user['id']]);$category=$cat->fetchColumn()?:null;
        $this->db->beginTransaction();try{$this->db->prepare('INSERT INTO clothes(user_id,label_id,category_id,name,image_path,brand,seller,purchase_price,currency,status) VALUES(:user_id,:label_id,:category,:name,:image,:brand,:seller,:price,:currency,\'available\')')->execute(['user_id'=>$user['id'],'label_id'=>$label,'category'=>$category,'name'=>$item['name'],'image'=>$item['photo_path'],'brand'=>$item['brand'],'seller'=>$item['seller'],'price'=>$item['estimated_price'],'currency'=>$item['currency']]);$clothingId=(int)$this->db->lastInsertId();$this->db->prepare('UPDATE wishlist_items SET status=\'purchased\' WHERE id=:id AND user_id=:user_id')->execute(['id'=>$item['id'],'user_id'=>$user['id']]);$this->db->commit();flash('success','Article transféré dans votre garde-robe. Ajoutez une photo principale depuis sa fiche si besoin.');redirect('clothes-edit',['id'=>$clothingId]);}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
}
