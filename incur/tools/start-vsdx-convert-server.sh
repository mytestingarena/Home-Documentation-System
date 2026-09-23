#!/usr/bin/env bash
# Start (or restart) the HDS Visio→draw.io convert service on Orin.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
# Prefer repo tools path when started from ~/.grok/tools symlink/copy
REPO="${HDS_REPO:-$HOME/Home-Documentation-System}"
TOOLS="$REPO/incur/tools"
PIDFILE="${XDG_RUNTIME_DIR:-/tmp}/hds-vsdx-convert.pid"
LOG="${HOME}/.grok/logs/vsdx-to-drawio-server.log"
mkdir -p "$(dirname "$LOG")"

if [[ -f "$PIDFILE" ]] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
  echo "Already running pid=$(cat "$PIDFILE")"
  exit 0
fi

export PATH="$HOME/.local/bin:/usr/bin:/bin:$PATH"
export HDS_VSDX_CONVERT_HOST="${HDS_VSDX_CONVERT_HOST:-0.0.0.0}"
export HDS_VSDX_CONVERT_PORT="${HDS_VSDX_CONVERT_PORT:-8765}"
nohup python3 "$TOOLS/vsdx-to-drawio-server.py" >>"$LOG" 2>&1 &
echo $! >"$PIDFILE"
sleep 1
curl -fsS "http://127.0.0.1:${HDS_VSDX_CONVERT_PORT}/health" || {
  echo "Service failed to start; see $LOG" >&2
  exit 1
}
echo "Started pid=$(cat "$PIDFILE") on :${HDS_VSDX_CONVERT_PORT}"
