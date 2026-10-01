<?php $title='Categorie e serie'; require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Categorie e serie</h1><p class="muted">Gerarchia gamme/famiglie/sottocategorie.</p></div><a class="btnlink" href="/idemaclima/admin/categories/form">Nuova categoria</a></div>
<?php if (($_GET['deleted'] ?? '') === '1'): ?>
<div style="margin:0 0 16px;padding:12px 14px;border:1px solid #86c995;border-radius:8px;background:#edf9f0;color:#17652a">Categoria eliminata correttamente.</div>
<?php elseif (isset($_GET['delete_error'])): ?>
<div style="margin:0 0 16px;padding:12px 14px;border:1px solid #e3a2a2;border-radius:8px;background:#fff1f1;color:#8b1f1f"><?php
$error=(string)$_GET['delete_error'];
echo htmlspecialchars($error==='not-empty'?'La categoria non può essere eliminata perché contiene sottocategorie o prodotti.':($error==='not-found'?'Categoria non trovata.':'Impossibile eliminare la categoria perché risulta ancora collegata ad altri contenuti.'),ENT_QUOTES,'UTF-8');
?></div>
<?php endif; ?>
<style>
.sort-head{color:inherit;text-decoration:none}.sort-head:hover{color:var(--blue)}.sort-head[aria-current="true"]{color:var(--blue)}
.category-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;white-space:nowrap}.category-actions form{margin:0}.delete-category{border:0;background:transparent;color:#b42318;text-decoration:underline;cursor:pointer;padding:0;font:inherit}.delete-category:hover{color:#7a1710}
</style>
<?php $sortLink=static function(string $key,string $label)use($sort,$dir):string{$next=$sort===$key&&$dir==='ASC'?'desc':'asc';$arrow=$sort===$key?($dir==='ASC'?' ↑':' ↓'):'';return '<a class="sort-head" '.($sort===$key?'aria-current="true"':'').' href="?'.http_build_query(['sort'=>$key,'dir'=>$next]).'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').$arrow.'</a>';}; ?>
<div style="overflow:auto"><table><thead><tr><th><?= $sortLink('name','Categoria / serie') ?></th><th><?= $sortLink('parent','Livello superiore') ?></th><th><?= $sortLink('slug','Slug') ?></th><th><?= $sortLink('order','Ordine') ?></th><th><?= $sortLink('status','Stato') ?></th><th></th></tr></thead><tbody>
<?php foreach($categories as $c): $canDelete=(int)($c['child_count']??0)===0&&(int)($c['product_count']??0)===0; ?>
<tr>
<td><strong><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></strong></td>
<td><?= htmlspecialchars((string)($c['parent_name']??'Categoria principale'),ENT_QUOTES,'UTF-8') ?></td>
<td class="muted"><?= htmlspecialchars($c['slug'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= (int)$c['sort_order'] ?></td>
<td><span class="badge"><?= htmlspecialchars(['draft'=>'Bozza','published'=>'Pubblicata','hidden'=>'Nascosta'][$c['content_status']??'']??($c['published']?'Pubblicata':'Nascosta'),ENT_QUOTES,'UTF-8') ?></span></td>
<td><div class="category-actions"><a href="/idemaclima/admin/categories/form?id=<?= (int)$c['id'] ?>">Modifica</a><?php if($canDelete): ?><form method="post" action="/idemaclima/admin/categories/delete" onsubmit="return confirm('Eliminare definitivamente la categoria «<?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?>»?');"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="delete-category" type="submit">Elimina</button></form><?php endif; ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>