<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use PDO;

final class ReferenceImportController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        $report = null;
        $error = null;
        require dirname(__DIR__, 2) . '/Views/admin/reference_import.php';
    }

    public static function run(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }
        $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['dry-run', 'execute'], true)) {
            http_response_code(400);
            exit('Operazione non valida');
        }
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        $report = null;
        $error = null;
        try {
            $report = self::import($action === 'execute');
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }
        require dirname(__DIR__, 2) . '/Views/admin/reference_import.php';
    }

    private static function import(bool $execute): array
    {
        $pdo = Database::connection();
        $rows = self::sourceRows();
        $report = ['mode' => $execute ? 'execute' : 'dry-run', 'source' => count($rows), 'new' => 0, 'existing' => 0, 'downloaded' => 0, 'updated' => 0];
        foreach ($rows as &$row) {
            $row['slug'] = self::slugify($row['title'] . '-' . $row['location']);
            $statement = $pdo->prepare('SELECT id FROM references_projects WHERE slug=? LIMIT 1');
            $statement->execute([$row['slug']]);
            $row['existing_id'] = (int) ($statement->fetchColumn() ?: 0);
            $report[$row['existing_id'] ? 'existing' : 'new']++;
        }
        unset($row);
        if (!$execute) {
            return $report;
        }

        $root = dirname(__DIR__, 3);
        $relativeDir = '/uploads/references/importate';
        $diskDir = $root . '/public' . $relativeDir;
        if (!is_dir($diskDir) && !mkdir($diskDir, 0775, true) && !is_dir($diskDir)) {
            throw new \RuntimeException('Impossibile creare la cartella immagini delle referenze.');
        }
        $createdFiles = [];
        $pdo->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $fileName = sprintf('%02d-%s.jpg', $index + 1, $row['slug']);
                $diskPath = $diskDir . '/' . $fileName;
                $publicPath = '/idemaclima' . $relativeDir . '/' . $fileName;
                if (!is_file($diskPath) || filesize($diskPath) < 1024) {
                    self::downloadImage($row['image'], $diskPath);
                    $createdFiles[] = $diskPath;
                    $report['downloaded']++;
                }
                $sort = ($index + 1) * 10;
                if ($row['existing_id']) {
                    $id = $row['existing_id'];
                    $pdo->prepare('UPDATE references_projects SET title=?,location=?,short_description=?,description=?,cover_image=?,published=1,sort_order=?,archived_at=NULL WHERE id=?')
                        ->execute([$row['title'], $row['location'], $row['system'], $row['system'], $publicPath, $sort, $id]);
                    $report['updated']++;
                } else {
                    $pdo->prepare('INSERT INTO references_projects(title,slug,location,project_year,short_description,description,cover_image,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')
                        ->execute([$row['title'], $row['slug'], $row['location'], null, $row['system'], $row['system'], $publicPath, 1, $sort]);
                    $id = (int) $pdo->lastInsertId();
                }
                $image = $pdo->prepare('SELECT id FROM reference_images WHERE reference_id=? ORDER BY id LIMIT 1');
                $image->execute([$id]);
                $imageId = (int) ($image->fetchColumn() ?: 0);
                if ($imageId) {
                    $pdo->prepare('UPDATE reference_images SET image_path=?,alt_text=?,caption=?,published=1,sort_order=10 WHERE id=?')
                        ->execute([$publicPath, $row['title'], $row['system'], $imageId]);
                } else {
                    $pdo->prepare('INSERT INTO reference_images(reference_id,image_path,alt_text,caption,published,sort_order) VALUES(?,?,?,?,1,10)')
                        ->execute([$id, $publicPath, $row['title'], $row['system']]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($createdFiles as $path) @unlink($path);
            throw $exception;
        }
        return $report;
    }

    private static function downloadImage(string $url, string $destination): void
    {
        $ch = curl_init($url);
        if ($ch === false) throw new \RuntimeException('Impossibile inizializzare il download.');
        $handle = fopen($destination, 'wb');
        if ($handle === false) throw new \RuntimeException('Impossibile salvare un’immagine.');
        curl_setopt_array($ch, [CURLOPT_FILE => $handle, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 12, CURLOPT_TIMEOUT => 45, CURLOPT_USERAGENT => 'IDEMA Reference Import/1.0', CURLOPT_FAILONERROR => true]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);
        if (!$ok || !is_file($destination) || filesize($destination) < 1024) {
            @unlink($destination);
            throw new \RuntimeException('Download immagine non riuscito: ' . ($error ?: $url));
        }
    }

    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtolower(trim($value))) ?: $value;
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }

    private static function sourceRows(): array
    {
        $data = [
            ['Vertemate con Minoprio (CO)','Idema Clima S.r.l.','Sistema VRF da 73 kW','https://www.idemaclima.it/wp-content/uploads/referenza-000.jpg'],
            ['Genova','Sede Logistica GLS','Chiller modulare da 55 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/f8/referenza-038-f802c25e.jpg'],
            ['Lissone (MB)','Pizzeria Donn’Angelin','Mini Chiller da 7 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/ca/referenza-031-ca4980eb.jpg'],
            ['Rivolta d’Adda (CR)','Avisco S.p.a.','Mini Chiller da 16 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/36/referenza-030-36646c49.jpg'],
            ['Osnago (LC)','Energy Solution S.r.l.','Mini Chiller da 16 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/4a/referenza-032-4a1dca31.jpg'],
            ['Milano','Cordusio Sim S.p.a.','Rooftop da 26 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/f3/referenza-036-f373138e.jpg'],
            ['Milano (zona Lorenteggio)','Ristorante Zio Provolone','Rooftop da 26 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/16/referenza-037-169fab9b.jpg'],
            ['Cantù (CO)','Palestra FitActive','Rooftop da 105 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/36/referenza-033-36b8d07a.jpg'],
            ['Milano','Radio Millenium','Sistema Mini VRF da 20 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/80/referenza-035-8010c262.jpg'],
            ['Garbagnate Milanese (MI)','A.B.A. S.r.l.','Sistema Mini VRF da 40 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/82/referenza-029-8243692b.jpg'],
            ['Prato','Mysea Bitstrot S.r.l.','Sistema VRF da 68 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/5b/referenza-034-5b518800.jpg'],
            ['Cologno Monzese (MI)','Uffici Came Più S.r.l.','Sistema VRF da 45 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/fc/referenza-004-fc80371e.jpg'],
            ['Marina di Campo, Isola d’Elba (LI)','Hotel Resort Montecristo','Sistema VRF da 74 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/91/referenza-010-91f92bba.jpg'],
            ['Milano','Hotel Genius Downtown','Sistema VRF da 33,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/4f/referenza-009-4f3fc7d4.jpg'],
            ['Milano','VIVA Hotel Milano','Sistema Mini VRF da 45 kW + VRF da 90 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/bc/referenza-011-bc0ae2b6.jpg'],
            ['Livigno (SO)','La Galleria','Sistema VRF da 73,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/ba/referenza-016-ba47a3e2.jpg'],
            ['Milano','Mipharm S.p.a.','Sistema VRF da 45 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/d8/referenza-019-d8d9a916.jpg'],
            ['Saronno (VA)','Residenza Miola','Sistema VRF da 90 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/14/referenza-026-14f48d3b.jpg'],
            ['Milano','Ospedale Luigi Sacco','Sistema Mini VRF da 26 kW + VRF da 45 kW + VRF da 28 kW + VRF da 40 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/3c/referenza-021-3c557367.jpg'],
            ['Burago di Molgora (MB)','Birrificio artigianale HIBU','Chiller da 35 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/70/referenza-008-70be99f9.jpg'],
            ['Como','Pasticceria Monti','Sistema Mini VRF da 26 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/65/referenza-023-65bd9325.jpg'],
            ['Bergamo','Aperigelato','Sistema Mini VRF da 14 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/94/referenza-001-94f7c065.jpg'],
            ['Catania','Profumerie Griffe','Sistema VRF da 45 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/d9/referenza-024-d9e49205.jpg'],
            ['Sondrio','United Colors of Benetton','Sistema VRF da 45 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/4d/referenza-003-4d897a9c.jpg'],
            ['Roncade (TV)','Estetica Emotion','Sistema Mini VRF da 26 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/9f/referenza-007-9ff20a12.jpg'],
            ['Castione Andevenno (SO)','Re del Mare','Sistema VRF da 45 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/a8/referenza-025-a864b9e8.jpg'],
            ['Brugherio (MB)','Maxi Zoo','Sistema VRF da 73,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/b8/referenza-018-b8c5992b.jpg'],
            ['Roma','Istituto Suore di San Giuseppe','Sistema VRF da 70 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/09/referenza-014-09f68386.jpg'],
            ['Morbegno (SO)','United Colors of Benetton','Sistema Mini VRF da 14 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/78/referenza-002-78e50d91.jpg'],
            ['Napoli','Supermercato SISA','Sistema VRF da 100,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/7d/referenza-028-7dc0ca4c.jpg'],
            ['Vertemate con Minoprio (CO)','Lariotex S.r.l.','Sistema VRF da 70 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/c9/referenza-017-c9399bf5.jpg'],
            ['Brescia','Ideal Clima S.r.l.','Sistema VRF da 33,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/5b/referenza-012-5b58fa7f.jpg'],
            ['Bergamo','I.N.P.S. – Istituto Nazionale Previdenza Sociale','Sistema VRF da 121,2 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/28/referenza-013-285827fa.jpg'],
            ['Collegno (TO)','Italmacello','Sistema Mini VRF da 52,5 kW + VRF da 80 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/ed/referenza-015-ed4d48ec.jpg'],
            ['Verona','DOC Servizi SOC COOP','Sistema VRF da 100,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/d6/referenza-006-d69a896e.jpg'],
            ['Cologno Monzese (MI)','Uffici DICOEL S.r.l.','Sistema VRF da 33,5 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/0f/referenza-005-0f3c7f49.jpg'],
            ['Bellinzona (Svizzera)','Vitadomo – Medicina interna','Sistema Mini VRF da 18 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/db/referenza-039-db09280e.jpg'],
            ['Bellinzona (Svizzera)','Vitadomo – Fisioterapia Montebello','Sistema Mini VRF da 18 kW','https://www.idemaclima.it/wp-content/uploads/yootheme/cache/cb/referenza-040-cbe4ea26.jpg'],
        ];
        return array_map(static fn(array $r): array => ['location'=>$r[0], 'title'=>$r[1], 'system'=>$r[2], 'image'=>$r[3]], $data);
    }
}
