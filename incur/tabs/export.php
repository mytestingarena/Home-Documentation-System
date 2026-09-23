<?php
// tabs/export.php — Export this house as PDF, spreadsheet, or new-owner packet

global $conn, $house_id, $house_name;

$export_errors = [
    'confirm' => 'Export cancelled — you must type confirm exactly.',
    'house' => 'Export failed — house not found.',
    'format' => 'Export failed — choose a format.',
    'zip' => 'Export failed — could not create the zip packet. Try PDF only, or ask your admin to enable PHP zip support.',
];
$export_error = '';
$code = $_GET['export_error'] ?? '';
if (isset($export_errors[$code])) {
    $export_error = $export_errors[$code];
}

$design_count = 0;
$manual_count = 0;
$designs = @$conn->query("SELECT COUNT(*) AS c FROM designs WHERE house_id = $house_id");
if ($designs && ($row = $designs->fetch_assoc())) {
    $design_count = (int)$row['c'];
}
$manuals = @$conn->query("SELECT COUNT(*) AS c FROM user_manuals WHERE house_id = $house_id");
if ($manuals && ($row = $manuals->fetch_assoc())) {
    $manual_count = (int)$row['c'];
}

$form_action = 'export-house.php';
?>

<h2>Export</h2>
<p class="homelab-intro">Download documentation for <strong><?php echo $house_name; ?></strong>. Use the new owner packet when handing the house off — it includes the PDF plus copies of designs, plans, and user manuals.</p>

<?php if ($export_error !== ''): ?>
    <p class="export-error-msg"><?php echo htmlspecialchars($export_error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<div class="section-card export-tab-card">
    <form method="post" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>" class="house-export-form">
        <input type="hidden" name="house_id" value="<?php echo (int)$house_id; ?>">

        <p class="house-export-label">Format</p>
        <div class="export-format-list">
            <label class="export-format-option">
                <input type="radio" name="export_format" value="packet" checked>
                <span>
                    <strong>New owner packet (.zip)</strong>
                    <span class="export-format-desc">PDF summary plus the actual design/plan files and user manuals. Best for handing the home to a buyer or new owner. Currently <?php echo $design_count; ?> design/plan file<?php echo $design_count === 1 ? '' : 's'; ?> and <?php echo $manual_count; ?> manual<?php echo $manual_count === 1 ? '' : 's'; ?>.</span>
                </span>
            </label>
            <label class="export-format-option">
                <input type="radio" name="export_format" value="pdf">
                <span>
                    <strong>Printable PDF summary</strong>
                    <span class="export-format-desc">A printable packet of house data only. File attachments are listed by name, not included.</span>
                </span>
            </label>
            <label class="export-format-option">
                <input type="radio" name="export_format" value="spreadsheet">
                <span>
                    <strong>Spreadsheet workbook (.xlsx)</strong>
                    <span class="export-format-desc">All house records in Excel, one sheet per section.</span>
                </span>
            </label>
        </div>

        <label class="house-export-label" for="export_confirm">Type confirm to export</label>
        <input type="text" name="export_confirm" id="export_confirm" class="house-export-confirm" placeholder="confirm" autocomplete="off">
        <input type="submit" name="export_house" value="Export" class="house-export-btn">
    </form>
</div>
