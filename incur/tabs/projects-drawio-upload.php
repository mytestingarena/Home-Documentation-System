<?php
// tabs/projects-drawio-upload.php — Upload .drawio from Projects list into Designs storage
// Same path/DB as Designs tab (uploads/designs/ + designs.house_id). Project id is verified
// for ownership only; Designs remains house-scoped (no parallel storage).

$project_id = intval($_POST['project_id'] ?? 0);
$_SESSION['project_drawio_error'] = null;
$_SESSION['project_drawio_success'] = null;

if ($project_id <= 0) {
    $_SESSION['project_drawio_error'] = 'Invalid project.';
    return;
}

$chk = $conn->prepare('SELECT id, name FROM projects WHERE id = ? AND house_id = ? LIMIT 1');
if (!$chk) {
    $_SESSION['project_drawio_error'] = 'Could not verify project.';
    return;
}
$chk->bind_param('ii', $project_id, $house_id);
$chk->execute();
$project = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$project) {
    $_SESSION['project_drawio_error'] = 'Project not found for this house.';
    return;
}

if (empty($_FILES['project_drawio']) || !isset($_FILES['project_drawio']['error'])) {
    $_SESSION['project_drawio_error'] = 'No file selected.';
    return;
}

$file = $_FILES['project_drawio'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['project_drawio_error'] = 'Upload failed (error ' . (int)$file['error'] . ').';
    return;
}

$original_name = basename((string)$file['name']);
$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$allowed = ['drawio', 'xml'];
if (!in_array($ext, $allowed, true)) {
    $_SESSION['project_drawio_error'] = 'Only .drawio (or .xml) files are allowed.';
    return;
}

// Soft size guard (ini may allow more); Designs uses large limits but keep project uploads modest.
$max_bytes = 32 * 1024 * 1024;
if (!empty($file['size']) && (int)$file['size'] > $max_bytes) {
    $_SESSION['project_drawio_error'] = 'File too large (max 32 MB).';
    return;
}

$target_dir = 'uploads/designs/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0775, true);
}

$base_name = pathinfo($original_name, PATHINFO_FILENAME);
$safe_base = preg_replace('/[^A-Za-z0-9_-]/', '_', $base_name);
$proj_tag = 'p' . $project_id;
$final_name = time() . '_' . $proj_tag . '_' . $safe_base . '.' . $ext;
$target = $target_dir . $final_name;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    $_SESSION['project_drawio_error'] = 'Could not save uploaded file.';
    return;
}

$stmt = $conn->prepare('INSERT INTO designs (house_id, filename, upload_date) VALUES (?, ?, NOW())');
if (!$stmt) {
    @unlink($target);
    $_SESSION['project_drawio_error'] = 'Database error saving design.';
    return;
}
$stmt->bind_param('is', $house_id, $final_name);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    @unlink($target);
    $_SESSION['project_drawio_error'] = 'Database insert failed.';
    return;
}

$pname = htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8');
$fname = htmlspecialchars($final_name, ENT_QUOTES, 'UTF-8');
$_SESSION['project_drawio_success'] = "Uploaded {$fname} for project \"{$pname}\" — available under Designs.";
$_SESSION['project_drawio_filename'] = $final_name;
