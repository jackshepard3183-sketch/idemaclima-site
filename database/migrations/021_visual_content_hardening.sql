ALTER TABLE gallery_albums
  ADD COLUMN archived_at DATETIME NULL AFTER published,
  ADD KEY idx_gallery_albums_public (published, archived_at, sort_order);

ALTER TABLE gallery_images
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE references_projects
  ADD COLUMN archived_at DATETIME NULL AFTER published,
  ADD KEY idx_references_public (published, archived_at, sort_order);

ALTER TABLE reference_images
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
