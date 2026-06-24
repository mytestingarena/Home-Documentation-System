<?php
// tabs/permanent.php — Permanent Items tab (shows latest data)

global $conn, $house_id;
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

<div class="collapsible-list-toolbar">
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.permanent-items-list .collapsible-section', true)">Expand all</button>
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.permanent-items-list .collapsible-section', false)">Collapse all</button>
</div>

<form method="post">
    <div class="permanent-items-list">
    <?php
    $item_types = ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac'];
    foreach ($item_types as $type) {
        $sql = "SELECT * FROM permanent_items WHERE house_id = $house_id AND item_type = '$type' ORDER BY id DESC LIMIT 1";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc() ?? [];
        $display_name = $type === 'ac' ? 'Air Conditioner' : ucfirst(str_replace('_', ' ', $type));
        echo "<details class='section-card permanent-item-card collapsible-section' id='permanent-$type'>";
        echo "<summary class='collapsible-summary'>";
        echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
        echo "<span class='collapsible-summary-title'>" . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . "</span>";
        echo "</summary>";
        echo "<div class='collapsible-body'>";
        echo "Brand: <input type='text' name='{$type}_brand' value='" . htmlspecialchars($row['brand'] ?? '') . "'><br><br>";
        echo "Model: <input type='text' name='{$type}_model' value='" . htmlspecialchars($row['model'] ?? '') . "'><br><br>";
        echo "SN: <input type='text' name='{$type}_sn' value='" . htmlspecialchars($row['sn'] ?? '') . "'><br><br>";
        echo "Efficiency: <input type='text' name='{$type}_efficiency' value='" . htmlspecialchars($row['efficiency'] ?? '') . "'><br><br>";
        echo "KWH: <input type='number' step='0.01' name='{$type}_kwh' value='" . ($row['kwh'] ?? 0) . "'><br><br>";
        if (in_array($type, ['water_heater','dishwasher'])) {
            echo "Capacity: <input type='number' name='{$type}_capacity' value='" . ($row['capacity'] ?? 0) . "'><br><br>";
        }
        echo "</div>";
        echo "</details>";
    }
    ?>
    </div>
    <input type="submit" name="update_permanent" value="Update Permanent Items">
</form>

<div class="permanent-separator" aria-hidden="true"></div>

<?php include __DIR__ . '/breakers.php'; ?>