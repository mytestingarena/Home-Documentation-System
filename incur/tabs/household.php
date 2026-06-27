<?php
// tabs/household.php — Household Items tab content

global $conn, $house_id;
?>

<h2>Household Items</h2>

<div class="section-card">
    <h3>Add New Item</h3>
    <form method="post">
        Type: <select name="type">
            <option value="TV">TV</option>
            <option value="Server">Server</option>
            <option value="Other">Other</option>
        </select><br><br>
        Brand: <input type="text" name="brand"><br><br>
        Model: <input type="text" name="model"><br><br>
        SN: <input type="text" name="sn"><br><br>
        Notes: <textarea name="notes" rows="3" style="width:100%;"></textarea><br><br>
        <input type="submit" name="add_household" value="Add Item">
    </form>
</div>

<?php
$sql = "SELECT * FROM household_items WHERE house_id = $house_id ORDER BY id DESC";
$items = $conn->query($sql);
if ($items->num_rows > 0) {
    while ($item = $items->fetch_assoc()) {
        $item_id = (int)$item['id'];
        $item_type = htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars($item['brand'] ?? '', ENT_QUOTES, 'UTF-8');
        $model = htmlspecialchars($item['model'] ?? '', ENT_QUOTES, 'UTF-8');
        $sn = htmlspecialchars($item['sn'] ?? '', ENT_QUOTES, 'UTF-8');
        $notes = htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8');
        $has_notes = trim($item['notes'] ?? '') !== '';

        echo "<div class='section-card' style='margin-top:20px;'>";
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
        echo "<div class='hds-ve-body'>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Brand:</span> " . hds_ve_display($item['brand'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Model:</span> " . hds_ve_display($item['model'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>SN:</span> " . hds_ve_display($item['sn'] ?? '') . "</p>";
        if ($has_notes) {
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> " . hds_ve_display($item['notes'] ?? '') . "</p>";
        }
        echo "</div>";
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='item_id' value='$item_id'>";
        echo "<label>Brand:</label><br>";
        echo "<input type='text' name='brand' value=\"$brand\"><br><br>";
        echo "<label>Model:</label><br>";
        echo "<input type='text' name='model' value=\"$model\"><br><br>";
        echo "<label>SN:</label><br>";
        echo "<input type='text' name='sn' value=\"$sn\"><br><br>";
        echo "<label>Notes:</label><br>";
        echo "<textarea name='notes' rows='4' style='width:100%;'>$notes</textarea><br><br>";
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
    echo "<p style='color:#777;'>No household items added yet.</p>";
}
?>