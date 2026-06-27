# Home Documentation System

**A self-hosted home reference for everything you own, maintain, and pay for.**

Home Documentation System (HDS) is a PHP web app that gives homeowners one organized place to record appliance specs, utility accounts, breaker layouts, maintenance history, photos, manuals, and project costs. Instead of digging through emails, paper folders, and phone photos when something breaks or a bill is due, the information is already there — searchable, structured, and tied to each property you manage.

This repository contains **application source only** — no user uploads, house records, WiFi passwords, or database dumps.

**Support:** If you find HDS useful, donations are welcome via Bitcoin: `bc1qdj9d6llxz0qswhewqwnmy8zl63lydm0p2mrk`

---

## Why homeowners need this

- **When the furnace fails at midnight**, you have the model, serial number, and past service log — including who did the work and what they charged.
- **When you flip a breaker**, the panel map shows which breaker feeds which room.
- **When propane or water bills arrive**, account numbers, due dates, receipts, and payment history are in one tab.
- **When you sell or rent**, walkthrough videos, exterior photos, and appliance details are ready to hand off.
- **When you start a deck or bathroom project**, materials, quantities, and running costs stay tracked until the job is done.
- **When a guest or contractor needs WiFi**, credentials are stored behind a simple password lock — not on a sticky note.

HDS supports **multiple houses** (primary home, cabin, rental) from a single install, with per-house tabs and sections you can show or hide in Admin.

---

## What you can document — by tab

### Permanent Items
Built-in home systems and outdoor work, each with specs and a maintenance log.

- **Furnace, water heater, dishwasher, washer, dryer, air conditioner** — brand, model, serial number, efficiency, kWh, capacity
- **Maintenance log per appliance** — date, part number, homeowner vs. contractor, notes, contractor price and payment method (debit, credit card, check)
- **Outdoor work** — deck, fence, landscaping, irrigation, pool, roofing, and more; date completed, contractor, notes, **photo uploads with rename**
- **Breaker panels** — add panels (6–30 breakers), label every breaker by room and amps, top-down numbering matching real panels

### Utility Services
Accounts, bills, and receipts for what keeps the house running.

- **Electric meter** — utility company, meter number, phone
- **Generator** — brand, model, serial, fuel type (LP/NG), efficiency
- **Water utility** — account and meter numbers, billing frequency, phone, bill tracking with paid/unpaid status
- **Propane** — account details, bills, receipt uploads

### Household Items
Smaller fixed assets inside the home.

- **TVs, servers, and other items** — type, brand, model, serial number, notes

### Tools
Inventory of tools kept at each property.

- **Tool name, power type** (battery, AC, pneumatic, manual), description, and which house it belongs to

### Maintenance
Service history for equipment that is not a built-in appliance.

- **ATVs, boats, lawnmowers, and other equipment** — per-item fluids, parts/filters with part numbers, and a dated maintenance log (hours/mileage, work performed, notes)

### Media
Visual record of the property.

- **Interior and exterior photos** — upload, rename, lightbox view, delete
- **Site walkthrough videos** — compressed MP4 upload with sync for on-disk files
- **IR interior and exterior scans** — thermal images kept separate from regular photos

### Designs
Plans and drawings for reference or renovation.

- **Upload design files** (PDF, drawings, spreadsheets) with thumbnails for PDFs

### User Manuals
PDF manuals for appliances and devices.

- **Upload and browse** owner manuals in one list per house

### Map Location
Where the property is and what you owe locally.

- **Address, GPS coordinates, map zoom, Google Maps embed**
- **Property taxes** — parcel ID, amount owed, due date, paid status, check number

### WiFi *(password-protected tab)*
Network credentials without leaving them in plain sight on the fridge.

- **Network name, password, and notes** — unlock with a tab password; show/hide password in the UI

### Project List
Track home improvement costs as you go.

- **Active projects** — add materials with quantity and unit price; running subtotal with tax
- **Completed projects** — mark done and keep a cost history

### Admin *(password-protected)*
Tailor the app to each house.

- **Show or hide any tab or section** (e.g. hide propane at a house that does not use it)

---

## Requirements

- PHP 8.x with mysqli
- MySQL / MariaDB
- Apache or nginx + php-fpm
- ffmpeg (optional, recommended for walkthrough video compression)

## Setup

1. Create the database and import schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE house_info CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p house_info < db/schema.sql
   mysql -u root -p house_info < db/migrations.sql
   ```

2. Copy `incur/` to your web root (e.g. `/var/www/html/incur/`).

3. Create `incur/config.local.php` from `incur/config.local.php.example` and set the real MySQL password.

4. Set the WiFi and Admin tab passwords in `incur/house.php` (`WIFI_TAB_PASSWORD` / `ADMIN_TAB_PASSWORD`) or move them to `config.local.php` before production use.

5. Ensure upload directories are writable by the web server:
   ```bash
   chown -R www-data:www-data incur/uploads
   chmod -R 775 incur/uploads
   ```

## Deploy note

Do **not** overwrite `config.local.php` on the server during deploys.

## Regenerating this export

From the project scripts folder:
```bash
./export-github-copy.sh
./publish-github.sh -m "Your commit message"
```

## Support

Bitcoin address for donations if you find this useful:

`bc1qdj9d6llxz0qswhewqwnmy8zl63lydm0p2mrk`