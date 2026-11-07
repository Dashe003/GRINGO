<?php
declare(strict_types=1);

final class BackupController extends Controller
{
    private const TABLES=['user_preferences','categories','subcategories','brands','styles','occasions','clothes','clothing_images','clothing_colors','clothing_favorites','outfits','outfit_items','outfit_favorites','outfit_history','calendar_events','collections','collection_items','laundry_history','wishlist_items','purchases','budgets','budget_entries','trips','trip_items','trip_outfits','exchange_rates','notifications'];
    private const COLUMNS=[
      'user_preferences'=>['user_id','currency','language','units','preferences_json'],
      'categories'=>['id','label_id','user_id','name','is_active','created_at'],
      'subcategories'=>['id','category_id','name','is_active'],
      'brands'=>['id','user_id','name'],'styles'=>['id','user_id','name'],'occasions'=>['id','user_id','name'],
      'clothes'=>['id','user_id','label_id','category_id','subcategory_id','name','description','image_path','color','brand','size','material','purchase_price','currency','purchase_date','seller','season','style','formality','rating','occasion','status','last_washed_at','notes','created_at','updated_at'],
      'clothing_images'=>['id','user_id','clothing_id','image_path','is_primary','created_at'],'clothing_colors'=>['clothing_id','color_id'],'clothing_favorites'=>['user_id','clothing_id','created_at'],
      'outfits'=>['id','user_id','name','description','cover_path','style','occasion','season','notes','rating','wear_count','last_worn_at','status','created_at','updated_at'],
      'outfit_items'=>['outfit_id','clothing_id','position'],'outfit_favorites'=>['user_id','outfit_id','created_at'],'outfit_history'=>['id','user_id','outfit_id','worn_at','note','created_at'],
      'calendar_events'=>['id','user_id','outfit_id','event_date','event_type','note','created_at'],
      'collections'=>['id','user_id','name','description','created_at'],'collection_items'=>['collection_id','clothing_id'],
      'laundry_history'=>['id','user_id','clothing_id','started_at','completed_at','note'],
      'wishlist_items'=>['id','user_id','name','label_id','photo_path','brand','seller','url','estimated_price','currency','priority','notes','status','created_at'],
      'purchases'=>['id','user_id','clothing_id','item_name','amount','currency','purchased_at','seller','notes','created_at'],
      'budgets'=>['id','user_id','name','amount','currency','period_start','period_end','created_at'],
      'budget_entries'=>['id','user_id','budget_id','purchase_id','amount','currency','entry_date','note'],
      'trips'=>['id','user_id','name','destination','description','start_date','end_date','notes','created_at'],
      'trip_items'=>['trip_id','clothing_id','quantity','packed'],'trip_outfits'=>['trip_id','outfit_id'],
      'exchange_rates'=>['id','user_id','from_currency','to_currency','rate','effective_date','created_at'],
      'notifications'=>['id','user_id','type','title','body','related_type','related_id','read_at','created_at']
    ];

    public function index(): void
    {
        $user=$this->requireAuth();$dir=$this->backupDir((int)$user['id']);$files=[];foreach(glob($dir.'/*.tar.gz')?:[] as $file)$files[]=['name'=>basename($file),'date'=>date('d.m.Y H:i',filemtime($file)),'size'=>filesize($file)];render('backup/index',['title'=>'Sauvegarde et restauration','files'=>$files,'zipAvailable'=>class_exists(PharData::class)]);
    }
    public function create(): void
    {
        $this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();try{$name=$this->export();flash('success','Sauvegarde créée. Téléchargez-la depuis la liste ci-dessous.');}catch(Throwable $e){flash('error','La sauvegarde a échoué : '.$e->getMessage());}redirect('backup');
    }
    public function download(): void
    {
        $user=$this->requireAuth();$name=basename((string)($_GET['file']??''));if(!preg_match('/^backup-[a-f0-9-]+\.tar\.gz$/',$name)){http_response_code(404);return;}$path=$this->backupDir((int)$user['id']).DIRECTORY_SEPARATOR.$name;if(!is_file($path)){http_response_code(404);return;}header('Content-Type: application/gzip');header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.$name.'"');readfile($path);exit;
    }
    public function restore(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$file=$_FILES['backup']??[];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>50*1024*1024){flash('error','Sélectionnez une archive valide de 50 Mo maximum.');redirect('backup');}
        if(!class_exists(PharData::class)){flash('error','PharData est nécessaire pour lire les archives TAR.GZ.');redirect('backup');}
        $work=$this->backupDir((int)$user['id']).DIRECTORY_SEPARATOR.'restore-'.bin2hex(random_bytes(8));if(!mkdir($work,0700,true)){flash('error','Le dossier temporaire de restauration ne peut pas être créé.');redirect('backup');}$tar=$work.DIRECTORY_SEPARATOR.'restore.tar.gz';
        try{if(!move_uploaded_file($file['tmp_name'],$tar))throw new RuntimeException('Impossible de recevoir le fichier.');$archive=new PharData($tar);if(!isset($archive['snapshot.json']))throw new RuntimeException('Archive GRINGO invalide : instantané manquant.');$snapshot=json_decode($archive['snapshot.json']->getContent(),true,512,JSON_THROW_ON_ERROR);$this->validateSnapshot($snapshot,(int)$user['id']);$this->restoreFiles($archive,$work);$this->restoreRows($snapshot,(int)$user['id']);flash('success','Les données de votre sauvegarde ont été restaurées.');
        }catch(Throwable $e){flash('error','Restauration refusée : '.$e->getMessage());}finally{$this->removeTree($work);}redirect('backup');
    }

    private function backupDir(int $userId): string{$dir=APP_ROOT.'/storage/backups/'.$userId;if(!is_dir($dir))mkdir($dir,0700,true);return $dir;}
    private function snapshot(int $userId): array
    {
        $snapshot=['format'=>'gringo-backup-v1','exported_at'=>date(DATE_ATOM),'user_id'=>$userId,'user'=>null,'tables'=>[]];$stmt=$this->db->prepare('SELECT id,full_name,username,email,avatar_path,theme,created_at,updated_at FROM users WHERE id=:id');$stmt->execute(['id'=>$userId]);$snapshot['user']=$stmt->fetch();
        $queries=[
          'user_preferences'=>'SELECT * FROM user_preferences WHERE user_id=?','categories'=>'SELECT * FROM categories WHERE user_id=?','subcategories'=>'SELECT s.* FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE c.user_id=?',
          'brands'=>'SELECT * FROM brands WHERE user_id=?','styles'=>'SELECT * FROM styles WHERE user_id=?','occasions'=>'SELECT * FROM occasions WHERE user_id=?','clothes'=>'SELECT * FROM clothes WHERE user_id=?',
          'clothing_images'=>'SELECT * FROM clothing_images WHERE user_id=?','clothing_colors'=>'SELECT cc.* FROM clothing_colors cc JOIN clothes c ON c.id=cc.clothing_id WHERE c.user_id=?','clothing_favorites'=>'SELECT * FROM clothing_favorites WHERE user_id=?',
          'outfits'=>'SELECT * FROM outfits WHERE user_id=?','outfit_items'=>'SELECT oi.* FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id WHERE o.user_id=?','outfit_favorites'=>'SELECT * FROM outfit_favorites WHERE user_id=?','outfit_history'=>'SELECT * FROM outfit_history WHERE user_id=?','calendar_events'=>'SELECT * FROM calendar_events WHERE user_id=?',
          'collections'=>'SELECT * FROM collections WHERE user_id=?','collection_items'=>'SELECT ci.* FROM collection_items ci JOIN collections c ON c.id=ci.collection_id WHERE c.user_id=?','laundry_history'=>'SELECT * FROM laundry_history WHERE user_id=?','wishlist_items'=>'SELECT * FROM wishlist_items WHERE user_id=?','purchases'=>'SELECT * FROM purchases WHERE user_id=?','budgets'=>'SELECT * FROM budgets WHERE user_id=?','budget_entries'=>'SELECT * FROM budget_entries WHERE user_id=?','trips'=>'SELECT * FROM trips WHERE user_id=?','trip_items'=>'SELECT ti.* FROM trip_items ti JOIN trips t ON t.id=ti.trip_id WHERE t.user_id=?','trip_outfits'=>'SELECT x.* FROM trip_outfits x JOIN trips t ON t.id=x.trip_id WHERE t.user_id=?','exchange_rates'=>'SELECT * FROM exchange_rates WHERE user_id=?','notifications'=>'SELECT * FROM notifications WHERE user_id=?'
        ];
        foreach($queries as $table=>$sql){$stmt=$this->db->prepare($sql);$stmt->execute([$userId]);$snapshot['tables'][$table]=$stmt->fetchAll();}
        return $snapshot;
    }
    private function export(): string
    {
        if(!class_exists(PharData::class))throw new RuntimeException('PharData n’est pas activé dans PHP.');$user=$this->requireAuth();$userId=(int)$user['id'];$dir=$this->backupDir($userId);$name='backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.tar.gz';$tarPath=$dir.DIRECTORY_SEPARATOR.substr($name,0,-3);$snapshot=$this->snapshot($userId);$archive=new PharData($tarPath);$archive['snapshot.json']=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $paths=[];$userRow=$snapshot['user'];if(!empty($userRow['avatar_path']))$paths[]=$userRow['avatar_path'];foreach($snapshot['tables']['clothes'] as $row)if($row['image_path'])$paths[]=$row['image_path'];foreach($snapshot['tables']['clothing_images'] as $row)$paths[]=$row['image_path'];foreach($snapshot['tables']['outfits'] as $row)if($row['cover_path'])$paths[]=$row['cover_path'];foreach($snapshot['tables']['wishlist_items'] as $row)if($row['photo_path'])$paths[]=$row['photo_path'];
        foreach(array_unique($paths) as $relative){if(!preg_match('#^storage/uploads/(?:clothes|wishlist|profiles|outfits)/[a-zA-Z0-9._-]+$#',$relative))continue;$absolute=APP_ROOT.'/'.$relative;if(is_file($absolute))$archive->addFile($absolute,'files/'.$relative);}
        $archive->compress(Phar::GZ);unset($archive);@unlink($tarPath);$compressed=$tarPath.'.gz';if(!is_file($compressed))throw new RuntimeException('La compression de la sauvegarde a échoué.');$target=$dir.DIRECTORY_SEPARATOR.$name;if(!rename($compressed,$target))throw new RuntimeException('La sauvegarde ne peut pas être stockée.');return $name;
    }
    private function validateSnapshot(array $s,int $userId): void
    {
        if(($s['format']??'')!=='gringo-backup-v1'||(int)($s['user_id']??0)!==$userId||!is_array($s['tables']??null)||!is_array($s['user']??null)||(int)($s['user']['id']??0)!==$userId)throw new RuntimeException('Cette archive ne correspond pas à votre compte.');foreach(self::TABLES as $table){if(!isset($s['tables'][$table])||!is_array($s['tables'][$table]))throw new RuntimeException('La sauvegarde ne contient pas la table '.$table.'.');foreach($s['tables'][$table] as $row){if(!is_array($row))throw new RuntimeException('Enregistrement mal formé.');if(in_array('user_id',self::COLUMNS[$table],true)&&(int)($row['user_id']??0)!==$userId)throw new RuntimeException('La sauvegarde contient un autre identifiant utilisateur.');}}
    }
    private function restoreFiles(PharData $archive,string $work): void
    {
        foreach(new RecursiveIteratorIterator($archive) as $entry){$path=str_replace('\\','/',$entry->getPathName());$prefix='phar://'.$archive->getPath().'/';$relative=substr($path,strlen($prefix));if(!str_starts_with($relative,'files/'))continue;$relative=substr($relative,6);if(str_contains($relative,'..')||!preg_match('#^storage/uploads/(?:clothes|wishlist|profiles|outfits)/[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp)$#i',$relative))continue;$content=$entry->getContent();if(strlen($content)>5*1024*1024)continue;$tmp=$work.'/image-'.bin2hex(random_bytes(5));file_put_contents($tmp,$content);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||@getimagesize($tmp)===false){@unlink($tmp);continue;}$destination=APP_ROOT.'/'.$relative;$directory=dirname($destination);if(!is_dir($directory))mkdir($directory,0755,true);rename($tmp,$destination);}
    }
    private function restoreRows(array $snapshot,int $userId): void
    {
        $deleteSql=[
          'DELETE x FROM trip_outfits x JOIN trips t ON t.id=x.trip_id WHERE t.user_id=:uid','DELETE ti FROM trip_items ti JOIN trips t ON t.id=ti.trip_id WHERE t.user_id=:uid','DELETE FROM trips WHERE user_id=:uid','DELETE FROM budget_entries WHERE user_id=:uid','DELETE FROM purchases WHERE user_id=:uid','DELETE FROM budgets WHERE user_id=:uid','DELETE FROM exchange_rates WHERE user_id=:uid','DELETE FROM wishlist_items WHERE user_id=:uid','DELETE FROM laundry_history WHERE user_id=:uid','DELETE FROM calendar_events WHERE user_id=:uid','DELETE FROM outfit_history WHERE user_id=:uid','DELETE FROM outfit_favorites WHERE user_id=:uid','DELETE ci FROM collection_items ci JOIN collections c ON c.id=ci.collection_id WHERE c.user_id=:uid','DELETE FROM collections WHERE user_id=:uid','DELETE oi FROM outfit_items oi JOIN outfits o ON o.id=oi.outfit_id WHERE o.user_id=:uid','DELETE FROM outfits WHERE user_id=:uid','DELETE FROM clothing_favorites WHERE user_id=:uid','DELETE FROM clothing_images WHERE user_id=:uid','DELETE cc FROM clothing_colors cc JOIN clothes c ON c.id=cc.clothing_id WHERE c.user_id=:uid','DELETE FROM clothes WHERE user_id=:uid','DELETE s FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE c.user_id=:uid','DELETE FROM categories WHERE user_id=:uid','DELETE FROM brands WHERE user_id=:uid','DELETE FROM styles WHERE user_id=:uid','DELETE FROM occasions WHERE user_id=:uid','DELETE FROM notifications WHERE user_id=:uid'];
        $tables=['categories','subcategories','brands','styles','occasions','clothes','clothing_images','clothing_colors','clothing_favorites','outfits','outfit_items','outfit_favorites','outfit_history','calendar_events','collections','collection_items','laundry_history','wishlist_items','purchases','budgets','budget_entries','trips','trip_items','trip_outfits','exchange_rates','notifications'];
        $data=$snapshot['tables'];$ids=[];foreach(['categories','clothes','outfits','collections','trips','budgets','purchases'] as $table)$ids[$table]=array_map('intval',array_column($data[$table],'id'));
        $allowed=[];foreach($tables as $table){$allowed[$table]=[];foreach($data[$table] as $row){if(in_array('user_id',self::COLUMNS[$table],true)&&(int)($row['user_id']??0)!==$userId)continue;if($table==='subcategories'&&!in_array((int)$row['category_id'],$ids['categories'],true))continue;if($table==='clothes'&&isset($row['category_id'])&&$row['category_id']!==null&&!$this->categoryAllowed((int)$row['category_id'],$ids['categories']))continue;if($table==='clothing_images'&&!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true))continue;if($table==='clothing_colors'&&!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true))continue;if($table==='clothing_favorites'&&!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true))continue;if($table==='outfit_items'&&(!in_array((int)$row['outfit_id'],$ids['outfits'],true)||!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true)))continue;if($table==='outfit_favorites'&&!in_array((int)$row['outfit_id'],$ids['outfits'],true))continue;if($table==='outfit_history'&&!in_array((int)$row['outfit_id'],$ids['outfits'],true))continue;if($table==='calendar_events'&&$row['outfit_id']!==null&&!in_array((int)$row['outfit_id'],$ids['outfits'],true))continue;if($table==='collection_items'&&(!in_array((int)$row['collection_id'],$ids['collections'],true)||!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true)))continue;if($table==='laundry_history'&&!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true))continue;if($table==='purchases'&&$row['clothing_id']!==null&&!in_array((int)$row['clothing_id'],array_map('intval',array_column($data['clothes'],'id')),true))continue;if($table==='budget_entries'&&(($row['budget_id']!==null&&!in_array((int)$row['budget_id'],$ids['budgets'],true))||($row['purchase_id']!==null&&!in_array((int)$row['purchase_id'],$ids['purchases'],true))))continue;if($table==='trip_items'&&!in_array((int)$row['trip_id'],$ids['trips'],true))continue;if($table==='trip_outfits'&&(!in_array((int)$row['trip_id'],$ids['trips'],true)||!in_array((int)$row['outfit_id'],$ids['outfits'],true)))continue;$allowed[$table][]=$row;}}
        $this->db->beginTransaction();try{foreach($deleteSql as $sql)$this->db->prepare($sql)->execute(['uid'=>$userId]);$u=$snapshot['user'];$this->db->prepare('UPDATE users SET full_name=:name,email=:email,theme=:theme WHERE id=:id')->execute(['name'=>$u['full_name'],'email'=>$u['email'],'theme'=>$u['theme'],'id'=>$userId]);foreach($tables as $table)foreach($allowed[$table] as $row)$this->insertRow($table,$row);$prefs=$snapshot['tables']['user_preferences'][0]??null;if($prefs){$this->db->prepare('UPDATE user_preferences SET currency=:currency,language=:language,units=:units,preferences_json=:preferences_json WHERE user_id=:user_id')->execute(['currency'=>$prefs['currency'],'language'=>$prefs['language'],'units'=>$prefs['units'],'preferences_json'=>$prefs['preferences_json'],'user_id'=>$userId]);}$this->db->commit();}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    private function categoryAllowed(int $id,array $owned): bool
    {
        if(in_array($id,$owned,true))return true;$stmt=$this->db->prepare('SELECT id FROM categories WHERE id=:id AND user_id IS NULL');$stmt->execute(['id'=>$id]);return (bool)$stmt->fetchColumn();
    }
    private function insertRow(string $table,array $row): void
    {
        $columns=array_values(array_intersect(self::COLUMNS[$table],array_keys($row)));if(!$columns)return;$quoted=implode(',',array_map(static fn($c)=>'`'.$c.'`',$columns));$marks=implode(',',array_map(static fn($c)=>':'.$c,$columns));$params=[];foreach($columns as $column)$params[$column]=$row[$column];$this->db->prepare("INSERT INTO `$table` ($quoted) VALUES ($marks)")->execute($params);
    }
    private function removeTree(string $dir): void
    {
        if(!is_dir($dir))return;foreach(new FilesystemIterator($dir) as $item){if($item->isDir())$this->removeTree($item->getPathname());else @unlink($item->getPathname());}@rmdir($dir);
    }
}
