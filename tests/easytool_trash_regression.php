<?php
declare(strict_types=1);
namespace App\Core { final class Security { public static function adminNoStore():void {} public static function nonce():string {return 'fixture';} } }
namespace {
require dirname(__DIR__).'/app/Core/AdminUi.php';
$user=['first_name'=>'Test','last_name'=>'Layout'];$csrf='fixture-csrf';$title='Richieste EasyTool';$status='';
$rows=[['id'=>42,'created_at'=>'2026-10-02 10:00:00','first_name'=>'<Test>','last_name'=>'Fixture','email'=>'test@example.invalid','city'=>'Test','province'=>'MI','professional_role'=>'Installatore','status'=>'new']];
foreach([false,true] as $trashed){
 ob_start();require dirname(__DIR__).'/app/Views/admin/incentive_requests.php';$html=ob_get_clean();
 $check=static function(bool $ok,string $message):void {if(!$ok)throw new \RuntimeException($message);};
 $check(str_contains($html,'&lt;Test&gt;'),'Names must be escaped');
 $check(str_contains($html,'fixture-csrf'),'Actions require CSRF token');
 $check(str_contains($html,'/incentives/'.($trashed?'restore':'delete').'"'),'Correct reversible action');
 $check(str_contains($html,'/incentives/purge"')===$trashed,'Permanent deletion only in trash');
 $check(str_contains($html,'name="confirm_permanent"')===$trashed,'Permanent deletion confirmation');
 $check(str_contains($html,'name="trash" value="'.($trashed?'1':'0').'"'),'Filter preserves trash state');
}
$controller=file_get_contents(dirname(__DIR__).'/app/Controllers/Admin/IncentivesController.php');
$check(str_contains($controller,"DELETE FROM incentive_requests WHERE id=? AND deleted_at IS NOT NULL"),'Cannot purge active requests');
$check(str_contains($controller,"SELECT status FROM incentive_requests WHERE id=? AND deleted_at IS NULL"),'Cannot update trashed requests');
echo "EasyTool trash regression OK\n";
}
