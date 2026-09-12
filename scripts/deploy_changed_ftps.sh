#!/usr/bin/env bash
set -euo pipefail

: "${FTP_SERVER:?Missing FTP_SERVER}"
: "${FTP_USERNAME:?Missing FTP_USERNAME}"
: "${FTP_PASSWORD:?Missing FTP_PASSWORD}"

package_root="build/idemaclima-staging"
remote_root="/www.rappresentanzeguanzirolisas.it/idemaclima"
changed_list="$(mktemp)"
trap 'rm -f "$changed_list"' EXIT

git diff --name-status --no-renames HEAD^ HEAD > "$changed_list"

is_managed_path() {
  local path="$1"
  [[ -f "$package_root/$path" ]] || return 1
  [[ "$path" != .env && "$path" != DEPLOY_SHA256SUMS.txt ]] || return 1
  [[ "$path" != storage/private/* && "$path" != public/uploads/* ]] || return 1
}

transfer_and_verify() {
  local relative="$1" source="$package_root/$1" remote="$remote_root/$1"
  local remote_copy remote_tmp attempt
  remote_copy="$(mktemp)"
  remote_tmp="${remote}.deploying.${GITHUB_RUN_ID:-$}.${RANDOM}"
  for attempt in 1 2 3; do
    rm -f "$remote_copy"
    lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "$FTP_SERVER" <<EOF
set ftp:ssl-allow yes
set ssl:verify-certificate yes
set ssl:check-hostname no
set net:max-retries 2
set net:timeout 20
mkdir -p "$(dirname "$remote")"
put "$source" -o "$remote_tmp"
get "$remote_tmp" -o "$remote_copy"
bye
EOF
    if cmp -s "$source" "$remote_copy"; then
      lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "$FTP_SERVER" <<EOF
set ftp:ssl-allow yes
set ssl:verify-certificate yes
set ssl:check-hostname no
mv "$remote_tmp" "$remote"
bye
EOF
      rm -f "$remote_copy"
      echo "Verified and published $relative"
      return 0
    fi
    echo "Integrity retry $attempt: $relative"
  done
  lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "$FTP_SERVER" <<EOF || true
set ftp:ssl-allow yes
set ssl:verify-certificate yes
set ssl:check-hostname no
rm -f "$remote_tmp"
bye
EOF
  rm -f "$remote_copy"
  echo "Integrity mismatch: $relative" >&2
  return 1
}

# Brand assets and their consuming views are intentionally deployed together.
# This prevents partial staging updates when rapid consecutive commits cancel older runs.
for required_path in \
  app/Views/public/_layout_start.php \
  app/Views/public/_layout_end.php \
  app/Views/public/warranty/form.php \
  public/brand-assets/idema-logo-96.png.php \
  public/brand-assets/idema-logo-180.png.php \
  public/brand-assets/idema-logo-512.png.php \
  public/brand-assets/idema-logo-nero.png.php \
  public/brand-assets/idema-clima.png.php \
  public/brand-assets/garanzia-10anni.png.php \
  public/brand-assets/garanzia-5anni.png.php \
  app/Controllers/Admin/WarrantyCertificateActionsTrait.php \
  app/Controllers/Admin/WarrantyCertificateLayoutTrait.php \
  app/Views/admin/warranty_certificate_layout.php \
  app/Views/public/contact/form.php \
  app/Views/public/editorial/incentives_easytool_form.php \
  app/Views/public/campus/cat_register.php \
  app/Views/public/campus/event.php \
  app/Views/public/warranty/form_fields.php \
  scripts/mirror_remote_assets.php \
  database/import/rendered_asset_sources.txt \
  app/Core/Url.php \
  app/Core/Seo.php \
  app/Services/CampusMailService.php \
  app/Services/ContactMailService.php \
  app/Services/IncentivesMailService.php \
  app/Services/WarrantyService.php
do
  printf 'M\t%s\n' "$required_path" >> "$changed_list"
done

while IFS=$'\t' read -r status first second; do
  [[ -n "$status" ]] || continue
  path="${second:-$first}"
  if [[ "$status" == D* ]]; then
    lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "$FTP_SERVER" <<EOF
set ftp:ssl-allow yes
set ssl:verify-certificate yes
set ssl:check-hostname no
rm -f "$remote_root/$first"
bye
EOF
  elif is_managed_path "$path"; then
    transfer_and_verify "$path"
  fi
done < "$changed_list"
