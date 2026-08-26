#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

printf '%s\n' '== IDEMA staging preflight =='
php scripts/staging_readiness.php
php scripts/migrate.php --status
bash tests/run_all.sh
printf '%s\n' 'Preflight staging completato senza applicare migration.'
