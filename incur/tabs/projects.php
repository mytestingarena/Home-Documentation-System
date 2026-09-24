<?php
// tabs/projects.php — Project List tab (quantity × price fixed, material delete, tax 5.5%)
// Per-project uploads: .drawio → Designs storage; receipts → shared receipts storage.

global $conn, $house_id, $hds_ui_settings;

require_once __DIR__ . '/../includes/utility-docs.php';

/**
 * Compact draw.io + receipts upload row for one project.
 */
function hds_project_upload_controls(int $pid): void
{
    $accept_receipt = 'application/pdf,.pdf,image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp';
    echo "<div class='project-uploads'>";

    // Compact horizontal rows: label | file | Upload (matches materials form density)
    echo "<form method='post' enctype='multipart/form-data' class='project-upload-form'>";
    echo "<input type='hidden' name='project_id' value='$pid'>";
    echo "<span class='project-upload-label'>draw.io</span>";
    echo "<input type='file' name='project_drawio' class='project-upload-file' accept='.drawio,.xml,application/xml' required>";
    echo "<input type='submit' name='upload_project_drawio' value='Upload' class='small-btn'>";
    echo "</form>";

    echo "<form method='post' enctype='multipart/form-data' class='project-upload-form'>";
    echo "<input type='hidden' name='project_id' value='$pid'>";
    echo "<span class='project-upload-label'>Receipts</span>";
    echo "<input type='file' name='project_receipts[]' class='project-upload-file' accept='$accept_receipt' multiple required>";
    echo "<input type='submit' name='upload_project_receipts' value='Upload' class='small-btn'>";
    echo "</form>";

    echo "</div>";
}

/**
 * Compact list of existing project receipts (links into uploads/receipts/).
 */
function hds_project_receipts_list(mysqli $conn, int $pid): void
{
    $res = $conn->query(
        "SELECT id, filename, upload_date FROM project_receipts WHERE project_id = $pid ORDER BY upload_date DESC, id DESC LIMIT 8"
    );
    if (!$res || $res->num_rows === 0) {
        return;
    }
    echo "<div class='project-receipts-list'>";
    echo "<strong>Receipts:</strong> ";
    $parts = [];
    while ($row = $res->fetch_assoc()) {
        $fn = $row['filename'];
        $url = htmlspecialchars(hds_utility_doc_url($fn), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(hds_utility_doc_display_name($fn), ENT_QUOTES, 'UTF-8');
        if (strlen($label) > 28) {
            $label = htmlspecialchars(substr(hds_utility_doc_display_name($fn), 0, 12) . '…' . substr(hds_utility_doc_display_name($fn), -10), ENT_QUOTES, 'UTF-8');
        }
        $parts[] = "<a href='$url' target='_blank' rel='noopener' class='receipt-link'>$label</a>";
    }
    echo implode(' · ', $parts);
    echo "</div>";
}
?>

<h2>Project List</h2>

<?php
if (!empty($_SESSION['project_drawio_success'])) {
    $msg = $_SESSION['project_drawio_success'];
    unset($_SESSION['project_drawio_success'], $_SESSION['project_drawio_filename']);
    echo "<p class='media-success'>" . $msg . " <a href='house.php?id=" . (int)$house_id . "&tab=designs'>View Designs</a></p>";
}
if (!empty($_SESSION['project_drawio_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['project_drawio_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['project_drawio_error']);
}
if (!empty($_SESSION['project_receipt_success'])) {
    echo "<p class='media-success'>" . $_SESSION['project_receipt_success'] . "</p>";
    unset($_SESSION['project_receipt_success']);
}
if (!empty($_SESSION['project_receipt_error'])) {
    echo "<p class='media-error'>" . htmlspecialchars($_SESSION['project_receipt_error'], ENT_QUOTES, 'UTF-8') . "</p>";
    unset($_SESSION['project_receipt_error']);
}
?>

<!-- Add New Project -->
<div class="section-card">
    <h3>Add New Project</h3>
    <form method="post">
        <input type="text" name="project_name" placeholder="Project description" required style="width:70%;">
        <input type="submit" name="add_project" value="Add Project">
    </form>
</div>

<?php if (hds_ui_section_enabled('projects-active', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Active Projects</h3>
    <?php
    $projects = $conn->query("SELECT * FROM projects WHERE house_id = $house_id AND completed = 0 ORDER BY date_added DESC");
    if ($projects->num_rows == 0) {
        echo "<p>No active projects yet.</p>";
    } else {
        while ($project = $projects->fetch_assoc()) {
            $pid = (int)$project['id'];
            $name = htmlspecialchars($project['name']);
            $date = date('M j, Y', strtotime($project['date_added']));

            echo "<div class='project-item'>";
            echo "<strong>$name</strong> - Added: $date<br>";

            // Materials list
            $subtotal = 0;
            $materials = $conn->query("SELECT * FROM project_materials WHERE project_id = $pid");
            if ($materials->num_rows > 0) {
                echo "<div class='material-list'>";
                while ($mat = $materials->fetch_assoc()) {
                    $mid = $mat['id'];
                    $mname = htmlspecialchars($mat['material_name']);
                    $price = $mat['price'];
                    $quantity = $mat['quantity'];
                    $item_total = $price * $quantity;
                    $subtotal += $item_total;
                    $url = $mat['url'] ? htmlspecialchars($mat['url']) : '';

                    echo "<div class='material-item'>";
                    echo "$mname (Qty: $quantity) - $" . number_format($item_total, 2);
                    if ($url) {
                        echo " <a href='$url' target='_blank' rel='noopener noreferrer' style='color:#007bff; text-decoration:none;'>[Link]</a>";
                    }
                    echo " <form method='post' style='display:inline;'>";
                    echo "<input type='hidden' name='material_id' value='$mid'>";
                    echo "<input type='hidden' name='project_id' value='$pid'>";
                    echo "<input type='submit' name='delete_material' value='Delete' onclick='return confirm(\"Delete this material?\");' style='background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.9em;'>";
                    echo "</form>";
                    echo "</div>";
                }
                echo "</div>";
            }

            $tax_rate = 0.055;
            $tax = $subtotal * $tax_rate;
            $grand_total = $subtotal + $tax;

            echo "<strong>Subtotal: $" . number_format($subtotal, 2) . "</strong><br>";
            echo "<strong>Sales Tax (5.5%): $" . number_format($tax, 2) . "</strong><br>";
            echo "<strong>Grand Total: $" . number_format($grand_total, 2) . "</strong><br><br>";

            // Add material form
            echo "<form method='post' style='margin:10px 0;'>";
            echo "<input type='hidden' name='project_id' value='$pid'>";
            echo "<input type='text' name='material_name' placeholder='Material needed' required style='width:40%;'>";
            echo "<input type='number' step='0.01' name='price' placeholder='Price' required style='width:15%;'>";
            echo "<input type='number' name='quantity' placeholder='Qty' value='1' min='1' style='width:10%;'>";
            echo "<input type='url' name='url' placeholder='Optional URL' style='width:25%;'>";
            echo "<input type='submit' name='add_material' value='Add Material'>";
            echo "</form>";

            hds_project_receipts_list($conn, $pid);
            hds_project_upload_controls($pid);

            // Complete button
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='project_id' value='$pid'>";
            echo "<input type='submit' name='complete_project' value='Mark Completed' onclick='return confirm(\"Mark this project as completed?\");'>";
            echo "</form>";

            // Delete project button
            echo " <form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='project_id' value='$pid'>";
            echo "<input type='submit' name='delete_project' value='Delete Project' onclick='return confirm(\"Delete this project and all its materials? This cannot be undone.\");' style='background:#dc3545; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;'>";
            echo "</form>";
            echo "</div>";
        }
    }
    ?>
</div>
<?php endif; ?>

<?php if (hds_ui_section_enabled('projects-completed', $hds_ui_settings)): ?>
<div class="section-card">
    <h3>Completed Projects Tracker</h3>
    <?php
    $completed = $conn->query("SELECT * FROM projects WHERE house_id = $house_id AND completed = 1 ORDER BY date_completed DESC");
    if ($completed->num_rows == 0) {
        echo "<p>No completed projects yet.</p>";
    } else {
        while ($proj = $completed->fetch_assoc()) {
            $pid = (int)$proj['id'];
            $name = htmlspecialchars($proj['name']);
            $date = date('M j, Y', strtotime($proj['date_completed']));

            echo "<div class='project-item'>";
            echo "<strong>$name</strong> - Completed: $date<br>";

            $subtotal = 0;
            $mats = $conn->query("SELECT * FROM project_materials WHERE project_id = $pid");
            if ($mats->num_rows > 0) {
                echo "<div class='material-list'>";
                while ($mat = $mats->fetch_assoc()) {
                    $mname = htmlspecialchars($mat['material_name']);
                    $price = $mat['price'];
                    $quantity = $mat['quantity'];
                    $item_total = $price * $quantity;
                    $subtotal += $item_total;
                    $url = $mat['url'] ? htmlspecialchars($mat['url']) : '';
                    echo "<div class='material-item'>$mname (Qty: $quantity) - $" . number_format($item_total, 2);
                    if ($url) {
                        echo " <a href='$url' target='_blank' rel='noopener noreferrer' style='color:#007bff; text-decoration:none;'>[Link]</a>";
                    }
                    echo "</div>";
                }
                echo "</div>";
            }

            $tax_rate = 0.055;
            $tax = $subtotal * $tax_rate;
            $grand_total = $subtotal + $tax;

            echo "<strong>Subtotal: $" . number_format($subtotal, 2) . "</strong><br>";
            echo "<strong>Sales Tax (5.5%): $" . number_format($tax, 2) . "</strong><br>";
            echo "<strong>Grand Total: $" . number_format($grand_total, 2) . "</strong><br>";

            hds_project_receipts_list($conn, $pid);
            hds_project_upload_controls($pid);

            // Delete button for completed projects
            echo "<form method='post' style='margin-top:10px;'>";
            echo "<input type='hidden' name='project_id' value='$pid'>";
            echo "<input type='submit' name='delete_project' value='Delete Project' onclick='return confirm(\"Delete this completed project and all its materials? This cannot be undone.\");' style='background:#dc3545; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;'>";
            echo "</form>";
            echo "</div>";
        }
    }
    ?>
</div>
<?php endif; ?>
