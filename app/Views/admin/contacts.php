<?php require __DIR__.'/_layout_start.php'; ?>
<?php
$sortKeys=['created_at','name','email','subject','status'];
$sort=in_array((string)($_GET['sort']??''),$sortKeys,true)?(string)$_GET['sort']:'created_at';
$dir=strtolower((string)($_GET['dir']??'desc'))==='asc'?'asc':'desc';
usort($rows,static function(array $a,array $b)use($sort,$dir):int{
    $value=static function(array $row,string $key):string{
        if($key==='name')return trim((string)($row['first_name']??'').' '.(string)($row['last_name']??''));
        return (string)($row[$key]??'');
    };
    $result=strnatcasecmp($value($a,$sort),$value($b,$sort));
    return $dir==='asc'?$result:-$result;
});
$sortHref=static function(string $key)use($sort,$dir,$status):string{
    return '?'.http_build_query(array_filter([
        'status'=>(string)($status??''),
        'sort'=>$key,
        'dir'=>$sort===$key&&$dir==='asc'?'desc':'asc',
    ],static fn($value):bool=>$value!==''));
};
$sortMark=static fn(string $key):string=>$sort===$key?($dir==='asc'?' ▲':' ▼'):' ↕';
?>
<style>
.contacts-table{min-width:820px}.contacts-table th{white-space:nowrap}.contacts-table .sort-link{display:inline-flex;align-items:center;gap:4px;color:inherit;text-decoration:none}.contacts-table .contact-datetime span{display:block;white-space:nowrap}.contacts-table .contact-subject{width:220px;max-width:220px;line-height:1.35;overflow-wrap:anywhere}.contacts-table .contact-action,.contacts-table .contact-action a{white-space:nowrap}
</style>
<div class="toolbar"><div><h1>Contatti</h1><p class="muted">Richieste inviate dal modulo pubblico.</p></div><a class="btnlink" href="/idemaclima/admin/contacts/import">Importa da WPForms</a></div>
<form method="get" class="panel" style="margin-bottom:18px"><div class="inlineform"><label>Stato<select name="status"><option value="">Tutti</option><?php foreach(['new'=>'Nuova','in_progress'=>'In lavorazione','closed'=>'Chiusa','spam'=>'Spam'] as $value=>$label):?><option value="<?=$value?>" <?=($status??'')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>"><button class="btn" type="submit">Filtra</button></div></form>
<div class="table-wrap"><table class="contacts-table"><thead><tr>
<th><a class="sort-link" href="<?= htmlspecialchars($sortHref('created_at')) ?>">Data e<br>ora<?= $sortMark('created_at') ?></a></th>
<th><a class="sort-link" href="<?= htmlspecialchars($sortHref('name')) ?>">Nome<?= $sortMark('name') ?></a></th>
<th><a class="sort-link" href="<?= htmlspecialchars($sortHref('email')) ?>">Email<?= $sortMark('email') ?></a></th>
<th><a class="sort-link" href="<?= htmlspecialchars($sortHref('subject')) ?>">Oggetto<?= $sortMark('subject') ?></a></th>
<th><a class="sort-link" href="<?= htmlspecialchars($sortHref('status')) ?>">Stato<?= $sortMark('status') ?></a></th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><?php $created=(string)$r['created_at'];$createdTs=strtotime($created); ?><tr>
<td class="contact-datetime"><span><?= htmlspecialchars($createdTs?date('d-m-Y',$createdTs):$created) ?></span><span><?= $createdTs?htmlspecialchars(date('H:i',$createdTs)):'' ?></span></td>
<td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td><td><?= htmlspecialchars($r['email']) ?></td>
<td class="contact-subject"><?= htmlspecialchars($r['subject']) ?><?php if(!empty($r['import_review_warning'])):?><br><span class="badge" style="white-space:nowrap">Da verificare</span><?php endif;?></td>
<td><span class="badge" style="white-space:nowrap"><?= htmlspecialchars($r['status']) ?></span></td><td class="contact-action"><a href="/idemaclima/admin/contacts/view?id=<?= (int)$r['id'] ?>">Apri</a></td></tr><?php endforeach; ?><?php if(!$rows):?><tr><td colspan="6" class="empty">Nessuna richiesta.</td></tr><?php endif;?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
