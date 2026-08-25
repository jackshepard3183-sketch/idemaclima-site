#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPORT="$ROOT/storage/logs/test-import-report.json"
php "$ROOT/database/import/import_datasheets.php" \
  --source="$ROOT/database/import/sample_datasheets.ts" \
  --report="$REPORT"
php -r '
$r=json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
assert($r["mode"] === "dry-run");
assert($r["stats"]["root_categories"] === 1);
assert($r["stats"]["subcategories"] === 1);
assert($r["stats"]["products"] === 1);
assert($r["stats"]["models"] === 2);
assert($r["stats"]["documents"] === 4);
assert($r["stats"]["combinations_detected"] === 0);
echo "OK import smoke test\n";
' "$REPORT"
