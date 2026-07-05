<?php
// tabs/household.php — Household Items tab content

global $conn, $house_id;

if (!function_exists('hds_household_type_label')) {
    function hds_household_type_label(string $type): string
    {
        return $type === 'Internet' ? 'Internet Service' : $type;
    }
}
?>

<h2>Household Items</h2>

<div class="section-card">
    <h3>Add New Item</h3>
    <form method="post" class="household-form" data-household-form>
        <div class="household-form-row">
            <label class="household-field">
                <span class="household-label">Type</span>
                <select name="type" data-household-type>
                    <option value="TV">TV</option>
                    <option value="Server">Server</option>
                    <option value="Internet">Internet Service</option>
                    <option value="Other">Other</option>
                </select>
            </label>
        </div>

        <div class="household-standard-fields" data-household-standard>
            <div class="household-form-grid">
                <label class="household-field">
                    <span class="household-label">Brand</span>
                    <input type="text" name="brand">
                </label>
                <label class="household-field">
                    <span class="household-label">Model</span>
                    <input type="text" name="model">
                </label>
                <label class="household-field">
                    <span class="household-label">SN</span>
                    <input type="text" name="sn">
                </label>
            </div>
        </div>

        <div class="household-internet-fields" data-household-internet hidden>
            <div class="household-form-grid">
                <label class="household-field">
                    <span class="household-label">Company</span>
                    <input type="text" name="household_isp_company" placeholder="ISP or provider name">
                </label>
                <label class="household-field">
                    <span class="household-label">Modem SN/PN</span>
                    <input type="text" name="household_modem_sn_pn" placeholder="Serial or part number">
                </label>
                <label class="household-field">
                    <span class="household-label">Expected speeds</span>
                    <input type="text" name="household_expected_speeds" placeholder="e.g. 500 Mbps down / 50 Mbps up">
                </label>
            </div>
        </div>

        <label class="household-field household-field--full">
            <span class="household-label">Notes</span>
            <textarea name="notes" rows="3"></textarea>
        </label>

        <input type="submit" name="add_household" value="Add Item" class="small-btn">
    </form>
</div>

<?php
$sql = "SELECT * FROM household_items WHERE house_id = $house_id ORDER BY id DESC";
$items = $conn->query($sql);
if ($items->num_rows > 0) {
    while ($item = $items->fetch_assoc()) {
        $item_id = (int)$item['id'];
        $raw_type = $item['type'] ?? 'TV';
        $is_internet = $raw_type === 'Internet';
        $item_type = htmlspecialchars(hds_household_type_label($raw_type), ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8');
        $model = htmlspecialchars($item['model'] ?? '', ENT_QUOTES, 'UTF-8');
        $sn = htmlspecialchars($item['sn'] ?? '', ENT_QUOTES, 'UTF-8');
        $isp_company = htmlspecialchars($item['isp_company'] ?? '', ENT_QUOTES, 'UTF-8');
        $modem_sn_pn = htmlspecialchars($item['modem_sn_pn'] ?? '', ENT_QUOTES, 'UTF-8');
        $expected_speeds = htmlspecialchars($item['expected_speeds'] ?? '', ENT_QUOTES, 'UTF-8');
        $notes = htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8');
        $has_notes = trim($item['notes'] ?? '') !== '';

        echo "<div class='section-card household-item-card'>";
        echo "<h3>$item_type #$item_id</h3>";
        echo "<div data-view-edit class='hds-ve-block'>";
        echo "<div data-view-edit-view>";
        echo "<div class='hds-ve-header'>";
        echo "<div class='hds-ve-actions'>";
        echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
        echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this $item_type?\");'>";
        echo "<input type='hidden' name='item_id' value='$item_id'>";
        echo "<input type='submit' name='delete_household' value='Delete' class='small-btn delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "<div class='hds-ve-body household-item-details'>";
        if ($is_internet) {
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Company:</span> " . hds_ve_display($item['isp_company'] ?? '') . "</p>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Modem SN/PN:</span> " . hds_ve_display($item['modem_sn_pn'] ?? '') . "</p>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Expected speeds:</span> " . hds_ve_display($item['expected_speeds'] ?? '') . "</p>";
        } else {
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Brand:</span> " . hds_ve_display($item['brand'] ?? '') . "</p>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Model:</span> " . hds_ve_display($item['model'] ?? '') . "</p>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>SN:</span> " . hds_ve_display($item['sn'] ?? '') . "</p>";
        }
        if ($has_notes) {
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> " . nl2br(hds_ve_display($item['notes'] ?? '')) . "</p>";
        }
        echo "</div>";
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post' class='household-form' data-household-form data-household-type-fixed='$raw_type'>";
        echo "<input type='hidden' name='item_id' value='$item_id'>";
        echo "<input type='hidden' name='household_item_type' value='" . htmlspecialchars($raw_type, ENT_QUOTES, 'UTF-8') . "'>";

        if ($is_internet) {
            echo "<div class='household-internet-fields' data-household-internet>";
            echo "<div class='household-form-grid'>";
            echo "<label class='household-field'><span class='household-label'>Company</span><input type='text' name='household_isp_company' value=\"$isp_company\"></label>";
            echo "<label class='household-field'><span class='household-label'>Modem SN/PN</span><input type='text' name='household_modem_sn_pn' value=\"$modem_sn_pn\"></label>";
            echo "<label class='household-field'><span class='household-label'>Expected speeds</span><input type='text' name='household_expected_speeds' value=\"$expected_speeds\"></label>";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='household-standard-fields' data-household-standard>";
            echo "<div class='household-form-grid'>";
            echo "<label class='household-field'><span class='household-label'>Brand</span><input type='text' name='brand' value=\"$brand\"></label>";
            echo "<label class='household-field'><span class='household-label'>Model</span><input type='text' name='model' value=\"$model\"></label>";
            echo "<label class='household-field'><span class='household-label'>SN</span><input type='text' name='sn' value=\"$sn\"></label>";
            echo "</div>";
            echo "</div>";
        }

        echo "<label class='household-field household-field--full'><span class='household-label'>Notes</span><textarea name='notes' rows='4'>$notes</textarea></label>";
        echo "<div class='hds-ve-edit-actions'>";
        echo "<input type='submit' name='update_household' value='Save'>";
        echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
        echo "</div>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<p class='empty-note'>No household items added yet.</p>";
}
?>