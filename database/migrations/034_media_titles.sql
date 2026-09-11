-- Normalize technical mirror prefixes for labels shown in the Media Library.
UPDATE media_assets
SET title = TRIM(SUBSTRING(title, 18))
WHERE title REGEXP '^[A-Fa-f0-9]{16}[[:space:]]';
