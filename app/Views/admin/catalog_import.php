<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Importazione catalogo | IDEMA Clima</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;color:#17202a;margin:0;padding:32px}
        main{max-width:980px;margin:auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 4px 18px #0001}
        h1{margin-top:0}.actions{display:flex;gap:12px;flex-wrap:wrap;margin:24px 0}
        button,a.button{border:0;border-radius:7px;padding:11px 16px;font-weight:700;text-decoration:none;cursor:pointer}
        button{background:#075a9c;color:#fff}.danger{background:#a61b1b}a.button{background:#e9eef3;color:#17202a}
        table{border-collapse:collapse;width:100%;margin-top:18px}th,td{padding:9px;border-bottom:1px solid #dfe5ea;text-align:left}
        .ok{background:#e9f7ef;padding:14px;border-left:4px solid #168249}.error{background:#fdeaea;padding:14px;border-left:4px solid #b42318}
        .note{background:#fff8df;padding:14px;border-left:4px solid #c18b00}.section{margin-top:30px;padding-top:22px;border-top:1px solid #dfe5ea}code{font-size:.92em}
    </style>
</head>
<body><main>
    <h1>Importazione catalogo IDEMA</h1>
    <p>Origine: <code>database/import/datasheets.ts</code>. Database: staging IDEMA separato da WordPress.</p>
    <div class="note">Esegui prima il controllo. L'importazione definitiva usa una transazione e viene annullata automaticamente in caso di errore.</div>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <div class="actions">
        <form method="post" action="/idemaclima/admin/catalog-import/run">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="dry-run">
            <button type="submit">Esegui controllo</button>
        </form>
        <form method="post" action="/idemaclima/admin/catalog-import/run" onsubmit="return confirm('Confermi l’importazione definitiva nel database IDEMA?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="execute">
            <button class="danger" type="submit">Importa nel database</button>
        </form>
        <a class="button" href="/idemaclima/admin">Torna al pannello</a>
    </div>

    <?php if (is_array($report)): $stats=$report['stats'] ?? []; ?>
        <p class="ok"><strong><?= ($report['mode'] ?? '') === 'execute' ? 'Importazione completata.' : 'Controllo completato senza scritture.' ?></strong></p>
        <table><tbody>
            <tr><th>Categorie principali</th><td><?= (int)($stats['root_categories'] ?? 0) ?></td></tr>
            <tr><th>Famiglie</th><td><?= (int)($stats['subcategories'] ?? 0) ?></td></tr>
            <tr><th>Prodotti</th><td><?= (int)($stats['products'] ?? 0) ?></td></tr>
            <tr><th>Prodotti non disponibili</th><td><?= (int)($stats['products_unavailable'] ?? 0) ?></td></tr>
            <tr><th>Coppie prodotto/modello</th><td><?= (int)($stats['model_pairs_unique'] ?? 0) ?></td></tr>
            <tr><th>Documenti unici</th><td><?= (int)($stats['unique_documents'] ?? 0) ?></td></tr>
            <tr><th>Collegamenti documenti</th><td><?= (int)($stats['document_entries'] ?? 0) ?></td></tr>
            <tr><th>Etichette ambigue</th><td><?= (int)($stats['ambiguous_labels'] ?? 0) ?></td></tr>
            <tr><th>Avvisi</th><td><?= count($report['warnings'] ?? []) ?></td></tr>
        </tbody></table>
    <?php endif; ?>

    <section class="section">
        <h2>Collegamenti PDF ufficiali</h2>
        <p>Sostituisce i collegamenti temporanei Lovable con i PDF già pubblicati sul sito ufficiale IDEMA, mantenendo invariati prodotti, modelli e associazioni.</p>
        <div class="actions">
            <form method="post" action="/idemaclima/admin/catalog-import/run">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="repair-check">
                <button type="submit">Controlla collegamenti PDF</button>
            </form>
            <form method="post" action="/idemaclima/admin/catalog-import/run" onsubmit="return confirm('Confermi la correzione dei collegamenti PDF nel database IDEMA?')">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="repair-execute">
                <button class="danger" type="submit">Correggi collegamenti PDF</button>
            </form>
        </div>

        <?php if (is_array($urlRepair)): ?>
            <p class="ok"><strong><?= ($urlRepair['mode'] ?? '') === 'execute' ? 'Collegamenti PDF corretti.' : 'Controllo collegamenti completato senza scritture.' ?></strong></p>
            <table><tbody>
                <tr><th>Collegamenti temporanei trovati</th><td><?= (int)($urlRepair['matched'] ?? 0) ?></td></tr>
                <tr><th>Collegamenti aggiornati</th><td><?= (int)($urlRepair['updated'] ?? 0) ?></td></tr>
                <?php foreach (($urlRepair['columns'] ?? []) as $column): ?>
                    <tr><th>Campo <?= htmlspecialchars((string)($column['column'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th><td><?= (int)($column['count'] ?? 0) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
        <?php endif; ?>
    </section>
</main></body></html>
