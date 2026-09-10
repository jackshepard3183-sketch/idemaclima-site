ALTER TABLE events
  ADD COLUMN category VARCHAR(120) NULL AFTER audience,
  ADD COLUMN speaker VARCHAR(190) NULL AFTER short_description,
  ADD COLUMN program TEXT NULL AFTER description,
  ADD COLUMN waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER max_seats,
  ADD KEY idx_events_category (category);
