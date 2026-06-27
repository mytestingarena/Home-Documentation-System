<?php
// tabs/media.php — Media tab content

require_once __DIR__ . '/../includes/media-video.php';

global $conn, $house_id, $hds_ui_settings;
?>

<h2>Media</h2>

<?php
if (!empty($_SESSION['media_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['media_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['media_error']);
}
if (!empty($_SESSION['media_warning'])) {
    echo "<p class='media-warning'>" . htmlspecialchars($_SESSION['media_warning'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['media_warning']);
}
if (!empty($_SESSION['media_success'])) {
    echo "<p class='media-success'>" . htmlspecialchars($_SESSION['media_success'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['media_success']);
}
?>

<?php
$photos_sort = $_GET['photos_sort'] ?? 'date_desc';
$photos_filter = $_GET['photos_filter'] ?? 'all';

$sort_map = [
    'date_desc' => 'upload_date DESC',
    'date_asc'  => 'upload_date ASC',
    'name_asc'  => 'filename ASC',
    'name_desc' => 'filename DESC',
];
$order_by = $sort_map[$photos_sort] ?? 'upload_date DESC';

$filter_map = ['Interior', 'Walkthrough', 'Exterior'];
$open_media_id = preg_replace('/[^a-z0-9\-]/', '', $_GET['open_media'] ?? '');
$where = '';
if ($photos_filter !== 'all' && in_array($photos_filter, $filter_map, true)) {
    $safe_filter = mysqli_real_escape_string($conn, $photos_filter);
    $where = "AND section = '$safe_filter'";
}

function photo_grid($conn, $house_id, $section, $is_ir, $order_by, $where, string $media_section_id = '', bool $videos_only = false) {
    $title = $is_ir ? "IR " . ucfirst($section) : ucfirst($section);
    $sql = "SELECT * FROM photos WHERE house_id = $house_id AND section = '$section' AND is_ir = $is_ir";
    if ($where) $sql .= " $where";
    $sql .= " ORDER BY $order_by";
    $photos = $conn->query($sql);

    echo "<div class='photo-gallery'>";
    if ($photos->num_rows == 0) {
        $empty_label = $videos_only ? 'No walkthrough videos yet.' : 'No media in this section yet.';
        echo "<p style='color:#777;'>$empty_label</p>";
    } else {
        while ($photo = $photos->fetch_assoc()) {
            $filename = $photo['filename'];
            $fn = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
            $date = date('M j, Y g:i A', strtotime($photo['upload_date']));
            $disk_path = hds_media_disk_path($filename);
            $url_path = hds_media_url_path($filename);
            $file_exists = file_exists($disk_path);
            $size = $file_exists ? filesize($disk_path) : 0;
            $size_str = $size > 1024*1024 ? round($size / (1024*1024), 1) . ' MB' : round($size / 1024, 1) . ' KB';

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $is_video = $videos_only || in_array($ext, hds_walkthrough_video_extensions(), true);

            echo "<div class='media-item'>";
            if ($is_video) {
                if ($file_exists) {
                    $video_src = htmlspecialchars($url_path, ENT_QUOTES, 'UTF-8');
                    $video_type = htmlspecialchars(hds_video_mime_type($filename), ENT_QUOTES, 'UTF-8');
                    echo "<video controls muted preload='metadata' playsinline class='media-content media-content--video'>";
                    echo "<source src='$video_src' type='$video_type'>";
                    echo "Your browser does not support the video tag.";
                    echo "</video>";
                } else {
                    echo "<div class='media-missing-file'>Video file not found on server.<br><small>" . $fn . "</small></div>";
                }
            } else {
                $image_src = htmlspecialchars($url_path, ENT_QUOTES, 'UTF-8');
                echo "<button type='button' class='media-lightbox-trigger' data-src='$image_src' data-caption='$fn' aria-label='View full size: $fn'>";
                echo "<img src='$image_src' alt='$title' class='media-content media-content--clickable'>";
                echo "</button>";
            }
            echo "<div class='media-info'>";
            echo "<p><i class='fa-solid " . ($is_video ? 'fa-video' : 'fa-file-image') . "' style='color:" . ($is_video ? '#dc3545' : '#f59e0b') . "; margin-right:6px;'></i>";
            echo "<a href='" . htmlspecialchars($url_path, ENT_QUOTES, 'UTF-8') . "' target='_blank' download>$fn</a></p>";
            echo "<p style='font-size:0.85em; color:#666;'>$size_str • Uploaded: $date</p>";
            echo "<div class='media-item-actions'>";
            $section_attr = htmlspecialchars($media_section_id, ENT_QUOTES, 'UTF-8');
            echo "<button type='button' class='small-btn media-rename-open' data-photo-id='{$photo['id']}' data-filename=\"$fn\" data-media-section='$section_attr'>Rename</button>";
            echo "<form method='post' class='media-delete-form'>";
            echo "<input type='hidden' name='photo_id' value='{$photo['id']}'>";
            echo "<input type='hidden' name='house_id' value='{$house_id}'>";
            echo "<input type='submit' name='delete_photo' value='Delete' class='delete-btn' onclick='return confirm(\"Delete this " . ($is_video ? 'video' : 'photo') . "?\");'>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
            echo "</div>";
        }
    }
    echo "</div>";
}

function render_media_section(string $id, string $title, string $section, int $is_ir, string $accept, string $button_label, string $help_text, $conn, $house_id, $order_by, $where, bool $videos_only = false): void {
    global $hds_ui_settings, $open_media_id;
    if (!hds_ui_section_enabled('media-' . $id, $hds_ui_settings)) {
        return;
    }
    $is_open = ($id === $open_media_id) ? ' open' : '';
    echo "<details class='section-card media-section-card collapsible-section' id='media-$id'$is_open>";
    echo "<summary class='collapsible-summary'>";
    echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
    echo "<span class='collapsible-summary-title'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "</summary>";
    echo "<div class='collapsible-body'>";
    echo "<form method='post' enctype='multipart/form-data'>";
    echo "<input type='file' name='photos[]' accept='$accept' multiple>";
    echo "<small>$help_text</small><br><br>";
    echo "<input type='hidden' name='section' value='" . htmlspecialchars($section, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='is_ir' value='$is_ir'>";
    echo "<input type='hidden' name='open_media_section' value='" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='submit' name='upload_photo' value='" . htmlspecialchars($button_label, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";
    photo_grid($conn, $house_id, $section, $is_ir, $order_by, $where, $id, $videos_only);
    echo "</div>";
    echo "</details>";
}
?>

<div class="section-card" style="margin-bottom:20px;">
    <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="id" value="<?php echo $house_id; ?>">
        <input type="hidden" name="tab" value="media">
        <label>Sort by:</label>
        <select name="photos_sort">
            <option value="date_desc" <?php echo ($photos_sort == 'date_desc') ? 'selected' : ''; ?>>Newest first</option>
            <option value="date_asc" <?php echo ($photos_sort == 'date_asc') ? 'selected' : ''; ?>>Oldest first</option>
            <option value="name_asc" <?php echo ($photos_sort == 'name_asc') ? 'selected' : ''; ?>>File name A-Z</option>
            <option value="name_desc" <?php echo ($photos_sort == 'name_desc') ? 'selected' : ''; ?>>File name Z-A</option>
        </select>

        <label>Filter:</label>
        <select name="photos_filter">
            <option value="all" <?php echo ($photos_filter == 'all') ? 'selected' : ''; ?>>All Sections</option>
            <option value="Interior" <?php echo ($photos_filter == 'Interior') ? 'selected' : ''; ?>>Interior</option>
            <option value="Walkthrough" <?php echo ($photos_filter == 'Walkthrough') ? 'selected' : ''; ?>>Site Walkthrough</option>
            <option value="Exterior" <?php echo ($photos_filter == 'Exterior') ? 'selected' : ''; ?>>Exterior</option>
        </select>

        <input type="submit" value="Apply">
    </form>
</div>

<div class="collapsible-list-toolbar">
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.media-sections-list .collapsible-section', true)">Expand all</button>
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.media-sections-list .collapsible-section', false)">Collapse all</button>
    <form method="post" class="media-sync-form" onsubmit="return confirm('Register files already on the server that are missing from the media list?');">
        <input type="submit" name="sync_media_files" value="Sync Server Files" class="small-btn">
    </form>
</div>

<div class="media-sections-list">
<?php
render_media_section('interior', 'Regular Interior Photos', 'Interior', 0, 'image/*', 'Upload Interior Photos', 'Select up to 15 photos', $conn, $house_id, $order_by, $where);
render_media_section(
    'walkthrough',
    'Site Walkthrough (Interior Sequence)',
    'Walkthrough',
    0,
    hds_walkthrough_video_accept(),
    'Upload Walkthrough Videos',
    'Video files only (MP4, MOV, AVI, MKV, WebM, etc.). Up to 15 per upload. Videos are compressed to streaming-friendly MP4 after upload.',
    $conn,
    $house_id,
    $order_by,
    $where,
    true
);
render_media_section('exterior', 'Regular Exterior Photos', 'Exterior', 0, 'image/*', 'Upload Exterior Photos', 'Select up to 15 photos', $conn, $house_id, $order_by, $where);
render_media_section('ir-interior', 'IR Interior Scans', 'Interior', 1, 'image/*', 'Upload IR Interior', 'Select up to 15 scans', $conn, $house_id, $order_by, $where);
render_media_section('ir-exterior', 'IR Exterior Scans', 'Exterior', 1, 'image/*', 'Upload IR Exterior', 'Select up to 15 scans', $conn, $house_id, $order_by, $where);
?>
</div>

<div id="mediaLightbox" class="media-lightbox" hidden aria-hidden="true">
    <div class="media-lightbox-backdrop" data-lightbox-close></div>
    <div class="media-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Image viewer">
        <button type="button" class="media-lightbox-close" data-lightbox-close aria-label="Close">&times;</button>
        <button type="button" class="media-lightbox-nav media-lightbox-prev" aria-label="Previous image">&lsaquo;</button>
        <button type="button" class="media-lightbox-nav media-lightbox-next" aria-label="Next image">&rsaquo;</button>
        <img class="media-lightbox-image" src="" alt="">
        <div class="media-lightbox-caption"></div>
        <div class="media-lightbox-counter"></div>
    </div>
</div>

<div id="mediaRenameModal" class="media-rename-modal" hidden aria-hidden="true">
    <div class="media-rename-backdrop" data-rename-close></div>
    <div class="media-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaRenameTitle">
        <button type="button" class="media-rename-close" data-rename-close aria-label="Close">&times;</button>
        <h3 id="mediaRenameTitle">Rename File</h3>
        <p class="media-rename-current-row">
            <span class="media-rename-label">Current name:</span>
            <span id="mediaRenameCurrent" class="media-rename-current"></span>
        </p>
        <form method="post" id="mediaRenameForm">
            <input type="hidden" name="photo_id" id="mediaRenamePhotoId" value="">
            <input type="hidden" name="house_id" value="<?php echo (int)$house_id; ?>">
            <input type="hidden" name="open_media_section" id="mediaRenameSection" value="">
            <label for="mediaRenameNew">New name:</label>
            <div class="media-rename-input-row">
                <input type="text" name="photo_basename" id="mediaRenameNew" required autocomplete="off" placeholder="Enter name without extension">
                <span id="mediaRenameExt" class="media-rename-ext"></span>
            </div>
            <div class="media-rename-actions">
                <button type="button" class="small-btn" data-rename-close>Cancel</button>
                <input type="submit" name="rename_photo" value="Save" class="media-rename-save">
            </div>
        </form>
    </div>
</div>