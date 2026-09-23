<?php
// tabs/designs.php — Designs tab content (download + in-frame draw.io viewer)

global $conn, $house_id, $hds_ui_settings;

if (!defined('HDS_DRAWIO_URL')) {
    define('HDS_DRAWIO_URL', '/incur/drawio');  // same-origin Apache proxy -> LAN draw.io
}

$drawio_base = rtrim(HDS_DRAWIO_URL, '/');

// Absolute base for designs-file.php so draw.io (other origin) can fetch the file.
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '192.168.1.110';
$script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/incur')), '/');
if ($script_dir === '' || $script_dir === '/') {
    $script_dir = '/incur';
}
$designs_file_endpoint = $scheme . '://' . $host . $script_dir . '/designs-file.php';

$viewable_exts = ['vsdx', 'vsd', 'vsdm', 'drawio', 'xml'];
require_once __DIR__ . '/../includes/vsdx-convert.php';
?>

<h2>Designs / Drawings / Plans</h2>

<?php if (hds_ui_section_enabled('designs-upload', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Upload Design Files</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="designs[]" multiple accept=".vsd,.vsdx,.vsdm,.drawio,.xml,.pdf,.xps,.doc,.docx,.xls,.xlsx,.ods,.odt,.zip">
        <br><small>Select multiple files at once.<br>Allowed: Visio (.vsd/.vsdx), draw.io (.drawio/.xml), PDF, XPS, Office docs, LibreOffice, ZIP<br><strong>.xps → .pdf</strong> via mutool (both kept). <strong>.vsdx/.vsd/.vsdm → .drawio</strong> via LAN draw.io (Visio kept as archive; View uses draw.io).</small><br><br>
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
        if ($designs_filter === 'vsd') {
            $where = "AND (LOWER(filename) LIKE '%.vsd' OR LOWER(filename) LIKE '%.vsdx' OR LOWER(filename) LIKE '%.vsdm')";
        } elseif ($designs_filter === 'drawio') {
            $where = "AND (LOWER(filename) LIKE '%.drawio' OR LOWER(filename) LIKE '%.xml')";
        } else {
            $where = "AND LOWER(filename) LIKE '%.$designs_filter%'";
        }
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
            <option value="drawio" <?php echo ($designs_filter == 'drawio') ? 'selected' : ''; ?>>draw.io</option>
            <option value="zip" <?php echo ($designs_filter == 'zip') ? 'selected' : ''; ?>>ZIP</option>
        </select>

        <input type="submit" value="Apply">
    </form>

    <?php
    $sql = "SELECT * FROM designs WHERE house_id = $house_id";
    if ($where) $sql .= " $where";
    $sql .= " ORDER BY $order_by";
    $result = $conn->query($sql);

    // Sibling map: Visio basename → .drawio when both exist (convert-on-upload).
    $all_names = [];
    $name_res = $conn->query("SELECT filename FROM designs WHERE house_id = $house_id");
    if ($name_res) {
        while ($nr = $name_res->fetch_assoc()) {
            $all_names[$nr['filename']] = true;
        }
    }

    if ($result->num_rows == 0) {
        echo "<p style='color:#777; font-style:italic;'>No design files match the filter.</p>";
    } else {
        echo "<div class='photo-grid'>";
        while ($file = $result->fetch_assoc()) {
            $raw_name = $file['filename'];
            $filename = htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8');
            $ext = strtolower(pathinfo($raw_name, PATHINFO_EXTENSION));

            $note = '';
            $badge = '';
            $view_filename = $raw_name;
            $is_visio = hds_designs_is_visio_ext($ext);
            $sibling_drawio = $is_visio ? hds_designs_drawio_sibling($raw_name) : '';
            $has_drawio_sibling = ($sibling_drawio !== '' && isset($all_names[$sibling_drawio]));

            if ($ext === 'pdf' && strpos($raw_name, '_') !== false) {
                $note = ' (from XPS)';
            }
            if ($is_visio && $has_drawio_sibling) {
                $badge = '<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;background:#dbeafe;color:#1e40af;font-size:0.75em;">Visio original</span>';
                // Primary View/Open target the converted draw.io (multi-page preserved).
                $view_filename = $sibling_drawio;
            } elseif ($is_visio) {
                $badge = '<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;background:#fef3c7;color:#92400e;font-size:0.75em;">Visio (no draw.io yet)</span>';
            } elseif (($ext === 'drawio' || $ext === 'xml')) {
                $visio_guess = preg_replace('/\.drawio$/i', '.vsdx', $raw_name);
                $visio_guess2 = preg_replace('/\.drawio$/i', '.vsd', $raw_name);
                $visio_guess3 = preg_replace('/\.drawio$/i', '.vsdm', $raw_name);
                if (isset($all_names[$visio_guess]) || isset($all_names[$visio_guess2]) || isset($all_names[$visio_guess3])) {
                    $badge = '<span style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:4px;background:#ccfbf1;color:#0f766e;font-size:0.75em;">Editable draw.io</span>';
                    $note = ' (from Visio)';
                }
            }

            $full_path = "uploads/designs/" . $raw_name;
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
            } else if ($ext === 'drawio' || $ext === 'xml') {
                $icon = 'fa-diagram-project';
                $icon_color = '#0d9488';
            } else if ($ext === 'zip') {
                $icon = 'fa-file-zipper';
                $icon_color = '#ea580c';
            }

            $preview = '<div style="height:140px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;border-radius:6px;font-weight:bold;color:#6c757d;border:1px solid #dee2e6;">.' . strtoupper(htmlspecialchars($ext, ENT_QUOTES, 'UTF-8')) . '</div>';

            echo "<div class='photo-item' style='text-align:center;'>";
            echo $preview;
            echo "<p style='margin:8px 0; font-size:0.95em;'>";
            echo "<i class='fa-solid $icon' style='color:$icon_color; margin-right:6px; font-size:1.2em;'></i>";
            echo "<a href='uploads/designs/$filename' target='_blank' download>$filename$note</a>$badge</p>";
            echo "<p style='font-size:0.85em; color:#666;'>$size_str • Uploaded: " . date('M j, Y g:i A', strtotime($file['upload_date'])) . "</p>";

            echo "<div class='design-item-actions' style='display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:10px;'>";

            // Prefer draw.io sibling for View when Visio was converted; Visio itself stays downloadable.
            $view_ext = strtolower(pathinfo($view_filename, PATHINFO_EXTENSION));
            if (in_array($view_ext, $viewable_exts, true)) {
                $file_url = $designs_file_endpoint . '?f=' . rawurlencode($view_filename);
                $title_enc = rawurlencode($view_filename);
                // Modal: lean UI (not lightbox — lightbox hides page tabs). Keep pages=1.
                // Do not set sidebar=0/windows=0 here either; those remove panel parents and
                // Diagram → Shapes can throw appendChild on null parentNode.
                $viewer_url = $drawio_base . '/?ui=min&splash=0&nav=1&layers=1&pages=1&title=' . $title_enc
                    . '#U' . rawurlencode($file_url);
                // Open in tab: full kennedy chrome so Shapes / Diagram menus work; pages still on.
                $tab_url = $drawio_base . '/?ui=kennedy&splash=0&nav=1&layers=1&pages=1&title=' . $title_enc
                    . '#U' . rawurlencode($file_url);
                $viewer_url_attr = htmlspecialchars($viewer_url, ENT_QUOTES, 'UTF-8');
                $tab_url_attr = htmlspecialchars($tab_url, ENT_QUOTES, 'UTF-8');
                $title_attr = htmlspecialchars($view_filename, ENT_QUOTES, 'UTF-8');
                $view_label = ($is_visio && $has_drawio_sibling) ? 'View draw.io' : 'View';
                echo "<button type='button' class='small-btn design-view-open' data-viewer-url=\"$viewer_url_attr\" data-tab-url=\"$tab_url_attr\" data-title=\"$title_attr\">$view_label</button>";
                // Optional: Visio-native view when a draw.io sibling is primary
                if ($is_visio && $has_drawio_sibling) {
                    $v_url = $designs_file_endpoint . '?f=' . rawurlencode($raw_name);
                    $v_title = rawurlencode($raw_name);
                    $v_viewer = $drawio_base . '/?ui=min&splash=0&nav=1&layers=1&pages=1&title=' . $v_title
                        . '#U' . rawurlencode($v_url);
                    $v_tab = $drawio_base . '/?ui=kennedy&splash=0&nav=1&layers=1&pages=1&title=' . $v_title
                        . '#U' . rawurlencode($v_url);
                    echo "<button type='button' class='small-btn design-view-open' data-viewer-url=\""
                        . htmlspecialchars($v_viewer, ENT_QUOTES, 'UTF-8') . "\" data-tab-url=\""
                        . htmlspecialchars($v_tab, ENT_QUOTES, 'UTF-8') . "\" data-title=\""
                        . htmlspecialchars($raw_name, ENT_QUOTES, 'UTF-8') . "\">View Visio</button>";
                }
            }

            echo "<form method='post' style='margin:0;' onsubmit='return confirm(\"Delete $filename permanently? This cannot be undone.\");'>";
            echo "<input type='hidden' name='design_id' value='{$file['id']}'>";
            echo "<input type='submit' name='delete_design' value='Delete' class='delete-btn'>";
            echo "</form>";
            echo "</div>";

            echo "</div>";
        }
        echo "</div>";

        // Newest PDF Preview - BELOW the list
        $newest_pdf_query = "SELECT filename FROM designs WHERE house_id = $house_id AND filename LIKE '%.pdf' ORDER BY upload_date DESC LIMIT 1";
        $newest_pdf = $conn->query($newest_pdf_query)->fetch_assoc();

        if ($newest_pdf) {
            $pdf_url = "uploads/designs/" . htmlspecialchars($newest_pdf['filename'], ENT_QUOTES, 'UTF-8');
            echo "<h3>Newest PDF Preview</h3>";
            echo "<iframe src='$pdf_url' class='pdf-preview' title='Newest PDF Preview'></iframe>";
        } else {
            echo "<p style='color:#777; font-style:italic; margin-top:20px;'>No PDF files uploaded yet.</p>";
        }
    }
    ?>
</div>

<!-- draw.io in-frame viewer modal -->
<div id="designViewerModal" class="design-viewer-modal" hidden aria-hidden="true">
    <div class="design-viewer-backdrop" data-design-viewer-close></div>
    <div class="design-viewer-dialog" role="dialog" aria-modal="true" aria-label="Design viewer">
        <div class="design-viewer-toolbar">
            <span class="design-viewer-title" id="designViewerTitle">Design</span>
            <div class="design-viewer-toolbar-actions">
                <a id="designViewerOpenTab" class="small-btn" href="#" target="_blank" rel="noopener">Open in tab</a>
                <button type="button" class="design-viewer-close" data-design-viewer-close aria-label="Close">&times;</button>
            </div>
        </div>
        <iframe id="designViewerFrame" class="design-viewer-frame" title="draw.io diagram viewer" allowfullscreen></iframe>
    </div>
</div>
<?php endif; ?>
