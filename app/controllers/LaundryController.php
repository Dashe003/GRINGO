<?php
declare(strict_types=1);

final class LaundryController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$status=(string)($_GET['status']??'');
        $sql='SELECT c.id,c.name,c.image_path,c.status,c.last_washed_at,l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status<>\'archived\'';$params=['user_id'=>$user['id']];
        if(in_array($status,['available','laundry','repair','unavailable'],true)){$sql.=' AND c.status=:status';$params['status']=$status;}
        $sql.=' ORDER BY FIELD(c.status,\'laundry\',\'repair\',\'unavailable\',\'available\'),c.name';$stmt=$this->db->prepare($sql);$stmt->execute($params);
        $history=$this->db->prepare('SELECT h.*,c.name AS clothing_name FROM laundry_history h JOIN clothes c ON c.id=h.clothing_id AND c.user_id=h.user_id WHERE h.user_id=:user_id ORDER BY h.started_at DESC LIMIT 30');$history->execute(['user_id'=>$user['id']]);
        render('laundry/index',['title'=>'Gestion du linge','clothes'=>$stmt->fetchAll(),'history'=>$history->fetchAll(),'selectedStatus'=>$status]);
    }
    public function status(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');$note=trim((string)($_POST['note']??''));
        if(!in_array($status,['available','laundry','repair','unavailable'],true)){http_response_code(422);return;}
        $stmt=$this->db->prepare('SELECT status FROM clothes WHERE id=:id AND user_id=:user_id AND status<>\'archived\'');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$clothing=$stmt->fetch();if(!$clothing){http_response_code(404);return;}
        $this->db->beginTransaction();
        try{
            if($status==='laundry' && $clothing['status']!=='laundry') $this->db->prepare('INSERT INTO laundry_history(user_id,clothing_id,note) VALUES(:user_id,:id,:note)')->execute(['user_id'=>$user['id'],'id'=>$id,'note'=>$note?:null]);
            if($status==='available' && $clothing['status']==='laundry'){
                $this->db->prepare('UPDATE laundry_history SET completed_at=NOW(),note=COALESCE(:note,note) WHERE user_id=:user_id AND clothing_id=:id AND completed_at IS NULL')->execute(['note'=>$note?:null,'user_id'=>$user['id'],'id'=>$id]);
                $this->db->prepare('UPDATE clothes SET last_washed_at=NOW() WHERE id=:id AND user_id=:user_id')->execute(['id'=>$id,'user_id'=>$user['id']]);
            }
            $this->db->prepare('UPDATE clothes SET status=:status,notes=CASE WHEN :note2=\'\' THEN notes ELSE :note3 END WHERE id=:id AND user_id=:user_id')->execute(['status'=>$status,'note2'=>$note,'note3'=>$note,'id'=>$id,'user_id'=>$user['id']]);
            $this->db->commit();flash('success','État du vêtement mis à jour.');
        }catch(Throwable $exception){$this->db->rollBack();throw $exception;}
        redirect('laundry',['status'=>(string)($_POST['filter']??'')]);
    }
}
