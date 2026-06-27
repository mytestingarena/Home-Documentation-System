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
    <link rel="stylesheet" href="styles.css">
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
    if ($delete_message) echo $delete_message;
    ?>

    <!-- Add New House Form -->
    <div class="section-card" style="margin-bottom:30px; max-width:500px; margin-left:auto; margin-right:auto;">
        <h3>Add New House</h3>
        <form method="post">
            <input type="text" name="house_name" placeholder="House name (e.g. Main House, Lake Cabin)" required style="width:100%; padding:12px; margin-bottom:10px; border-radius:6px; border:1px solid #ccc;">
            <input type="submit" name="add_house" value="Add House" style="background:#3498db; color:white; border:none; padding:12px 24px; border-radius:8px; cursor:pointer; width:100%;">
        </form>
    </div>

    <!-- Houses Grid -->
    <div class="houses-grid">
        <?php
        $sql = "SELECT id, name FROM houses ORDER BY id";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                $name = htmlspecialchars($row['name']);
                echo "<div class='house-card'>";
                echo "<h2>$name</h2>";
                echo "<p><a href='house.php?id=$id' style='color:#3498db; text-decoration:none; font-weight:bold;'>View Details</a></p>";

                // Delete form with confirmation
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
</body>
</html>
