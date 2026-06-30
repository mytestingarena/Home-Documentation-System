<?php
// includes/permanent-maintenance-log.php — maintenance log UI for permanent item sections

require_once __DIR__ . '/permanent-log-images.php';

function hds_permanent_log_types(): array
{
    return ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac', 'outdoor_work', 'breakers'];
}

function hds_permanent_log_completed_label(string $value): string
{
    return $value === 'contractor' ? 'Contractor completed' : 'Home owner completed';
}

function hds_permanent_log_payment_label(?string $value): string
{
    return match ($value) {
        'debit' => 'Debit',
        'cc' => 'Credit card',
        'check' => 'Check',
        'cash' => 'Cash',
        default => '',
    };
}

function hds_permanent_log_parse_contractor_fields(array $post): array
{
    $completed_by = ($post['perm_log_completed_by'] ?? '') === 'contractor' ? 'contractor' : 'homeowner';
    if ($completed_by !== 'contractor') {
        return [
            'completed_by' => 'homeowner',
            'contractor_price' => null,
            'payment_method' => null,
            'payment_reference' => null,
        ];
    }

    $price_raw = trim($post['perm_log_price'] ?? '');
    $contractor_price = $price_raw !== '' ? round((float)$price_raw, 2) : null;
    $payment_method = in_array($post['perm_log_payment_method'] ?? '', ['debit', 'cc', 'check', 'cash'], true)
        ? $post['perm_log_payment_method']
        : null;
    $payment_reference = trim($post['perm_log_payment_ref'] ?? '') ?: null;

    return [
        'completed_by' => 'contractor',
        'contractor_price' => $contractor_price,
        'payment_method' => $payment_method,
        'payment_reference' => $payment_reference,
    ];
}

function hds_render_permanent_completed_by_field(string $name, string $selected = 'homeowner'): void
{
    $home_checked = $selected !== 'contractor' ? ' checked' : '';
    $contractor_checked = $selected === 'contractor' ? ' checked' : '';
    echo "<div class='perm-log-completed-by'>";
    echo "<label class='perm-log-radio'><input type='radio' name='$name' value='homeowner'$home_checked required> Home owner completed</label>";
    echo "<label class='perm-log-radio'><input type='radio' name='$name' value='contractor'$contractor_checked required> Contractor completed</label>";
    echo "</div>";
}

function hds_render_permanent_contractor_fields(
    ?float $price = null,
    ?string $payment_method = null,
    ?string $payment_reference = null,
    bool $is_contractor = false
): void {
    $hidden = $is_contractor ? '' : ' hidden';
    $price_value = $price !== null ? htmlspecialchars(number_format($price, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '';
    $payment_ref = htmlspecialchars($payment_reference ?? '', ENT_QUOTES, 'UTF-8');
    $debit_checked = $payment_method === 'debit' ? ' checked' : '';
    $cc_checked = $payment_method === 'cc' ? ' checked' : '';
    $check_checked = $payment_method === 'check' ? ' checked' : '';
    $cash_checked = $payment_method === 'cash' ? ' checked' : '';

    echo "<div class='perm-log-contractor-fields'$hidden>";
    echo "<label>Price:</label> <input type='number' step='0.01' min='0' name='perm_log_price' value=\"$price_value\" placeholder='0.00'><br><br>";
    echo "<label>Payment method:</label><br>";
    echo "<div class='perm-log-payment-method'>";
    echo "<label class='perm-log-radio'><input type='radio' name='perm_log_payment_method' value='debit'$debit_checked> Debit</label>";
    echo "<label class='perm-log-radio'><input type='radio' name='perm_log_payment_method' value='cc'$cc_checked> Credit card</label>";
    echo "<label class='perm-log-radio'><input type='radio' name='perm_log_payment_method' value='check'$check_checked> Check</label>";
    echo "<label class='perm-log-radio'><input type='radio' name='perm_log_payment_method' value='cash'$cash_checked> Cash</label>";
    echo "</div><br>";
    echo "<label>Payment reference (optional):</label> <input type='text' name='perm_log_payment_ref' value=\"$payment_ref\" placeholder='Last 4, check number, etc.'><br><br>";
    echo "</div>";
}

function hds_render_permanent_maintenance_log($conn, int $house_id, string $item_type): void
{
    $item_type = preg_replace('/[^a-z_]/', '', $item_type);
    if (!in_array($item_type, hds_permanent_log_types(), true)) {
        return;
    }

    $type_esc = mysqli_real_escape_string($conn, $item_type);
    $logs = $conn->query(
        "SELECT * FROM permanent_maintenance_log
         WHERE house_id = $house_id AND item_type = '$type_esc'
         ORDER BY log_date DESC, id DESC"
    );

    echo "<div class='maintenance-subsection permanent-maintenance-log'>";
    echo "<h4><i class='fas fa-clipboard-list' aria-hidden='true'></i> Maintenance Log</h4>";

    if ($logs && $logs->num_rows > 0) {
        while ($log = $logs->fetch_assoc()) {
            $log_id = (int)$log['id'];
            $log_date_raw = $log['log_date'];
            $log_date_display = date('M j, Y', strtotime($log_date_raw));
            $part_number = htmlspecialchars($log['part_number'] ?? '', ENT_QUOTES, 'UTF-8');
            $completed_by = $log['completed_by'] === 'contractor' ? 'contractor' : 'homeowner';
            $completed_label = htmlspecialchars(hds_permanent_log_completed_label($completed_by), ENT_QUOTES, 'UTF-8');
            $log_notes = htmlspecialchars($log['notes'] ?? '', ENT_QUOTES, 'UTF-8');
            $has_part = trim($log['part_number'] ?? '') !== '';
            $has_notes = trim($log['notes'] ?? '') !== '';
            $contractor_price = isset($log['contractor_price']) && $log['contractor_price'] !== null
                ? (float)$log['contractor_price']
                : null;
            $payment_method = $log['payment_method'] ?? null;
            $payment_reference = $log['payment_reference'] ?? null;
            $payment_label = hds_permanent_log_payment_label($payment_method);
            $has_contractor_payment = $completed_by === 'contractor' && (
                $contractor_price !== null || $payment_label !== '' || trim((string)$payment_reference) !== ''
            );

            echo "<div data-view-edit class='hds-ve-block hds-ve-block--card maintenance-log-entry'>";
            echo "<div data-view-edit-view>";
            echo "<div class='hds-ve-header hds-ve-header--split'>";
            echo "<div class='maintenance-log-view-meta'>";
            echo "<strong class='maintenance-log-view-date'>$log_date_display</strong>";
            echo "<span class='maintenance-log-view-hours'>$completed_label</span>";
            if ($has_part) {
                echo "<span class='maintenance-log-view-hours'>Part #: $part_number</span>";
            }
            if ($has_contractor_payment) {
                if ($contractor_price !== null) {
                    $price_display = htmlspecialchars('$' . number_format($contractor_price, 2), ENT_QUOTES, 'UTF-8');
                    echo "<span class='maintenance-log-view-hours'>Price: $price_display</span>";
                }
                if ($payment_label !== '' || trim((string)$payment_reference) !== '') {
                    $payment_display = htmlspecialchars($payment_label, ENT_QUOTES, 'UTF-8');
                    $ref_display = htmlspecialchars(trim((string)$payment_reference), ENT_QUOTES, 'UTF-8');
                    $payment_line = $payment_display;
                    if ($payment_line !== '' && $ref_display !== '') {
                        $payment_line .= ' — #' . $ref_display;
                    } elseif ($ref_display !== '') {
                        $payment_line = '#' . $ref_display;
                    }
                    echo "<span class='maintenance-log-view-hours'>Payment: $payment_line</span>";
                }
            }
            echo "</div>";
            echo "<div class='hds-ve-actions'>";
            echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
            echo "<form method='post' action='house.php?id=$house_id&tab=permanent' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this log entry?\");'>";
            echo "<input type='hidden' name='permanent_log_id' value='$log_id'>";
            echo "<input type='hidden' name='item_type' value='$item_type'>";
            echo "<input type='submit' name='delete_permanent_log' value='Delete' class='small-btn delete-btn'>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
            echo "<div class='hds-ve-body'>";
            if ($has_notes) {
                echo "<p class='hds-ve-field'><span class='hds-ve-label'>Notes:</span> $log_notes</p>";
            }
            echo "</div>";
            echo "</div>";

            echo "<div data-view-edit-form hidden>";
            echo "<form method='post' action='house.php?id=$house_id&tab=permanent' class='maintenance-form perm-log-form'>";
            echo "<input type='hidden' name='permanent_log_id' value='$log_id'>";
            echo "<input type='hidden' name='item_type' value='$item_type'>";
            echo "<label>Date:</label> <input type='date' name='perm_log_date' value=\"" . htmlspecialchars($log_date_raw, ENT_QUOTES, 'UTF-8') . "\" required><br><br>";
            echo "<label>Part number:</label> <input type='text' name='perm_log_part_number' value=\"$part_number\" placeholder='Optional'><br><br>";
            echo "<label>Completed by:</label><br>";
            hds_render_permanent_completed_by_field('perm_log_completed_by', $completed_by);
            hds_render_permanent_contractor_fields(
                $contractor_price,
                $payment_method,
                $payment_reference,
                $completed_by === 'contractor'
            );
            echo "<label>Notes:</label><br>";
            echo "<textarea name='perm_log_notes' rows='3' style='width:100%;'>$log_notes</textarea><br><br>";
            echo "<div class='hds-ve-edit-actions'>";
            echo "<input type='submit' name='update_permanent_log' value='Save Log Entry'>";
            echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
            echo "</div>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
            hds_render_perm_log_images($conn, $house_id, $log_id, $item_type);
        }
    } else {
        echo "<p class='empty-note'>No maintenance log entries yet.</p>";
    }

    echo "<form method='post' action='house.php?id=$house_id&tab=permanent' class='maintenance-form maintenance-log-add perm-log-form'>";
    echo "<input type='hidden' name='item_type' value='$item_type'>";
    echo "<strong>Add log entry:</strong><br><br>";
    echo "<label>Date:</label> <input type='date' name='perm_log_date' value=\"" . date('Y-m-d') . "\" required><br><br>";
    echo "<label>Part number:</label> <input type='text' name='perm_log_part_number' placeholder='Optional'><br><br>";
    echo "<label>Completed by:</label><br>";
    hds_render_permanent_completed_by_field('perm_log_completed_by', 'homeowner');
    hds_render_permanent_contractor_fields();
    echo "<label>Notes:</label><br>";
    echo "<textarea name='perm_log_notes' rows='3' style='width:100%;' placeholder='What was done, observations, etc.'></textarea><br><br>";
    echo "<input type='submit' name='add_permanent_log' value='Add Log Entry'>";
    echo "</form>";
    echo "</div>";

    static $perm_log_rename_modal_rendered = false;
    if (!$perm_log_rename_modal_rendered) {
        hds_render_perm_log_rename_modal($house_id);
        $perm_log_rename_modal_rendered = true;
    }
}

function hds_render_perm_log_rename_modal(int $house_id): void
{
    $house_id = (int)$house_id;
    ?>
<div id="permLogRenameModal" class="media-rename-modal" hidden aria-hidden="true">
    <div class="media-rename-backdrop" data-perm-log-rename-close></div>
    <div class="media-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="permLogRenameTitle">
        <button type="button" class="media-rename-close" data-perm-log-rename-close aria-label="Close">&times;</button>
        <h3 id="permLogRenameTitle">Rename Photo</h3>
        <p class="media-rename-current-row">
            <span class="media-rename-label">Current name:</span>
            <span id="permLogRenameCurrent" class="media-rename-current"></span>
        </p>
        <form method="post" id="permLogRenameForm" action="house.php?id=<?php echo $house_id; ?>&tab=permanent">
            <input type="hidden" name="perm_log_image_id" id="permLogRenameImageId" value="">
            <input type="hidden" name="permanent_log_id" id="permLogRenameLogId" value="">
            <input type="hidden" name="item_type" id="permLogRenameItemType" value="">
            <label for="permLogRenameNew">New name:</label>
            <div class="media-rename-input-row">
                <input type="text" name="perm_log_image_basename" id="permLogRenameNew" required autocomplete="off" placeholder="Enter name without extension">
                <span id="permLogRenameExt" class="media-rename-ext"></span>
            </div>
            <div class="media-rename-actions">
                <button type="button" class="small-btn" data-perm-log-rename-close>Cancel</button>
                <input type="submit" name="rename_perm_log_image" value="Save" class="media-rename-save">
            </div>
        </form>
    </div>
</div>
    <?php
}
