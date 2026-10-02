<?php
namespace App\Core { final class Security {public static function adminNoStore():void{} public static function nonce():string{return 'fixture';}} }
namespace {
require dirname(__DIR__).'/app/Core/AdminUi.php';
require dirname(__DIR__).'/app/Auth/AdminAuth.php';
$user=['role'=>'admin','first_name'=>'Test','last_name'=>'Layout','username'=>'test'];$csrf='fixture';$_SERVER['REQUEST_URI']='/idemaclima/admin/product-images';
$media=[];$summary=[];$items=[['category_group'=>'Linea VRF','category_name'=>'Unità interne','name'=>'Prodotto esempio','source_catalog'=>'Catalogo 2026','source_page'=>'12','image_path'=>'','current_width'=>0,'current_height'=>0,'candidate_path'=>'','candidate_width'=>0,'candidate_height'=>0,'protected'=>0,'review_status'=>'to_review','product_id'=>1,'notes'=>'Importata automaticamente dal nome del file; candidata da verificare e non approvata.']];
$bulkImported=$bulkSkipped=$saved=$approved=$candidateRemoved=$transparencyChecked=$transparencyRemoved=$removed=0;$bulkErrors=[];$error='';
require dirname(__DIR__).'/app/Views/admin/product_images.php';
}
