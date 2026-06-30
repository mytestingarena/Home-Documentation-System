<?php
// includes/house-work-images.php — image upload/display for house work items

function hds_house_work_upload_dir(): string
{
    return __DIR__ . '/../uploads/house-work/';
}

function hds_house_work_ensure_upload_dir(): ?string
{
    $target_dir = hds_house_work_upload_dir();
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0775, true)) {
            return 'Could not create the photo upload folder on the server.';
        }
    }
    if (!is_writable($target_dir)) {
        return 'The photo upload folder is not writable by the web server. Ask your admin to run: chown -R www-data:www-data uploads/house-work && chmod 775 uploads/house-work';
    }
    return null;
}

function hds_house_work_upload_url(string $filename): string
{
    return 'uploads/house-work/' . rawurlencode($filename);
}

function hds_house_work_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_house_work_sanitize_basename(string $raw): string
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

function hds_house_work_delete_image_files(mysqli $conn, int $house_work_id, int $house_id): void
{
    $house_work_id = (int)$house_work_id;
    $images = $conn->query(
        "SELECT i.filename
         FROM house_work_images i
         INNER JOIN house_work_items w ON i.house_work_id = w.id
         WHERE i.house_work_id = $house_work_id AND w.house_id = $house_id"
    );
    if (!$images) {
        return;
    }
    $dir = hds_house_work_upload_dir();
    while ($row = $images->fetch_assoc()) {
        $path = $dir . $row['filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function hds_render_house_work_images(mysqli $conn, int $house_id, int $house_work_id): void
{
    $house_work_id = (int)$house_work_id;
    $images = $conn->query(
        "SELECT i.id, i.filename, i.upload_date
         FROM house_work_images i
         INNER JOIN house_work_items w ON i.house_work_id = w.id
         WHERE i.house_work_id = $house_work_id AND w.house_id = $house_id
         ORDER BY i.upload_date DESC, i.id DESC"
    );

    echo "<div class='house-work-images'>";
    echo "<h5><i class='fas fa-images' aria-hidden='true'></i> Photos</h5>";

    if ($images && $images->num_rows > 0) {
        echo "<div class='house-work-gallery' data-lightbox-gallery='house-$house_work_id'>";
        while ($image = $images->fetch_assoc()) {
            $image_id = (int)$image['id'];
            $filename = $image['filename'];
            $url = htmlspecialchars(hds_house_work_upload_url($filename), ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

            echo "<div class='house-work-photo'>";
            echo "<button type='button' class='media-lightbox-trigger house-work-photo-thumb' data-src='$url' data-caption='$caption' aria-label='View full size'>";
            echo "<img src='$url' alt='$caption' loading='lazy'>";
            echo "</button>";
            echo "<p class='house-work-photo-name' title='$caption'>$caption</p>";
            echo "<div class='house-work-photo-actions'>";
            echo "<button type='button' class='small-btn house-rename-open' data-house-image-id='$image_id' data-house-work-id='$house_work_id' data-filename=\"$caption\">Rename</button>";
            echo "<form method='post' class='house-work-photo-delete' onsubmit='return confirm(\"Delete this photo?\");'>";
            echo "<input type='hidden' name='house_work_id' value='$house_work_id'>";
            echo "<input type='hidden' name='house_image_id' value='$image_id'>";
            echo "<button type='submit' name='delete_house_work_image' class='small-btn delete-btn'>Delete</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p class='empty-note house-work-images-empty'>No photos yet.</p>";
    }

    echo "<form method='post' enctype='multipart/form-data' class='house-work-upload-form'>";
    echo "<input type='hidden' name='house_work_id' value='$house_work_id'>";
    echo "<label class='house-work-upload-label'>Add photos to this entry:</label>";
    echo "<input type='file' name='house_images[]' accept='image/jpeg,image/png,image/gif,image/webp' multiple required>";
    echo "<input type='submit' name='upload_house_work_image' value='Upload Photos' class='small-btn'>";
    echo "<span class='house-work-upload-hint'>Photos attach to this house work entry — use <strong>Upload Photos</strong> after choosing a file.</span>";
    echo "</form>";
    echo "</div>";
}
