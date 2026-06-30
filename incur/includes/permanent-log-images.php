<?php
// includes/permanent-log-images.php — image upload/display for permanent maintenance log entries

function hds_perm_log_upload_dir(): string
{
    return __DIR__ . '/../uploads/maintenance-log/';
}

function hds_perm_log_ensure_upload_dir(): ?string
{
    $target_dir = hds_perm_log_upload_dir();
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0775, true)) {
            return 'Could not create the photo upload folder on the server.';
        }
    }
    if (!is_writable($target_dir)) {
        return 'The photo upload folder is not writable by the web server. Ask your admin to run: chown -R www-data:www-data uploads/maintenance-log && chmod 775 uploads/maintenance-log';
    }
    return null;
}

function hds_perm_log_upload_url(string $filename): string
{
    return 'uploads/maintenance-log/' . rawurlencode($filename);
}

function hds_perm_log_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_perm_log_sanitize_basename(string $raw): string
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

function hds_perm_log_delete_images_for_log(mysqli $conn, int $log_id, int $house_id): void
{
    $log_id = (int)$log_id;
    $images = $conn->query(
        "SELECT i.filename
         FROM permanent_maintenance_log_images i
         INNER JOIN permanent_maintenance_log l ON i.log_id = l.id
         WHERE i.log_id = $log_id AND l.house_id = $house_id"
    );
    if (!$images) {
        return;
    }
    $dir = hds_perm_log_upload_dir();
    while ($row = $images->fetch_assoc()) {
        $path = $dir . $row['filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function hds_render_perm_log_images(mysqli $conn, int $house_id, int $log_id, string $item_type): void
{
    $log_id = (int)$log_id;
    $type_esc = mysqli_real_escape_string($conn, preg_replace('/[^a-z_]/', '', $item_type));
    $images = $conn->query(
        "SELECT i.id, i.filename, i.upload_date
         FROM permanent_maintenance_log_images i
         INNER JOIN permanent_maintenance_log l ON i.log_id = l.id
         WHERE i.log_id = $log_id AND l.house_id = $house_id AND l.item_type = '$type_esc'
         ORDER BY i.upload_date DESC, i.id DESC"
    );

    echo "<div class='perm-log-images'>";
    echo "<h5><i class='fas fa-images' aria-hidden='true'></i> Photos</h5>";

    if ($images && $images->num_rows > 0) {
        echo "<div class='perm-log-gallery' data-lightbox-gallery='perm-log-$log_id'>";
        while ($image = $images->fetch_assoc()) {
            $image_id = (int)$image['id'];
            $filename = $image['filename'];
            $url = htmlspecialchars(hds_perm_log_upload_url($filename), ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

            echo "<div class='perm-log-photo'>";
            echo "<button type='button' class='media-lightbox-trigger perm-log-photo-thumb' data-src='$url' data-caption='$caption' aria-label='View full size'>";
            echo "<img src='$url' alt='$caption' loading='lazy'>";
            echo "</button>";
            echo "<p class='perm-log-photo-name' title='$caption'>$caption</p>";
            echo "<div class='perm-log-photo-actions'>";
            echo "<button type='button' class='small-btn perm-log-rename-open' data-perm-log-image-id='$image_id' data-perm-log-id='$log_id' data-item-type='$type_esc' data-filename=\"$caption\">Rename</button>";
            echo "<form method='post' class='perm-log-photo-delete' onsubmit='return confirm(\"Delete this photo?\");'>";
            echo "<input type='hidden' name='permanent_log_id' value='$log_id'>";
            echo "<input type='hidden' name='perm_log_image_id' value='$image_id'>";
            echo "<input type='hidden' name='item_type' value='$type_esc'>";
            echo "<button type='submit' name='delete_perm_log_image' class='small-btn delete-btn'>Delete</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p class='empty-note perm-log-images-empty'>No photos yet.</p>";
    }

    echo "<form method='post' enctype='multipart/form-data' class='perm-log-upload-form'>";
    echo "<input type='hidden' name='permanent_log_id' value='$log_id'>";
    echo "<input type='hidden' name='item_type' value='$type_esc'>";
    echo "<label class='perm-log-upload-label'>Add photos to this log entry:</label>";
    echo "<input type='file' name='perm_log_images[]' accept='image/jpeg,image/png,image/gif,image/webp' multiple required>";
    echo "<input type='submit' name='upload_perm_log_image' value='Upload Photos' class='small-btn'>";
    echo "</form>";
    echo "</div>";
}
