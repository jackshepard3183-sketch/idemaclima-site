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
  local remote_copy attempt
  remote_copy="$(mktemp)"
  for attempt in 1 2 3; do
    rm -f "$remote_copy"
    lftp -u "$FTP_USERNAME","$FTP_PASSWORD" "$FTP_SERVER" <<EOF
set ftp:ssl-allow yes
set ssl:verify-certificate yes
set ssl:check-hostname no
set net:max-retries 2
set net:timeout 20
mkdir -p "$(dirname "$remote")"
put "$source" -o "$remote"
get "$remote" -o "$remote_copy"
bye
EOF
    if cmp -s "$source" "$remote_copy"; then
      rm -f "$remote_copy"
      echo "Verified $relative"
      return 0
    fi
    echo "Integrity retry $attempt: $relative"
  done
  rm -f "$remote_copy"
  echo "Integrity mismatch: $relative" >&2
  return 1
}

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

