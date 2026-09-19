<?php
declare(strict_types=1);
namespace App\Controllers\Admin;


use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;


final class MediaLibraryController
{
    private const IMAGE_MIMES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    private const PDF_MIMES=['application/pdf'=>'pdf'];


    public static function index():void
    {
        AdminAuth::requireLogin(); self::syncExisting();
        $pdo=Database::connection(); $q=trim((string)($_GET['q']??'')); $category=(string)($_GET['category']??''); $sort=(string)($_GET['sort']??'newest'); $unused=($_GET['unused']??'')==='1';
        $where=['archived_at IS NULL'];$params=[];
        if(in_array($category,['images','documents'],true)){$where[]='category=?';$params[]=$category;}
        if($q!==''){$where[]='(title LIKE ? OR filename LIKE ? OR alt_text LIKE ?)';$needle='%'.$q.'%';array_push($params,$needle,$needle,$needle);}
        if($unused)$where[]=self::unusedCondition();
        $page=max(1,Validator::int($_GET['page']??1));$perPage=48;$count=$pdo->prepare('SELECT COUNT(*) FROM media_assets WHERE '.implode(' AND ',$where));$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);
        $orders=['az'=>'title ASC,id ASC','za'=>'title DESC,id DESC','oldest'=>'created_at ASC,id ASC','newest'=>'created_at DESC,id DESC'];if(!isset($orders[$sort]))$sort='newest';
        $sql='SELECT * FROM media_assets WHERE '.implode(' AND ',$where).' ORDER BY '.$orders[$sort].' LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage);$stmt=$pdo->prepare($sql);$stmt->execute($params);$assets=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($assets as &$asset)$asset['usage_count']=self::usageCount($pdo,(string)$asset['file_path']);unset($asset);
        $title='Media Library';$user=AdminAuth::user();$csrf=Security::csrfToken();$notice=(string)($_GET['notice']??'');$error=(string)($_GET['error']??'');
        require dirname(__DIR__,2).'/Views/admin/media_library.php';
    }


    public static function upload():void
    {
        AdminAuth::requireLogin();self::csrf();$files=$_FILES['media_files']??null;$saved=0;$errors=[];
        if(!is_array($files)||!is_array($files['name']??null))$errors[]='Seleziona almeno un file.';
        else foreach(array_keys($files['name']) as $i){$file=['name'=>$files['name'][$i]??'','type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];$asset=self::store($file,$errors);if($asset){self::insertAsset($asset);$saved++;}}
        Audit::log('media.upload','media_asset',null,['saved'=>$saved,'errors'=>count($errors)]);self::redirect($saved.' file caricati.',implode(' ',$errors));
    }


    public static function update():void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$title=trim((string)($_POST['title']??''));$alt=trim((string)($_POST['alt_text']??''));if($id<1||$title===''||mb_strlen($title)>255||mb_strlen($alt)>255)self::redirect('','Dati non validi.');
        $s=Database::connection()->prepare('UPDATE media_assets SET title=?,alt_text=? WHERE id=? AND archived_at IS NULL');$s->execute([$title,$alt?:null,$id]);Audit::log('media.update','media_asset',$id);self::redirect('Dettagli aggiornati.');
    }


    public static function replace():void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM media_assets WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$asset=$s->fetch(PDO::FETCH_ASSOC);if(!$asset)self::redirect('','File non trovato.');
        $errors=[];$file=$_FILES['replacement']??null;if(!is_array($file)){self::redirect('','Seleziona il file sostitutivo.');}
        $validated=self::validate($file,$errors,(string)$asset['category']);if(!$validated)self::redirect('',implode(' ',$errors));
        $absolute=dirname(__DIR__,3).'/public'.(string)$asset['file_path'];$root=realpath(dirname(__DIR__,3).'/public/uploads');$parent=realpath(dirname($absolute));if($root===false||$parent===false||!str_starts_with($parent,$root)||!is_file($absolute))self::redirect('','Percorso del file non valido.');
        $backup=$absolute.'.bak-'.bin2hex(random_bytes(4));if(!copy($absolute,$backup)||!move_uploaded_file((string)$file['tmp_name'],$absolute)){@unlink($backup);self::redirect('','Sostituzione non riuscita.');}@chmod($absolute,0644);
        try{$pdo->prepare('UPDATE media_assets SET filename=?,mime_type=?,file_size=?,sha256=? WHERE id=?')->execute([(string)$file['name'],$validated['mime'],$validated['size'],hash_file('sha256',$absolute),$id]);@unlink($backup);}catch(\Throwable $e){@copy($backup,$absolute);@unlink($backup);throw $e;}
        Audit::log('media.replace','media_asset',$id,['path'=>$asset['file_path']]);self::redirect('File sostituito: tutti i collegamenti esistenti sono rimasti validi.');
    }


    public static function archive():void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT file_path,category FROM media_assets WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$asset=$s->fetch(PDO::FETCH_ASSOC);if(!$asset)self::redirect('','File non trovato.');$path=(string)$asset['file_path'];$uses=self::usageCount($pdo,$path);if($uses>0)self::redirect('','Il file è usato in '.$uses.' contenuti: rimuovi prima i collegamenti.');
        if(($_POST['delete_permanently']??'')==='1'){
            if((string)$asset['category']!=='images')self::redirect('','L’eliminazione definitiva è disponibile solo per le immagini.');
            $absolute=dirname(__DIR__,3).'/public'.$path;$root=realpath(dirname(__DIR__,3).'/public/uploads');$parent=realpath(dirname($absolute));if($root===false||$parent===false||!str_starts_with($parent,$root)||!is_file($absolute))self::redirect('','Percorso del file non valido.');
            if(!@unlink($absolute))self::redirect('','Non è stato possibile eliminare il file.');$pdo->prepare('DELETE FROM media_assets WHERE id=?')->execute([$id]);Audit::log('media.delete','media_asset',$id,['path'=>$path]);self::redirect('Immagine eliminata definitivamente.');
        }
        $pdo->prepare('UPDATE media_assets SET archived_at=NOW() WHERE id=?')->execute([$id]);Audit::log('media.archive','media_asset',$id,['path'=>$path]);self::redirect('File archiviato.');
    }


    private static function syncExisting():void
    {
        $root=dirname(__DIR__,3).'/public/uploads';if(!is_dir($root))return;$pdo=Database::connection();if((int)$pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn()>0)return;$insert=$pdo->prepare('INSERT IGNORE INTO media_assets(title,filename,file_path,mime_type,file_size,sha256,category,created_by) VALUES(?,?,?,?,?,?,?,NULL)');$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));$seen=0;
        foreach($it as $file){if(!$file->isFile()||$seen>=5000)continue;$relative=str_replace('\\','/',substr($file->getPathname(),strlen(dirname(__DIR__,3).'/public')));$mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());if(!isset(self::IMAGE_MIMES[$mime])&&!isset(self::PDF_MIMES[$mime]))continue;$category=isset(self::PDF_MIMES[$mime])?'documents':'images';$rawTitle=preg_replace('/^[a-f0-9]{16}-/i','',pathinfo($file->getFilename(),PATHINFO_FILENAME))??pathinfo($file->getFilename(),PATHINFO_FILENAME);$title=trim(str_replace(['-','_'],' ',$rawTitle));$insert->execute([$title?:$file->getFilename(),$file->getFilename(),$relative,$mime,$file->getSize(),hash_file('sha256',$file->getPathname()),$category]);$seen++;}
    }


    private static function store(array $file,array &$errors):?array
    {
        $valid=self::validate($file,$errors,null);if(!$valid)return null;$base=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',pathinfo((string)$file['name'],PATHINFO_FILENAME))?:'file';$base=trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$base)),'-')?:'file';$filename=substr($base,0,80).'-'.bin2hex(random_bytes(6)).'.'.$valid['extension'];$dir='/uploads/media/'.date('Y').'/'.date('m');$absolute=dirname(__DIR__,3).'/public'.$dir;if(!is_dir($absolute)&&!mkdir($absolute,0755,true)&&!is_dir($absolute))throw new \RuntimeException('Impossibile creare la cartella media.');$path=$absolute.'/'.$filename;if(!move_uploaded_file((string)$file['tmp_name'],$path)){$errors[]='Impossibile salvare '.(string)$file['name'].'.';return null;}@chmod($path,0644);return ['title'=>pathinfo((string)$file['name'],PATHINFO_FILENAME),'filename'=>(string)$file['name'],'path'=>$dir.'/'.$filename,'mime'=>$valid['mime'],'size'=>$valid['size'],'sha256'=>hash_file('sha256',$path),'category'=>$valid['category']];
    }


    private static function validate(array $file,array &$errors,?string $requiredCategory):?array
    {
        $name=(string)($file['name']??'file');$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);$tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);if($error!==UPLOAD_ERR_OK||$tmp===''||!is_uploaded_file($tmp)||$size<1){$errors[]=$name.': caricamento non valido.';return null;}$mime=(string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);$images=self::IMAGE_MIMES;$pdfs=self::PDF_MIMES;$category=isset($images[$mime])?'images':(isset($pdfs[$mime])?'documents':'');if($category===''||($requiredCategory!==null&&$category!==$requiredCategory)){$errors[]=$name.': formato non consentito.';return null;}$limit=$category==='images'?8*1024*1024:25*1024*1024;if($size>$limit){$errors[]=$name.': file troppo grande.';return null;}if($category==='images'&&(@getimagesize($tmp)===false)){$errors[]=$name.': immagine non valida.';return null;}if($category==='documents'){$h=@fopen($tmp,'rb');$sig=$h?(string)fread($h,5):'';if($h)fclose($h);if($sig!=='%PDF-'){$errors[]=$name.': PDF non valido.';return null;}}return ['mime'=>$mime,'extension'=>($images+$pdfs)[$mime],'size'=>$size,'category'=>$category];
    }


    private static function insertAsset(array $a):void{Database::connection()->prepare('INSERT INTO media_assets(title,filename,file_path,mime_type,file_size,sha256,category,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([$a['title'],$a['filename'],$a['path'],$a['mime'],$a['size'],$a['sha256'],$a['category'],AdminAuth::id()]);}
    private static function unusedCondition():string{return "NOT EXISTS(SELECT 1 FROM products x WHERE x.image_path=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM documents x WHERE x.file_path=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM catalogs x WHERE x.cover_image=media_assets.file_path OR x.pdf_path=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM gallery_albums x WHERE x.cover_image=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM gallery_images x WHERE x.image_path=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM references_projects x WHERE x.cover_image=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM reference_images x WHERE x.image_path=media_assets.file_path) AND NOT EXISTS(SELECT 1 FROM editorial_pages x WHERE x.body LIKE CONCAT('%',media_assets.file_path,'%')) AND NOT EXISTS(SELECT 1 FROM editorial_sections x WHERE x.body LIKE CONCAT('%',media_assets.file_path,'%')) AND NOT EXISTS(SELECT 1 FROM faq_items x WHERE x.answer LIKE CONCAT('%',media_assets.file_path,'%')) AND NOT EXISTS(SELECT 1 FROM assistance_resources x WHERE x.external_url=media_assets.file_path)";}
    private static function usageCount(PDO $pdo,string $path):int{$queries=[['products','image_path',false],['documents','file_path',false],['catalogs','cover_image',false],['catalogs','pdf_path',false],['gallery_albums','cover_image',false],['gallery_images','image_path',false],['references_projects','cover_image',false],['reference_images','image_path',false],['editorial_pages','body',true],['editorial_sections','body',true],['faq_items','answer',true],['assistance_resources','external_url',false]];$n=0;foreach($queries as [$table,$column,$contains]){try{$s=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} ".($contains?'LIKE':'=')." ?");$s->execute([$contains?'%'.$path.'%':$path]);$n+=(int)$s->fetchColumn();}catch(\Throwable){}}return $n;}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
    private static function redirect(string $notice='',string $error=''):never{$q=http_build_query(array_filter(['notice'=>$notice,'error'=>$error],static fn($v)=>$v!==''));header('Location:/idemaclima/admin/media'.($q!==''?'?'.$q:''));exit;}
}
