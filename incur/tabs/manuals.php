<?php
// tabs/manuals.php — User Manuals tab content

global $conn, $house_id;
?>

<h2>User Manual PDFs</h2>

<div class="section-card">
    <h3>Upload Manuals</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="manuals[]" multiple accept=".pdf,.doc,.docx">
        <br><small>Select PDF or Word files (up to 10)</small><br><br>
        <input type="submit" name="upload_manuals" value="Upload Manuals">
    </form>
</div>

<div class="section-card manual-list">
    <h3>Uploaded Manuals</h3>
    <?php
    $sql = "SELECT * FROM user_manuals WHERE house_id = $house_id ORDER BY upload_date DESC";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "<p>No manuals uploaded yet.</p>";
    } else {
        while ($manual = $result->fetch_assoc()) {
            $fn = htmlspecialchars($manual['filename']);
            $date = date('M j, Y g:i A', strtotime($manual['upload_date']));
            $path = "uploads/manuals/" . $fn;
            $size = file_exists($path) ? filesize($path) : 0;
            $size_str = $size > 1024*1024 ? round($size / (1024*1024), 1) . ' MB' : round($size / 1024, 1) . ' KB';

            echo "<div class='manual-item'>
                  <div>
                      <strong>$fn</strong><br>
                      <small>Uploaded: $date • $size_str</small>
                  </div>
                  <form method='post' style='margin:0;'>
                      <input type='hidden' name='manual_id' value='{$manual['id']}'>
                      <input type='submit' name='delete_manual' value='Delete' class='delete-manual-btn' onclick='return confirm(\"Delete $fn?\");'>
                  </form>
                  </div>";
        }
    }
    ?>
</div>
