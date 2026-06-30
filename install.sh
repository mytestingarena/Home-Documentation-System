#!/usr/bin/env bash
# Interactive installer for Home Documentation System (HDS).
#
# Usage (from repository root):
#   ./install.sh
#
# On a normal host you may be prompted to re-run via sudo.
# Inside an LXC you are usually already root — answer "no" to sudo.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")" && pwd)"
APP_SRC="${REPO_ROOT}/incur"
DB_DIR="${REPO_ROOT}/db"

WEB_PATH="/var/www/html/incur"
WEB_USER="www-data"
DB_NAME="house_info"
DB_USER="house"
MYSQL_ADMIN_HOST="localhost"
MYSQL_ADMIN_USER="root"
MYSQL_USE_SOCKET=0
IMPORT_WATER_SCHEMA=0

UPLOAD_SUBDIRS=(photos receipts manuals designs outdoor-work house-work maintenance-log)

die() { echo "Error: $*" >&2; exit 1; }
warn() { echo "Warning: $*" >&2; }

PREREQ_MISSING=()
PREREQ_INSTALL_APT=()
WEB_SERVER=""
DB_SERVICE=""

ensure_root() {
    if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
        return 0
    fi

    echo ""
    echo "This installer needs root to create the database and deploy under the web root."
    echo "On a normal Linux host, choose sudo. Inside an LXC you are often already root"
    echo "and sudo may not exist — log in as root and run ./install.sh directly."
    echo ""

    local answer=""
    read -rp "Re-run this script with sudo? [Y/n]: " answer
    answer="${answer:-Y}"

    if [[ "$answer" =~ ^[Yy] ]]; then
        command -v sudo >/dev/null 2>&1 \
            || die "sudo not found. Log in as root and run: ./install.sh"
        exec sudo -E "$0" "$@"
    fi

    die "Run as root without sudo, e.g.: su -   then   ./install.sh"
}

prompt_default() {
    local label="$1" default="$2" value=""
    read -rp "$label [$default]: " value
    [[ -z "$value" ]] && echo "$default" || echo "$value"
}

prompt_yes_no() {
    local label="$1" default="${2:-n}" hint="y/N"
    [[ "$default" == "y" ]] && hint="Y/n"
    local value=""
    read -rp "$label [$hint]: " value
    value="${value:-$default}"
    [[ "$value" =~ ^[Yy] ]]
}

prompt_secret() {
    local label="$1" value=""
    read -rsp "$label: " value
    echo ""
    echo "$value"
}

sql_escape() { printf "%s" "$1" | sed "s/'/''/g"; }
php_single_quoted() { printf "%s" "$1" | sed "s/'/\\\\'/g"; }

generate_password() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -base64 24 | tr -d '/+=' | head -c 24
    else
        tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24
    fi
}

mysql_admin() {
    if [[ "$MYSQL_USE_SOCKET" -eq 1 ]]; then
        if [[ -n "${MYSQL_ADMIN_PASS:-}" ]]; then
            MYSQL_PWD="$MYSQL_ADMIN_PASS" mysql -u "$MYSQL_ADMIN_USER" "$@"
        else
            mysql -u "$MYSQL_ADMIN_USER" "$@"
        fi
    else
        if [[ -n "${MYSQL_ADMIN_PASS:-}" ]]; then
            MYSQL_PWD="$MYSQL_ADMIN_PASS" mysql -h "$MYSQL_ADMIN_HOST" -u "$MYSQL_ADMIN_USER" "$@"
        else
            mysql -h "$MYSQL_ADMIN_HOST" -u "$MYSQL_ADMIN_USER" "$@"
        fi
    fi
}

mysql_app() {
    MYSQL_PWD="$DB_PASS" mysql -h "$MYSQL_APP_HOST" -u "$DB_USER" "$@"
}

pkg_installed() {
    local pkg="$1"
    if command -v dpkg-query >/dev/null 2>&1; then
        dpkg-query -W -f='${Status}' "$pkg" 2>/dev/null | grep -q "install ok installed"
        return
    fi
    if command -v rpm >/dev/null 2>&1; then
        rpm -q "$pkg" >/dev/null 2>&1
        return
    fi
    return 1
}

service_running() {
    local svc="$1"
    if command -v systemctl >/dev/null 2>&1; then
        systemctl is-active --quiet "$svc" 2>/dev/null
        return
    fi
    return 0
}

php_mysqli_ok() {
    command -v php >/dev/null 2>&1 || return 1
    php -r 'exit(extension_loaded("mysqli") ? 0 : 1);' 2>/dev/null
}

detect_web_server() {
    WEB_SERVER=""
    if command -v apache2 >/dev/null 2>&1 || pkg_installed apache2 || pkg_installed httpd; then
        WEB_SERVER="apache2"
        if pkg_installed httpd && ! pkg_installed apache2; then
            WEB_SERVER="httpd"
        fi
        return 0
    fi
    if command -v nginx >/dev/null 2>&1 || pkg_installed nginx; then
        WEB_SERVER="nginx"
        return 0
    fi
    return 1
}

detect_db_service() {
    DB_SERVICE=""
    for svc in mariadb mysql mysqld; do
        if service_running "$svc"; then
            DB_SERVICE="$svc"
            return 0
        fi
    done
    if pkg_installed mariadb-server || pkg_installed mysql-server || pkg_installed mariadb; then
        DB_SERVICE="mariadb"
        return 0
    fi
    return 1
}

add_prereq_missing() {
    PREREQ_MISSING+=("$1")
}

queue_apt_package() {
    local pkg="$1"
    local existing
    for existing in "${PREREQ_INSTALL_APT[@]}"; do
        [[ "$existing" == "$pkg" ]] && return 0
    done
    PREREQ_INSTALL_APT+=("$pkg")
}

detect_php_version() {
    if command -v php >/dev/null 2>&1; then
        php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true
        return 0
    fi
    if command -v apt-cache >/dev/null 2>&1; then
        apt-cache show php 2>/dev/null | awk -F': ' '/^Version:/{print $2; exit}' \
            | grep -oE '[0-9]+\.[0-9]+' | head -1
        return 0
    fi
    return 1
}

queue_php_mysql_package() {
    local php_version="${1:-}"
    if [[ -z "$php_version" ]]; then
        php_version="$(detect_php_version || true)"
    fi
    if [[ -n "$php_version" ]] && command -v apt-cache >/dev/null 2>&1 \
        && apt-cache show "php${php_version}-mysql" >/dev/null 2>&1; then
        queue_apt_package "php${php_version}-mysql"
    elif command -v apt-cache >/dev/null 2>&1 && apt-cache show php-mysql >/dev/null 2>&1; then
        queue_apt_package "php-mysql"
    else
        queue_apt_package "php-mysqli"
    fi
}

check_php_prereqs() {
    if ! command -v php >/dev/null 2>&1; then
        add_prereq_missing "PHP is not installed"
        queue_apt_package "php"
        queue_apt_package "libapache2-mod-php"
        queue_php_mysql_package
        return 1
    fi

    local php_version
    php_version="$(detect_php_version || true)"
    if [[ -n "$php_version" ]]; then
        local major="${php_version%%.*}"
        if [[ "$major" -lt 8 ]]; then
            warn "PHP ${php_version} found; PHP 8.x is recommended."
        fi
    fi

    if ! php_mysqli_ok; then
        add_prereq_missing "PHP mysqli extension is not enabled (install php-mysql)"
        queue_php_mysql_package "$php_version"
        if ! pkg_installed libapache2-mod-php && ! pkg_installed "php${php_version}-apache2"; then
            queue_apt_package "libapache2-mod-php"
        fi
        return 1
    fi

    echo "  PHP: OK ($(php -r 'echo PHP_VERSION;') with mysqli)"
    return 0
}

check_web_server_prereqs() {
    if detect_web_server; then
        if [[ "$WEB_SERVER" == "nginx" ]]; then
            warn "nginx detected. This installer targets Apache; ensure php-fpm is configured for PHP."
        fi
        if service_running "$WEB_SERVER" || service_running apache2 || service_running httpd; then
            echo "  Web server: OK (${WEB_SERVER})"
            return 0
        fi
        add_prereq_missing "Web server (${WEB_SERVER}) is installed but not running"
        return 1
    fi

    add_prereq_missing "Apache is not installed (apache2 package)"
    queue_apt_package "apache2"
    return 1
}

check_database_prereqs() {
    if ! command -v mysql >/dev/null 2>&1; then
        add_prereq_missing "MariaDB/MySQL client is not installed"
        queue_apt_package "mariadb-client"
    fi

    if pkg_installed mariadb-server || pkg_installed mysql-server || pkg_installed mariadb; then
        if detect_db_service && service_running "$DB_SERVICE"; then
            echo "  Database server: OK (${DB_SERVICE})"
            return 0
        fi
        add_prereq_missing "MariaDB/MySQL server is installed but not running (${DB_SERVICE:-unknown})"
        return 1
    fi

    add_prereq_missing "MariaDB/MySQL server is not installed (mariadb-server package)"
    queue_apt_package "mariadb-server"
    return 1
}

check_tool_prereqs() {
    if ! command -v rsync >/dev/null 2>&1; then
        add_prereq_missing "rsync is not installed"
        queue_apt_package "rsync"
    else
        echo "  rsync: OK"
    fi
}

check_repo_files() {
    [[ -d "$APP_SRC" ]] || die "App source not found: $APP_SRC"
    [[ -f "$DB_DIR/schema.sql" ]] || die "Missing: $DB_DIR/schema.sql"
    [[ -f "$DB_DIR/migrations.sql" ]] || die "Missing: $DB_DIR/migrations.sql"
}

install_prerequisites_apt() {
    [[ "${#PREREQ_INSTALL_APT[@]}" -eq 0 ]] && return 0

    if ! command -v apt-get >/dev/null 2>&1; then
        die "Automatic install needs apt-get (Debian/Ubuntu). Install packages manually and re-run."
    fi

    echo ""
    echo "Installing packages: ${PREREQ_INSTALL_APT[*]}"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y "${PREREQ_INSTALL_APT[@]}"

    for svc in apache2 mariadb mysql httpd; do
        if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${svc}.service" >/dev/null 2>&1; then
            systemctl enable --now "$svc" 2>/dev/null || true
        fi
    done

    if command -v systemctl >/dev/null 2>&1; then
        systemctl reload apache2 2>/dev/null || systemctl reload httpd 2>/dev/null || true
    fi
}

run_prereq_checks() {
    PREREQ_MISSING=()
    PREREQ_INSTALL_APT=()
    check_tool_prereqs
    check_php_prereqs || true
    check_web_server_prereqs || true
    check_database_prereqs || true
}

print_prereq_missing() {
    local item
    echo ""
    echo "Missing requirements:"
    for item in "${PREREQ_MISSING[@]}"; do
        echo "  - $item"
    done
}

check_prerequisites() {
    echo ""
    echo "Checking required software..."

    check_repo_files
    run_prereq_checks

    if [[ "${#PREREQ_MISSING[@]}" -eq 0 ]]; then
        echo "  All required software is present."
        return 0
    fi

    print_prereq_missing

    local install_round=0
    while [[ "${#PREREQ_MISSING[@]}" -gt 0 && "${#PREREQ_INSTALL_APT[@]}" -gt 0 ]] \
        && command -v apt-get >/dev/null 2>&1; do
        echo ""
        echo "Suggested apt packages: ${PREREQ_INSTALL_APT[*]}"
        if ! prompt_yes_no "Install missing packages now with apt-get?" "y"; then
            break
        fi
        install_prerequisites_apt
        install_round=$((install_round + 1))
        echo ""
        echo "Re-checking requirements..."
        run_prereq_checks
        if [[ "${#PREREQ_MISSING[@]}" -eq 0 ]]; then
            echo "  All required software is present."
            return 0
        fi
        print_prereq_missing
        [[ "$install_round" -ge 2 ]] && break
    done

    if [[ "${#PREREQ_MISSING[@]}" -gt 0 ]]; then
        echo ""
        die "Install the missing software above, then re-run ./install.sh"
    fi

    if ! command -v mysql >/dev/null 2>&1; then
        die "mysql client not available after prerequisite install."
    fi
    if ! command -v rsync >/dev/null 2>&1; then
        die "rsync not available after prerequisite install."
    fi
    if ! php_mysqli_ok; then
        die "PHP mysqli extension is still not available. Install php-mysql (or php8.4-mysql) and retry."
    fi
}

collect_prompts() {
    echo ""
    echo "=== Home Documentation System — Interactive Install ==="
    echo ""

    WEB_PATH="$(prompt_default "Web install path" "$WEB_PATH")"
    WEB_USER="$(prompt_default "Web server user" "$WEB_USER")"
    id "$WEB_USER" >/dev/null 2>&1 || die "Web server user does not exist: $WEB_USER"

    echo ""
    echo "MySQL admin connection"
    if prompt_yes_no "Use local socket authentication (typical for root inside LXC)?" "y"; then
        MYSQL_USE_SOCKET=1
        MYSQL_ADMIN_USER="$(prompt_default "MySQL admin user" "root")"
        if prompt_yes_no "MySQL admin password required?" "n"; then
            MYSQL_ADMIN_PASS="$(prompt_secret "MySQL admin password")"
        else
            MYSQL_ADMIN_PASS=""
        fi
    else
        MYSQL_USE_SOCKET=0
        MYSQL_ADMIN_HOST="$(prompt_default "MySQL admin host" "$MYSQL_ADMIN_HOST")"
        MYSQL_ADMIN_USER="$(prompt_default "MySQL admin user" "$MYSQL_ADMIN_USER")"
        MYSQL_ADMIN_PASS="$(prompt_secret "MySQL admin password")"
    fi

    echo ""
    DB_NAME="$(prompt_default "Database name" "$DB_NAME")"
    DB_USER="$(prompt_default "Database user" "$DB_USER")"
    if prompt_yes_no "Generate a random database password?" "y"; then
        DB_PASS="$(generate_password)"
        echo "  Generated database password: $DB_PASS"
    else
        DB_PASS="$(prompt_secret "Database password")"
        [[ -n "$DB_PASS" ]] || die "Database password cannot be empty."
    fi

    echo ""
    echo "Tab passwords (WiFi and Admin unlock screens)"
    WIFI_TAB_PASSWORD="$(prompt_secret "WiFi tab password")"
    [[ -n "$WIFI_TAB_PASSWORD" ]] || die "WiFi tab password cannot be empty."
    if prompt_yes_no "Use the same password for the Admin tab?" "y"; then
        ADMIN_TAB_PASSWORD="$WIFI_TAB_PASSWORD"
    else
        ADMIN_TAB_PASSWORD="$(prompt_secret "Admin tab password")"
        [[ -n "$ADMIN_TAB_PASSWORD" ]] || die "Admin tab password cannot be empty."
    fi

    echo ""
    SERVER_HOSTNAME="$(prompt_default "Server hostname for URL summary" "$(hostname -f 2>/dev/null || hostname)")"

    if [[ -f "$DB_DIR/water_schema.sql" ]]; then
        echo ""
        prompt_yes_no "Import optional db/water_schema.sql?" "n" && IMPORT_WATER_SCHEMA=1
    fi

    MYSQL_APP_HOST="localhost"
}

test_mysql_admin() {
    echo ""
    echo "Testing MySQL admin connection..."
    mysql_admin -e "SELECT 1;" >/dev/null 2>&1 || die "Could not connect to MySQL."
    echo "  MySQL admin connection OK."
}

setup_database() {
    echo ""
    echo "Creating database and MySQL user..."
    local pass_sql
    pass_sql="$(sql_escape "$DB_PASS")"
    mysql_admin -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql_admin -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${pass_sql}';" 2>/dev/null \
        || mysql_admin -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${pass_sql}';"
    mysql_admin -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
    echo "  Database '${DB_NAME}' and user '${DB_USER}' ready."
}

import_schema() {
    echo ""
    echo "Importing database schema..."
    if ! mysql_app "$DB_NAME" < "$DB_DIR/schema.sql"; then
        echo ""
        warn "Schema import failed. If a previous attempt left a partial database, reset it:"
        echo "  mysql -u ${MYSQL_ADMIN_USER} -e 'DROP DATABASE \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'"
        die "Schema import failed."
    fi
    if ! mysql_app "$DB_NAME" < "$DB_DIR/migrations.sql"; then
        die "migrations.sql import failed."
    fi
    echo "  Imported schema.sql and migrations.sql."
    if [[ "$IMPORT_WATER_SCHEMA" -eq 1 ]]; then
        mysql_app "$DB_NAME" < "$DB_DIR/water_schema.sql"
        echo "  Imported water_schema.sql."
    fi
}

deploy_application() {
    echo ""
    echo "Deploying application to ${WEB_PATH}..."
    mkdir -p "$WEB_PATH"
    rsync -a \
        --exclude 'uploads/**' \
        --exclude 'config.local.php' \
        --exclude 'backup.sql' \
        --exclude '*.sql' \
        "$APP_SRC/" "$WEB_PATH/"
    echo "  Application files synced (existing config.local.php preserved)."
}

write_config_local() {
    local config_path="${WEB_PATH}/config.local.php"
    if [[ -f "$config_path" ]]; then
        echo ""
        echo "Keeping existing ${config_path}."
        return 0
    fi

    echo ""
    echo "Writing ${config_path}..."
    cat > "$config_path" <<PHP
<?php
// Generated by install.sh on $(date -Iseconds)

\$password = '$(php_single_quoted "$DB_PASS")';

if (!defined('WIFI_TAB_PASSWORD')) {
    define('WIFI_TAB_PASSWORD', '$(php_single_quoted "$WIFI_TAB_PASSWORD")');
}
if (!defined('ADMIN_TAB_PASSWORD')) {
    define('ADMIN_TAB_PASSWORD', '$(php_single_quoted "$ADMIN_TAB_PASSWORD")');
}
?>
PHP
    chmod 640 "$config_path"
    chown root:"$WEB_USER" "$config_path"
    echo "  config.local.php created."
}

setup_upload_dirs() {
    echo ""
    echo "Creating upload directories..."
    local uploads_root="${WEB_PATH}/uploads" sub
    mkdir -p "$uploads_root"
    for sub in "${UPLOAD_SUBDIRS[@]}"; do
        mkdir -p "${uploads_root}/${sub}"
        chmod 775 "${uploads_root}/${sub}"
    done
    chown -R "${WEB_USER}:${WEB_USER}" "$uploads_root"
    chmod 775 "$uploads_root"
    echo "  Upload directories ready for ${WEB_USER}."
}

set_web_permissions() {
    echo ""
    echo "Setting web root ownership..."
    chown -R "${WEB_USER}:${WEB_USER}" "$WEB_PATH"
    if [[ -f "${WEB_PATH}/config.local.php" ]]; then
        chown root:"$WEB_USER" "${WEB_PATH}/config.local.php"
        chmod 640 "${WEB_PATH}/config.local.php"
    fi
    echo "  Ownership updated."
}

print_summary() {
    echo ""
    echo "=============================================="
    echo "  Home Documentation System — Install complete"
    echo "=============================================="
    echo "  Web root:  ${WEB_PATH}"
    echo "  Web user:  ${WEB_USER}"
    echo "  Database:  ${DB_NAME} (user: ${DB_USER})"
    echo "  URL:       http://${SERVER_HOSTNAME}/incur/"
    echo "  Secrets:   ${WEB_PATH}/config.local.php"
    echo ""
}

main() {
    ensure_root
    check_prerequisites
    collect_prompts
    test_mysql_admin
    setup_database
    import_schema
    deploy_application
    write_config_local
    setup_upload_dirs
    set_web_permissions
    print_summary
}

main "$@"