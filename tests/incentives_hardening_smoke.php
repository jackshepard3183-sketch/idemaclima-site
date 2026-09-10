<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
 'public controller'=>file_get_contents($root.'/app/Controllers/Public/IncentivesController.php'),
 'admin controller'=>file_get_contents($root.'/app/Controllers/Admin/IncentivesController.php'),
 'public page'=>file_get_contents($root.'/app/Views/public/editorial/incentives.php'),
 'EasyTool form'=>file_get_contents($root.'/app/Views/public/editorial/incentives_easytool_form.php'),
 'router'=>file_get_contents($root.'/public/index.php'),
 'migration'=>file_get_contents($root.'/database/migrations/030_incentive_requests.sql'),
 'mail service'=>file_get_contents($root.'/app/Services/IncentivesMailService.php'),
];
foreach($files as $label=>$content)if(!is_string($content)||$content==='')throw new RuntimeException('File non leggibile: '.$label);
$checks=[
 ['public controller','RateLimiter::allow','rate limit'],['public controller','Security::verifyCsrf','CSRF'],
 ['public controller','company_website','honeypot'],['public controller','email_confirm','conferma email'],
 ['admin controller',"Audit::log('incentive_request.update'",'audit'],['router',"->post('/detrazioni-e-incentivi/consulenza-energetica'",'route pubblica'],
 ['router',"->get('/admin/incentives'",'route amministrativa'],['EasyTool form','warranty-region','selettore regioni'],
 ['EasyTool form','privacy-policy/38092343','privacy iubenda'],['public page','documentGroups','documenti backend'],
 ['migration','incentive_requests','schema richieste'],
 ['mail service','incentives_recipients','destinatari configurabili'],['mail service','Reply-To:','risposta al richiedente'],
];
foreach($checks as [$file,$needle,$label])if(!str_contains($files[$file],$needle))throw new RuntimeException('Check Detrazioni fallito: '.$label);
foreach(['name="message"','type="file"','Schede tecniche dei prodotti'] as $forbidden)if(str_contains($files['EasyTool form'],$forbidden)||str_contains($files['public page'],$forbidden))throw new RuntimeException('Campo o CTA non consentito: '.$forbidden);
foreach(['first_name','last_name','region','province','city','postal_code','phone','email','email_confirm','role'] as $field)if(!str_contains($files['EasyTool form'],'name="'.$field.'"'))throw new RuntimeException('Campo EasyTool mancante: '.$field);
fwrite(STDOUT,"Incentives hardening smoke OK\n");
