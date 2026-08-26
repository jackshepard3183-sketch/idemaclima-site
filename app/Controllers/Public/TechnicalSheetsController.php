<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use PDO;

final class TechnicalSheetsController
{
    public static function index(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT c.id, c.name, c.slug, c.sort_order,
                    COUNT(DISTINCT p.id) AS product_count,
                    COUNT(DISTINCT d.id) AS document_count
             FROM product_categories c
             LEFT JOIN product_categories child ON child.parent_id = c.id AND child.published = 1
             LEFT JOIN products p ON p.category_id = child.id AND p.published = 1
             LEFT JOIN document_links dl ON dl.product_id = p.id
             LEFT JOIN documents d ON d.id = dl.document_id AND d.published = 1
             WHERE c.parent_id IS NULL AND c.published = 1
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        );
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::render('technical_sheets/index', [
            'title' => 'Schede tecniche',
            'categories' => $categories,
        ]);
    }

    public static function category(string $slug): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, slug FROM product_categories WHERE slug = ? AND parent_id IS NULL AND published = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            self::notFound();
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.slug, c.sort_order,
                    COUNT(DISTINCT p.id) AS product_count
             FROM product_categories c
             LEFT JOIN products p ON p.category_id = c.id AND p.published = 1
             WHERE c.parent_id = ? AND c.published = 1
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        );
        $stmt->execute([(int)$category['id']]);
        $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::render('technical_sheets/category', [
            'title' => $category['name'] . ' - Schede tecniche',
            'category' => $category,
            'families' => $families,
        ]);
    }

    public static function family(string $slug): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.slug, parent.name AS parent_name, parent.slug AS parent_slug
             FROM product_categories c
             JOIN product_categories parent ON parent.id = c.parent_id
             WHERE c.slug = ? AND c.published = 1 LIMIT 1'
        );
        $stmt->execute([$slug]);
        $family = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$family) {
            self::notFound();
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.description, p.product_role, p.refrigerant,
                    p.status, p.image_path, p.sort_order,
                    COUNT(DISTINCT m.id) AS model_count,
                    COUNT(DISTINCT d.id) AS document_count
             FROM products p
             LEFT JOIN product_models m ON m.product_id = p.id AND m.published = 1
             LEFT JOIN document_links dl ON dl.product_id = p.id
             LEFT JOIN documents d ON d.id = dl.document_id AND d.published = 1
             WHERE p.category_id = ? AND p.published = 1
             GROUP BY p.id
             ORDER BY (p.status = "active") DESC, p.sort_order, p.name'
        );
        $stmt->execute([(int)$family['id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        self::render('technical_sheets/family', [
            'title' => $family['name'] . ' - Schede tecniche',
            'family' => $family,
            'products' => $products,
        ]);
    }

    public static function product(string $slug): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.name AS family_name, c.slug AS family_slug,
                    parent.name AS category_name, parent.slug AS category_slug
             FROM products p
             JOIN product_categories c ON c.id = p.category_id
             LEFT JOIN product_categories parent ON parent.id = c.parent_id
             WHERE p.slug = ? AND p.published = 1 LIMIT 1'
        );
        $stmt->execute([$slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            self::notFound();
            return;
        }

        $stmt = $pdo->prepare('SELECT id, code, name, sort_order FROM product_models WHERE product_id = ? AND published = 1 ORDER BY sort_order, code');
        $stmt->execute([(int)$product['id']]);
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            'SELECT d.id, d.title, d.filename, d.file_path, d.revision, d.document_year,
                    dt.name AS type_name, dt.slug AS type_slug, dl.model_id
             FROM document_links dl
             JOIN documents d ON d.id = dl.document_id AND d.published = 1
             JOIN document_types dt ON dt.id = d.document_type_id AND dt.active = 1
             WHERE dl.product_id = ?
             ORDER BY dt.sort_order, dt.name, d.sort_order, d.title'
        );
        $stmt->execute([(int)$product['id']]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($documents as $document) {
            $grouped[$document['type_name']][] = $document;
        }

        self::render('technical_sheets/product', [
            'title' => $product['name'] . ' - Schede tecniche',
            'product' => $product,
            'models' => $models,
            'documentGroups' => $grouped,
        ]);
    }

    private static function render(string $view, array $data): void
    {
        extract($data, EXTR_SKIP);
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__, 2) . '/Views/public/_layout_start.php';
        require dirname(__DIR__, 2) . '/Views/public/' . $view . '.php';
        require dirname(__DIR__, 2) . '/Views/public/_layout_end.php';
    }

    private static function notFound(): void
    {
        http_response_code(404);
        self::render('404', ['title' => 'Pagina non trovata']);
    }
}
