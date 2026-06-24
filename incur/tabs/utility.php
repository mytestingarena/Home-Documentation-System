<?php
// tabs/utility.php — Utility Services tab

global $conn, $house_id, $hds_ui_settings;

$receipts_uploaded = false;
if (isset($_POST['upload_propane_receipt']) && !empty($_FILES['receipts']['name'][0])) {
    $receipts_uploaded = true;
}
?>

<h2>Utility Services</h2>

<div class="collapsible-list-toolbar">
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.utility-stack .collapsible-section', true)">Expand all</button>
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.utility-stack .collapsible-section', false)">Collapse all</button>
</div>

<div class="utility-stack">

    <?php if (hds_ui_section_enabled('utility-electric', $hds_ui_settings)): ?>
    <details class="utility-block utility-block--electric collapsible-section" id="utility-electric">
        <summary class="collapsible-summary">
            <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
            <i class="fas fa-bolt utility-icon" aria-hidden="true"></i>
            <h3>Electric Meter</h3>
        </summary>
        <div class="collapsible-body">
        <?php
        $meter = $conn->query("SELECT * FROM electric_meters WHERE house_id = $house_id")->fetch_assoc() ?? [];
        ?>
        <form method="post">
            Meter Number: <input type="text" name="meter_number" value="<?php echo htmlspecialchars($meter['meter_number'] ?? ''); ?>"><br><br>
            Company: <input type="text" name="company" value="<?php echo htmlspecialchars($meter['company'] ?? ''); ?>"><br><br>
            Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($meter['phone'] ?? ''); ?>"><br><br>
            <input type="submit" name="save_meter_account" value="Save Account Info">
        </form>

        <form method="post" class="utility-subform">
            <strong>Add Current/Next Bill:</strong><br><br>
            Amount Owed ($): <input type="number" step="0.01" name="amount_owed" placeholder="0.00" required><br><br>
            Due Date: <input type="date" name="due_date" required><br><br>
            <input type="submit" name="save_meter_bill" value="Save Bill">
        </form>

        <div class="billing-history">
            <h4>Bill History</h4>
            <?php
            $bills = $conn->query("SELECT * FROM utility_bills WHERE house_id = $house_id AND utility_type = 'electric' ORDER BY due_date DESC");
            if ($bills->num_rows > 0) {
                while ($bill = $bills->fetch_assoc()) {
                    $paid_class = $bill['is_paid'] ? 'paid' : 'unpaid';
                    echo "<div class='billing-entry $paid_class'>";
                    echo "<strong>$" . number_format($bill['amount_owed'], 2) . "</strong> — Due: " . $bill['due_date'] . "<br>";
                    echo "<div class='billing-entry-actions'>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='bill_id' value='" . $bill['id'] . "'>";
                    echo "Paid: <input type='checkbox' name='is_paid' " . ($bill['is_paid'] ? 'checked' : '') . " onchange='this.form.submit();'>";
                    echo "<input type='hidden' name='toggle_bill_paid' value='1'>";
                    echo "</form>";
                    echo "<form method='post' onsubmit='return confirm(\"Delete this bill?\");'>";
                    echo "<input type='hidden' name='bill_id' value='" . $bill['id'] . "'>";
                    echo "<input type='submit' name='delete_bill' value='Delete' class='delete-bill-btn'>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";
                }
            } else {
                echo "<p class='empty-note'>No electric bills recorded yet.</p>";
            }
            ?>
        </div>
        </div>
    </details>
    <?php endif; ?>

    <?php if (hds_ui_section_enabled('utility-generator', $hds_ui_settings)): ?>
    <details class="utility-block utility-block--generator collapsible-section" id="utility-generator">
        <summary class="collapsible-summary">
            <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
            <i class="fas fa-plug-circle-bolt utility-icon" aria-hidden="true"></i>
            <h3>Generator</h3>
        </summary>
        <div class="collapsible-body">
        <?php
        $generator = $conn->query("SELECT * FROM generators WHERE house_id = $house_id LIMIT 1")->fetch_assoc() ?? [];
        $fuel_type = ($generator['fuel_type'] ?? 'LP') === 'NG' ? 'NG' : 'LP';
        ?>
        <form method="post">
            Brand: <input type="text" name="generator_brand" value="<?php echo htmlspecialchars($generator['brand'] ?? ''); ?>"><br><br>
            Model: <input type="text" name="generator_model" value="<?php echo htmlspecialchars($generator['model'] ?? ''); ?>"><br><br>
            Serial Number: <input type="text" name="generator_sn" value="<?php echo htmlspecialchars($generator['sn'] ?? ''); ?>"><br><br>
            Efficiency / Rating: <input type="text" name="generator_efficiency" value="<?php echo htmlspecialchars($generator['efficiency'] ?? ''); ?>"><br><br>
            Power (kW): <input type="number" step="0.01" name="generator_kwh" value="<?php echo htmlspecialchars($generator['kwh'] ?? '0.00'); ?>"><br><br>
            Fuel Type:
            <select name="generator_fuel_type">
                <option value="LP" <?php echo $fuel_type === 'LP' ? 'selected' : ''; ?>>LP (Propane)</option>
                <option value="NG" <?php echo $fuel_type === 'NG' ? 'selected' : ''; ?>>NG (Natural Gas)</option>
            </select><br><br>
            <input type="submit" name="save_generator" value="Save Generator Info">
        </form>
        </div>
    </details>
    <?php endif; ?>

    <?php if (hds_ui_section_enabled('utility-water', $hds_ui_settings)): ?>
    <details class="utility-block utility-block--water collapsible-section" id="utility-water">
        <summary class="collapsible-summary">
            <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
            <i class="fas fa-droplet utility-icon" aria-hidden="true"></i>
            <h3>Water Utility</h3>
        </summary>
        <div class="collapsible-body">
        <?php
        $water = $conn->query("SELECT * FROM water_utilities WHERE house_id = $house_id LIMIT 1")->fetch_assoc() ?? [];
        ?>
        <form method="post">
            Account Number: <input type="text" name="account_number" value="<?php echo htmlspecialchars($water['account_number'] ?? ''); ?>"><br><br>
            Meter Number: <input type="text" name="meter_number" value="<?php echo htmlspecialchars($water['meter_number'] ?? ''); ?>"><br><br>
            Billing Frequency:
            <select name="billing_frequency">
                <option value="Monthly"   <?php echo ($water['billing_frequency'] ?? 'Monthly') === 'Monthly'   ? 'selected' : ''; ?>>Monthly</option>
                <option value="Quarterly" <?php echo ($water['billing_frequency'] ?? 'Monthly') === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                <option value="Annual"    <?php echo ($water['billing_frequency'] ?? 'Monthly') === 'Annual'    ? 'selected' : ''; ?>>Annual</option>
            </select><br><br>
            Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($water['phone'] ?? ''); ?>"><br><br>
            <input type="submit" name="save_water_account" value="Save Account Info">
        </form>

        <form method="post" class="utility-subform">
            <strong>Add Current/Next Bill:</strong><br><br>
            Amount Owed ($): <input type="number" step="0.01" name="amount_owed" placeholder="0.00" required><br><br>
            Due Date: <input type="date" name="due_date" required><br><br>
            <input type="submit" name="save_water_bill" value="Save Bill">
        </form>

        <div class="billing-history">
            <h4>Bill History (with Receipts)</h4>
            <?php
            $bills = $conn->query("SELECT * FROM utility_bills WHERE house_id = $house_id AND utility_type = 'water' ORDER BY due_date DESC");
            if ($bills->num_rows > 0) {
                while ($bill = $bills->fetch_assoc()) {
                    $paid_class = $bill['is_paid'] ? 'paid' : 'unpaid';
                    $bill_id = $bill['id'];
                    $receipts = $conn->query("SELECT * FROM water_receipts WHERE bill_id = $bill_id ORDER BY upload_date DESC");

                    echo "<div class='billing-entry $paid_class'>";
                    echo "<div class='billing-entry-top'>";
                    echo "<div><strong>$" . number_format($bill['amount_owed'], 2) . "</strong> — Due: " . $bill['due_date'] . "</div>";
                    $payment_method = $bill['payment_method'] ?? '';
                    echo "<div class='billing-entry-actions'>";
                    echo "<form method='post' class='billing-paid-form'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "<label class='billing-paid-label'>Paid: <input type='checkbox' name='is_paid' " . ($bill['is_paid'] ? 'checked' : '') . " onchange='this.form.submit();'></label>";
                    echo "<label class='billing-paid-label'>Paid with:";
                    echo "<select name='payment_method' class='billing-payment-select' onchange='this.form.submit();'>";
                    echo "<option value=''" . ($payment_method === '' ? ' selected' : '') . ">—</option>";
                    echo "<option value='debit'" . ($payment_method === 'debit' ? ' selected' : '') . ">Debit</option>";
                    echo "<option value='credit'" . ($payment_method === 'credit' ? ' selected' : '') . ">Credit</option>";
                    echo "<option value='check'" . ($payment_method === 'check' ? ' selected' : '') . ">Check</option>";
                    echo "</select></label>";
                    echo "<input type='hidden' name='toggle_bill_paid' value='1'>";
                    echo "</form>";
                    echo "<form method='post' onsubmit='return confirm(\"Delete this bill and all receipts?\");'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "<input type='submit' name='delete_bill' value='Delete' class='delete-bill-btn'>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";

                    echo "<form method='post' enctype='multipart/form-data' class='receipt-upload-form'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "<input type='file' name='receipts[]' accept='.pdf' multiple>";
                    echo "<small>Upload PDF receipt(s) (max 5)</small><br>";
                    echo "<input type='submit' name='upload_receipt' value='Upload Receipt(s)'>";
                    echo "</form>";

                    if ($receipts->num_rows > 0 || $receipts_uploaded) {
                        if ($receipts_uploaded) {
                            $receipts = $conn->query("SELECT * FROM water_receipts WHERE bill_id = $bill_id ORDER BY upload_date DESC");
                        }
                        echo "<div class='receipt-list'>";
                        echo "<strong>Uploaded Receipts:</strong><br>";
                        while ($receipt = $receipts->fetch_assoc()) {
                            $rfn = htmlspecialchars($receipt['filename']);
                            $rpath = "uploads/receipts/" . $rfn;
                            $rdate = date('M j, Y g:i A', strtotime($receipt['upload_date']));
                            echo "<a href='$rpath' target='_blank' class='receipt-link'>$rfn - $rdate</a>";
                        }
                        echo "</div>";
                    }

                    echo "</div>";
                }
            } else {
                echo "<p class='empty-note'>No water bills recorded yet.</p>";
            }
            ?>
        </div>
        </div>
    </details>
    <?php endif; ?>

    <?php if (hds_ui_section_enabled('utility-propane', $hds_ui_settings)): ?>
    <details class="utility-block utility-block--propane collapsible-section" id="utility-propane">
        <summary class="collapsible-summary">
            <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
            <i class="fas fa-fire-flame-simple utility-icon" aria-hidden="true"></i>
            <h3>Propane</h3>
        </summary>
        <div class="collapsible-body">
        <?php
        $propane = $conn->query("SELECT * FROM propane_utilities WHERE house_id = $house_id LIMIT 1")->fetch_assoc() ?? [];
        ?>
        <form method="post">
            Gallons: <input type="number" step="0.1" name="gallons" value="<?php echo htmlspecialchars($propane['gallons'] ?? ''); ?>"><br><br>
            Provider: <input type="text" name="provider" value="<?php echo htmlspecialchars($propane['provider'] ?? ''); ?>"><br><br>
            Tank SN: <input type="text" name="tank_sn" value="<?php echo htmlspecialchars($propane['tank_sn'] ?? ''); ?>"><br><br>
            Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($propane['phone'] ?? ''); ?>"><br><br>
            <input type="submit" name="save_propane_account" value="Save Propane Info">
        </form>

        <form method="post" class="utility-subform">
            <strong>Add Current/Next Bill:</strong><br><br>
            Amount Owed ($): <input type="number" step="0.01" name="amount_owed" placeholder="0.00" required><br><br>
            Due Date: <input type="date" name="due_date" required><br><br>
            <input type="submit" name="save_propane_bill" value="Save Bill">
        </form>

        <div class="billing-history">
            <h4>Bill History (with Receipts)</h4>
            <?php
            $bills = $conn->query("SELECT * FROM utility_bills WHERE house_id = $house_id AND utility_type = 'propane' ORDER BY due_date DESC");
            if ($bills->num_rows > 0) {
                while ($bill = $bills->fetch_assoc()) {
                    $paid_class = $bill['is_paid'] ? 'paid' : 'unpaid';
                    $bill_id = $bill['id'];
                    $receipts = $conn->query("SELECT * FROM propane_receipts WHERE bill_id = $bill_id ORDER BY upload_date DESC");

                    echo "<div class='billing-entry $paid_class'>";
                    echo "<div class='billing-entry-top'>";
                    echo "<div><strong>$" . number_format($bill['amount_owed'], 2) . "</strong> — Due: " . $bill['due_date'] . "</div>";
                    echo "<div class='billing-entry-actions'>";
                    echo "<form method='post'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "Paid: <input type='checkbox' name='is_paid' " . ($bill['is_paid'] ? 'checked' : '') . " onchange='this.form.submit();'>";
                    echo "<input type='hidden' name='toggle_bill_paid' value='1'>";
                    echo "</form>";
                    echo "<form method='post' onsubmit='return confirm(\"Delete this bill and all receipts?\");'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "<input type='submit' name='delete_bill' value='Delete' class='delete-bill-btn'>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";

                    echo "<form method='post' enctype='multipart/form-data' class='receipt-upload-form'>";
                    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
                    echo "<input type='file' name='receipts[]' accept='.pdf' multiple>";
                    echo "<small>Upload PDF receipt(s) (max 5)</small><br>";
                    echo "<input type='submit' name='upload_propane_receipt' value='Upload Receipt(s)'>";
                    echo "</form>";

                    if ($receipts->num_rows > 0 || $receipts_uploaded) {
                        if ($receipts_uploaded) {
                            $receipts = $conn->query("SELECT * FROM propane_receipts WHERE bill_id = $bill_id ORDER BY upload_date DESC");
                        }
                        echo "<div class='receipt-list'>";
                        echo "<strong>Uploaded Receipts:</strong><br>";
                        while ($receipt = $receipts->fetch_assoc()) {
                            $rfn = htmlspecialchars($receipt['filename']);
                            $rpath = "uploads/receipts/" . $rfn;
                            $rdate = date('M j, Y g:i A', strtotime($receipt['upload_date']));
                            echo "<a href='$rpath' target='_blank' class='receipt-link'>$rfn - $rdate</a>";
                        }
                        echo "</div>";
                    }

                    echo "</div>";
                }
            } else {
                echo "<p class='empty-note'>No propane bills recorded yet.</p>";
            }
            ?>
        </div>
        </div>
    </details>
    <?php endif; ?>

</div>