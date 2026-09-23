<?php
// export-house.php — Download house data as spreadsheet, PDF, or new-owner zip packet.

include 'config.php';
require_once __DIR__ . '/includes/house-export.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['export_house'])) {
    header('Location: index.php');
    exit;
}

$house_id = intval($_POST['house_id'] ?? 0);
$format = strtolower(trim($_POST['export_format'] ?? ''));
$confirm = strtolower(trim($_POST['export_confirm'] ?? ''));

function hds_export_fail(int $house_id, string $code): void
{
    if ($house_id > 0) {
        header('Location: house.php?id=' . $house_id . '&tab=export&export_error=' . urlencode($code));
    } else {
        header('Location: index.php');
    }
    exit;
}

if ($confirm !== 'confirm') {
    hds_export_fail($house_id, 'confirm');
}

if ($house_id <= 0) {
    hds_export_fail(0, 'house');
}

if (!in_array($format, ['spreadsheet', 'pdf', 'packet'], true)) {
    hds_export_fail($house_id, 'format');
}

$export = hds_export_collect_house_data($conn, $house_id);
if ($export === null) {
    hds_export_fail($house_id, 'house');
}

$house_name = trim((string)($export['meta']['name'] ?? 'House'));

if ($format === 'spreadsheet') {
    hds_export_send_spreadsheet($export, $house_name);
    exit;
}

if ($format === 'packet') {
    hds_export_send_packet($export, $house_name);
    exit;
}

hds_export_send_pdf($export, $house_name);
