<?php
/**
 * Serve a design file for draw.io / browser viewers.
 * Adds CORS so the LAN draw.io instance (different origin/port) can fetch
 * .vsdx / .drawio / .xml over XHR when embedded in a lightbox iframe.
 */
require_once __DIR__ . '/config.php';

$filename = isset($_GET['f']) ? (string)$_GET['f'] : '';
$filename = basename(str_replace(["\0", '\\'], '', $filename));

if ($filename === '' || $filename === '.' || $filename === '..') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing file';
    exit;
}

// Only serve files that exist in the designs table (prevents arbitrary path reads).
$stmt = $conn->prepare('SELECT id, filename FROM designs WHERE filename = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Database error';
    exit;
}
$stmt->bind_param('s', $filename);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

$path = __DIR__ . '/uploads/designs/' . $filename;
$real_base = realpath(__DIR__ . '/uploads/designs');
$real_path = realpath($path);

if ($real_base === false || $real_path === false || strpos($real_path, $real_base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found';
    exit;
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime_map = [
    'vsdx'   => 'application/vnd.ms-visio.drawing',
    'vsd'    => 'application/vnd.visio',
    'vsdm'   => 'application/vnd.ms-visio.drawing.macroEnabled.12',
    'drawio' => 'application/vnd.jgraph.mxfile',
    'xml'    => 'application/xml',
    'pdf'    => 'application/pdf',
    'svg'    => 'image/svg+xml',
    'png'    => 'image/png',
    'jpg'    => 'image/jpeg',
    'jpeg'   => 'image/jpeg',
];
$content_type = $mime_map[$ext] ?? 'application/octet-stream';

// draw.io runs on :8080 (and :80); HDS on another host — allow LAN cross-origin fetch.
$allowed_origins = [
    'http://192.168.1.10:8080',
    'http://192.168.1.10',
    'http://192.168.1.10:80',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    // Files are already LAN-public under uploads/; wildcard keeps lightbox working
    // for odd Origin values (e.g. null) without exposing credentials.
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range');
header('Access-Control-Expose-Headers: Content-Length, Content-Type, Accept-Ranges, Content-Range');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($real_path));
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
// Content-Disposition: inline so viewers can load; download still uses uploads/ path.
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

readfile($real_path);
exit;
