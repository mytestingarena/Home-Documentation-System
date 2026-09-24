<?php
// tabs/projects.php — Project List tab (quantity × price fixed, material delete, tax 5.5%)
// Per-project uploads: .drawio → Designs storage; receipts → shared receipts storage.
// Each project is a collapsible details card (collapsed by default).

global $conn, $house_id, $hds_ui_settings;

require_once __DIR__ . '/../includes/utility-docs.php';

/**
 * Compact draw.io + receipts upload row for one project.
 */
function hds_project_upload_controls(int $pid): void
{
    $accept_receipt = 'application/pdf,.pdf,image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp';
    echo "<div class='project-uploads'>";

    // Two-line blocks: label alone, then Browse + Upload on one row
    echo "<form method='post' enctype='multipart/form-data' class='project-upload-form'>";
    echo "<input type='hidden' name='project_id' value='$pid'>";
    echo "<span class='project-upload-label'>draw.io files accepted</span>";
    echo "<div class='project-upload-controls'>";
    echo "<input type='file' name='project_drawio' class='project-upload-file' accept='.drawio,.xml,application/xml' required>";
    echo "<input type='submit' name='upload_project_drawio' value='Upload' class='small-btn'>";
    echo "</div>";
    echo "</form>";

    echo "<form method='post' enctype='multipart/form-data' class='project-upload-form'>";
    echo "<input type='hidden' name='project_id' value='$pid'>";
    echo "<span class='project-upload-label'>Receipt files accepted (PDF, JPG, PNG)</span>";
    echo "<div class='project-upload-controls'>";
    echo "<input type='file' name='project_receipts[]' class='project-upload-file' accept='$accept_receipt' multiple required>";
    echo "<input type='submit' name='upload_project_receipts' value='Upload' class='small-btn'>";
    echo "</div>";
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

/**
 * Load materials for a project; returns [rows, subtotal].
 */
function hds_project_load_materials(mysqli $conn, int $pid): array
{
    $rows = [];
    $subtotal = 0.0;
    $materials = $conn->query("SELECT * FROM project_materials WHERE project_id = $pid");
    if ($materials) {
        while ($mat = $materials->fetch_assoc()) {
            $item_total = ((float)$mat['price']) * ((float)$mat['quantity']);
            $subtotal += $item_total;
            $rows[] = $mat;
        }
    }
    return [$rows, $subtotal];
}

/**
 * Render tax/totals lines for a project subtotal.
 */
function hds_project_totals_block(float $subtotal): array
{
    $tax_rate = 0.055;
    $tax = $subtotal * $tax_rate;
    $grand_total = $subtotal + $tax;
    return [$tax, $grand_total];
}

/**
 * Render one collapsible project card.
 *
 * @param bool $completed  Whether the project is completed.
 * @param bool $allow_edit Whether materials can be added/deleted and Mark Completed shown.
 */
function hds_render_project_card(mysqli $conn, array $project, bool $completed, bool $allow_edit): void
{
    $pid = (int)$project['id'];
    $name = htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8');
    [$materials, $subtotal] = hds_project_load_materials($conn, $pid);
    [, $grand_total] = hds_project_totals_block($subtotal);
    $total_label = '$' . number_format($grand_total, 2);

    if ($completed) {
        $date_raw = $project['date_completed'] ?? $project['date_added'];
        $date = date('M j, Y', strtotime($date_raw));
        $date_label = "Completed: $date";
        $status_label = 'Completed';
        $status_class = 'project-status-badge project-status-badge--completed';
        $open_attr = '';
        $card_mod = 'project-card--completed';
    } else {
        $date = date('M j, Y', strtotime($project['date_added']));
        $date_label = "Added: $date";
        $status_label = 'Active';
        $status_class = 'project-status-badge project-status-badge--active';
        $open_attr = '';
        $card_mod = 'project-card--active';
    }

    $date_added_val = '';
    if (!empty($project['date_added'])) {
        $date_added_val = date('Y-m-d', strtotime($project['date_added']));
    }
    $date_completed_val = '';
    if (!empty($project['date_completed'])) {
        $date_completed_val = date('Y-m-d', strtotime($project['date_completed']));
    }
    $completed_flag = $completed ? '1' : '0';
    $name_attr = htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8');

    echo "<details class='section-card project-card collapsible-section $card_mod' id='project-$pid' data-project-id='$pid'$open_attr>";
    echo "<summary class='collapsible-summary'>";
    echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
    echo "<span class='collapsible-summary-title'>$name</span>";
    echo "<span class='$status_class'>$status_label</span>";
    echo "<span class='project-summary-meta'>" . htmlspecialchars($date_label, ENT_QUOTES, 'UTF-8') . " · $total_label</span>";
    echo "<button type='button' class='project-edit-open' title='Edit project' aria-label='Edit project'";
    echo " data-project-id='$pid'";
    echo " data-project-name=\"$name_attr\"";
    echo " data-date-added='$date_added_val'";
    echo " data-date-completed='$date_completed_val'";
    echo " data-completed='$completed_flag'";
    echo "><i class='fas fa-pen' aria-hidden='true'></i></button>";
    echo "</summary>";
    echo "<div class='collapsible-body'>";

    if (count($materials) > 0) {
        echo "<div class='material-list'>";
        foreach ($materials as $mat) {
            $mid = (int)$mat['id'];
            $mname = htmlspecialchars($mat['material_name'], ENT_QUOTES, 'UTF-8');
            $price = (float)$mat['price'];
            $quantity = (float)$mat['quantity'];
            $item_total = $price * $quantity;
            $url = !empty($mat['url']) ? htmlspecialchars($mat['url'], ENT_QUOTES, 'UTF-8') : '';

            echo "<div class='material-item'>";
            echo "$mname (Qty: $quantity) - $" . number_format($item_total, 2);
            if ($url) {
                echo " <a href='$url' target='_blank' rel='noopener noreferrer' style='color:#007bff; text-decoration:none;'>[Link]</a>";
            }
            if ($allow_edit) {
                echo " <form method='post' style='display:inline;'>";
                echo "<input type='hidden' name='material_id' value='$mid'>";
                echo "<input type='hidden' name='project_id' value='$pid'>";
                echo "<input type='submit' name='delete_material' value='Delete' onclick='return confirm(\"Delete this material?\");' style='background:#dc3545; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:0.9em;'>";
                echo "</form>";
            }
            echo "</div>";
        }
        echo "</div>";
    }

    [$tax, $grand_total] = hds_project_totals_block($subtotal);
    echo "<strong>Subtotal: $" . number_format($subtotal, 2) . "</strong><br>";
    echo "<strong>Sales Tax (5.5%): $" . number_format($tax, 2) . "</strong><br>";
    echo "<strong>Grand Total: $" . number_format($grand_total, 2) . "</strong><br>";

    if ($allow_edit) {
        echo "<form method='post' style='margin:10px 0;'>";
        echo "<input type='hidden' name='project_id' value='$pid'>";
        echo "<input type='text' name='material_name' placeholder='Material needed' required style='width:40%;'>";
        echo "<input type='number' step='0.01' name='price' placeholder='Price' required style='width:15%;'>";
        echo "<input type='number' name='quantity' placeholder='Qty' value='1' min='1' style='width:10%;'>";
        echo "<input type='url' name='url' placeholder='Optional URL' style='width:25%;'>";
        echo "<input type='submit' name='add_material' value='Add Material'>";
        echo "</form>";
    }

    hds_project_receipts_list($conn, $pid);
    hds_project_upload_controls($pid);

    if ($allow_edit) {
        echo "<form method='post' style='display:inline;'>";
        echo "<input type='hidden' name='project_id' value='$pid'>";
        echo "<input type='submit' name='complete_project' value='Mark Completed' onclick='return confirm(\"Mark this project as completed?\");'>";
        echo "</form>";
        echo " ";
    }

    $confirm_msg = $completed
        ? 'Delete this completed project and all its materials? This cannot be undone.'
        : 'Delete this project and all its materials? This cannot be undone.';
    echo "<form method='post' style='display:inline;" . ($completed ? " margin-top:10px;" : "") . "'>";
    echo "<input type='hidden' name='project_id' value='$pid'>";
    echo "<input type='submit' name='delete_project' value='Delete Project' onclick='return confirm(\"" . htmlspecialchars($confirm_msg, ENT_QUOTES, 'UTF-8') . "\");' style='background:#dc3545; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer;'>";
    echo "</form>";

    echo "</div></details>";
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
<h3 class="projects-section-title">Active Projects</h3>
<?php
$projects = $conn->query("SELECT * FROM projects WHERE house_id = $house_id AND completed = 0 ORDER BY date_added DESC");
if (!$projects || $projects->num_rows == 0) {
    echo "<p>No active projects yet.</p>";
} else {
    echo "<div class='collapsible-list-toolbar'>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.projects-list--active .collapsible-section', true)\">Expand all</button>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.projects-list--active .collapsible-section', false)\">Collapse all</button>";
    echo "</div>";
    echo "<div class='projects-list projects-list--active'>";
    while ($project = $projects->fetch_assoc()) {
        hds_render_project_card($conn, $project, false, true);
    }
    echo "</div>";
}
?>
<?php endif; ?>

<?php if (hds_ui_section_enabled('projects-completed', $hds_ui_settings)): ?>
<h3 class="projects-section-title">Completed Projects Tracker</h3>
<?php
$completed = $conn->query("SELECT * FROM projects WHERE house_id = $house_id AND completed = 1 ORDER BY date_completed DESC");
if (!$completed || $completed->num_rows == 0) {
    echo "<p>No completed projects yet.</p>";
} else {
    echo "<div class='collapsible-list-toolbar'>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.projects-list--completed .collapsible-section', true)\">Expand all</button>";
    echo "<button type='button' class='small-btn' onclick=\"collapsibleExpandAll('.projects-list--completed .collapsible-section', false)\">Collapse all</button>";
    echo "</div>";
    echo "<div class='projects-list projects-list--completed'>";
    while ($proj = $completed->fetch_assoc()) {
        hds_render_project_card($conn, $proj, true, false);
    }
    echo "</div>";
}
?>
<?php endif; ?>

<!-- Edit project modal (name + dates; materials stay add/delete) -->
<div id="projectEditModal" class="media-rename-modal" hidden aria-hidden="true">
    <div class="media-rename-backdrop" data-project-edit-close></div>
    <div class="media-rename-dialog" role="dialog" aria-modal="true" aria-labelledby="projectEditTitle">
        <button type="button" class="media-rename-close" data-project-edit-close aria-label="Close">&times;</button>
        <h3 id="projectEditTitle">Edit Project</h3>
        <form method="post" id="projectEditForm">
            <input type="hidden" name="project_id" id="projectEditId" value="">
            <div class="hds-ve-field">
                <label class="media-rename-label" for="projectEditName">Project name</label>
                <input type="text" name="project_name" id="projectEditName" required maxlength="255" autocomplete="off" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #ccc; border-radius:6px;">
            </div>
            <div class="hds-ve-field">
                <label class="media-rename-label" for="projectEditDateAdded">Date added</label>
                <input type="date" name="date_added" id="projectEditDateAdded" required style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #ccc; border-radius:6px;">
            </div>
            <div class="hds-ve-field" id="projectEditDateCompletedWrap" hidden>
                <label class="media-rename-label" for="projectEditDateCompleted">Date completed</label>
                <input type="date" name="date_completed" id="projectEditDateCompleted" style="width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #ccc; border-radius:6px;">
            </div>
            <div class="media-rename-actions" style="margin-top:16px;">
                <button type="button" class="small-btn" data-project-edit-close>Cancel</button>
                <input type="submit" name="update_project" value="Save" class="media-rename-save">
            </div>
        </form>
    </div>
</div>

