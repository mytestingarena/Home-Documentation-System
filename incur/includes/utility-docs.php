<?php
// includes/utility-docs.php — PDF bill/receipt helpers for water utility

function hds_utility_doc_dir(): string
{
    return __DIR__ . '/../uploads/receipts/';
}

function hds_utility_doc_thumb_dir(): string
{
    return hds_utility_doc_dir() . 'thumbs/';
}

function hds_utility_doc_url(string $filename): string
{
    return 'uploads/receipts/' . rawurlencode($filename);
}

function hds_utility_doc_thumb_url(string $filename): string
{
    return 'uploads/receipts/thumbs/' . rawurlencode(hds_utility_doc_thumb_name($filename));
}

function hds_utility_doc_thumb_name(string $pdf_filename): string
{
    return pathinfo($pdf_filename, PATHINFO_FILENAME) . '.png';
}

function hds_utility_doc_is_image(string $filename): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, hds_utility_doc_image_extensions(), true);
}

function hds_utility_doc_image_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_utility_doc_allowed_extensions(string $doc_type): array
{
    if ($doc_type === 'bill') {
        return array_merge(['pdf'], hds_utility_doc_image_extensions());
    }
    return ['pdf'];
}

function hds_utility_doc_display_name(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = preg_replace('/_\d{9,}_\d+$/', '', $base);
    $base = preg_replace('/_\d{9,}$/', '', $base);
    $base = str_replace('_', ' ', $base);
    $base = trim($base);
    if ($base === '') {
        return $filename;
    }
    return $ext !== '' ? $base . '.' . $ext : $base;
}

function hds_utility_doc_ensure_dirs(): ?string
{
    $dir = hds_utility_doc_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return 'Could not create the receipts folder.';
    }
    $thumbs = hds_utility_doc_thumb_dir();
    if (!is_dir($thumbs) && !@mkdir($thumbs, 0775, true)) {
        return 'Could not create the receipt thumbnail folder.';
    }
    if (!is_writable($dir)) {
        return 'The receipts folder is not writable. Ask your admin to run: chown -R www-data:www-data uploads/receipts && chmod 775 uploads/receipts';
    }
    return null;
}

function hds_utility_doc_generate_thumb(string $filename): bool
{
    if (hds_utility_doc_is_image($filename)) {
        return is_file(hds_utility_doc_dir() . $filename);
    }
    $pdf = hds_utility_doc_dir() . $filename;
    if (!is_file($pdf)) {
        return false;
    }
    $dir_error = hds_utility_doc_ensure_dirs();
    if ($dir_error !== null) {
        return false;
    }
    $thumb = hds_utility_doc_thumb_dir() . hds_utility_doc_thumb_name($filename);
    if (is_file($thumb) && filesize($thumb) > 0) {
        return true;
    }
    $cmd = 'mutool draw -q -F png -w 400 -o ' . escapeshellarg($thumb) . ' ' . escapeshellarg($pdf) . ' 1 2>/dev/null';
    exec($cmd, $out, $code);
    return is_file($thumb) && filesize($thumb) > 0;
}

function hds_utility_doc_delete_files(string $filename): void
{
    $pdf = hds_utility_doc_dir() . $filename;
    if (is_file($pdf)) {
        unlink($pdf);
    }
    $thumb = hds_utility_doc_thumb_dir() . hds_utility_doc_thumb_name($filename);
    if (is_file($thumb)) {
        unlink($thumb);
    }
}

function hds_utility_doc_sanitize_basename(string $raw): string
{
    $basename = basename($raw);
    $basename = preg_replace('/[^a-zA-Z0-9._\- ()]/', '_', $basename);
    $basename = trim($basename, '. ');
    if (str_contains($basename, '.')) {
        $basename = pathinfo($basename, PATHINFO_FILENAME);
        $basename = trim($basename, '. ');
    }
    return $basename;
}

function hds_utility_doc_rename(string $old_filename, string $new_basename): array
{
    $old_ext = strtolower(pathinfo($old_filename, PATHINFO_EXTENSION));
    $basename = hds_utility_doc_sanitize_basename($new_basename);
    if ($basename === '') {
        return ['ok' => false, 'error' => 'Please enter a valid file name.'];
    }
    $new_name = ($old_ext !== '') ? $basename . '.' . $old_ext : $basename;
    if ($new_name === $old_filename) {
        return ['ok' => true, 'filename' => $old_filename];
    }

    $dir = hds_utility_doc_dir();
    $old_path = $dir . $old_filename;
    $new_path = $dir . $new_name;
    if (file_exists($new_path)) {
        return ['ok' => false, 'error' => 'A file with that name already exists.'];
    }

    if (is_file($old_path) && !rename($old_path, $new_path)) {
        return ['ok' => false, 'error' => 'Could not rename the file on disk.'];
    }

    $old_thumb = hds_utility_doc_thumb_dir() . hds_utility_doc_thumb_name($old_filename);
    $new_thumb = hds_utility_doc_thumb_dir() . hds_utility_doc_thumb_name($new_name);
    if (is_file($old_thumb)) {
        if (file_exists($new_thumb) || !@rename($old_thumb, $new_thumb)) {
            @unlink($old_thumb);
        }
    }

    return ['ok' => true, 'filename' => $new_name];
}

function hds_utility_normalize_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
        $raw = 'https://' . $raw;
    }
    $parts = parse_url($raw);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
        return '';
    }
    return $raw;
}

function hds_utility_payment_url_html(?string $url): string
{
    $url = trim((string)$url);
    if ($url === '') {
        return hds_ve_display('');
    }
    $href = hds_utility_normalize_url($url);
    if ($href === '') {
        return hds_ve_display($url);
    }
    $href_esc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars(preg_replace('#^https?://#i', '', $href), ENT_QUOTES, 'UTF-8');
    return "<a href='$href_esc' target='_blank' rel='noopener noreferrer' class='utility-pay-link'>$label</a>";
}

function hds_utility_normalize_type(string $type): string
{
    return $type === 'propane' ? 'propane' : 'water';
}

function hds_utility_docs_table(string $utility_type): string
{
    return hds_utility_normalize_type($utility_type) === 'propane' ? 'propane_receipts' : 'water_receipts';
}

function hds_utility_bill_owned(mysqli $conn, int $bill_id, int $house_id, string $utility_type = 'water'): bool
{
    if ($bill_id <= 0) {
        return false;
    }
    $utility_type = hds_utility_normalize_type($utility_type);
    $type_sql = mysqli_real_escape_string($conn, $utility_type);
    $result = $conn->query(
        "SELECT id FROM utility_bills WHERE id=$bill_id AND house_id=$house_id AND utility_type='$type_sql' LIMIT 1"
    );
    return $result && $result->num_rows > 0;
}

function hds_utility_docs_upload(mysqli $conn, int $bill_id, string $doc_type, string $field_name, string $utility_type = 'water'): int
{
    $doc_type = $doc_type === 'bill' ? 'bill' : 'receipt';
    $table = hds_utility_docs_table($utility_type);
    $dir_error = hds_utility_doc_ensure_dirs();
    if ($dir_error !== null) {
        return 0;
    }
    if (empty($_FILES[$field_name]['name'])) {
        return 0;
    }

    $names = $_FILES[$field_name]['name'];
    $tmps = $_FILES[$field_name]['tmp_name'];
    $errors = $_FILES[$field_name]['error'];
    if (!is_array($names)) {
        $names = [$names];
        $tmps = [$tmps];
        $errors = [$errors];
    }

    $count = 0;
    $max = 5;
    $dir = hds_utility_doc_dir();
    $allowed = hds_utility_doc_allowed_extensions($doc_type);
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
        $safe_base = trim($safe_base, '. ') ?: 'document';
        $final_name = $safe_base . '_' . time() . '_' . $count . '.' . $ext;
        $target = $dir . $final_name;
        if (!move_uploaded_file($tmp, $target)) {
            continue;
        }
        $safe_name = mysqli_real_escape_string($conn, $final_name);
        if ($conn->query("INSERT INTO $table (bill_id, filename, doc_type, upload_date) VALUES ($bill_id, '$safe_name', '$doc_type', NOW())")) {
            hds_utility_doc_generate_thumb($final_name);
            $count++;
        } else {
            @unlink($target);
        }
    }
    return $count;
}

function hds_render_utility_pdf_cards(mysqli $conn, int $house_id, int $bill_id, string $doc_type, string $utility_type = 'water'): void
{
    $doc_type = $doc_type === 'bill' ? 'bill' : 'receipt';
    $utility_type = hds_utility_normalize_type($utility_type);
    $table = hds_utility_docs_table($utility_type);
    $docs = $conn->query(
        "SELECT r.id, r.filename, r.upload_date
         FROM $table r
         INNER JOIN utility_bills b ON r.bill_id = b.id
         WHERE r.bill_id = $bill_id AND b.house_id = $house_id AND r.doc_type = '$doc_type'
         ORDER BY r.upload_date DESC, r.id DESC"
    );
    if (!$docs || $docs->num_rows === 0) {
        $empty = $doc_type === 'bill' ? 'No bill copy uploaded yet.' : 'No payment receipts uploaded yet.';
        echo "<p class='empty-note utility-pdf-empty'>$empty</p>";
        return;
    }

    echo "<div class='utility-pdf-grid' data-lightbox-gallery='$utility_type-$doc_type-$bill_id'>";
    while ($doc = $docs->fetch_assoc()) {
        $doc_id = (int)$doc['id'];
        $filename = $doc['filename'];
        $is_image = hds_utility_doc_is_image($filename);
        $url = htmlspecialchars(hds_utility_doc_url($filename), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(hds_utility_doc_display_name($filename), ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars(date('M j, Y g:i A', strtotime($doc['upload_date'])), ENT_QUOTES, 'UTF-8');
        $has_thumb = hds_utility_doc_generate_thumb($filename);
        $thumb_src = $is_image ? $url : htmlspecialchars(hds_utility_doc_thumb_url($filename), ENT_QUOTES, 'UTF-8');
        $kind = $is_image ? 'photo' : 'PDF';

        echo "<div class='utility-pdf-card'>";
        if ($is_image) {
            echo "<button type='button' class='media-lightbox-trigger utility-pdf-thumb' data-src='$url' data-caption='$label' aria-label='View $label'>";
            echo "<img src='$thumb_src' alt='$label' loading='lazy'>";
            echo "</button>";
        } else {
            echo "<a href='$url' target='_blank' rel='noopener' class='utility-pdf-thumb' aria-label='Open $label'>";
            if ($has_thumb) {
                echo "<img src='$thumb_src' alt='' loading='lazy'>";
            } else {
                echo "<span class='utility-pdf-fallback'><i class='fas fa-file-pdf' aria-hidden='true'></i></span>";
            }
            echo "</a>";
        }
        echo "<div class='utility-pdf-meta'>";
        echo "<a href='$url' target='_blank' rel='noopener' class='utility-pdf-name'>$label</a>";
        echo "<p class='utility-pdf-date'>Uploaded $date</p>";
        $filename_attr = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
        $rename_base = htmlspecialchars(pathinfo(hds_utility_doc_display_name($filename), PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
        echo "<div class='utility-pdf-actions'>";
        echo "<a href='$url' target='_blank' rel='noopener' class='small-btn'>Open</a>";
        echo "<a href='$url' download class='small-btn'>Download</a>";
        echo "<button type='button' class='small-btn water-doc-rename-open' data-water-doc-id='$doc_id' data-bill-id='$bill_id' data-utility-type='$utility_type' data-filename=\"$filename_attr\" data-rename-base=\"$rename_base\">Rename</button>";
        echo "<form method='post' class='utility-pdf-delete' onsubmit='return confirm(\"Delete this $kind?\");'>";
        echo "<input type='hidden' name='bill_id' value='$bill_id'>";
        echo "<input type='hidden' name='water_doc_id' value='$doc_id'>";
        echo "<input type='hidden' name='utility_type' value='$utility_type'>";
        echo "<button type='submit' name='delete_water_doc' class='small-btn delete-btn'>Delete</button>";
        echo "</form>";
        echo "</div>";
        echo "</div>";
        if (!$is_image) {
            echo "<details class='utility-pdf-embed'>";
            echo "<summary>Preview PDF</summary>";
            echo "<iframe src='$url' title='$label' loading='lazy'></iframe>";
            echo "</details>";
        }
        echo "</div>";
    }
    echo "</div>";
}

function hds_render_utility_pdf_upload(int $bill_id, string $doc_type, string $utility_type = 'water'): void
{
    $doc_type = $doc_type === 'bill' ? 'bill' : 'receipt';
    $utility_type = hds_utility_normalize_type($utility_type);
    if ($utility_type === 'propane') {
        $field = $doc_type === 'bill' ? 'propane_bills' : 'propane_receipts';
        $submit = $doc_type === 'bill' ? 'upload_propane_bill_pdf' : 'upload_propane_receipt';
        $kind = 'propane';
    } else {
        $field = $doc_type === 'bill' ? 'water_bills' : 'receipts';
        $submit = $doc_type === 'bill' ? 'upload_water_bill_pdf' : 'upload_receipt';
        $kind = 'water';
    }
    $label = $doc_type === 'bill' ? 'Upload bill copy' : 'Upload receipt PDF';
    $hint = $doc_type === 'bill'
        ? "PDF or photo of the $kind bill (JPG, PNG, WebP, GIF, or PDF — max 5)"
        : 'PDF payment receipt(s) (max 5)';
    $accept = $doc_type === 'bill'
        ? 'application/pdf,.pdf,image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp'
        : 'application/pdf,.pdf';

    echo "<form method='post' enctype='multipart/form-data' class='utility-pdf-upload'>";
    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
    echo "<input type='hidden' name='utility_type' value='$utility_type'>";
    echo "<label class='utility-pdf-upload-label'>$label</label>";
    echo "<input type='file' name='{$field}[]' accept='$accept' multiple required>";
    echo "<input type='submit' name='$submit' value='Upload' class='small-btn'>";
    echo "<span class='utility-pdf-upload-hint'>$hint</span>";
    echo "</form>";
}

function hds_render_utility_bill_entry(mysqli $conn, int $house_id, array $bill, string $utility_type, int $open_bill_id = 0): void
{
    $utility_type = hds_utility_normalize_type($utility_type);
    $bill_id = (int)($bill['id'] ?? 0);
    $paid_class = !empty($bill['is_paid']) ? 'paid' : 'unpaid';
    $paid_label = !empty($bill['is_paid']) ? 'Paid' : 'Unpaid';
    $payment_method = $bill['payment_method'] ?? '';
    $is_open = ($bill_id > 0 && $bill_id === $open_bill_id) ? ' open' : '';
    $due = htmlspecialchars((string)($bill['due_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $amount = number_format((float)($bill['amount_owed'] ?? 0), 2);
    $delete_msg = $utility_type === 'propane'
        ? 'Delete this bill and all attached files?'
        : 'Delete this bill and all attached PDFs?';

    echo "<details class='billing-entry $paid_class collapsible-section' id='bill-$bill_id'$is_open>";
    echo "<summary class='collapsible-summary billing-entry-summary'>";
    echo "<i class='fas fa-chevron-right collapsible-chevron' aria-hidden='true'></i>";
    echo "<span class='collapsible-summary-title'><strong>\$$amount</strong> — Due: $due</span>";
    echo "<span class='billing-status-badge billing-status-badge--$paid_class'>$paid_label</span>";
    echo "<div class='billing-entry-actions' onclick='event.stopPropagation()'>";
    echo "<form method='post' class='billing-paid-form'>";
    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
    echo "<label class='billing-paid-label'>Paid: <input type='checkbox' name='is_paid' " . (!empty($bill['is_paid']) ? 'checked' : '') . " onchange='this.form.submit();'></label>";
    echo "<label class='billing-paid-label'>Paid with:";
    echo "<select name='payment_method' class='billing-payment-select' onchange='this.form.submit();'>";
    echo "<option value=''" . ($payment_method === '' ? ' selected' : '') . ">—</option>";
    echo "<option value='debit'" . ($payment_method === 'debit' ? ' selected' : '') . ">Debit</option>";
    echo "<option value='credit'" . ($payment_method === 'credit' ? ' selected' : '') . ">Credit</option>";
    echo "<option value='check'" . ($payment_method === 'check' ? ' selected' : '') . ">Check</option>";
    echo "</select></label>";
    echo "<input type='hidden' name='toggle_bill_paid' value='1'>";
    echo "</form>";
    echo "<form method='post' onsubmit='return confirm(\"" . htmlspecialchars($delete_msg, ENT_QUOTES, 'UTF-8') . "\");'>";
    echo "<input type='hidden' name='bill_id' value='$bill_id'>";
    echo "<input type='submit' name='delete_bill' value='Delete' class='delete-bill-btn'>";
    echo "</form>";
    echo "</div>";
    echo "</summary>";
    echo "<div class='collapsible-body'>";
    echo "<div class='utility-docs-block'>";
    echo "<h5 class='utility-docs-heading'><i class='fas fa-file-invoice' aria-hidden='true'></i> Bill copy</h5>";
    hds_render_utility_pdf_cards($conn, $house_id, $bill_id, 'bill', $utility_type);
    hds_render_utility_pdf_upload($bill_id, 'bill', $utility_type);
    echo "</div>";
    echo "<div class='utility-docs-block'>";
    echo "<h5 class='utility-docs-heading'><i class='fas fa-receipt' aria-hidden='true'></i> Payment receipts</h5>";
    hds_render_utility_pdf_cards($conn, $house_id, $bill_id, 'receipt', $utility_type);
    hds_render_utility_pdf_upload($bill_id, 'receipt', $utility_type);
    echo "</div>";
    echo "</div>";
    echo "</details>";
}
