<?php
// tabs/designs-delete.php — Designs delete handler (called from house.php)
$design_id = intval($_POST['design_id']);

$stmt = $conn->prepare("SELECT filename FROM designs WHERE id = ? AND house_id = ?");
$stmt->bind_param("ii", $design_id, $house_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $filename = $row['filename'];
    $file_path = "uploads/designs/" . $filename;

    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $delete_stmt = $conn->prepare("DELETE FROM designs WHERE id = ? AND house_id = ?");
    $delete_stmt->bind_param("ii", $design_id, $house_id);
    $delete_stmt->execute();
    $delete_stmt->close();
}
$stmt->close();
?>
