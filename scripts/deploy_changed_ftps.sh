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
    local transfer_url="${FTP_SERVER%/}$remote_tmp"
    [[ "$transfer_url" == *://* ]] || transfer_url="ftp://$transfer_url"
    if ! curl --fail --silent --show-error --ssl-reqd --connect-timeout 20 --max-time 60 \
      --user "$FTP_USERNAME:$FTP_PASSWORD" --ftp-create-dirs --upload-file "$source" "$transfer_url"; then
      echo "Upload retry $attempt: $relative"
      continue
    fi
    if ! curl --fail --silent --show-error --ssl-reqd --connect-timeout 20 --max-time 60 \
      --user "$FTP_USERNAME:$FTP_PASSWORD" --output "$remote_copy" "$transfer_url"; then
      echo "Download verification retry $attempt: $relative"
      continue
    fi
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
    if [[ -f "$remote_copy" ]]; then
      echo "Transferred file differs: $(wc -c < "$source") source bytes, $(wc -c < "$remote_copy") remote bytes"
    else
      echo "Remote verification copy was not downloaded: $relative"
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

# Keep the frontend audit fix together across retried deployments.
for audit_path in \
  app/Views/admin/_layout_start.php \
  app/Controllers/Public/TechnicalSheetsController.php \
  app/Views/public/home.php \
  app/Views/public/content/catalogs.php \
  app/Views/public/technical_sheets/index.php \
  database/migrations/074_correct_idronica_igc_badges.sql \
  public/catalogs.js \
  public/assets/fonts/fonts.css \
  public/assets/fonts/*.woff
do
  printf 'M\t%s\n' "$audit_path" >> "$changed_list"
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
