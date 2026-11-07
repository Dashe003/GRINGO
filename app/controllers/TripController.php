<?php
declare(strict_types=1);

final class TripController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$stmt=$this->db->prepare('SELECT t.*,(SELECT COUNT(*) FROM trip_items ti WHERE ti.trip_id=t.id) AS item_count,(SELECT COUNT(*) FROM trip_items ti WHERE ti.trip_id=t.id AND ti.packed=1) AS packed_count FROM trips t WHERE t.user_id=:user_id ORDER BY t.start_date DESC');$stmt->execute(['user_id'=>$user['id']]);render('trips/index',['title'=>'Valise virtuelle','trips'=>$stmt->fetchAll()]);
    }
    public function show(): void
    {
        $user=$this->requireAuth();$id=(int)($_GET['id']??0);$trip=null;$selected=[];$selectedOutfits=[];
        if($id){$stmt=$this->db->prepare('SELECT * FROM trips WHERE id=:id AND user_id=:user_id');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$trip=$stmt->fetch();if(!$trip){http_response_code(404);render('errors/404',['title'=>'Voyage introuvable']);return;}$stmt=$this->db->prepare('SELECT ti.clothing_id,ti.quantity,ti.packed,c.name,c.image_path,l.name AS label_name FROM trip_items ti JOIN clothes c ON c.id=ti.clothing_id AND c.user_id=:user_id JOIN clothing_labels l ON l.id=c.label_id WHERE ti.trip_id=:id ORDER BY l.sort_order,c.name');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$selected=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT outfit_id FROM trip_outfits WHERE trip_id=:id');$stmt->execute(['id'=>$id]);$selectedOutfits=array_map('intval',array_column($stmt->fetchAll(),'outfit_id'));}
        $stmt=$this->db->prepare('SELECT c.id,c.name,c.image_path,c.status,l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status<>\'archived\' ORDER BY l.sort_order,c.name');$stmt->execute(['user_id'=>$user['id']]);$clothes=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT id,name,occasion,season FROM outfits WHERE user_id=:user_id AND status=\'active\' ORDER BY name');$stmt->execute(['user_id'=>$user['id']]);$outfits=$stmt->fetchAll();
        render('trips/show',['title'=>$trip?$trip['name']:'Préparer un voyage','trip'=>$trip,'selected'=>$selected,'selectedOutfits'=>$selectedOutfits,'clothes'=>$clothes,'outfits'=>$outfits]);
    }
    public function save(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));$destination=trim((string)($_POST['destination']??''));$description=trim((string)($_POST['description']??''));$notes=trim((string)($_POST['notes']??''));$start=(string)($_POST['start_date']??'');$end=(string)($_POST['end_date']??'');$itemIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['items']??[])))));$outfitIds=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['outfits']??[])))));
        if($name===''||mb_strlen($name)>120||!$this->validDate($start)||!$this->validDate($end)||$start>$end){flash('error','Vérifiez le nom et les dates du voyage.');redirect($id?'trip-show':'trips',$id?['id'=>$id]:[]);}
        if($itemIds){$marks=implode(',',array_fill(0,count($itemIds),'?'));$stmt=$this->db->prepare("SELECT COUNT(*) FROM clothes WHERE user_id=? AND status<>'archived' AND id IN ($marks)");$stmt->execute(array_merge([$user['id']],$itemIds));if((int)$stmt->fetchColumn()!==count($itemIds)){flash('error','Une pièce sélectionnée n’est pas accessible.');redirect($id?'trip-show':'trips',$id?['id'=>$id]:[]);}}
        if($outfitIds){$marks=implode(',',array_fill(0,count($outfitIds),'?'));$stmt=$this->db->prepare("SELECT COUNT(*) FROM outfits WHERE user_id=? AND status='active' AND id IN ($marks)");$stmt->execute(array_merge([$user['id']],$outfitIds));if((int)$stmt->fetchColumn()!==count($outfitIds)){flash('error','Une tenue sélectionnée n’est pas accessible.');redirect($id?'trip-show':'trips',$id?['id'=>$id]:[]);}}
        if($id){$stmt=$this->db->prepare('SELECT id FROM trips WHERE id=:id AND user_id=:user_id');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$stmt->fetch()){http_response_code(404);return;}}
        $this->db->beginTransaction();try{if($id){$this->db->prepare('UPDATE trips SET name=:name,destination=:destination,description=:description,notes=:notes,start_date=:start,end_date=:end WHERE id=:id AND user_id=:user_id')->execute(['name'=>$name,'destination'=>$destination?:null,'description'=>$description?:null,'notes'=>$notes?:null,'start'=>$start,'end'=>$end,'id'=>$id,'user_id'=>$user['id']]);$tripId=$id;$this->db->prepare('DELETE FROM trip_items WHERE trip_id=:id')->execute(['id'=>$id]);$this->db->prepare('DELETE FROM trip_outfits WHERE trip_id=:id')->execute(['id'=>$id]);}else{$this->db->prepare('INSERT INTO trips(user_id,name,destination,description,notes,start_date,end_date) VALUES(:user_id,:name,:destination,:description,:notes,:start,:end)')->execute(['user_id'=>$user['id'],'name'=>$name,'destination'=>$destination?:null,'description'=>$description?:null,'notes'=>$notes?:null,'start'=>$start,'end'=>$end]);$tripId=(int)$this->db->lastInsertId();}
            $insertItem=$this->db->prepare('INSERT IGNORE INTO trip_items(trip_id,clothing_id,quantity,packed) VALUES(:trip_id,:clothing_id,:quantity,0)');foreach($itemIds as $clothingId){$quantity=max(1,min(99,(int)(($_POST['quantity'][$clothingId]??1))));$insertItem->execute(['trip_id'=>$tripId,'clothing_id'=>$clothingId,'quantity'=>$quantity]);}
            $insertOutfit=$this->db->prepare('INSERT INTO trip_outfits(trip_id,outfit_id) VALUES(:trip_id,:outfit_id)');foreach($outfitIds as $outfitId){$insertOutfit->execute(['trip_id'=>$tripId,'outfit_id'=>$outfitId]);$this->db->prepare('INSERT IGNORE INTO trip_items(trip_id,clothing_id,quantity,packed) SELECT :trip_id,oi.clothing_id,1,0 FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id AND o.user_id=:user_id JOIN clothes c ON c.id=oi.clothing_id AND c.user_id=:user_id2 WHERE oi.outfit_id=:outfit_id AND c.status<>\'archived\'')->execute(['trip_id'=>$tripId,'user_id'=>$user['id'],'user_id2'=>$user['id'],'outfit_id'=>$outfitId]);}
            $this->db->commit();flash('success','Voyage et liste de valise enregistrés.');redirect('trip-show',['id'=>$tripId]);
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function packed(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$tripId=(int)($_POST['trip_id']??0);$clothingId=(int)($_POST['clothing_id']??0);$packed=(int)($_POST['packed']??0)?1:0;$stmt=$this->db->prepare('UPDATE trip_items ti JOIN trips t ON t.id=ti.trip_id SET ti.packed=:packed WHERE ti.trip_id=:trip_id AND ti.clothing_id=:clothing_id AND t.user_id=:user_id');$stmt->execute(['packed'=>$packed,'trip_id'=>$tripId,'clothing_id'=>$clothingId,'user_id'=>$user['id']]);redirect('trip-show',['id'=>$tripId]);
    }
    public function delete(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$this->db->prepare('DELETE FROM trips WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success','Voyage supprimé.');redirect('trips');
    }
    private function validDate(string $v):bool{$d=DateTime::createFromFormat('!Y-m-d',$v);return $d!==false&&$d->format('Y-m-d')===$v;}
}
