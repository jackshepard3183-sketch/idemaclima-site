<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class GalleryContent
{
    public static function ensureSchema(): void
    {
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_sections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_key VARCHAR(80) NOT NULL UNIQUE,
            eyebrow VARCHAR(160) NULL,
            title VARCHAR(220) NOT NULL,
            description TEXT NULL,
            layout_type VARCHAR(30) NOT NULL DEFAULT 'cards',
            published TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_content_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            section_id INT UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL DEFAULT 'image',
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            media_url VARCHAR(1000) NOT NULL,
            link_url VARCHAR(1000) NULL,
            link_label VARCHAR(160) NULL,
            published TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gallery_items_section (section_id,published,sort_order),
            CONSTRAINT fk_gallery_items_section FOREIGN KEY (section_id) REFERENCES gallery_sections(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if ((int)$pdo->query('SELECT COUNT(*) FROM gallery_sections')->fetchColumn() === 0) {
            self::seed($pdo);
        }
    }

    public static function sections(bool $publishedOnly = false): array
    {
        self::ensureSchema();
        $where = $publishedOnly ? ' WHERE s.published=1' : '';
        $sql = 'SELECT s.*,COUNT(i.id) item_count FROM gallery_sections s LEFT JOIN gallery_content_items i ON i.section_id=s.id' . $where . ' GROUP BY s.id ORDER BY s.sort_order,s.id';
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function items(?int $sectionId = null, bool $publishedOnly = false): array
    {
        self::ensureSchema();
        $conditions = [];
        $params = [];
        if ($sectionId) { $conditions[] = 'i.section_id=?'; $params[] = $sectionId; }
        if ($publishedOnly) { $conditions[] = 'i.published=1'; $conditions[] = 's.published=1'; }
        $sql = 'SELECT i.*,s.section_key,s.title section_title,s.layout_type FROM gallery_content_items i JOIN gallery_sections s ON s.id=i.section_id';
        if ($conditions) $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY s.sort_order,i.sort_order,i.id';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function publicSections(): array
    {
        $sections = self::sections(true);
        $items = self::items(null, true);
        $grouped = [];
        foreach ($items as $item) $grouped[(int)$item['section_id']][] = $item;
        foreach ($sections as &$section) $section['items'] = $grouped[(int)$section['id']] ?? [];
        unset($section);
        return $sections;
    }

    private static function seed(PDO $pdo): void
    {
        $sections = [
            ['air','— Novità',"Migliorare la qualità dell'aria.",'','cards',10],
            ['design','— Design','Nuove colorazioni ISAX-R32.','','cards',20],
            ['smart-home','— Smart Home','Controllo vocale.','','split',30],
            ['purifier','— Aria pulita',"Purificatore d'aria FTXM-740XIT.",'','split-reverse',40],
            ['headquarters','— Foto & video','La nostra sede.','IDEMA CLIMA® ha la sua sede legale a Milano, ma il cuore pulsante della società è la sede operativa di Vertemate con Minoprio (CO): un moderno stabilimento che ospita Direzione, uffici, formazione, show-room e una struttura tecnica interna per test e verifica qualità. A pochi chilometri, una logistica di oltre 4.000 m² gestisce lo stoccaggio del materiale.','photos',50],
            ['tv-spots','— Spot televisivi','IDEMA® in TV.','','videos',60],
            ['video-pills','— Videopillole','IDEMA®: tutorial e approfondimenti.','','videos',70],
        ];
        $insertSection = $pdo->prepare('INSERT INTO gallery_sections(section_key,eyebrow,title,description,layout_type,published,sort_order) VALUES(?,?,?,?,?,1,?)');
        $ids = [];
        foreach ($sections as $section) { $insertSection->execute($section); $ids[$section[0]] = (int)$pdo->lastInsertId(); }

        $wp = 'https://www.idemaclima.it/wp-content/uploads/';
        $items = [
            ['air','image','Doppia Filtrazione',"Oltre al filtro purificatore standard, i modelli ISAX-R32 COLOR offrono di serie una doppia filtrazione che migliora la qualità dell'aria rendendola fresca e salubre.",$wp.'doppia-filtrazione.jpg',null,null,10],
            ['air','image','Super Ionizzatore',"La tecnologia avanzata genera milioni di ioni positivi e negativi per ogni m³, contribuendo a eliminare i batteri presenti nell'aria.",$wp.'super-ionizzatore.jpg',null,null,20],
            ['air','image','Lampada Germicida','La lampada SMUV-101 con LED UVC e UVA aiuta a eliminare virus e batteri e a migliorare la qualità dell’aria.',$wp.'lampada-germicida.jpg',null,null,30],
            ['design','image','ISAX-R32 Silver','Una livrea versatile e sempre elegante, adatta a soggiorni classici e moderni.',$wp.'post_isasilver.png',null,null,10],
            ['design','image','ISAX-R32 Titanium','Una finitura essenziale e raffinata progettata per gli ambienti hi-tech.',$wp.'post_isatitanium.png',null,null,20],
            ['design','image','ISAX-R32 Black','Una livrea elegante pensata per ambienti moderni e minimalisti.',$wp.'post_isablack.png',null,null,30],
            ['smart-home','image','Controllo vocale','Con Amazon Alexa e Google Home Assistant puoi controllare accensione, modalità, temperatura e le principali funzioni degli split IDEMA®.',$wp.'controllo-vocale.png','/idemaclima/configurazione-wi-fi','Configurazione Wi-Fi',10],
            ['purifier','image',"Purificatore d'aria FTXM-740XIT",'Per ambienti fino a 85 m², con filtrazione a 5 livelli, ionizzazione e monitoraggio della qualità dell’aria.',$wp.'purificatore-aria.png','/idemaclima/uploads/catalogs/2026/09/ftxm-740xit-2023-c353fde5921e.pdf','Scarica la presentazione',10],
            ['headquarters','image','Esterno della sede IDEMA®','',$wp.'foto1.png',null,null,10],
            ['headquarters','image','Sala meeting e corsi IDEMA®','',$wp.'foto2.png',null,null,20],
            ['headquarters','image','Reception IDEMA®','',$wp.'foto3.png',null,null,30],
            ['headquarters','image','Parete espositiva IDEMA®','',$wp.'foto4.jpg',null,null,40],
            ['tv-spots','youtube','Spot TV 2025 — Una garanzia che dura più della tua ultima relazione?','Alcuni prodotti IDEMA® offrono fino a 10 anni di garanzia.','qpNDhXeYL4g',null,null,10],
            ['tv-spots','youtube','Spot TV 2024 — Gli Esperti del Freddo','Un gruppo di eschimesi scopre l’efficacia di un climatizzatore IDEMA®.','dp8VelGmsAM',null,null,20],
            ['tv-spots','youtube','Spot TV 2022 — Non lasciare che il caldo ti dia alla testa','Rinfrescati le idee con IDEMA®.','lCg-R0Sa7pY',null,null,30],
            ['tv-spots','youtube',"Spot TV 2022 — Quest'estate non darti delle arie!",'Goditi il fresco silenzioso di un climatizzatore IDEMA®.','q9xkh9yXv0Q',null,null,40],
            ['tv-spots','youtube','Secondo spot televisivo IDEMA®','Efficienza, silenziosità, controllo vocale e purificazione.','dQ7KXza0cZ8',null,null,50],
            ['tv-spots','youtube','Primo spot televisivo IDEMA®','Design e tecnologia con 5 anni di garanzia.','hy8vfq67wFY',null,null,60],
            ['video-pills','youtube',"Purificatore d'Aria FTXM-740XIT",'Purificazione XL, filtrazione a 5 stadi e monitoraggio della qualità dell’aria.','0bnv0YPV1Ng',null,null,10],
            ['video-pills','youtube','Lampada UVC/UVA SMUV-101 — Montaggio','Istruzioni di montaggio della lampada germicida IDEMA®.','dv9KUfQNpb8',null,null,20],
            ['video-pills','youtube',"Estrazione dei filtri dell'aria",'Come estrarre, detergere e riposizionare correttamente i filtri.','oeQedmKbtmM',null,null,30],
            ['video-pills','youtube','Modelli ISA-COLOR','Titanium, Silver e Black: tre livree di design.','RfwOHcIFb2o',null,null,40],
            ['video-pills','youtube','Modello ISA-R32','Unità interna DC Inverter R32 con predisposizione Wi-Fi.','j735ZoblRpA',null,null,50],
            ['video-pills','youtube','Modello ISZ-R32','Unità interna compatta DC Inverter R32.','eqbv0h721RM',null,null,60],
            ['video-pills','youtube','Manutenzione e Sanificazione','Manutenzione di climatizzazione, VMC e purificatori.','xtzLPoU2kdo',null,null,70],
            ['video-pills','youtube','Kit Wi-Fi — Controllo remoto','Configurazione dei kit Wi-Fi IDEMA®.','RgIdsZOC_Mw',null,null,80],
            ['video-pills','youtube','Ecoincentivi','Conto Termico e agevolazioni per la climatizzazione.','GuoSK-u_vA8',null,null,90],
        ];
        $insertItem = $pdo->prepare('INSERT INTO gallery_content_items(section_id,media_type,title,description,media_url,link_url,link_label,published,sort_order) VALUES(?,?,?,?,?,?,?,1,?)');
        foreach ($items as $item) { $key=array_shift($item); array_unshift($item,$ids[$key]); $insertItem->execute($item); }
    }
}