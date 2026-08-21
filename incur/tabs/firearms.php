<?php
// tabs/firearms.php — Firearm inventory (photos, serial numbers, specs)

global $conn, $house_id;

require_once __DIR__ . '/../includes/firearms.php';

$open_firearm_id = intval($_GET['open_firearm'] ?? 0);
$form_action = 'house.php?id=' . (int)$house_id . '&tab=firearms';
?>

<h2>Firearms</h2>
<p class="homelab-intro">Keep a private inventory of firearms at this house — photos, serial numbers, and specs for insurance and records.</p>

<?php
if (!empty($_SESSION['firearm_photo_success'])) {
    echo "<p class='media-success'>" . htmlspecialchars($_SESSION['firearm_photo_success'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['firearm_photo_success']);
}
if (!empty($_SESSION['firearm_photo_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['firearm_photo_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['firearm_photo_error']);
}
?>

<div class="section-card">
    <form method="post" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>" class="homelab-add-form">
        <h4>Add Firearm</h4>
        <div class="homelab-form-grid">
            <?php
            hds_firearms_form_field('Name / Nickname', 'firearm_name', '', 'e.g. Bedside 9mm, Hunting rifle', true);
            echo "<div class='homelab-field'><label>Type</label>";
            hds_render_firearms_type_select('firearm_type', 'pistol');
            echo "</div>";
            hds_firearms_form_field('Manufacturer', 'firearm_manufacturer', '', 'e.g. Glock, Remington');
            hds_firearms_form_field('Model', 'firearm_model', '', 'e.g. 19 Gen 5, 870 Express');
            hds_firearms_form_field('Caliber / Gauge', 'firearm_caliber', '', 'e.g. 9mm, .223, 12 gauge');
            hds_firearms_form_field('Serial Number', 'firearm_serial', '', 'As stamped on the firearm');
            hds_firearms_form_field('Barrel Length', 'firearm_barrel', '', 'e.g. 4.02 in, 18 in');
            echo "<div class='homelab-field'><label>Action</label>";
            hds_render_firearms_action_select('firearm_action', 'semi_auto');
            echo "</div>";
            hds_firearms_form_field('Finish / Color', 'firearm_finish', '', 'e.g. Black, stainless, FDE');
            hds_firearms_form_field('Purchase Date', 'firearm_purchase_date', '', '', false, 'date');
            hds_firearms_form_field('Purchase Price', 'firearm_purchase_price', '', 'e.g. 549.00', false, 'number');
            hds_firearms_form_field('Storage Location', 'firearm_storage', '', 'e.g. Bedroom safe, gun cabinet');
            hds_firearms_form_textarea('Notes', 'firearm_notes', '', 'Optics, accessories, FFL, insurance policy, etc.');
            ?>
            <div class="homelab-field homelab-field--submit">
                <input type="submit" name="add_firearm" value="Add Firearm" class="small-btn">
            </div>
        </div>
    </form>
</div>

<?php
$firearms = @$conn->query("SELECT * FROM firearms WHERE house_id = $house_id ORDER BY name ASC, id ASC");
if ($firearms === false) {
    echo "<p class='empty-note'>The firearms tables are not installed yet. On the database host run: <code>mysql -u root -p house_info &lt; db/migrations.sql</code></p>";
} elseif ($firearms->num_rows > 0) {
    echo "<div class='collapsible-list-toolbar'>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.firearms-list .collapsible-section', true)\">Expand all</button>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.firearms-list .collapsible-section', false)\">Collapse all</button>";
    echo "</div>";
    echo "<div class='firearms-list'>";
    while ($row = $firearms->fetch_assoc()) {
        $item_id = (int)$row['id'];
        $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $type = $row['firearm_type'] ?? 'other';
        $type_label = htmlspecialchars(hds_firearms_type_label($type), ENT_QUOTES, 'UTF-8');
        $action = $row['action_type'] ?? '';
        $action_label = $action !== '' ? hds_firearms_action_label($action) : '';
        $purchase_date = $row['purchase_date'] ?? '';
        $purchase_price = $row['purchase_price'] ?? null;
        $is_open = ($item_id === $open_firearm_id) ? ' open' : '';
        $summary_bits = array_filter([
            $row['manufacturer'] ?? '',
            $row['model'] ?? '',
            $row['caliber'] ?? '',
        ]);
        $summary_line = htmlspecialchars(implode(' · ', $summary_bits), ENT_QUOTES, 'UTF-8');

        echo "<details class='section-card collapsible-section firearm-card' id='firearm-$item_id'$is_open>";
        echo "<summary class='collapsible-summary'>";
        echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
        echo "<span class='collapsible-summary-title'>$name</span>";
        echo "<span class='tool-type-badge'>$type_label</span>";
        if ($summary_line !== '') {
            echo "<span class='firearm-summary-meta'>$summary_line</span>";
        }
        echo "</summary>";
        echo "<div class='collapsible-body'>";

        echo "<div data-view-edit class='hds-ve-block'>";
        echo "<div data-view-edit-view>";
        echo "<div class='hds-ve-header'>";
        echo "<div class='hds-ve-actions'>";
        echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
        echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this firearm record and its photos?\");'>";
        echo "<input type='hidden' name='firearm_id' value='$item_id'>";
        echo "<input type='submit' name='delete_firearm' value='Delete' class='small-btn delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "<div class='homelab-details'>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Type:</span> $type_label</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Manufacturer:</span> " . hds_ve_display($row['manufacturer'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Model:</span> " . hds_ve_display($row['model'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Caliber / Gauge:</span> " . hds_ve_display($row['caliber'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Serial Number:</span> " . hds_ve_display($row['serial_number'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Barrel Length:</span> " . hds_ve_display($row['barrel_length'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Action:</span> " . hds_ve_display($action_label) . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Finish:</span> " . hds_ve_display($row['finish'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Purchase Date:</span> " . hds_ve_display($purchase_date !== '' && $purchase_date !== null ? date('M j, Y', strtotime($purchase_date)) : '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Purchase Price:</span> " . hds_ve_display($purchase_price !== null && $purchase_price !== '' ? '$' . number_format((float)$purchase_price, 2) : '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Storage:</span> " . hds_ve_display($row['storage_location'] ?? '') . "</p>";
        echo "</div>";
        if (trim($row['notes'] ?? '') !== '') {
            echo "<p class='hds-ve-field' style='margin-top:12px;'><span class='hds-ve-label'>Notes:</span> " . hds_ve_display($row['notes'] ?? '') . "</p>";
        }
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post' action='" . htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') . "' class='homelab-add-form'>";
        echo "<input type='hidden' name='firearm_id' value='$item_id'>";
        echo "<div class='homelab-form-grid'>";
        hds_firearms_form_field('Name / Nickname', 'firearm_name', $row['name'] ?? '', '', true);
        echo "<div class='homelab-field'><label>Type</label>";
        hds_render_firearms_type_select('firearm_type', $type);
        echo "</div>";
        hds_firearms_form_field('Manufacturer', 'firearm_manufacturer', $row['manufacturer'] ?? '');
        hds_firearms_form_field('Model', 'firearm_model', $row['model'] ?? '');
        hds_firearms_form_field('Caliber / Gauge', 'firearm_caliber', $row['caliber'] ?? '');
        hds_firearms_form_field('Serial Number', 'firearm_serial', $row['serial_number'] ?? '');
        hds_firearms_form_field('Barrel Length', 'firearm_barrel', $row['barrel_length'] ?? '');
        echo "<div class='homelab-field'><label>Action</label>";
        hds_render_firearms_action_select('firearm_action', $action);
        echo "</div>";
        hds_firearms_form_field('Finish / Color', 'firearm_finish', $row['finish'] ?? '');
        hds_firearms_form_field('Purchase Date', 'firearm_purchase_date', $purchase_date ?? '', '', false, 'date');
        hds_firearms_form_field('Purchase Price', 'firearm_purchase_price', $purchase_price !== null ? (string)$purchase_price : '', '', false, 'number');
        hds_firearms_form_field('Storage Location', 'firearm_storage', $row['storage_location'] ?? '');
        hds_firearms_form_textarea('Notes', 'firearm_notes', $row['notes'] ?? '');
        echo "<div class='homelab-field homelab-field--submit'>";
        echo "<div class='hds-ve-edit-actions'>";
        echo "<input type='submit' name='update_firearm' value='Save'>";
        echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
        echo "</div></div>";
        echo "</div></form></div>";
        echo "</div>";

        hds_render_firearm_images($conn, $house_id, $item_id);
        echo "</div></details>";
    }
    echo "</div>";
} else {
    echo "<p class='empty-note'>No firearms recorded at this house yet. Add one above.</p>";
}
?>

<div id="firearmRenameModal" class="media-rename-modal" hidden aria-hidden="true">
    <div class="media-rename-backdrop" data-firearm-rename-close></div>
    <div class="media-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="firearmRenameTitle">
        <button type="button" class="media-rename-close" data-firearm-rename-close aria-label="Close">&times;</button>
        <h3 id="firearmRenameTitle">Rename Photo</h3>
        <p class="media-rename-current-row">
            <span class="media-rename-label">Current name:</span>
            <span id="firearmRenameCurrent" class="media-rename-current"></span>
        </p>
        <form method="post" id="firearmRenameForm" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="firearm_image_id" id="firearmRenameImageId" value="">
            <input type="hidden" name="firearm_id" id="firearmRenameFirearmId" value="">
            <label for="firearmRenameNew">New name:</label>
            <div class="media-rename-input-row">
                <input type="text" name="firearm_image_basename" id="firearmRenameNew" required autocomplete="off" placeholder="Enter name without extension">
                <span id="firearmRenameExt" class="media-rename-ext"></span>
            </div>
            <div class="media-rename-actions">
                <button type="button" class="small-btn" data-firearm-rename-close>Cancel</button>
                <input type="submit" name="rename_firearm_image" value="Save" class="media-rename-save">
            </div>
        </form>
    </div>
</div>
