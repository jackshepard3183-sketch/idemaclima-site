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
  python3 - "$package_root/$1" "$remote_root/$1" <<'FTPS'
import ftplib, hashlib, os, ssl, sys, uuid
from urllib.parse import urlparse
source, remote = sys.argv[1:]
endpoint = os.environ["FTP_SERVER"]
url = urlparse(endpoint if "://" in endpoint else "ftp://" + endpoint)
context = ssl.create_default_context()
# Match the existing Aruba configuration: validate the CA chain; its
# certificate names the provider rather than the configured customer host.
context.check_hostname = False
expected = open(source, "rb").read()
temporary = remote + ".deploying." + uuid.uuid4().hex
for attempt in range(1, 4):
    ftp = ftplib.FTP_TLS(context=context, timeout=30)
    try:
        ftp.connect(url.hostname, url.port or 21)
        ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        directory = ""
        for part in remote.rsplit("/", 1)[0].split("/"):
            if not part:
                continue
            directory += "/" + part
            try:
                ftp.mkd(directory)
            except ftplib.error_perm:
                ftp.cwd(directory)
        with open(source, "rb") as stream:
            ftp.storbinary("STOR " + temporary, stream, blocksize=8192)
        downloaded = bytearray()
        ftp.retrbinary("RETR " + temporary, downloaded.extend, blocksize=8192)
        if downloaded != expected:
            print("Integrity retry %s: %s / %s bytes" % (attempt, len(downloaded), len(expected)))
            continue
        ftp.rename(temporary, remote)
        print("Verified and published " + remote)
        break
    except Exception as error:
        print("FTPS retry %s: %s" % (attempt, type(error).__name__))
        if attempt == 3:
            sys.exit(1)
    finally:
        ftp.close()
else:
    sys.exit("FTPS integrity check failed")
FTPS
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
