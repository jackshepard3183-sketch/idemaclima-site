-- Rimuove esclusivamente l'ultima traccia di eliminazione relativa
-- all'evento aperto creato ed eliminato per errore.
DELETE FROM audit_log
WHERE id = (
  SELECT id
  FROM (
    SELECT id
    FROM audit_log
    WHERE action = 'campus.event.delete'
      AND entity_type = 'event'
      AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.audience')) = 'public'
    ORDER BY created_at DESC, id DESC
    LIMIT 1
  ) AS latest_deleted_public_event
);
