<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class MonoSplitImportController
{
    public static function index():void
    {
        AdminAuth::requireLogin();
        self::render(['preview'=>null,'error'=>null,'old'=>[]]);
    }

    public static function run():void
    {
        AdminAuth::requireLogin();
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $action=(string)($_POST['action']??'preview');
        if(!in_array($action,['preview','execute'],true)){http_response_code(400);exit('Operazione non valida');}
        $old=['product_id'=>Validator::int($_POST['product_id']??0),'source_url'=>trim((string)($_POST['source_url']??'')),'source_page'=>Validator::int($_POST['source_page']??0),'source_text'=>trim((string)($_POST['source_text']??''))];
        try{
            self::validate($old);
            $preview=self::parse($old['source_text']);
            if($action==='execute'){
                self::apply($old,$preview);
                header('Location:/idemaclima/admin/mono-split-import?imported=1');exit;
            }
            self::render(['preview'=>$preview,'error'=>null,'old'=>$old]);
        }catch(\Throwable $e){self::render(['preview'=>null,'error'=>$e->getMessage(),'old'=>$old]);}
    }

    private static function validate(array $input):void
    {
        if($input['product_id']<1)throw new \RuntimeException('Seleziona il prodotto Mono Split da aggiornare.');
        if($input['source_page']<1||$input['source_page']>999)throw new \RuntimeException('Indica un numero di pagina valido.');
        if($input['source_url']===''||mb_strlen($input['source_url'])>1000||!filter_var($input['source_url'],FILTER_VALIDATE_URL))throw new \RuntimeException('Indica il collegamento HTTPS del catalogo o listino.');
        $parts=parse_url($input['source_url']);$host=strtolower((string)($parts['host']??''));$scheme=strtolower((string)($parts['scheme']??''));
        if($scheme!=='https'||!in_array($host,['idemaclima.it','www.idemaclima.it','www.rappresentanzeguanzirolisas.it'],true))throw new \RuntimeException('Sono accettati solo cataloghi IDEMA o file presenti sullo staging.');
        if(mb_strlen($input['source_text'])<20||mb_strlen($input['source_text'])>60000)throw new \RuntimeException('Incolla il testo della pagina indicata (da 20 a 60.000 caratteri).');
        $pdo=Database::connection();$s=$pdo->prepare("SELECT p.id FROM products p JOIN product_categories c ON c.id=p.category_id WHERE p.id=? AND c.name='Mono Split' AND p.name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR')");$s->execute([$input['product_id']]);if(!$s->fetchColumn())throw new \RuntimeException('Il prodotto selezionato non appartiene ai sei Mono Split correnti.');
    }

    private static function parse(string $text):array
    {
        $text=str_replace(["\r\n","\r"],"\n",$text);$lines=preg_split('/\n+/u',$text)?:[];$result=['description'=>[],'features'=>[],'specifications'=>[],'accessories'=>[]];$section='description';
        foreach($lines as $raw){$line=trim((string)preg_replace('/\s+/u',' ',$raw));if($line==='')continue;$heading=mb_strtoupper(rtrim($line,':'),'UTF-8');
            if(preg_match('/^(CARATTERISTICHE|FUNZIONI|PLUS)$/u',$heading)){$section='features';continue;}
            if(preg_match('/^(SPECIFICHE|DATI TECNICI|SPECIFICHE TECNICHE)$/u',$heading)){$section='specifications';continue;}
            if(preg_match('/^(ACCESSORI|ACCESSORI OPZIONALI)$/u',$heading)){$section='accessories';continue;}
            if($section==='specifications'){$parts=preg_split('/\s*(?:\||:|\t)\s*/u',$line,2);if(count($parts)===2&&$parts[0]!==''&&$parts[1]!=='')$result[$section][]=mb_substr($parts[0],0,160).' | '.mb_substr($parts[1],0,255);}
            elseif($section==='accessories'){$parts=preg_split('/\s*\|\s*/u',$line,3);$result[$section][]=count($parts)>=2?implode(' | ',$parts):' | '.mb_substr($line,0,200).' | ';}
            else $result[$section][]=mb_substr($line,0,$section==='features'?255:2000);
        }
        $result['description']=trim(implode("\n",$result['description']));$result['features']=array_values(array_unique($result['features']));$result['specifications']=array_values(array_unique($result['specifications']));$result['accessories']=array_values(array_unique($result['accessories']));
        if($result['description']===''&&$result['features']===[]&&$result['specifications']===[]&&$result['accessories']===[])throw new \RuntimeException('Non è stato possibile riconoscere contenuti utili nella pagina.');
        return $result;
    }

    private static function apply(array $input,array $data):void
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            if($data['description']!=='')$pdo->prepare('UPDATE products SET description=? WHERE id=?')->execute([$data['description'],$input['product_id']]);
            $maps=['features'=>['product_features','INSERT INTO product_features(product_id,label,sort_order) VALUES(?,?,?)'],'specifications'=>['product_specifications','INSERT INTO product_specifications(product_id,specification_key,specification_value,sort_order) VALUES(?,?,?,?)'],'accessories'=>['product_accessories','INSERT INTO product_accessories(product_id,code,name,description,sort_order,published) VALUES(?,?,?,?,?,1)']];
            foreach($maps as $key=>[$table,$sql]){if($data[$key]===[])continue;$pdo->prepare("DELETE FROM {$table} WHERE product_id=?")->execute([$input['product_id']]);$q=$pdo->prepare($sql);$sort=0;foreach($data[$key] as $line){if($key==='features')$q->execute([$input['product_id'],$line,$sort++]);elseif($key==='specifications'){[$a,$b]=array_map('trim',explode('|',$line,2));$q->execute([$input['product_id'],$a,$b,$sort++]);}else{[$a,$b,$c]=array_map('trim',array_pad(explode('|',$line,3),3,''));$q->execute([$input['product_id'],$a?:null,$b,$c?:null,$sort++]);}}}
            Audit::log('mono_split.page_import','product',$input['product_id'],['source_url'=>$input['source_url'],'source_page'=>$input['source_page'],'counts'=>['features'=>count($data['features']),'specifications'=>count($data['specifications']),'accessories'=>count($data['accessories'])]]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function render(array $data):void
    {
        extract($data,EXTR_SKIP);$pdo=Database::connection();$products=$pdo->query("SELECT p.id,p.name,c.name family_name FROM products p JOIN product_categories c ON c.id=p.category_id WHERE c.name='Mono Split' AND p.name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR') ORDER BY FIELD(p.name,'ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR')")->fetchAll(PDO::FETCH_ASSOC);$title='Importa pagina Mono Split';$user=AdminAuth::user();$csrf=Security::csrfToken();$imported=isset($_GET['imported']);require dirname(__DIR__,2).'/Views/admin/mono_split_import.php';
    }
}
