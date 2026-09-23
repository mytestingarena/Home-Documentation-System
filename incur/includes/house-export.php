<?php
// House data export helpers — realtor-friendly spreadsheet ZIP and PDF.

declare(strict_types=1);

require_once __DIR__ . '/media-video.php';

function hds_export_sanitize_filename(string $name): string
{
    $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($name)) ?? 'house';
    $slug = trim($slug, '-');
    return $slug !== '' ? strtolower($slug) : 'house';
}

function hds_export_fetch_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** Columns omitted from exports (internal / app-only). */
function hds_export_skip_column(string $key): bool
{
    static $skip = [
        'id' => true,
        'house_id' => true,
        'panel_id' => true,
        'string_id' => true,
        'equipment_id' => true,
        'project_id' => true,
        'hardware_id' => true,
        'bill_id' => true,
        'outdoor_work_id' => true,
        'house_work_id' => true,
        'log_id' => true,
        'google_embed_src' => true,
        'map_zoom' => true,
        'thumbnail' => true,
    ];

    return isset($skip[$key]);
}

/** Human-readable labels for database column names. */
function hds_export_friendly_label(string $key): string
{
    static $labels = [
        'name' => 'Name',
        'item_type' => 'Item Type',
        'brand' => 'Brand',
        'model' => 'Model',
        'sn' => 'Serial Number',
        'efficiency' => 'Efficiency',
        'kwh' => 'kWh',
        'capacity' => 'Capacity',
        'fuel_type' => 'Fuel Type',
        'meter_number' => 'Meter Number',
        'company' => 'Company',
        'phone' => 'Phone',
        'connection_type' => 'Connection Type',
        'watts' => 'Watts',
        'size' => 'Panel Size (spaces)',
        'column_num' => 'Column',
        'row_num' => 'Row',
        'room' => 'Room / Location',
        'amp' => 'Amps',
        'panel_name' => 'Panel',
        'trade' => 'Trade',
        'city' => 'City',
        'grok_recommendation' => 'Recommended',
        'type' => 'Type',
        'isp_company' => 'ISP Company',
        'modem_sn_pn' => 'Modem S/N or P/N',
        'expected_speeds' => 'Expected Speeds',
        'section' => 'Section',
        'filename' => 'File Name',
        'is_ir' => 'Photo Type',
        'upload_date' => 'Uploaded',
        'utility_type' => 'Utility',
        'amount_owed' => 'Amount Owed',
        'due_date' => 'Due Date',
        'is_paid' => 'Payment Status',
        'payment_method' => 'Payment Method',
        'account_number' => 'Account Number',
        'billing_frequency' => 'Billing Frequency',
        'gallons' => 'Tank Gallons',
        'provider' => 'Provider',
        'tank_sn' => 'Tank Serial Number',
        'parcel_id' => 'Parcel ID',
        'check_number' => 'Check Number',
        'date_added' => 'Date Added',
        'completed' => 'Completed',
        'date_completed' => 'Date Completed',
        'material_name' => 'Material',
        'price' => 'Price',
        'quantity' => 'Quantity',
        'url' => 'URL',
        'project_name' => 'Project',
        'network_name' => 'Network Name',
        'password' => 'Password',
        'power_type' => 'Power Type',
        'description' => 'Description',
        'category' => 'Category',
        'equipment_name' => 'Equipment',
        'fluid_name' => 'Fluid',
        'specification' => 'Specification',
        'part_name' => 'Part',
        'part_number' => 'Part Number',
        'log_date' => 'Date',
        'hours_mileage' => 'Hours / Mileage',
        'work_type' => 'Work Type',
        'contractor' => 'Contractor',
        'completed_by' => 'Completed By',
        'contractor_price' => 'Contractor Price',
        'payment_reference' => 'Payment Reference',
        'device_type' => 'Device Type',
        'make_model' => 'Make / Model',
        'cpu' => 'CPU',
        'ram' => 'RAM',
        'storage' => 'Storage',
        'ip_address' => 'IP Address',
        'mac_address' => 'MAC Address',
        'location' => 'Location',
        'role' => 'Role',
        'serial_number' => 'Serial Number',
        'instance_type' => 'Instance Type',
        'os' => 'Operating System',
        'cpu_cores' => 'CPU Cores',
        'disk' => 'Disk',
        'network' => 'Network',
        'ports' => 'Ports',
        'purpose' => 'Purpose',
        'backup_notes' => 'Backup Notes',
        'created_at' => 'Created',
        'address' => 'Address',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'tax_number' => 'Tax / Parcel Number',
    ];

    if (isset($labels[$key])) {
        return $labels[$key];
    }

    $label = str_replace('_', ' ', $key);
    return ucwords($label);
}

/** Property overview fields shown first in summary exports. */
function hds_export_house_overview_fields(): array
{
    return [
        'name' => 'Property Name',
        'address' => 'Address',
        'tax_number' => 'Tax / Parcel Number',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
    ];
}

function hds_export_collect_house_data(mysqli $conn, int $house_id): ?array
{
    $house = $conn->query("SELECT * FROM houses WHERE id = $house_id LIMIT 1");
    if (!$house || $house->num_rows === 0) {
        return null;
    }

    $meta = $house->fetch_assoc();
    $sections = [];

    $add = static function (string $title, array $rows, string $tab = '') use (&$sections): void {
        if (count($rows) === 0) {
            return;
        }
        $sections[] = [
            'title' => $title,
            'tab' => $tab !== '' ? $tab : $title,
            'rows' => $rows,
        ];
    };

    // Realtor-friendly order: systems and services first, then improvements, then reference files.
    $add('Permanent Items', hds_export_fetch_rows($conn, "SELECT * FROM permanent_items WHERE house_id = $house_id ORDER BY item_type"), 'Permanent Items');
    $add('Permanent Maintenance Log', hds_export_fetch_rows($conn, "SELECT * FROM permanent_maintenance_log WHERE house_id = $house_id ORDER BY log_date DESC, id DESC"), 'Permanent Items');
    $breakerPanels = hds_export_collect_breaker_panels($conn, $house_id);
    if (count($breakerPanels) > 0) {
        $sections[] = [
            'title' => 'Breaker Panels',
            'tab' => 'Permanent Items',
            'kind' => 'breaker_panels',
            'rows' => $breakerPanels,
        ];
    }
    $add('Contractors', hds_export_fetch_rows($conn, "SELECT * FROM contractors WHERE house_id = $house_id ORDER BY name"), 'Contractors');
    $add('Electric Meters', hds_export_fetch_rows($conn, "SELECT * FROM electric_meters WHERE house_id = $house_id"), 'Utilities');
    $add('Water Utility', hds_export_fetch_rows($conn, "SELECT * FROM water_utilities WHERE house_id = $house_id"), 'Utilities');
    $add('Propane Utility', hds_export_fetch_rows($conn, "SELECT * FROM propane_utilities WHERE house_id = $house_id"), 'Utilities');
    $add('Utility Bills', hds_export_fetch_rows($conn, "SELECT * FROM utility_bills WHERE house_id = $house_id ORDER BY due_date DESC"), 'Utilities');
    $add('Property Taxes', hds_export_fetch_rows($conn, "SELECT * FROM property_taxes WHERE house_id = $house_id ORDER BY due_date DESC"), 'Utilities');
    $add('Generators', hds_export_fetch_rows($conn, "SELECT * FROM generators WHERE house_id = $house_id"), 'Utilities');
    $add('Solar Inverters', hds_export_fetch_rows($conn, "SELECT * FROM solar_inverters WHERE house_id = $house_id"), 'Utilities');
    $add('Solar Strings', hds_export_fetch_rows($conn, "SELECT * FROM solar_strings WHERE house_id = $house_id"), 'Utilities');
    $add('Solar Panels', hds_export_fetch_rows(
        $conn,
        "SELECT p.brand, p.watts, s.connection_type
         FROM solar_panels p
         INNER JOIN solar_strings s ON p.string_id = s.id
         WHERE s.house_id = $house_id
         ORDER BY p.id"
    ), 'Utilities');
    $add('Battery Strings', hds_export_fetch_rows($conn, "SELECT connection_type FROM battery_strings WHERE house_id = $house_id"), 'Utilities');
    $add('Batteries', hds_export_fetch_rows(
        $conn,
        "SELECT b.brand, b.watts, s.connection_type
         FROM batteries b
         INNER JOIN battery_strings s ON b.string_id = s.id
         WHERE s.house_id = $house_id
         ORDER BY b.id"
    ), 'Utilities');
    $add('Electric Panels', hds_export_fetch_rows($conn, "SELECT name, size FROM electric_panels WHERE house_id = $house_id ORDER BY name"), 'Utilities');
    $add('Breakers', hds_export_fetch_rows(
        $conn,
        "SELECT p.name AS panel_name, b.column_num, b.row_num, b.room, b.amp
         FROM breakers b
         INNER JOIN electric_panels p ON b.panel_id = p.id
         WHERE p.house_id = $house_id
         ORDER BY p.name, b.column_num, b.row_num"
    ), 'Utilities');
    $add('Household Items', hds_export_fetch_rows($conn, "SELECT type, brand, model, sn, isp_company, modem_sn_pn, expected_speeds, notes FROM household_items WHERE house_id = $house_id ORDER BY type, id"), 'Household');
    $add('House Work', hds_export_fetch_rows($conn, "SELECT work_type, description, date_completed, contractor, notes FROM house_work_items WHERE house_id = $house_id ORDER BY date_completed DESC"), 'Work');
    $add('Outdoor Work', hds_export_fetch_rows($conn, "SELECT work_type, description, date_completed, contractor, notes FROM outdoor_work_items WHERE house_id = $house_id ORDER BY date_completed DESC"), 'Work');
    $add('Projects', hds_export_fetch_rows($conn, "SELECT name, date_added, completed, date_completed FROM projects WHERE house_id = $house_id ORDER BY date_added DESC"), 'Projects');
    $add('Project Materials', hds_export_fetch_rows(
        $conn,
        "SELECT p.name AS project_name, m.material_name, m.price, m.quantity, m.url
         FROM project_materials m
         INNER JOIN projects p ON m.project_id = p.id
         WHERE p.house_id = $house_id
         ORDER BY p.name, m.id"
    ), 'Projects');
    $add('Maintenance Equipment', hds_export_fetch_rows($conn, "SELECT name, category, notes FROM maintenance_equipment WHERE house_id = $house_id ORDER BY name"), 'Maintenance');
    $add('Maintenance Fluids', hds_export_fetch_rows(
        $conn,
        "SELECT e.name AS equipment_name, f.fluid_name, f.specification, f.capacity, f.notes
         FROM maintenance_fluids f
         INNER JOIN maintenance_equipment e ON f.equipment_id = e.id
         WHERE e.house_id = $house_id
         ORDER BY e.name, f.id"
    ), 'Maintenance');
    $add('Maintenance Parts', hds_export_fetch_rows(
        $conn,
        "SELECT e.name AS equipment_name, p.part_name, p.part_number, p.notes
         FROM maintenance_parts p
         INNER JOIN maintenance_equipment e ON p.equipment_id = e.id
         WHERE e.house_id = $house_id
         ORDER BY e.name, p.id"
    ), 'Maintenance');
    $add('Maintenance Log', hds_export_fetch_rows(
        $conn,
        "SELECT e.name AS equipment_name, l.log_date, l.description, l.hours_mileage, l.notes
         FROM maintenance_log l
         INNER JOIN maintenance_equipment e ON l.equipment_id = e.id
         WHERE e.house_id = $house_id
         ORDER BY l.log_date DESC, l.id DESC"
    ), 'Maintenance');
    $add('Tools', hds_export_fetch_rows($conn, "SELECT name, power_type, description FROM tools WHERE house_id = $house_id ORDER BY name"), 'Tools');
    $add('WiFi Networks', hds_export_fetch_rows($conn, "SELECT network_name, password, notes FROM wifi_networks WHERE house_id = $house_id ORDER BY network_name"), 'WiFi');
    $add('Homelab Hardware', hds_export_fetch_rows($conn, "SELECT name, device_type, make_model, cpu, ram, storage, ip_address, location, role, serial_number, notes FROM homelab_hardware WHERE house_id = $house_id ORDER BY name"), 'Home Lab');
    $add('Homelab Instances', hds_export_fetch_rows($conn, "SELECT name, instance_type, os, ip_address, cpu_cores, ram, disk, network, ports, purpose, backup_notes, notes FROM homelab_instances WHERE house_id = $house_id ORDER BY name"), 'Home Lab');
    $add('Photos', hds_export_fetch_rows($conn, "SELECT section, filename, is_ir, upload_date FROM photos WHERE house_id = $house_id ORDER BY upload_date DESC"), 'Media and Files');
    $add('Designs and Plans', hds_export_fetch_rows($conn, "SELECT filename, upload_date FROM designs WHERE house_id = $house_id ORDER BY upload_date DESC"), 'Media and Files');
    $add('User Manuals', hds_export_fetch_rows($conn, "SELECT filename, upload_date FROM user_manuals WHERE house_id = $house_id ORDER BY upload_date DESC"), 'Media and Files');

    return [
        'meta' => $meta,
        'sections' => $sections,
        'section_images' => hds_export_collect_section_images($conn, $house_id),
        'exported_at' => date('Y-m-d H:i:s'),
    ];
}

function hds_export_collect_breaker_panels(mysqli $conn, int $house_id): array
{
    $panels = [];
    $result = $conn->query("SELECT id, name, size FROM electric_panels WHERE house_id = $house_id ORDER BY name, id");
    if (!$result) {
        return [];
    }

    while ($panel = $result->fetch_assoc()) {
        $panel_id = (int)$panel['id'];
        $size = (int)($panel['size'] ?? 24);
        if (!in_array($size, [6, 12, 24, 28, 30], true)) {
            $size = 24;
        }
        $max_rows = (int)ceil($size / 2);
        $map = [];
        $breakers = $conn->query("SELECT column_num, row_num, room, amp FROM breakers WHERE panel_id = $panel_id");
        if ($breakers) {
            while ($breaker = $breakers->fetch_assoc()) {
                $key = (int)$breaker['column_num'] . '-' . (int)$breaker['row_num'];
                $map[$key] = $breaker;
            }
        }

        $grid = [];
        for ($row = 1; $row <= $max_rows; $row++) {
            $left = $map['1-' . $row] ?? null;
            $right = $map['2-' . $row] ?? null;
            $grid[] = [
                'left_num' => ($row - 1) * 2 + 1,
                'right_num' => ($row - 1) * 2 + 2,
                'left_room' => trim((string)($left['room'] ?? '')),
                'left_amp' => ($left && $left['amp'] !== null && $left['amp'] !== '') ? (string)$left['amp'] : '',
                'right_room' => trim((string)($right['room'] ?? '')),
                'right_amp' => ($right && $right['amp'] !== null && $right['amp'] !== '') ? (string)$right['amp'] : '',
            ];
        }

        $panels[] = [
            'name' => (string)($panel['name'] ?? 'Panel'),
            'size' => $size,
            'grid' => $grid,
        ];
    }

    return $panels;
}

function hds_export_incur_root(): string
{
    return dirname(__DIR__);
}

function hds_export_is_image_extension(string $ext): bool
{
    return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

function hds_export_is_video_extension(string $ext): bool
{
    return in_array(strtolower($ext), hds_walkthrough_video_extensions(), true);
}

/** @return list<array{path: string, caption: string}> */
function hds_export_collect_section_images(mysqli $conn, int $house_id): array
{
    $root = hds_export_incur_root();
    $images = [];

    $add = static function (string $section, string $path, string $caption) use (&$images): void {
        if (!is_file($path)) {
            return;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!hds_export_is_image_extension($ext)) {
            return;
        }
        $images[$section][] = [
            'path' => $path,
            'caption' => $caption,
        ];
    };

    $photos = hds_export_fetch_rows(
        $conn,
        "SELECT section, filename, is_ir, upload_date
         FROM photos
         WHERE house_id = $house_id
         ORDER BY section, is_ir, upload_date DESC"
    );
    foreach ($photos as $photo) {
        $filename = basename((string)$photo['filename']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (hds_export_is_video_extension($ext)) {
            continue;
        }
        $sectionLabel = trim((string)($photo['section'] ?? 'Photo'));
        if ((int)($photo['is_ir'] ?? 0) === 1) {
            $sectionLabel = 'IR ' . $sectionLabel;
        }
        $add(
            'Photos',
            $root . '/uploads/photos/' . $filename,
            $sectionLabel . ' — ' . $filename
        );
    }

    $permImages = hds_export_fetch_rows(
        $conn,
        "SELECT l.item_type, l.log_date, i.filename
         FROM permanent_maintenance_log_images i
         INNER JOIN permanent_maintenance_log l ON i.log_id = l.id
         WHERE l.house_id = $house_id
         ORDER BY l.log_date DESC, i.upload_date DESC"
    );
    foreach ($permImages as $image) {
        $filename = basename((string)$image['filename']);
        $add(
            'Permanent Maintenance Log',
            $root . '/uploads/maintenance-log/' . $filename,
            (string)$image['item_type'] . ' — ' . (string)$image['log_date'] . ' — ' . $filename
        );
    }

    $houseWorkImages = hds_export_fetch_rows(
        $conn,
        "SELECT w.work_type, w.date_completed, i.filename
         FROM house_work_images i
         INNER JOIN house_work_items w ON i.house_work_id = w.id
         WHERE w.house_id = $house_id
         ORDER BY w.date_completed DESC, i.upload_date DESC"
    );
    foreach ($houseWorkImages as $image) {
        $filename = basename((string)$image['filename']);
        $add(
            'House Work',
            $root . '/uploads/house-work/' . $filename,
            (string)$image['work_type'] . ' — ' . (string)($image['date_completed'] ?? 'No date') . ' — ' . $filename
        );
    }

    $outdoorImages = hds_export_fetch_rows(
        $conn,
        "SELECT w.work_type, w.date_completed, i.filename
         FROM outdoor_work_images i
         INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id
         WHERE w.house_id = $house_id
         ORDER BY w.date_completed DESC, i.upload_date DESC"
    );
    foreach ($outdoorImages as $image) {
        $filename = basename((string)$image['filename']);
        $add(
            'Outdoor Work',
            $root . '/uploads/outdoor-work/' . $filename,
            (string)$image['work_type'] . ' — ' . (string)($image['date_completed'] ?? 'No date') . ' — ' . $filename
        );
    }

    $designs = hds_export_fetch_rows(
        $conn,
        "SELECT filename, thumbnail, upload_date
         FROM designs
         WHERE house_id = $house_id
         ORDER BY upload_date DESC"
    );
    foreach ($designs as $design) {
        $filename = basename((string)$design['filename']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $path = '';
        if (hds_export_is_image_extension($ext)) {
            $path = $root . '/uploads/designs/' . $filename;
        } elseif (!empty($design['thumbnail'])) {
            $thumb = ltrim((string)$design['thumbnail'], '/');
            $candidate = $root . '/' . $thumb;
            if (is_file($candidate)) {
                $path = $candidate;
            }
        }
        if ($path !== '') {
            $add('Designs and Plans', $path, $filename);
        }
    }

    return $images;
}

/** @return ?array{jpeg: string, width: int, height: int} */
function hds_export_prepare_pdf_image(string $path, int $targetPx = 432): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!hds_export_is_image_extension($ext)) {
        return null;
    }

    $source = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'png' => @imagecreatefrompng($path),
        'gif' => @imagecreatefromgif($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };

    if ($source === false) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($source);
        return null;
    }

    $cropSize = min($width, $height);
    $srcX = (int)(($width - $cropSize) / 2);
    $srcY = (int)(($height - $cropSize) / 2);
    $square = imagecreatetruecolor($targetPx, $targetPx);
    if ($square === false) {
        imagedestroy($source);
        return null;
    }

    imagecopyresampled($square, $source, 0, 0, $srcX, $srcY, $targetPx, $targetPx, $cropSize, $cropSize);
    imagedestroy($source);

    ob_start();
    imagejpeg($square, null, 85);
    $jpeg = ob_get_clean();
    imagedestroy($square);

    if (!is_string($jpeg) || $jpeg === '') {
        return null;
    }

    return [
        'jpeg' => $jpeg,
        'width' => $targetPx,
        'height' => $targetPx,
    ];
}

function hds_export_row_headers(array $rows): array
{
    if (count($rows) === 0) {
        return [];
    }

    return array_values(array_filter(
        array_keys($rows[0]),
        static fn (string $key): bool => !hds_export_skip_column($key)
    ));
}

function hds_export_flatten_value(string $key, $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if ($key === 'is_paid') {
        return (int)$value === 1 ? 'Paid' : 'Unpaid';
    }

    if (in_array($key, ['grok_recommendation', 'completed'], true)) {
        return (int)$value === 1 ? 'Yes' : 'No';
    }

    if ($key === 'is_ir') {
        return (int)$value === 1 ? 'Infrared' : 'Standard';
    }

    return (string)$value;
}

function hds_export_sheet_name(string $title, array &$used): string
{
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', '', trim($title)) ?? 'Sheet';
    $name = $name !== '' ? $name : 'Sheet';
    if (strlen($name) > 31) {
        $name = substr($name, 0, 31);
    }

    $base = $name;
    $suffix = 2;
    while (isset($used[strtolower($name)])) {
        $extra = ' ' . $suffix;
        $name = substr($base, 0, 31 - strlen($extra)) . $extra;
        $suffix++;
    }

    $used[strtolower($name)] = true;
    return $name;
}

/** @return list<list<string>> */
function hds_export_summary_sheet_rows(array $export, string $house_name): array
{
    $rows = [
        ['Home Documentation System — Property Export'],
        [],
        ['Property', $house_name],
        ['Exported', (string)($export['exported_at'] ?? date('Y-m-d H:i:s'))],
        [],
        ['Property Overview'],
    ];

    foreach (hds_export_house_overview_fields() as $field => $label) {
        $raw = $export['meta'][$field] ?? '';
        if ($raw === null || $raw === '') {
            continue;
        }
        $rows[] = [$label, hds_export_flatten_value($field, $raw)];
    }

    $rows[] = [];
    $rows[] = ['Workbook Tabs'];
    $rows[] = ['Section', 'Records'];
    foreach ($export['sections'] as $section) {
        if (($section['kind'] ?? '') === 'breaker_panels') {
            continue;
        }
        $rows[] = [$section['title'], (string)count($section['rows'])];
    }

    return $rows;
}

/** @return list<list<string>> */
function hds_export_section_sheet_rows(array $section): array
{
    $rows = [];
    if (($section['kind'] ?? '') === 'breaker_panels') {
        return $rows;
    }
    $headers = hds_export_row_headers($section['rows']);
    if (count($headers) === 0) {
        return $rows;
    }

    $rows[] = array_map('hds_export_friendly_label', $headers);
    foreach ($section['rows'] as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = hds_export_flatten_value($header, $row[$header] ?? '');
        }
        $rows[] = $line;
    }

    return $rows;
}

function hds_export_csv_string(array $export, string $house_name): string
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        return '';
    }

    fwrite($handle, "\xEF\xBB\xBF");

    foreach (hds_export_summary_sheet_rows($export, $house_name) as $row) {
        fputcsv($handle, $row);
    }

    foreach ($export['sections'] as $section) {
        if (($section['kind'] ?? '') === 'breaker_panels') {
            continue;
        }
        fputcsv($handle, []);
        fputcsv($handle, ['=== ' . $section['title'] . ' ===']);
        foreach (hds_export_section_sheet_rows($section) as $row) {
            fputcsv($handle, $row);
        }
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv === false ? '' : $csv;
}

final class HdsWorkbookXlsx
{
    /** @var list<array{name: string, rows: list<list<string>>}> */
    private array $sheets = [];

    public function addSheet(string $name, array $rows): void
    {
        $this->sheets[] = [
            'name' => $name,
            'rows' => $rows,
        ];
    }

    public function build(): ?string
    {
        if (!class_exists('ZipArchive') || count($this->sheets) === 0) {
            return null;
        }

        $tmpdir = sys_get_temp_dir() . '/hds-xlsx-' . bin2hex(random_bytes(8));
        if (!mkdir($tmpdir, 0700, true) && !is_dir($tmpdir)) {
            return null;
        }

        $xlDir = $tmpdir . '/xl';
        $relsDir = $tmpdir . '/_rels';
        $sheetDir = $xlDir . '/worksheets';
        $xlRelsDir = $xlDir . '/_rels';
        mkdir($relsDir, 0700, true);
        mkdir($sheetDir, 0700, true);
        mkdir($xlRelsDir, 0700, true);

        $sheetCount = count($this->sheets);
        $sheetEntries = [];
        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
XML;

        $workbookRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
XML;

        $workbookSheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $sheetNum = $index + 1;
            $sheetPath = "worksheets/sheet{$sheetNum}.xml";
            $sheetEntries[] = $sheetPath;
            $contentTypes .= "\n  <Override PartName=\"/xl/{$sheetPath}\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $workbookSheets .= sprintf(
                '<sheet name="%s" sheetId="%d" r:id="rId%d"/>',
                $this->xmlAttr($sheet['name']),
                $sheetNum,
                $sheetNum + 1
            );
            $workbookRels .= sprintf(
                "\n  <Relationship Id=\"rId%d\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"%s\"/>",
                $sheetNum + 1,
                $sheetPath
            );
            file_put_contents($sheetDir . "/sheet{$sheetNum}.xml", $this->worksheetXml($sheet['rows']));
        }

        $contentTypes .= "\n</Types>";
        $workbookRels .= "\n</Relationships>";

        $workbookXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>{$workbookSheets}</sheets>
</workbook>
XML;

        file_put_contents($tmpdir . '/[Content_Types].xml', $contentTypes);
        file_put_contents($relsDir . '/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        file_put_contents($xlDir . '/workbook.xml', $workbookXml);
        file_put_contents($xlRelsDir . '/workbook.xml.rels', $workbookRels);
        file_put_contents($xlDir . '/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>
XML);

        $zipPath = $tmpdir . '/workbook.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            hds_export_rmdir_recursive($tmpdir);
            return null;
        }

        $zip->addFile($tmpdir . '/[Content_Types].xml', '[Content_Types].xml');
        $zip->addFile($relsDir . '/.rels', '_rels/.rels');
        $zip->addFile($xlDir . '/workbook.xml', 'xl/workbook.xml');
        $zip->addFile($xlRelsDir . '/workbook.xml.rels', 'xl/_rels/workbook.xml.rels');
        $zip->addFile($xlDir . '/styles.xml', 'xl/styles.xml');
        foreach ($sheetEntries as $index => $entry) {
            $zip->addFile($sheetDir . '/sheet' . ($index + 1) . '.xml', 'xl/' . $entry);
        }
        $zip->close();

        $binary = file_get_contents($zipPath);
        hds_export_rmdir_recursive($tmpdir);

        return $binary === false ? null : $binary;
    }

    /** @param list<list<string>> $rows */
    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row as $colIndex => $value) {
                $cellRef = $this->columnLetter($colIndex) . $rowNum;
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $xml .= '<c r="' . $cellRef . '" t="inlineStr"' . $style . '><is><t>'
                    . $this->xmlText((string)$value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function columnLetter(int $index): string
    {
        $index++;
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - 1, 26);
        }
        return $letters;
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

function hds_export_build_xlsx(array $export, string $house_name): ?string
{
    $workbook = new HdsWorkbookXlsx();
    $usedNames = [];

    $workbook->addSheet(hds_export_sheet_name('Summary', $usedNames), hds_export_summary_sheet_rows($export, $house_name));

    foreach ($export['sections'] as $section) {
        $rows = hds_export_section_sheet_rows($section);
        if (count($rows) === 0) {
            continue;
        }
        $workbook->addSheet(hds_export_sheet_name($section['title'], $usedNames), $rows);
    }

    return $workbook->build();
}

function hds_export_send_spreadsheet(array $export, string $house_name): void
{
    $slug = hds_export_sanitize_filename($house_name);
    $date = date('Y-m-d');

    $xlsx = hds_export_build_xlsx($export, $house_name);
    if ($xlsx !== null) {
        $filename = 'property-export-' . $slug . '-' . $date . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string)strlen($xlsx));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $xlsx;
        return;
    }

    hds_export_send_spreadsheet_fallback($export, $house_name);
}

function hds_export_send_spreadsheet_fallback(array $export, string $house_name): void
{
    $slug = hds_export_sanitize_filename($house_name);
    $filename = 'property-export-' . $slug . '-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo hds_export_csv_string($export, $house_name);
}

function hds_export_rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            hds_export_rmdir_recursive($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function hds_export_pick_pdf_columns(array $headers, int $max = 5): array
{
    if (count($headers) <= $max) {
        return $headers;
    }

    static $priority = [
        'name' => 100,
        'item_type' => 95,
        'work_type' => 95,
        'type' => 90,
        'brand' => 90,
        'model' => 85,
        'description' => 85,
        'trade' => 85,
        'material_name' => 85,
        'panel_name' => 85,
        'equipment_name' => 85,
        'project_name' => 85,
        'date_completed' => 80,
        'log_date' => 80,
        'due_date' => 80,
        'date_added' => 75,
        'completed' => 70,
        'contractor' => 70,
        'company' => 70,
        'provider' => 70,
        'utility_type' => 70,
        'amount_owed' => 65,
        'notes' => 60,
        'room' => 60,
        'amp' => 55,
        'sn' => 50,
        'filename' => 50,
        'section' => 45,
    ];

    usort($headers, static function (string $a, string $b) use ($priority): int {
        $scoreA = $priority[$a] ?? 10;
        $scoreB = $priority[$b] ?? 10;
        if ($scoreA === $scoreB) {
            return strcmp($a, $b);
        }
        return $scoreB <=> $scoreA;
    });

    return array_slice($headers, 0, $max);
}

function hds_export_pdf_column_weight(string $header): float
{
    static $weights = [
        'notes' => 3.0,
        'description' => 3.0,
        'backup_notes' => 2.5,
        'url' => 2.0,
        'purpose' => 2.0,
        'material_name' => 1.8,
        'filename' => 1.8,
        'network_name' => 1.6,
        'equipment_name' => 1.5,
        'project_name' => 1.5,
        'panel_name' => 1.4,
        'name' => 1.4,
        'make_model' => 1.4,
        'work_type' => 1.3,
        'item_type' => 1.3,
        'contractor' => 1.3,
        'brand' => 1.2,
        'model' => 1.2,
        'room' => 1.2,
        'log_date' => 1.0,
        'date_completed' => 1.0,
        'due_date' => 1.0,
        'date_added' => 1.0,
        'hours_mileage' => 1.0,
        'amount_owed' => 1.0,
        'amp' => 0.8,
        'column_num' => 0.7,
        'row_num' => 0.7,
    ];

    return $weights[$header] ?? 1.0;
}

final class HdsRealtorPdf
{
    private const PAGE_W = 612.0;
    private const PAGE_H = 792.0;
    private const MARGIN_L = 45.0;
    private const MARGIN_R = 45.0;
    private const MARGIN_T = 718.0;
    private const MARGIN_B = 52.0;
    private const MIN_SECTION_START = 130.0;
    private const IMAGE_COLS = 2;
    private const IMAGE_COL_GAP = 12.0;

    private string $houseName = '';
    private string $exportedAt = '';
    /** @var list<list<array<string, mixed>>> */
    private array $pages = [];
    /** @var list<array{tab: string, section: string}> */
    private array $pageMeta = [];
    private float $y = self::MARGIN_T;
    private string $activeSectionTitle = '';
    private string $activeTabTitle = '';
    /** @var list<string> */
    private array $activeTableHeaders = [];
    /** @var array<string, float> */
    private array $activeColumnWidths = [];
    /** @var array<string, list<array{path: string, caption: string}>> */
    private array $sectionImages = [];
    /** @var array<string, array{jpeg: string, width: int, height: int}> */
    private array $imageObjects = [];
    /** @var array<string, string> */
    private array $imagePathRegistry = [];

    public function build(array $export, string $house_name): void
    {
        $this->houseName = $house_name;
        $this->exportedAt = (string)($export['exported_at'] ?? date('Y-m-d H:i:s'));
        $this->sectionImages = $export['section_images'] ?? [];
        $this->newPage();
        $this->drawCover($export['meta'] ?? []);
        $this->newPage();
        $this->drawOverview($export['meta'] ?? []);
        $this->drawContents($export['sections'] ?? []);

        $groups = [];
        foreach ($export['sections'] as $section) {
            if (in_array((string)($section['title'] ?? ''), ['Electric Panels', 'Breakers'], true)) {
                continue;
            }
            $tab = (string)($section['tab'] ?? $section['title']);
            $groups[$tab][] = $section;
        }

        foreach ($groups as $tabName => $sections) {
            $this->drawTab((string)$tabName, $sections);
        }
    }

    public function render(): string
    {
        $totalPages = max(1, count($this->pages));
        $contentStreams = [];
        $pageImageUsage = [];

        foreach ($this->pages as $index => $ops) {
            $pageNum = $index + 1;
            $usedImages = [];
            $stream = $this->pageChromeStream($index, $pageNum, $totalPages);

            foreach ($ops as $op) {
                $stream .= match ($op['type']) {
                    'text' => $this->textOp($op),
                    'line' => $this->lineOp($op),
                    'rect' => $this->rectOp($op),
                    'image' => $this->imageOp($op),
                    default => '',
                };
                if (($op['type'] ?? '') === 'image') {
                    $usedImages[(string)$op['name']] = true;
                }
            }

            if ($index > 0) {
                $stream .= $this->lineOp([
                    'x1' => self::MARGIN_L,
                    'y1' => 40,
                    'x2' => self::PAGE_W - self::MARGIN_R,
                    'y2' => 40,
                ]);
            }
            $stream .= $this->textOp([
                'x' => self::MARGIN_L,
                'y' => 28,
                'size' => 8,
                'text' => $this->houseName,
                'bold' => false,
            ]);
            $stream .= $this->textOp([
                'x' => self::PAGE_W - self::MARGIN_R - 90,
                'y' => 28,
                'size' => 8,
                'text' => 'Page ' . $pageNum . ' of ' . $totalPages,
                'bold' => false,
            ]);

            $contentStreams[] = $stream;
            $pageImageUsage[$index] = array_keys($usedImages);
        }

        return $this->assemblePdf($contentStreams, $pageImageUsage);
    }

    private function newPage(): void
    {
        $this->pages[] = [];
        $this->y = self::MARGIN_T;
        $this->pageMeta[] = [
            'tab' => $this->activeTabTitle,
            'section' => $this->activeSectionTitle,
        ];
    }

    private function usableWidth(): float
    {
        return self::PAGE_W - self::MARGIN_L - self::MARGIN_R;
    }

    private function remainingSpace(): float
    {
        return $this->y - self::MARGIN_B;
    }

    private function contentHeight(): float
    {
        return self::MARGIN_T - self::MARGIN_B;
    }

    private function ensureSpace(float $needed): void
    {
        if ($this->y - $needed < self::MARGIN_B) {
            $this->newPage();
            if ($this->activeSectionTitle !== '') {
                $this->drawSectionContinuationBanner();
            }
        }
    }

    private function ensureBlockFits(float $needed, float $minStart = self::MIN_SECTION_START): void
    {
        $available = $this->remainingSpace();
        if ($needed <= $available) {
            return;
        }
        if ($needed <= $this->contentHeight() || $available < $minStart) {
            $this->newPage();
        }
    }

    private function setRunningTitles(string $tab, string $section): void
    {
        $this->activeTabTitle = $tab;
        $this->activeSectionTitle = $section;
        $index = count($this->pageMeta) - 1;
        if ($index >= 0) {
            if ($this->pageMeta[$index]['tab'] === '') {
                $this->pageMeta[$index]['tab'] = $tab;
            }
            if ($this->pageMeta[$index]['section'] === '') {
                $this->pageMeta[$index]['section'] = $section;
            }
        }
    }

    private function pageChromeStream(int $index, int $pageNum, int $totalPages): string
    {
        if ($index === 0) {
            return '';
        }

        $meta = $this->pageMeta[$index] ?? ['tab' => '', 'section' => ''];
        $running = trim((string)($meta['tab'] ?? ''));
        if ($running === '') {
            $running = trim((string)($meta['section'] ?? ''));
        }
        $exported = $this->exportedAt !== '' ? date('M j, Y', strtotime($this->exportedAt)) : date('M j, Y');

        $stream = $this->rectOp([
            'x' => 0,
            'y' => 752,
            'w' => self::PAGE_W,
            'h' => 40,
            'r' => 0.173,
            'g' => 0.243,
            'b' => 0.314,
        ]);
        $stream .= $this->textOp([
            'x' => self::MARGIN_L,
            'y' => 775,
            'size' => 11,
            'text' => 'Home Documentation System',
            'bold' => true,
            'r' => 1,
            'g' => 1,
            'b' => 1,
        ]);
        $houseLabel = $this->houseName;
        $houseWidth = $this->textWidth($houseLabel, 11);
        $maxHouse = 240.0;
        if ($houseWidth > $maxHouse) {
            while (strlen($houseLabel) > 4 && $this->textWidth($houseLabel . '...', 11) > $maxHouse) {
                $houseLabel = substr($houseLabel, 0, -1);
            }
            $houseLabel .= '...';
            $houseWidth = $this->textWidth($houseLabel, 11);
        }
        $stream .= $this->textOp([
            'x' => self::PAGE_W - self::MARGIN_R - $houseWidth,
            'y' => 775,
            'size' => 11,
            'text' => $houseLabel,
            'bold' => true,
            'r' => 1,
            'g' => 1,
            'b' => 1,
        ]);
        $sub = 'Property export  ·  ' . $exported;
        if ($running !== '') {
            $sub .= '  ·  ' . $running;
        }
        $stream .= $this->textOp([
            'x' => self::MARGIN_L,
            'y' => 760,
            'size' => 8,
            'text' => $sub,
            'bold' => false,
            'r' => 0.85,
            'g' => 0.89,
            'b' => 0.93,
        ]);

        return $stream;
    }

    private function addText(float $x, float $y, int $size, string $text, bool $bold = false): void
    {
        $this->pages[count($this->pages) - 1][] = [
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'text' => $text,
            'bold' => $bold,
        ];
    }

    private function addRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void
    {
        $this->pages[count($this->pages) - 1][] = [
            'type' => 'rect',
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'r' => $r,
            'g' => $g,
            'b' => $b,
        ];
    }

    private function addLine(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->pages[count($this->pages) - 1][] = [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
        ];
    }

    private function drawWrapped(float $x, float $maxWidth, int $size, string $text, bool $bold = false, float $lineHeight = 13.0): void
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate, $size) > $maxWidth && $line !== '') {
                $this->ensureSpace($lineHeight);
                $this->addText($x, $this->y, $size, $line, $bold);
                $this->y -= $lineHeight;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $this->ensureSpace($lineHeight);
            $this->addText($x, $this->y, $size, $line, $bold);
            $this->y -= $lineHeight;
        }
    }

    private function textWidth(string $text, int $size): float
    {
        return strlen($text) * ($size * 0.52);
    }

    /** @return list<string> */
    private function wrapLines(string $text, int $size, float $maxWidth): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $lines = [];
        $paragraphs = preg_split("/\r\n|\n|\r/", $text) ?: [$text];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($candidate, $size) > $maxWidth && $line !== '') {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** @param list<string> $headers */
    private function computeColumnWidths(array $headers, float $tableWidth): array
    {
        $totalWeight = 0.0;
        foreach ($headers as $header) {
            $totalWeight += hds_export_pdf_column_weight($header);
        }
        if ($totalWeight <= 0) {
            $totalWeight = (float)count($headers);
        }

        $widths = [];
        foreach ($headers as $header) {
            $widths[$header] = (hds_export_pdf_column_weight($header) / $totalWeight) * $tableWidth;
        }

        return $widths;
    }

    private function drawCover(array $meta): void
    {
        $address = trim((string)($meta['address'] ?? ''));
        $centerX = self::PAGE_W / 2;

        $this->addText($centerX - 120, 500, 22, 'Property Documentation', true);
        $this->addText($centerX - 95, 470, 14, 'Home Documentation System', false);
        $this->addText($centerX - (strlen($this->houseName) * 3.5), 410, 18, $this->houseName, true);

        if ($address !== '') {
            $this->drawWrapped($centerX - 180, 360, 11, $address, false, 14.0);
            $this->y = 360;
        }

        $this->addText($centerX - 105, 300, 11, 'Prepared for real estate handoff', false);
        $this->addText($centerX - 70, 275, 11, 'Exported ' . $this->exportedAt, false);
        $this->addText($centerX - 125, 240, 10, 'Print this PDF or share the Excel workbook.', false);
    }

    private function drawOverview(array $meta): void
    {
        $this->addText(self::MARGIN_L, $this->y, 16, 'Property Overview', true);
        $this->y -= 24;

        foreach (hds_export_house_overview_fields() as $field => $label) {
            $value = hds_export_flatten_value($field, $meta[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $this->ensureSpace(16);
            $this->addText(self::MARGIN_L, $this->y, 10, $label . ':', true);
            $this->drawWrapped(self::MARGIN_L + 130, $this->usableWidth() - 130, 10, $value);
            $this->y -= 4;
        }

        $this->y -= 10;
        $this->addText(self::MARGIN_L, $this->y, 10, 'This packet summarizes systems, maintenance, utilities, improvements, and on-file documents.', false);
        $this->y -= 18;
    }

    private function drawContents(array $sections): void
    {
        $this->ensureSpace(30);
        $this->addText(self::MARGIN_L, $this->y, 14, 'Contents', true);
        $this->y -= 20;

        $groups = [];
        foreach ($sections as $section) {
            if (in_array((string)($section['title'] ?? ''), ['Electric Panels', 'Breakers'], true)) {
                continue;
            }
            $tab = (string)($section['tab'] ?? $section['title']);
            $groups[$tab][] = $section;
        }

        foreach ($groups as $tabName => $tabSections) {
            $this->ensureSpace(16);
            $this->addText(self::MARGIN_L, $this->y, 10, (string)$tabName, true);
            $this->y -= 14;
            foreach ($tabSections as $section) {
                $this->ensureSpace(13);
                $line = $section['title'] . '  —  ' . count($section['rows']) . ' record' . (count($section['rows']) === 1 ? '' : 's');
                $this->addText(self::MARGIN_L + 14, $this->y, 9, $line, false);
                $this->y -= 13;
            }
            $this->y -= 4;
        }
        $this->y -= 6;
    }

    /** @param list<array<string, mixed>> $sections */
    private function drawTab(string $tabName, array $sections): void
    {
        if (count($sections) === 0) {
            return;
        }

        $showTabHeading = count($sections) > 1 || (($sections[0]['title'] ?? '') !== $tabName);
        $tabHeight = $showTabHeading ? 28.0 : 0.0;
        foreach ($sections as $section) {
            $tabHeight += $this->estimateSectionHeight($section);
        }

        $this->activeTabTitle = $tabName;
        $this->ensureBlockFits($tabHeight, self::MIN_SECTION_START);

        if ($showTabHeading) {
            $this->ensureBlockFits(28.0, 80.0);
            $this->setRunningTitles($tabName, (string)($sections[0]['title'] ?? $tabName));
            $this->addText(self::MARGIN_L, $this->y, 13, strtoupper($tabName), true);
            $this->y -= 8;
            $this->addLine(self::MARGIN_L, $this->y, self::MARGIN_L + $this->usableWidth(), $this->y);
            $this->y -= 16;
        }

        foreach ($sections as $index => $section) {
            $this->ensureBlockFits($this->estimateSectionHeight($section), self::MIN_SECTION_START);
            $this->drawSection($section, $index === 0 && !$showTabHeading);
        }

        $this->y -= 10;
        $this->activeTabTitle = '';
        $this->activeSectionTitle = '';
    }

    private function drawSection(array $section, bool $asTabTitle = false): void
    {
        if (($section['kind'] ?? '') === 'breaker_panels') {
            $this->drawBreakerPanels($section, $asTabTitle);
            return;
        }

        $headers = hds_export_row_headers($section['rows']);
        if (count($headers) === 0) {
            return;
        }

        $title = (string)$section['title'];
        $tab = (string)($section['tab'] ?? $title);
        $this->setRunningTitles($tab, $title);

        $titleSize = $asTabTitle ? 15 : 12;
        $this->addText(self::MARGIN_L, $this->y, $titleSize, $title, true);
        $this->y -= $asTabTitle ? 22 : 18;

        $pdfHeaders = hds_export_pick_pdf_columns($headers, 6);
        $tableWidth = $this->usableWidth();
        $this->activeTableHeaders = $pdfHeaders;
        $this->activeColumnWidths = $this->computeColumnWidths($pdfHeaders, $tableWidth);
        $fontSize = 8;

        $this->drawTableHeader($fontSize);

        foreach ($section['rows'] as $row) {
            $this->drawTableRow($row, $fontSize);
        }

        if (count($headers) > count($pdfHeaders)) {
            $this->y -= 8;
            $this->drawWrapped(
                self::MARGIN_L,
                $this->usableWidth(),
                8,
                'Additional fields available in the spreadsheet export: ' . implode(', ', array_map('hds_export_friendly_label', array_diff($headers, $pdfHeaders))),
                false,
                11.0
            );
        }

        $sectionImages = $this->sectionImages[$section['title']] ?? [];
        if (count($sectionImages) > 0) {
            $this->drawSectionImages($sectionImages);
        }

        $this->y -= 8;
        $this->activeTableHeaders = [];
        $this->activeColumnWidths = [];
    }

    private function estimateSectionHeight(array $section): float
    {
        if (($section['kind'] ?? '') === 'breaker_panels') {
            return $this->estimateBreakerPanelsHeight($section);
        }

        $headers = hds_export_row_headers($section['rows']);
        if (count($headers) === 0) {
            return 0.0;
        }

        $pdfHeaders = hds_export_pick_pdf_columns($headers, 6);
        $widths = $this->computeColumnWidths($pdfHeaders, $this->usableWidth());
        $fontSize = 8;
        $height = 22.0;
        $height += $this->estimateTableHeaderHeight($pdfHeaders, $widths, $fontSize);

        foreach ($section['rows'] as $row) {
            $height += $this->estimateTableRowHeight($row, $pdfHeaders, $widths, $fontSize);
        }

        if (count($headers) > count($pdfHeaders)) {
            $height += 28.0;
        }

        $images = $this->sectionImages[$section['title']] ?? [];
        if (count($images) > 0) {
            $size = $this->imageGridSize();
            $rows = (int)ceil(count($images) / self::IMAGE_COLS);
            $height += 30.0 + ($rows * ($size + 28.0));
        }

        return $height + 10.0;
    }

    /** @param list<string> $headers */
    private function estimateTableHeaderHeight(array $headers, array $widths, int $fontSize): float
    {
        $maxLines = 1;
        foreach ($headers as $header) {
            $lines = $this->wrapLines(hds_export_friendly_label($header), $fontSize, ($widths[$header] ?? 80) - 8);
            $maxLines = max($maxLines, max(1, count($lines)));
        }
        return ($maxLines * 11.0) + 4.0;
    }

    /** @param list<string> $headers */
    private function estimateTableRowHeight(array $row, array $headers, array $widths, int $fontSize): float
    {
        $maxLines = 1;
        foreach ($headers as $header) {
            $value = hds_export_flatten_value($header, $row[$header] ?? '');
            $lines = $this->wrapLines($value, $fontSize, ($widths[$header] ?? 80) - 8);
            $maxLines = max($maxLines, max(1, count($lines)));
        }
        return ($maxLines * 11.0) + 6.0;
    }

    /** @return list<float> */
    private function breakerColumnWidths(): array
    {
        $total = $this->usableWidth();
        $num = 32.0;
        $amp = 42.0;
        $room = ($total - (($num + $amp) * 2)) / 2;
        return [$num, $room, $amp, $num, $room, $amp];
    }

    private function breakerRowHeight(array $row, array $widths, int $fontSize = 8): float
    {
        $leftLines = max(1, count($this->wrapLines((string)($row['left_room'] ?? ''), $fontSize, $widths[1] - 8)));
        $rightLines = max(1, count($this->wrapLines((string)($row['right_room'] ?? ''), $fontSize, $widths[4] - 8)));
        return max(16.0, 6.0 + (max($leftLines, $rightLines) * 10.0));
    }

    private function estimateBreakerPanelsHeight(array $section): float
    {
        $widths = $this->breakerColumnWidths();
        $height = 22.0;
        foreach ($section['rows'] as $panel) {
            $height += 20.0 + 18.0;
            foreach ($panel['grid'] ?? [] as $row) {
                $height += $this->breakerRowHeight($row, $widths);
            }
            $height += 14.0;
        }
        return $height;
    }

    private function drawBreakerPanels(array $section, bool $asTabTitle = false): void
    {
        $title = (string)$section['title'];
        $tab = (string)($section['tab'] ?? $title);
        $this->setRunningTitles($tab, $title);

        $titleSize = $asTabTitle ? 15 : 12;
        $this->addText(self::MARGIN_L, $this->y, $titleSize, $title, true);
        $this->y -= $asTabTitle ? 22 : 18;

        foreach ($section['rows'] as $panel) {
            $this->drawBreakerPanel($panel);
        }

        $this->y -= 4;
    }

    private function drawBreakerPanel(array $panel): void
    {
        $name = trim((string)($panel['name'] ?? 'Panel'));
        $size = (int)($panel['size'] ?? 0);
        $grid = $panel['grid'] ?? [];
        $widths = $this->breakerColumnWidths();
        $headerHeight = 16.0;
        $titleHeight = 18.0;

        $needed = $titleHeight + $headerHeight;
        foreach ($grid as $row) {
            $needed += $this->breakerRowHeight($row, $widths);
        }
        $this->ensureBlockFits($needed, $titleHeight + $headerHeight + 48.0);

        $label = $name . '  (' . $size . ' breakers)';
        $this->setRunningTitles($this->activeTabTitle !== '' ? $this->activeTabTitle : 'Permanent Items', $label);
        $this->addText(self::MARGIN_L, $this->y, 11, $label, true);
        $this->y -= $titleHeight;

        $this->drawBreakerHeaderRow($widths, $headerHeight);

        foreach ($grid as $row) {
            $rowHeight = $this->breakerRowHeight($row, $widths);
            if ($this->y - $rowHeight < self::MARGIN_B) {
                $this->newPage();
                $this->addText(self::MARGIN_L, $this->y, 11, $label . ' (continued)', true);
                $this->y -= $titleHeight;
                $this->drawBreakerHeaderRow($widths, $headerHeight);
            }
            $this->drawBreakerDataRow($row, $widths, $rowHeight);
        }

        $this->y -= 12;
    }

    /** @param list<float> $widths */
    private function drawBreakerHeaderRow(array $widths, float $rowHeight): void
    {
        $labels = ['#', 'Room / Location', 'Amps', '#', 'Room / Location', 'Amps'];
        $this->drawBreakerGridRow($labels, $widths, $rowHeight, true);
    }

    /** @param list<float> $widths */
    private function drawBreakerDataRow(array $row, array $widths, float $rowHeight): void
    {
        $values = [
            (string)($row['left_num'] ?? ''),
            (string)($row['left_room'] ?? ''),
            (string)($row['left_amp'] ?? ''),
            (string)($row['right_num'] ?? ''),
            (string)($row['right_room'] ?? ''),
            (string)($row['right_amp'] ?? ''),
        ];
        $this->drawBreakerGridRow($values, $widths, $rowHeight, false);
    }

    /** @param list<string> $values */
    /** @param list<float> $widths */
    private function drawBreakerGridRow(array $values, array $widths, float $rowHeight, bool $header): void
    {
        $x = self::MARGIN_L;
        $yTop = $this->y;
        $yBottom = $yTop - $rowHeight;
        $fontSize = $header ? 8 : 8;

        for ($i = 0; $i < 6; $i++) {
            $w = $widths[$i];
            if ($header) {
                $this->addRect($x, $yBottom, $w, $rowHeight, 0.925, 0.941, 0.945);
            } elseif ($i === 0 || $i === 3) {
                $this->addRect($x, $yBottom, $w, $rowHeight, 0.973, 0.976, 0.980);
            }

            $this->addLine($x, $yTop, $x + $w, $yTop);
            $this->addLine($x, $yBottom, $x + $w, $yBottom);
            $this->addLine($x, $yBottom, $x, $yTop);
            $this->addLine($x + $w, $yBottom, $x + $w, $yTop);

            $text = $values[$i] ?? '';
            $isNum = ($i === 0 || $i === 3 || $i === 2 || $i === 5);
            $lines = $this->wrapLines($text, $fontSize, $w - 8);
            if (count($lines) === 0) {
                $lines = [''];
            }
            $textBlock = count($lines) * 10.0;
            $textY = $yTop - (($rowHeight - $textBlock) / 2) - 8;
            foreach ($lines as $line) {
                $textX = $x + 4;
                if ($isNum) {
                    $textX = $x + max(2, ($w - $this->textWidth($line, $fontSize)) / 2);
                }
                $this->addText($textX, $textY, $fontSize, $line, $header || $i === 0 || $i === 3);
                $textY -= 10;
            }

            $x += $w;
        }

        $this->y -= $rowHeight;
    }

    private function imageGridSize(): float
    {
        return ($this->usableWidth() - (self::IMAGE_COL_GAP * (self::IMAGE_COLS - 1))) / self::IMAGE_COLS;
    }

    private function imageColumnX(int $column): float
    {
        $size = $this->imageGridSize();
        return self::MARGIN_L + ($column * ($size + self::IMAGE_COL_GAP));
    }

    /** @param list<array{path: string, caption: string}> $images */
    private function drawSectionImages(array $images): void
    {
        $size = $this->imageGridSize();
        $rowGap = 14.0;
        $captionLineHeight = 10.0;
        $fontSize = 8;

        $this->y -= 12;
        $this->ensureSpace(24);
        $this->addText(self::MARGIN_L, $this->y, 11, 'Images', true);
        $this->y -= 18;

        $count = count($images);
        for ($index = 0; $index < $count; $index += self::IMAGE_COLS) {
            $left = $images[$index];
            $right = $images[$index + 1] ?? null;

            $leftCaption = (string)($left['caption'] ?? basename((string)$left['path']));
            $rightCaption = $right !== null
                ? (string)($right['caption'] ?? basename((string)$right['path']))
                : '';
            $leftLines = $this->wrapLines($leftCaption, $fontSize, $size - 8);
            $rightLines = $right !== null ? $this->wrapLines($rightCaption, $fontSize, $size - 8) : [];
            $captionLines = max(count($leftLines), count($rightLines), 1);
            $rowHeight = $size + 8 + ($captionLines * $captionLineHeight) + $rowGap;

            if ($this->y - $rowHeight < self::MARGIN_B) {
                $this->newPage();
                $this->drawSectionContinuationBanner();
                $this->addText(self::MARGIN_L, $this->y, 11, 'Images (continued)', true);
                $this->y -= 18;
            }

            $yBottom = $this->y - $size;
            $this->drawImageCell($left, 0, $yBottom, $size);
            if ($right !== null) {
                $this->drawImageCell($right, 1, $yBottom, $size);
            }

            $this->y -= $size + 8;
            for ($line = 0; $line < $captionLines; $line++) {
                if (isset($leftLines[$line])) {
                    $this->addText($this->imageColumnX(0) + 4, $this->y, $fontSize, $leftLines[$line], false);
                }
                if (isset($rightLines[$line])) {
                    $this->addText($this->imageColumnX(1) + 4, $this->y, $fontSize, $rightLines[$line], false);
                }
                $this->y -= $captionLineHeight;
            }

            $this->y -= $rowGap;
        }
    }

    /** @param array{path: string, caption: string} $image */
    private function drawImageCell(array $image, int $column, float $yBottom, float $size): void
    {
        $x = $this->imageColumnX($column);
        $imageName = $this->registerImageFile((string)$image['path']);

        if ($imageName !== null) {
            $this->addImageFrame($x, $yBottom, $size, $imageName);
            return;
        }

        $this->addText(
            $x + 4,
            $yBottom + $size - 16,
            9,
            '[Image unavailable: ' . basename((string)$image['path']) . ']',
            false
        );
    }

    private function registerImageFile(string $path): ?string
    {
        if (isset($this->imagePathRegistry[$path])) {
            return $this->imagePathRegistry[$path];
        }

        $prepared = hds_export_prepare_pdf_image($path);
        if ($prepared === null) {
            return null;
        }

        $imageName = 'Im' . count($this->imageObjects);
        $this->imageObjects[$imageName] = $prepared;
        $this->imagePathRegistry[$path] = $imageName;

        return $imageName;
    }

    private function addImageFrame(float $x, float $yBottom, float $size, string $imageName): void
    {
        $this->pages[count($this->pages) - 1][] = [
            'type' => 'image',
            'name' => $imageName,
            'x' => $x,
            'y' => $yBottom,
            'size' => $size,
        ];

        $yTop = $yBottom + $size;
        $this->addLine($x, $yTop, $x + $size, $yTop);
        $this->addLine($x, $yBottom, $x + $size, $yBottom);
        $this->addLine($x, $yBottom, $x, $yTop);
        $this->addLine($x + $size, $yBottom, $x + $size, $yTop);
    }

    private function drawSectionContinuationBanner(): void
    {
        if ($this->activeSectionTitle === '') {
            return;
        }

        $this->addText(self::MARGIN_L, $this->y, 11, $this->activeSectionTitle . ' (continued)', true);
        $this->y -= 18;
    }

    private function drawTableHeader(int $fontSize): void
    {
        $lineHeight = 11.0;
        $cellPadding = 4.0;
        $wrappedHeaders = [];
        $maxLines = 1;

        foreach ($this->activeTableHeaders as $header) {
            $label = hds_export_friendly_label($header);
            $colWidth = $this->activeColumnWidths[$header];
            $lines = $this->wrapLines($label, $fontSize, $colWidth - 8);
            if (count($lines) === 0) {
                $lines = [''];
            }
            $wrappedHeaders[$header] = $lines;
            $maxLines = max($maxLines, count($lines));
        }

        $rowHeight = ($maxLines * $lineHeight) + $cellPadding;
        $this->ensureSpace($rowHeight + 6);
        $yTop = $this->y;
        $textBaseline = $yTop - $cellPadding - 7;

        foreach ($this->activeTableHeaders as $header) {
            $x = $this->columnX($header);
            $colWidth = $this->activeColumnWidths[$header];
            foreach ($wrappedHeaders[$header] as $lineIndex => $line) {
                $this->addText(
                    $x + 4,
                    $textBaseline - ($lineIndex * $lineHeight),
                    $fontSize,
                    $line,
                    true
                );
            }
        }

        $this->addLine(self::MARGIN_L, $yTop, self::MARGIN_L + $this->usableWidth(), $yTop);
        $this->addLine(self::MARGIN_L, $yTop - $rowHeight, self::MARGIN_L + $this->usableWidth(), $yTop - $rowHeight);
        $this->y -= $rowHeight;
    }

    private function columnX(string $header): float
    {
        $x = self::MARGIN_L;
        foreach ($this->activeTableHeaders as $activeHeader) {
            if ($activeHeader === $header) {
                return $x;
            }
            $x += $this->activeColumnWidths[$activeHeader];
        }

        return self::MARGIN_L;
    }

    private function drawTableRow(array $row, int $fontSize): void
    {
        $lineHeight = 11.0;
        $cellPadding = 4.0;
        $wrappedCells = [];
        $maxLines = 1;

        foreach ($this->activeTableHeaders as $header) {
            $value = hds_export_flatten_value($header, $row[$header] ?? '');
            $colWidth = $this->activeColumnWidths[$header];
            $lines = $this->wrapLines($value, $fontSize, $colWidth - 8);
            $wrappedCells[$header] = $lines;
            $maxLines = max($maxLines, max(1, count($lines)));
        }

        $lineIndex = 0;
        while ($lineIndex < $maxLines) {
            if ($this->y - $lineHeight < self::MARGIN_B) {
                $this->newPage();
                $this->drawSectionContinuationBanner();
                $this->drawTableHeader($fontSize);
            }

            $yTop = $this->y;
            $textBaseline = $yTop - $cellPadding - 7;

            foreach ($this->activeTableHeaders as $header) {
                $lines = $wrappedCells[$header];
                if (!isset($lines[$lineIndex])) {
                    continue;
                }

                $this->addText(
                    $this->columnX($header) + 4,
                    $textBaseline,
                    $fontSize,
                    $lines[$lineIndex],
                    false
                );
            }

            $this->y -= $lineHeight;
            $lineIndex++;
        }

        $this->addLine(self::MARGIN_L, $this->y, self::MARGIN_L + $this->usableWidth(), $this->y);
        $this->y -= 6;
    }

    private function textOp(array $op): string
    {
        $font = !empty($op['bold']) ? '/F2' : '/F1';
        $r = $op['r'] ?? 0;
        $g = $op['g'] ?? 0;
        $b = $op['b'] ?? 0;
        $stream = sprintf("%.3f %.3f %.3f rg\n", $r, $g, $b);
        $stream .= "BT\n";
        $stream .= sprintf("%s %d Tf\n", $font, $op['size']);
        $stream .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $op['x'], $op['y']);
        $stream .= '(' . $this->escape((string)$op['text']) . ") Tj\n";
        $stream .= "ET\n0 g\n";
        return $stream;
    }

    private function lineOp(array $op): string
    {
        return sprintf(
            "0 G\n%.2f %.2f m %.2f %.2f l S\n",
            $op['x1'],
            $op['y1'],
            $op['x2'],
            $op['y2']
        );
    }

    private function rectOp(array $op): string
    {
        return sprintf(
            "%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n0 g\n",
            $op['r'] ?? 0,
            $op['g'] ?? 0,
            $op['b'] ?? 0,
            $op['x'],
            $op['y'],
            $op['w'],
            $op['h']
        );
    }

    private function imageOp(array $op): string
    {
        return sprintf(
            "q\n%.2f 0 0 %.2f %.2f %.2f cm\n/%s Do\nQ\n",
            $op['size'],
            $op['size'],
            $op['x'],
            $op['y'],
            $op['name']
        );
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function assemblePdf(array $contentStreams, array $pageImageUsage): string
    {
        $pageCount = max(1, count($contentStreams));
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $nextId = 3;
        $pageObjIds = [];
        $contentObjIds = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjIds[$i] = $nextId++;
            $contentObjIds[$i] = $nextId++;
        }

        $fontRegularId = $nextId++;
        $fontBoldId = $nextId++;

        $imageObjIds = [];
        foreach (array_keys($this->imageObjects) as $imageName) {
            $imageObjIds[$imageName] = $nextId++;
        }

        $kidRefs = [];
        foreach ($pageObjIds as $pageObjId) {
            $kidRefs[] = $pageObjId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kidRefs) . '] /Count ' . $pageCount . ' >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $resourceParts = [
                '/Font << /F1 ' . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >>',
            ];

            $xObjectParts = [];
            foreach ($pageImageUsage[$i] ?? [] as $imageName) {
                if (isset($imageObjIds[$imageName])) {
                    $xObjectParts[] = '/' . $imageName . ' ' . $imageObjIds[$imageName] . ' 0 R';
                }
            }
            if (count($xObjectParts) > 0) {
                $resourceParts[] = '/XObject << ' . implode(' ', $xObjectParts) . ' >>';
            }

            $objects[$pageObjIds[$i]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '
                . $contentObjIds[$i] . ' 0 R /Resources << ' . implode(' ', $resourceParts) . ' >> >>';
            $objects[$contentObjIds[$i]] = '<< /Length ' . strlen($contentStreams[$i]) . " >>\nstream\n"
                . $contentStreams[$i] . "\nendstream";
        }

        $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($this->imageObjects as $imageName => $image) {
            $objectId = $imageObjIds[$imageName];
            $objects[$objectId] = '<< /Type /XObject /Subtype /Image /Width ' . $image['width']
                . ' /Height ' . $image['height']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
                . strlen($image['jpeg']) . " >>\nstream\n" . $image['jpeg'] . "\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        $maxId = max(array_keys($objects));

        for ($id = 1; $id <= $maxId; $id++) {
            if (!isset($objects[$id])) {
                continue;
            }
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $objects[$id] . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer << /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefPos\n%%EOF";

        return $pdf;
    }
}

function hds_export_send_pdf(array $export, string $house_name): void
{
    $slug = hds_export_sanitize_filename($house_name);
    $filename = 'property-export-' . $slug . '-' . date('Y-m-d') . '.pdf';

    $pdf = new HdsRealtorPdf();
    $pdf->build($export, $house_name);
    $binary = $pdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($binary));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo $binary;
}

function hds_export_zip_entry_name(string $filename): string
{
    $base = basename($filename);
    $base = str_replace(['\\', '/'], '_', $base);
    $base = trim($base);
    if ($base === '' || $base === '.' || $base === '..') {
        $base = 'file';
    }
    return $base;
}

function hds_export_unique_zip_name(string $name, array &$used): string
{
    $name = hds_export_zip_entry_name($name);
    if (!isset($used[$name])) {
        $used[$name] = true;
        return $name;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $stem = pathinfo($name, PATHINFO_FILENAME);
    $i = 2;
    do {
        $candidate = $ext !== '' ? $stem . '_' . $i . '.' . $ext : $stem . '_' . $i;
        $i++;
    } while (isset($used[$candidate]));
    $used[$candidate] = true;
    return $candidate;
}

/** @return array{files: list<array{folder: string, path: string, name: string}>, missing: list<string>} */
function hds_export_collect_handoff_files(array $export): array
{
    $root = hds_export_incur_root();
    $files = [];
    $missing = [];

    $groups = [
        'Designs and Plans' => 'uploads/designs/',
        'User Manuals' => 'uploads/manuals/',
    ];

    foreach ($export['sections'] as $section) {
        $title = (string)($section['title'] ?? '');
        if (!isset($groups[$title])) {
            continue;
        }
        $dir = $groups[$title];
        foreach ($section['rows'] as $row) {
            $filename = hds_export_zip_entry_name((string)($row['filename'] ?? ''));
            if ($filename === 'file') {
                continue;
            }
            $path = $root . '/' . $dir . $filename;
            if (is_file($path) && is_readable($path)) {
                $files[] = [
                    'folder' => $title,
                    'path' => $path,
                    'name' => $filename,
                ];
            } else {
                $missing[] = $title . '/' . $filename;
            }
        }
    }

    return ['files' => $files, 'missing' => $missing];
}

function hds_export_packet_readme(array $export, string $house_name, array $included, array $missing): string
{
    $lines = [
        'Home Documentation System — new owner packet',
        '',
        'Property: ' . $house_name,
        'Exported: ' . (string)($export['exported_at'] ?? date('Y-m-d H:i:s')),
        '',
        'This zip is meant to hand off with the home. It contains:',
        '  - Property-Summary.pdf — printable house documentation',
        '  - Designs and Plans/ — drawings, plans, and related files',
        '  - User Manuals/ — appliance and equipment manuals',
        '',
        'Included files (' . count($included) . '):',
    ];

    if (count($included) === 0) {
        $lines[] = '  (none found on the server)';
    } else {
        foreach ($included as $file) {
            $lines[] = '  - ' . $file['folder'] . '/' . $file['name'];
        }
    }

    if (count($missing) > 0) {
        $lines[] = '';
        $lines[] = 'Listed in the house record but missing from the server:';
        foreach ($missing as $item) {
            $lines[] = '  - ' . $item;
        }
    }

    $lines[] = '';
    $lines[] = 'A spreadsheet workbook can also be exported from Home Documentation System if you need the data in Excel.';
    $lines[] = '';
    return implode("\n", $lines) . "\n";
}

function hds_export_send_packet(array $export, string $house_name): void
{
    if (!class_exists('ZipArchive')) {
        header('Location: house.php?id=' . (int)($export['meta']['id'] ?? 0) . '&tab=export&export_error=zip');
        exit;
    }

    @set_time_limit(180);

    $slug = hds_export_sanitize_filename($house_name);
    $date = date('Y-m-d');
    $packetName = 'property-packet-' . $slug . '-' . $date;
    $downloadName = $packetName . '.zip';

    $pdf = new HdsRealtorPdf();
    $pdf->build($export, $house_name);
    $pdfBinary = $pdf->render();
    $handoff = hds_export_collect_handoff_files($export);

    $tmpdir = rtrim(sys_get_temp_dir(), '/') . '/hds-packet-' . bin2hex(random_bytes(8));
    if (!@mkdir($tmpdir, 0700, true) && !is_dir($tmpdir)) {
        header('Location: house.php?id=' . (int)($export['meta']['id'] ?? 0) . '&tab=export&export_error=zip');
        exit;
    }

    $pdfPath = $tmpdir . '/Property-Summary.pdf';
    $zipPath = $tmpdir . '/packet.zip';
    $ok = @file_put_contents($pdfPath, $pdfBinary) !== false;

    $zip = new ZipArchive();
    if (!$ok || $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        hds_export_rmdir_recursive($tmpdir);
        header('Location: house.php?id=' . (int)($export['meta']['id'] ?? 0) . '&tab=export&export_error=zip');
        exit;
    }

    $zip->addFile($pdfPath, $packetName . '/Property-Summary.pdf');
    $zip->addFromString(
        $packetName . '/README.txt',
        hds_export_packet_readme($export, $house_name, $handoff['files'], $handoff['missing'])
    );

    $usedNames = [];
    foreach ($handoff['files'] as $file) {
        $folder = $file['folder'];
        if (!isset($usedNames[$folder])) {
            $usedNames[$folder] = [];
        }
        $entry = hds_export_unique_zip_name($file['name'], $usedNames[$folder]);
        $zip->addFile($file['path'], $packetName . '/' . $folder . '/' . $entry);
    }

    $zip->close();

    if (!is_file($zipPath)) {
        hds_export_rmdir_recursive($tmpdir);
        header('Location: house.php?id=' . (int)($export['meta']['id'] ?? 0) . '&tab=export&export_error=zip');
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string)filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($zipPath);
    hds_export_rmdir_recursive($tmpdir);
}

// Backward-compatible alias used by export-house.php
function hds_export_send_csv(array $export, string $house_name): void
{
    hds_export_send_spreadsheet($export, $house_name);
}