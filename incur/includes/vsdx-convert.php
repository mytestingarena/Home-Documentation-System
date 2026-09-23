<?php
/**
 * Visio → draw.io conversion helper for Designs uploads.
 *
 * Calls the Orin LAN convert service (Playwright + draw.io), which returns
 * an mxfile. On failure we log and leave the Visio archive in place — never
 * fail the whole upload (same spirit as mutool XPS→PDF).
 */

if (!defined('HDS_VSDX_CONVERT_URL')) {
    // Orin convert service (see incur/tools/vsdx-to-drawio-server.py)
    define('HDS_VSDX_CONVERT_URL', 'http://192.168.1.186:8765/convert');
}

if (!defined('HDS_VSDX_CONVERT_TIMEOUT')) {
    define('HDS_VSDX_CONVERT_TIMEOUT', 150);
}

/**
 * @return string Sibling .drawio filename for a Visio filename.
 */
function hds_designs_drawio_sibling(string $visio_filename): string
{
    return (string)preg_replace('/\.(vsdx|vsd|vsdm)$/i', '.drawio', $visio_filename);
}

/**
 * @return bool True if extension is a Visio binary/xml package we convert.
 */
function hds_designs_is_visio_ext(string $ext): bool
{
    $ext = strtolower($ext);
    return in_array($ext, ['vsdx', 'vsd', 'vsdm'], true);
}

/**
 * Convert an already-stored Visio design to a sibling .drawio file + DB row.
 *
 * @param mysqli $conn
 * @param int    $house_id
 * @param string $visio_filename  Basename already in uploads/designs + designs table
 * @param string $target_dir      Trailing-slash directory path (relative or abs)
 * @return array{ok:bool,drawio?:string,error?:string,pages?:int}
 */
function hds_convert_visio_to_drawio(mysqli $conn, int $house_id, string $visio_filename, string $target_dir): array
{
    $visio_filename = basename($visio_filename);
    if (!hds_designs_is_visio_ext(pathinfo($visio_filename, PATHINFO_EXTENSION))) {
        return ['ok' => false, 'error' => 'not visio'];
    }

    $drawio_name = hds_designs_drawio_sibling($visio_filename);
    if ($drawio_name === $visio_filename) {
        return ['ok' => false, 'error' => 'bad sibling name'];
    }

    $drawio_path = $target_dir . $drawio_name;
    if (is_file($drawio_path)) {
        // Already converted; ensure DB row exists.
        $stmt = $conn->prepare('SELECT id FROM designs WHERE house_id = ? AND filename = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('is', $house_id, $drawio_name);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$exists) {
                $ins = $conn->prepare('INSERT INTO designs (house_id, filename, upload_date) VALUES (?, ?, NOW())');
                if ($ins) {
                    $ins->bind_param('is', $house_id, $drawio_name);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
        return ['ok' => true, 'drawio' => $drawio_name, 'pages' => null];
    }

    $url = HDS_VSDX_CONVERT_URL;
    $payload = json_encode(['filename' => $visio_filename], JSON_UNESCAPED_SLASHES);
    $pages = null;
    $xml = null;
    $err = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/xml, application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => (int)HDS_VSDX_CONVERT_TIMEOUT,
        ]);
        $xml = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        $hdr_pages = null;
        // curl does not expose custom headers easily without header callback
        curl_close($ch);
        if ($xml === false || $code < 200 || $code >= 300) {
            $err = "convert HTTP $code" . ($cerr ? " ($cerr)" : '');
            if (is_string($xml) && $xml !== '' && $xml[0] === '{') {
                $j = json_decode($xml, true);
                if (!empty($j['error'])) {
                    $err .= ': ' . $j['error'];
                }
            }
            $xml = null;
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
                'content' => $payload,
                'timeout' => (int)HDS_VSDX_CONVERT_TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);
        $xml = @file_get_contents($url, false, $ctx);
        if ($xml === false || strpos($xml, '<mxfile') === false) {
            $err = 'convert request failed';
            $xml = null;
        }
    }

    if ($xml === null || strpos($xml, '<mxfile') === false) {
        $msg = $err ?: 'no mxfile returned';
        error_log("HDS Visio→draw.io failed for $visio_filename: $msg");
        return ['ok' => false, 'error' => $msg];
    }

    if (@file_put_contents($drawio_path, $xml) === false) {
        error_log("HDS Visio→draw.io could not write $drawio_path");
        return ['ok' => false, 'error' => 'write failed'];
    }
    @chmod($drawio_path, 0664);

    if (preg_match_all('/<diagram\b/i', $xml, $m)) {
        $pages = count($m[0]);
    }

    $ins = $conn->prepare('INSERT INTO designs (house_id, filename, upload_date) VALUES (?, ?, NOW())');
    if ($ins) {
        $ins->bind_param('is', $house_id, $drawio_name);
        $ins->execute();
        $ins->close();
    } else {
        // Fallback like XPS path
        $safe = $conn->real_escape_string($drawio_name);
        $conn->query("INSERT INTO designs (house_id, filename, upload_date) VALUES ($house_id, '$safe', NOW())");
    }

    return ['ok' => true, 'drawio' => $drawio_name, 'pages' => $pages];
}
