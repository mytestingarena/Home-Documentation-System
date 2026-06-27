<?php
// tabs/contractors.php — Contractor list (per house)

global $conn, $house_id;
?>

<h2>Contractors</h2>
<p class="contractors-intro">Keep a list of plumbers, electricians, HVAC techs, and other contractors you use for this property.</p>

<div class="section-card">
    <h3>Add Contractor</h3>
    <form method="post" class="contractor-add-form">
        <label>Name:</label>
        <input type="text" name="contractor_name" placeholder="Company or contact name" required>
        <label>What they do:</label>
        <input type="text" name="contractor_trade" placeholder="e.g. Plumber, Electrician, Tree removal">
        <label>Phone:</label>
        <input type="text" name="contractor_phone" placeholder="e.g. 608-555-1234">
        <label>City:</label>
        <input type="text" name="contractor_city" placeholder="e.g. Madison">
        <input type="submit" name="add_contractor" value="Add Contractor">
    </form>
</div>

<?php
$contractors = $conn->query("SELECT * FROM contractors WHERE house_id = $house_id ORDER BY name ASC, id ASC");
if ($contractors && $contractors->num_rows > 0) {
    echo "<div class='contractors-list'>";
    while ($row = $contractors->fetch_assoc()) {
        $contractor_id = (int)$row['id'];
        $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $trade = htmlspecialchars($row['trade'] ?? '', ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $city = htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8');
        $title = $name !== '' ? $name : 'Contractor #' . $contractor_id;

        echo "<div class='section-card contractor-card'>";
        echo "<div data-view-edit class='hds-ve-block'>";
        echo "<div data-view-edit-view>";
        echo "<div class='hds-ve-header hds-ve-header--split'>";
        echo "<strong class='contractor-card-title'>$title</strong>";
        echo "<div class='hds-ve-actions'>";
        echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
        echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this contractor?\");'>";
        echo "<input type='hidden' name='contractor_id' value='$contractor_id'>";
        echo "<input type='submit' name='delete_contractor' value='Delete' class='small-btn delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "<div class='hds-ve-body'>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>What they do:</span> " . hds_ve_display($row['trade'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Phone:</span> " . hds_ve_display($row['phone'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>City:</span> " . hds_ve_display($row['city'] ?? '') . "</p>";
        echo "</div>";
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='contractor_id' value='$contractor_id'>";
        echo "<label>Name:</label><br>";
        echo "<input type='text' name='contractor_name' value=\"$name\" required style='width:100%;'><br><br>";
        echo "<label>What they do:</label><br>";
        echo "<input type='text' name='contractor_trade' value=\"$trade\" style='width:100%;'><br><br>";
        echo "<label>Phone:</label><br>";
        echo "<input type='text' name='contractor_phone' value=\"$phone\" style='width:100%;'><br><br>";
        echo "<label>City:</label><br>";
        echo "<input type='text' name='contractor_city' value=\"$city\" style='width:100%;'><br><br>";
        echo "<div class='hds-ve-edit-actions'>";
        echo "<input type='submit' name='update_contractor' value='Save'>";
        echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
        echo "</div>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    echo "</div>";
} else {
    echo "<p class='empty-note'>No contractors added yet.</p>";
}
?>