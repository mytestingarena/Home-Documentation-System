# Home Documentation System

PHP web app for documenting house utilities, equipment, media, breakers, and maintenance.

This repository contains **application source only** — no user uploads, house records, WiFi passwords, or database dumps.

## Requirements

- PHP 8.x with mysqli
- MySQL / MariaDB
- Apache or nginx + php-fpm

## Setup

1. Create the database and import schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE house_info CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p house_info < db/schema.sql
   mysql -u root -p house_info < db/migrations.sql
   ```

2. Copy `incur/` to your web root (e.g. `/var/www/html/incur/`).

3. Create `incur/config.local.php` from `incur/config.local.php.example` and set the real MySQL password.

4. Set the WiFi tab unlock password in `incur/house.php` (`WIFI_TAB_PASSWORD`) or move it to `config.local.php` before production use.

5. Ensure upload directories are writable by the web server:
   ```bash
   chown -R www-data:www-data incur/uploads
   chmod -R 755 incur/uploads
   ```

## Deploy note

Do **not** overwrite `config.local.php` on the server during deploys.

## Regenerating this export

On the LXC server:
```bash
./export-github-copy.sh
```

## Support

Bitcoin address for donations if you find this useful:

`bc1qdj9d6llxz0qswhewqwnmy8zl63lydm0p2mrk`
