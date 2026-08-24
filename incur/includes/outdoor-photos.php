<?php
// includes/outdoor-photos.php — EXIF dating, thumbs, and helpers for Outdoor Photos

function hds_outdoor_photos_albums(): array
{
    return [
        'trail_camera' => [
            'label' => 'Trail Camera',
            'icon' => 'fa-camera',
            'section' => 'outdoor-photos-trail',
            'blurb' => 'Game cam stills, dated from the picture metadata.',
        ],
        'fishing' => [
            'label' => 'Fishing',
            'icon' => 'fa-fish',
            'section' => 'outdoor-photos-fishing',
            'blurb' => 'Catch photos and time on the water, dated from the picture metadata.',
        ],
    ];
}

function hds_outdoor_photos_upload_dir(): string
{
    return __DIR__ . '/../uploads/outdoor-photos/';
}

function hds_outdoor_photos_thumb_dir(): string
{
    return hds_outdoor_photos_upload_dir() . 'thumbs/';
}

function hds_outdoor_photos_url(string $filename): string
{
    return 'uploads/outdoor-photos/' . rawurlencode($filename);
}

function hds_outdoor_photos_thumb_url(string $thumb_filename): string
{
    return 'uploads/outdoor-photos/thumbs/' . rawurlencode($thumb_filename);
}

function hds_outdoor_photos_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_outdoor_photos_schema_sql(): string
{
    return "CREATE TABLE IF NOT EXISTS outdoor_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        house_id INT NOT NULL,
        album ENUM('trail_camera', 'fishing') NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL DEFAULT '',
        thumbnail VARCHAR(255) DEFAULT NULL,
        taken_at DATETIME DEFAULT NULL,
        date_source ENUM('exif', 'filename', 'upload', 'manual') NOT NULL DEFAULT 'upload',
        camera_make VARCHAR(100) DEFAULT NULL,
        camera_model VARCHAR(100) DEFAULT NULL,
        gps_lat DECIMAL(10,7) DEFAULT NULL,
        gps_lng DECIMAL(10,7) DEFAULT NULL,
        caption VARCHAR(500) DEFAULT NULL,
        width INT DEFAULT NULL,
        height INT DEFAULT NULL,
        file_size INT DEFAULT NULL,
        upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
        INDEX idx_outdoor_photos_album (house_id, album, taken_at),
        INDEX idx_outdoor_photos_taken (house_id, taken_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
}

function hds_outdoor_photos_ensure_table(mysqli $conn): bool
{
    static $ready = null;
    if ($ready === true) {
        return true;
    }
    if ($conn->query(hds_outdoor_photos_schema_sql())) {
        $ready = true;
        return true;
    }
    $ready = false;
    return false;
}

function hds_outdoor_photos_ensure_dirs(): ?string
{
    $dirs = [hds_outdoor_photos_upload_dir(), hds_outdoor_photos_thumb_dir()];
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return 'Could not create the outdoor photo folder on the server.';
        }
        if (!is_writable($dir)) {
            return 'The outdoor photo folder is not writable. Ask your admin to run: chown -R www-data:www-data uploads/outdoor-photos && chmod -R 775 uploads/outdoor-photos';
        }
    }
    return null;
}

function hds_outdoor_clip(string $text, int $max): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max) : $text;
}

function hds_outdoor_photos_sanitize_basename(string $raw): string
{
    $basename = basename($raw);
    $basename = preg_replace('/[^a-zA-Z0-9._\- ()]/', '_', $basename);
    $basename = trim($basename, '. ');
    if (str_contains($basename, '.')) {
        $basename = pathinfo($basename, PATHINFO_FILENAME);
        $basename = trim($basename, '. ');
    }
    return $basename;
}

function hds_outdoor_exif_rational($value): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return 0.0;
    }
    if (str_contains($text, '/')) {
        [$num, $den] = array_pad(explode('/', $text, 2), 2, '1');
        $den = (float) $den;
        return $den == 0.0 ? 0.0 : ((float) $num / $den);
    }
    return (float) $text;
}

function hds_outdoor_exif_gps_to_decimal($coord, $ref): ?float
{
    if (!is_array($coord) || count($coord) < 3) {
        return null;
    }
    $dec = hds_outdoor_exif_rational($coord[0])
        + (hds_outdoor_exif_rational($coord[1]) / 60)
        + (hds_outdoor_exif_rational($coord[2]) / 3600);
    $ref = strtoupper(trim((string) $ref));
    if ($ref === 'S' || $ref === 'W') {
        $dec *= -1;
    }
    if (!is_finite($dec) || abs($dec) > 180) {
        return null;
    }
    return round($dec, 7);
}

function hds_outdoor_parse_exif_datetime(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || str_starts_with($raw, '0000')) {
        return null;
    }
    $raw = str_replace(['T', '/', '.'], [' ', ':', ':'], $raw);
    if (!preg_match('/^(20\d{2}|19\d{2}):(\d{2}):(\d{2})[ ](\d{2}):(\d{2}):(\d{2})/', $raw, $m)) {
        return null;
    }
    $year = (int) $m[1];
    $month = (int) $m[2];
    $day = (int) $m[3];
    $hour = (int) $m[4];
    $minute = (int) $m[5];
    $second = (int) $m[6];
    if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
}

function hds_outdoor_date_from_filename(string $name): ?string
{
    if (!preg_match('/(20\d{2})[-_.]?(\d{2})[-_.]?(\d{2})(?:[-_.]?(\d{2})[-_.]?(\d{2})[-_.]?(\d{2}))?/', $name, $m)) {
        return null;
    }
    $year = (int) $m[1];
    $month = (int) $m[2];
    $day = (int) $m[3];
    $hour = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0;
    $minute = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
    $second = isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0;
    if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
}

function hds_outdoor_read_photo_meta(string $path, string $original_name): array
{
    $meta = [
        'taken_at' => null,
        'date_source' => 'upload',
        'camera_make' => null,
        'camera_model' => null,
        'gps_lat' => null,
        'gps_lng' => null,
        'orientation' => 1,
        'width' => null,
        'height' => null,
    ];

    $info = @getimagesize($path);
    if (is_array($info)) {
        $meta['width'] = (int) $info[0];
        $meta['height'] = (int) $info[1];
    }

    $exif = null;
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path, 'ANY_TAG', true);
    }

    if (is_array($exif)) {
        $date_candidates = [
            $exif['EXIF']['DateTimeOriginal'] ?? null,
            $exif['EXIF']['DateTimeDigitized'] ?? null,
            $exif['IFD0']['DateTime'] ?? null,
            $exif['EXIF']['DateTime'] ?? null,
        ];
        foreach ($date_candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $parsed = hds_outdoor_parse_exif_datetime($candidate);
            if ($parsed !== null) {
                $meta['taken_at'] = $parsed;
                $meta['date_source'] = 'exif';
                break;
            }
        }

        $make = trim((string) ($exif['IFD0']['Make'] ?? $exif['EXIF']['Make'] ?? ''));
        $model = trim((string) ($exif['IFD0']['Model'] ?? $exif['EXIF']['Model'] ?? ''));
        $meta['camera_make'] = $make !== '' ? hds_outdoor_clip($make, 100) : null;
        $meta['camera_model'] = $model !== '' ? hds_outdoor_clip($model, 100) : null;

        $orientation = (int) ($exif['IFD0']['Orientation'] ?? $exif['EXIF']['Orientation'] ?? 1);
        if ($orientation >= 1 && $orientation <= 8) {
            $meta['orientation'] = $orientation;
        }

        $lat = $exif['GPS']['GPSLatitude'] ?? null;
        $lat_ref = $exif['GPS']['GPSLatitudeRef'] ?? null;
        $lng = $exif['GPS']['GPSLongitude'] ?? null;
        $lng_ref = $exif['GPS']['GPSLongitudeRef'] ?? null;
        $meta['gps_lat'] = hds_outdoor_exif_gps_to_decimal($lat, $lat_ref);
        $meta['gps_lng'] = hds_outdoor_exif_gps_to_decimal($lng, $lng_ref);
    }

    if ($meta['taken_at'] === null) {
        $from_name = hds_outdoor_date_from_filename($original_name);
        if ($from_name !== null) {
            $meta['taken_at'] = $from_name;
            $meta['date_source'] = 'filename';
        }
    }

    if ($meta['taken_at'] === null) {
        $meta['taken_at'] = date('Y-m-d H:i:s');
        $meta['date_source'] = 'upload';
    }

    return $meta;
}

function hds_outdoor_orient_image($image, int $orientation)
{
    switch ($orientation) {
        case 2:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 4:
            imageflip($image, IMG_FLIP_VERTICAL);
            break;
        case 5:
            imageflip($image, IMG_FLIP_VERTICAL);
            $image = imagerotate($image, -90, 0);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 7:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, -90, 0);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }
    return $image;
}

function hds_outdoor_make_thumb(string $src_path, string $dest_path, string $ext, int $orientation = 1, int $max_edge = 480): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $source = match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($src_path),
        'png' => @imagecreatefrompng($src_path),
        'gif' => @imagecreatefromgif($src_path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src_path) : false,
        default => false,
    };
    if ($source === false) {
        return false;
    }

    if (function_exists('imagepalettetotruecolor') && !imageistruecolor($source)) {
        @imagepalettetotruecolor($source);
    }

    if ($orientation > 1) {
        $rotated = hds_outdoor_orient_image($source, $orientation);
        if ($rotated !== $source && $rotated !== false) {
            imagedestroy($source);
            $source = $rotated;
        }
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width < 1 || $height < 1) {
        imagedestroy($source);
        return false;
    }

    $scale = min(1, $max_edge / max($width, $height));
    $new_w = max(1, (int) round($width * $scale));
    $new_h = max(1, (int) round($height * $scale));
    $thumb = imagecreatetruecolor($new_w, $new_h);
    if ($thumb === false) {
        imagedestroy($source);
        return false;
    }

    imagealphablending($thumb, true);
    $bg = imagecolorallocate($thumb, 18, 24, 22);
    imagefilledrectangle($thumb, 0, 0, $new_w, $new_h, $bg);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
    $ok = imagejpeg($thumb, $dest_path, 82);
    imagedestroy($thumb);
    imagedestroy($source);
    return $ok;
}

function hds_outdoor_photos_unlink_files(?string $filename, ?string $thumbnail): void
{
    $dir = hds_outdoor_photos_upload_dir();
    if ($filename) {
        $path = $dir . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if ($thumbnail) {
        $thumb_path = hds_outdoor_photos_thumb_dir() . $thumbnail;
        if (is_file($thumb_path)) {
            @unlink($thumb_path);
        }
    }
}

function hds_outdoor_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is larger than the server upload limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension.',
        default => 'Upload failed (error ' . $code . ').',
    };
}

function hds_outdoor_photos_handle_upload(mysqli $conn, int $house_id): void
{
    $albums = hds_outdoor_photos_albums();
    $album = (string) ($_POST['outdoor_album'] ?? 'trail_camera');
    if (!isset($albums[$album])) {
        $album = 'trail_camera';
    }

    if (!hds_outdoor_photos_ensure_table($conn)) {
        $_SESSION['outdoor_photos_error'] = 'Could not create the outdoor photos table: ' . $conn->error;
        return;
    }

    $dir_error = hds_outdoor_photos_ensure_dirs();
    if ($dir_error !== null) {
        $_SESSION['outdoor_photos_error'] = $dir_error;
        return;
    }

    if (empty($_FILES['outdoor_photos']['name']) || !is_array($_FILES['outdoor_photos']['name'])) {
        $_SESSION['outdoor_photos_error'] = 'No files were received. The upload may have exceeded the server size limit.';
        return;
    }

    @set_time_limit(300);
    $max = 80;
    $count = 0;
    $allowed = hds_outdoor_photos_allowed_extensions();
    $errors = [];
    $skipped_heic = false;
    $target_dir = hds_outdoor_photos_upload_dir();
    $thumb_dir = hds_outdoor_photos_thumb_dir();

    $stmt = $conn->prepare(
        'INSERT INTO outdoor_photos
            (house_id, album, filename, original_name, thumbnail, taken_at, date_source,
             camera_make, camera_model, gps_lat, gps_lng, width, height, file_size)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        $_SESSION['outdoor_photos_error'] = 'Database prepare failed: ' . $conn->error;
        return;
    }

    foreach ($_FILES['outdoor_photos']['tmp_name'] as $k => $tmp) {
        if ($count >= $max) {
            $errors[] = 'Stopped after ' . $max . ' files. Upload the rest in another batch.';
            break;
        }

        $original_name = basename((string) ($_FILES['outdoor_photos']['name'][$k] ?? ''));
        $upload_err = (int) ($_FILES['outdoor_photos']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
        if ($upload_err !== UPLOAD_ERR_OK) {
            if ($original_name !== '') {
                $errors[] = $original_name . ': ' . hds_outdoor_upload_error_message($upload_err);
            }
            continue;
        }

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (in_array($ext, ['heic', 'heif', 'tif', 'tiff', 'raw', 'dng'], true)) {
            $skipped_heic = true;
            continue;
        }
        if (!in_array($ext, $allowed, true)) {
            $errors[] = $original_name . ': File type not allowed. Use JPG, PNG, GIF, or WebP.';
            continue;
        }

        $safe_base = hds_outdoor_photos_sanitize_basename($original_name);
        if ($safe_base === '') {
            $safe_base = 'photo';
        }
        $final_name = date('Ymd-His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe_base . '.' . $ext;
        $target = $target_dir . $final_name;

        if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $target)) {
            $errors[] = $original_name . ': Could not save the uploaded file.';
            continue;
        }

        $meta = hds_outdoor_read_photo_meta($target, $original_name);
        $thumb_name = pathinfo($final_name, PATHINFO_FILENAME) . '.jpg';
        $thumb_path = $thumb_dir . $thumb_name;
        $thumbnail = null;
        if (hds_outdoor_make_thumb($target, $thumb_path, $ext, (int) $meta['orientation'])) {
            $thumbnail = $thumb_name;
        }

        $file_size = (int) (@filesize($target) ?: 0);
        $gps_lat = $meta['gps_lat'] !== null ? (string) $meta['gps_lat'] : null;
        $gps_lng = $meta['gps_lng'] !== null ? (string) $meta['gps_lng'] : null;
        $width = $meta['width'];
        $height = $meta['height'];
        $taken_at = $meta['taken_at'];
        $date_source = $meta['date_source'];
        $camera_make = $meta['camera_make'];
        $camera_model = $meta['camera_model'];

        $stmt->bind_param(
            'issssssssssiii',
            $house_id,
            $album,
            $final_name,
            $original_name,
            $thumbnail,
            $taken_at,
            $date_source,
            $camera_make,
            $camera_model,
            $gps_lat,
            $gps_lng,
            $width,
            $height,
            $file_size
        );

        if (!$stmt->execute()) {
            $errors[] = $original_name . ': Saved on disk but database insert failed — ' . $stmt->error;
            hds_outdoor_photos_unlink_files($final_name, $thumbnail);
            continue;
        }

        $count++;
    }
    $stmt->close();

    $messages = [];
    if ($count > 0) {
        $album_label = $albums[$album]['label'];
        $messages[] = 'Uploaded ' . $count . ' photo' . ($count === 1 ? '' : 's') . ' to ' . $album_label . '. Dates come from each picture’s metadata when it is present.';
    }
    if ($skipped_heic) {
        $messages[] = 'HEIC/RAW files were skipped. Export them as JPG first.';
    }
    if ($errors) {
        $messages[] = implode("\n", $errors);
    }

    if ($count === 0) {
        $_SESSION['outdoor_photos_error'] = $messages ? implode("\n\n", $messages) : 'No files were uploaded.';
    } elseif ($errors || $skipped_heic) {
        $_SESSION['outdoor_photos_warning'] = implode("\n\n", $messages);
    } else {
        $_SESSION['outdoor_photos_success'] = implode("\n\n", $messages);
    }
}

function hds_outdoor_photos_delete(mysqli $conn, int $house_id, int $photo_id): void
{
    if ($photo_id <= 0 || !hds_outdoor_photos_ensure_table($conn)) {
        return;
    }
    $stmt = $conn->prepare('SELECT filename, thumbnail FROM outdoor_photos WHERE id = ? AND house_id = ?');
    $stmt->bind_param('ii', $photo_id, $house_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return;
    }
    hds_outdoor_photos_unlink_files($row['filename'] ?? null, $row['thumbnail'] ?? null);
    $del = $conn->prepare('DELETE FROM outdoor_photos WHERE id = ? AND house_id = ?');
    $del->bind_param('ii', $photo_id, $house_id);
    $del->execute();
    $del->close();
}

function hds_outdoor_photos_update_meta(mysqli $conn, int $house_id, int $photo_id, string $caption, string $taken_at_raw): void
{
    if ($photo_id <= 0 || !hds_outdoor_photos_ensure_table($conn)) {
        return;
    }

    $caption = hds_outdoor_clip(trim($caption), 500);
    $caption_arg = $caption === '' ? null : $caption;

    $taken_at = null;
    $date_source = null;
    $raw = trim($taken_at_raw);
    if ($raw !== '') {
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $raw .= ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $raw)) {
            $raw .= ':00';
        }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
        if ($dt instanceof DateTime) {
            $taken_at = $dt->format('Y-m-d H:i:s');
            $date_source = 'manual';
        }
    }

    if ($taken_at !== null) {
        $stmt = $conn->prepare(
            'UPDATE outdoor_photos
             SET caption = ?, taken_at = ?, date_source = ?
             WHERE id = ? AND house_id = ?'
        );
        $stmt->bind_param('sssii', $caption_arg, $taken_at, $date_source, $photo_id, $house_id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE outdoor_photos SET caption = ? WHERE id = ? AND house_id = ?'
        );
        $stmt->bind_param('sii', $caption_arg, $photo_id, $house_id);
    }
    $stmt->execute();
    $stmt->close();
}

function hds_outdoor_photos_fetch(mysqli $conn, int $house_id, string $album, ?int $year = null): array
{
    if (!hds_outdoor_photos_ensure_table($conn)) {
        return [];
    }
    if ($year !== null && $year > 0) {
        $stmt = $conn->prepare(
            'SELECT * FROM outdoor_photos
             WHERE house_id = ? AND album = ? AND YEAR(COALESCE(taken_at, upload_date)) = ?
             ORDER BY COALESCE(taken_at, upload_date) DESC, id DESC'
        );
        $stmt->bind_param('isi', $house_id, $album, $year);
    } else {
        $stmt = $conn->prepare(
            'SELECT * FROM outdoor_photos
             WHERE house_id = ? AND album = ?
             ORDER BY COALESCE(taken_at, upload_date) DESC, id DESC'
        );
        $stmt->bind_param('is', $house_id, $album);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function hds_outdoor_photos_year_counts(mysqli $conn, int $house_id, string $album): array
{
    if (!hds_outdoor_photos_ensure_table($conn)) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT YEAR(COALESCE(taken_at, upload_date)) AS photo_year, COUNT(*) AS photo_count
         FROM outdoor_photos
         WHERE house_id = ? AND album = ?
         GROUP BY photo_year
         ORDER BY photo_year DESC'
    );
    $stmt->bind_param('is', $house_id, $album);
    $stmt->execute();
    $result = $stmt->get_result();
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[(int) $row['photo_year']] = (int) $row['photo_count'];
    }
    $stmt->close();
    return $years;
}

function hds_outdoor_photos_album_counts(mysqli $conn, int $house_id): array
{
    $counts = ['trail_camera' => 0, 'fishing' => 0];
    if (!hds_outdoor_photos_ensure_table($conn)) {
        return $counts;
    }
    $stmt = $conn->prepare(
        'SELECT album, COUNT(*) AS photo_count FROM outdoor_photos WHERE house_id = ? GROUP BY album'
    );
    $stmt->bind_param('i', $house_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $album = (string) $row['album'];
        if (isset($counts[$album])) {
            $counts[$album] = (int) $row['photo_count'];
        }
    }
    $stmt->close();
    return $counts;
}

function hds_outdoor_photos_group_timeline(array $photos): array
{
    $grouped = [];
    foreach ($photos as $photo) {
        $stamp = $photo['taken_at'] ?: $photo['upload_date'];
        $ts = strtotime((string) $stamp) ?: time();
        $year = (int) date('Y', $ts);
        $month_key = date('Y-m', $ts);
        $day_key = date('Y-m-d', $ts);
        if (!isset($grouped[$year])) {
            $grouped[$year] = [];
        }
        if (!isset($grouped[$year][$month_key])) {
            $grouped[$year][$month_key] = [
                'label' => date('F Y', $ts),
                'days' => [],
            ];
        }
        if (!isset($grouped[$year][$month_key]['days'][$day_key])) {
            $grouped[$year][$month_key]['days'][$day_key] = [
                'label' => date('l, F j, Y', $ts),
                'photos' => [],
            ];
        }
        $grouped[$year][$month_key]['days'][$day_key]['photos'][] = $photo;
    }
    return $grouped;
}

function hds_outdoor_photos_display_src(array $photo): string
{
    $filename = (string) ($photo['filename'] ?? '');
    $thumb = (string) ($photo['thumbnail'] ?? '');
    if ($thumb !== '' && is_file(hds_outdoor_photos_thumb_dir() . $thumb)) {
        return hds_outdoor_photos_thumb_url($thumb);
    }
    return hds_outdoor_photos_url($filename);
}

function hds_outdoor_photos_full_src(array $photo): string
{
    return hds_outdoor_photos_url((string) ($photo['filename'] ?? ''));
}

function hds_outdoor_photos_taken_label(array $photo): string
{
    $stamp = $photo['taken_at'] ?: $photo['upload_date'];
    $ts = strtotime((string) $stamp);
    if ($ts === false) {
        return '';
    }
    return date('g:i A', $ts);
}

function hds_outdoor_photos_date_source_label(string $source): string
{
    return match ($source) {
        'exif' => 'From photo metadata',
        'filename' => 'From file name',
        'manual' => 'Date set manually',
        default => 'Upload time (no metadata date)',
    };
}

function hds_outdoor_photos_maps_url(?string $lat, ?string $lng): ?string
{
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return null;
    }
    return 'https://maps.google.com/?q=' . rawurlencode($lat . ',' . $lng);
}

function hds_outdoor_photos_redirect(int $house_id, bool $keep_year = true): void
{
    $albums = array_keys(hds_outdoor_photos_albums());
    $album = (string) ($_POST['outdoor_album'] ?? 'trail_camera');
    if (!in_array($album, $albums, true)) {
        $album = 'trail_camera';
    }
    $url = 'house.php?id=' . $house_id . '&tab=outdoor-photos&album=' . rawurlencode($album);
    $year = (int) ($_POST['outdoor_year'] ?? 0);
    if ($keep_year && $year >= 1990 && $year <= 2100) {
        $url .= '&year=' . $year;
    }
    header('Location: ' . $url);
    exit;
}

function hds_outdoor_photos_delete_house_files(mysqli $conn, int $house_id): void
{
    if (!hds_outdoor_photos_ensure_table($conn)) {
        return;
    }
    $stmt = $conn->prepare('SELECT filename, thumbnail FROM outdoor_photos WHERE house_id = ?');
    $stmt->bind_param('i', $house_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        hds_outdoor_photos_unlink_files($row['filename'] ?? null, $row['thumbnail'] ?? null);
    }
    $stmt->close();
    $del = $conn->prepare('DELETE FROM outdoor_photos WHERE house_id = ?');
    $del->bind_param('i', $house_id);
    $del->execute();
    $del->close();
}
