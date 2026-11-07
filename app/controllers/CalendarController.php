<?php
declare(strict_types=1);

final class CalendarController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$mode=(string)($_GET['view']??'month');if(!in_array($mode,['day','week','month'],true))$mode='month';
        $month=(string)($_GET['month']??date('Y-m'));if(!preg_match('/^\d{4}-\d{2}$/',$month)||!checkdate((int)substr($month,5,2),1,(int)substr($month,0,4)))$month=date('Y-m');
        $first=new DateTimeImmutable($month.'-01');$focus=(string)($_GET['date']??date('Y-m-d'));$d=DateTimeImmutable::createFromFormat('!Y-m-d',$focus);if(!$d||$d->format('Y-m-d')!==$focus)$d=new DateTimeImmutable('today');
        if($mode==='day'){$start=$d;$end=$d;}elseif($mode==='week'){$start=$d->modify('monday this week');$end=$start->modify('+6 days');}else{$start=$first->modify('monday this week');$end=$first->modify('last day of this month')->modify('sunday this week');}
        $stmt=$this->db->prepare("SELECT ce.*,o.name AS outfit_name,COALESCE(o.cover_path,(SELECT c.image_path FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id ORDER BY oi.position,c.id LIMIT 1)) AS outfit_image,(SELECT COUNT(*) FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id AND c.status<>'available') AS unavailable_count FROM calendar_events ce LEFT JOIN outfits o ON o.id=ce.outfit_id AND o.user_id=ce.user_id WHERE ce.user_id=:user_id AND ce.event_date BETWEEN :start AND :end ORDER BY ce.event_date,ce.id");$stmt->execute(['user_id'=>$user['id'],'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d')]);$eventsByDate=[];$eventRows=$stmt->fetchAll();foreach($eventRows as $event)$eventsByDate[$event['event_date']][]=$event;
        $eventOutfitIds=array_values(array_unique(array_filter(array_map('intval',array_column($eventRows,'outfit_id')))));$weekPiecesByOutfit=[];if($eventOutfitIds){$marks=implode(',',array_fill(0,count($eventOutfitIds),'?'));$pieces=$this->db->prepare("SELECT oi.outfit_id,c.name,c.image_path FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id JOIN clothes c ON c.id=oi.clothing_id AND c.user_id=o.user_id WHERE o.user_id=? AND oi.outfit_id IN ($marks) ORDER BY oi.outfit_id,oi.position,c.id");$pieces->execute(array_merge([(int)$user['id']],$eventOutfitIds));foreach($pieces->fetchAll() as $piece)$weekPiecesByOutfit[(int)$piece['outfit_id']][]=$piece;}
        $editingEvent=null;$editId=(int)($_GET['edit_id']??0);if($editId){$edit=$this->db->prepare('SELECT * FROM calendar_events WHERE id=:id AND user_id=:user_id AND event_type=\'planned\'');$edit->execute(['id'=>$editId,'user_id'=>$user['id']]);$editingEvent=$edit->fetch()?:null;}
        $outfits=$this->db->prepare("SELECT o.id,o.name,COALESCE(o.cover_path,(SELECT c.image_path FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id ORDER BY oi.position,c.id LIMIT 1)) AS outfit_image,(SELECT COUNT(*) FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id AND c.status<>'available') AS unavailable_count FROM outfits o WHERE o.user_id=:user_id AND o.status='active' ORDER BY o.name");$outfits->execute(['user_id'=>$user['id']]);
        render('calendar/index',['title'=>'Calendrier','month'=>$month,'first'=>$first,'start'=>$start,'end'=>$end,'focus'=>$d,'mode'=>$mode,'eventsByDate'=>$eventsByDate,'weekPiecesByOutfit'=>$weekPiecesByOutfit,'outfits'=>$outfits->fetchAll(),'editingEvent'=>$editingEvent,'conflictWarning'=>flash('conflict'),'csrfToken'=>$_SESSION['_token']]);
    }

    public function autoSave(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); return; }
        verify_csrf();

        $date = (string) ($_POST['event_date'] ?? '');
        $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            $this->jsonResponse(['ok' => false, 'message' => 'Choisissez une date valide.'], 422);
            return;
        }

        $eventId = (int) ($_POST['event_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? 'save');
        if (!in_array($action, ['save', 'delete'], true)) {
            $this->jsonResponse(['ok' => false, 'message' => 'Action inconnue.'], 422);
            return;
        }
        if ($action === 'delete') {
            $delete = $this->db->prepare("DELETE FROM calendar_events WHERE id=:id AND user_id=:user_id AND event_type='planned'");
            $delete->execute(['id' => $eventId, 'user_id' => $user['id']]);
            if (!$delete->rowCount()) { $this->jsonResponse(['ok' => false, 'message' => 'Cette programmation est introuvable.'], 404); return; }
            $this->jsonResponse(['ok' => true]);
            return;
        }

        $outfitId = (int) ($_POST['outfit_id'] ?? 0);
        $outfit = $this->db->prepare("SELECT id,(SELECT COUNT(*) FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id AND c.status<>'available') AS unavailable_count FROM outfits o WHERE o.id=:id AND o.user_id=:user_id AND o.status='active'");
        $outfit->execute(['id' => $outfitId, 'user_id' => $user['id']]);
        $outfitData = $outfit->fetch();
        if (!$outfitData) { $this->jsonResponse(['ok' => false, 'message' => 'Choisissez une tenue active de votre espace.'], 422); return; }

        if ($eventId) {
            $save = $this->db->prepare("UPDATE calendar_events SET outfit_id=:outfit_id,event_date=:event_date WHERE id=:id AND user_id=:user_id AND event_type='planned'");
            $save->execute(['outfit_id' => $outfitId, 'event_date' => $date, 'id' => $eventId, 'user_id' => $user['id']]);
            if (!$save->rowCount()) {
                $check = $this->db->prepare("SELECT id FROM calendar_events WHERE id=:id AND user_id=:user_id AND event_type='planned'");
                $check->execute(['id' => $eventId, 'user_id' => $user['id']]);
                if (!$check->fetch()) { $this->jsonResponse(['ok' => false, 'message' => 'Cette programmation est introuvable.'], 404); return; }
            }
        } else {
            $save = $this->db->prepare("INSERT INTO calendar_events(user_id,outfit_id,event_date,event_type) VALUES(:user_id,:outfit_id,:event_date,'planned')");
            $save->execute(['user_id' => $user['id'], 'outfit_id' => $outfitId, 'event_date' => $date]);
            $eventId = (int) $this->db->lastInsertId();
        }

        $this->jsonResponse(['ok' => true, 'event_id' => $eventId, 'unavailable_count' => (int) $outfitData['unavailable_count']]);
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function save(): void
    {
        $user=$this->requireAuth(); if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;} verify_csrf();
        $id=(int)($_POST['id']??0); $outfitId=(int)($_POST['outfit_id']??0); $date=(string)($_POST['event_date']??''); $note=trim((string)($_POST['note']??''));
        $back=(string)($_POST['month']??date('Y-m'));
        $errors=[];
        $dateObject=DateTime::createFromFormat('!Y-m-d',$date);if(!$dateObject||$dateObject->format('Y-m-d')!==$date)$errors[]='Choisissez une date valide.';
        $stmt=$this->db->prepare('SELECT id FROM outfits WHERE id=:id AND user_id=:user_id AND status=\'active\''); $stmt->execute(['id'=>$outfitId,'user_id'=>$user['id']]);
        if(!$stmt->fetch()) $errors[]='Choisissez une tenue de votre espace.';
        if($id){$stmt=$this->db->prepare('SELECT id FROM calendar_events WHERE id=:id AND user_id=:user_id');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$stmt->fetch())$errors[]='Cette programmation n’est pas accessible.';}
        if($errors){foreach($errors as $error)flash('error',$error);redirect('calendar',['month'=>$back]);}
        $conflict=$this->db->prepare('SELECT COUNT(*) FROM calendar_events WHERE user_id=:user_id AND event_date=:date AND event_type=\'planned\' AND id<>:id');
        $conflict->execute(['user_id'=>$user['id'],'date'=>$date,'id'=>$id]);
        if((int)$conflict->fetchColumn()>0) flash('conflict','Une autre tenue est déjà prévue à cette date. Vous pouvez tout de même enregistrer cette programmation.');
        if($id){$stmt=$this->db->prepare('UPDATE calendar_events SET outfit_id=:outfit_id,event_date=:date,note=:note WHERE id=:id AND user_id=:user_id');$stmt->execute(['outfit_id'=>$outfitId,'date'=>$date,'note'=>$note,'id'=>$id,'user_id'=>$user['id']]);}
        else{$stmt=$this->db->prepare('INSERT INTO calendar_events(user_id,outfit_id,event_date,note) VALUES(:user_id,:outfit_id,:date,:note)');$stmt->execute(['user_id'=>$user['id'],'outfit_id'=>$outfitId,'date'=>$date,'note'=>$note]);}
        flash('success',$id?'Programmation modifiée.':'Tenue ajoutée au calendrier.');redirect('calendar',['month'=>substr($date,0,7)]);
    }

    public function status(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();
        $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['worn','cancelled'],true)){http_response_code(422);return;}
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT outfit_id,event_type FROM calendar_events WHERE id=:id AND user_id=:user_id FOR UPDATE');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$event=$stmt->fetch();if(!$event){$this->db->rollBack();http_response_code(404);return;}
            $this->db->prepare('UPDATE calendar_events SET event_type=:status WHERE id=:id AND user_id=:user_id')->execute(['status'=>$status,'id'=>$id,'user_id'=>$user['id']]);
            if($status==='worn' && $event['event_type']==='planned' && $event['outfit_id']){
                $this->db->prepare('INSERT INTO outfit_history(user_id,outfit_id,worn_at,note) VALUES(:user_id,:outfit_id,NOW(),\'Portée depuis le calendrier\')')->execute(['user_id'=>$user['id'],'outfit_id'=>$event['outfit_id']]);
                $this->db->prepare('UPDATE outfits SET wear_count=wear_count+1,last_worn_at=NOW() WHERE id=:id AND user_id=:user_id')->execute(['id'=>$event['outfit_id'],'user_id'=>$user['id']]);
            }
            $this->db->commit();flash('success',$status==='worn'?'Tenue marquée comme portée.':'Programmation annulée.');
        }catch(Throwable $exception){$this->db->rollBack();throw $exception;}
        redirect('calendar',['month'=>(string)($_POST['month']??date('Y-m'))]);
    }

    public function delete(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();
        $this->db->prepare('DELETE FROM calendar_events WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success','Programmation supprimée.');redirect('calendar',['month'=>(string)($_POST['month']??date('Y-m'))]);
    }
}
