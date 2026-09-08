<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Services\GalleryContent;
use PDO;

final class ContentController
{
    public static function catalogs(): void
    {
        $rows=Database::connection()->query('SELECT c.*,d.published document_published FROM catalogs c LEFT JOIN documents d ON d.id=c.document_id WHERE c.published=1 AND (c.document_id IS NULL OR d.published=1) ORDER BY c.sort_order,c.title')->fetchAll(PDO::FETCH_ASSOC);
        self::render('content/catalogs',['title'=>'Cataloghi','catalogs'=>$rows]);
    }

    public static function gallery(): void
    {
        $rows=Database::connection()->query('SELECT a.*,COUNT(i.id) image_count FROM gallery_albums a LEFT JOIN gallery_images i ON i.album_id=a.id AND i.published=1 WHERE a.published=1 AND a.archived_at IS NULL GROUP BY a.id ORDER BY a.sort_order,a.title')->fetchAll(PDO::FETCH_ASSOC);
        $sections=GalleryContent::publicSections();
        self::render('content/gallery',['title'=>'Galleria','albums'=>$rows,'gallerySections'=>$sections]);
    }

    public static function galleryAlbum(string $slug): void
    {
        $pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM gallery_albums WHERE slug=? AND published=1 AND archived_at IS NULL LIMIT 1');$s->execute([$slug]);$album=$s->fetch(PDO::FETCH_ASSOC);
        if(!$album){self::notFound();return;}
        $s=$pdo->prepare('SELECT * FROM gallery_images WHERE album_id=? AND published=1 ORDER BY sort_order,id');$s->execute([(int)$album['id']]);$images=$s->fetchAll(PDO::FETCH_ASSOC);
        self::render('content/gallery_album',['title'=>$album['title'].' - Galleria','album'=>$album,'images'=>$images]);
    }

    public static function references(): void
    {
        $rows=Database::connection()->query('SELECT * FROM references_projects WHERE published=1 AND archived_at IS NULL ORDER BY sort_order,title')->fetchAll(PDO::FETCH_ASSOC);
        self::render('content/references',['title'=>'Referenze','references'=>$rows]);
    }

    public static function reference(string $slug): void
    {
        $pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM references_projects WHERE slug=? AND published=1 AND archived_at IS NULL LIMIT 1');$s->execute([$slug]);$reference=$s->fetch(PDO::FETCH_ASSOC);
        if(!$reference){self::notFound();return;}
        $s=$pdo->prepare('SELECT * FROM reference_images WHERE reference_id=? AND published=1 ORDER BY sort_order,id');$s->execute([(int)$reference['id']]);$images=$s->fetchAll(PDO::FETCH_ASSOC);
        self::render('content/reference',['title'=>$reference['title'].' - Referenze','reference'=>$reference,'images'=>$images]);
    }

    private static function render(string $view,array $data):void
    {
        extract($data,EXTR_SKIP);header('Content-Type:text/html; charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';
    }

    private static function notFound():void{http_response_code(404);self::render('404',['title'=>'Pagina non trovata']);}
}