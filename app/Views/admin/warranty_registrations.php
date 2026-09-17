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
?>
<style>
.warranty-search{display:flex;gap:10px;align-items:end;margin:0 0 18px}.warranty-search label{flex:1;margin:0}.warranty-search .btnlink{white-space:nowrap}.warranty-status-badge{white-space:nowrap!important;word-break:normal!important}.warranty-result-count{margin:8px 0 0}
@media(max-width:760px){.warranty-search{align-items:stretch;flex-direction:column}.warranty-search .btn,.warranty-search .btnlink{width:100%;text-align:center}}
</style>
<div class="toolbar"><div><h1>Registrazioni garanzia</h1><p class="muted">Richieste ricevute dal modulo pubblico.</p></div><div><a class="btnlink" href="/idemaclima/admin/warranties?import=1">Importa WPForms</a> <a class="btnlink" href="/idemaclima/admin/warranties/certificate-layout">Editor PDF</a> <a class="btnlink" href="/idemaclima/admin/warranties/rules">Regole garanzia</a></div></div>
<form class="warranty-search" method="get" action="/idemaclima/admin/warranties">
    <label>Cerca nelle garanzie
        <input type="search" name="q" value="<?= htmlspecialchars($warrantySearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Codice IDM, cliente, email, modello o stato">
    </label>
    <button class="btn" type="submit">Cerca</button>
    <?php if ($warrantySearch !== ''): ?><a class="btnlink" href="/idemaclima/admin/warranties">Azzera ricerca</a><?php endif; ?>
</form>
<?php if ($warrantySearch !== ''): ?><p class="muted warranty-result-count"><?= count($filteredRegistrations) ?> risultat<?= count($filteredRegistrations) === 1 ? 'o' : 'i' ?> per “<?= htmlspecialchars($warrantySearch, ENT_QUOTES, 'UTF-8') ?>”.</p><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Cliente</th><th>Prodotto / Modello</th><th>Data fattura</th><th>Garanzia</th><th>Stato</th><th>Verifica</th><th>Ricevuta</th></tr></thead><tbody>
<?php foreach($filteredRegistrations as $r): ?><tr><td><a href="/idemaclima/admin/warranties/registration?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars((string)($r['certificate_number'] ?? '—'),ENT_QUOTES,'UTF-8') ?></a></td><td><?= htmlspecialchars($r['customer_last_name'].' '.$r['customer_first_name']) ?><br><span class="muted"><?= htmlspecialchars($r['email']) ?></span></td><td><?= htmlspecialchars($r['product_name']) ?><br><span class="muted"><?= htmlspecialchars($r['code']) ?></span></td><td><?= htmlspecialchars($r['invoice_date']) ?></td><td><?= (int)$r['warranty_years'] ?> anni</td><td><span class="badge warranty-status-badge"><?= htmlspecialchars($r['status']) ?></span></td><td><?php if(!empty($r['import_review_warning'])): ?><span class="badge warranty-status-badge" style="background:#fef3c7;color:#92400e" title="<?= htmlspecialchars((string)$r['import_review_warning']) ?>">Da verificare</span><br><small><?= htmlspecialchars((string)$r['import_review_warning']) ?></small><?php else: ?><span class="muted">—</span><?php endif; ?></td><td><?= htmlspecialchars($r['created_at']) ?></td></tr><?php endforeach; ?>
<?php if (!$filteredRegistrations): ?><tr><td colspan="8" class="muted">Nessuna garanzia trovata.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
