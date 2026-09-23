<?php
// tabs/media-upload.php — Media/Photos upload handler (called from house.php)

global $conn, $house_id;

require_once __DIR__ . '/../includes/media-video.php';

$section = $_POST['section'] ?? 'Interior';
if (!in_array($section, ['Interior', 'Exterior', 'Walkthrough'], true)) {
    $section = 'Interior';
}
$is_ir = intval($_POST['is_ir'] ?? 0);
$is_walkthrough = ($section === 'Walkthrough');
$target_dir = hds_media_upload_dir();

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0775, true);
}

if ($is_walkthrough) {
    @set_time_limit(600);
    @ini_set('max_execution_time', '600');
}

$count = 0;
$max = 15;
$allowed_images = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_videos = hds_walkthrough_video_extensions();
$rejected_non_video = false;
$conversion_failed = false;
$conversion_errors = [];
$upload_errors = [];
$db_errors = [];

if (empty($_FILES['photos']['name']) || !is_array($_FILES['photos']['name'])) {
    $_SESSION['media_error'] = 'No files were received. The upload may have exceeded post_max_size or upload_max_filesize on the server.';
    return;
}

foreach ($_FILES['photos']['tmp_name'] as $k => $tmp) {
    if ($count >= $max) {
        break;
    }

    $original_name = basename((string)($_FILES['photos']['name'][$k] ?? ''));
    $upload_err = (int)($_FILES['photos']['error'][$k] ?? UPLOAD_ERR_NO_FILE);

    if ($upload_err !== UPLOAD_ERR_OK) {
        if ($original_name !== '') {
            $upload_errors[] = $original_name . ': ' . hds_upload_error_message($upload_err);
        }
        continue;
    }

    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $is_video = in_array($ext, $allowed_videos, true);

    if ($is_walkthrough) {
        if (!$is_video) {
            $rejected_non_video = true;
            continue;
        }
    } elseif (!in_array($ext, $allowed_images, true)) {
        $upload_errors[] = $original_name . ': File type not allowed for this section.';
        continue;
    }

    $final_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original_name);
    $target = $target_dir . $final_name;

    if (!move_uploaded_file($tmp, $target)) {
        $upload_errors[] = $original_name . ': Could not save uploaded file to ' . $target_dir;
        continue;
    }

    $fn = $final_name;
    $photo_id = 0;

    $register_error = '';
    if (!hds_media_register_file($conn, $house_id, $fn, $section, $is_ir, $register_error)) {
        $db_errors[] = $original_name . ': Saved to disk but database insert failed — ' . $register_error;
        continue;
    }

    $photo_id = (int)$conn->insert_id;

    if ($is_walkthrough) {
        $converted_name = pathinfo($final_name, PATHINFO_FILENAME) . '_stream.mp4';
        $converted_path = $target_dir . $converted_name;

        $ffmpeg_error = '';
        if (hds_compress_walkthrough_video($target, $converted_path, $ffmpeg_error)) {
            unlink($target);
            $fn = $converted_name;
            $stmt = $conn->prepare('UPDATE photos SET filename = ? WHERE id = ? AND house_id = ?');
            $stmt->bind_param('sii', $fn, $photo_id, $house_id);
            if (!$stmt->execute()) {
                $update_error = trim($stmt->error) !== '' ? $stmt->error : $conn->error;
                $db_errors[] = $original_name . ': Compressed video saved but database update failed — ' . $update_error;
            }
            $stmt->close();
        } else {
            $conversion_failed = true;
            $conversion_errors[] = $original_name . ":\n" . $ffmpeg_error;
        }
    }

    $count++;
}

$messages = [];

if ($count > 0) {
    $messages[] = 'Successfully uploaded ' . $count . ' file' . ($count === 1 ? '' : 's') . '.';
}

if ($rejected_non_video) {
    $messages[] = 'Site Walkthrough accepts video files only (MP4, MOV, AVI, MKV, WebM, etc.).';
}

if (!empty($upload_errors)) {
    $messages[] = "Upload errors:\n" . implode("\n", $upload_errors);
}

if (!empty($db_errors)) {
    $messages[] = "Database errors:\n" . implode("\n", $db_errors);
}

if ($conversion_failed) {
    $messages[] = "Compression warnings:\n" . implode("\n\n", $conversion_errors);
}

if ($count === 0 && empty($messages)) {
    $_SESSION['media_error'] = 'No files were uploaded.';
} elseif ($count === 0) {
    $_SESSION['media_error'] = implode("\n\n", $messages);
} elseif ($conversion_failed || !empty($upload_errors) || !empty($db_errors) || $rejected_non_video) {
    $_SESSION['media_warning'] = implode("\n\n", $messages);
} else {
    $_SESSION['media_success'] = implode("\n\n", $messages);
}
?>