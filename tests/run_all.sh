#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

"$ROOT/tests/php_syntax.sh"

if command -v php >/dev/null 2>&1; then
  php "$ROOT/tests/router_regression.php"
  php "$ROOT/tests/historical_import_smoke.php"
  php "$ROOT/tests/supplemental_import_smoke.php"
  php "$ROOT/tests/migration_pipeline_smoke.php"
  php "$ROOT/tests/assistance_hardening_smoke.php"
  php "$ROOT/tests/warranty_hardening_smoke.php"
  php "$ROOT/tests/campus_hardening_smoke.php"
fi

if [ -x "$ROOT/tests/import_datasheets_smoke.sh" ]; then
  "$ROOT/tests/import_datasheets_smoke.sh"
fi

echo "Tutti i test disponibili sono completati."
