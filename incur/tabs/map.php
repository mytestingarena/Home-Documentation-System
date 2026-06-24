<?php
// tabs/map.php — Map Location with stored Google embed link

global $conn, $house_id, $hds_ui_settings;
?>

<h2>Map Location</h2>

<?php
// Fetch current data
$house_data = $conn->query("SELECT address, latitude, longitude, tax_number, map_zoom, google_embed_src FROM houses WHERE id = $house_id")->fetch_assoc();
$lat = floatval($house_data['latitude'] ?? 0);
$lng = floatval($house_data['longitude'] ?? 0);
$zoom = intval($house_data['map_zoom'] ?? 17);
$address = htmlspecialchars($house_data['address'] ?? '');
$tax = htmlspecialchars($house_data['tax_number'] ?? '');
$embed_src = htmlspecialchars($house_data['google_embed_src'] ?? '');
?>

<?php if (hds_ui_section_enabled('map-property', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Property Details</h3>
    <form method="post">
        <label>Address:</label><br>
        <textarea name="address" rows="3" style="width:100%;"><?php echo $address; ?></textarea><br><br>

        <label>GPS Coordinates (optional):</label><br>
        <input type="text" name="latitude" placeholder="Latitude (e.g. 42.322785)" value="<?php echo $lat ?: ''; ?>" style="width:48%; margin-right:2%;">
        <input type="text" name="longitude" placeholder="Longitude (e.g. -88.282123)" value="<?php echo $lng ?: ''; ?>" style="width:48%;"><br><br>

        <label>Parcel/Tax ID:</label><br>
        <input type="text" name="tax_number" value="<?php echo $tax; ?>" style="width:100%;"><br><br>

        <label>Map Zoom Level (recommended 16–20, optional):</label><br>
        <input type="number" name="map_zoom" min="1" max="20" value="<?php echo $zoom; ?>" style="width:100px;"><br><br>

        <label>Google Maps Embed Link (recommended – paste the src URL from embed code):</label><br>
        <input type="text" name="google_embed_src" value="<?php echo $embed_src; ?>" placeholder="https://www.google.com/maps/embed?pb=..." style="width:100%;"><br>
        <small>How to get it: Go to Google Maps → search your address → Share → Embed a map → Copy the src URL only (everything inside quotes after src=)</small><br><br>

        <input type="submit" name="update_map" value="Save Location Info">
    </form>
</div>

<?php if (!empty($embed_src)): ?>
    <div class="section-card">
        <h3>Map View</h3>
        <iframe 
            width="100%" 
            height="500" 
            frameborder="0" 
            style="border:0;" 
            referrerpolicy="no-referrer-when-downgrade"
            src="<?php echo $embed_src; ?>"
            allowfullscreen>
        </iframe>

        <br><br>
        <small>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($lat . ',' . $lng); ?>" target="_blank">
                Open in Google Maps (larger view)
            </a>
        </small>
    </div>
<?php elseif ($lat != 0 && $lng != 0): ?>
    <div class="section-card" style="background:#fff3cd; border:1px solid #ffeeba; padding:20px; text-align:center;">
        <p style="color:#856404; font-size:1.1em;">
            No embed link saved yet.<br>
            Paste a Google Maps embed src URL above to show a custom map view.
        </p>
    </div>
<?php else: ?>
    <div class="section-card" style="background:#f8f9fa; border:1px solid #ddd; text-align:center; padding:40px;">
        <p style="color:#666; font-style:italic; font-size:1.1em;">
            No GPS coordinates or embed link saved.<br>
            Enter details above and save to show the map.
        </p>
    </div>
<?php endif; ?>
<?php endif; ?>

<?php if (hds_ui_section_enabled('map-taxes', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Property Taxes</h3>
    <form method="post">
        <strong>Current/Next Tax Bill:</strong><br><br>
        Amount Owed ($): <input type="number" step="0.01" name="amount_owed" placeholder="0.00" required><br><br>
        Due Date: <input type="date" name="due_date" required><br><br>
        <input type="submit" name="save_tax_bill" value="Save Tax Bill">
    </form>

    <div class="tax-history">
        <h4>Tax History</h4>
        <?php
        $taxes = $conn->query("SELECT * FROM property_taxes WHERE house_id = $house_id ORDER BY due_date DESC");
        if ($taxes->num_rows > 0) {
            while ($tax = $taxes->fetch_assoc()) {
                $paid_class = $tax['is_paid'] ? 'paid' : 'unpaid';
                $check_number = htmlspecialchars($tax['check_number'] ?? '', ENT_QUOTES, 'UTF-8');
                echo "<div class='tax-entry $paid_class'>
                      <div>
                          <strong>$" . number_format($tax['amount_owed'], 2) . "</strong> — Due: " . $tax['due_date'] . "
                      </div>
                      <div style='display:flex; align-items:center; gap:15px; flex-wrap:wrap;'>
                          <form method='post' style='margin:0; display:flex; align-items:center; gap:10px; flex-wrap:wrap;'>
                              <input type='hidden' name='tax_id' value='" . $tax['id'] . "'>
                              <label style='display:inline-flex; align-items:center; gap:6px; margin:0;'>
                                  Paid: <input type='checkbox' name='is_paid' " . ($tax['is_paid'] ? 'checked' : '') . " onchange='this.form.submit();'>
                              </label>
                              <label style='display:inline-flex; align-items:center; gap:6px; margin:0;'>
                                  Check #: <input type='text' name='check_number' value=\"$check_number\" placeholder='Check number' class='tax-check-input' onchange='this.form.submit();'>
                              </label>
                              <input type='hidden' name='toggle_tax_paid' value='1'>
                          </form>
                          <form method='post' style='margin:0;' onsubmit='return confirm(\"Delete this tax bill permanently?\");'>
                              <input type='hidden' name='tax_id' value='" . $tax['id'] . "'>
                              <input type='submit' name='delete_tax_bill' value='Delete' class='delete-tax-btn'>
                          </form>
                      </div>
                      </div>";
            }
        } else {
            echo "<p>No tax bills recorded yet.</p>";
        }
        ?>
    </div>
</div>
<?php endif; ?>
