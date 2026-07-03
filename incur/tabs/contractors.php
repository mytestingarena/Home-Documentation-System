<?php
// tabs/contractors.php — Contractor list (per house)

global $conn, $house_id;
?>

<h2>Contractors</h2>
<p class="contractors-intro">Keep a list of plumbers, electricians, HVAC techs, and other contractors you use for this property.</p>

<div class="section-card">
    <h3>Add Contractor</h3>
    <form method="post" class="contractor-add-form">
        <div class="contractor-form-row">
            <div class="contractor-field">
                <label for="contractor_name_new">Name</label>
                <input type="text" id="contractor_name_new" name="contractor_name" placeholder="Company or contact name" required>
            </div>
            <div class="contractor-field">
                <label for="contractor_trade_new">What they do</label>
                <input type="text" id="contractor_trade_new" name="contractor_trade" placeholder="Plumber, Electrician, etc.">
            </div>
            <div class="contractor-field">
                <label for="contractor_phone_new">Phone</label>
                <input type="text" id="contractor_phone_new" name="contractor_phone" placeholder="608-555-1234">
            </div>
            <div class="contractor-field">
                <label for="contractor_city_new">City</label>
                <input type="text" id="contractor_city_new" name="contractor_city" placeholder="Madison">
            </div>
            <div class="contractor-field contractor-field--submit">
                <input type="submit" name="add_contractor" value="Add Contractor" class="small-btn">
            </div>
        </div>
        <div class="contractor-form-extras">
            <label class="contractor-checkbox">
                <input type="checkbox" id="contractor_grok_new" name="contractor_grok_recommendation" value="1">
                Grok recommendation
            </label>
            <div class="contractor-field contractor-field--notes">
                <label for="contractor_notes_new">Notes</label>
                <textarea id="contractor_notes_new" name="contractor_notes" rows="3" placeholder="Why you recommend them, scope of work, etc."></textarea>
            </div>
        </div>
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
        $notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8');
        $grok_recommendation = !empty($row['grok_recommendation']);
        $title = $name !== '' ? $name : 'Contractor #' . $contractor_id;

        echo "<div class='section-card contractor-card'>";
        echo "<div data-view-edit class='hds-ve-block'>";
        echo "<div data-view-edit-view>";
        echo "<div class='hds-ve-header hds-ve-header--split'>";
        echo "<div class='contractor-card-heading'>";
        echo "<strong class='contractor-card-title'>$title</strong>";
        if ($grok_recommendation) {
            echo "<span class='contractor-grok-badge'>Grok recommendation</span>";
        }
        echo "</div>";
        echo "<div class='hds-ve-actions'>";
        echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
        echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this contractor?\");'>";
        echo "<input type='hidden' name='contractor_id' value='$contractor_id'>";
        echo "<input type='submit' name='delete_contractor' value='Delete' class='small-btn delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        echo "<div class='hds-ve-body contractor-details'>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>What they do:</span> " . hds_ve_display($row['trade'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>Phone:</span> " . hds_ve_display($row['phone'] ?? '') . "</p>";
        echo "<p class='hds-ve-field'><span class='hds-ve-label'>City:</span> " . hds_ve_display($row['city'] ?? '') . "</p>";
        if (trim($row['notes'] ?? '') !== '') {
            echo "<p class='hds-ve-field contractor-notes-field'><span class='hds-ve-label'>Notes:</span> " . nl2br(hds_ve_display($row['notes'] ?? '')) . "</p>";
        }
        echo "</div>";
        echo "</div>";

        echo "<div data-view-edit-form hidden>";
        echo "<form method='post' class='contractor-edit-form'>";
        echo "<input type='hidden' name='contractor_id' value='$contractor_id'>";
        echo "<div class='contractor-form-row'>";
        echo "<div class='contractor-field'><label>Name</label><input type='text' name='contractor_name' value=\"$name\" required></div>";
        echo "<div class='contractor-field'><label>What they do</label><input type='text' name='contractor_trade' value=\"$trade\"></div>";
        echo "<div class='contractor-field'><label>Phone</label><input type='text' name='contractor_phone' value=\"$phone\"></div>";
        echo "<div class='contractor-field'><label>City</label><input type='text' name='contractor_city' value=\"$city\"></div>";
        echo "</div>";
        echo "<div class='contractor-form-extras'>";
        $grok_checked = $grok_recommendation ? ' checked' : '';
        echo "<label class='contractor-checkbox'><input type='checkbox' name='contractor_grok_recommendation' value='1'$grok_checked> Grok recommendation</label>";
        echo "<div class='contractor-field contractor-field--notes'><label>Notes</label><textarea name='contractor_notes' rows='3'>$notes</textarea></div>";
        echo "</div>";
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