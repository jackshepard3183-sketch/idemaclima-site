<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Url;
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
             LEFT JOIN product_category_links pcl ON pcl.category_id = child.id
             LEFT JOIN products p ON p.published = 1 AND (p.category_id = child.id OR p.id = pcl.product_id)
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

    public static function search(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $results = [];

        if ($query !== '') {
            $pdo = Database::connection();
            $like = '%' . $query . '%';
            $stmt = $pdo->prepare(
                'SELECT DISTINCT p.id, p.name, p.slug, p.status, p.refrigerant,
                        c.name AS family_name, c.slug AS family_slug, parent.name AS category_name, parent.slug AS category_slug,
                        GROUP_CONCAT(DISTINCT m.code ORDER BY m.code SEPARATOR ", ") AS matched_models,
                        GROUP_CONCAT(DISTINCT d.title ORDER BY d.title SEPARATOR " | ") AS matched_documents
                 FROM products p
                 JOIN product_categories c ON c.id = p.category_id
                 LEFT JOIN product_categories parent ON parent.id = c.parent_id
                 LEFT JOIN product_models m ON m.product_id = p.id AND m.published = 1
                 LEFT JOIN document_links dl ON dl.product_id = p.id
                 LEFT JOIN documents d ON d.id = dl.document_id AND d.published = 1
                 WHERE p.published = 1
                   AND (p.name LIKE ? OR p.description LIKE ? OR m.code LIKE ? OR d.title LIKE ? OR d.filename LIKE ?)
                 GROUP BY p.id
                 ORDER BY (p.status = "active") DESC, p.name
                 LIMIT 100'
            );
            $stmt->execute([$like, $like, $like, $like, $like]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        self::render('technical_sheets/search', [
            'title' => 'Ricerca schede tecniche',
            'query' => $query,
            'results' => $results,
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
             LEFT JOIN product_category_links pcl ON pcl.category_id = c.id
             LEFT JOIN products p ON p.published = 1 AND (p.category_id = c.id OR p.id = pcl.product_id)
             WHERE c.parent_id = ? AND c.published = 1
             GROUP BY c.id
             HAVING COUNT(DISTINCT p.id) > 0
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
             LEFT JOIN product_category_links pcl ON pcl.product_id = p.id AND pcl.category_id = ?
             LEFT JOIN product_models m ON m.product_id = p.id AND m.published = 1
             LEFT JOIN document_links dl ON dl.product_id = p.id
             LEFT JOIN documents d ON d.id = dl.document_id AND d.published = 1
             WHERE p.published = 1 AND (p.category_id = ? OR pcl.category_id IS NOT NULL)
             GROUP BY p.id
             ORDER BY (p.status = "active") DESC, p.sort_order, p.name'
        );
        $stmt->execute([(int)$family['id'], (int)$family['id']]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $productDetails = self::productDetails($pdo, array_map(static fn(array $row): int => (int)$row['id'], $products));

        self::render('technical_sheets/family', [
            'title' => $family['name'] . ' - Schede tecniche',
            'family' => $family,
            'products' => $products,
            'productDetails' => $productDetails,
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
        if (!self::hasDedicatedPage($product)) {
            header('Location: ' . Url::to('/schede-tecniche/famiglia/' . rawurlencode((string)$product['family_slug'])) . '#prodotto-' . rawurlencode((string)$product['slug']), true, 302);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.slug, parent.name AS parent_name, parent.slug AS parent_slug
             FROM product_category_links pcl
             JOIN product_categories c ON c.id = pcl.category_id AND c.published = 1
             LEFT JOIN product_categories parent ON parent.id = c.parent_id
             WHERE pcl.product_id = ?
             ORDER BY COALESCE(parent.sort_order, c.sort_order), c.sort_order, c.name'
        );
        $stmt->execute([(int)$product['id']]);
        $secondaryCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT id, code, name, sort_order FROM product_models WHERE product_id = ? AND published = 1 ORDER BY sort_order, code');
        $stmt->execute([(int)$product['id']]);
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT label, sort_order FROM product_features WHERE product_id = ? ORDER BY sort_order, id');
        $stmt->execute([(int)$product['id']]);
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT specification_key, specification_value, sort_order FROM product_specifications WHERE product_id = ? ORDER BY sort_order, id');
        $stmt->execute([(int)$product['id']]);
        $specifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT code, name, description, sort_order FROM product_accessories WHERE product_id = ? AND published = 1 ORDER BY sort_order, id');
        $stmt->execute([(int)$product['id']]);
        $accessories = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $grouped = self::normalizeDocumentGroups($grouped);

        self::render('technical_sheets/product', [
            'title' => $product['name'] . ' - Schede tecniche',
            'product' => $product,
            'secondaryCategories' => $secondaryCategories,
            'models' => $models,
            'features' => $features,
            'specifications' => $specifications,
            'accessories' => $accessories,
            'documentGroups' => $grouped,
        ]);
    }

    public static function hasDedicatedPage(array $product): bool
    {
        static $names=['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR'];
        return ($product['category_slug']??'')==='linea-residenziale-r32'
            && ($product['family_name']??'')==='Mono Split'
            && in_array((string)($product['name']??''),$names,true);
    }

    private static function productDetails(PDO $pdo,array $ids): array
    {
        if(!$ids)return [];
        $ids=array_values(array_unique(array_map('intval',$ids)));$in=implode(',',$ids);$details=[];
        foreach($ids as $id)$details[$id]=['models'=>[],'features'=>[],'specifications'=>[],'accessories'=>[],'documentGroups'=>[]];
        $queries=[
            'models'=>"SELECT product_id,code,name FROM product_models WHERE published=1 AND product_id IN ($in) ORDER BY sort_order,code",
            'features'=>"SELECT product_id,label FROM product_features WHERE product_id IN ($in) ORDER BY sort_order,id",
            'specifications'=>"SELECT product_id,specification_key,specification_value FROM product_specifications WHERE product_id IN ($in) ORDER BY sort_order,id",
            'accessories'=>"SELECT product_id,code,name,description FROM product_accessories WHERE published=1 AND product_id IN ($in) ORDER BY sort_order,id",
        ];
        foreach($queries as $key=>$sql)foreach($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row)$details[(int)$row['product_id']][$key][]=$row;
        $sql="SELECT dl.product_id,d.id,d.title,d.filename,dt.name type_name FROM document_links dl JOIN documents d ON d.id=dl.document_id AND d.published=1 JOIN document_types dt ON dt.id=d.document_type_id AND dt.active=1 WHERE dl.product_id IN ($in) ORDER BY dt.sort_order,dt.name,d.sort_order,d.title";
        foreach($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row)$details[(int)$row['product_id']]['documentGroups'][(string)$row['type_name']][]=$row;
        foreach($details as &$detail)$detail['documentGroups']=self::normalizeDocumentGroups($detail['documentGroups']);
        unset($detail);
        return $details;
    }

    private static function normalizeDocumentGroups(array $groups): array
    {
        $normalized=[];
        foreach($groups as $name=>$documents){
            // Imported document types use both singular and plural labels.
            $name=trim((string)$name);
            $key=match(true){
                stripos($name,'scheda')!==false=>'Schede tecniche',
                stripos($name,'tabella')!==false=>'Tabelle rese',
                stripos($name,'resa')!==false=>'Tabelle rese',
                stripos($name,'detraz')!==false=>'Detrazioni fiscali',
                stripos($name,'conto')!==false=>'Conto termico',
                stripos($name,'manual')!==false=>'Manuali',
                default=>$name,
            };
            $normalized[$key]=array_merge($normalized[$key]??[],$documents);
        }
        $ordered=[];foreach(['Schede tecniche','Tabelle rese','Detrazioni fiscali','Conto termico','Manuali'] as $key)if(isset($normalized[$key])){$ordered[$key]=$normalized[$key];unset($normalized[$key]);}
        return $ordered+$normalized;
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
