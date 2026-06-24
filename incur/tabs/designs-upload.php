<?php
// tabs/designs-upload.php — Designs upload handler (called from house.php)
$target_dir = "uploads/designs/";
if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

$count = 0;
$max = 20;

foreach ($_FILES['designs']['tmp_name'] as $k => $tmp) {
    if ($count >= $max) break;
    if ($_FILES['designs']['error'][$k] !== UPLOAD_ERR_OK) continue;

    $original_name = basename($_FILES['designs']['name'][$k]);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $base_name = pathinfo($original_name, PATHINFO_FILENAME);
    $safe_base = preg_replace('/[^A-Za-z0-9_-]/', '_', $base_name);
    $final_name = time() . '_' . $safe_base . '.' . $ext;
    $target = $target_dir . $final_name;

    if (move_uploaded_file($tmp, $target)) {
        $sql = "INSERT INTO designs (house_id, filename, upload_date) VALUES ($house_id, '$final_name', NOW())";
        $conn->query($sql);

        if ($ext === 'xps') {
            $pdf_name = $safe_base . '_' . time() . '.pdf';
            $pdf_path = $target_dir . $pdf_name;

            $cmd = "mutool convert -o " . escapeshellarg($pdf_path) . " " . escapeshellarg($target);

            exec($cmd . " 2>&1", $output, $return_var);

            if ($return_var === 0 && file_exists($pdf_path)) {
                $sql_pdf = "INSERT INTO designs (house_id, filename, upload_date) VALUES ($house_id, '$pdf_name', NOW())";
                $conn->query($sql_pdf);
            } else {
                error_log("mutool XPS → PDF failed for $final_name. Return code: $return_var. Output: " . implode("\n", $output));
            }
        }

        $count++;
    }
}
?>
