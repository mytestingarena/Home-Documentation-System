<?php
// index.php — Houses List (with add/delete houses, fixed invalid ID after add)

include 'config.php';

// Handle ADD new house
$add_message = '';
if (isset($_POST['add_house']) && !empty(trim($_POST['house_name']))) {
    $house_name = mysqli_real_escape_string($conn, trim($_POST['house_name']));
    $sql = "INSERT INTO houses (name) VALUES ('$house_name')";
    if ($conn->query($sql)) {
        $add_message = "<p style='color:green; font-weight:bold; text-align:center;'>House '$house_name' added successfully!</p>";
    } else {
        $add_message = "<p style='color:red; font-weight:bold; text-align:center;'>Error adding house: " . $conn->error . "</p>";
    }
}

// Handle DELETE house
$export_message = '';
if (isset($_GET['export_error'])) {
    $export_errors = [
        'confirm' => 'Export cancelled — you must type confirm exactly.',
        'house' => 'Export failed — house not found.',
        'format' => 'Export failed — choose spreadsheet or PDF.',
    ];
    $code = $_GET['export_error'];
    if (isset($export_errors[$code])) {
        $export_message = "<p class='export-error-msg'>" . htmlspecialchars($export_errors[$code], ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

$delete_message = '';
if (isset($_POST['delete_house']) && isset($_POST['house_id']) && isset($_POST['confirm_delete'])) {
    $confirm = strtolower(trim($_POST['confirm_delete']));
    if ($confirm === 'approve') {
        $house_id = intval($_POST['house_id']);

        // Delete uploaded files before removing DB rows
        $file_tables = [
            'photos'      => 'uploads/photos/',
            'designs'     => 'uploads/designs/',
            'user_manuals'=> 'uploads/manuals/',
        ];
        require_once __DIR__ . '/includes/outdoor-photos.php';
        hds_outdoor_photos_delete_house_files($conn, $house_id);
        foreach ($file_tables as $table => $dir) {
            $rows = $conn->query("SELECT filename FROM $table WHERE house_id = $house_id");
            if ($rows) {
                while ($row = $rows->fetch_assoc()) {
                    $path = $dir . $row['filename'];
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }
        }

        $bills = $conn->query("SELECT id FROM utility_bills WHERE house_id = $house_id");
        if ($bills) {
            while ($bill = $bills->fetch_assoc()) {
                $bill_id = (int)$bill['id'];
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
            }
        }

        // Delete child rows (order matters where CASCADE is not defined)
        $conn->query("DELETE FROM project_materials WHERE project_id IN (SELECT id FROM projects WHERE house_id = $house_id)");
        $conn->query("DELETE FROM projects WHERE house_id = $house_id");
        $conn->query("DELETE FROM utility_bills WHERE house_id = $house_id");
        $conn->query("DELETE FROM property_taxes WHERE house_id = $house_id");
        $conn->query("DELETE FROM water_utilities WHERE house_id = $house_id");
        $conn->query("DELETE FROM propane_utilities WHERE house_id = $house_id");
        $conn->query("DELETE FROM permanent_items WHERE house_id = $house_id");
        $conn->query("DELETE FROM household_items WHERE house_id = $house_id");
        @$conn->query("DELETE FROM contractors WHERE house_id = $house_id");
        @$conn->query("DELETE FROM homelab_instances WHERE house_id = $house_id");
        @$conn->query("DELETE FROM homelab_hardware WHERE house_id = $house_id");
        $conn->query("DELETE FROM tools WHERE house_id = $house_id");
        $conn->query("DELETE FROM maintenance_equipment WHERE house_id = $house_id");
        @$conn->query("DELETE FROM wifi_networks WHERE house_id = $house_id");
        $conn->query("DELETE FROM photos WHERE house_id = $house_id");
        $conn->query("DELETE FROM designs WHERE house_id = $house_id");
        $conn->query("DELETE FROM user_manuals WHERE house_id = $house_id");
        $conn->query("DELETE FROM electric_meters WHERE house_id = $house_id");
        $conn->query("DELETE FROM generators WHERE house_id = $house_id");
        $conn->query("DELETE FROM solar_inverters WHERE house_id = $house_id");
        $conn->query("DELETE FROM solar_panels WHERE string_id IN (SELECT id FROM solar_strings WHERE house_id = $house_id)");
        $conn->query("DELETE FROM solar_strings WHERE house_id = $house_id");
        $conn->query("DELETE FROM batteries WHERE string_id IN (SELECT id FROM battery_strings WHERE house_id = $house_id)");
        $conn->query("DELETE FROM battery_strings WHERE house_id = $house_id");
        $conn->query("DELETE FROM breakers WHERE panel_id IN (SELECT id FROM electric_panels WHERE house_id = $house_id)");
        $conn->query("DELETE FROM electric_panels WHERE house_id = $house_id");
        $outdoor_imgs = @$conn->query("SELECT i.filename FROM outdoor_work_images i INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id WHERE w.house_id = $house_id");
        if ($outdoor_imgs) {
            while ($img = $outdoor_imgs->fetch_assoc()) {
                $path = 'uploads/outdoor-work/' . $img['filename'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        @$conn->query("DELETE FROM outdoor_work_items WHERE house_id = $house_id");
        $house_imgs = @$conn->query("SELECT i.filename FROM house_work_images i INNER JOIN house_work_items w ON i.house_work_id = w.id WHERE w.house_id = $house_id");
        if ($house_imgs) {
            while ($img = $house_imgs->fetch_assoc()) {
                $path = 'uploads/house-work/' . $img['filename'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        @$conn->query("DELETE FROM house_work_items WHERE house_id = $house_id");
        $firearm_imgs = @$conn->query("SELECT i.filename FROM firearm_images i INNER JOIN firearms f ON i.firearm_id = f.id WHERE f.house_id = $house_id");
        if ($firearm_imgs) {
            while ($img = $firearm_imgs->fetch_assoc()) {
                $path = 'uploads/firearms/' . $img['filename'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        @$conn->query("DELETE FROM firearms WHERE house_id = $house_id");
        $perm_log_imgs = @$conn->query("SELECT i.filename FROM permanent_maintenance_log_images i INNER JOIN permanent_maintenance_log l ON i.log_id = l.id WHERE l.house_id = $house_id");
        if ($perm_log_imgs) {
            while ($img = $perm_log_imgs->fetch_assoc()) {
                $path = 'uploads/maintenance-log/' . $img['filename'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        @$conn->query("DELETE FROM permanent_maintenance_log WHERE house_id = $house_id");

        $sql = "DELETE FROM houses WHERE id = $house_id";
        if ($conn->query($sql)) {
            $delete_message = "<p style='color:green; font-weight:bold; text-align:center;'>House deleted successfully.</p>";
        } else {
            $delete_message = "<p style='color:red; font-weight:bold; text-align:center;'>Error deleting house: " . $conn->error . "</p>";
        }
    } else {
        $delete_message = "<p style='color:red; font-weight:bold; text-align:center;'>Deletion cancelled — you must type 'approve' exactly.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Documentation System - Houses</title>
    <link rel="stylesheet" href="styles.css?v=20260823a">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="container">

    <header>
        <div class="logo-container">
            <img src="logo.png" alt="Home Documentation System" style="max-width:180px; height:auto;">
            <span class="logo-text">Home Documentation System</span>
        </div>
    </header>

    <h1>Houses</h1>

    <?php
    if ($add_message) echo $add_message;
    if ($export_message) echo $export_message;
    if ($delete_message) echo $delete_message;
    ?>

    <?php
    $houses = [];
    $houses_result = $conn->query("SELECT id, name FROM houses ORDER BY name ASC, id ASC");
    if ($houses_result) {
        while ($row = $houses_result->fetch_assoc()) {
            $houses[] = $row;
        }
    }
    ?>

    <div class="houses-admin-row">
        <div class="section-card houses-admin-card">
            <h3>Add New House</h3>
            <form method="post">
                <input type="text" name="house_name" placeholder="House name (e.g. Main House, Lake Cabin)" required style="width:100%; padding:12px; margin-bottom:10px; border-radius:6px; border:1px solid #ccc;">
                <input type="submit" name="add_house" value="Add House" style="background:#3498db; color:white; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; width:100%;">
            </form>
        </div>

        <div class="section-card houses-admin-card">
            <h3>Export House Data</h3>
            <p class="house-export-hint">Choose a house first, then pick a format for realtor handoff.</p>
            <?php if (count($houses) > 0): ?>
            <form method="post" action="export-house.php" class="house-export-form" id="houseExportForm">
                <label class="house-export-label" for="export_house_id">House</label>
                <select name="house_id" id="export_house_id" class="house-export-select" required>
                    <option value="">Select a house...</option>
                    <?php foreach ($houses as $house): ?>
                        <option value="<?php echo (int)$house['id']; ?>"><?php echo htmlspecialchars($house['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="house-export-options" id="houseExportOptions" hidden>
                    <p class="house-export-label">Format</p>
                    <div class="house-export-format">
                        <label class="house-export-choice"><input type="radio" name="export_format" value="spreadsheet" checked> Spreadsheet workbook (.xlsx)</label>
                        <label class="house-export-choice"><input type="radio" name="export_format" value="pdf"> Printable PDF summary</label>
                    </div>
                    <input type="text" name="export_confirm" class="house-export-confirm" placeholder="Type confirm to export" autocomplete="off">
                    <input type="submit" name="export_house" value="Export Data" class="house-export-btn">
                </div>
            </form>
            <?php else: ?>
            <p class="empty-note">Add a house above before exporting.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Houses Grid -->
    <div class="houses-grid">
        <?php
        if (count($houses) > 0) {
            foreach ($houses as $row) {
                $id = (int)$row['id'];
                $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                echo "<div class='house-card'>";
                echo "<h2>$name</h2>";
                echo "<p class='house-card-link'><a href='house.php?id=$id'>View Details</a></p>";

                echo "<form method='post' style='margin-top:15px;'>";
                echo "<input type='hidden' name='house_id' value='$id'>";
                echo "<input type='text' name='confirm_delete' placeholder='Type \"approve\" to delete' style='width:100%; padding:8px; margin-bottom:8px; border-radius:6px; border:1px solid #ccc;'>";
                echo "<input type='submit' name='delete_house' value='Delete House' onclick='return confirm(\"This will delete the house and ALL related data (manuals, projects, etc.). Type \"approve\" in the box to confirm.\");' style='background:#dc3545; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; width:100%;'>";
                echo "</form>";
                echo "</div>";
            }
        } else {
            echo "<p style='text-align:center; color:#777;'>No houses found. Add one above!</p>";
        }
        ?>
    </div>

</div>
<?php include __DIR__ . '/includes/site-footer.php'; ?>
<script>
(function() {
  var select = document.getElementById("export_house_id");
  var options = document.getElementById("houseExportOptions");
  if (!select || !options) return;
  function toggleExportOptions() {
    options.hidden = !select.value;
  }
  select.addEventListener("change", toggleExportOptions);
  toggleExportOptions();
})();
</script>
</body>
</html>
