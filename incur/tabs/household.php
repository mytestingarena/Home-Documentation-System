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
        echo "<div class='section-card' style='margin-top:20px;'>";
        echo "<h3>" . htmlspecialchars($item['type']) . " #" . $item['id'] . "</h3>";
        echo "<form method='post'>";
        echo "Brand: <input type='text' name='brand' value='" . htmlspecialchars($item['brand'] ?? '') . "'><br><br>";
        echo "Model: <input type='text' name='model' value='" . htmlspecialchars($item['model'] ?? '') . "'><br><br>";
        echo "SN: <input type='text' name='sn' value='" . htmlspecialchars($item['sn'] ?? '') . "'><br><br>";
        echo "Notes: <textarea name='notes' rows='4' style='width:100%;'>" . htmlspecialchars($item['notes'] ?? '') . "</textarea><br><br>";
        echo "<input type='hidden' name='item_id' value='" . $item['id'] . "'>";
        echo "<input type='submit' name='update_household' value='Update'>";
        echo "</form>";
        echo "<form method='post' style='margin-top:10px;'>";
        echo "<input type='hidden' name='item_id' value='" . $item['id'] . "'>";
        echo "<input type='submit' name='delete_household' value='Delete' class='delete-btn' onclick='return confirm(\"Delete this " . htmlspecialchars($item['type']) . "?\");'>";
        echo "</form>";
        echo "</div>";
    }
} else {
    echo "<p style='color:#777;'>No household items added yet.</p>";
}
?>
