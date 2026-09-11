<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use PDO;

trait WarrantyCertificateLayoutTrait
{
    private static function defaultCertificateLayout(): array
    {
        return [
            'accent'=>'#7dbf0a','badge'=>'#ff8f00','panel'=>'#f7f9fb','text'=>'#141a26','muted'=>'#627085',
            'title'=>'Certificato ufficiale','eyebrow'=>'Estensione di garanzia','coverage'=>'Copertura {anni} anni totali {formula}',
            'customer_title'=>'Dati intestatario','product_title'=>'Prodotto registrato','serials_title'=>'Numeri di serie',
            'legal_text'=>'Idema Clima S.r.l. attesta l’estensione della garanzia per {sistema}, relativa alla combinazione {combinazione}, alle condizioni riportate nel documento di garanzia di riferimento pubblicato sul sito www.idemaclima.it nella sezione garanzia.',
            'footer_company'=>'Idema Clima S.r.l. - P. IVA 03293510966',
            'footer_address'=>'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO) - www.idemaclima.it',
            'font_scale'=>1.0,'density'=>1.0,'show_logo'=>1,'show_badge'=>1,
            'blocks'=>['customer'=>1,'product'=>1,'legal'=>1],'order'=>['customer','product','legal'],
        ];
    }

    private static function certificateLayout(?PDO $pdo=null): array
    {
        $defaults=self::defaultCertificateLayout();
        try {
            $pdo??=Database::connection(); self::ensureCertificateLayoutTable($pdo);
            $raw=$pdo->query("SELECT layout_json FROM warranty_certificate_layouts WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
            $saved=is_string($raw)?json_decode($raw,true):null;
            if(!is_array($saved)) return $defaults;
            $layout=array_replace($defaults,array_intersect_key($saved,$defaults));
            foreach (['legal_text','footer_company'] as $companyField) {
                $layout[$companyField]=(string)preg_replace('/IDEMA CLIMA(?:®)? S\\.r\\.l\\./i','Idema Clima S.r.l.',(string)$layout[$companyField]);
            }
            $layout['blocks']=array_replace($defaults['blocks'],is_array($saved['blocks']??null)?$saved['blocks']:[]);
            $order=is_array($saved['order']??null)?array_values(array_intersect($saved['order'],['customer','product','legal'])):[];
            $layout['order']=array_values(array_unique(array_merge($order,$defaults['order'])));
            return $layout;
        } catch (\Throwable) { return $defaults; }
    }

    public static function certificateLayoutEditor(): void
    {
        AdminAuth::requireLogin(); $pdo=Database::connection();
        $layout=self::certificateLayout($pdo); $title='Editor certificato PDF';
        $user=AdminAuth::user(); $csrf=Security::csrfToken(); $saved=isset($_GET['saved']);
        require dirname(__DIR__,2).'/Views/admin/warranty_certificate_layout.php';
    }

    public static function saveCertificateLayout(): void
    {
        AdminAuth::requireLogin();
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $defaults=self::defaultCertificateLayout(); $layout=$defaults;
        foreach(['accent','badge','panel','text','muted'] as $key){
            $value=strtolower(trim((string)($_POST[$key]??$defaults[$key])));
            if(!preg_match('/^#[0-9a-f]{6}$/',$value)){http_response_code(422);exit('Colore non valido.');}
            $layout[$key]=$value;
        }
        $limits=['title'=>80,'eyebrow'=>80,'coverage'=>160,'customer_title'=>80,'product_title'=>80,'serials_title'=>80,'legal_text'=>1000,'footer_company'=>190,'footer_address'=>300];
        foreach($limits as $key=>$max){
            $value=trim((string)($_POST[$key]??$defaults[$key]));
            if($value===''||mb_strlen($value)>$max){http_response_code(422);exit('Controlla il campo '.str_replace('_',' ',$key).'.');}
            $layout[$key]=$value;
        }
        $layout['font_scale']=max(.85,min(1.10,(float)($_POST['font_scale']??1)));
        $layout['density']=max(.85,min(1.10,(float)($_POST['density']??1)));
        $layout['show_logo']=isset($_POST['show_logo'])?1:0; $layout['show_badge']=isset($_POST['show_badge'])?1:0;
        foreach(array_keys($defaults['blocks']) as $block)$layout['blocks'][$block]=isset($_POST['block_'.$block])?1:0;
        $order=array_values(array_intersect(explode(',',(string)($_POST['block_order']??'')),array_keys($defaults['blocks'])));
        $layout['order']=array_values(array_unique(array_merge($order,array_keys($defaults['blocks']))));
        $pdo=Database::connection(); self::ensureCertificateLayoutTable($pdo); $admin=AdminAuth::user();
        $pdo->beginTransaction();
        try{
            $pdo->exec('UPDATE warranty_certificate_layouts SET is_active=0 WHERE is_active=1');
            $stmt=$pdo->prepare('INSERT INTO warranty_certificate_layouts(layout_json,is_active,updated_by) VALUES(?,1,?)');
            $stmt->execute([json_encode($layout,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)($admin['id']??0)]);
            Audit::log('warranty.certificate_layout.update','warranty_certificate_layout',(int)$pdo->lastInsertId(),[]); $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(500);exit('Impossibile salvare il modello.');}
        header('Location:/idemaclima/admin/warranties/certificate-layout?saved=1');exit;
    }

    public static function resetCertificateLayout(): void
    {
        AdminAuth::requireLogin();
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $pdo=Database::connection(); self::ensureCertificateLayoutTable($pdo); $pdo->exec('UPDATE warranty_certificate_layouts SET is_active=0 WHERE is_active=1');
        Audit::log('warranty.certificate_layout.reset','warranty_certificate_layout',null,[]);
        header('Location:/idemaclima/admin/warranties/certificate-layout?saved=1');exit;
    }

    public static function certificateLayoutPreview(): void
    {
        AdminAuth::requireLogin();
        $years=(int)($_GET['years']??10)===5?5:10;
        if($years===5){
            $registration=['warranty_years'=>5,'extension_formula'=>'2 + 3','customer_first_name'=>'Alessandro','customer_last_name'=>'De Santis','fiscal_code'=>'DSSLSS80A01F205X','address'=>'Via della Progettazione Termotecnica, 125','city'=>'Vertemate con Minoprio','province'=>'CO','postal_code'=>'22070','region'=>'Lombardia','email'=>'cliente@example.it','phone'=>'+39 031 888 1637','product_type'=>'mono','outer_unit'=>'','combination'=>'ISPT-12-R32','code'=>'ISPT-12-R32','invoice_date'=>'2026-09-11'];
            $units=[['unit_type'=>'outdoor','serial_number'=>'UE-TEST-5ANNI-000001'],['unit_type'=>'indoor','serial_number'=>'UI-TEST-5ANNI-000001']];
        }else{
            $registration=['warranty_years'=>10,'extension_formula'=>'5 + 5','customer_first_name'=>'Alessandro Massimiliano','customer_last_name'=>'De Santis','fiscal_code'=>'DSSLSS80A01F205X','address'=>'Via della Progettazione Termotecnica, 125','city'=>'Vertemate con Minoprio','province'=>'CO','postal_code'=>'22070','region'=>'Lombardia','email'=>'cliente@example.it','phone'=>'+39 031 888 1637','product_type'=>'multi','outer_unit'=>'3MWTZ-70-R32','combination'=>'WTMC-25UI-BLK-R32 + 2x WTMC-35UI-BLK-R32','code'=>'WTMC-35UI-BLK-R32','invoice_date'=>'2026-08-31'];
            $units=[['unit_type'=>'outdoor','serial_number'=>'UE-TEST-2026-00000001'],['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-00000001'],['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-00000002']];
        }
        $pdf=self::buildCertificatePdf($registration,$units,'IDM-ANTEPRIMA');
        header('Content-Type: application/pdf'); header('Cache-Control: private, no-store'); header('Content-Disposition: inline; filename="Anteprima-Certificato-IDEMA.pdf"');
        echo $pdf;exit;
    }

    private static function ensureCertificateLayoutTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS warranty_certificate_layouts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,layout_json LONGTEXT NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,updated_by BIGINT UNSIGNED NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_warranty_layout_active(is_active,updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
