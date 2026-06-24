<?php
// house.php — COMPLETE with propane receipt upload handler added

include 'config.php';

if (!defined('WIFI_TAB_PASSWORD')) {
    define('WIFI_TAB_PASSWORD', 'change_me_before_deploy');
}

if (!defined('ADMIN_TAB_PASSWORD')) {
    define('ADMIN_TAB_PASSWORD', WIFI_TAB_PASSWORD);
}

require_once __DIR__ . '/includes/ui-settings.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$house_id = intval($_GET['id'] ?? 0);
if ($house_id <= 0) {
    die("Invalid house ID");
}

// Check if house exists
$sql = "SELECT name, address, latitude, longitude, tax_number, map_zoom, google_embed_src FROM houses WHERE id = $house_id LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    die("House ID $house_id not found in database");
}
$house = $result->fetch_assoc();
$house_name = htmlspecialchars($house['name'] ?? 'Unknown House');

$valid_tabs = ['permanent', 'utility', 'household', 'tools', 'maintenance', 'media', 'designs', 'manuals', 'map', 'wifi', 'projects', 'admin'];
$hds_ui_settings = hds_ui_load_settings($conn, $house_id);
$active_tab = $_GET['tab'] ?? 'permanent';
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'permanent';
}
if ($active_tab !== 'admin' && !hds_ui_tab_enabled($active_tab, $hds_ui_settings)) {
    $active_tab = hds_ui_first_enabled_tab($hds_ui_settings);
}

function house_redirect(int $house_id, string $tab = 'permanent', $open_section = 0, string $open_param = 'open_equipment'): void {
    global $valid_tabs;
    if (!in_array($tab, $valid_tabs, true)) {
        $tab = 'permanent';
    }
    $url = 'house.php?id=' . $house_id . '&tab=' . urlencode($tab);
    if ($open_param !== '' && $open_section !== 0 && $open_section !== '') {
        $url .= '&' . urlencode($open_param) . '=' . urlencode((string)$open_section);
    }
    header('Location: ' . $url);
    exit;
}

// Upload limits
ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '256M');
ini_set('max_file_uploads', '20');

// POST handling – redirect to CURRENT URL after any change
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // WIFI TAB - UNLOCK / LOCK
    if (isset($_POST['wifi_unlock'])) {
        $access_password = $_POST['wifi_access_password'] ?? '';
        if (hash_equals(WIFI_TAB_PASSWORD, $access_password)) {
            $_SESSION['wifi_unlocked'] = true;
            unset($_SESSION['wifi_error']);
        } else {
            $_SESSION['wifi_error'] = 'Incorrect password. Please try again.';
        }
        house_redirect($house_id, 'wifi');
    }

    if (isset($_POST['wifi_lock'])) {
        unset($_SESSION['wifi_unlocked']);
        house_redirect($house_id, 'wifi');
    }

    // ADMIN TAB - UNLOCK / LOCK
    if (isset($_POST['admin_unlock'])) {
        $access_password = $_POST['admin_access_password'] ?? '';
        if (hash_equals(ADMIN_TAB_PASSWORD, $access_password)) {
            $_SESSION['admin_unlocked'] = true;
            unset($_SESSION['admin_error']);
        } else {
            $_SESSION['admin_error'] = 'Incorrect password. Please try again.';
        }
        house_redirect($house_id, 'admin');
    }

    if (isset($_POST['admin_lock'])) {
        unset($_SESSION['admin_unlocked']);
        house_redirect($house_id, 'admin');
    }

    if (isset($_POST['save_ui_settings']) && !empty($_SESSION['admin_unlocked'])) {
        $enabled_keys = $_POST['ui_enabled'] ?? [];
        if (!is_array($enabled_keys)) {
            $enabled_keys = [];
        }
        $allowed = array_flip(hds_ui_all_setting_keys());
        $enabled_lookup = [];
        foreach ($enabled_keys as $key) {
            $key = (string)$key;
            if (isset($allowed[$key])) {
                $enabled_lookup[$key] = true;
            }
        }

        $conn->query("DELETE FROM house_ui_settings WHERE house_id = $house_id");
        foreach (hds_ui_all_setting_keys() as $key) {
            $enabled = isset($enabled_lookup[$key]) ? 1 : 0;
            $safe_key = mysqli_real_escape_string($conn, $key);
            $conn->query("INSERT INTO house_ui_settings (house_id, setting_key, enabled)
                          VALUES ($house_id, '$safe_key', $enabled)");
        }

        header('Location: house.php?id=' . $house_id . '&tab=admin&admin_saved=1');
        exit;
    }

    // PERMANENT ITEMS UPDATE
    if (isset($_POST['update_permanent'])) {
        $item_types = ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac'];
        $success = true;

        foreach ($item_types as $type) {
            $brand       = mysqli_real_escape_string($conn, $_POST["{$type}_brand"] ?? '');
            $model       = mysqli_real_escape_string($conn, $_POST["{$type}_model"] ?? '');
            $sn          = mysqli_real_escape_string($conn, $_POST["{$type}_sn"] ?? '');
            $efficiency  = mysqli_real_escape_string($conn, $_POST["{$type}_efficiency"] ?? '');
            $kwh         = floatval($_POST["{$type}_kwh"] ?? 0);
            $capacity    = intval($_POST["{$type}_capacity"] ?? 0);

            $sql = "INSERT INTO permanent_items 
                    (house_id, item_type, brand, model, sn, efficiency, kwh, capacity) 
                    VALUES ($house_id, '$type', '$brand', '$model', '$sn', '$efficiency', $kwh, $capacity)
                    ON DUPLICATE KEY UPDATE 
                    brand = VALUES(brand), model = VALUES(model), sn = VALUES(sn), 
                    efficiency = VALUES(efficiency), kwh = VALUES(kwh), capacity = VALUES(capacity)";
            
            if (!$conn->query($sql)) {
                $success = false;
                error_log("Permanent Items update failed for $type: " . $conn->error);
            }
        }

        house_redirect($house_id, 'permanent');
    }

    // ELECTRIC METER - SAVE ACCOUNT INFO
    if (isset($_POST['save_meter_account'])) {
        $meter_number = mysqli_real_escape_string($conn, $_POST['meter_number'] ?? '');
        $company      = mysqli_real_escape_string($conn, $_POST['company'] ?? '');
        $phone        = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $existing = $conn->query("SELECT id FROM electric_meters WHERE house_id = $house_id LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $conn->query("UPDATE electric_meters SET meter_number='$meter_number', company='$company', phone='$phone' WHERE house_id=$house_id");
        } else {
            $conn->query("INSERT INTO electric_meters (house_id, meter_number, company, phone) VALUES ($house_id, '$meter_number', '$company', '$phone')");
        }
        house_redirect($house_id, 'utility');
    }

    // ELECTRIC METER - SAVE NEW BILL
    if (isset($_POST['save_meter_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                          VALUES ($house_id, 'electric', $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'utility');
    }

    // GENERATOR - SAVE INFO
    if (isset($_POST['save_generator'])) {
        $brand      = mysqli_real_escape_string($conn, $_POST['generator_brand'] ?? '');
        $model      = mysqli_real_escape_string($conn, $_POST['generator_model'] ?? '');
        $sn         = mysqli_real_escape_string($conn, $_POST['generator_sn'] ?? '');
        $efficiency = mysqli_real_escape_string($conn, $_POST['generator_efficiency'] ?? '');
        $kwh        = floatval($_POST['generator_kwh'] ?? 0);
        $fuel_type  = ($_POST['generator_fuel_type'] ?? 'LP') === 'NG' ? 'NG' : 'LP';

        $existing = $conn->query("SELECT id FROM generators WHERE house_id = $house_id LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $conn->query("UPDATE generators SET brand='$brand', model='$model', sn='$sn', efficiency='$efficiency', kwh=$kwh, fuel_type='$fuel_type'
                          WHERE house_id=$house_id");
        } else {
            $conn->query("INSERT INTO generators (house_id, brand, model, sn, efficiency, kwh, fuel_type)
                          VALUES ($house_id, '$brand', '$model', '$sn', '$efficiency', $kwh, '$fuel_type')");
        }
        house_redirect($house_id, 'utility');
    }

    // WATER UTILITY - SAVE ACCOUNT INFO
    if (isset($_POST['save_water_account'])) {
        $account_number    = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
        $meter_number      = mysqli_real_escape_string($conn, $_POST['meter_number'] ?? '');
        $billing_frequency = in_array($_POST['billing_frequency'] ?? 'Monthly', ['Monthly', 'Quarterly', 'Annual'], true)
            ? $_POST['billing_frequency'] : 'Monthly';
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $conn->query("INSERT INTO water_utilities (house_id, account_number, meter_number, billing_frequency, phone)
                      VALUES ($house_id, '$account_number', '$meter_number', '$billing_frequency', '$phone')
                      ON DUPLICATE KEY UPDATE
                      account_number=VALUES(account_number),
                      meter_number=VALUES(meter_number),
                      billing_frequency=VALUES(billing_frequency),
                      phone=VALUES(phone)");
        house_redirect($house_id, 'utility');
    }

    // WATER UTILITY - SAVE NEW BILL
    if (isset($_POST['save_water_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                          VALUES ($house_id, 'water', $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'utility');
    }

    // UTILITY BILL - TOGGLE PAID STATUS
    if (isset($_POST['toggle_bill_paid'])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $is_paid = isset($_POST['is_paid']) ? 1 : 0;
        if ($bill_id > 0) {
            if (isset($_POST['payment_method'])) {
                $payment_method = $_POST['payment_method'] ?? '';
                $allowed_methods = ['debit', 'credit', 'check', ''];
                if (!in_array($payment_method, $allowed_methods, true)) {
                    $payment_method = '';
                }
                $payment_sql = $payment_method === ''
                    ? 'NULL'
                    : "'" . mysqli_real_escape_string($conn, $payment_method) . "'";
                $conn->query("UPDATE utility_bills SET is_paid = $is_paid, payment_method = $payment_sql WHERE id = $bill_id AND house_id = $house_id");
            } else {
                $conn->query("UPDATE utility_bills SET is_paid = $is_paid WHERE id = $bill_id AND house_id = $house_id");
            }
        }
        house_redirect($house_id, 'utility');
    }

    // UTILITY BILL - DELETE (receipt files removed first)
    if (isset($_POST['delete_bill'])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        if ($bill_id > 0) {
            foreach (['water_receipts', 'propane_receipts'] as $receipt_table) {
                $receipts = $conn->query("SELECT filename FROM $receipt_table WHERE bill_id = $bill_id");
                if ($receipts) {
                    while ($receipt = $receipts->fetch_assoc()) {
                        $path = 'uploads/receipts/' . $receipt['filename'];
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                }
            }
            $conn->query("DELETE FROM utility_bills WHERE id = $bill_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'utility');
    }

    // BREAKER PANEL - ADD
    if (isset($_POST['add_breaker_panel'])) {
        $panel_name = mysqli_real_escape_string($conn, $_POST['new_panel_name'] ?? 'Main Panel');
        $panel_size = intval($_POST['new_panel_size'] ?? 24);
        if (!in_array($panel_size, [6, 12, 24, 28], true)) {
            $panel_size = 24;
        }
        $conn->query("INSERT INTO electric_panels (house_id, name, size) VALUES ($house_id, '$panel_name', $panel_size)");
        house_redirect($house_id, 'permanent', (int)$conn->insert_id, 'open_panel');
    }

    // BREAKER PANEL - SAVE LABELS
    if (isset($_POST['save_breakers'])) {
        $panel_id = intval($_POST['panel_id'] ?? 0);
        $panel = $conn->query("SELECT size FROM electric_panels WHERE id = $panel_id AND house_id = $house_id LIMIT 1")->fetch_assoc();
        if ($panel) {
            $panel_size = intval($panel['size']);
            $max_rows   = (int)ceil($panel_size / 2);
            for ($row = 1; $row <= $max_rows; $row++) {
                $pairs = [
                    [1, mysqli_real_escape_string($conn, $_POST["left_room_$row"] ?? ''), intval($_POST["left_amp_$row"] ?? 0)],
                    [2, mysqli_real_escape_string($conn, $_POST["right_room_$row"] ?? ''), intval($_POST["right_amp_$row"] ?? 0)],
                ];
                foreach ($pairs as [$col, $room, $amp]) {
                    $existing = $conn->query("SELECT id FROM breakers WHERE panel_id=$panel_id AND column_num=$col AND row_num=$row LIMIT 1");
                    if ($existing && $existing->num_rows > 0) {
                        $conn->query("UPDATE breakers SET room='$room', amp=$amp WHERE panel_id=$panel_id AND column_num=$col AND row_num=$row");
                    } else {
                        $conn->query("INSERT INTO breakers (panel_id, column_num, row_num, room, amp) VALUES ($panel_id, $col, $row, '$room', $amp)");
                    }
                }
            }
        }
        house_redirect($house_id, 'permanent', $panel_id, 'open_panel');
    }

    // BREAKER PANEL - DELETE
    if (isset($_POST['delete_panel'])) {
        $panel_id = intval($_POST['panel_id'] ?? 0);
        $panel = $conn->query("SELECT id FROM electric_panels WHERE id = $panel_id AND house_id = $house_id LIMIT 1")->fetch_assoc();
        if ($panel) {
            $conn->query("DELETE FROM breakers WHERE panel_id = $panel_id");
            $conn->query("DELETE FROM electric_panels WHERE id = $panel_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'permanent');
    }

    // HOUSEHOLD ITEMS
    if (isset($_POST['add_household'])) {
        $type  = mysqli_real_escape_string($conn, $_POST['type'] ?? 'TV');
        $brand = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
        $sn    = mysqli_real_escape_string($conn, $_POST['sn'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        $conn->query("INSERT INTO household_items (house_id, type, brand, model, sn, notes)
                      VALUES ($house_id, '$type', '$brand', '$model', '$sn', '$notes')");
        house_redirect($house_id, 'household');
    }

    if (isset($_POST['update_household'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $brand   = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $model   = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
        $sn      = mysqli_real_escape_string($conn, $_POST['sn'] ?? '');
        $notes   = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        $conn->query("UPDATE household_items SET brand='$brand', model='$model', sn='$sn', notes='$notes'
                      WHERE id=$item_id AND house_id=$house_id");
        house_redirect($house_id, 'household');
    }

    if (isset($_POST['delete_household'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $conn->query("DELETE FROM household_items WHERE id=$item_id AND house_id=$house_id");
        house_redirect($house_id, 'household');
    }

    // TOOLS
    if (isset($_POST['add_tool']) && !empty(trim($_POST['tool_name'] ?? ''))) {
        $tool_name        = mysqli_real_escape_string($conn, trim($_POST['tool_name']));
        $tool_description = mysqli_real_escape_string($conn, trim($_POST['tool_description'] ?? ''));
        $power_type       = $_POST['power_type'] ?? 'manual';
        $tool_house_id    = intval($_POST['tool_house_id'] ?? $house_id);
        $allowed_types    = ['battery', 'ac', 'pneumatic', 'manual', 'na'];
        if (!in_array($power_type, $allowed_types, true)) {
            $power_type = 'manual';
        }
        $house_check = $conn->query("SELECT id FROM houses WHERE id = $tool_house_id LIMIT 1");
        if ($house_check && $house_check->num_rows > 0) {
            $conn->query("INSERT INTO tools (house_id, name, power_type, description)
                          VALUES ($tool_house_id, '$tool_name', '$power_type', '$tool_description')");
            if ($tool_house_id === $house_id) {
                house_redirect($house_id, 'tools', (int)$conn->insert_id, 'open_tool');
            }
        }
        house_redirect($house_id, 'tools');
    }

    if (isset($_POST['update_tool'])) {
        $tool_id       = intval($_POST['tool_id'] ?? 0);
        $tool_name        = mysqli_real_escape_string($conn, trim($_POST['tool_name'] ?? ''));
        $tool_description = mysqli_real_escape_string($conn, trim($_POST['tool_description'] ?? ''));
        $power_type       = $_POST['power_type'] ?? 'manual';
        $tool_house_id    = intval($_POST['tool_house_id'] ?? $house_id);
        $allowed_types    = ['battery', 'ac', 'pneumatic', 'manual', 'na'];
        if (!in_array($power_type, $allowed_types, true)) {
            $power_type = 'manual';
        }
        if ($tool_id > 0 && $tool_name !== '') {
            $house_check = $conn->query("SELECT id FROM houses WHERE id = $tool_house_id LIMIT 1");
            if ($house_check && $house_check->num_rows > 0) {
                $conn->query("UPDATE tools SET name='$tool_name', power_type='$power_type', house_id=$tool_house_id, description='$tool_description'
                              WHERE id=$tool_id");
            }
        }
        house_redirect($house_id, 'tools', $tool_id, 'open_tool');
    }

    if (isset($_POST['delete_tool'])) {
        $tool_id = intval($_POST['tool_id'] ?? 0);
        if ($tool_id > 0) {
            $conn->query("DELETE FROM tools WHERE id=$tool_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'tools');
    }

    // MAINTENANCE EQUIPMENT
    $maint_categories = ['atv', 'boat', 'lawnmower', 'other'];
    if (isset($_POST['add_equipment']) && !empty(trim($_POST['equipment_name'] ?? ''))) {
        $eq_name     = mysqli_real_escape_string($conn, trim($_POST['equipment_name']));
        $eq_category = in_array($_POST['equipment_category'] ?? 'other', $maint_categories, true) ? $_POST['equipment_category'] : 'other';
        $eq_notes    = mysqli_real_escape_string($conn, trim($_POST['equipment_notes'] ?? ''));
        $conn->query("INSERT INTO maintenance_equipment (house_id, name, category, notes)
                      VALUES ($house_id, '$eq_name', '$eq_category', '$eq_notes')");
        house_redirect($house_id, 'maintenance', (int)$conn->insert_id);
    }
    if (isset($_POST['update_equipment'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $eq_name      = mysqli_real_escape_string($conn, trim($_POST['equipment_name'] ?? ''));
        $eq_category  = in_array($_POST['equipment_category'] ?? 'other', $maint_categories, true) ? $_POST['equipment_category'] : 'other';
        $eq_notes     = mysqli_real_escape_string($conn, trim($_POST['equipment_notes'] ?? ''));
        if ($equipment_id > 0 && $eq_name !== '') {
            $conn->query("UPDATE maintenance_equipment SET name='$eq_name', category='$eq_category', notes='$eq_notes'
                          WHERE id=$equipment_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_equipment'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($equipment_id > 0) {
            $conn->query("DELETE FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance');
    }
    if (isset($_POST['add_fluid']) && !empty(trim($_POST['fluid_name'] ?? ''))) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $fluid_name   = mysqli_real_escape_string($conn, trim($_POST['fluid_name']));
        $fluid_spec   = mysqli_real_escape_string($conn, trim($_POST['fluid_spec'] ?? ''));
        $fluid_cap    = mysqli_real_escape_string($conn, trim($_POST['fluid_capacity'] ?? ''));
        $fluid_notes  = mysqli_real_escape_string($conn, trim($_POST['fluid_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_fluids (equipment_id, fluid_name, specification, capacity, notes)
                          VALUES ($equipment_id, '$fluid_name', '$fluid_spec', '$fluid_cap', '$fluid_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_fluid'])) {
        $fluid_id     = intval($_POST['fluid_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $fluid_name   = mysqli_real_escape_string($conn, trim($_POST['fluid_name'] ?? ''));
        $fluid_spec   = mysqli_real_escape_string($conn, trim($_POST['fluid_spec'] ?? ''));
        $fluid_cap    = mysqli_real_escape_string($conn, trim($_POST['fluid_capacity'] ?? ''));
        $fluid_notes  = mysqli_real_escape_string($conn, trim($_POST['fluid_notes'] ?? ''));
        if ($fluid_id > 0 && $fluid_name !== '') {
            $conn->query("UPDATE maintenance_fluids f
                          INNER JOIN maintenance_equipment e ON f.equipment_id = e.id
                          SET f.fluid_name='$fluid_name', f.specification='$fluid_spec', f.capacity='$fluid_cap', f.notes='$fluid_notes'
                          WHERE f.id=$fluid_id AND f.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_fluid'])) {
        $fluid_id     = intval($_POST['fluid_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($fluid_id > 0) {
            $conn->query("DELETE f FROM maintenance_fluids f
                          INNER JOIN maintenance_equipment e ON f.equipment_id = e.id
                          WHERE f.id=$fluid_id AND f.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['add_part']) && !empty(trim($_POST['part_name'] ?? ''))) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $part_name    = mysqli_real_escape_string($conn, trim($_POST['part_name']));
        $part_number  = mysqli_real_escape_string($conn, trim($_POST['part_number'] ?? ''));
        $part_notes   = mysqli_real_escape_string($conn, trim($_POST['part_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_parts (equipment_id, part_name, part_number, notes)
                          VALUES ($equipment_id, '$part_name', '$part_number', '$part_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_part'])) {
        $part_id      = intval($_POST['part_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $part_name    = mysqli_real_escape_string($conn, trim($_POST['part_name'] ?? ''));
        $part_number  = mysqli_real_escape_string($conn, trim($_POST['part_number'] ?? ''));
        $part_notes   = mysqli_real_escape_string($conn, trim($_POST['part_notes'] ?? ''));
        if ($part_id > 0 && $part_name !== '') {
            $conn->query("UPDATE maintenance_parts p
                          INNER JOIN maintenance_equipment e ON p.equipment_id = e.id
                          SET p.part_name='$part_name', p.part_number='$part_number', p.notes='$part_notes'
                          WHERE p.id=$part_id AND p.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_part'])) {
        $part_id      = intval($_POST['part_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($part_id > 0) {
            $conn->query("DELETE p FROM maintenance_parts p
                          INNER JOIN maintenance_equipment e ON p.equipment_id = e.id
                          WHERE p.id=$part_id AND p.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['add_log']) && !empty(trim($_POST['log_description'] ?? '')) && !empty($_POST['log_date'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $log_date     = mysqli_real_escape_string($conn, $_POST['log_date']);
        $log_desc     = mysqli_real_escape_string($conn, trim($_POST['log_description']));
        $log_hours    = mysqli_real_escape_string($conn, trim($_POST['log_hours'] ?? ''));
        $log_notes    = mysqli_real_escape_string($conn, trim($_POST['log_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_log (equipment_id, log_date, description, hours_mileage, notes)
                          VALUES ($equipment_id, '$log_date', '$log_desc', '$log_hours', '$log_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_log'])) {
        $log_id       = intval($_POST['log_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $log_date     = mysqli_real_escape_string($conn, $_POST['log_date'] ?? '');
        $log_desc     = mysqli_real_escape_string($conn, trim($_POST['log_description'] ?? ''));
        $log_hours    = mysqli_real_escape_string($conn, trim($_POST['log_hours'] ?? ''));
        $log_notes    = mysqli_real_escape_string($conn, trim($_POST['log_notes'] ?? ''));
        if ($log_id > 0 && $log_date !== '' && $log_desc !== '') {
            $conn->query("UPDATE maintenance_log l
                          INNER JOIN maintenance_equipment e ON l.equipment_id = e.id
                          SET l.log_date='$log_date', l.description='$log_desc', l.hours_mileage='$log_hours', l.notes='$log_notes'
                          WHERE l.id=$log_id AND l.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_log'])) {
        $log_id       = intval($_POST['log_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($log_id > 0) {
            $conn->query("DELETE l FROM maintenance_log l
                          INNER JOIN maintenance_equipment e ON l.equipment_id = e.id
                          WHERE l.id=$log_id AND l.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }

    // WIFI NETWORKS (requires unlocked session)
    if (!empty($_SESSION['wifi_unlocked'])) {
        if (isset($_POST['add_wifi']) && !empty(trim($_POST['network_name'] ?? ''))) {
            $network_name  = mysqli_real_escape_string($conn, trim($_POST['network_name']));
            $wifi_password = mysqli_real_escape_string($conn, $_POST['wifi_password'] ?? '');
            $wifi_notes    = mysqli_real_escape_string($conn, trim($_POST['wifi_notes'] ?? ''));
            $conn->query("INSERT INTO wifi_networks (house_id, network_name, password, notes)
                          VALUES ($house_id, '$network_name', '$wifi_password', '$wifi_notes')");
            house_redirect($house_id, 'wifi');
        }

        if (isset($_POST['update_wifi'])) {
            $wifi_id       = intval($_POST['wifi_id'] ?? 0);
            $network_name  = mysqli_real_escape_string($conn, trim($_POST['network_name'] ?? ''));
            $wifi_password = mysqli_real_escape_string($conn, $_POST['wifi_password'] ?? '');
            $wifi_notes    = mysqli_real_escape_string($conn, trim($_POST['wifi_notes'] ?? ''));
            if ($wifi_id > 0 && $network_name !== '') {
                $conn->query("UPDATE wifi_networks SET network_name='$network_name', password='$wifi_password', notes='$wifi_notes'
                              WHERE id=$wifi_id AND house_id=$house_id");
            }
            house_redirect($house_id, 'wifi');
        }

        if (isset($_POST['delete_wifi'])) {
            $wifi_id = intval($_POST['wifi_id'] ?? 0);
            if ($wifi_id > 0) {
                $conn->query("DELETE FROM wifi_networks WHERE id=$wifi_id AND house_id=$house_id");
            }
            house_redirect($house_id, 'wifi');
        }
    }

    // PROPERTY TAX BILLS
    if (isset($_POST['save_tax_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO property_taxes (house_id, amount_owed, due_date, is_paid)
                          VALUES ($house_id, $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'map');
    }

    if (isset($_POST['toggle_tax_paid'])) {
        $tax_id       = intval($_POST['tax_id'] ?? 0);
        $is_paid      = isset($_POST['is_paid']) ? 1 : 0;
        $check_number = mysqli_real_escape_string($conn, trim($_POST['check_number'] ?? ''));
        if ($tax_id > 0) {
            $conn->query("UPDATE property_taxes SET is_paid = $is_paid, check_number = '$check_number' WHERE id = $tax_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'map');
    }

    if (isset($_POST['delete_tax_bill'])) {
        $tax_id = intval($_POST['tax_id'] ?? 0);
        if ($tax_id > 0) {
            $conn->query("DELETE FROM property_taxes WHERE id = $tax_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'map');
    }

    // DESIGNS UPLOAD
    if (isset($_POST['upload_designs']) && !empty($_FILES['designs']['name'][0])) {
        include __DIR__ . '/tabs/designs-upload.php';
        house_redirect($house_id, 'designs');
    }

    // DESIGNS DELETE
    if (isset($_POST['delete_design'])) {
        include __DIR__ . '/tabs/designs-delete.php';
        house_redirect($house_id, 'designs');
    }

    // MEDIA/PHOTOS UPLOAD
    if (isset($_POST['upload_photo']) && !empty($_FILES['photos']['name'][0])) {
        include __DIR__ . '/tabs/media-upload.php';
        house_redirect($house_id, 'media');
    }

    // MEDIA/PHOTOS DELETE
    if (isset($_POST['delete_photo'])) {
        $photo_id = intval($_POST['photo_id'] ?? 0);
        $house_id_post = intval($_POST['house_id'] ?? 0);

        if ($photo_id > 0 && $house_id_post == $house_id) {
            $stmt = $conn->prepare("SELECT filename FROM photos WHERE id = ? AND house_id = ?");
            $stmt->bind_param("ii", $photo_id, $house_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $filename = $row['filename'];
                $path = "uploads/photos/" . $filename;

                if (file_exists($path)) {
                    unlink($path);
                }

                $delete_stmt = $conn->prepare("DELETE FROM photos WHERE id = ? AND house_id = ?");
                $delete_stmt->bind_param("ii", $photo_id, $house_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            $stmt->close();
        }

        house_redirect($house_id, 'media');
    }

    // USER MANUALS UPLOAD
    if (isset($_POST['upload_manuals']) && !empty($_FILES['manuals']['name'][0])) {
        $target_dir = "uploads/manuals/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 10;
        $allowed = ['pdf', 'doc', 'docx'];

        foreach ($_FILES['manuals']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['manuals']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['manuals']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO user_manuals (house_id, filename, upload_date) VALUES ($house_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }
        house_redirect($house_id, 'manuals');
    }

    // USER MANUALS DELETE
    if (isset($_POST['delete_manual'])) {
        $manual_id = intval($_POST['manual_id']);

        $stmt = $conn->prepare("SELECT filename FROM user_manuals WHERE id = ? AND house_id = ?");
        $stmt->bind_param("ii", $manual_id, $house_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $filename = $row['filename'];
            $path = "uploads/manuals/" . $filename;

            if (file_exists($path)) {
                unlink($path);
            }

            $delete_stmt = $conn->prepare("DELETE FROM user_manuals WHERE id = ?");
            $delete_stmt->bind_param("i", $manual_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        $stmt->close();

        house_redirect($house_id, 'manuals');
    }

    // MAP UPDATE
    if (isset($_POST['update_map'])) {
        $new_address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        $new_lat = floatval($_POST['latitude'] ?? 0);
        $new_lng = floatval($_POST['longitude'] ?? 0);
        $new_tax = mysqli_real_escape_string($conn, $_POST['tax_number'] ?? '');
        $new_zoom = intval($_POST['map_zoom'] ?? 17);
        $new_embed_src = mysqli_real_escape_string($conn, $_POST['google_embed_src'] ?? '');

        $sql = "UPDATE houses SET 
                address = '$new_address',
                latitude = $new_lat,
                longitude = $new_lng,
                tax_number = '$new_tax',
                map_zoom = $new_zoom,
                google_embed_src = '$new_embed_src'
                WHERE id = $house_id";
        $conn->query($sql);

        house_redirect($house_id, 'map');
    }

    // WATER BILL RECEIPT UPLOAD
    if (isset($_POST['upload_receipt']) && !empty($_FILES['receipts']['name'][0])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $target_dir = "uploads/receipts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 5;
        $allowed = ['pdf'];

        foreach ($_FILES['receipts']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['receipts']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['receipts']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO water_receipts (bill_id, filename, upload_date) VALUES ($bill_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }

        house_redirect($house_id, 'utility');
    }

    // PROPANE ACCOUNT SAVE
    if (isset($_POST['save_propane_account'])) {
        $gallons   = floatval($_POST['gallons'] ?? 0);
        $provider  = mysqli_real_escape_string($conn, $_POST['provider'] ?? '');
        $tank_sn   = mysqli_real_escape_string($conn, $_POST['tank_sn'] ?? '');
        $phone     = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $sql = "INSERT INTO propane_utilities (house_id, gallons, provider, tank_sn, phone)
                VALUES ($house_id, $gallons, '$provider', '$tank_sn', '$phone')
                ON DUPLICATE KEY UPDATE
                gallons = $gallons, provider = '$provider', tank_sn = '$tank_sn', phone = '$phone'";
        $conn->query($sql);

        house_redirect($house_id, 'utility');
    }

    // PROPANE BILL SAVE
    if (isset($_POST['save_propane_bill'])) {
        $amount_owed = floatval($_POST['amount_owed'] ?? 0);
        $due_date    = mysqli_real_escape_string($conn, $_POST['due_date'] ?? '');

        $sql = "INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                VALUES ($house_id, 'propane', $amount_owed, '$due_date', 0)";
        $conn->query($sql);

        house_redirect($house_id, 'utility');
    }

    // PROPANE BILL RECEIPT UPLOAD
    if (isset($_POST['upload_propane_receipt']) && !empty($_FILES['receipts']['name'][0])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $target_dir = "uploads/receipts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 5;
        $allowed = ['pdf'];

        foreach ($_FILES['receipts']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['receipts']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['receipts']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO propane_receipts (bill_id, filename, upload_date) VALUES ($bill_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }

        house_redirect($house_id, 'utility');
    }

    // PROJECT LIST - ADD NEW PROJECT
    if (isset($_POST['add_project'])) {
        $project_name = mysqli_real_escape_string($conn, $_POST['project_name'] ?? '');
        $date_added = date('Y-m-d');

        if (!empty($project_name)) {
            $sql = "INSERT INTO projects (house_id, name, date_added, completed) VALUES ($house_id, '$project_name', '$date_added', 0)";
            $conn->query($sql);
        }

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - MARK COMPLETED
    if (isset($_POST['complete_project'])) {
        $project_id = intval($_POST['project_id']);

        $sql = "UPDATE projects SET completed = 1, date_completed = NOW() WHERE id = $project_id AND house_id = $house_id";
        $conn->query($sql);

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - ADD MATERIAL TO PROJECT
    if (isset($_POST['add_material'])) {
        $project_id = intval($_POST['project_id']);
        $material_name = mysqli_real_escape_string($conn, $_POST['material_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $url = mysqli_real_escape_string($conn, $_POST['url'] ?? '');

        $sql = "INSERT INTO project_materials (project_id, material_name, price, quantity, url) 
                VALUES ($project_id, '$material_name', $price, $quantity, '$url')";
        $conn->query($sql);

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - DELETE PROJECT
    if (isset($_POST['delete_project'])) {
        $project_id = intval($_POST['project_id']);

        $conn->query("DELETE FROM project_materials WHERE project_id = $project_id");
        $conn->query("DELETE FROM projects WHERE id = $project_id AND house_id = $house_id");

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - DELETE INDIVIDUAL MATERIAL
    if (isset($_POST['delete_material'])) {
        $material_id = intval($_POST['material_id']);
        $project_id = intval($_POST['project_id']);

        $conn->query("DELETE FROM project_materials WHERE id = $material_id AND project_id = $project_id");

        house_redirect($house_id, 'projects');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $house_name; ?> - Home Documentation System</title>
    <link rel="stylesheet" href="styles.css?v=20260624b">
    <script src="scripts.js?v=20260624b"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="container">

    <header id="pageHeader">
        <div class="header-bar">
            <div class="logo-container">
                <img src="logo.png" alt="Home Documentation System" style="max-width:180px; height:auto;">
                <span class="logo-text">Home Documentation System</span>
            </div>
            <div class="menu-toggle" onclick="toggleMenu()" aria-label="Open menu" role="button" tabindex="0"><i class="fas fa-bars"></i></div>
        </div>
        <div class="header-footer">
            <a href="index.php" class="back-link">← Back to Houses</a>
        </div>

        <nav class="tab-menu" id="tabMenu">
            <?php foreach (hds_ui_registry()['tabs'] as $tab_key => $tab_meta): ?>
                <?php if (!hds_ui_tab_enabled($tab_key, $hds_ui_settings)) continue; ?>
                <button class="tablink <?php echo $active_tab === $tab_key ? 'active' : ''; ?>" onclick="openTab(event, '<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>')"><i class="fas <?php echo htmlspecialchars($tab_meta['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($tab_meta['label'], ENT_QUOTES, 'UTF-8'); ?></button>
            <?php endforeach; ?>
            <button class="tablink tablink--admin <?php echo $active_tab === 'admin' ? 'active' : ''; ?>" onclick="openTab(event, 'admin')"><i class="fas fa-sliders"></i> Admin</button>
        </nav>
    </header>

    <h1><?php echo $house_name; ?></h1>

    <!-- Tab contents -->
    <?php foreach (hds_ui_registry()['tabs'] as $tab_key => $tab_meta): ?>
        <?php if (!hds_ui_tab_enabled($tab_key, $hds_ui_settings)) continue; ?>
        <div id="<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" class="tab" style="display: <?php echo $active_tab === $tab_key ? 'block' : 'none'; ?>;">
            <?php
            $tab_file = __DIR__ . '/tabs/' . $tab_key . '.php';
            if (file_exists($tab_file)) {
                include $tab_file;
            } else {
                echo "<p class='empty-note'>Tab file is missing on the server: tabs/" . htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8') . ".php</p>";
            }
            ?>
        </div>
    <?php endforeach; ?>

    <div id="admin" class="tab" style="display: <?php echo $active_tab === 'admin' ? 'block' : 'none'; ?>;">
        <?php include __DIR__ . '/tabs/admin.php'; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/site-footer.php'; ?>

<!-- Close connection at the very end -->
<?php $conn->close(); ?>
<script>window.HDS_HOUSE_ID = <?php echo (int)$house_id; ?>;</script>

</body>
</html>
