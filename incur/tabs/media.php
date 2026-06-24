<?php
// tabs/media.php — Media tab content

global $conn, $house_id;
?>

<h2>Media</h2>

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
$where = '';
if ($photos_filter !== 'all' && in_array($photos_filter, $filter_map, true)) {
    $safe_filter = mysqli_real_escape_string($conn, $photos_filter);
    $where = "AND section = '$safe_filter'";
}

function photo_grid($conn, $house_id, $section, $is_ir, $order_by, $where) {
    $title = $is_ir ? "IR " . ucfirst($section) : ucfirst($section);
    $sql = "SELECT * FROM photos WHERE house_id = $house_id AND section = '$section' AND is_ir = $is_ir";
    if ($where) $sql .= " $where";
    $sql .= " ORDER BY $order_by";
    $photos = $conn->query($sql);

    echo "<div class='photo-gallery'>";
    if ($photos->num_rows == 0) {
        echo "<p style='color:#777;'>No media in this section yet.</p>";
    } else {
        while ($photo = $photos->fetch_assoc()) {
            $fn = htmlspecialchars($photo['filename']);
            $date = date('M j, Y g:i A', strtotime($photo['upload_date']));
            $full_path = "uploads/photos/" . $fn;
            $size = file_exists($full_path) ? filesize($full_path) : 0;
            $size_str = $size > 1024*1024 ? round($size / (1024*1024), 1) . ' MB' : round($size / 1024, 1) . ' KB';

            $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
            $is_video = in_array($ext, ['mp4','webm','mov','avi','mkv']);

            echo "<div class='media-item'>";
            if ($is_video) {
                echo "<video controls class='media-content'><source src='$full_path' type='video/mp4'>Your browser does not support the video tag.</video>";
            } else {
                echo "<img src='$full_path' alt='$title' class='media-content'>";
            }
            echo "<div class='media-info'>";
            echo "<p><i class='fa-solid " . ($is_video ? 'fa-video' : 'fa-file-image') . "' style='color:" . ($is_video ? '#dc3545' : '#f59e0b') . "; margin-right:6px;'></i>";
            echo "<a href='$full_path' target='_blank' download>$fn</a></p>";
            echo "<p style='font-size:0.85em; color:#666;'>$size_str • Uploaded: $date</p>";
            echo "<form method='post' style='margin:8px 0;'>";
            echo "<input type='hidden' name='photo_id' value='{$photo['id']}'>";
            echo "<input type='hidden' name='house_id' value='{$house_id}'>";
            echo "<input type='submit' name='delete_photo' value='Delete' class='delete-btn' onclick='return confirm(\"Delete this " . ($is_video ? 'video' : 'photo') . "?\");'>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
    }
    echo "</div>";
}

function render_media_section(string $id, string $title, string $section, int $is_ir, string $accept, string $button_label, string $help_text, $conn, $house_id, $order_by, $where): void {
    echo "<details class='section-card media-section-card collapsible-section' id='media-$id'>";
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
    echo "<input type='submit' name='upload_photo' value='" . htmlspecialchars($button_label, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";
    photo_grid($conn, $house_id, $section, $is_ir, $order_by, $where);
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
</div>

<div class="media-sections-list">
<?php
render_media_section('interior', 'Regular Interior Photos', 'Interior', 0, 'image/*', 'Upload Interior Photos', 'Select up to 15 photos', $conn, $house_id, $order_by, $where);
render_media_section('walkthrough', 'Site Walkthrough (Interior Sequence)', 'Walkthrough', 0, 'image/*,video/*', 'Upload Walkthrough Media', 'Select up to 15 photos or videos', $conn, $house_id, $order_by, $where);
render_media_section('exterior', 'Regular Exterior Photos', 'Exterior', 0, 'image/*', 'Upload Exterior Photos', 'Select up to 15 photos', $conn, $house_id, $order_by, $where);
render_media_section('ir-interior', 'IR Interior Scans', 'Interior', 1, 'image/*', 'Upload IR Interior', 'Select up to 15 scans', $conn, $house_id, $order_by, $where);
render_media_section('ir-exterior', 'IR Exterior Scans', 'Exterior', 1, 'image/*', 'Upload IR Exterior', 'Select up to 15 scans', $conn, $house_id, $order_by, $where);
?>
</div>