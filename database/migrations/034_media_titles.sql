UPDATE media_assets
SET title = TRIM(SUBSTRING(title, 18))
WHERE title REGEXP '^[A-Fa-f0-9]{16}[[:space:]]';
