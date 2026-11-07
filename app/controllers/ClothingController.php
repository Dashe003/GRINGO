<?php
declare(strict_types=1);

final class ClothingController extends Controller
{
    private function labels(): array
    {
        return $this->db->query('SELECT id, name FROM clothing_labels WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
    }

    private function categories(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT id,label_id,name FROM categories WHERE is_active=1 AND (user_id IS NULL OR user_id=:user_id) ORDER BY label_id,name');$stmt->execute(['user_id'=>$userId]);return $stmt->fetchAll();
    }

    private function subcategories(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT s.id,s.category_id,s.name FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE s.is_active=1 AND c.is_active=1 AND (c.user_id IS NULL OR c.user_id=:user_id) ORDER BY s.name');
        $stmt->execute(['user_id'=>$userId]);return $stmt->fetchAll();
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $query = trim((string) ($_GET['q'] ?? ''));
        $label = filter_input(INPUT_GET, 'label', FILTER_VALIDATE_INT) ?: null;
        $status = trim((string)($_GET['status'] ?? ''));
        $sort = (string)($_GET['sort'] ?? 'newest');
        $viewMode=(string)($_GET['view']??'grid');if(!in_array($viewMode,['grid','list'],true))$viewMode='grid';
        $category=(int)($_GET['category']??0);$filters=[];$filterValues=[];
        foreach(['color','brand','size','season','style'] as $filter){$value=trim((string)($_GET[$filter]??''));if($value!==''){$filters[]="c.$filter LIKE :filter_$filter";$filterValues['filter_'.$filter]='%'.$value.'%';}}
        foreach(['min_price'=>'>=','max_price'=>'<='] as $filter=>$operator){$value=trim((string)($_GET[$filter]??''));if($value!==''&&is_numeric($value)&&(float)$value>=0){$filters[]='c.purchase_price '.$operator.' :'.$filter;$filterValues[$filter]=$value;}}
        if(isset($filterValues['min_price'])||isset($filterValues['max_price'])){$priceCurrency=strtoupper(trim((string)($_GET['price_currency']??'')));if(preg_match('/^[A-Z]{3}$/',$priceCurrency)){$filters[]='c.currency=:price_currency';$filterValues['price_currency']=$priceCurrency;}}
        $sorts=['newest'=>'c.created_at DESC','oldest'=>'c.created_at ASC','name'=>'c.name ASC','brand'=>'c.brand ASC,c.name ASC'];
        $currentPage = max(1, (int) ($_GET['p'] ?? 1));
        $sql = 'SELECT c.id, c.name, c.image_path, c.color, c.brand, c.size, c.status, c.rating, l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id = c.label_id WHERE c.user_id = :user_id';
        $params = ['user_id' => $user['id']];
        if ($query !== '') { $sql .= ' AND (c.name LIKE :q_name OR c.brand LIKE :q_brand OR c.color LIKE :q_color)'; $params['q_name'] = $params['q_brand'] = $params['q_color'] = '%' . $query . '%'; }
        if ($label) { $sql .= ' AND c.label_id = :label'; $params['label'] = $label; }
        if ($status !== '' && in_array($status,['available','laundry','repair','unavailable','archived'],true)) { $sql .= ' AND c.status = :status'; $params['status']=$status; }
        if($category>0){$sql.=' AND c.category_id=:category';$params['category']=$category;}
        if($filters){$sql.=' AND '.implode(' AND ',$filters);$params=array_merge($params,$filterValues);}
        $countSql = preg_replace('/^SELECT .*? FROM clothes/s', 'SELECT COUNT(*) FROM clothes', $sql);
        $countSql = str_replace(' JOIN clothing_labels l ON l.id = c.label_id', '', $countSql);
        $count = $this->db->prepare($countSql);
        $count->execute($params);
        $totalItems = (int) $count->fetchColumn();
        $perPage = 20;
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $offset = ($currentPage - 1) * $perPage;
        $sql .= ' ORDER BY ' . ($sorts[$sort]??$sorts['newest']) . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $catalogFilters=['category'=>$category];foreach(['color','brand','size','season','style','min_price','max_price','price_currency'] as $filter)$catalogFilters[$filter]=trim((string)($_GET[$filter]??''));
        render('clothes/index', ['title' => 'Ma garde-robe', 'clothes' => $stmt->fetchAll(), 'labels' => $this->labels(), 'categories'=>$this->categories((int)$user['id']),'query' => $query, 'selectedLabel' => $label, 'selectedStatus'=>$status,'sort'=>$sort,'viewMode'=>$viewMode,'filterValues'=>$catalogFilters,'currentPage' => $currentPage, 'totalPages' => $totalPages, 'totalItems' => $totalItems]);
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $errors = [];
        $values = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $values = $this->validate($_POST, $errors);
            $image = $this->storeImage($_FILES['photo'] ?? [], $errors);
            $gallery=$this->storeGallery($_FILES['gallery']??[],$errors);
            if (!$errors) {
                $stmt = $this->db->prepare('INSERT INTO clothes (user_id, label_id, category_id, subcategory_id, name, description, image_path, color, brand, size, purchase_price, currency, season, style, formality, occasion, status, notes) VALUES (:user_id, :label_id, :category_id, :subcategory_id, :name, :description, :image, :color, :brand, :size, :price, :currency, :season, :style, :formality, :occasion, :status, :notes)');
                $stmt->execute(['user_id' => $user['id'], 'label_id' => $values['label_id'], 'category_id'=>$values['category_id'],'subcategory_id'=>$values['subcategory_id'], 'name' => $values['name'], 'description' => $values['description'], 'image' => $image, 'color' => $values['color'], 'brand' => $values['brand'], 'size' => $values['size'], 'price' => $values['purchase_price'], 'currency' => $values['currency'], 'season' => $values['season'], 'style' => $values['style'], 'formality'=>$values['formality'], 'occasion' => $values['occasion'], 'status' => $values['status'], 'notes' => $values['notes']]);
                $clothingId=(int)$this->db->lastInsertId();$insertImage=$this->db->prepare('INSERT INTO clothing_images(user_id,clothing_id,image_path,is_primary) VALUES(:user_id,:clothing_id,:path,0)');foreach($gallery as $path)$insertImage->execute(['user_id'=>$user['id'],'clothing_id'=>$clothingId,'path'=>$path]);
                flash('success', 'Le vêtement a été ajouté à votre garde-robe.');
                redirect('clothes');
            }
        }
        render('clothes/form', ['title' => 'Ajouter un vêtement', 'labels' => $this->labels(), 'categories'=>$this->categories((int)$user['id']),'subcategories'=>$this->subcategories((int)$user['id']), 'errors' => $errors, 'values' => $values, 'editing' => false]);
    }

    public function show(): void
    {
        $user = $this->requireAuth();
        $item = $this->findOwned($user['id'], (int) ($_GET['id'] ?? 0));
        if (!$item) { http_response_code(404); render('errors/404', ['title' => 'Vêtement introuvable']); return; }
        $gallery=$this->db->prepare('SELECT id,image_path FROM clothing_images WHERE user_id=:user_id AND clothing_id=:clothing_id AND is_primary=0 ORDER BY id');$gallery->execute(['user_id'=>$user['id'],'clothing_id'=>$item['id']]);
        render('clothes/show', ['title' => $item['name'], 'item' => $item,'gallery'=>$gallery->fetchAll()]);
    }

    public function edit(): void
    {
        $user = $this->requireAuth();
        $id = (int) ($_GET['id'] ?? 0);
        $item = $this->findOwned($user['id'], $id);
        if (!$item) { http_response_code(404); render('errors/404', ['title' => 'Vêtement introuvable']); return; }
        $errors = [];
        $values = $item;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $values = $this->validate($_POST, $errors);
            $image = $item['image_path'];
            if (!empty($_FILES['photo']['name'])) { $image = $this->storeImage($_FILES['photo'], $errors); }
            $gallery=$this->storeGallery($_FILES['gallery']??[],$errors);
            if (!$errors) {
                $stmt = $this->db->prepare('UPDATE clothes SET label_id=:label_id, category_id=:category_id, subcategory_id=:subcategory_id, name=:name, description=:description, image_path=:image, color=:color, brand=:brand, size=:size, purchase_price=:price, currency=:currency, season=:season, style=:style, formality=:formality, occasion=:occasion, status=:status, notes=:notes WHERE id=:id AND user_id=:user_id');
                $stmt->execute(['label_id'=>$values['label_id'],'category_id'=>$values['category_id'],'subcategory_id'=>$values['subcategory_id'],'name'=>$values['name'],'description'=>$values['description'],'image'=>$image,'color'=>$values['color'],'brand'=>$values['brand'],'size'=>$values['size'],'price'=>$values['purchase_price'],'currency'=>$values['currency'],'season'=>$values['season'],'style'=>$values['style'],'formality'=>$values['formality'],'occasion'=>$values['occasion'],'status'=>$values['status'],'notes'=>$values['notes'],'id'=>$id,'user_id'=>$user['id']]);
                $insertImage=$this->db->prepare('INSERT INTO clothing_images(user_id,clothing_id,image_path,is_primary) VALUES(:user_id,:clothing_id,:path,0)');foreach($gallery as $path)$insertImage->execute(['user_id'=>$user['id'],'clothing_id'=>$id,'path'=>$path]);
                flash('success', 'Les modifications ont été enregistrées.');
                redirect('clothes-show', ['id' => $id]);
            }
        }
        render('clothes/form', ['title' => 'Modifier un vêtement', 'labels' => $this->labels(), 'categories'=>$this->categories((int)$user['id']),'subcategories'=>$this->subcategories((int)$user['id']), 'errors' => $errors, 'values' => $values, 'editing' => true, 'id' => $id]);
    }

    public function delete(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        verify_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $this->db->prepare('UPDATE clothes SET status = \'archived\' WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $user['id']]);
        flash('success', 'Le vêtement a été archivé.');
        redirect('clothes');
    }

    public function favorite(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);
        $stmt=$this->db->prepare('SELECT id FROM clothes WHERE id=:id AND user_id=:user_id AND status<>\'archived\'');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);if(!$stmt->fetch()){http_response_code(404);return;}
        $stmt=$this->db->prepare('SELECT 1 FROM clothing_favorites WHERE user_id=:user_id AND clothing_id=:id');$stmt->execute(['user_id'=>$user['id'],'id'=>$id]);$isFavorite=(bool)$stmt->fetchColumn();
        if($isFavorite)$this->db->prepare('DELETE FROM clothing_favorites WHERE user_id=:user_id AND clothing_id=:id')->execute(['user_id'=>$user['id'],'id'=>$id]);else $this->db->prepare('INSERT INTO clothing_favorites(user_id,clothing_id) VALUES(:user_id,:id)')->execute(['user_id'=>$user['id'],'id'=>$id]);
        flash('success',$isFavorite?'Vêtement retiré des favoris.':'Vêtement ajouté aux favoris.');redirect('clothes-show',['id'=>$id]);
    }

    public function rate(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();
        $id=(int)($_POST['id']??0);$item=$this->findOwned((int)$user['id'],$id);if(!$item){http_response_code(404);return;}
        $raw=(string)($_POST['rating']??'0');$rating=$raw==='0'||$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT);
        if($rating!==null&&($rating===false||$rating<1||$rating>5)){flash('error','La note doit être comprise entre 1 et 5 étoiles.');redirect('clothes-show',['id'=>$id]);}
        $this->db->prepare('UPDATE clothes SET rating=:rating WHERE id=:id AND user_id=:user_id')->execute(['rating'=>$rating,'id'=>$id,'user_id'=>$user['id']]);
        flash('success',$rating===null?'Note retirée.':'Note enregistrée.');redirect('clothes-show',['id'=>$id]);
    }

    public function restore(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);
        $stmt=$this->db->prepare("UPDATE clothes SET status='available' WHERE id=:id AND user_id=:user_id AND status='archived'");$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);
        flash($stmt->rowCount()?'success':'error',$stmt->rowCount()?'La piece a ete restauree.':'Cette piece archivee est introuvable.');redirect($stmt->rowCount()?'clothes-show':'clothes',$stmt->rowCount()?['id'=>$id]:[]);
    }

    public function deleteGalleryPhoto(): void
    {
        $user=$this->requireAuth();if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);return;}verify_csrf();$id=(int)($_POST['id']??0);$stmt=$this->db->prepare('SELECT image_path FROM clothing_images WHERE id=:id AND user_id=:user_id AND is_primary=0');$stmt->execute(['id'=>$id,'user_id'=>$user['id']]);$path=$stmt->fetchColumn();if($path){$this->db->prepare('DELETE FROM clothing_images WHERE id=:id AND user_id=:user_id AND is_primary=0')->execute(['id'=>$id,'user_id'=>$user['id']]);$absolute=APP_ROOT.'/'.$path;if(str_starts_with((string)$path,'storage/uploads/clothes/')&&is_file($absolute))@unlink($absolute);flash('success','Photo supprimee.');}redirect('clothes-show',['id'=>(int)($_POST['clothing_id']??0)]);
    }

    private function findOwned(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT c.*, l.name AS label_name FROM clothes c JOIN clothing_labels l ON l.id=c.label_id WHERE c.id=:id AND c.user_id=:user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function validate(array $input, array &$errors): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $label = filter_var($input['label_id'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 160) $errors[] = 'Le nom est obligatoire (160 caractères maximum).';
        $check = $this->db->prepare('SELECT id FROM clothing_labels WHERE id=:id AND is_active=1');
        $check->execute(['id' => $label ?: 0]);
        if (!$check->fetch()) $errors[] = 'Choisissez un libellé valide.';
        $category = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
        $userId=(int)(current_user()['id']??0);
        $check = $this->db->prepare('SELECT id FROM categories WHERE id=:id AND label_id=:label_id AND is_active=1 AND (user_id IS NULL OR user_id=:user_id)');
        $check->execute(['id'=>$category?:0,'label_id'=>$label?:0,'user_id'=>$userId]);
        if (!$check->fetch()) $errors[] = 'Choisissez une catégorie correspondant au libellé.';
        $subcategory = filter_var($input['subcategory_id'] ?? null, FILTER_VALIDATE_INT);
        if ($subcategory) {
            $check=$this->db->prepare('SELECT s.id FROM subcategories s JOIN categories c ON c.id=s.category_id WHERE s.id=:id AND c.id=:category_id AND s.is_active=1 AND c.is_active=1');
            $check->execute(['id'=>$subcategory,'category_id'=>$category?:0]);
            if (!$check->fetch()) $errors[]='La sous-catégorie ne correspond pas à la catégorie.';
        }
        $status = (string) ($input['status'] ?? 'available');
        if (!in_array($status, ['available','laundry','repair','unavailable','archived'], true)) $status = 'available';
        $price = trim((string) ($input['purchase_price'] ?? ''));
        if ($price !== '' && (!is_numeric($price) || (float) $price < 0)) $errors[] = 'Le prix doit être un montant positif.';
        $currency = strtoupper(substr(trim((string) ($input['currency'] ?? 'USD')), 0, 3));
        $formality=(string)($input['formality']??'');if(!in_array($formality,['casual','smart_casual','formal','black_tie'],true))$formality='';
        return ['name'=>$name,'label_id'=>$label ?: 0,'category_id'=>$category?:null,'subcategory_id'=>$subcategory?:null,'description'=>trim((string)($input['description']??'')),'color'=>trim((string)($input['color']??'')),'brand'=>trim((string)($input['brand']??'')),'size'=>trim((string)($input['size']??'')),'purchase_price'=>$price===''?null:$price,'currency'=>$currency,'season'=>trim((string)($input['season']??'')),'style'=>trim((string)($input['style']??'')),'formality'=>$formality?:null,'occasion'=>trim((string)($input['occasion']??'')),'status'=>$status,'notes'=>trim((string)($input['notes']??''))];
    }

    private function storeImage(array $file, array &$errors): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $errors[] = 'Ajoutez une photo principale.'; return null; }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) { $errors[] = 'La photo doit faire 5 Mo maximum.'; return null; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) { $errors[] = 'Format de photo non accepté. Utilisez JPG, PNG ou WEBP.'; return null; }
        $directory = APP_ROOT . '/storage/uploads/clothes';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) { $errors[] = 'Le dossier de photos ne peut pas être créé.'; return null; }
        $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) { $errors[] = 'La photo n’a pas pu être enregistrée.'; return null; }
        return 'storage/uploads/clothes/' . $name;
    }

    private function storeGallery(array $files,array &$errors): array
    {
        $paths=[];if(empty($files['name'])||!is_array($files['name']))return $paths;$dir=APP_ROOT.'/storage/uploads/clothes';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir)){$errors[]='Le dossier de photos ne peut pas etre cree.';return $paths;}
        foreach($files['name'] as $i=>$original){if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;if(($files['error'][$i]??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||($files['size'][$i]??0)>5*1024*1024){$errors[]='Chaque photo supplementaire doit faire 5 Mo maximum.';break;}$tmp=$files['tmp_name'][$i]??'';$mime=is_file($tmp)?(new finfo(FILEINFO_MIME_TYPE))->file($tmp):'';$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext||@getimagesize($tmp)===false){$errors[]='Une photo supplementaire a un format invalide.';break;}$name=bin2hex(random_bytes(20)).'.'.$ext;if(!move_uploaded_file($tmp,$dir.'/'.$name)){$errors[]='Une photo supplementaire ne peut pas etre enregistree.';break;}$paths[]='storage/uploads/clothes/'.$name;}
        if($errors){foreach($paths as $path)@unlink(APP_ROOT.'/'.$path);return [];}return $paths;
    }
}
