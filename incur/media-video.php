<?php
// Walkthrough video upload helpers

function hds_walkthrough_video_extensions(): array {
    return ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg'];
}

function hds_walkthrough_video_accept(): string {
    return 'video/*,.mp4,.mov,.avi,.mkv,.webm,.wmv,.flv,.m4v,.mpeg,.mpg';
}

function hds_media_upload_dir(): string {
    return dirname(__DIR__) . '/uploads/photos/';
}

function hds_media_disk_path(string $filename): string {
    return hds_media_upload_dir() . basename($filename);
}

function hds_media_url_path(string $filename): string {
    return 'uploads/photos/' . basename($filename);
}

function hds_video_mime_type(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',
        'wmv'  => 'video/x-ms-wmv',
        'flv'  => 'video/x-flv',
        'mpeg' => 'video/mpeg',
        'mpg'  => 'video/mpeg',
    ];

    return $map[$ext] ?? 'video/mp4';
}

function hds_ffmpeg_path(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $candidates = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/bin/ffmpeg'];
    foreach ($candidates as $candidate) {
        if (is_executable($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    $paths = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
    foreach (explode(':', $paths) as $dir) {
        $candidate = rtrim($dir, '/') . '/ffmpeg';
        if (is_executable($candidate)) {
            $cached = $candidate;
            return $cached;
        }
    }

    exec('command -v ffmpeg 2>/dev/null', $out, $return_var);
    if ($return_var === 0 && !empty($out[0]) && is_executable(trim($out[0]))) {
        $cached = trim($out[0]);
        return $cached;
    }

    $cached = '';
    return $cached;
}

function hds_php_can_exec(): bool {
    if (!function_exists('exec')) {
        return false;
    }

    $disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
    return !in_array('exec', $disabled, true);
}

function hds_ffmpeg_available(): bool {
    return hds_php_can_exec() && hds_ffmpeg_path() !== '';
}

function hds_ffmpeg_error_message(array $output, int $return_code, string $command = ''): string {
    if (!hds_ffmpeg_available()) {
        return 'ffmpeg is not installed or not executable by the web server.';
    }

    $lines = array_values(array_filter(array_map('trim', $output), static function (string $line): bool {
        return $line !== '';
    }));

    $message = '';
    if (!empty($lines)) {
        $tail = array_slice($lines, -10);
        $message = implode("\n", $tail);
        if (strlen($message) > 1500) {
            $message = substr($message, -1500);
        }
    } else {
        $message = 'ffmpeg exited with code ' . $return_code . '.';
    }

    if ($command !== '') {
        $message .= "\n\nCommand: " . $command;
    }

    return $message;
}

function hds_run_ffmpeg(array $args, ?string &$error_message = null): bool {
    $error_message = '';
    $ffmpeg = hds_ffmpeg_path();
    if ($ffmpeg === '') {
        $error_message = 'ffmpeg is not installed or not executable by the web server.';
        return false;
    }

    $command = escapeshellarg($ffmpeg);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
    $command .= ' 2>&1';

    $output = [];
    $return_var = 1;
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        return true;
    }

    $error_message = hds_ffmpeg_error_message($output, $return_var, $command);
    return false;
}

/**
 * Transcode to an H.264/AAC MP4 tuned for browser streaming (faststart moov atom).
 */
function hds_compress_walkthrough_video(string $source_path, string $output_path, ?string &$error_message = null): bool {
    $error_message = '';

    if (!is_file($source_path)) {
        $error_message = 'Uploaded file is missing on disk: ' . $source_path;
        return false;
    }

    if (!hds_php_can_exec()) {
        $error_message = 'PHP exec() is disabled on this server, so ffmpeg cannot run.';
        return false;
    }

    if (!hds_ffmpeg_available()) {
        $error_message = 'ffmpeg is not installed or not executable by the web server.';
        return false;
    }

    $output_dir = dirname($output_path);
    if (!is_dir($output_dir) && !mkdir($output_dir, 0775, true) && !is_dir($output_dir)) {
        $error_message = 'Cannot create upload directory: ' . $output_dir;
        return false;
    }

    if (!is_writable($output_dir)) {
        $error_message = 'Upload directory is not writable by the web server: ' . $output_dir;
        return false;
    }

    @unlink($output_path);

    $attempts = [
        [
            '-y', '-i', $source_path,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c:v', 'libx264', '-crf', '23', '-preset', 'medium',
            '-vf', 'scale=min(1920,iw):-2',
            '-movflags', '+faststart',
            '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
            $output_path,
        ],
        [
            '-y', '-i', $source_path,
            '-map', '0:v:0',
            '-c:v', 'libx264', '-crf', '23', '-preset', 'fast',
            '-vf', 'scale=min(1920,iw):-2',
            '-movflags', '+faststart',
            '-an',
            $output_path,
        ],
        [
            '-y', '-i', $source_path,
            '-c:v', 'libx264', '-crf', '23', '-preset', 'fast',
            '-movflags', '+faststart',
            '-c:a', 'aac', '-b:a', '128k',
            $output_path,
        ],
    ];

    $errors = [];
    foreach ($attempts as $args) {
        $attempt_error = '';
        if (hds_run_ffmpeg($args, $attempt_error) && is_file($output_path) && filesize($output_path) > 0) {
            return true;
        }
        $errors[] = $attempt_error;
        @unlink($output_path);
    }

    $error_message = implode("\n\n--- Next attempt ---\n\n", array_filter($errors));
    return false;
}

function hds_upload_error_message(int $code): string {
    $map = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];

    return $map[$code] ?? 'Unknown upload error (code ' . $code . ').';
}

function hds_media_guess_meta(string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, hds_walkthrough_video_extensions(), true)) {
        return ['section' => 'Walkthrough', 'is_ir' => 0];
    }

    if (preg_match('/(^|[_-])ir[_-]/i', $filename)) {
        return ['section' => 'Interior', 'is_ir' => 1];
    }

    return ['section' => 'Interior', 'is_ir' => 0];
}

function hds_media_filename_exists(mysqli $conn, int $house_id, string $filename): bool {
    $stmt = $conn->prepare('SELECT id FROM photos WHERE house_id = ? AND filename = ? LIMIT 1');
    $stmt->bind_param('is', $house_id, $filename);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function hds_media_register_file(mysqli $conn, int $house_id, string $filename, string $section, int $is_ir, ?string &$error_message = null): bool {
    $error_message = '';

    $disk_path = hds_media_disk_path($filename);
    if (!is_file($disk_path)) {
        $error_message = 'File not found on disk: ' . $disk_path;
        return false;
    }

    if (hds_media_filename_exists($conn, $house_id, $filename)) {
        $error_message = 'This filename is already registered for this house.';
        return false;
    }

    $stmt = $conn->prepare('INSERT INTO photos (house_id, section, filename, is_ir, upload_date) VALUES (?, ?, ?, ?, NOW())');
    if (!$stmt) {
        $error_message = trim($conn->error) !== '' ? $conn->error : 'Could not prepare database insert.';
        if ($section === 'Walkthrough') {
            $error_message .= ' The photos.section column may need the Walkthrough value — run migrations.sql on the server.';
        }
        return false;
    }

    $stmt->bind_param('issi', $house_id, $section, $filename, $is_ir);
    if (!$stmt->execute()) {
        $error_message = trim($stmt->error) !== '' ? $stmt->error : (trim($conn->error) !== '' ? $conn->error : 'Insert failed for unknown reason.');
        if ($section === 'Walkthrough' && stripos($error_message, 'truncat') !== false) {
            $error_message .= ' Run: ALTER TABLE photos MODIFY COLUMN section ENUM(\'Interior\',\'Exterior\',\'Walkthrough\') DEFAULT NULL;';
        }
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

function hds_media_sync_orphans(mysqli $conn, int $house_id): array {
    $registered = 0;
    $skipped = 0;
    $dir = hds_media_upload_dir();

    if (!is_dir($dir)) {
        return ['registered' => 0, 'skipped' => 0];
    }

    $files = scandir($dir);
    if ($files === false) {
        return ['registered' => 0, 'skipped' => 0];
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . $file;
        if (!is_file($path)) {
            continue;
        }

        if (hds_media_filename_exists($conn, $house_id, $file)) {
            $skipped++;
            continue;
        }

        $meta = hds_media_guess_meta($file);
        if (hds_media_register_file($conn, $house_id, $file, $meta['section'], $meta['is_ir'])) {
            $registered++;
        }
    }

    return ['registered' => $registered, 'skipped' => $skipped];
}