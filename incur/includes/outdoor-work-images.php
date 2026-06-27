<?php
// includes/outdoor-work-images.php — image upload/display for outdoor work items

function hds_outdoor_work_upload_dir(): string
{
    return __DIR__ . '/../uploads/outdoor-work/';
}

function hds_outdoor_work_ensure_upload_dir(): ?string
{
    $target_dir = hds_outdoor_work_upload_dir();
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0775, true)) {
            return 'Could not create the photo upload folder on the server.';
        }
    }
    if (!is_writable($target_dir)) {
        return 'The photo upload folder is not writable by the web server. Ask your admin to run: chown -R www-data:www-data uploads/outdoor-work && chmod 775 uploads/outdoor-work';
    }
    return null;
}

function hds_outdoor_work_upload_url(string $filename): string
{
    return 'uploads/outdoor-work/' . rawurlencode($filename);
}

function hds_outdoor_work_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_outdoor_work_sanitize_basename(string $raw): string
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

function hds_outdoor_work_delete_image_files(mysqli $conn, int $outdoor_id, int $house_id): void
{
    $outdoor_id = (int)$outdoor_id;
    $images = $conn->query(
        "SELECT i.filename
         FROM outdoor_work_images i
         INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id
         WHERE i.outdoor_work_id = $outdoor_id AND w.house_id = $house_id"
    );
    if (!$images) {
        return;
    }
    $dir = hds_outdoor_work_upload_dir();
    while ($row = $images->fetch_assoc()) {
        $path = $dir . $row['filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function hds_render_outdoor_work_images(mysqli $conn, int $house_id, int $outdoor_id): void
{
    $outdoor_id = (int)$outdoor_id;
    $images = $conn->query(
        "SELECT i.id, i.filename, i.upload_date
         FROM outdoor_work_images i
         INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id
         WHERE i.outdoor_work_id = $outdoor_id AND w.house_id = $house_id
         ORDER BY i.upload_date DESC, i.id DESC"
    );

    echo "<div class='outdoor-work-images'>";
    echo "<h5><i class='fas fa-images' aria-hidden='true'></i> Photos</h5>";

    if ($images && $images->num_rows > 0) {
        echo "<div class='outdoor-work-gallery' data-lightbox-gallery='outdoor-$outdoor_id'>";
        while ($image = $images->fetch_assoc()) {
            $image_id = (int)$image['id'];
            $filename = $image['filename'];
            $url = htmlspecialchars(hds_outdoor_work_upload_url($filename), ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

            echo "<div class='outdoor-work-photo'>";
            echo "<button type='button' class='media-lightbox-trigger outdoor-work-photo-thumb' data-src='$url' data-caption='$caption' aria-label='View full size'>";
            echo "<img src='$url' alt='$caption' loading='lazy'>";
            echo "</button>";
            echo "<p class='outdoor-work-photo-name' title='$caption'>$caption</p>";
            echo "<div class='outdoor-work-photo-actions'>";
            echo "<button type='button' class='small-btn outdoor-rename-open' data-outdoor-image-id='$image_id' data-outdoor-id='$outdoor_id' data-filename=\"$caption\">Rename</button>";
            echo "<form method='post' class='outdoor-work-photo-delete' onsubmit='return confirm(\"Delete this photo?\");'>";
            echo "<input type='hidden' name='outdoor_id' value='$outdoor_id'>";
            echo "<input type='hidden' name='outdoor_image_id' value='$image_id'>";
            echo "<button type='submit' name='delete_outdoor_work_image' class='small-btn delete-btn'>Delete</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p class='empty-note outdoor-work-images-empty'>No photos yet.</p>";
    }

    echo "<form method='post' enctype='multipart/form-data' class='outdoor-work-upload-form'>";
    echo "<input type='hidden' name='outdoor_id' value='$outdoor_id'>";
    echo "<label class='outdoor-work-upload-label'>Add photos to this entry:</label>";
    echo "<input type='file' name='outdoor_images[]' accept='image/jpeg,image/png,image/gif,image/webp' multiple required>";
    echo "<input type='submit' name='upload_outdoor_work_image' value='Upload Photos' class='small-btn'>";
    echo "<span class='outdoor-work-upload-hint'>Photos attach to this outdoor work entry — use <strong>Upload Photos</strong> after choosing a file.</span>";
    echo "</form>";
    echo "</div>";
}