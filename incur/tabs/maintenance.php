<?php
// tabs/maintenance.php — Equipment fluids, parts, and maintenance logs (per house)

global $conn, $house_id;

$categories = [
    'atv'        => 'ATV',
    'boat'       => 'Boat',
    'lawnmower'  => 'Lawnmower',
    'other'      => 'Other',
];
?>

<h2>Maintenance</h2>
<p class="maintenance-intro">Document fluids, filter/part numbers, and service history for ATVs, boats, lawnmowers, and other equipment at this house.</p>

<div class="section-card">
    <h3>Add Equipment</h3>
    <form method="post" class="maintenance-form">
        <label>Equipment Name:</label><br>
        <input type="text" name="equipment_name" placeholder="e.g. Honda Rancher, Pontoon boat, Toro mower" required style="width:100%;"><br><br>

        <label>Type:</label><br>
        <select name="equipment_category" required>
            <?php foreach ($categories as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Notes:</label><br>
        <textarea name="equipment_notes" rows="2" style="width:100%;" placeholder="Year, model, engine size, location, etc."></textarea><br><br>

        <input type="submit" name="add_equipment" value="Add Equipment">
    </form>
</div>

<?php
$equipment_list = $conn->query("SELECT * FROM maintenance_equipment WHERE house_id = $house_id ORDER BY category ASC, name ASC");

if ($equipment_list && $equipment_list->num_rows > 0) {
    $open_equipment_id = intval($_GET['open_equipment'] ?? 0);
    echo "<div class='collapsible-list-toolbar'>";
    echo "<button type='button' class='small-btn' onclick='maintenanceExpandAll(true)'>Expand all</button>";
    echo "<button type='button' class='small-btn' onclick='maintenanceExpandAll(false)'>Collapse all</button>";
    echo "</div>";
    echo "<div class='maintenance-list'>";
    while ($equipment = $equipment_list->fetch_assoc()) {
        $equipment_id = (int)$equipment['id'];
        $eq_name = htmlspecialchars($equipment['name'], ENT_QUOTES, 'UTF-8');
        $eq_category = $equipment['category'] ?? 'other';
        $eq_cat_label = htmlspecialchars($categories[$eq_category] ?? 'Other', ENT_QUOTES, 'UTF-8');
        $eq_notes = htmlspecialchars($equipment['notes'] ?? '', ENT_QUOTES, 'UTF-8');

        $is_open = ($equipment_id === $open_equipment_id) ? ' open' : '';
        echo "<details class='section-card maintenance-equipment-card collapsible-section' id='maintenance-eq-$equipment_id'$is_open>";
        echo "<summary class='collapsible-summary'>";
        echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
        echo "<span class='collapsible-summary-title'>$eq_name</span>";
        echo "<span class='maintenance-category-badge maintenance-category-badge--$eq_category'>$eq_cat_label</span>";
        echo "</summary>";
        echo "<div class='collapsible-body'>";

        $has_eq_notes = trim($equipment['notes'] ?? '') !== '';
        echo "<div data-view-edit class='hds-ve-block maintenance-equipment-details'>";
        echo "<div data-view-edit-view>";
        echo "<div class='hds-ve-header'>";
        echo "<div class='hds-ve-actions'>";
        echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
        echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this equipment and all its fluids, parts, and log entries?\");'>";
        echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
        echo "<input type='submit' name='delete_equipment' value='Delete Equipment' class='small-btn delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "<div class='hds-ve-body'>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Equipment Name:</span> $eq_name</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Type:</span> <span class='maintenance-category-badge maintenance-category-badge--$eq_category'>$eq_cat_label</span></p>";
        if ($has_eq_notes) {
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> $eq_notes</p>";
        }
        echo "</div>";
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post' class='maintenance-form maintenance-equipment-form'>";
        echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
        echo "<label>Equipment Name:</label><br>";
        echo "<input type='text' name='equipment_name' value=\"$eq_name\" required style='width:100%;'><br><br>";
        echo "<label>Type:</label><br>";
        echo "<select name='equipment_category' required>";
        foreach ($categories as $value => $label) {
            $selected = $eq_category === $value ? ' selected' : '';
            echo "<option value='$value'$selected>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
        }
        echo "</select><br><br>";
        echo "<label>Notes:</label><br>";
        echo "<textarea name='equipment_notes' rows='2' style='width:100%;'>$eq_notes</textarea><br><br>";
        echo "<div class='hds-ve-edit-actions'>";
        echo "<input type='submit' name='update_equipment' value='Save Equipment'>";
        echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
        echo "</div>";
        echo "</form>";
        echo "</div>";
        echo "</div>";

        // --- Fluids ---
        echo "<div class='maintenance-subsection'>";
        echo "<h4><i class='fas fa-oil-can' aria-hidden='true'></i> Fluids</h4>";
        $fluids = $conn->query("SELECT * FROM maintenance_fluids WHERE equipment_id = $equipment_id ORDER BY fluid_name ASC");
        if ($fluids && $fluids->num_rows > 0) {
            while ($fluid = $fluids->fetch_assoc()) {
                $fluid_id = (int)$fluid['id'];
                $fluid_name = htmlspecialchars($fluid['fluid_name'], ENT_QUOTES, 'UTF-8');
                $fluid_spec = htmlspecialchars($fluid['specification'] ?? '', ENT_QUOTES, 'UTF-8');
                $fluid_capacity = htmlspecialchars($fluid['capacity'] ?? '', ENT_QUOTES, 'UTF-8');
                $fluid_notes = htmlspecialchars($fluid['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                echo "<div data-view-edit class='hds-ve-block hds-ve-block--inline'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header'>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this fluid?\");'>";
                echo "<input type='hidden' name='fluid_id' value='$fluid_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<input type='submit' name='delete_fluid' value='Del' class='small-btn delete-btn'>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                echo "<div class='hds-ve-body hds-ve-inline-grid'>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Fluid:</span> " . hds_ve_display($fluid['fluid_name']) . "</p>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Spec:</span> " . hds_ve_display($fluid['specification'] ?? '') . "</p>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Capacity:</span> " . hds_ve_display($fluid['capacity'] ?? '') . "</p>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> " . hds_ve_display($fluid['notes'] ?? '') . "</p>";
                echo "</div>";
                echo "</div>";
                echo "<div data-view-edit-form hidden>";
                echo "<form method='post' class='maintenance-item-row'>";
                echo "<input type='hidden' name='fluid_id' value='$fluid_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<input type='text' name='fluid_name' value=\"$fluid_name\" placeholder='Fluid' required>";
                echo "<input type='text' name='fluid_spec' value=\"$fluid_spec\" placeholder='Specification'>";
                echo "<input type='text' name='fluid_capacity' value=\"$fluid_capacity\" placeholder='Capacity'>";
                echo "<input type='text' name='fluid_notes' value=\"$fluid_notes\" placeholder='Notes'>";
                echo "<div class='hds-ve-edit-actions' style='grid-column:1/-1;'>";
                echo "<input type='submit' name='update_fluid' value='Save' class='small-btn'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p class='empty-note'>No fluids recorded yet.</p>";
        }
        echo "<form method='post' class='maintenance-inline-add'>";
        echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
        echo "<input type='text' name='fluid_name' placeholder='Fluid (e.g. Engine oil)' required>";
        echo "<input type='text' name='fluid_spec' placeholder='Spec (e.g. 5W-30)'>";
        echo "<input type='text' name='fluid_capacity' placeholder='Capacity (e.g. 2.5 qt)'>";
        echo "<input type='text' name='fluid_notes' placeholder='Notes'>";
        echo "<input type='submit' name='add_fluid' value='Add Fluid'>";
        echo "</form>";
        echo "</div>";

        // --- Parts ---
        echo "<div class='maintenance-subsection'>";
        echo "<h4><i class='fas fa-filter' aria-hidden='true'></i> Parts &amp; Filters</h4>";
        $parts = $conn->query("SELECT * FROM maintenance_parts WHERE equipment_id = $equipment_id ORDER BY part_name ASC");
        if ($parts && $parts->num_rows > 0) {
            while ($part = $parts->fetch_assoc()) {
                $part_id = (int)$part['id'];
                $part_name = htmlspecialchars($part['part_name'], ENT_QUOTES, 'UTF-8');
                $part_number = htmlspecialchars($part['part_number'] ?? '', ENT_QUOTES, 'UTF-8');
                $part_notes = htmlspecialchars($part['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                echo "<div data-view-edit class='hds-ve-block hds-ve-block--inline'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header'>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this part?\");'>";
                echo "<input type='hidden' name='part_id' value='$part_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<input type='submit' name='delete_part' value='Del' class='small-btn delete-btn'>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                echo "<div class='hds-ve-body hds-ve-inline-grid'>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Part:</span> " . hds_ve_display($part['part_name']) . "</p>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Part #:</span> " . hds_ve_display($part['part_number'] ?? '') . "</p>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> " . hds_ve_display($part['notes'] ?? '') . "</p>";
                echo "</div>";
                echo "</div>";
                echo "<div data-view-edit-form hidden>";
                echo "<form method='post' class='maintenance-item-row maintenance-item-row--parts'>";
                echo "<input type='hidden' name='part_id' value='$part_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<input type='text' name='part_name' value=\"$part_name\" placeholder='Part' required>";
                echo "<input type='text' name='part_number' value=\"$part_number\" placeholder='Part number'>";
                echo "<input type='text' name='part_notes' value=\"$part_notes\" placeholder='Notes'>";
                echo "<div class='hds-ve-edit-actions' style='grid-column:1/-1;'>";
                echo "<input type='submit' name='update_part' value='Save' class='small-btn'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p class='empty-note'>No parts recorded yet.</p>";
        }
        echo "<form method='post' class='maintenance-inline-add maintenance-inline-add--parts'>";
        echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
        echo "<input type='text' name='part_name' placeholder='Part (e.g. Oil filter)' required>";
        echo "<input type='text' name='part_number' placeholder='Part number'>";
        echo "<input type='text' name='part_notes' placeholder='Notes'>";
        echo "<input type='submit' name='add_part' value='Add Part'>";
        echo "</form>";
        echo "</div>";

        // --- Maintenance log ---
        echo "<div class='maintenance-subsection'>";
        echo "<h4><i class='fas fa-clipboard-list' aria-hidden='true'></i> Maintenance Log</h4>";
        $logs = $conn->query("SELECT * FROM maintenance_log WHERE equipment_id = $equipment_id ORDER BY log_date DESC, id DESC");
        if ($logs && $logs->num_rows > 0) {
            while ($log = $logs->fetch_assoc()) {
                $log_id = (int)$log['id'];
                $log_date_raw = $log['log_date'];
                $log_date_display = date('M j, Y', strtotime($log_date_raw));
                $log_hours = htmlspecialchars($log['hours_mileage'] ?? '', ENT_QUOTES, 'UTF-8');
                $log_desc = htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8');
                $log_notes = htmlspecialchars($log['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                $has_notes = trim($log['notes'] ?? '') !== '';

                echo "<div data-view-edit class='hds-ve-block hds-ve-block--card maintenance-log-entry'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header hds-ve-header--split'>";
                echo "<div class='maintenance-log-view-meta'>";
                echo "<strong class='maintenance-log-view-date'>$log_date_display</strong>";
                if ($log_hours !== '') {
                    echo "<span class='maintenance-log-view-hours'>Hours / Mileage: $log_hours</span>";
                }
                echo "</div>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this log entry?\");'>";
                echo "<input type='hidden' name='log_id' value='$log_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<input type='submit' name='delete_log' value='Delete' class='small-btn delete-btn'>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                echo "<div class='hds-ve-body'>";
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Work performed:</span> $log_desc</p>";
                if ($has_notes) {
                    echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> $log_notes</p>";
                }
                echo "</div>";
                echo "</div>";

                echo "<div data-view-edit-form hidden>";
                echo "<form method='post' class='maintenance-form'>";
                echo "<input type='hidden' name='log_id' value='$log_id'>";
                echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
                echo "<label>Date:</label> <input type='date' name='log_date' value=\"" . htmlspecialchars($log_date_raw, ENT_QUOTES, 'UTF-8') . "\" required><br><br>";
                echo "<label>Hours / Mileage:</label> <input type='text' name='log_hours' value=\"$log_hours\" placeholder='Optional'><br><br>";
                echo "<label>Work performed:</label><br>";
                echo "<textarea name='log_description' rows='2' style='width:100%;' required>$log_desc</textarea><br><br>";
                echo "<label>Notes:</label><br>";
                echo "<textarea name='log_notes' rows='2' style='width:100%;'>$log_notes</textarea><br><br>";
                echo "<div class='hds-ve-edit-actions'>";
                echo "<input type='submit' name='update_log' value='Save Log Entry'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<p class='empty-note'>No maintenance log entries yet.</p>";
        }
        echo "<form method='post' class='maintenance-form maintenance-log-add'>";
        echo "<input type='hidden' name='equipment_id' value='$equipment_id'>";
        echo "<strong>Add log entry:</strong><br><br>";
        echo "<label>Date:</label> <input type='date' name='log_date' value=\"" . date('Y-m-d') . "\" required><br><br>";
        echo "<label>Hours / Mileage:</label> <input type='text' name='log_hours' placeholder='Optional'><br><br>";
        echo "<label>Work performed:</label><br>";
        echo "<textarea name='log_description' rows='2' style='width:100%;' placeholder='Oil change, impeller replaced, winterized, etc.' required></textarea><br><br>";
        echo "<label>Notes:</label><br>";
        echo "<textarea name='log_notes' rows='2' style='width:100%;'></textarea><br><br>";
        echo "<input type='submit' name='add_log' value='Add Log Entry'>";
        echo "</form>";
        echo "</div>";

        echo "</div>"; // collapsible-body
        echo "</details>";
    }
    echo "</div>";
} else {
    echo "<p class='empty-note'>No equipment added yet. Use the form above to start documenting fluids, parts, and maintenance.</p>";
}
?>