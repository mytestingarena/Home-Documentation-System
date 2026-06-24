<?php
// tabs/tools.php — Tools inventory (per house)

global $conn, $house_id;

$power_types = [
    'battery'   => 'Battery',
    'ac'        => 'AC',
    'pneumatic' => 'Pneumatic',
    'manual'    => 'Manual',
    'na'        => 'N/A',
];

$houses = $conn->query("SELECT id, name FROM houses ORDER BY name ASC");

function render_tool_house_select($houses, int $selected_id, string $field_name = 'tool_house_id'): void {
    echo "<select name='" . htmlspecialchars($field_name, ENT_QUOTES, 'UTF-8') . "' class='tool-house-select' required>";
    if ($houses && $houses->num_rows > 0) {
        while ($house = $houses->fetch_assoc()) {
            $id = (int)$house['id'];
            $name = htmlspecialchars($house['name'], ENT_QUOTES, 'UTF-8');
            $selected = $id === $selected_id ? ' selected' : '';
            echo "<option value='$id'$selected>$name</option>";
        }
    }
    echo "</select>";
}
?>

<h2>Tools</h2>

<div class="section-card">
    <h3>Add New Tool</h3>
    <form method="post" class="tool-form">
        <label>Tool Name:</label><br>
        <input type="text" name="tool_name" placeholder="e.g. Circular saw, Impact driver" required style="width:100%;"><br><br>

        <label>Power Type:</label><br>
        <select name="power_type" required>
            <?php foreach ($power_types as $value => $label): ?>
                <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Location (House):</label><br>
        <?php
        if ($houses) {
            $houses->data_seek(0);
            render_tool_house_select($houses, $house_id);
        }
        ?><br><br>

        <label>Description:</label><br>
        <textarea name="tool_description" rows="4" style="width:100%;" placeholder="SN, model number (MN), brand, notes, etc."></textarea><br><br>

        <input type="submit" name="add_tool" value="Add Tool">
    </form>
</div>

<?php
$tools = $conn->query("SELECT * FROM tools WHERE house_id = $house_id ORDER BY name ASC");

if ($tools && $tools->num_rows > 0) {
    $open_tool_id = intval($_GET['open_tool'] ?? 0);
    echo "<div class='collapsible-list-toolbar'>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.tools-list .collapsible-section', true)\">Expand all</button>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.tools-list .collapsible-section', false)\">Collapse all</button>";
    echo "</div>";
    echo "<div class='tools-list'>";
    while ($tool = $tools->fetch_assoc()) {
        $tool_id = (int)$tool['id'];
        $tool_name = htmlspecialchars($tool['name'], ENT_QUOTES, 'UTF-8');
        $power_type = $tool['power_type'] ?? 'manual';
        $power_label = htmlspecialchars($power_types[$power_type] ?? ucfirst($power_type), ENT_QUOTES, 'UTF-8');
        $tool_house_id = (int)$tool['house_id'];
        $tool_description = htmlspecialchars($tool['description'] ?? '', ENT_QUOTES, 'UTF-8');

        $is_open = ($tool_id === $open_tool_id) ? ' open' : '';
        echo "<details class='section-card tool-card collapsible-section' id='tool-$tool_id'$is_open>";
        echo "<summary class='collapsible-summary'>";
        echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
        echo "<span class='collapsible-summary-title'>$tool_name</span>";
        echo "<span class='tool-type-badge tool-type-badge--$power_type'>$power_label</span>";
        echo "</summary>";
        echo "<div class='collapsible-body'>";

        echo "<form method='post' class='tool-form'>";
        echo "<input type='hidden' name='tool_id' value='$tool_id'>";

        echo "<label>Tool Name:</label><br>";
        echo "<input type='text' name='tool_name' value=\"$tool_name\" required style='width:100%;'><br><br>";

        echo "<label>Power Type:</label><br>";
        echo "<select name='power_type' required>";
        foreach ($power_types as $value => $label) {
            $selected = $power_type === $value ? ' selected' : '';
            echo "<option value='$value'$selected>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
        }
        echo "</select><br><br>";

        echo "<label>Location (House):</label><br>";
        if ($houses) {
            $houses->data_seek(0);
            render_tool_house_select($houses, $tool_house_id);
        }
        echo "<br><small class='tool-move-note'>Change the house to move this tool to another location.</small><br><br>";

        echo "<label>Description:</label><br>";
        echo "<textarea name='tool_description' rows='4' style='width:100%;' placeholder='SN, model number (MN), brand, notes, etc.'>$tool_description</textarea><br><br>";

        echo "<input type='submit' name='update_tool' value='Save Tool'>";
        echo "</form>";

        echo "<form method='post' class='tool-delete-form' onsubmit='return confirm(\"Delete this tool?\");'>";
        echo "<input type='hidden' name='tool_id' value='$tool_id'>";
        echo "<input type='submit' name='delete_tool' value='Delete' class='delete-btn'>";
        echo "</form>";
        echo "</div>";
        echo "</details>";
    }
    echo "</div>";
} else {
    echo "<p class='empty-note'>No tools at this house yet. Add one above or move a tool here from another house.</p>";
}
?>