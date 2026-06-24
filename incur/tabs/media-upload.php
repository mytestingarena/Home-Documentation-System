<?php
// tabs/media-upload.php — Media/Photos upload handler (called from house.php)
$section = $_POST['section'] ?? 'Interior';
if (!in_array($section, ['Interior', 'Exterior', 'Walkthrough'], true)) {
    $section = 'Interior';
}
$is_ir = intval($_POST['is_ir'] ?? 0);
$target_dir = "uploads/photos/";
if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);
$count = 0;
$max = 15;
$allowed_images = ['jpg','jpeg','png','gif','webp'];
$allowed_videos = ['mp4','mov','avi','mkv','webm','wmv','flv'];

foreach ($_FILES['photos']['tmp_name'] as $k => $tmp) {
    if ($count >= $max) break;
    if ($_FILES['photos']['error'][$k] !== UPLOAD_ERR_OK) continue;

    $original_name = basename($_FILES['photos']['name'][$k]);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $is_video = in_array($ext, $allowed_videos);

    $final_name = time() . '_' . $original_name;
    $target = $target_dir . $final_name;

    if (in_array($ext, $allowed_images) || $is_video) {
        if (move_uploaded_file($tmp, $target)) {
            $fn = $final_name;

            if ($is_video && $section === 'Walkthrough') {
                $converted_name = pathinfo($final_name, PATHINFO_FILENAME) . '_hevc.mp4';
                $converted_path = $target_dir . $converted_name;

                $ffmpeg_cmd = "ffmpeg -i " . escapeshellarg($target) . " -c:v libx265 -crf 23 -preset medium -c:a aac -b:a 128k " . escapeshellarg($converted_path) . " 2>&1";
                exec($ffmpeg_cmd, $output, $return_var);

                if ($return_var === 0 && file_exists($converted_path)) {
                    unlink($target);
                    $fn = $converted_name;
                }
            }

            $sql = "INSERT INTO photos (house_id, section, filename, is_ir, upload_date) VALUES ($house_id, '$section', '$fn', $is_ir, NOW())";
            $conn->query($sql);
            $count++;
        }
    }
}
?>
