<?php
// tabs/designs.php — Designs tab content

global $conn, $house_id, $hds_ui_settings;
?>

<h2>Designs / Drawings / Plans</h2>

<?php if (hds_ui_section_enabled('designs-upload', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Upload Design Files</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="designs[]" multiple>
        <br><small>Select multiple files at once.<br>Allowed: Visio (.vsd/.vsdx), PDF, XPS, Office docs, LibreOffice, ZIP<br><strong>.xps files are automatically converted to .pdf using mutool (both kept)</strong></small><br><br>
        <input type="submit" name="upload_designs" value="Upload Files">
    </form>
</div>
<?php endif; ?>

<?php if (hds_ui_section_enabled('designs-list', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Uploaded Designs</h3>

    <?php
    $designs_sort = $_GET['designs_sort'] ?? 'date_desc';
    $designs_filter = $_GET['designs_filter'] ?? 'all';

    $order_by = 'upload_date DESC';
    if ($designs_sort == 'date_asc') $order_by = 'upload_date ASC';
    if ($designs_sort == 'name_asc') $order_by = 'filename ASC';
    if ($designs_sort == 'name_desc') $order_by = 'filename DESC';

    $where = '';
    if ($designs_filter != 'all') {
        $where = "AND LOWER(filename) LIKE '%.$designs_filter%'";
    }
    ?>

    <form method="get" style="display:flex; gap:15px; flex-wrap:wrap; align-items:center; margin-bottom:15px;">
        <input type="hidden" name="id" value="<?php echo $house_id; ?>">
        <input type="hidden" name="tab" value="designs">
        <label>Sort by:</label>
        <select name="designs_sort">
            <option value="date_desc" <?php echo ($designs_sort == 'date_desc') ? 'selected' : ''; ?>>Newest first</option>
            <option value="date_asc" <?php echo ($designs_sort == 'date_asc') ? 'selected' : ''; ?>>Oldest first</option>
            <option value="name_asc" <?php echo ($designs_sort == 'name_asc') ? 'selected' : ''; ?>>File name A-Z</option>
            <option value="name_desc" <?php echo ($designs_sort == 'name_desc') ? 'selected' : ''; ?>>File name Z-A</option>
        </select>

        <label>Filter by type:</label>
        <select name="designs_filter">
            <option value="all" <?php echo ($designs_filter == 'all') ? 'selected' : ''; ?>>All</option>
            <option value="pdf" <?php echo ($designs_filter == 'pdf') ? 'selected' : ''; ?>>PDF</option>
            <option value="xps" <?php echo ($designs_filter == 'xps') ? 'selected' : ''; ?>>XPS</option>
            <option value="vsd" <?php echo ($designs_filter == 'vsd') ? 'selected' : ''; ?>>Visio</option>
            <option value="zip" <?php echo ($designs_filter == 'zip') ? 'selected' : ''; ?>>ZIP</option>
        </select>

        <input type="submit" value="Apply">
    </form>

    <?php
    $sql = "SELECT * FROM designs WHERE house_id = $house_id";
    if ($where) $sql .= " $where";
    $sql .= " ORDER BY $order_by";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "<p style='color:#777; font-style:italic;'>No design files match the filter.</p>";
    } else {
        echo "<div class='photo-grid'>";
        while ($file = $result->fetch_assoc()) {
            $filename = htmlspecialchars($file['filename']);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            $note = ($ext === 'pdf' && strpos($filename, '_') !== false) ? ' (from XPS)' : '';

            $full_path = "uploads/designs/" . $filename;
            $size = file_exists($full_path) ? filesize($full_path) : 0;
            $size_str = $size > 1024*1024 ? round($size / (1024*1024), 1) . ' MB' : round($size / 1024, 1) . ' KB';

            $icon = 'fa-file';
            $icon_color = '#6b7280';
            if ($ext === 'pdf') {
                $icon = 'fa-file-pdf';
                $icon_color = '#dc2626';
            } else if ($ext === 'xps') {
                $icon = 'fa-file-pdf';
                $icon_color = '#7c3aed';
            } else if (strpos($ext, 'vsd') !== false) {
                $icon = 'fa-file-lines';
                $icon_color = '#1d4ed8';
            } else if ($ext === 'zip') {
                $icon = 'fa-file-zipper';
                $icon_color = '#ea580c';
            }

            $preview = '<div style="height:140px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;border-radius:6px;font-weight:bold;color:#6c757d;border:1px solid #dee2e6;">.' . strtoupper($ext) . '</div>';

            echo "<div class='photo-item' style='text-align:center;'>";
            echo $preview;
            echo "<p style='margin:8px 0; font-size:0.95em;'>";
            echo "<i class='fa-solid $icon' style='color:$icon_color; margin-right:6px; font-size:1.2em;'></i>";
            echo "<a href='uploads/designs/$filename' target='_blank' download>$filename$note</a></p>";
            echo "<p style='font-size:0.85em; color:#666;'>$size_str • Uploaded: " . date('M j, Y g:i A', strtotime($file['upload_date'])) . "</p>";

            echo "<form method='post' style='margin-top:10px;' onsubmit='return confirm(\"Delete $filename permanently? This cannot be undone.\");'>";
            echo "<input type='hidden' name='design_id' value='{$file['id']}'>";
            echo "<input type='submit' name='delete_design' value='Delete' class='delete-btn'>";
            echo "</form>";

            echo "</div>";
        }
        echo "</div>";

        // Newest PDF Preview - BELOW the list
        $newest_pdf_query = "SELECT filename FROM designs WHERE house_id = $house_id AND filename LIKE '%.pdf' ORDER BY upload_date DESC LIMIT 1";
        $newest_pdf = $conn->query($newest_pdf_query)->fetch_assoc();

        if ($newest_pdf) {
            $pdf_url = "uploads/designs/" . htmlspecialchars($newest_pdf['filename']);
            echo "<h3>Newest PDF Preview</h3>";
            echo "<iframe src='$pdf_url' class='pdf-preview' title='Newest PDF Preview'></iframe>";
        } else {
            echo "<p style='color:#777; font-style:italic; margin-top:20px;'>No PDF files uploaded yet.</p>";
        }
    }
    ?>
</div>
<?php endif; ?>
