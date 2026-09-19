<?php
declare(strict_types=1);
namespace App\Controllers\Admin;


use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Core\Validator;
use PDO;


final class ProductImagesController
{
    private const PROTECTED_PRODUCTS=['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR'];
    private const STATUSES=['to_review','insufficient','recovered','missing_catalog','approved'];


    public static function index(): void
    {
        AdminAuth::requireLogin(); $pdo=Database::connection(); self::syncProducts($pdo);
        $sql="SELECT r.*,p.name,p.slug,p.image_path,c.name category_name,parent.name category_group
              FROM product_image_reviews r JOIN products p ON p.id=r.product_id
              JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_categories parent ON parent.id=c.parent_id
              ORDER BY r.protected DESC,COALESCE(parent.sort_order,c.sort_order),COALESCE(parent.name,c.name),c.sort_order,c.name,p.sort_order,p.name";
        $items=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($items as &$item){[$item['current_width'],$item['current_height']]=self::dimensions((string)($item['image_path']??''));} unset($item);
        $media=$pdo->query("SELECT id,title,file_path FROM media_assets WHERE category='images' AND archived_at IS NULL ORDER BY title,filename")->fetchAll(PDO::FETCH_ASSOC);
        $summary=array_fill_keys(self::STATUSES,0); foreach($items as $item)$summary[(string)$item['review_status']]++;
        $user=AdminAuth::user(); $csrf=Security::csrfToken(); $saved=isset($_GET['saved']); $approved=isset($_GET['approved']); $removed=isset($_GET['removed']); $candidateRemoved=isset($_GET['candidate_removed']); $transparencyChecked=Validator::int($_GET['transparency_checked']??0); $transparencyRemoved=Validator::int($_GET['transparency_removed']??0); $error=trim((string)($_GET['error']??'')); $bulkImported=Validator::int($_GET['bulk_imported']??0); $bulkSkipped=Validator::int($_GET['bulk_skipped']??0); $bulkErrors=$_SESSION['product_image_bulk_errors']??[]; unset($_SESSION['product_image_bulk_errors']);
        require dirname(__DIR__,2).'/Views/admin/product_images.php';
    }


    public static function save(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection();
        if(($_POST['audit_transparency']??'')==='1')self::auditTransparency($pdo);
        $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $status=(string)($_POST['review_status']??'to_review'); if(!in_array($status,array_diff(self::STATUSES,['approved']),true))$status='to_review';
        $catalog=trim((string)($_POST['source_catalog']??'')); $page=Validator::int($_POST['source_page']??0)?:null; $notes=trim((string)($_POST['notes']??''));
        $errors=[]; if(self::hasUpload('candidate_file')&&!self::transparentImageFile((string)$_FILES['candidate_file']['tmp_name']))$errors[]='L’immagine candidata deve avere uno sfondo realmente trasparente.'; $uploaded=$errors?null:Upload::contentImage('candidate_file','products',$errors); $candidate=(string)($review['candidate_path']??'');
        if($uploaded)$candidate=(string)$uploaded['path'];
        elseif(($mediaId=Validator::int($_POST['media_id']??0))>0){$m=$pdo->prepare("SELECT file_path FROM media_assets WHERE id=? AND category='images' AND archived_at IS NULL");$m->execute([$mediaId]);$candidate=(string)($m->fetchColumn()?:'');if($candidate===''||!self::transparentManagedImage($candidate))$errors[]='Immagine della Media Library non valida o priva di sfondo trasparente.';}
        if($errors){if($uploaded)Upload::removeManaged((string)$uploaded['path']);self::redirectError(implode(' ',$errors));}
        [$width,$height]=self::dimensions($candidate);
        $q=$pdo->prepare('UPDATE product_image_reviews SET review_status=?,candidate_path=?,candidate_width=?,candidate_height=?,source_catalog=?,source_page=?,notes=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?');
        $q->execute([$status,$candidate?:null,$width,$height,$catalog?:null,$page,$notes?:null,AdminAuth::id(),$productId]);
        Audit::log('product_image.review','product',$productId,['status'=>$status,'candidate_path'=>$candidate]);
        header('Location: /idemaclima/admin/product-images?saved=1'); exit;
    }




    public static function bulkImport(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); self::syncProducts($pdo);
        $files=$_FILES['candidate_files']??null; $catalog=trim((string)($_POST['source_catalog']??''));
        if(!is_array($files)||!isset($files['name'])||!is_array($files['name']))self::redirectError('Seleziona almeno un’immagine.');
        $rows=$pdo->query("SELECT r.product_id,r.review_status,r.protected,p.name,p.slug FROM product_image_reviews r JOIN products p ON p.id=r.product_id")->fetchAll(PDO::FETCH_ASSOC);
        $lookup=[]; foreach($rows as $row){if((int)$row['protected']===1||$row['review_status']==='approved')continue;foreach([(string)$row['name'],(string)$row['slug']] as $value)$lookup[self::key($value)][]=$row;}
        $imported=0;$skipped=0;$errors=[];$count=count($files['name']);
        for($i=0;$i<$count;$i++){
            if((int)($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
            $_FILES['candidate_file']=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
            if(!self::transparentImageFile((string)($_FILES['candidate_file']['tmp_name']??''))){$skipped++;$errors[]=(string)$files['name'][$i].': sfondo non trasparente.';continue;}
            $uploadErrors=[];$uploaded=Upload::contentImage('candidate_file','products',$uploadErrors);
            if(!$uploaded){$skipped++;$errors=array_merge($errors,$uploadErrors);continue;}
            $key=self::key(pathinfo((string)$files['name'][$i],PATHINFO_FILENAME));$matches=$lookup[$key]??[];
            if(!$matches){Upload::removeManaged((string)$uploaded['path']);$skipped++;$errors[]='Nessun prodotto corrisponde a '.(string)$files['name'][$i].'.';continue;}
            [$width,$height]=self::dimensions((string)$uploaded['path']);
            foreach($matches as $match){$q=$pdo->prepare("UPDATE product_image_reviews SET review_status='recovered',candidate_path=?,candidate_width=?,candidate_height=?,source_catalog=?,notes=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=? AND protected=0 AND review_status<>'approved'");$q->execute([(string)$uploaded['path'],$width,$height,$catalog?:'Importazione multipla','Importata automaticamente dal nome del file; candidata da verificare e non assegnata al frontend.',AdminAuth::id(),(int)$match['product_id']]);if($q->rowCount()>0){$imported++;Audit::log('product_image.bulk_import','product',(int)$match['product_id'],['candidate_path'=>$uploaded['path'],'source_name'=>$files['name'][$i]]);}}
        }
        unset($_FILES['candidate_file']);$_SESSION['product_image_bulk_errors']=array_slice(array_values(array_unique($errors)),0,20);
        header('Location: /idemaclima/admin/product-images?bulk_imported='.$imported.'&bulk_skipped='.$skipped);exit;
    }


    public static function approve(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $candidate=(string)($review['candidate_path']??''); if($candidate==='')self::redirectError('Carica o seleziona prima un’immagine candidata.'); if(!self::transparentManagedImage($candidate))self::redirectError('L’immagine candidata non supera il controllo dello sfondo trasparente.');
        $replaceExisting=isset($_POST['replace_existing'])&&$_POST['replace_existing']==='1';
        try{$assigned=Upload::copyProductImage($candidate,(string)$review['product_name'],$replaceExisting);}
        catch(\Throwable $e){self::redirectError($e->getMessage());}
        [$width,$height]=self::dimensions($assigned);$current=(string)($review['product_image_path']??''); $pdo->beginTransaction();
        try{$pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$assigned,$productId]);$pdo->prepare("UPDATE product_image_reviews SET original_image_path=COALESCE(original_image_path,?),candidate_path=?,candidate_width=?,candidate_height=?,review_status='approved',approved_at=CURRENT_TIMESTAMP,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([$current?:null,$assigned,$width,$height,AdminAuth::id(),$productId]);Audit::log('product_image.approve','product',$productId,['previous_path'=>$current,'source_path'=>$candidate,'approved_path'=>$assigned,'replaced_existing'=>$replaceExisting]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        header('Location: /idemaclima/admin/product-images?approved=1'); exit;
    }


    public static function removeCandidate(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $candidate=(string)($review['candidate_path']??'');
        if($review['review_status']==='approved'||$candidate==='')self::redirectError('Non risulta presente un’immagine candidata da rimuovere.');
        $pdo->prepare("UPDATE product_image_reviews SET candidate_path=NULL,candidate_width=NULL,candidate_height=NULL,review_status='to_review',reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([AdminAuth::id(),$productId]);
        Audit::log('product_image.remove_candidate','product',$productId,['removed_path'=>$candidate]);
        $q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM products WHERE image_path=?)+(SELECT COUNT(*) FROM product_image_reviews WHERE candidate_path=? OR original_image_path=?)+(SELECT COUNT(*) FROM media_assets WHERE file_path=?)');
        $q->execute([$candidate,$candidate,$candidate,$candidate]);if((int)$q->fetchColumn()===0)Upload::removeManaged($candidate);
        header('Location: /idemaclima/admin/product-images?candidate_removed=1'); exit;
    }


    public static function removeAssigned(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $assigned=(string)($review['candidate_path']??'');$current=(string)($review['product_image_path']??'');
        if($review['review_status']!=='approved'||$assigned===''||$current!==$assigned)self::redirectError('L’immagine nuova non risulta attualmente assegnata al prodotto.');
        $restore=(string)($review['original_image_path']??'');$pdo->beginTransaction();
        try{
            $pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$restore?:null,$productId]);
            $pdo->prepare("UPDATE product_image_reviews SET original_image_path=NULL,candidate_path=NULL,candidate_width=NULL,candidate_height=NULL,review_status='to_review',approved_at=NULL,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([AdminAuth::id(),$productId]);
            Audit::log('product_image.remove_assigned','product',$productId,['removed_path'=>$assigned,'restored_path'=>$restore]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        $q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM products WHERE image_path=?)+(SELECT COUNT(*) FROM product_image_reviews WHERE candidate_path=? OR original_image_path=?)');
        $q->execute([$assigned,$assigned,$assigned]);if((int)$q->fetchColumn()===0)Upload::removeManaged($assigned);
        header('Location: /idemaclima/admin/product-images?removed=1'); exit;
    }


    private static function syncProducts(PDO $pdo): void
    {
        $marks=implode(',',array_fill(0,count(self::PROTECTED_PRODUCTS),'?'));
        $pdo->prepare("INSERT IGNORE INTO product_image_reviews(product_id,review_status,protected) SELECT id,'to_review',CASE WHEN name IN ({$marks}) THEN 1 ELSE 0 END FROM products")->execute(self::PROTECTED_PRODUCTS);
    }
    private static function review(PDO $pdo,int $productId): array|false
    {
        self::syncProducts($pdo);$q=$pdo->prepare('SELECT r.*,p.name product_name,p.image_path product_image_path FROM product_image_reviews r JOIN products p ON p.id=r.product_id WHERE r.product_id=?');$q->execute([$productId]);return $q->fetch(PDO::FETCH_ASSOC);
    }
    private static function dimensions(string $path): array
    {
        if($path===''||!str_starts_with($path,'/uploads/'))return[null,null];$file=dirname(__DIR__,3).'/public'.$path;$info=is_file($file)?@getimagesize($file):false;return $info===false?[null,null]:[(int)$info[0],(int)$info[1]];
    }
    private static function hasUpload(string $field):bool{return isset($_FILES[$field])&&is_array($_FILES[$field])&&(int)($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;}
    private static function transparentManagedImage(string $path):bool
    {
        if($path===''||!str_starts_with($path,'/uploads/'))return false;return self::transparentImageFile(dirname(__DIR__,3).'/public'.$path);
    }
    private static function transparentImageFile(string $file):bool
    {
        if($file===''||!is_file($file))return false;$info=@getimagesize($file);if($info===false||((int)$info[0]*(int)$info[1])>50000000)return false;$mime=(string)($info['mime']??'');
        $image=$mime==='image/png'?@imagecreatefrompng($file):($mime==='image/webp'?@imagecreatefromwebp($file):false);if(!$image)return false;$w=imagesx($image);$h=imagesy($image);if($w<2||$h<2){imagedestroy($image);return false;}
        $step=max(1,(int)floor(max($w,$h)/600));$transparent=0;$total=0;$check=static function($im,int $x,int $y):bool{return ((imagecolorat($im,$x,$y)>>24)&0x7F)>=16;};
        for($x=0;$x<$w;$x+=$step){$total+=2;$transparent+=(int)$check($image,$x,0)+(int)$check($image,$x,$h-1);}for($y=0;$y<$h;$y+=$step){$total+=2;$transparent+=(int)$check($image,0,$y)+(int)$check($image,$w-1,$y);}
        $corners=$check($image,0,0)&&$check($image,$w-1,0)&&$check($image,0,$h-1)&&$check($image,$w-1,$h-1);imagedestroy($image);return $corners&&$total>0&&($transparent/$total)>=0.90;
    }
    private static function auditTransparency(PDO $pdo):never
    {
        self::syncProducts($pdo);$rows=$pdo->query("SELECT product_id,candidate_path FROM product_image_reviews WHERE protected=0 AND review_status<>'approved' AND candidate_path IS NOT NULL AND candidate_path<>''")->fetchAll(PDO::FETCH_ASSOC);$checked=0;$removed=0;
        foreach($rows as $row){$checked++;$path=(string)$row['candidate_path'];if(self::transparentManagedImage($path))continue;$pdo->prepare("UPDATE product_image_reviews SET candidate_path=NULL,candidate_width=NULL,candidate_height=NULL,review_status='to_review',notes=CONCAT_WS(' ',NULLIF(notes,''),'Candidatura rimossa: sfondo non trasparente.'),reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([AdminAuth::id(),(int)$row['product_id']]);Audit::log('product_image.transparency_reject','product',(int)$row['product_id'],['removed_path'=>$path]);$removed++;$q=$pdo->prepare('SELECT (SELECT COUNT(*) FROM products WHERE image_path=?)+(SELECT COUNT(*) FROM product_image_reviews WHERE candidate_path=? OR original_image_path=?)+(SELECT COUNT(*) FROM media_assets WHERE file_path=?)');$q->execute([$path,$path,$path,$path]);if((int)$q->fetchColumn()===0)Upload::removeManaged($path);}
        header('Location: /idemaclima/admin/product-images?transparency_checked='.$checked.'&transparency_removed='.$removed);exit;
    }
    private static function key(string $value): string
    {
        $value=strtolower(trim($value));$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-');
    }
    private static function csrf(): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
    }
    private static function redirectError(string $message): never
    {
        header('Location: /idemaclima/admin/product-images?error='.rawurlencode($message));exit;
    }
}
// Deployment sync: transparent candidate validation.
