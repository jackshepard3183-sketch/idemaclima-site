<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$auth=file_get_contents($root.'/app/Auth/AdminAuth.php');
$controller=file_get_contents($root.'/app/Controllers/Admin/SettingsController.php');
$routes=file_get_contents($root.'/public/index.php');
$list=file_get_contents($root.'/app/Views/admin/settings_users.php');
$form=file_get_contents($root.'/app/Views/admin/settings_user_form.php');
foreach([$auth,$controller,$routes,$list,$form] as $content)if(!is_string($content)||$content==='')throw new RuntimeException('Gestione utenti non leggibile.');
$checks=[
 [$auth,'authorizeRequest','autorizzazione centralizzata'],
 [$auth,'admin_role_permissions WHERE role_slug=? AND section_key=?','permessi per ruolo e sezione'],
 [$auth,'if(!self::hasPermission($role,$section))','blocco sezioni non autorizzate'],
 [$controller,'password_hash($password,PASSWORD_DEFAULT)','password sicure'],
 [$controller,"strlen(\$password)<12",'lunghezza minima password'],
 [$controller,"\$id===(int)\$manager['id']&&!\$active",'protezione account corrente'],
 [$routes,"'/admin/settings/users/form'",'rotta form utenti'],
 [$routes,"'/admin/settings/users/save'",'rotta salvataggio utenti'],
 [$form,'autocomplete="new-password"','campo password protetto'],
 [$list,"\$roleMap[\$r['slug']]=\$r['name']",'etichette ruoli configurabili'],
];
foreach($checks as [$haystack,$needle,$label])if(!str_contains($haystack,$needle))throw new RuntimeException('Check utenti fallito: '.$label);
fwrite(STDOUT,"Admin users/permissions smoke OK\n");
