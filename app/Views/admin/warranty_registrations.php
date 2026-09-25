<?php
require __DIR__.'/_layout_start.php';

$warrantySearch = trim((string)($_GET['q'] ?? ''));
$filteredRegistrations = $registrations;
if ($warrantySearch !== '') {
    $filteredRegistrations = array_values(array_filter($registrations, static function (array $registration) use ($warrantySearch): bool {
        $haystack = implode(' ', [
            (string)($registration['certificate_number'] ?? ''),
            (string)($registration['customer_first_name'] ?? ''),
            (string)($registration['customer_last_name'] ?? ''),
            (string)($registration['email'] ?? ''),
            (string)($registration['product_name'] ?? ''),
            (string)($registration['code'] ?? ''),
            (string)($registration['status'] ?? ''),
            (string)($registration['import_review_warning'] ?? ''),
        ]);
        return mb_stripos($haystack, $warrantySearch, 0, 'UTF-8') !== false;
    }));
}

$sortableColumns = [
    'id' => 'ID',
    'customer' => 'Cliente',
    'product' => 'Prodotto / Modello',
    'invoice_date' => 'Data fattura',
    'warranty' => 'Garanzia',
    'status' => 'Stato',
    'verification' => 'Verifica',
    'received' => 'Data ricevuta',
];
$warrantySort = (string)($_GET['sort'] ?? '');
$warrantyDirection = strtolower((string)($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
if (!array_key_exists($warrantySort, $sortableColumns)) $warrantySort = '';

$sortValue = static function (array $row, string $column): string|int {
    return match ($column) {
        'id' => (string)($row['certificate_number'] ?? ''),
        'customer' => mb_strtolower(trim((string)($row['customer_last_name'] ?? '').' '.(string)($row['customer_first_name'] ?? '')), 'UTF-8'),
        'product' => mb_strtolower(trim((string)($row['product_name'] ?? '').' '.(string)($row['code'] ?? '')), 'UTF-8'),
        'invoice_date' => strtotime((string)($row['invoice_date'] ?? '')) ?: 0,
        'warranty' => (int)($row['warranty_years'] ?? 0),
        'status' => mb_strtolower((string)($row['status'] ?? ''), 'UTF-8'),
        'verification' => empty($row['import_review_warning']) ? '' : mb_strtolower((string)$row['import_review_warning'], 'UTF-8'),
        'received' => strtotime((string)($row['created_at'] ?? '')) ?: 0,
        default => '',
    };
};
if ($warrantySort !== '') {
    usort($filteredRegistrations, static function (array $a, array $b) use ($sortValue, $warrantySort, $warrantyDirection): int {
        $left = $sortValue($a, $warrantySort);
        $right = $sortValue($b, $warrantySort);
        $comparison = is_int($left) && is_int($right) ? $left <=> $right : strnatcasecmp((string)$left, (string)$right);
        if ($comparison === 0) $comparison = ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
        return $warrantyDirection === 'desc' ? -$comparison : $comparison;
    });
}
$sortUrl = static function (string $column) use ($warrantySearch, $warrantySort, $warrantyDirection): string {
    $nextDirection = $warrantySort === $column && $warrantyDirection === 'asc' ? 'desc' : 'asc';
    $query = ['sort'=>$column, 'dir'=>$nextDirection];
    if ($warrantySearch !== '') $query['q'] = $warrantySearch;
    return '/idemaclima/admin/warranties?'.http_build_query($query);
};
$formatWarrantyDate = static function (mixed $value): string {
    $value = trim((string)$value);
    if ($value === '') return '—';
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('d-m-Y', $timestamp);
};
?>
<style>
.warranty-search{display:flex;gap:10px;align-items:end;margin:0 0 18px}.warranty-search label{flex:1;margin:0}.warranty-search .btnlink{white-space:nowrap}.warranty-status-badge{white-space:nowrap!important;word-break:normal!important}.warranty-result-count{margin:8px 0 0}.warranty-date{white-space:nowrap!important}.sortable-header{white-space:nowrap}.sortable-header a{display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none}.sortable-header a:hover,.sortable-header a:focus-visible{color:var(--blue);text-decoration:underline}.sort-indicator{color:var(--blue);font-size:12px}.sort-hint{margin:0 0 10px}
@media(max-width:760px){.warranty-search{align-items:stretch;flex-direction:column}.warranty-search .btn,.warranty-search .btnlink{width:100%;text-align:center}}
</style>
<div class="toolbar"><div><h1>Registrazioni garanzia</h1><p class="muted">Richieste ricevute dal modulo pubblico.</p></div><div><a class="btnlink" href="/idemaclima/admin/warranties?import=1">Importa WPForms</a> <a class="btnlink" href="/idemaclima/admin/warranties/certificate-layout">Editor PDF</a> <a class="btnlink" href="/idemaclima/admin/warranties/rules">Regole garanzia</a></div></div>
<form class="warranty-search" method="get" action="/idemaclima/admin/warranties">
    <label>Cerca nelle garanzie
        <input type="search" name="q" value="<?= htmlspecialchars($warrantySearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Codice IDM, cliente, email, modello o stato">
    </label>
    <?php if($warrantySort!==''): ?><input type="hidden" name="sort" value="<?=htmlspecialchars($warrantySort,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="dir" value="<?=htmlspecialchars($warrantyDirection,ENT_QUOTES,'UTF-8')?>"><?php endif; ?>
    <button class="btn" type="submit">Cerca</button>
    <?php if ($warrantySearch !== ''): ?><a class="btnlink" href="/idemaclima/admin/warranties<?= $warrantySort!==''?'?'.http_build_query(['sort'=>$warrantySort,'dir'=>$warrantyDirection]):'' ?>">Azzera ricerca</a><?php endif; ?>
</form>
<?php if ($warrantySearch !== ''): ?><p class="muted warranty-result-count"><?= count($filteredRegistrations) ?> risultat<?= count($filteredRegistrations) === 1 ? 'o' : 'i' ?> per “<?= htmlspecialchars($warrantySearch, ENT_QUOTES, 'UTF-8') ?>”.</p><?php endif; ?>
<p class="muted sort-hint">Seleziona un’intestazione per ordinare la colonna; selezionala nuovamente per invertire l’ordine.</p>
<div class="table-wrap"><table><thead><tr>
<?php foreach($sortableColumns as $column=>$label): $activeSort=$warrantySort===$column; ?>
<th class="sortable-header" <?= $activeSort?'aria-sort="'.($warrantyDirection==='asc'?'ascending':'descending').'"':'' ?>><a href="<?=htmlspecialchars($sortUrl($column),ENT_QUOTES,'UTF-8')?>"><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?> <span class="sort-indicator" aria-hidden="true"><?= $activeSort?($warrantyDirection==='asc'?'▲':'▼'):'↕' ?></span></a></th>
<?php endforeach; ?>
</tr></thead><tbody>
<?php foreach($filteredRegistrations as $r): ?><tr><td><a href="/idemaclima/admin/warranties/registration?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars((string)($r['certificate_number'] ?? '—'),ENT_QUOTES,'UTF-8') ?></a></td><td><?= htmlspecialchars($r['customer_last_name'].' '.$r['customer_first_name']) ?><br><span class="muted"><?= htmlspecialchars($r['email']) ?></span></td><td><?= htmlspecialchars($r['product_name']) ?><br><span class="muted"><?= htmlspecialchars($r['code']) ?></span></td><td class="warranty-date" data-sort-value="<?=htmlspecialchars((string)($r['invoice_date']??''),ENT_QUOTES,'UTF-8')?>"><?= htmlspecialchars($formatWarrantyDate($r['invoice_date']??''),ENT_QUOTES,'UTF-8') ?></td><td><?= $r['warranty_years'] === null ? 'Da verificare' : (int)$r['warranty_years'].' anni' ?></td><td><span class="badge warranty-status-badge"><?= htmlspecialchars($r['status']) ?></span></td><td><?php if(!empty($r['import_review_warning'])): ?><span class="badge warranty-status-badge" style="background:#fef3c7;color:#92400e" title="<?= htmlspecialchars((string)$r['import_review_warning']) ?>">Da verificare</span><br><small><?= htmlspecialchars((string)$r['import_review_warning']) ?></small><?php else: ?><span class="muted">—</span><?php endif; ?></td><td class="warranty-date" data-sort-value="<?=htmlspecialchars((string)($r['created_at']??''),ENT_QUOTES,'UTF-8')?>"><?= htmlspecialchars($formatWarrantyDate($r['created_at']??''),ENT_QUOTES,'UTF-8') ?></td></tr><?php endforeach; ?>
<?php if (!$filteredRegistrations): ?><tr><td colspan="8" class="muted">Nessuna garanzia trovata.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>

