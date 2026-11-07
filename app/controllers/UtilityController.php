<?php
declare(strict_types=1);

final class UtilityController extends Controller
{
    public function favorites(): void
    {
        $user=$this->requireAuth();
        $clothes=$this->db->prepare('SELECT c.id,c.name,c.image_path,c.rating,l.name AS label_name FROM clothing_favorites f JOIN clothes c ON c.id=f.clothing_id AND c.user_id=f.user_id JOIN clothing_labels l ON l.id=c.label_id WHERE f.user_id=:user_id ORDER BY f.created_at DESC');$clothes->execute(['user_id'=>$user['id']]);
        $outfits=$this->db->prepare('SELECT o.id,o.name,o.rating FROM outfit_favorites f JOIN outfits o ON o.id=f.outfit_id AND o.user_id=f.user_id WHERE f.user_id=:user_id AND o.status=\'active\' ORDER BY f.created_at DESC');$outfits->execute(['user_id'=>$user['id']]);$outfitRows=$outfits->fetchAll();$piecesByOutfit=[];
        if($outfitRows){$ids=array_map('intval',array_column($outfitRows,'id'));$marks=implode(',',array_fill(0,count($ids),'?'));$pieces=$this->db->prepare("SELECT oi.outfit_id,c.name,c.image_path FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id JOIN clothes c ON c.id=oi.clothing_id AND c.user_id=o.user_id WHERE o.user_id=? AND oi.outfit_id IN ($marks) ORDER BY oi.outfit_id,oi.position,c.id");$pieces->execute(array_merge([(int)$user['id']],$ids));foreach($pieces->fetchAll() as $piece)$piecesByOutfit[(int)$piece['outfit_id']][]=$piece;}
        render('favorites/index',['title'=>'Favoris','clothes'=>$clothes->fetchAll(),'outfits'=>$outfitRows,'piecesByOutfit'=>$piecesByOutfit]);
    }

    public function generator(): void
    {
        $user=$this->requireAuth();$occasion=trim((string)($_GET['occasion']??''));$style=trim((string)($_GET['style']??''));$season=trim((string)($_GET['season']??''));$dominant=trim((string)($_GET['color']??''));$formality=(string)($_GET['formality']??'');if(!in_array($formality,['casual','smart_casual','formal','black_tie'],true))$formality='';$proposals=[];
        $stmt=$this->db->prepare('SELECT c.id,c.name,c.label_id,c.image_path,c.color,c.occasion,c.style,c.season,c.formality,l.name AS label_name,COALESCE((SELECT COUNT(*) FROM outfit_items oi JOIN outfit_history oh ON oh.outfit_id=oi.outfit_id WHERE oi.clothing_id=c.id AND oh.user_id=c.user_id),0) AS wear_count FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status=\'available\' ORDER BY c.id');$stmt->execute(['user_id'=>$user['id']]);$items=$stmt->fetchAll();
        $groups=[];foreach($items as $item){if($occasion!==''&&$item['occasion']!==''&&stripos($item['occasion'],$occasion)===false)continue;if($style!==''&&$item['style']!==''&&stripos($item['style'],$style)===false)continue;if($season!==''&&$item['season']!==''&&stripos($item['season'],$season)===false)continue;if($formality!==''&&$item['formality']!==null&&$item['formality']!==$formality)continue;if($dominant!==''&&stripos((string)$item['color'],$dominant)===false)continue;$groups[(int)$item['label_id']][]=$item;}
        $labelNames=[1=>'HAUT',2=>'BAS',3=>'CHAUSSURES',4=>'COUVRE-CHEF',5=>'ACCESSOIRES',6=>'EXTÉRIEUR'];
        $recent=$this->db->prepare('SELECT GROUP_CONCAT(oi.clothing_id ORDER BY oi.clothing_id SEPARATOR \'-\') AS signature FROM outfit_history h JOIN outfit_items oi ON oi.outfit_id=h.outfit_id WHERE h.user_id=:user_id AND h.worn_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY h.outfit_id ORDER BY MAX(h.worn_at) DESC LIMIT 30');$recent->execute(['user_id'=>$user['id']]);$seen=array_filter(array_column($recent->fetchAll(),'signature'));
        for($attempt=0;$attempt<18&&count($proposals)<3;$attempt++){$ids=[];$chosen=[];foreach([1,2,3,6,4,5] as $label){if(empty($groups[$label]))continue;$pool=$groups[$label];usort($pool,static fn($a,$b)=>((int)$a['wear_count']<=> (int)$b['wear_count']) ?: random_int(-1,1));$take=($label>=4&&count($pool)>1&&random_int(0,100)<25)?2:1;foreach(array_slice($pool,0,$take) as $item){$ids[]=(int)$item['id'];$chosen[]=$item;}}
            if(count($ids)<2)break;$signature=$ids;sort($signature);$signature=implode('-',$signature);if(in_array($signature,$seen,true))continue;$key=implode('-',$ids);if(isset($proposals[$key]))continue;$proposals[$key]=['items'=>$chosen,'ids'=>$ids];}
        render('generator/index',['title'=>'Suggestions de tenues','proposals'=>array_values($proposals),'filters'=>compact('occasion','style','season','dominant','formality')]);
    }

    public function saveSuggestion(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['items']??[])))));$name=trim((string)($_POST['name']??'Tenue suggérée'));if(count($ids)<2){flash('error','La proposition ne contient pas assez de pièces disponibles.');redirect('generator');}
        $marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$this->db->prepare("SELECT id FROM clothes WHERE user_id=? AND status='available' AND id IN ($marks)");$stmt->execute(array_merge([$user['id']],$ids));if(count($stmt->fetchAll())!==count($ids)){flash('error','Une pièce de cette proposition n’est plus disponible.');redirect('generator');}
        $this->db->beginTransaction();try{$this->db->prepare('INSERT INTO outfits(user_id,name,description) VALUES(:user_id,:name,\'Proposition créée par le générateur local\')')->execute(['user_id'=>$user['id'],'name'=>$name]);$outfitId=(int)$this->db->lastInsertId();$insert=$this->db->prepare('INSERT INTO outfit_items(outfit_id,clothing_id,position) VALUES(:outfit_id,:clothing_id,:position)');foreach($ids as $position=>$clothingId)$insert->execute(['outfit_id'=>$outfitId,'clothing_id'=>$clothingId,'position'=>$position]);$this->db->commit();flash('success','La tenue a été enregistrée.');redirect('outfits-show',['id'=>$outfitId]);}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function colors(): void
    {
        $user=$this->requireAuth();$colors=$this->db->query('SELECT id,name,hex_code,color_family FROM colors ORDER BY name')->fetchAll();$selected=(int)($_GET['color_id']??0);$compatible=[];$explanation='';$clothes=[];
        if($selected){$stmt=$this->db->prepare('SELECT c.name,c.hex_code,c.color_family,cc.compatibility,cc.explanation FROM colors c LEFT JOIN color_compatibilities cc ON (cc.color_a_id=c.id AND cc.color_b_id=:selected) OR (cc.color_b_id=c.id AND cc.color_a_id=:selected2) WHERE c.id<>:not_selected ORDER BY FIELD(cc.compatibility,\'harmonious\',\'contrast\',\'difficult\'),c.name');$stmt->execute(['selected'=>$selected,'selected2'=>$selected,'not_selected'=>$selected]);$compatible=$stmt->fetchAll();$chosen=$this->db->prepare('SELECT name FROM colors WHERE id=:id');$chosen->execute(['id'=>$selected]);$explanation=(string)$chosen->fetchColumn();
            $names=array_column($compatible,'name');$names[]=$explanation;$clauses=[];$params=['user_id'=>$user['id']];foreach(array_values(array_unique(array_filter($names))) as $i=>$name){$key='color'.$i;$clauses[]="c.color LIKE :$key";$params[$key]='%'.$name.'%';}if($clauses){$stmt=$this->db->prepare('SELECT c.id,c.name,c.color,c.image_path,l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status=\'available\' AND ('.implode(' OR ',$clauses).') ORDER BY l.sort_order,c.name');$stmt->execute($params);$clothes=$stmt->fetchAll();}}
        render('colors/index',['title'=>'Assistant couleurs','colors'=>$colors,'selected'=>$selected,'compatible'=>$compatible,'clothes'=>$clothes,'selectedName'=>$explanation]);
    }

    public function search(): void
    {
        $user=$this->requireAuth();$q=trim((string)($_GET['q']??''));$results=['clothes'=>[],'brands'=>[],'outfits'=>[],'collections'=>[],'trips'=>[],'categories'=>[]];
        if(mb_strlen($q)>=2){$like='%'.$q.'%';$stmt=$this->db->prepare('SELECT c.id,c.name,l.name AS detail FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id=:user_id AND c.status<>\'archived\' AND (c.name LIKE :q1 OR c.brand LIKE :q2 OR c.color LIKE :q3) ORDER BY c.name LIMIT 30');$stmt->execute(['user_id'=>$user['id'],'q1'=>$like,'q2'=>$like,'q3'=>$like]);$results['clothes']=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT MIN(id) AS id,brand AS name,COUNT(*) AS detail FROM clothes WHERE user_id=:user_id AND brand LIKE :query AND brand<>\'\' GROUP BY brand ORDER BY brand LIMIT 20');$stmt->execute(['user_id'=>$user['id'],'query'=>$like]);$results['brands']=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT id,name,occasion AS detail FROM outfits WHERE user_id=:user_id AND status=\'active\' AND (name LIKE :q1 OR style LIKE :q2 OR occasion LIKE :q3) LIMIT 30');$stmt->execute(['user_id'=>$user['id'],'q1'=>$like,'q2'=>$like,'q3'=>$like]);$results['outfits']=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT id,name,description AS detail FROM collections WHERE user_id=:user_id AND (name LIKE :q1 OR description LIKE :q2) LIMIT 20');$stmt->execute(['user_id'=>$user['id'],'q1'=>$like,'q2'=>$like]);$results['collections']=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT id,name,destination AS detail FROM trips WHERE user_id=:user_id AND (name LIKE :q1 OR destination LIKE :q2) LIMIT 20');$stmt->execute(['user_id'=>$user['id'],'q1'=>$like,'q2'=>$like]);$results['trips']=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT id,name,label_id AS detail FROM categories WHERE is_active=1 AND (user_id IS NULL OR user_id=:user_id) AND name LIKE :q');$stmt->execute(['user_id'=>$user['id'],'q'=>$like]);$results['categories']=$stmt->fetchAll();}
        render('search/index',['title'=>'Recherche','query'=>$q,'results'=>$results]);
    }

    public function statistics(): void
    {
        $user=$this->requireAuth();$stmt=$this->db->prepare('SELECT l.name,COUNT(c.id) AS total FROM clothing_labels l LEFT JOIN clothes c ON c.label_id=l.id AND c.user_id=:user_id GROUP BY l.id ORDER BY l.sort_order');$stmt->execute(['user_id'=>$user['id']]);$labels=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT status,COUNT(*) AS total FROM clothes WHERE user_id=:user_id GROUP BY status');$stmt->execute(['user_id'=>$user['id']]);$statusMap=[];foreach($stmt->fetchAll() as $row)$statusMap[$row['status']]=(int)$row['total'];$stmt=$this->db->prepare('SELECT COALESCE(color,\'Non renseignée\') AS name,COUNT(*) AS total FROM clothes WHERE user_id=:user_id AND status<>\'archived\' GROUP BY color ORDER BY total DESC LIMIT 8');$stmt->execute(['user_id'=>$user['id']]);$colors=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT COALESCE(style,\'Non renseigné\') AS name,COUNT(*) AS total FROM clothes WHERE user_id=:user_id AND status<>\'archived\' GROUP BY style ORDER BY total DESC LIMIT 8');$stmt->execute(['user_id'=>$user['id']]);$styles=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT currency,SUM(amount) AS total FROM purchases WHERE user_id=:user_id AND purchased_at>=DATE_FORMAT(CURDATE(),\'%Y-01-01\') GROUP BY currency ORDER BY currency');$stmt->execute(['user_id'=>$user['id']]);$spend=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT DATE_FORMAT(purchased_at,\'%Y-%m\') AS month,currency,SUM(amount) AS total FROM purchases WHERE user_id=:user_id AND purchased_at>=DATE_SUB(CURDATE(),INTERVAL 12 MONTH) GROUP BY month,currency ORDER BY month,currency');$stmt->execute(['user_id'=>$user['id']]);$spendTrend=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT c.name,COUNT(h.id) AS wears FROM clothes c LEFT JOIN outfit_items oi ON oi.clothing_id=c.id LEFT JOIN outfit_history h ON h.outfit_id=oi.outfit_id AND h.user_id=c.user_id WHERE c.user_id=:user_id AND c.status<>\'archived\' GROUP BY c.id ORDER BY wears DESC,c.name LIMIT 8');$stmt->execute(['user_id'=>$user['id']]);$wears=$stmt->fetchAll();$stmt=$this->db->prepare('SELECT COUNT(*) FROM outfits WHERE user_id=:user_id AND status=\'active\'');$stmt->execute(['user_id'=>$user['id']]);$outfitCount=(int)$stmt->fetchColumn();
        render('statistics/index',['title'=>'Statistiques','labels'=>$labels,'statuses'=>$statusMap,'colors'=>$colors,'styles'=>$styles,'spend'=>$spend,'spendTrend'=>$spendTrend,'wears'=>$wears,'outfitCount'=>$outfitCount]);
    }

    public function notifications(): void
    {
        $user=$this->requireAuth();$this->syncNotifications((int)$user['id']);$stmt=$this->db->prepare('SELECT * FROM notifications WHERE user_id=:user_id ORDER BY read_at IS NULL DESC,created_at DESC LIMIT 100');$stmt->execute(['user_id'=>$user['id']]);render('notifications/index',['title'=>'Notifications','notifications'=>$stmt->fetchAll()]);
    }
    private function syncNotifications(int $userId): void
    {
        $this->db->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id) SELECT ce.user_id,'calendar','Tenue prévue aujourd’hui',CONCAT('Une tenue est programmée pour ',o.name),'calendar',ce.id FROM calendar_events ce JOIN outfits o ON o.id=ce.outfit_id AND o.user_id=ce.user_id WHERE ce.user_id=:user_id AND ce.event_type='planned' AND ce.event_date=CURDATE() AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=ce.user_id AND n.related_type='calendar' AND n.related_id=ce.id)")->execute(['user_id'=>$userId]);
        $this->db->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id) SELECT ce.user_id,'calendar-conflict','Pièce indisponible','Une tenue planifiée contient au moins une pièce actuellement indisponible.','calendar-conflict',ce.id FROM calendar_events ce JOIN outfit_items oi ON oi.outfit_id=ce.outfit_id JOIN clothes c ON c.id=oi.clothing_id AND c.user_id=ce.user_id WHERE ce.user_id=:user_id AND ce.event_type='planned' AND ce.event_date>=CURDATE() AND c.status NOT IN ('available','archived') AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=ce.user_id AND n.related_type='calendar-conflict' AND n.related_id=ce.id) GROUP BY ce.id,ce.user_id")->execute(['user_id'=>$userId]);
        $this->db->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id) SELECT c.user_id,'laundry','Vêtement indisponible',CONCAT(c.name,' est encore au lavage.'),'laundry',c.id FROM clothes c WHERE c.user_id=:user_id AND c.status='laundry' AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=c.user_id AND n.related_type='laundry' AND n.related_id=c.id)")->execute(['user_id'=>$userId]);
        $this->db->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id) SELECT t.user_id,'trip','Voyage bientôt',CONCAT(t.name,' commence le ',DATE_FORMAT(t.start_date,'%d/%m/%Y')),'trip',t.id FROM trips t WHERE t.user_id=:user_id AND t.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=t.user_id AND n.related_type='trip' AND n.related_id=t.id)")->execute(['user_id'=>$userId]);
        $this->db->prepare("INSERT INTO notifications(user_id,type,title,body,related_type,related_id) SELECT b.user_id,'budget','Budget dépassé',CONCAT('Le budget ',b.name,' dépasse son montant prévu.'),'budget',b.id FROM budgets b JOIN purchases p ON p.user_id=b.user_id AND p.currency=b.currency AND p.purchased_at BETWEEN b.period_start AND b.period_end WHERE b.user_id=:user_id AND b.period_start<=CURDATE() AND b.period_end>=CURDATE() GROUP BY b.id,b.user_id,b.name,b.amount HAVING SUM(p.amount)>b.amount AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.user_id=b.user_id AND n.related_type='budget' AND n.related_id=b.id)")->execute(['user_id'=>$userId]);
    }
    public function readNotification(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$this->db->prepare('UPDATE notifications SET read_at=NOW() WHERE id=:id AND user_id=:user_id')->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);redirect('notifications');
    }

    public function profile(): void
    {
        $user=$this->requireAuth();$errors=[];if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$name=trim((string)($_POST['full_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$theme=(string)($_POST['theme']??'dark');if($name===''||mb_strlen($name)>120)$errors[]='Le nom est obligatoire (120 caractères maximum).';if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Adresse e-mail invalide.';if(!in_array($theme,['dark','light'],true))$theme='dark';$check=$this->db->prepare('SELECT id FROM users WHERE email=:email AND id<>:id');$check->execute(['email'=>$email,'id'=>$user['id']]);if($check->fetch())$errors[]='Cette adresse e-mail est déjà utilisée.';
            $current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');if($new!==''&&(!password_verify($current,$this->passwordHash((int)$user['id'])??'')||strlen($new)<10))$errors[]='Pour changer le mot de passe, saisissez votre mot de passe actuel et un nouveau mot de passe de 10 caractères minimum.';
            $avatarPath=null;$photo=$_FILES['avatar']??[];if(!$errors&&($photo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$avatarPath=$this->storeAvatar($photo,$errors);
            if(!$errors){$this->db->beginTransaction();try{$sql='UPDATE users SET full_name=:name,email=:email,theme=:theme';$params=['name'=>$name,'email'=>$email,'theme'=>$theme,'id'=>$user['id']];if($new!==''){$sql.=',password_hash=:password';$params['password']=password_hash($new,PASSWORD_DEFAULT);}if($avatarPath!==null){$sql.=',avatar_path=:avatar';$params['avatar']=$avatarPath;}$sql.=' WHERE id=:id';$this->db->prepare($sql)->execute($params);$currency=strtoupper(substr((string)($_POST['currency']??'USD'),0,3));if(!preg_match('/^[A-Z]{3}$/',$currency))$currency='USD';$this->db->prepare('UPDATE user_preferences SET currency=:currency WHERE user_id=:user_id')->execute(['currency'=>$currency,'user_id'=>$user['id']]);$this->db->commit();$_SESSION['user']['full_name']=$name;$_SESSION['user']['email']=$email;$_SESSION['user']['theme']=$theme;if($avatarPath!==null)$_SESSION['user']['avatar_path']=$avatarPath;flash('success','Profil et préférences enregistrés.');redirect('profile');}catch(Throwable $e){$this->db->rollBack();throw $e;}}
        }
        $prefs=$this->db->prepare('SELECT currency FROM user_preferences WHERE user_id=:user_id');$prefs->execute(['user_id'=>$user['id']]);render('profile/index',['title'=>'Mon profil','user'=>$user,'currency'=>$prefs->fetchColumn()?:'USD','errors'=>$errors]);
    }
    private function passwordHash(int $userId): ?string{$stmt=$this->db->prepare('SELECT password_hash FROM users WHERE id=:id');$stmt->execute(['id'=>$userId]);$value=$stmt->fetchColumn();return $value?:null;}

    private function storeAvatar(array $file,array &$errors): ?string
    {
        if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024){$errors[]='La photo de profil doit faire 5 Mo maximum.';return null;}$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext||@getimagesize($file['tmp_name'])===false){$errors[]='Format de photo invalide. Utilisez JPG, PNG ou WEBP.';return null;}$dir=APP_ROOT.'/storage/uploads/profiles';if(!is_dir($dir))mkdir($dir,0755,true);$name=bin2hex(random_bytes(20)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)){$errors[]='La photo n’a pas pu être enregistrée.';return null;}return 'storage/uploads/profiles/'.$name;
    }

    public function deleteAccount(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();if((string)($_POST['confirmation']??'')!=='SUPPRIMER'||!password_verify((string)($_POST['password']??''),$this->passwordHash((int)$user['id'])??'')){flash('error','La confirmation ou le mot de passe est incorrect.');redirect('profile');}
        $this->db->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$user['id']]);$_SESSION=[];session_destroy();redirect('register');
    }

    public function settings(): void
    {
        $user=$this->requireAuth();
        $labels=$this->db->query('SELECT id,name,sort_order,is_active FROM clothing_labels ORDER BY sort_order,name')->fetchAll();
        $stmt=$this->db->prepare('SELECT c.*,l.name AS label_name,(c.user_id IS NULL) AS is_default FROM categories c JOIN clothing_labels l ON l.id=c.label_id WHERE c.user_id IS NULL OR c.user_id=:user_id ORDER BY l.sort_order,c.name');$stmt->execute(['user_id'=>$user['id']]);$categories=$stmt->fetchAll();
        $stmt=$this->db->prepare('SELECT s.id,s.name,s.category_id,s.is_active,c.name AS category_name,c.label_id,c.user_id FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE c.user_id IS NULL OR c.user_id=:user_id ORDER BY c.name,s.name');$stmt->execute(['user_id'=>$user['id']]);
        render('settings/index',['title'=>'Paramètres','labels'=>$labels,'activeLabels'=>array_values(array_filter($labels,static fn($label)=>(int)$label['is_active']===1)),'categories'=>$categories,'subcategories'=>$stmt->fetchAll()]);
    }

    public function saveCategory(): void { $this->saveCategoryRecord(); }
    public function editCategory(): void { $this->saveCategoryRecord(); }

    private function saveCategoryRecord(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$label=(int)($_POST['label_id']??0);$name=trim((string)($_POST['name']??''));
        if(mb_strlen($name)<2||mb_strlen($name)>80){flash('error','La catégorie doit contenir de 2 à 80 caractères.');redirect('settings');}
        $check=$this->db->prepare('SELECT id FROM clothing_labels WHERE id=:id AND is_active=1');$check->execute(['id'=>$label]);if(!$check->fetch()){flash('error','Libellé invalide ou inactif.');redirect('settings');}
        if($id){$owned=$this->db->prepare('SELECT id FROM categories WHERE id=:id AND (user_id IS NULL OR user_id=:user_id)');$owned->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$owned->fetch()){http_response_code(404);return;}}
        $duplicate=$this->db->prepare('SELECT id FROM categories WHERE label_id=:label AND name=:name AND id<>:id');$duplicate->execute(['label'=>$label,'name'=>$name,'id'=>$id]);if($duplicate->fetch()){flash('error','Cette catégorie existe déjà sous ce libellé.');redirect('settings');}
        if($id){$this->db->prepare('UPDATE categories SET label_id=:label,name=:name WHERE id=:id AND (user_id IS NULL OR user_id=:user_id)')->execute(['label'=>$label,'name'=>$name,'id'=>$id,'user_id'=>$user['id']]);flash('success','Catégorie modifiée.');}
        else{$this->db->prepare('INSERT INTO categories(label_id,user_id,name) VALUES(:label,:user_id,:name)')->execute(['label'=>$label,'user_id'=>$user['id'],'name'=>$name]);flash('success','Catégorie ajoutée.');}redirect('settings');
    }

    public function categoryStatus(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$active=(int)($_POST['is_active']??0);if(!in_array($active,[0,1],true))$active=0;
        if($active===0){$id=(int)($_POST['id']??0);$usage=$this->db->prepare('SELECT COUNT(*) FROM clothes c LEFT JOIN subcategories s ON s.id=c.subcategory_id WHERE (c.category_id=:id OR s.category_id=:id2) AND (c.user_id=:user_id OR EXISTS(SELECT 1 FROM categories x WHERE x.id=:category_id AND x.user_id IS NULL))');$usage->execute(['id'=>$id,'id2'=>$id,'user_id'=>$user['id'],'category_id'=>$id]);if((int)$usage->fetchColumn()>0){flash('error','Cette catégorie est utilisée par des pièces. Reclassez-les avant de la désactiver.');redirect('settings');}}
        $this->db->prepare('UPDATE categories SET is_active=:active WHERE id=:id AND (user_id IS NULL OR user_id=:user_id)')->execute(['active'=>$active,'id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);flash('success',$active?'Catégorie activée.':'Catégorie désactivée.');redirect('settings');
    }

    public function deleteCategory(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$check=$this->db->prepare('SELECT id,user_id FROM categories WHERE id=:id AND (user_id IS NULL OR user_id=:user_id)');$check->execute(['id'=>$id,'user_id'=>$user['id']]);$category=$check->fetch();if(!$category){http_response_code(404);return;}
        if($category['user_id']===null){$usage=$this->db->prepare('SELECT COUNT(*) FROM clothes c LEFT JOIN subcategories s ON s.id=c.subcategory_id WHERE c.category_id=:id OR s.category_id=:id2');$usage->execute(['id'=>$id,'id2'=>$id]);}
        else{$usage=$this->db->prepare('SELECT COUNT(*) FROM clothes c LEFT JOIN subcategories s ON s.id=c.subcategory_id WHERE c.user_id=:user_id AND (c.category_id=:id OR s.category_id=:id2)');$usage->execute(['user_id'=>$user['id'],'id'=>$id,'id2'=>$id]);}
        if((int)$usage->fetchColumn()>0){flash('error','Cette catégorie est encore utilisée par des pièces. Désactivez-la pour conserver leur classement.');redirect('settings');}
        $this->db->beginTransaction();try{$this->db->prepare('DELETE FROM subcategories WHERE category_id=:id')->execute(['id'=>$id]);$this->db->prepare('DELETE FROM categories WHERE id=:id AND (user_id IS NULL OR user_id=:user_id)')->execute(['id'=>$id,'user_id'=>$user['id']]);$this->db->commit();flash('success','Catégorie supprimée.');}catch(Throwable $exception){$this->db->rollBack();throw $exception;}redirect('settings');
    }

    public function saveSubcategory(): void { $this->saveSubcategoryRecord(); }
    public function editSubcategory(): void { $this->saveSubcategoryRecord(); }

    private function saveSubcategoryRecord(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$category=(int)($_POST['category_id']??0);$name=trim((string)($_POST['name']??''));
        $check=$this->db->prepare('SELECT id FROM categories WHERE id=:id AND is_active=1 AND (user_id IS NULL OR user_id=:user_id)');$check->execute(['id'=>$category,'user_id'=>$user['id']]);if(!$check->fetch()||mb_strlen($name)<2||mb_strlen($name)>80){flash('error','Catégorie ou nom de sous-catégorie invalide.');redirect('settings');}
        if($id){$owned=$this->db->prepare('SELECT s.id FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE s.id=:id AND (c.user_id IS NULL OR c.user_id=:user_id)');$owned->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$owned->fetch()){http_response_code(404);return;}}
        $duplicate=$this->db->prepare('SELECT id FROM subcategories WHERE category_id=:category AND name=:name AND id<>:id');$duplicate->execute(['category'=>$category,'name'=>$name,'id'=>$id]);if($duplicate->fetch()){flash('error','Cette sous-catégorie existe déjà.');redirect('settings');}
        if($id){$this->db->prepare('UPDATE subcategories SET category_id=:category,name=:name WHERE id=:id')->execute(['category'=>$category,'name'=>$name,'id'=>$id]);flash('success','Sous-catégorie modifiée.');}
        else{$this->db->prepare('INSERT INTO subcategories(category_id,name) VALUES(:category,:name)')->execute(['category'=>$category,'name'=>$name]);flash('success','Sous-catégorie ajoutée.');}redirect('settings');
    }

    public function deleteSubcategory(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$stmt=$this->db->prepare('DELETE s FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE s.id=:id AND (c.user_id IS NULL OR c.user_id=:user_id)');$stmt->execute(['id'=>(int)($_POST['id']??0),'user_id'=>$user['id']]);if(!$stmt->rowCount())flash('error','Sous-catégorie introuvable.');else flash('success','Sous-catégorie supprimée.');redirect('settings');
    }

    public function saveLabel(): void
    {
        $this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));if(mb_strlen($name)<2||mb_strlen($name)>40){flash('error','Le libellé doit contenir de 2 à 40 caractères.');redirect('settings');}
        $duplicate=$this->db->prepare('SELECT id FROM clothing_labels WHERE name=:name AND id<>:id');$duplicate->execute(['name'=>$name,'id'=>$id]);if($duplicate->fetch()){flash('error','Ce libellé principal existe déjà.');redirect('settings');}
        if($id){$this->db->prepare('UPDATE clothing_labels SET name=:name WHERE id=:id')->execute(['name'=>$name,'id'=>$id]);flash('success','Libellé principal modifié.');}
        else{$next=(int)$this->db->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM clothing_labels')->fetchColumn();$this->db->prepare('INSERT INTO clothing_labels(name,sort_order,is_active) VALUES(:name,:sort_order,1)')->execute(['name'=>$name,'sort_order'=>$next]);flash('success','Libellé principal ajouté.');}redirect('settings');
    }

    public function deleteLabel(): void
    {
        $this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);
        $usage=$this->db->prepare('SELECT (SELECT COUNT(*) FROM clothes WHERE label_id=:clothes) + (SELECT COUNT(*) FROM categories WHERE label_id=:categories) + (SELECT COUNT(*) FROM wishlist_items WHERE label_id=:wishlist)');$usage->execute(['clothes'=>$id,'categories'=>$id,'wishlist'=>$id]);if((int)$usage->fetchColumn()>0){flash('error','Ce libellé est encore utilisé par des vêtements, envies ou catégories. Déplacez-les avant de le supprimer.');redirect('settings');}
        $this->db->prepare('DELETE FROM clothing_labels WHERE id=:id')->execute(['id'=>$id]);flash('success','Libellé principal supprimé.');redirect('settings');
    }
}
