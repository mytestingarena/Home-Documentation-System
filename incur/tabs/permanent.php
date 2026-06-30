<?php
// tabs/permanent.php — Permanent Items tab (shows latest data)

global $conn, $house_id, $hds_ui_settings;

require_once __DIR__ . '/../includes/permanent-maintenance-log.php';
?>

<h2>Permanent Items</h2>

<?php if (isset($_GET['saved'])): ?>
    <div style="padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:4px;">
        <?php if ($_GET['saved'] == '1'): ?>
            <strong style="color:green;">Permanent Items updated successfully!</strong>
        <?php else: ?>
            <strong style="color:red;">Update failed - check error log.</strong>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
if (!empty($_SESSION['outdoor_photo_success'])) {
    echo "<p class='media-success'>" . htmlspecialchars($_SESSION['outdoor_photo_success'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['outdoor_photo_success']);
}
if (!empty($_SESSION['outdoor_photo_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['outdoor_photo_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['outdoor_photo_error']);
}
if (!empty($_SESSION['house_photo_success'])) {
    echo "<p class='media-success'>" . htmlspecialchars($_SESSION['house_photo_success'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['house_photo_success']);
}
if (!empty($_SESSION['house_photo_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['house_photo_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['house_photo_error']);
}
?>

<div class="collapsible-list-toolbar">
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.permanent-items-list .collapsible-section', true)">Expand all</button>
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.permanent-items-list .collapsible-section', false)">Collapse all</button>
</div>

<div class="permanent-items-list">
<?php
$open_permanent_id = preg_replace('/[^a-z_]/', '', $_GET['open_permanent'] ?? '');
$item_types = ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac'];
foreach ($item_types as $type) {
    if (!hds_ui_section_enabled('permanent-' . $type, $hds_ui_settings)) {
        continue;
    }
    $sql = "SELECT * FROM permanent_items WHERE house_id = $house_id AND item_type = '$type' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc() ?? [];

    $brand = $row['brand'] ?? '';
    $model = $row['model'] ?? '';
    $sn = $row['sn'] ?? '';
    $efficiency = $row['efficiency'] ?? '';
    $kwh = $row['kwh'] ?? 0;
    $capacity = $row['capacity'] ?? 0;
    $has_capacity = in_array($type, ['water_heater', 'dishwasher'], true);

    $display_name = $type === 'ac' ? 'Air Conditioner' : ucfirst(str_replace('_', ' ', $type));
    $is_open = ($type === $open_permanent_id) ? ' open' : '';

    echo "<details class='section-card permanent-item-card collapsible-section' id='permanent-$type'$is_open>";
    echo "<summary class='collapsible-summary'>";
    echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
    echo "<span class='collapsible-summary-title'>" . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "</summary>";
    echo "<div class='collapsible-body'>";

    echo "<div data-view-edit class='hds-ve-block'>";
    echo "<div data-view-edit-view>";
    echo "<div class='hds-ve-header'>";
    echo "<div class='hds-ve-actions'>";
    echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
    echo "</div>";
    echo "</div>";
    echo "<div class='hds-ve-body'>";
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>Brand:</span> " . hds_ve_display($brand) . "</p>";
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>Model:</span> " . hds_ve_display($model) . "</p>";
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>SN:</span> " . hds_ve_display($sn) . "</p>";
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>Efficiency:</span> " . hds_ve_display($efficiency) . "</p>";
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>KWH:</span> " . hds_ve_display((string)$kwh) . "</p>";
    if ($has_capacity) {
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Capacity:</span> " . hds_ve_display((string)$capacity) . "</p>";
    }
    echo "</div>";
    echo "</div>";

    echo "<div data-view-edit-form hidden>";
    echo "<form method='post' class='permanent-item-form'>";
    echo "<input type='hidden' name='item_type' value='$type'>";
    echo "<label>Brand:</label><br>";
    echo "<input type='text' name='{$type}_brand' value='" . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    echo "<label>Model:</label><br>";
    echo "<input type='text' name='{$type}_model' value='" . htmlspecialchars($model, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    echo "<label>SN:</label><br>";
    echo "<input type='text' name='{$type}_sn' value='" . htmlspecialchars($sn, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    echo "<label>Efficiency:</label><br>";
    echo "<input type='text' name='{$type}_efficiency' value='" . htmlspecialchars($efficiency, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    echo "<label>KWH:</label><br>";
    echo "<input type='number' step='0.01' name='{$type}_kwh' value='" . htmlspecialchars((string)$kwh, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    if ($has_capacity) {
        echo "<label>Capacity:</label><br>";
        echo "<input type='number' name='{$type}_capacity' value='" . htmlspecialchars((string)$capacity, ENT_QUOTES, 'UTF-8') . "'><br><br>";
    }
    echo "<div class='hds-ve-edit-actions'>";
    echo "<input type='submit' name='update_permanent_item' value='Save'>";
    echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
    echo "</div>";
    echo "</form>";
    echo "</div>";
    echo "</div>";

    hds_render_permanent_maintenance_log($conn, $house_id, $type);

    echo "</div>";
    echo "</details>";
}
?>
</div>

<div class="permanent-separator" aria-hidden="true"></div>

<?php
if (hds_ui_section_enabled('permanent-outdoor_work', $hds_ui_settings)) {
    include __DIR__ . '/outdoor-work.php';
}

if (hds_ui_section_enabled('permanent-house_work', $hds_ui_settings)) {
    include __DIR__ . '/house-work.php';
}

if (hds_ui_section_enabled('permanent-breakers', $hds_ui_settings)) {
    include __DIR__ . '/breakers.php';
}
?>