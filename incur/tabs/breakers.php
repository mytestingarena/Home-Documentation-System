<?php
// tabs/breakers.php — Breaker panels (included from permanent.php)

global $conn, $house_id;

$open_panel_id = intval($_GET['open_panel'] ?? 0);
$breakers_open = $open_panel_id > 0 ? ' open' : '';

function render_breaker_table_rows($conn, int $panel_id, int $panel_size, bool $readonly = false): void {
    $max_rows = (int)ceil($panel_size / 2);
    for ($row = 1; $row <= $max_rows; $row++) {
        $top_left_num  = ($row - 1) * 2 + 1;
        $top_right_num = ($row - 1) * 2 + 2;

        $left = $conn->query("SELECT room, amp FROM breakers WHERE panel_id = $panel_id AND column_num = 1 AND row_num = $row")->fetch_assoc();
        $left_room_raw = $left ? ($left['room'] ?? '') : '';
        $left_amp_raw  = ($left && $left['amp'] !== null && $left['amp'] !== '') ? (string)$left['amp'] : '';
        $left_room = htmlspecialchars($left_room_raw, ENT_QUOTES, 'UTF-8');
        $left_amp  = htmlspecialchars($left_amp_raw, ENT_QUOTES, 'UTF-8');

        $right = $conn->query("SELECT room, amp FROM breakers WHERE panel_id = $panel_id AND column_num = 2 AND row_num = $row")->fetch_assoc();
        $right_room_raw = $right ? ($right['room'] ?? '') : '';
        $right_amp_raw  = ($right && $right['amp'] !== null && $right['amp'] !== '') ? (string)$right['amp'] : '';
        $right_room = htmlspecialchars($right_room_raw, ENT_QUOTES, 'UTF-8');
        $right_amp  = htmlspecialchars($right_amp_raw, ENT_QUOTES, 'UTF-8');

        echo "<tr>";
        echo "<td class='breaker-num'>$top_left_num</td>";
        if ($readonly) {
            echo "<td class='breaker-room'>" . hds_ve_display($left_room_raw, '') . "</td>";
            echo "<td class='breaker-amp'>" . hds_ve_display($left_amp_raw, '') . "</td>";
            echo "<td class='breaker-num'>$top_right_num</td>";
            echo "<td class='breaker-room'>" . hds_ve_display($right_room_raw, '') . "</td>";
            echo "<td class='breaker-amp'>" . hds_ve_display($right_amp_raw, '') . "</td>";
        } else {
            echo "<td class='breaker-room'><input type='text' class='breaker-room-input' name='left_room_$row' value=\"$left_room\"></td>";
            echo "<td class='breaker-amp'><input type='text' class='breaker-amp-input' name='left_amp_$row' value=\"$left_amp\"></td>";
            echo "<td class='breaker-num'>$top_right_num</td>";
            echo "<td class='breaker-room'><input type='text' class='breaker-room-input' name='right_room_$row' value=\"$right_room\"></td>";
            echo "<td class='breaker-amp'><input type='text' class='breaker-amp-input' name='right_amp_$row' value=\"$right_amp\"></td>";
        }
        echo "</tr>";
    }
}
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
                <option value="30">30 Breaker Panel</option>
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
                $panel_id = (int)$panel['id'];
                $panel_name = htmlspecialchars($panel['name'], ENT_QUOTES, 'UTF-8');
                $panel_size = (int)$panel['size'];
                $is_open = ($panel_id === $open_panel_id) ? ' open' : '';

                echo "<details class='panel-section collapsible-section' id='panel-$panel_id'$is_open>";
                echo "<summary class='collapsible-summary'>";
                echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
                echo "<span class='collapsible-summary-title'>$panel_name ($panel_size breakers)</span>";
                echo "</summary>";
                echo "<div class='collapsible-body'>";

                echo "<div data-view-edit class='hds-ve-block'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header'>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this panel and all its breaker labels?\");'>";
                echo "<input type='hidden' name='panel_id' value='$panel_id'>";
                echo "<input type='submit' name='delete_panel' value='Delete Panel' class='small-btn delete-btn'>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                echo "<div class='breaker-table-wrap'>";
                echo "<table class='breaker-table breaker-table--readonly'>";
                echo "<tr><th>Breaker #</th><th>Room/Location</th><th>Amps</th><th>Breaker #</th><th>Room/Location</th><th>Amps</th></tr>";
                render_breaker_table_rows($conn, $panel_id, $panel_size, true);
                echo "</table>";
                echo "</div>";
                echo "</div>";

                echo "<div data-view-edit-form hidden>";
                echo "<form method='post'>";
                echo "<input type='hidden' name='panel_id' value='$panel_id'>";
                echo "<div class='breaker-table-wrap'>";
                echo "<table class='breaker-table'>";
                echo "<tr><th>Breaker #</th><th>Room/Location</th><th>Amps</th><th>Breaker #</th><th>Room/Location</th><th>Amps</th></tr>";
                render_breaker_table_rows($conn, $panel_id, $panel_size, false);
                echo "</table>";
                echo "</div>";
                echo "<div class='hds-ve-edit-actions'>";
                echo "<input type='submit' name='save_breakers' value='Save Breaker Labels'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";

                echo "</div>";
                echo "</details>";
            }
        } else {
            echo "<p class='empty-note'>No breaker panels added yet. Use the form above to add one.</p>";
        }
        ?>
        </div>

        <?php hds_render_permanent_maintenance_log($conn, $house_id, 'breakers'); ?>
    </div>
</details>