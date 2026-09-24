<?php
// tabs/projects-receipt-upload.php — Upload project receipts into shared receipts storage
// Reuses uploads/receipts/ + utility-docs helpers (dirs, thumbs, allowed extensions).
// DB: project_receipts (project_id) — same pattern as water_receipts/propane_receipts.

require_once __DIR__ . '/../includes/utility-docs.php';

$project_id = intval($_POST['project_id'] ?? 0);
$_SESSION['project_receipt_error'] = null;
$_SESSION['project_receipt_success'] = null;

if ($project_id <= 0) {
    $_SESSION['project_receipt_error'] = 'Invalid project.';
    return;
}

$chk = $conn->prepare('SELECT id, name FROM projects WHERE id = ? AND house_id = ? LIMIT 1');
if (!$chk) {
    $_SESSION['project_receipt_error'] = 'Could not verify project.';
    return;
}
$chk->bind_param('ii', $project_id, $house_id);
$chk->execute();
$project = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$project) {
    $_SESSION['project_receipt_error'] = 'Project not found for this house.';
    return;
}

$dir_error = hds_utility_doc_ensure_dirs();
if ($dir_error !== null) {
    $_SESSION['project_receipt_error'] = $dir_error;
    return;
}

if (empty($_FILES['project_receipts']['name'])) {
    $_SESSION['project_receipt_error'] = 'No file selected.';
    return;
}

$names = $_FILES['project_receipts']['name'];
$tmps = $_FILES['project_receipts']['tmp_name'];
$errors = $_FILES['project_receipts']['error'];
if (!is_array($names)) {
    $names = [$names];
    $tmps = [$tmps];
    $errors = [$errors];
}

// Match utility bill-copy allow-list (PDF + common images) — useful for phone photos of receipts.
$allowed = hds_utility_doc_allowed_extensions('bill');
$dir = hds_utility_doc_dir();
$count = 0;
$max = 5;
$saved = [];

foreach ($tmps as $k => $tmp) {
    if ($count >= $max) {
        break;
    }
    if ((int)($errors[$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }
    $original = basename((string)($names[$k] ?? ''));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        continue;
    }
    $base = pathinfo($original, PATHINFO_FILENAME);
    $safe_base = preg_replace('/[^A-Za-z0-9._\- ()]/', '_', $base);
    $safe_base = trim($safe_base, '. ') ?: 'receipt';
    $final_name = 'p' . $project_id . '_' . $safe_base . '_' . time() . '_' . $count . '.' . $ext;
    $target = $dir . $final_name;
    if (!move_uploaded_file($tmp, $target)) {
        continue;
    }
    $stmt = $conn->prepare(
        'INSERT INTO project_receipts (project_id, filename, upload_date) VALUES (?, ?, NOW())'
    );
    if (!$stmt) {
        @unlink($target);
        continue;
    }
    $stmt->bind_param('is', $project_id, $final_name);
    if ($stmt->execute()) {
        hds_utility_doc_generate_thumb($final_name);
        $saved[] = $final_name;
        $count++;
    } else {
        @unlink($target);
    }
    $stmt->close();
}

$pname = htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8');
if ($count > 0) {
    $_SESSION['project_receipt_success'] = $count === 1
        ? "1 receipt uploaded for project \"{$pname}\"."
        : "{$count} receipts uploaded for project \"{$pname}\".";
} else {
    $_SESSION['project_receipt_error'] = 'No valid receipts uploaded. Allowed: PDF, JPG, PNG, GIF, WEBP (max 5).';
}
