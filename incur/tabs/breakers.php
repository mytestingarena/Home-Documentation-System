<?php
// tabs/breakers.php — Breaker panels (included from permanent.php)

global $conn, $house_id;

$open_panel_id = intval($_GET['open_panel'] ?? 0);
$breakers_open = $open_panel_id > 0 ? ' open' : '';
?>

<details class="permanent-section permanent-section--breakers collapsible-section" id="permanent-breakers"<?php echo $breakers_open; ?>>
    <summary class="collapsible-summary">
        <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
        <i class="fas fa-table-cells-large permanent-icon" aria-hidden="true"></i>
        <h3>Breaker Panels</h3>
    </summary>

    <div class="collapsible-body">
        <form method="post" class="breaker-add-form">
            <label>Add New Panel:</label><br>
            <select name="new_panel_size">
                <option value="6">6 Breaker Panel</option>
                <option value="12">12 Breaker Panel</option>
                <option value="24">24 Breaker Panel</option>
                <option value="28">28 Breaker Panel</option>
            </select>
            <input type="text" name="new_panel_name" placeholder="Panel Name (e.g. Main, Garage Sub)" required>
            <input type="submit" name="add_breaker_panel" value="Add Panel">
        </form>

        <div class="collapsible-list-toolbar">
            <button type="button" class="small-btn" onclick="collapsibleExpandAll('.breaker-panels-list .collapsible-section', true)">Expand all panels</button>
            <button type="button" class="small-btn" onclick="collapsibleExpandAll('.breaker-panels-list .collapsible-section', false)">Collapse all panels</button>
        </div>

        <div class="breaker-panels-list">
        <?php
        $panels = $conn->query("SELECT id, name, size FROM electric_panels WHERE house_id = $house_id ORDER BY id");
        if ($panels->num_rows > 0) {
            while ($panel = $panels->fetch_assoc()) {
                $panel_id = $panel['id'];
                $panel_name = htmlspecialchars($panel['name']);
                $panel_size = $panel['size'];
                $is_open = ($panel_id === $open_panel_id) ? ' open' : '';

                echo "<details class='panel-section collapsible-section' id='panel-$panel_id'$is_open>";
                echo "<summary class='collapsible-summary'>";
                echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
                echo "<span class='collapsible-summary-title'>$panel_name ($panel_size breakers)</span>";
                echo "</summary>";
                echo "<div class='collapsible-body'>";
                echo "<form method='post'>";
                echo "<input type='hidden' name='panel_id' value='$panel_id'>";
                echo "<div class='breaker-table-wrap'>";
                echo "<table class='breaker-table'>";
                echo "<tr><th>Breaker #</th><th>Room/Location</th><th>Amps</th><th>Breaker #</th><th>Room/Location</th><th>Amps</th></tr>";

                $max_rows = ceil($panel_size / 2);
                for ($row = 1; $row <= $max_rows; $row++) {
                    $top_left_num  = $panel_size - ($row - 1) * 2;
                    $top_right_num = $top_left_num - 1;

                    $left = $conn->query("SELECT room, amp FROM breakers WHERE panel_id = $panel_id AND column_num = 1 AND row_num = $row")->fetch_assoc();
                    $left_room = htmlspecialchars($left ? ($left['room'] ?? '') : '', ENT_QUOTES, 'UTF-8');
                    $left_amp  = ($left && $left['amp'] !== null && $left['amp'] !== '') ? htmlspecialchars((string)$left['amp'], ENT_QUOTES, 'UTF-8') : '';

                    $right = $conn->query("SELECT room, amp FROM breakers WHERE panel_id = $panel_id AND column_num = 2 AND row_num = $row")->fetch_assoc();
                    $right_room = htmlspecialchars($right ? ($right['room'] ?? '') : '', ENT_QUOTES, 'UTF-8');
                    $right_amp  = ($right && $right['amp'] !== null && $right['amp'] !== '') ? htmlspecialchars((string)$right['amp'], ENT_QUOTES, 'UTF-8') : '';

                    echo "<tr>";
                    echo "<td class='breaker-num'>$top_left_num</td>";
                    echo "<td class='breaker-room'><input type='text' class='breaker-room-input' name='left_room_$row' value=\"$left_room\"></td>";
                    echo "<td class='breaker-amp'><input type='text' class='breaker-amp-input' name='left_amp_$row' value=\"$left_amp\"></td>";
                    echo "<td class='breaker-num'>$top_right_num</td>";
                    echo "<td class='breaker-room'><input type='text' class='breaker-room-input' name='right_room_$row' value=\"$right_room\"></td>";
                    echo "<td class='breaker-amp'><input type='text' class='breaker-amp-input' name='right_amp_$row' value=\"$right_amp\"></td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
                echo "<div class='panel-actions'>";
                echo "<input type='submit' name='save_breakers' value='Save Breaker Labels'>";
                echo "<input type='submit' name='delete_panel' value='Delete Panel' class='delete-panel-btn' onclick='return confirm(\"Delete this panel and all its breaker labels?\");'>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</details>";
            }
        } else {
            echo "<p class='empty-note'>No breaker panels added yet. Use the form above to add one.</p>";
        }
        ?>
        </div>
    </div>
</details>