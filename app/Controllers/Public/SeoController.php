<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Seo;
use PDO;
use Throwable;

final class SeoController
{
    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $base = Seo::publicBaseUrl();
        $urls = [];
        $add = static function (string $path, ?string $lastmod = null) use (&$urls, $base): void {
            $path = Seo::normalizePath($path);
            if ($path === '') return;
            $urls[$path] = ['loc' => $base . ($path === '/' ? '/' : $path), 'lastmod' => $lastmod];
        };

        foreach (['/','/schede-tecniche','/cataloghi','/assistenza','/garanzia','/campus','/galleria','/referenze','/contatti','/faq','/configurazione-wi-fi','/detrazioni-e-incentivi','/detrazioni-e-incentivi/conto-termico','/schede-tecniche/dichiarazioni-conformita-ce'] as $path) $add($path);

        try {
            $pdo = Database::connection();

            foreach ($pdo->query('SELECT slug,updated_at FROM product_categories WHERE published=1 AND parent_id IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/schede-tecniche/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM product_categories WHERE published=1 AND parent_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/schede-tecniche/famiglia/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM products WHERE published=1')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/schede-tecniche/prodotto/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM gallery_albums WHERE published=1 AND archived_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/galleria/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM references_projects WHERE published=1 AND archived_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/referenze/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM events WHERE published=1 AND cancelled=0 AND audience="public"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/campus/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
            foreach ($pdo->query('SELECT slug,updated_at FROM editorial_pages WHERE published=1')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $add('/' . $row['slug'], self::dateOnly($row['updated_at'] ?? null));
            }
        } catch (Throwable) {
            // Static URLs remain available before all migrations are installed.
        }

        ksort($urls);
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $row) {
            echo '  <url><loc>' . htmlspecialchars($row['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if ($row['lastmod']) echo '<lastmod>' . htmlspecialchars($row['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
            echo "</url>\n";
        }
        echo "</urlset>\n";
    }

    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo "User-agent: *\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /campus/cat/\n";
        echo 'Sitemap: ' . Seo::publicBaseUrl() . "/sitemap.xml\n";
    }

    private static function dateOnly(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
