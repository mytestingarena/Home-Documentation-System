<?php
// includes/firearms.php — firearm inventory helpers and photo gallery

function hds_firearms_types(): array
{
    return [
        'pistol' => 'Pistol',
        'revolver' => 'Revolver',
        'rifle' => 'Rifle',
        'shotgun' => 'Shotgun',
        'other' => 'Other',
    ];
}

function hds_firearms_actions(): array
{
    return [
        'semi_auto' => 'Semi-automatic',
        'bolt' => 'Bolt action',
        'pump' => 'Pump action',
        'lever' => 'Lever action',
        'revolver' => 'Revolver',
        'break' => 'Break action',
        'other' => 'Other',
    ];
}

function hds_firearms_type_label(string $type): string
{
    return hds_firearms_types()[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function hds_firearms_action_label(string $action): string
{
    return hds_firearms_actions()[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

function hds_firearms_esc(mysqli $conn, ?string $value): string
{
    return mysqli_real_escape_string($conn, trim((string)$value));
}

function hds_firearms_sql_date(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return 'NULL';
    }
    return "'" . $raw . "'";
}

function hds_firearms_sql_price(?string $raw): string
{
    $raw = trim((string)$raw);
    if ($raw === '' || !is_numeric($raw)) {
        return 'NULL';
    }
    return number_format((float)$raw, 2, '.', '');
}

function hds_firearms_parse_post(mysqli $conn): array
{
    $types = array_keys(hds_firearms_types());
    $actions = array_keys(hds_firearms_actions());
    $type = $_POST['firearm_type'] ?? 'other';
    $action = $_POST['firearm_action'] ?? 'other';
    if (!in_array($type, $types, true)) {
        $type = 'other';
    }
    if (!in_array($action, $actions, true)) {
        $action = 'other';
    }

    return [
        'name' => hds_firearms_esc($conn, $_POST['firearm_name'] ?? ''),
        'firearm_type' => hds_firearms_esc($conn, $type),
        'manufacturer' => hds_firearms_esc($conn, $_POST['firearm_manufacturer'] ?? ''),
        'model' => hds_firearms_esc($conn, $_POST['firearm_model'] ?? ''),
        'caliber' => hds_firearms_esc($conn, $_POST['firearm_caliber'] ?? ''),
        'serial_number' => hds_firearms_esc($conn, $_POST['firearm_serial'] ?? ''),
        'barrel_length' => hds_firearms_esc($conn, $_POST['firearm_barrel'] ?? ''),
        'action_type' => hds_firearms_esc($conn, $action),
        'finish' => hds_firearms_esc($conn, $_POST['firearm_finish'] ?? ''),
        'purchase_date_sql' => hds_firearms_sql_date($_POST['firearm_purchase_date'] ?? ''),
        'purchase_price_sql' => hds_firearms_sql_price($_POST['firearm_purchase_price'] ?? ''),
        'storage_location' => hds_firearms_esc($conn, $_POST['firearm_storage'] ?? ''),
        'notes' => hds_firearms_esc($conn, $_POST['firearm_notes'] ?? ''),
    ];
}

function hds_render_firearms_type_select(string $name, string $selected = 'pistol'): void
{
    echo "<select name='$name' required>";
    foreach (hds_firearms_types() as $value => $label) {
        $sel = $value === $selected ? ' selected' : '';
        echo "<option value='$value'$sel>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
    }
    echo "</select>";
}

function hds_render_firearms_action_select(string $name, string $selected = 'semi_auto'): void
{
    echo "<select name='$name'>";
    echo "<option value=''" . ($selected === '' ? ' selected' : '') . ">—</option>";
    foreach (hds_firearms_actions() as $value => $label) {
        $sel = $value === $selected ? ' selected' : '';
        echo "<option value='$value'$sel>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
    }
    echo "</select>";
}

function hds_firearms_form_field(string $label, string $name, ?string $value = '', string $placeholder = '', bool $required = false, string $input_type = 'text'): void
{
    $value_esc = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    $placeholder_esc = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $req = $required ? ' required' : '';
    echo "<div class='homelab-field'>";
    echo "<label>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</label>";
    $step = $input_type === 'number' ? ' step="0.01" min="0"' : '';
    echo "<input type='$input_type' name='$name' value=\"$value_esc\" placeholder='$placeholder_esc'$req$step>";
    echo "</div>";
}

function hds_firearms_form_textarea(string $label, string $name, ?string $value = '', string $placeholder = ''): void
{
    $value_esc = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    $placeholder_esc = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    echo "<div class='homelab-field homelab-field--wide'>";
    echo "<label>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</label>";
    echo "<textarea name='$name' rows='2' placeholder='$placeholder_esc'>$value_esc</textarea>";
    echo "</div>";
}

function hds_firearms_upload_dir(): string
{
    return __DIR__ . '/../uploads/firearms/';
}

function hds_firearms_ensure_upload_dir(): ?string
{
    $target_dir = hds_firearms_upload_dir();
    if (!is_dir($target_dir)) {
        if (!@mkdir($target_dir, 0775, true)) {
            return 'Could not create the photo upload folder on the server.';
        }
    }
    if (!is_writable($target_dir)) {
        return 'The photo upload folder is not writable by the web server. Ask your admin to run: chown -R www-data:www-data uploads/firearms && chmod 775 uploads/firearms';
    }
    return null;
}

function hds_firearms_upload_url(string $filename): string
{
    return 'uploads/firearms/' . rawurlencode($filename);
}

function hds_firearms_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function hds_firearms_sanitize_basename(string $raw): string
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

function hds_firearms_delete_image_files(mysqli $conn, int $firearm_id, int $house_id): void
{
    $firearm_id = (int)$firearm_id;
    $images = $conn->query(
        "SELECT i.filename
         FROM firearm_images i
         INNER JOIN firearms f ON i.firearm_id = f.id
         WHERE i.firearm_id = $firearm_id AND f.house_id = $house_id"
    );
    if (!$images) {
        return;
    }
    $dir = hds_firearms_upload_dir();
    while ($row = $images->fetch_assoc()) {
        $path = $dir . $row['filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function hds_render_firearm_images(mysqli $conn, int $house_id, int $firearm_id): void
{
    $firearm_id = (int)$firearm_id;
    $images = $conn->query(
        "SELECT i.id, i.filename, i.upload_date
         FROM firearm_images i
         INNER JOIN firearms f ON i.firearm_id = f.id
         WHERE i.firearm_id = $firearm_id AND f.house_id = $house_id
         ORDER BY i.upload_date DESC, i.id DESC"
    );

    echo "<div class='outdoor-work-images firearms-images'>";
    echo "<h5><i class='fas fa-images' aria-hidden='true'></i> Photos</h5>";

    if ($images && $images->num_rows > 0) {
        echo "<div class='outdoor-work-gallery' data-lightbox-gallery='firearm-$firearm_id'>";
        while ($image = $images->fetch_assoc()) {
            $image_id = (int)$image['id'];
            $filename = $image['filename'];
            $url = htmlspecialchars(hds_firearms_upload_url($filename), ENT_QUOTES, 'UTF-8');
            $caption = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');

            echo "<div class='outdoor-work-photo'>";
            echo "<button type='button' class='media-lightbox-trigger outdoor-work-photo-thumb' data-src='$url' data-caption='$caption' aria-label='View full size'>";
            echo "<img src='$url' alt='$caption' loading='lazy'>";
            echo "</button>";
            echo "<p class='outdoor-work-photo-name' title='$caption'>$caption</p>";
            echo "<div class='outdoor-work-photo-actions'>";
            echo "<button type='button' class='small-btn firearm-rename-open' data-firearm-image-id='$image_id' data-firearm-id='$firearm_id' data-filename=\"$caption\">Rename</button>";
            echo "<form method='post' class='outdoor-work-photo-delete' onsubmit='return confirm(\"Delete this photo?\");'>";
            echo "<input type='hidden' name='firearm_id' value='$firearm_id'>";
            echo "<input type='hidden' name='firearm_image_id' value='$image_id'>";
            echo "<button type='submit' name='delete_firearm_image' class='small-btn delete-btn'>Delete</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p class='empty-note outdoor-work-images-empty'>No photos yet.</p>";
    }

    echo "<form method='post' enctype='multipart/form-data' class='outdoor-work-upload-form'>";
    echo "<input type='hidden' name='firearm_id' value='$firearm_id'>";
    echo "<label class='outdoor-work-upload-label'>Add photos of this firearm:</label>";
    echo "<input type='file' name='firearm_images[]' accept='image/jpeg,image/png,image/gif,image/webp' multiple required>";
    echo "<input type='submit' name='upload_firearm_image' value='Upload Photos' class='small-btn'>";
    echo "<span class='outdoor-work-upload-hint'>Photos attach to this record — use <strong>Upload Photos</strong> after choosing a file.</span>";
    echo "</form>";
    echo "</div>";
}
