#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/idemaclima-staging"
ZIP="$ROOT/build/idemaclima-staging.zip"

rm -rf "$OUT" "$ZIP"
mkdir -p "$OUT"

# Copy application files while excluding development/local state.
rsync -a \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.env' \
  --exclude='build/' \
  --exclude='database/import/reports/*.json' \
  --exclude='storage/private/*' \
  --exclude='public/uploads/documents/migrated/*' \
  "$ROOT/" "$OUT/"

mkdir -p "$OUT/storage/private" "$OUT/public/uploads/documents/migrated" "$OUT/database/import/reports"
cp "$ROOT/deploy/.env.staging.example" "$OUT/.env.example-staging"

# Safety marker: deployment package must never contain a real .env.
if [ -f "$OUT/.env" ]; then
  echo "ERRORE: il pacchetto contiene .env" >&2
  exit 2
fi

# Generate manifest for post-upload verification.
(
  cd "$OUT"
  find . -type f -print0 | sort -z | xargs -0 sha256sum > DEPLOY_SHA256SUMS.txt
)

if command -v zip >/dev/null 2>&1; then
  (cd "$ROOT/build" && zip -qr "$(basename "$ZIP")" "$(basename "$OUT")")
  echo "Pacchetto ZIP creato: $ZIP"
else
  echo "Cartella pacchetto creata: $OUT"
  echo "Comando zip non disponibile: ZIP non generato."
fi
