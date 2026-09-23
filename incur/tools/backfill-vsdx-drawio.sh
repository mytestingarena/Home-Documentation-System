#!/usr/bin/env bash
# Backfill Visio → draw.io for existing Designs rows (run on Orin).
# Usage: backfill-vsdx-drawio.sh [house_id ...]
# Default: houses 1 and 2.
set -euo pipefail
export PATH="$HOME/.local/bin:/usr/bin:/bin:$PATH"
REPO="${HDS_REPO:-$HOME/Home-Documentation-System}"
TOOLS="$REPO/incur/tools"
SSH_KEY="${HDS_SSH_KEY:-$HOME/.ssh/id_ed25519_hds}"
HDS_HOST="${HDS_HOST:-192.168.1.110}"
HDS_WEB="${HDS_WEB_ROOT:-/var/www/html/incur}"
DRAWIO="${HDS_DRAWIO_URL_ABS:-http://192.168.1.110/incur/drawio}"

houses=("$@")
if [[ ${#houses[@]} -eq 0 ]]; then
  houses=(1 2)
fi

ssh_hds() {
  /usr/bin/ssh -i "$SSH_KEY" -o StrictHostKeyChecking=no -o IdentitiesOnly=yes "root@$HDS_HOST" "$@"
}

sibling_name() {
  python3 -c 'import re,sys; print(re.sub(r"\.(vsdx|vsd|vsdm)$", ".drawio", sys.argv[1], flags=re.I))' "$1"
}

echo "==> Ensuring convert service is up..."
bash "$TOOLS/start-vsdx-convert-server.sh"

for hid in "${houses[@]}"; do
  echo "==> House $hid — listing Visio designs"
  mapfile -t files < <(ssh_hds "php -r \"
    require '${HDS_WEB}/config.php';
    \\\$r=\\\$conn->query('SELECT filename FROM designs WHERE house_id=${hid} AND (LOWER(filename) LIKE \\\'%.vsdx\\\' OR LOWER(filename) LIKE \\\'%.vsd\\\' OR LOWER(filename) LIKE \\\'%.vsdm\\\') ORDER BY id');
    while(\\\$row=\\\$r->fetch_assoc()) echo \\\$row['filename'].PHP_EOL;
  \"")
  for f in "${files[@]}"; do
    [[ -z "$f" ]] && continue
    sibling="$(sibling_name "$f")"
    echo "---- $f → $sibling"
    if ssh_hds "test -f '${HDS_WEB}/uploads/designs/${sibling}'"; then
      echo "    already on disk"
    else
      tmp="$(mktemp /tmp/hds-backfill-XXXXXX.drawio)"
      python3 "$TOOLS/vsdx-to-drawio.py" --filename "$f" --out "$tmp" --drawio "$DRAWIO" --timeout 150
      # Quote remote path so spaces in legacy Visio basenames work with scp
      remote_path="${HDS_WEB}/uploads/designs/${sibling}"
      /usr/bin/scp -i "$SSH_KEY" -o StrictHostKeyChecking=no -o IdentitiesOnly=yes \
        "$tmp" "root@${HDS_HOST}:$(printf %q "$remote_path")"
      ssh_hds "chown www-data:www-data '${HDS_WEB}/uploads/designs/${sibling}' && chmod 664 '${HDS_WEB}/uploads/designs/${sibling}'"
      rm -f "$tmp"
      echo "    file uploaded"
    fi
    ssh_hds "php -r \"
      require '${HDS_WEB}/config.php';
      \\\$fn='${sibling}';
      \\\$hid=${hid};
      \\\$stmt=\\\$conn->prepare('SELECT id FROM designs WHERE house_id=? AND filename=? LIMIT 1');
      \\\$stmt->bind_param('is', \\\$hid, \\\$fn);
      \\\$stmt->execute();
      \\\$ex=\\\$stmt->get_result()->fetch_assoc();
      \\\$stmt->close();
      if (!\\\$ex) {
        \\\$ins=\\\$conn->prepare('INSERT INTO designs (house_id, filename, upload_date) VALUES (?, ?, NOW())');
        \\\$ins->bind_param('is', \\\$hid, \\\$fn);
        \\\$ins->execute();
        \\\$ins->close();
        echo 'inserted '.\\\$fn.PHP_EOL;
      } else {
        echo 'db ok id='.\\\$ex['id'].PHP_EOL;
      }
    \""
  done
done
echo "==> Backfill complete."
