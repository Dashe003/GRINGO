<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user=$this->requireAuth();$id=(int)$user['id'];
        $stmt=$this->db->prepare('SELECT l.id,l.name,COUNT(c.id) AS total FROM clothing_labels l LEFT JOIN clothes c ON c.label_id=l.id AND c.user_id=:user_id GROUP BY l.id ORDER BY l.sort_order');$stmt->execute(['user_id'=>$id]);$labels=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT status,COUNT(*) AS total FROM clothes WHERE user_id=:user_id GROUP BY status');$stmt->execute(['user_id'=>$id]);$statuses=[];foreach($stmt->fetchAll() as $r)$statuses[$r['status']]=(int)$r['total'];
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM outfits WHERE user_id=:user_id AND status=\'active\'');$stmt->execute(['user_id'=>$id]);$outfitCount=(int)$stmt->fetchColumn();
        $stmt=$this->db->prepare('SELECT c.id,c.name,c.label_id,l.name AS label_name,c.image_path,c.created_at FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status<>\'archived\' ORDER BY c.created_at DESC LIMIT 4');$stmt->execute(['user_id'=>$id]);$recentClothes=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT o.id,o.name,o.created_at,(SELECT c.image_path FROM outfit_items oi JOIN clothes c ON c.id=oi.clothing_id WHERE oi.outfit_id=o.id ORDER BY oi.position LIMIT 1) AS image_path FROM outfits o WHERE o.user_id=:user_id AND o.status=\'active\' ORDER BY o.created_at DESC LIMIT 4');$stmt->execute(['user_id'=>$id]);$recentOutfits=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT h.worn_at,o.id,o.name FROM outfit_history h JOIN outfits o ON o.id=h.outfit_id AND o.user_id=h.user_id WHERE h.user_id=:user_id ORDER BY h.worn_at DESC LIMIT 5');$stmt->execute(['user_id'=>$id]);$recentWears=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT ce.event_date,o.id AS outfit_id,o.name AS outfit_name,ce.note FROM calendar_events ce JOIN outfits o ON o.id=ce.outfit_id AND o.user_id=ce.user_id WHERE ce.user_id=:user_id AND ce.event_type=\'planned\' AND ce.event_date>=CURDATE() ORDER BY ce.event_date,ce.id LIMIT 5');$stmt->execute(['user_id'=>$id]);$upcoming=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT currency,SUM(purchase_price) AS total FROM clothes WHERE user_id=:user_id AND purchase_price IS NOT NULL AND status<>\'archived\' GROUP BY currency ORDER BY currency');$stmt->execute(['user_id'=>$id]);$wardrobeValue=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT currency,SUM(amount) AS total FROM purchases WHERE user_id=:user_id AND purchased_at>=DATE_FORMAT(CURDATE(),\'%Y-%m-01\') GROUP BY currency ORDER BY currency');$stmt->execute(['user_id'=>$id]);$monthSpend=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT b.name,b.amount,b.currency,COALESCE(SUM(CASE WHEN p.currency=b.currency AND p.purchased_at BETWEEN b.period_start AND b.period_end THEN p.amount ELSE 0 END),0) AS spent FROM budgets b LEFT JOIN purchases p ON p.user_id=b.user_id AND p.purchased_at BETWEEN b.period_start AND b.period_end WHERE b.user_id=:user_id AND CURDATE() BETWEEN b.period_start AND b.period_end GROUP BY b.id ORDER BY b.period_start DESC LIMIT 3');$stmt->execute(['user_id'=>$id]);$budgets=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT c.name,COUNT(h.id) AS wears FROM clothes c LEFT JOIN outfit_items oi ON oi.clothing_id=c.id LEFT JOIN outfit_history h ON h.outfit_id=oi.outfit_id AND h.user_id=c.user_id WHERE c.user_id=:user_id AND c.status<>\'archived\' GROUP BY c.id ORDER BY wears DESC,c.name LIMIT 5');$stmt->execute(['user_id'=>$id]);$wearFrequency=$stmt->fetchAll();
        render('dashboard/index',['title'=>'Tableau de bord','user'=>$user,'labels'=>$labels,'statuses'=>$statuses,'outfitCount'=>$outfitCount,'recentClothes'=>$recentClothes,'recentOutfits'=>$recentOutfits,'recentWears'=>$recentWears,'upcoming'=>$upcoming,'wardrobeValue'=>$wardrobeValue,'monthSpend'=>$monthSpend,'budgets'=>$budgets,'wearFrequency'=>$wearFrequency]);
    }
}