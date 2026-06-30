#!/usr/bin/env bash
# One-shot bootstrap for Home Documentation System (HDS).
#
# Run on a fresh Debian/Ubuntu LXC or VM as root:
#   bash -c "$(curl -fsSL https://raw.githubusercontent.com/mytestingarena/Home-Documentation-System/main/bootstrap-install.sh)"
#
# Downloads the repo, then runs ./install.sh (interactive).

set -euo pipefail

REPO_URL="${HDS_REPO_URL:-https://github.com/mytestingarena/Home-Documentation-System.git}"
INSTALL_DIR="${HDS_INSTALL_DIR:-${HOME}/Home-Documentation-System}"
BRANCH="${HDS_BRANCH:-main}"

die() { echo "Error: $*" >&2; exit 1; }

usage() {
    cat <<'EOF'
Home Documentation System — bootstrap installer

One-liner (run as root on Debian/Ubuntu):
  bash -c "$(curl -fsSL https://raw.githubusercontent.com/mytestingarena/Home-Documentation-System/main/bootstrap-install.sh)"

Options:
  --keep-repo    Update with git pull instead of deleting and re-cloning
  -h, --help     Show this help

Environment:
  HDS_INSTALL_DIR   Clone destination (default: ~/Home-Documentation-System)
  HDS_REPO_URL      Git remote URL
  HDS_BRANCH        Branch to clone (default: main)
EOF
}

ensure_root() {
    if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
        echo "This bootstrap must run as root (typical inside an LXC)."
        if command -v sudo >/dev/null 2>&1; then
            echo "Re-running with sudo..."
            exec sudo -E bash "$0" "$@"
        fi
        die "Log in as root and run the one-liner again."
    fi
}

ensure_apt() {
    command -v apt-get >/dev/null 2>&1 || die "This bootstrap requires apt-get (Debian/Ubuntu)."
}

ensure_bootstrap_tools() {
    local missing=()
    command -v curl >/dev/null 2>&1 || missing+=(curl)
    command -v git >/dev/null 2>&1 || missing+=(git)
    command -v ca-certificates >/dev/null 2>&1 || true
    dpkg-query -W -f='${Status}' ca-certificates 2>/dev/null | grep -q "install ok installed" \
        || missing+=(ca-certificates)

    [[ "${#missing[@]}" -eq 0 ]] && return 0

    echo "Installing bootstrap tools: ${missing[*]}"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y "${missing[@]}"
}

prepare_repo() {
    local fresh="${1:-1}"

    if [[ "$fresh" -eq 1 ]]; then
        echo "==> Fresh clone to ${INSTALL_DIR}"
        rm -rf "$INSTALL_DIR"
        git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$INSTALL_DIR"
    elif [[ -d "${INSTALL_DIR}/.git" ]]; then
        echo "==> Updating existing clone at ${INSTALL_DIR}"
        git -C "$INSTALL_DIR" pull --ff-only origin "$BRANCH"
    else
        echo "==> Cloning to ${INSTALL_DIR}"
        git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$INSTALL_DIR"
    fi

    [[ -x "${INSTALL_DIR}/install.sh" ]] || die "install.sh not found after clone."
    [[ -f "${INSTALL_DIR}/db/schema.sql" ]] || die "db/schema.sql not found after clone."
}

main() {
    local keep_repo=0

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --keep-repo)
                keep_repo=1
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                die "Unknown option: $1 (try --help)"
                ;;
        esac
    done

    echo ""
    echo "=== Home Documentation System — Bootstrap ==="
    echo ""

    ensure_root "$@"
    ensure_apt
    ensure_bootstrap_tools
    prepare_repo "$((1 - keep_repo))"

    echo ""
    echo "==> Starting interactive installer..."
    cd "$INSTALL_DIR"
    exec ./install.sh
}

main "$@"