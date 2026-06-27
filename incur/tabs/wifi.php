<?php
// tabs/wifi.php — Password-protected WiFi credentials (per house)

global $conn, $house_id;

$wifi_unlocked = (session_status() === PHP_SESSION_ACTIVE) && !empty($_SESSION['wifi_unlocked']);
$wifi_error = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['wifi_error'] ?? '') : '';
if (session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['wifi_error']);
}
?>

<h2>WiFi</h2>

<?php if (!$wifi_unlocked): ?>
    <div class="section-card wifi-lock-card">
        <h3><i class="fas fa-lock" aria-hidden="true"></i> Protected Area</h3>
        <p>Enter the access password to view and manage WiFi credentials for this house.</p>
        <?php if ($wifi_error): ?>
            <p class="wifi-error"><?php echo htmlspecialchars($wifi_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form method="post" class="wifi-unlock-form">
            <label for="wifi_access_password">Password:</label><br>
            <input type="password" id="wifi_access_password" name="wifi_access_password" required autocomplete="current-password"><br><br>
            <input type="submit" name="wifi_unlock" value="Unlock WiFi Tab">
        </form>
    </div>
<?php else: ?>
    <div class="wifi-toolbar">
        <form method="post" class="wifi-lock-form">
            <input type="submit" name="wifi_lock" value="Lock WiFi Tab" class="wifi-lock-btn">
        </form>
    </div>

    <div class="section-card">
        <h3>Add WiFi Network</h3>
        <form method="post" class="wifi-form">
            <label>Network Name (SSID):</label><br>
            <input type="text" name="network_name" placeholder="e.g. HomeNetwork-5G" required style="width:100%;"><br><br>

            <label>WiFi Password:</label><br>
            <input type="text" name="wifi_password" placeholder="Network password" style="width:100%;"><br><br>

            <label>Notes:</label><br>
            <textarea name="wifi_notes" rows="3" style="width:100%;" placeholder="Router location, guest network, etc."></textarea><br><br>

            <input type="submit" name="add_wifi" value="Add Network">
        </form>
    </div>

    <?php
    $networks = $conn->query("SELECT * FROM wifi_networks WHERE house_id = $house_id ORDER BY network_name ASC");

    if ($networks && $networks->num_rows > 0) {
        echo "<div class='wifi-list'>";
        while ($network = $networks->fetch_assoc()) {
            $network_id = (int)$network['id'];
            $network_name = htmlspecialchars($network['network_name'], ENT_QUOTES, 'UTF-8');
            $wifi_password = htmlspecialchars($network['password'] ?? '', ENT_QUOTES, 'UTF-8');
            $wifi_notes = htmlspecialchars($network['notes'] ?? '', ENT_QUOTES, 'UTF-8');
            $has_password = trim($network['password'] ?? '') !== '';
            $has_notes = trim($network['notes'] ?? '') !== '';
            $password_display = $has_password ? '••••••••' : '—';

            echo "<div class='section-card wifi-card'>";
            echo "<h3><i class='fas fa-wifi' aria-hidden='true'></i> $network_name</h3>";

            echo "<div data-view-edit class='hds-ve-block'>";
            echo "<div data-view-edit-view>";
            echo "<div class='hds-ve-header'>";
            echo "<div class='hds-ve-actions'>";
            echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
            echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this WiFi network?\");'>";
            echo "<input type='hidden' name='wifi_id' value='$network_id'>";
            echo "<input type='submit' name='delete_wifi' value='Delete' class='small-btn delete-btn'>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
            echo "<div class='hds-ve-body'>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Network Name:</span> $network_name</p>";
            echo "<p class='hds-ve-field'><span class='hds-ve-label'>Password:</span> $password_display</p>";
            if ($has_notes) {
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> " . hds_ve_display($network['notes'] ?? '') . "</p>";
            }
            echo "</div>";
            echo "</div>";

            echo "<div data-view-edit-form hidden>";
            echo "<form method='post' class='wifi-form'>";
            echo "<input type='hidden' name='wifi_id' value='$network_id'>";
            echo "<label>Network Name (SSID):</label><br>";
            echo "<input type='text' name='network_name' value=\"$network_name\" required style='width:100%;'><br><br>";
            echo "<label>WiFi Password:</label><br>";
            echo "<div class='wifi-password-row'>";
            echo "<input type='password' class='wifi-password-field' name='wifi_password' value=\"$wifi_password\" style='width:100%;'>";
            echo "<button type='button' class='wifi-toggle-btn' onclick='toggleWifiPassword(this)'>Show</button>";
            echo "</div><br>";
            echo "<label>Notes:</label><br>";
            echo "<textarea name='wifi_notes' rows='3' style='width:100%;'>$wifi_notes</textarea><br><br>";
            echo "<div class='hds-ve-edit-actions'>";
            echo "<input type='submit' name='update_wifi' value='Save Network'>";
            echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
            echo "</div>";
            echo "</form>";
            echo "</div>";
            echo "</div>";

            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p class='empty-note'>No WiFi networks saved for this house yet.</p>";
    }
    ?>
<?php endif; ?>