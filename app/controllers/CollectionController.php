<?php
declare(strict_types=1);

final class CollectionController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$stmt=$this->db->prepare('SELECT c.*,(SELECT COUNT(*) FROM collection_items ci WHERE ci.collection_id=c.id) AS item_count FROM collections c WHERE c.user_id=:user_id ORDER BY c.created_at DESC');$stmt->execute(['user_id'=>$user['id']]);
        $clothes=$this->db->prepare('SELECT id,name,image_path FROM clothes WHERE user_id=:user_id AND status<>\'archived\' ORDER BY name');$clothes->execute(['user_id'=>$user['id']]);
        render('collections/index',['title'=>'Collections','collections'=>$stmt->fetchAll(),'clothes'=>$clothes->fetchAll()]);
    }
    public function show(): void
    {
        $user=$this->requireAuth();$id=(int)($_GET['id']??0);$stmt=$this->db->prepare('SELECT * FROM collections WHERE id=:id AND user_id=:user_id');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$collection=$stmt->fetch();if(!$collection){http_response_code(404);render('errors/404',['title'=>'Collection introuvable']);return;}
        $items=$this->db->prepare('SELECT c.id,c.name,c.image_path,l.name AS label_name FROM collection_items ci JOIN clothes c ON c.id=ci.clothing_id AND c.user_id=:user_id JOIN clothing_labels l ON l.id=c.label_id WHERE ci.collection_id=:collection_id ORDER BY c.name');$items->execute(['user_id'=>$user['id'],'collection_id'=>$id]);
        $clothes=$this->db->prepare('SELECT id,name,image_path FROM clothes WHERE user_id=:user_id AND status<>\'archived\' ORDER BY name');$clothes->execute(['user_id'=>$user['id']]);render('collections/show',['title'=>$collection['name'],'collection'=>$collection,'items'=>$items->fetchAll(),'clothes'=>$clothes->fetchAll()]);
    }
    public function save(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['items']??[])),static fn($v)=>$v>0)));$errors=[];
        if($name===''||mb_strlen($name)>120)$errors[]='Le nom est obligatoire (120 caractères maximum).';
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$check=$this->db->prepare("SELECT COUNT(*) FROM clothes WHERE user_id=? AND status<>'archived' AND id IN ($marks)");$check->execute(array_merge([$user['id']],$ids));if((int)$check->fetchColumn()!==count($ids))$errors[]='Certaines pièces ne sont pas accessibles.';}
        if($id){$check=$this->db->prepare('SELECT id FROM collections WHERE id=:id AND user_id=:user_id');$check->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$check->fetch())$errors[]='Cette collection n’est pas accessible.';}
        if($errors){foreach($errors as $error)flash('error',$error);redirect($id?'collection-show':'collections',$id?['id'=>$id]:[]);}
        $this->db->beginTransaction();try{if($id){$this->db->prepare('UPDATE collections SET name=:name,description=:description WHERE id=:id AND user_id=:user_id')->execute(['name'=>$name,'description'=>$description,'id'=>$id,'user_id'=>$user['id']]);$this->db->prepare('DELETE FROM collection_items WHERE collection_id=:id')->execute(['id'=>$id]);}else{$this->db->prepare('INSERT INTO collections(user_id,name,description) VALUES(:user_id,:name,:description)')->execute(['user_id'=>$user['id'],'name'=>$name,'description'=>$description]);$id=(int)$this->db->lastInsertId();}
            $insert=$this->db->prepare('INSERT INTO collection_items(collection_id,clothing_id) VALUES(:collection_id,:clothing_id)');foreach($ids as $clothingId)$insert->execute(['collection_id'=>$id,'clothing_id'=>$clothingId]);$this->db->commit();flash('success','Collection enregistrée.');redirect('collection-show',['id'=>$id]);
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function delete(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$this->db->prepare('DELETE FROM collections WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success','Collection supprimée.');redirect('collections');
    }
}
