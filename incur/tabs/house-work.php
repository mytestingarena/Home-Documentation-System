<?php
// tabs/house-work.php — House work section (included from permanent.php)

global $conn, $house_id;

require_once __DIR__ . '/../includes/house-work-images.php';

$open_house_work_id = intval($_GET['open_house_work'] ?? 0);
$house_work_open = ($open_house_work_id > 0 || ($_GET['open_permanent'] ?? '') === 'house_work') ? ' open' : '';

function hds_house_work_types(): array
{
    return [
        'foundation' => 'Foundation',
        'basement' => 'Basement / Crawl space',
        'framing' => 'Framing / Structure',
        'waterproofing' => 'Waterproofing / Drainage',
        'insulation' => 'Insulation',
        'drywall' => 'Drywall / Plaster',
        'flooring' => 'Flooring',
        'plumbing' => 'Plumbing (interior)',
        'electrical' => 'Electrical (interior)',
        'hvac' => 'HVAC / Ductwork',
        'windows_doors' => 'Windows / Doors',
        'kitchen_bath' => 'Kitchen / Bath remodel',
        'painting' => 'Interior painting',
        'mold' => 'Mold / Remediation',
        'other' => 'Other',
    ];
}

function hds_house_work_type_label(string $type): string
{
    $types = hds_house_work_types();
    return $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function hds_render_house_work_type_select(string $name, string $selected = ''): void
{
    echo "<select name='$name' required>";
    if ($selected === '') {
        echo "<option value=''>Select type...</option>";
    }
    foreach (hds_house_work_types() as $value => $label) {
        $sel = $value === $selected ? ' selected' : '';
        $label_esc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo "<option value='$value'$sel>$label_esc</option>";
    }
    echo "</select>";
}
?>

<details class="permanent-section permanent-section--house collapsible-section" id="permanent-house_work"<?php echo $house_work_open; ?>>
    <summary class="collapsible-summary">
        <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
        <i class="fas fa-house-chimney permanent-icon" aria-hidden="true"></i>
        <h3>House Work</h3>
    </summary>

    <div class="collapsible-body">
        <form method="post" action="house.php?id=<?php echo (int)$house_id; ?>&tab=permanent" class="house-work-add-form">
            <label>Add house work:</label><br>
            <?php hds_render_house_work_type_select('house_work_type'); ?>
            <input type="text" name="house_description" placeholder="Description (e.g. Foundation crack repair, east wall)" required>
            <input type="date" name="house_date_completed" title="Date completed">
            <input type="text" name="house_contractor" placeholder="Contractor (optional)">
            <input type="text" name="house_notes" placeholder="Notes (optional)">
            <input type="submit" name="add_house_work" value="Add Item">
        </form>

        <div class="house-work-list">
        <?php
        $items = $conn->query("SELECT * FROM house_work_items WHERE house_id = $house_id ORDER BY date_completed DESC, id DESC");
        if ($items && $items->num_rows > 0) {
            while ($item = $items->fetch_assoc()) {
                $item_id = (int)$item['id'];
                $work_type = $item['work_type'] ?? 'other';
                $type_label = htmlspecialchars(hds_house_work_type_label($work_type), ENT_QUOTES, 'UTF-8');
                $description = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $date_raw = $item['date_completed'] ?? '';
                $date_display = $date_raw !== '' ? date('M j, Y', strtotime($date_raw)) : '';
                $contractor = htmlspecialchars($item['contractor'] ?? '', ENT_QUOTES, 'UTF-8');
                $notes = htmlspecialchars($item['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                $has_date = $date_raw !== '';
                $has_contractor = trim($item['contractor'] ?? '') !== '';
                $has_notes = trim($item['notes'] ?? '') !== '';

                echo "<div class='house-work-entry-wrap'>";
                echo "<div data-view-edit class='hds-ve-block hds-ve-block--card house-work-entry'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header hds-ve-header--split'>";
                echo "<div class='maintenance-log-view-meta'>";
                echo "<strong class='maintenance-log-view-date'>$type_label</strong>";
                echo "<span class='maintenance-log-view-hours'>$description</span>";
                if ($has_date) {
                    echo "<span class='maintenance-log-view-hours'>Completed: " . htmlspecialchars($date_display, ENT_QUOTES, 'UTF-8') . "</span>";
                }
                if ($has_contractor) {
                    echo "<span class='maintenance-log-view-hours'>Contractor: $contractor</span>";
                }
                echo "</div>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this house work item?\");'>";
                echo "<input type='hidden' name='house_work_id' value='$item_id'>";
                echo "<input type='submit' name='delete_house_work' value='Delete' class='small-btn delete-btn'>";
                echo "</form>";
                echo "</div>";
                echo "</div>";
                if ($has_notes) {
                    echo "<div class='hds-ve-body'>";
                    echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> $notes</p>";
                    echo "</div>";
                }
                echo "</div>";

                echo "<div data-view-edit-form hidden>";
                echo "<form method='post' class='maintenance-form'>";
                echo "<input type='hidden' name='house_work_id' value='$item_id'>";
                echo "<label>Type:</label><br>";
                hds_render_house_work_type_select('house_work_type', $work_type);
                echo "<br><br><label>Description:</label><br>";
                echo "<input type='text' name='house_description' value=\"$description\" style='width:100%;' required><br><br>";
                echo "<label>Date completed:</label> <input type='date' name='house_date_completed' value=\"" . htmlspecialchars($date_raw, ENT_QUOTES, 'UTF-8') . "\"><br><br>";
                echo "<label>Contractor:</label> <input type='text' name='house_contractor' value=\"$contractor\" placeholder='Optional'><br><br>";
                echo "<label>Notes:</label><br>";
                echo "<textarea name='house_notes' rows='3' style='width:100%;'>$notes</textarea><br><br>";
                echo "<div class='hds-ve-edit-actions'>";
                echo "<input type='submit' name='update_house_work' value='Save'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
                echo "</div>";

                hds_render_house_work_images($conn, $house_id, $item_id);
                echo "</div>";
            }
        } else {
            echo "<p class='empty-note'>No house work recorded yet. Use the form above to add items like foundation repair, remodeling, etc.</p>";
        }
        ?>
        </div>

        <?php hds_render_permanent_maintenance_log($conn, $house_id, 'house_work'); ?>
    </div>
</details>

<div id="houseRenameModal" class="media-rename-modal" hidden aria-hidden="true">
    <div class="media-rename-backdrop" data-house-rename-close></div>
    <div class="media-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="houseRenameTitle">
        <button type="button" class="media-rename-close" data-house-rename-close aria-label="Close">&times;</button>
        <h3 id="houseRenameTitle">Rename Photo</h3>
        <p class="media-rename-current-row">
            <span class="media-rename-label">Current name:</span>
            <span id="houseRenameCurrent" class="media-rename-current"></span>
        </p>
        <form method="post" id="houseRenameForm">
            <input type="hidden" name="house_image_id" id="houseRenameImageId" value="">
            <input type="hidden" name="house_work_id" id="houseRenameHouseWorkId" value="">
            <label for="houseRenameNew">New name:</label>
            <div class="media-rename-input-row">
                <input type="text" name="house_image_basename" id="houseRenameNew" required autocomplete="off" placeholder="Enter name without extension">
                <span id="houseRenameExt" class="media-rename-ext"></span>
            </div>
            <div class="media-rename-actions">
                <button type="button" class="small-btn" data-house-rename-close>Cancel</button>
                <input type="submit" name="rename_house_work_image" value="Save" class="media-rename-save">
            </div>
        </form>
    </div>
</div>
