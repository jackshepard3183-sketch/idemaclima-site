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
        $user=AdminAuth::user(); $csrf=Security::csrfToken(); $saved=isset($_GET['saved']); $approved=isset($_GET['approved']); $error=trim((string)($_GET['error']??''));
        require dirname(__DIR__,2).'/Views/admin/product_images.php';
    }

    public static function save(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $status=(string)($_POST['review_status']??'to_review'); if(!in_array($status,array_diff(self::STATUSES,['approved']),true))$status='to_review';
        $catalog=trim((string)($_POST['source_catalog']??'')); $page=Validator::int($_POST['source_page']??0)?:null; $notes=trim((string)($_POST['notes']??''));
        $errors=[]; $uploaded=Upload::contentImage('candidate_file','products',$errors); $candidate=(string)($review['candidate_path']??'');
        if($uploaded)$candidate=(string)$uploaded['path'];
        elseif(($mediaId=Validator::int($_POST['media_id']??0))>0){$m=$pdo->prepare("SELECT file_path FROM media_assets WHERE id=? AND category='images' AND archived_at IS NULL");$m->execute([$mediaId]);$candidate=(string)($m->fetchColumn()?:'');if($candidate==='')$errors[]='Immagine della Media Library non valida.';}
        if($errors){if($uploaded)Upload::removeManaged((string)$uploaded['path']);self::redirectError(implode(' ',$errors));}
        [$width,$height]=self::dimensions($candidate);
        $q=$pdo->prepare('UPDATE product_image_reviews SET review_status=?,candidate_path=?,candidate_width=?,candidate_height=?,source_catalog=?,source_page=?,notes=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?');
        $q->execute([$status,$candidate?:null,$width,$height,$catalog?:null,$page,$notes?:null,AdminAuth::id(),$productId]);
        Audit::log('product_image.review','product',$productId,['status'=>$status,'candidate_path'=>$candidate]);
        header('Location: /idemaclima/admin/product-images?saved=1'); exit;
    }

    public static function approve(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review||(int)$review['protected']===1)self::redirectError('Le immagini dei 6 Mono Split sono protette.');
        $candidate=(string)($review['candidate_path']??''); if($candidate==='')self::redirectError('Carica o seleziona prima un’immagine candidata.');
        try{$assigned=Upload::copyProductImage($candidate,(string)$review['product_name']);}
        catch(\Throwable $e){self::redirectError($e->getMessage());}
        [$width,$height]=self::dimensions($assigned);$current=(string)($review['product_image_path']??''); $pdo->beginTransaction();
        try{$pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$assigned,$productId]);$pdo->prepare("UPDATE product_image_reviews SET original_image_path=COALESCE(original_image_path,?),candidate_path=?,candidate_width=?,candidate_height=?,review_status='approved',approved_at=CURRENT_TIMESTAMP,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([$current?:null,$assigned,$width,$height,AdminAuth::id(),$productId]);Audit::log('product_image.approve','product',$productId,['previous_path'=>$current,'source_path'=>$candidate,'approved_path'=>$assigned]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        header('Location: /idemaclima/admin/product-images?approved=1'); exit;
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
    private static function csrf(): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
    }
    private static function redirectError(string $message): never
    {
        header('Location: /idemaclima/admin/product-images?error='.rawurlencode($message));exit;
    }
}
