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
        if(isset($_GET['normalize_approved_margin']))self::normalizeApprovedMargin($pdo);
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
        if(!$review)self::redirectError('Prodotto non trovato.');
        
        $status=(string)($_POST['review_status']??'to_review'); if(!in_array($status,array_diff(self::STATUSES,['approved']),true))$status='to_review';
        $catalog=trim((string)($_POST['source_catalog']??'')); $page=Validator::int($_POST['source_page']??0)?:null; $notes=trim((string)($_POST['notes']??''));
        $errors=[]; if(self::hasUpload('candidate_file')&&!self::transparentImageFile((string)$_FILES['candidate_file']['tmp_name']))$errors[]='L’immagine candidata deve avere uno sfondo realmente trasparente.'; $uploaded=$errors?null:Upload::contentImage('candidate_file','products',$errors); $candidate=(string)($review['candidate_path']??'');
        if($uploaded)$candidate=(string)$uploaded['path'];
        elseif(($mediaId=Validator::int($_POST['media_id']??0))>0){$m=$pdo->prepare("SELECT file_path FROM media_assets WHERE id=? AND category='images' AND archived_at IS NULL");$m->execute([$mediaId]);$candidate=(string)($m->fetchColumn()?:'');$recoveringMissing=!self::managedImageExists((string)($review['product_image_path']??''));if($candidate===''||(!$recoveringMissing&&!self::transparentManagedImage($candidate)))$errors[]='Immagine della Media Library non valida o priva di sfondo trasparente.';}
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
        $rows=$pdo->query("SELECT r.product_id,r.review_status,r.protected,p.name,p.slug,c.name category_name,parent.name category_group FROM product_image_reviews r JOIN products p ON p.id=r.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_categories parent ON parent.id=c.parent_id")->fetchAll(PDO::FETCH_ASSOC);
        $lookup=[]; foreach($rows as $row){if((int)$row['protected']===1)continue;foreach([(string)$row['name'],(string)$row['slug']] as $value)$lookup[self::key($value)][]=$row;}
        $imported=0;$skipped=0;$errors=[];$count=count($files['name']);
        for($i=0;$i<$count;$i++){
            if((int)($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
            $_FILES['candidate_file']=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
            $key=self::key(pathinfo((string)$files['name'][$i],PATHINFO_FILENAME));$matches=$lookup[$key]??[];
            $residentialMatch=(bool)array_filter($matches,static fn($match)=>self::isResidential($match));
            if(!self::transparentImageFile((string)($_FILES['candidate_file']['tmp_name']??''),$residentialMatch?0.60:0.90)){$skipped++;$errors[]=(string)$files['name'][$i].': sfondo non trasparente.';continue;}
            $uploadErrors=[];$uploaded=Upload::contentImage('candidate_file','products',$uploadErrors);
            if(!$uploaded){$skipped++;$errors=array_merge($errors,$uploadErrors);continue;}
            if(!$matches){Upload::removeManaged((string)$uploaded['path']);$skipped++;$errors[]='Nessun prodotto corrisponde a '.(string)$files['name'][$i].'.';continue;}
            [$width,$height]=self::dimensions((string)$uploaded['path']);
            if(array_filter($matches,static fn($match)=>self::isResidential($match))&&($width!==1000||$height!==1000)){Upload::removeManaged((string)$uploaded['path']);$skipped++;$errors[]=(string)$files['name'][$i].': la Linea Residenziale richiede 1000×1000 px.';continue;}
            foreach($matches as $match){$q=$pdo->prepare("UPDATE product_image_reviews SET review_status='recovered',candidate_path=?,candidate_width=?,candidate_height=?,source_catalog=?,notes=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=? AND protected=0");$q->execute([(string)$uploaded['path'],$width,$height,$catalog?:'Importazione multipla','Importata automaticamente dal nome del file; candidata da verificare e non assegnata al frontend.',AdminAuth::id(),(int)$match['product_id']]);if($q->rowCount()>0){$imported++;Audit::log('product_image.bulk_import','product',(int)$match['product_id'],['candidate_path'=>$uploaded['path'],'source_name'=>$files['name'][$i]]);}}
        }
        unset($_FILES['candidate_file']);$_SESSION['product_image_bulk_errors']=array_slice(array_values(array_unique($errors)),0,20);
        header('Location: /idemaclima/admin/product-images?bulk_imported='.$imported.'&bulk_skipped='.$skipped);exit;
    }


    public static function approve(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review)self::redirectError('Prodotto non trovato.');
        $current=(string)($review['product_image_path']??''); $recoverMissing=!self::managedImageExists($current);
        
        $candidate=(string)($review['candidate_path']??''); if($candidate==='')self::redirectError('Carica o seleziona prima un’immagine candidata.'); if(!$recoverMissing&&!self::transparentManagedImage($candidate,self::isResidential($review)?0.60:0.90))self::redirectError('L’immagine candidata non supera il controllo dello sfondo trasparente.');
        $replaceExisting=isset($_POST['replace_existing'])&&$_POST['replace_existing']==='1';
        if(self::isResidential($review)||self::dimensions($candidate)===[1000,1000])try{$assigned=self::copyOriginal1000($candidate,(string)$review['product_name']);}catch(\Throwable $e){self::redirectError($e->getMessage());}
        elseif($recoverMissing&&in_array((string)$review['product_name'],['ISA-R32','ISAT-R32'],true))$assigned=self::copyRecovered150($candidate,(string)$review['product_name']);
        elseif($recoverMissing)$assigned=$candidate;
        else try{$assigned=Upload::copyProductImage($candidate,(string)$review['product_name'],$replaceExisting);}
        catch(\Throwable $e){self::redirectError($e->getMessage());}
        [$width,$height]=self::dimensions($assigned); $pdo->beginTransaction();
        try{$pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$assigned,$productId]);$pdo->prepare("UPDATE product_image_reviews SET original_image_path=COALESCE(original_image_path,?),candidate_path=?,candidate_width=?,candidate_height=?,review_status='approved',approved_at=CURRENT_TIMESTAMP,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([$current?:null,$assigned,$width,$height,AdminAuth::id(),$productId]);Audit::log('product_image.approve','product',$productId,['previous_path'=>$current,'source_path'=>$candidate,'approved_path'=>$assigned,'replaced_existing'=>$replaceExisting]);$pdo->commit();}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        header('Location: /idemaclima/admin/product-images?approved=1'); exit;
    }


    public static function removeCandidate(): void
    {
        AdminAuth::requireLogin(); self::csrf(); $pdo=Database::connection(); $productId=Validator::int($_POST['product_id']??0); $review=self::review($pdo,$productId);
        if(!$review)self::redirectError('Prodotto non trovato.');
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
        if(!$review)self::redirectError('Prodotto non trovato.');
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



    private static function normalizeProtectedCandidates(PDO $pdo): void
    {
        $rows=$pdo->query("SELECT r.product_id,r.candidate_path,r.candidate_width,r.candidate_height,p.name FROM product_image_reviews r JOIN products p ON p.id=r.product_id WHERE r.protected=1 AND r.candidate_path IS NOT NULL AND r.candidate_path<>''")->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){
            $path=(string)$row['candidate_path'];
            [$width,$height]=self::dimensions($path);
            if(($width===600&&$height===600)||($width===1000&&$height===1000))continue;
            try{
                $normalized=Upload::copyProductImage($path,(string)$row['name'].'-candidate',true);
                $pdo->prepare('UPDATE product_image_reviews SET candidate_path=?,candidate_width=600,candidate_height=600 WHERE product_id=?')->execute([$normalized,(int)$row['product_id']]);
                Audit::log('product_image.normalize_candidate','product',(int)$row['product_id'],['previous_path'=>$path,'candidate_path'=>$normalized,'width'=>600,'height'=>600]);
            }catch(\Throwable $e){
                // La gestione immagini resta accessibile anche se una singola sorgente non è convertibile.
            }
        }
    }

    private static function normalizeApprovedMargin(PDO $pdo): never
    {
        $after=max(0,Validator::int($_GET['after']??0));
        $normalized=max(0,Validator::int($_GET['normalized']??0));
        $errors=max(0,Validator::int($_GET['normalization_errors']??0));
        $q=$pdo->prepare("SELECT r.product_id,r.candidate_path,p.name
                         FROM product_image_reviews r
                         JOIN products p ON p.id=r.product_id
                         WHERE r.protected=0 AND r.review_status='approved'
                           AND NOT EXISTS (SELECT 1 FROM product_categories c LEFT JOIN product_categories parent ON parent.id=c.parent_id JOIN products pr ON pr.category_id=c.id WHERE pr.id=r.product_id AND (c.name='Linea Residenziale R32' OR parent.name='Linea Residenziale R32'))
                           AND r.candidate_path IS NOT NULL AND r.candidate_path<>''
                           AND r.product_id>?
                         ORDER BY r.product_id LIMIT 20");
        $q->execute([$after]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $last=$after;
        foreach($rows as $row){
            $productId=(int)$row['product_id'];
            $last=max($last,$productId);
            $path=(string)$row['candidate_path'];
            if(self::dimensions($path)===[1000,1000])continue;
            try{
                $assigned=Upload::copyProductImage($path,(string)$row['name'],true);
                $pdo->prepare('UPDATE products SET image_path=? WHERE id=?')->execute([$assigned,$productId]);
                $pdo->prepare("UPDATE product_image_reviews SET candidate_path=?,candidate_width=1000,candidate_height=1000,notes=CONCAT_WS(' ',NULLIF(notes,''),'Normalizzata automaticamente: prodotto entro 980×980 px su tela trasparente 1000×1000 px.'),reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE product_id=?")->execute([$assigned,AdminAuth::id(),$productId]);
                Audit::log('product_image.normalize_approved_margin','product',$productId,['previous_path'=>$path,'assigned_path'=>$assigned,'content_max'=>980,'canvas'=>1000]);
                $normalized++;
            }catch(\Throwable $e){
                $errors++;
                Audit::log('product_image.normalize_approved_margin_error','product',$productId,['path'=>$path,'error'=>$e->getMessage()]);
            }
        }
        if(count($rows)===20){
            header('Location: /idemaclima/admin/product-images?normalize_approved_margin=1&after='.$last.'&normalized='.$normalized.'&normalization_errors='.$errors);
            exit;
        }
        header('Location: /idemaclima/admin/product-images?margin_normalized='.$normalized.'&normalization_errors='.$errors);
        exit;
    }

    private static function syncProducts(PDO $pdo): void
    {
        $marks=implode(',',array_fill(0,count(self::PROTECTED_PRODUCTS),'?'));
        $pdo->prepare("INSERT IGNORE INTO product_image_reviews(product_id,review_status,protected) SELECT id,'to_review',0 FROM products")->execute();
        $pdo->prepare("UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_categories parent ON parent.id=c.parent_id SET r.protected=0 WHERE r.protected=1 AND p.name IN ({$marks}) AND (c.name='Linea Residenziale R32' OR parent.name='Linea Residenziale R32')")->execute(self::PROTECTED_PRODUCTS);
    }
    private static function review(PDO $pdo,int $productId): array|false
    {
        self::syncProducts($pdo);$q=$pdo->prepare('SELECT r.*,p.name product_name,p.image_path product_image_path,c.name category_name,parent.name category_group FROM product_image_reviews r JOIN products p ON p.id=r.product_id JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_categories parent ON parent.id=c.parent_id WHERE r.product_id=?');$q->execute([$productId]);return $q->fetch(PDO::FETCH_ASSOC);
    }
    private static function isResidential(array $row): bool
    {
        return ($row['category_group']??'')==='Linea Residenziale R32'||($row['category_name']??'')==='Linea Residenziale R32';
    }

    private static function copyOriginal1000(string $path,string $model): string
    {
        if(!str_starts_with($path,'/uploads/'))throw new \RuntimeException('Percorso immagine non valido.');
        $public=realpath(dirname(__DIR__,3).'/public');
        $source=realpath(dirname(__DIR__,3).'/public'.$path);
        if($public===false||$source===false||!str_starts_with($source,$public.DIRECTORY_SEPARATOR))throw new \RuntimeException('Immagine non disponibile.');
        $info=@getimagesize($source);
        if(!$info||$info[0]!==1000||$info[1]!==1000||!in_array($info['mime'],['image/png','image/webp'],true)||!self::transparentImageFile($source,0.50))throw new \RuntimeException('È richiesta un’immagine PNG o WebP trasparente da 1000×1000 px.');
        $base=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$model)?:'prodotto';
        $base=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$base),'-'))?:'prodotto';
        $ext=$info['mime']==='image/png'?'png':'webp';
        $dir='/uploads/products/'.date('Y').'/'.date('m');
        $absolute=$public.$dir;
        if(!is_dir($absolute)&&!mkdir($absolute,0755,true)&&!is_dir($absolute))throw new \RuntimeException('Impossibile creare la cartella immagini.');
        $filename=$base.'-1000-'.substr(hash_file('sha256',$source),0,12).'.'.$ext;
        if(!is_file($absolute.'/'.$filename)&&!copy($source,$absolute.'/'.$filename))throw new \RuntimeException('Impossibile copiare l’immagine originale.');
        @chmod($absolute.'/'.$filename,0644);
        return $dir.'/'.$filename;
    }

    private static function dimensions(string $path): array
    {
        if($path===''||!str_starts_with($path,'/uploads/'))return[null,null];$file=dirname(__DIR__,3).'/public'.$path;$info=is_file($file)?@getimagesize($file):false;return $info===false?[null,null]:[(int)$info[0],(int)$info[1]];
    }
    private static function copyRecovered150(string $path,string $name):string
    {
        $source=realpath(dirname(__DIR__,3).'/public'.$path);
        $root=realpath(dirname(__DIR__,3).'/public/uploads');
        $info=$source!==false?@getimagesize($source):false;
        if($source===false||$root===false||!str_starts_with($source,$root.DIRECTORY_SEPARATOR)||$info===false)self::redirectError('La sorgente 150×150 non è disponibile.');
        $mime=(string)($info['mime']??'');$loader=$mime==='image/png'?'imagecreatefrompng':($mime==='image/webp'?'imagecreatefromwebp':($mime==='image/jpeg'?'imagecreatefromjpeg':''));
        if($loader===''||!function_exists($loader))self::redirectError('Formato sorgente non supportato.');
        $image=@$loader($source);if($image===false)self::redirectError('Impossibile leggere la sorgente 150×150.');
        $canvas=imagecreatetruecolor(150,150);imagealphablending($canvas,false);imagesavealpha($canvas,true);$transparent=imagecolorallocatealpha($canvas,255,255,255,127);imagefill($canvas,0,0,$transparent);
        $scale=min(150/(int)$info[0],150/(int)$info[1]);$w=max(1,(int)round((int)$info[0]*$scale));$h=max(1,(int)round((int)$info[1]*$scale));imagecopyresampled($canvas,$image,(int)floor((150-$w)/2),(int)floor((150-$h)/2),0,0,$w,$h,(int)$info[0],(int)$info[1]);
        $dir='/uploads/products/'.date('Y').'/'.date('m');$absolute=dirname(__DIR__,3).'/public'.$dir;if(!is_dir($absolute)&&!mkdir($absolute,0755,true)&&!is_dir($absolute))self::redirectError('Impossibile creare la cartella di ripristino.');
        $base=strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name)?:'prodotto'));$file=$absolute.'/'.trim($base,'-').'-150.png';
        $written=imagepng($canvas,$file,9);imagedestroy($canvas);imagedestroy($image);if(!$written)self::redirectError('Impossibile creare l’immagine 150×150.');@chmod($file,0644);return $dir.'/'.basename($file);
    }
    private static function managedImageExists(string $path):bool
    {
        if($path===''||!str_starts_with($path,'/uploads/'))return false;
        $file=realpath(dirname(__DIR__,3).'/public'.$path);
        $root=realpath(dirname(__DIR__,3).'/public/uploads');
        return $file!==false&&$root!==false&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)&&@getimagesize($file)!==false;
    }
    private static function hasUpload(string $field):bool{return isset($_FILES[$field])&&is_array($_FILES[$field])&&(int)($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;}
    private static function transparentManagedImage(string $path,float $minimum=0.90):bool
    {
        if($path===''||!str_starts_with($path,'/uploads/'))return false;return self::transparentImageFile(dirname(__DIR__,3).'/public'.$path,$minimum);
    }
    private static function transparentImageFile(string $file,float $minimum=0.90):bool
    {
        if($file===''||!is_file($file))return false;$info=@getimagesize($file);if($info===false||((int)$info[0]*(int)$info[1])>50000000)return false;$mime=(string)($info['mime']??'');
        if((int)$info[0]===1000&&(int)$info[1]===1000&&$minimum===0.90)$minimum=0.50;
        $image=$mime==='image/png'?@imagecreatefrompng($file):($mime==='image/webp'?@imagecreatefromwebp($file):false);if(!$image)return false;$w=imagesx($image);$h=imagesy($image);if($w<2||$h<2){imagedestroy($image);return false;}
        $step=max(1,(int)floor(max($w,$h)/600));$transparent=0;$total=0;$check=static function($im,int $x,int $y):bool{return ((imagecolorat($im,$x,$y)>>24)&0x7F)>=16;};
        for($x=0;$x<$w;$x+=$step){$total+=2;$transparent+=(int)$check($image,$x,0)+(int)$check($image,$x,$h-1);}for($y=0;$y<$h;$y+=$step){$total+=2;$transparent+=(int)$check($image,0,$y)+(int)$check($image,$w-1,$y);}
        $corners=$check($image,0,0)&&$check($image,$w-1,0)&&$check($image,0,$h-1)&&$check($image,$w-1,$h-1);imagedestroy($image);return $corners&&$total>0&&($transparent/$total)>=$minimum;
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
// Deployment sync: protected Mono Split candidates normalized to 600x600.