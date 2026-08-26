CREATE TABLE editorial_pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(220) NOT NULL,
  eyebrow VARCHAR(160) NULL,
  intro TEXT NULL,
  body MEDIUMTEXT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(320) NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_editorial_pages_published (published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE editorial_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  anchor_slug VARCHAR(190) NULL,
  body MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_editorial_sections_page FOREIGN KEY (page_id) REFERENCES editorial_pages(id) ON DELETE CASCADE,
  KEY idx_editorial_sections_page (page_id, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faq_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED NOT NULL,
  question VARCHAR(500) NOT NULL,
  answer MEDIUMTEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_faq_items_page FOREIGN KEY (page_id) REFERENCES editorial_pages(id) ON DELETE CASCADE,
  KEY idx_faq_items_page (page_id, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE editorial_page_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  group_label VARCHAR(190) NULL,
  label VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_editorial_page_documents_page FOREIGN KEY (page_id) REFERENCES editorial_pages(id) ON DELETE CASCADE,
  CONSTRAINT fk_editorial_page_documents_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  UNIQUE KEY uq_editorial_page_document (page_id, document_id, group_label),
  KEY idx_editorial_page_documents_page (page_id, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO editorial_pages (slug,title,eyebrow,intro,published,sort_order) VALUES
('faq','FAQ','Domande e risposte','Risposte alle domande più frequenti sui prodotti e sugli impianti IDEMA.',1,10),
('configurazione-wi-fi','Configurazione Wi-Fi','Per smartphone e tablet','Guide e risorse per configurare i dispositivi Wi-Fi compatibili con i sistemi IDEMA.',1,20),
('detrazioni-e-incentivi','Detrazioni e Incentivi','Agevolazioni e risparmio energetico','Informazioni utili su detrazioni fiscali, incentivi e procedure collegate agli interventi di climatizzazione ed efficientamento energetico.',1,30),
('detrazioni-e-incentivi/conto-termico','Conto Termico','Detrazioni e Incentivi','Informazioni sul meccanismo di incentivazione del Conto Termico e sui principali requisiti di accesso.',1,40),
('schede-tecniche/dichiarazioni-conformita-ce','Dichiarazioni Conformità CE','Schede Tecniche','Raccolta delle dichiarazioni di conformità CE dei prodotti IDEMA, organizzate per linea e famiglia.',1,50);
