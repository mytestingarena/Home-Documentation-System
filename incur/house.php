<?php
// house.php — COMPLETE with propane receipt upload handler added

include 'config.php';

if (!defined('WIFI_TAB_PASSWORD')) {
    define('WIFI_TAB_PASSWORD', 'change_me_before_deploy');
}

if (!defined('ADMIN_TAB_PASSWORD')) {
    define('ADMIN_TAB_PASSWORD', WIFI_TAB_PASSWORD);
}

require_once __DIR__ . '/includes/ui-settings.php';
require_once __DIR__ . '/includes/view-edit.php';
require_once __DIR__ . '/includes/permanent-maintenance-log.php';
require_once __DIR__ . '/includes/permanent-log-images.php';
require_once __DIR__ . '/includes/outdoor-work-images.php';
require_once __DIR__ . '/includes/house-work-images.php';
require_once __DIR__ . '/includes/homelab.php';
require_once __DIR__ . '/includes/sidebar-nav.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$house_id = intval($_GET['id'] ?? 0);
if ($house_id <= 0) {
    die("Invalid house ID");
}

// Check if house exists
$sql = "SELECT name, address, latitude, longitude, tax_number, map_zoom, google_embed_src FROM houses WHERE id = $house_id LIMIT 1";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    die("House ID $house_id not found in database");
}
$house = $result->fetch_assoc();
$house_name = htmlspecialchars($house['name'] ?? 'Unknown House');

$valid_tabs = ['permanent', 'utility', 'household', 'contractors', 'homelab', 'tools', 'maintenance', 'media', 'designs', 'manuals', 'map', 'wifi', 'projects', 'admin'];
$hds_ui_settings = hds_ui_load_settings($conn, $house_id);
$active_tab = $_GET['tab'] ?? 'permanent';
if (!in_array($active_tab, $valid_tabs, true)) {
    $active_tab = 'permanent';
}
if ($active_tab !== 'admin' && !hds_ui_tab_enabled($active_tab, $hds_ui_settings)) {
    $active_tab = hds_ui_first_enabled_tab($hds_ui_settings);
}

function house_redirect(int $house_id, string $tab = 'permanent', $open_section = 0, string $open_param = 'open_equipment'): void {
    global $valid_tabs;
    if (!in_array($tab, $valid_tabs, true)) {
        $tab = 'permanent';
    }
    $url = 'house.php?id=' . $house_id . '&tab=' . urlencode($tab);
    if ($open_param !== '' && $open_section !== 0 && $open_section !== '') {
        $url .= '&' . urlencode($open_param) . '=' . urlencode((string)$open_section);
    }
    header('Location: ' . $url);
    exit;
}

// Upload limits
ini_set('upload_max_filesize', '128M');
ini_set('post_max_size', '256M');
ini_set('max_file_uploads', '20');

// POST handling – redirect to CURRENT URL after any change
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // WIFI TAB - UNLOCK / LOCK
    if (isset($_POST['wifi_unlock'])) {
        $access_password = $_POST['wifi_access_password'] ?? '';
        if (hash_equals(WIFI_TAB_PASSWORD, $access_password)) {
            $_SESSION['wifi_unlocked'] = true;
            unset($_SESSION['wifi_error']);
        } else {
            $_SESSION['wifi_error'] = 'Incorrect password. Please try again.';
        }
        house_redirect($house_id, 'wifi');
    }

    if (isset($_POST['wifi_lock'])) {
        unset($_SESSION['wifi_unlocked']);
        house_redirect($house_id, 'wifi');
    }

    // ADMIN TAB - UNLOCK / LOCK
    if (isset($_POST['admin_unlock'])) {
        $access_password = $_POST['admin_access_password'] ?? '';
        if (hash_equals(ADMIN_TAB_PASSWORD, $access_password)) {
            $_SESSION['admin_unlocked'] = true;
            unset($_SESSION['admin_error']);
        } else {
            $_SESSION['admin_error'] = 'Incorrect password. Please try again.';
        }
        house_redirect($house_id, 'admin');
    }

    if (isset($_POST['admin_lock'])) {
        unset($_SESSION['admin_unlocked']);
        house_redirect($house_id, 'admin');
    }

    if (isset($_POST['save_ui_settings']) && !empty($_SESSION['admin_unlocked'])) {
        $enabled_keys = $_POST['ui_enabled'] ?? [];
        if (!is_array($enabled_keys)) {
            $enabled_keys = [];
        }
        $allowed = array_flip(hds_ui_all_setting_keys());
        $enabled_lookup = [];
        foreach ($enabled_keys as $key) {
            $key = (string)$key;
            if (isset($allowed[$key])) {
                $enabled_lookup[$key] = true;
            }
        }

        $conn->query("DELETE FROM house_ui_settings WHERE house_id = $house_id");
        foreach (hds_ui_all_setting_keys() as $key) {
            $enabled = isset($enabled_lookup[$key]) ? 1 : 0;
            $safe_key = mysqli_real_escape_string($conn, $key);
            $conn->query("INSERT INTO house_ui_settings (house_id, setting_key, enabled)
                          VALUES ($house_id, '$safe_key', $enabled)");
        }

        header('Location: house.php?id=' . $house_id . '&tab=admin&admin_saved=1');
        exit;
    }

    // PERMANENT ITEMS UPDATE (single item)
    if (isset($_POST['update_permanent_item'])) {
        $item_types = ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac'];
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');

        if (in_array($type, $item_types, true)) {
            $brand       = mysqli_real_escape_string($conn, $_POST["{$type}_brand"] ?? '');
            $model       = mysqli_real_escape_string($conn, $_POST["{$type}_model"] ?? '');
            $sn          = mysqli_real_escape_string($conn, $_POST["{$type}_sn"] ?? '');
            $efficiency  = mysqli_real_escape_string($conn, $_POST["{$type}_efficiency"] ?? '');
            $kwh         = floatval($_POST["{$type}_kwh"] ?? 0);
            $capacity    = intval($_POST["{$type}_capacity"] ?? 0);

            $sql = "INSERT INTO permanent_items 
                    (house_id, item_type, brand, model, sn, efficiency, kwh, capacity) 
                    VALUES ($house_id, '$type', '$brand', '$model', '$sn', '$efficiency', $kwh, $capacity)
                    ON DUPLICATE KEY UPDATE 
                    brand = VALUES(brand), model = VALUES(model), sn = VALUES(sn), 
                    efficiency = VALUES(efficiency), kwh = VALUES(kwh), capacity = VALUES(capacity)";

            if (!$conn->query($sql)) {
                error_log("Permanent Items update failed for $type: " . $conn->error);
                header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=0');
                exit;
            }

            header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
            exit;
        }

        house_redirect($house_id, 'permanent');
    }

    // OUTDOOR WORK
    $outdoor_work_types = ['deck_patio', 'fence', 'driveway', 'landscaping', 'irrigation', 'pool', 'shed', 'gutters', 'roofing', 'siding', 'retaining_wall', 'other'];

    if (isset($_POST['add_outdoor_work']) && !empty(trim($_POST['outdoor_description'] ?? ''))) {
        $work_type = $_POST['outdoor_work_type'] ?? 'other';
        if (!in_array($work_type, $outdoor_work_types, true)) {
            $work_type = 'other';
        }
        $work_type = mysqli_real_escape_string($conn, $work_type);
        $description = mysqli_real_escape_string($conn, trim($_POST['outdoor_description']));
        $date_completed = mysqli_real_escape_string($conn, $_POST['outdoor_date_completed'] ?? '');
        $contractor = mysqli_real_escape_string($conn, trim($_POST['outdoor_contractor'] ?? ''));
        $notes = mysqli_real_escape_string($conn, trim($_POST['outdoor_notes'] ?? ''));
        $date_sql = $date_completed !== '' ? "'$date_completed'" : 'NULL';
        $conn->query("INSERT INTO outdoor_work_items (house_id, work_type, description, date_completed, contractor, notes)
                      VALUES ($house_id, '$work_type', '$description', $date_sql, '$contractor', '$notes')");
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }
    if (isset($_POST['update_outdoor_work']) && !empty(trim($_POST['outdoor_description'] ?? ''))) {
        $outdoor_id = intval($_POST['outdoor_id'] ?? 0);
        $work_type = $_POST['outdoor_work_type'] ?? 'other';
        if (!in_array($work_type, $outdoor_work_types, true)) {
            $work_type = 'other';
        }
        $work_type = mysqli_real_escape_string($conn, $work_type);
        $description = mysqli_real_escape_string($conn, trim($_POST['outdoor_description']));
        $date_completed = mysqli_real_escape_string($conn, $_POST['outdoor_date_completed'] ?? '');
        $contractor = mysqli_real_escape_string($conn, trim($_POST['outdoor_contractor'] ?? ''));
        $notes = mysqli_real_escape_string($conn, trim($_POST['outdoor_notes'] ?? ''));
        $date_sql = $date_completed !== '' ? "'$date_completed'" : 'NULL';
        if ($outdoor_id > 0) {
            $conn->query("UPDATE outdoor_work_items
                          SET work_type='$work_type', description='$description', date_completed=$date_sql,
                              contractor='$contractor', notes='$notes'
                          WHERE id=$outdoor_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }
    if (isset($_POST['delete_outdoor_work'])) {
        $outdoor_id = intval($_POST['outdoor_id'] ?? 0);
        if ($outdoor_id > 0) {
            hds_outdoor_work_delete_image_files($conn, $outdoor_id, $house_id);
            $conn->query("DELETE FROM outdoor_work_items WHERE id=$outdoor_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }
    if (isset($_POST['upload_outdoor_work_image'])) {
        $outdoor_id = intval($_POST['outdoor_id'] ?? 0);
        $owned = $outdoor_id > 0
            ? $conn->query("SELECT id FROM outdoor_work_items WHERE id=$outdoor_id AND house_id=$house_id LIMIT 1")
            : null;
        $upload_errors = [];
        $upload_count = 0;

        if (!$owned || $owned->num_rows === 0) {
            $upload_errors[] = 'Outdoor work item not found.';
        } else {
            $dir_error = hds_outdoor_work_ensure_upload_dir();
            if ($dir_error !== null) {
                $upload_errors[] = $dir_error;
            } elseif (empty($_FILES['outdoor_images']['name']) || !is_array($_FILES['outdoor_images']['name'])) {
                $upload_errors[] = 'No photo file was selected.';
            } else {
                $target_dir = hds_outdoor_work_upload_dir();
                $allowed = hds_outdoor_work_allowed_extensions();
                $max = 10;
                foreach ($_FILES['outdoor_images']['tmp_name'] as $k => $tmp) {
                    if ($upload_count >= $max) {
                        break;
                    }
                    $original_name = basename((string)($_FILES['outdoor_images']['name'][$k] ?? ''));
                    $upload_err = (int)($_FILES['outdoor_images']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                    if ($upload_err !== UPLOAD_ERR_OK) {
                        if ($original_name !== '') {
                            $upload_errors[] = $original_name . ': upload failed (error code ' . $upload_err . ').';
                        }
                        continue;
                    }
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        $upload_errors[] = $original_name . ': file type not allowed (use JPG, PNG, GIF, or WebP).';
                        continue;
                    }
                    $final_name = time() . '_' . $outdoor_id . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original_name);
                    $target = $target_dir . $final_name;
                    if (move_uploaded_file($tmp, $target)) {
                        $safe_name = mysqli_real_escape_string($conn, $final_name);
                        if ($conn->query("INSERT INTO outdoor_work_images (outdoor_work_id, filename) VALUES ($outdoor_id, '$safe_name')")) {
                            $upload_count++;
                        } else {
                            @unlink($target);
                            $upload_errors[] = $original_name . ': saved to disk but database insert failed.';
                        }
                    } else {
                        $upload_errors[] = $original_name . ': could not save file (check folder permissions).';
                    }
                }
            }
        }

        if ($upload_count > 0) {
            $_SESSION['outdoor_photo_success'] = $upload_count === 1
                ? '1 photo uploaded successfully.'
                : $upload_count . ' photos uploaded successfully.';
        }
        if (!empty($upload_errors)) {
            $_SESSION['outdoor_photo_error'] = implode(' ', $upload_errors);
        } elseif ($upload_count === 0 && empty($_SESSION['outdoor_photo_success'])) {
            $_SESSION['outdoor_photo_error'] = 'No photos were uploaded.';
        }

        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }
    if (isset($_POST['delete_outdoor_work_image'])) {
        $outdoor_id = intval($_POST['outdoor_id'] ?? 0);
        $image_id = intval($_POST['outdoor_image_id'] ?? 0);
        if ($outdoor_id > 0 && $image_id > 0) {
            $result = $conn->query(
                "SELECT i.filename
                 FROM outdoor_work_images i
                 INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id
                 WHERE i.id=$image_id AND i.outdoor_work_id=$outdoor_id AND w.house_id=$house_id
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $path = hds_outdoor_work_upload_dir() . $row['filename'];
                if (is_file($path)) {
                    unlink($path);
                }
                $conn->query("DELETE FROM outdoor_work_images WHERE id=$image_id AND outdoor_work_id=$outdoor_id");
            }
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }
    if (isset($_POST['rename_outdoor_work_image'])) {
        $outdoor_id = intval($_POST['outdoor_id'] ?? 0);
        $image_id = intval($_POST['outdoor_image_id'] ?? 0);
        $new_basename_raw = trim($_POST['outdoor_image_basename'] ?? '');

        if ($outdoor_id > 0 && $image_id > 0 && $new_basename_raw !== '') {
            $result = $conn->query(
                "SELECT i.filename
                 FROM outdoor_work_images i
                 INNER JOIN outdoor_work_items w ON i.outdoor_work_id = w.id
                 WHERE i.id=$image_id AND i.outdoor_work_id=$outdoor_id AND w.house_id=$house_id
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $old_filename = $row['filename'];
                $old_ext = strtolower(pathinfo($old_filename, PATHINFO_EXTENSION));
                $basename = hds_outdoor_work_sanitize_basename($new_basename_raw);
                $new_name = ($old_ext !== '') ? $basename . '.' . $old_ext : $basename;

                if ($basename === '') {
                    $_SESSION['outdoor_photo_error'] = 'Please enter a valid file name.';
                } elseif ($new_name !== $old_filename) {
                    $dir = hds_outdoor_work_upload_dir();
                    $old_path = $dir . $old_filename;
                    $new_path = $dir . $new_name;
                    if (file_exists($new_path)) {
                        $_SESSION['outdoor_photo_error'] = 'A file with that name already exists.';
                    } elseif (file_exists($old_path) && rename($old_path, $new_path)) {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE outdoor_work_images SET filename='$safe_name' WHERE id=$image_id AND outdoor_work_id=$outdoor_id");
                        $_SESSION['outdoor_photo_success'] = 'Photo renamed successfully.';
                    } elseif (file_exists($old_path)) {
                        $_SESSION['outdoor_photo_error'] = 'Could not rename the file on disk.';
                    } else {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE outdoor_work_images SET filename='$safe_name' WHERE id=$image_id AND outdoor_work_id=$outdoor_id");
                        $_SESSION['outdoor_photo_success'] = 'Photo name updated.';
                    }
                }
            }
        } else {
            $_SESSION['outdoor_photo_error'] = 'Please enter a new file name.';
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=outdoor_work&saved=1');
        exit;
    }


    // HOUSE WORK
    $house_work_types = ['foundation', 'basement', 'framing', 'waterproofing', 'insulation', 'drywall', 'flooring', 'plumbing', 'electrical', 'hvac', 'windows_doors', 'kitchen_bath', 'painting', 'mold', 'other'];

    if (isset($_POST['add_house_work'])) {
        $description_raw = trim($_POST['house_description'] ?? '');
        $work_type = $_POST['house_work_type'] ?? '';
        if ($description_raw === '') {
            $_SESSION['house_work_error'] = 'Please enter a description.';
            header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work');
            exit;
        }
        if ($work_type === '' || !in_array($work_type, $house_work_types, true)) {
            $_SESSION['house_work_error'] = 'Please select a work type.';
            header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work');
            exit;
        }
        $work_type = mysqli_real_escape_string($conn, $work_type);
        $description = mysqli_real_escape_string($conn, $description_raw);
        $date_completed = mysqli_real_escape_string($conn, $_POST['house_date_completed'] ?? '');
        $contractor = mysqli_real_escape_string($conn, trim($_POST['house_contractor'] ?? ''));
        $notes = mysqli_real_escape_string($conn, trim($_POST['house_notes'] ?? ''));
        $date_sql = $date_completed !== '' ? "'$date_completed'" : 'NULL';
        $contractor_meta = hds_permanent_log_parse_contractor_fields($_POST);
        $completed_by = mysqli_real_escape_string($conn, $contractor_meta['completed_by']);
        $price_sql = $contractor_meta['contractor_price'] !== null ? $contractor_meta['contractor_price'] : 'NULL';
        $method_sql = $contractor_meta['payment_method'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor_meta['payment_method']) . "'"
            : 'NULL';
        $ref_sql = $contractor_meta['payment_reference'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor_meta['payment_reference']) . "'"
            : 'NULL';
        $conn->query("INSERT INTO house_work_items (house_id, work_type, description, date_completed, contractor, completed_by, contractor_price, payment_method, payment_reference, notes)
                      VALUES ($house_id, '$work_type', '$description', $date_sql, '$contractor', '$completed_by', $price_sql, $method_sql, $ref_sql, '$notes')");
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }
    if (isset($_POST['update_house_work']) && !empty(trim($_POST['house_description'] ?? ''))) {
        $house_work_id = intval($_POST['house_work_id'] ?? 0);
        $work_type = $_POST['house_work_type'] ?? 'other';
        if (!in_array($work_type, $house_work_types, true)) {
            $work_type = 'other';
        }
        $work_type = mysqli_real_escape_string($conn, $work_type);
        $description = mysqli_real_escape_string($conn, trim($_POST['house_description']));
        $date_completed = mysqli_real_escape_string($conn, $_POST['house_date_completed'] ?? '');
        $contractor = mysqli_real_escape_string($conn, trim($_POST['house_contractor'] ?? ''));
        $notes = mysqli_real_escape_string($conn, trim($_POST['house_notes'] ?? ''));
        $date_sql = $date_completed !== '' ? "'$date_completed'" : 'NULL';
        $contractor_meta = hds_permanent_log_parse_contractor_fields($_POST);
        $completed_by = mysqli_real_escape_string($conn, $contractor_meta['completed_by']);
        $price_sql = $contractor_meta['contractor_price'] !== null ? $contractor_meta['contractor_price'] : 'NULL';
        $method_sql = $contractor_meta['payment_method'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor_meta['payment_method']) . "'"
            : 'NULL';
        $ref_sql = $contractor_meta['payment_reference'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor_meta['payment_reference']) . "'"
            : 'NULL';
        if ($house_work_id > 0) {
            $conn->query("UPDATE house_work_items
                          SET work_type='$work_type', description='$description', date_completed=$date_sql,
                              contractor='$contractor', completed_by='$completed_by', contractor_price=$price_sql,
                              payment_method=$method_sql, payment_reference=$ref_sql, notes='$notes'
                          WHERE id=$house_work_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }
    if (isset($_POST['delete_house_work'])) {
        $house_work_id = intval($_POST['house_work_id'] ?? 0);
        if ($house_work_id > 0) {
            hds_house_work_delete_image_files($conn, $house_work_id, $house_id);
            $conn->query("DELETE FROM house_work_items WHERE id=$house_work_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }
    if (isset($_POST['upload_house_work_image'])) {
        $house_work_id = intval($_POST['house_work_id'] ?? 0);
        $owned = $house_work_id > 0
            ? $conn->query("SELECT id FROM house_work_items WHERE id=$house_work_id AND house_id=$house_id LIMIT 1")
            : null;
        $upload_errors = [];
        $upload_count = 0;

        if (!$owned || $owned->num_rows === 0) {
            $upload_errors[] = 'House work item not found.';
        } else {
            $dir_error = hds_house_work_ensure_upload_dir();
            if ($dir_error !== null) {
                $upload_errors[] = $dir_error;
            } elseif (empty($_FILES['house_images']['name']) || !is_array($_FILES['house_images']['name'])) {
                $upload_errors[] = 'No photo file was selected.';
            } else {
                $target_dir = hds_house_work_upload_dir();
                $allowed = hds_house_work_allowed_extensions();
                $max = 10;
                foreach ($_FILES['house_images']['tmp_name'] as $k => $tmp) {
                    if ($upload_count >= $max) {
                        break;
                    }
                    $original_name = basename((string)($_FILES['house_images']['name'][$k] ?? ''));
                    $upload_err = (int)($_FILES['house_images']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                    if ($upload_err !== UPLOAD_ERR_OK) {
                        if ($original_name !== '') {
                            $upload_errors[] = $original_name . ': upload failed (error code ' . $upload_err . ').';
                        }
                        continue;
                    }
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        $upload_errors[] = $original_name . ': file type not allowed (use JPG, PNG, GIF, or WebP).';
                        continue;
                    }
                    $final_name = time() . '_' . $house_work_id . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original_name);
                    $target = $target_dir . $final_name;
                    if (move_uploaded_file($tmp, $target)) {
                        $safe_name = mysqli_real_escape_string($conn, $final_name);
                        if ($conn->query("INSERT INTO house_work_images (house_work_id, filename) VALUES ($house_work_id, '$safe_name')")) {
                            $upload_count++;
                        } else {
                            @unlink($target);
                            $upload_errors[] = $original_name . ': saved to disk but database insert failed.';
                        }
                    } else {
                        $upload_errors[] = $original_name . ': could not save file (check folder permissions).';
                    }
                }
            }
        }

        if ($upload_count > 0) {
            $_SESSION['house_photo_success'] = $upload_count === 1
                ? '1 photo uploaded successfully.'
                : $upload_count . ' photos uploaded successfully.';
        }
        if (!empty($upload_errors)) {
            $_SESSION['house_photo_error'] = implode(' ', $upload_errors);
        } elseif ($upload_count === 0 && empty($_SESSION['house_photo_success'])) {
            $_SESSION['house_photo_error'] = 'No photos were uploaded.';
        }

        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }
    if (isset($_POST['delete_house_work_image'])) {
        $house_work_id = intval($_POST['house_work_id'] ?? 0);
        $image_id = intval($_POST['house_image_id'] ?? 0);
        if ($house_work_id > 0 && $image_id > 0) {
            $result = $conn->query(
                "SELECT i.filename
                 FROM house_work_images i
                 INNER JOIN house_work_items w ON i.house_work_id = w.id
                 WHERE i.id=$image_id AND i.house_work_id=$house_work_id AND w.house_id=$house_id
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $path = hds_house_work_upload_dir() . $row['filename'];
                if (is_file($path)) {
                    unlink($path);
                }
                $conn->query("DELETE FROM house_work_images WHERE id=$image_id AND house_work_id=$house_work_id");
            }
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }
    if (isset($_POST['rename_house_work_image'])) {
        $house_work_id = intval($_POST['house_work_id'] ?? 0);
        $image_id = intval($_POST['house_image_id'] ?? 0);
        $new_basename_raw = trim($_POST['house_image_basename'] ?? '');

        if ($house_work_id > 0 && $image_id > 0 && $new_basename_raw !== '') {
            $result = $conn->query(
                "SELECT i.filename
                 FROM house_work_images i
                 INNER JOIN house_work_items w ON i.house_work_id = w.id
                 WHERE i.id=$image_id AND i.house_work_id=$house_work_id AND w.house_id=$house_id
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $old_filename = $row['filename'];
                $old_ext = strtolower(pathinfo($old_filename, PATHINFO_EXTENSION));
                $basename = hds_house_work_sanitize_basename($new_basename_raw);
                $new_name = ($old_ext !== '') ? $basename . '.' . $old_ext : $basename;

                if ($basename === '') {
                    $_SESSION['house_photo_error'] = 'Please enter a valid file name.';
                } elseif ($new_name !== $old_filename) {
                    $dir = hds_house_work_upload_dir();
                    $old_path = $dir . $old_filename;
                    $new_path = $dir . $new_name;
                    if (file_exists($new_path)) {
                        $_SESSION['house_photo_error'] = 'A file with that name already exists.';
                    } elseif (file_exists($old_path) && rename($old_path, $new_path)) {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE house_work_images SET filename='$safe_name' WHERE id=$image_id AND house_work_id=$house_work_id");
                        $_SESSION['house_photo_success'] = 'Photo renamed successfully.';
                    } elseif (file_exists($old_path)) {
                        $_SESSION['house_photo_error'] = 'Could not rename the file on disk.';
                    } else {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE house_work_images SET filename='$safe_name' WHERE id=$image_id AND house_work_id=$house_work_id");
                        $_SESSION['house_photo_success'] = 'Photo name updated.';
                    }
                }
            }
        } else {
            $_SESSION['house_photo_error'] = 'Please enter a new file name.';
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=house_work&saved=1');
        exit;
    }

    // PERMANENT ITEMS - MAINTENANCE LOG
    $permanent_log_types = ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac', 'outdoor_work', 'breakers'];

    if (isset($_POST['add_permanent_log']) && !empty($_POST['perm_log_date'])) {
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        $log_date = mysqli_real_escape_string($conn, $_POST['perm_log_date']);
        $part_number = mysqli_real_escape_string($conn, trim($_POST['perm_log_part_number'] ?? ''));
        $log_notes = mysqli_real_escape_string($conn, trim($_POST['perm_log_notes'] ?? ''));
        $contractor = hds_permanent_log_parse_contractor_fields($_POST);
        $completed_by = $contractor['completed_by'];
        $price_sql = $contractor['contractor_price'] !== null ? $contractor['contractor_price'] : 'NULL';
        $method_sql = $contractor['payment_method'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor['payment_method']) . "'"
            : 'NULL';
        $ref_sql = $contractor['payment_reference'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor['payment_reference']) . "'"
            : 'NULL';
        if (!in_array($type, $permanent_log_types, true)) {
            $_SESSION['perm_log_error'] = 'Invalid section for maintenance log.';
            header('Location: house.php?id=' . $house_id . '&tab=permanent&saved=1');
            exit;
        }
        $insert_ok = $conn->query("INSERT INTO permanent_maintenance_log
                      (house_id, item_type, log_date, part_number, completed_by, contractor_price, payment_method, payment_reference, notes)
                      VALUES ($house_id, '$type', '$log_date', '$part_number', '$completed_by', $price_sql, $method_sql, $ref_sql, '$log_notes')");
        if (!$insert_ok) {
            $_SESSION['perm_log_error'] = 'Could not save log entry. ' . $conn->error;
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }
    if (isset($_POST['update_permanent_log'])) {
        $log_id = intval($_POST['permanent_log_id'] ?? 0);
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        $log_date = mysqli_real_escape_string($conn, $_POST['perm_log_date'] ?? '');
        $part_number = mysqli_real_escape_string($conn, trim($_POST['perm_log_part_number'] ?? ''));
        $log_notes = mysqli_real_escape_string($conn, trim($_POST['perm_log_notes'] ?? ''));
        $contractor = hds_permanent_log_parse_contractor_fields($_POST);
        $completed_by = $contractor['completed_by'];
        $price_sql = $contractor['contractor_price'] !== null ? $contractor['contractor_price'] : 'NULL';
        $method_sql = $contractor['payment_method'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor['payment_method']) . "'"
            : 'NULL';
        $ref_sql = $contractor['payment_reference'] !== null
            ? "'" . mysqli_real_escape_string($conn, $contractor['payment_reference']) . "'"
            : 'NULL';
        if ($log_id > 0 && $log_date !== '' && in_array($type, $permanent_log_types, true)) {
            $conn->query("UPDATE permanent_maintenance_log
                          SET log_date='$log_date', part_number='$part_number', completed_by='$completed_by',
                              contractor_price=$price_sql, payment_method=$method_sql, payment_reference=$ref_sql,
                              notes='$log_notes'
                          WHERE id=$log_id AND house_id=$house_id AND item_type='$type'");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }
    if (isset($_POST['delete_permanent_log'])) {
        $log_id = intval($_POST['permanent_log_id'] ?? 0);
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        if ($log_id > 0 && in_array($type, $permanent_log_types, true)) {
            $conn->query("DELETE FROM permanent_maintenance_log WHERE id=$log_id AND house_id=$house_id AND item_type='$type'");
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }


    if (isset($_POST['upload_perm_log_image'])) {
        $log_id = intval($_POST['permanent_log_id'] ?? 0);
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        $owned = $log_id > 0 && in_array($type, ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac', 'outdoor_work', 'breakers'], true)
            ? $conn->query("SELECT id FROM permanent_maintenance_log WHERE id=$log_id AND house_id=$house_id AND item_type='$type' LIMIT 1")
            : null;
        $upload_errors = [];
        $upload_count = 0;

        if (!$owned || $owned->num_rows === 0) {
            $upload_errors[] = 'Log entry not found.';
        } else {
            $dir_error = hds_perm_log_ensure_upload_dir();
            if ($dir_error !== null) {
                $upload_errors[] = $dir_error;
            } elseif (empty($_FILES['perm_log_images']['name']) || !is_array($_FILES['perm_log_images']['name'])) {
                $upload_errors[] = 'No photo file was selected.';
            } else {
                $target_dir = hds_perm_log_upload_dir();
                $allowed = hds_perm_log_allowed_extensions();
                $max = 10;
                foreach ($_FILES['perm_log_images']['tmp_name'] as $k => $tmp) {
                    if ($upload_count >= $max) {
                        break;
                    }
                    $original_name = basename((string)($_FILES['perm_log_images']['name'][$k] ?? ''));
                    $upload_err = (int)($_FILES['perm_log_images']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                    if ($upload_err !== UPLOAD_ERR_OK) {
                        if ($original_name !== '') {
                            $upload_errors[] = $original_name . ': upload failed (error code ' . $upload_err . ').';
                        }
                        continue;
                    }
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        $upload_errors[] = $original_name . ': file type not allowed (use JPG, PNG, GIF, or WebP).';
                        continue;
                    }
                    $final_name = time() . '_' . $log_id . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original_name);
                    $target = $target_dir . $final_name;
                    if (move_uploaded_file($tmp, $target)) {
                        $safe_name = mysqli_real_escape_string($conn, $final_name);
                        if ($conn->query("INSERT INTO permanent_maintenance_log_images (log_id, filename) VALUES ($log_id, '$safe_name')")) {
                            $upload_count++;
                        } else {
                            @unlink($target);
                            $upload_errors[] = $original_name . ': saved to disk but database insert failed.';
                        }
                    } else {
                        $upload_errors[] = $original_name . ': could not save file (check folder permissions).';
                    }
                }
            }
        }

        if ($upload_count > 0) {
            $_SESSION['perm_log_photo_success'] = $upload_count === 1
                ? '1 photo uploaded successfully.'
                : $upload_count . ' photos uploaded successfully.';
        }
        if (!empty($upload_errors)) {
            $_SESSION['perm_log_photo_error'] = implode(' ', $upload_errors);
        } elseif ($upload_count === 0 && empty($_SESSION['perm_log_photo_success'])) {
            $_SESSION['perm_log_photo_error'] = 'No photos were uploaded.';
        }

        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }
    if (isset($_POST['delete_perm_log_image'])) {
        $log_id = intval($_POST['permanent_log_id'] ?? 0);
        $image_id = intval($_POST['perm_log_image_id'] ?? 0);
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        if ($log_id > 0 && $image_id > 0 && in_array($type, ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac', 'outdoor_work', 'breakers'], true)) {
            $result = $conn->query(
                "SELECT i.filename
                 FROM permanent_maintenance_log_images i
                 INNER JOIN permanent_maintenance_log l ON i.log_id = l.id
                 WHERE i.id=$image_id AND i.log_id=$log_id AND l.house_id=$house_id AND l.item_type='$type'
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $path = hds_perm_log_upload_dir() . $row['filename'];
                if (is_file($path)) {
                    unlink($path);
                }
                $conn->query("DELETE FROM permanent_maintenance_log_images WHERE id=$image_id AND log_id=$log_id");
            }
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }
    if (isset($_POST['rename_perm_log_image'])) {
        $log_id = intval($_POST['permanent_log_id'] ?? 0);
        $image_id = intval($_POST['perm_log_image_id'] ?? 0);
        $type = preg_replace('/[^a-z_]/', '', $_POST['item_type'] ?? '');
        $new_basename_raw = trim($_POST['perm_log_image_basename'] ?? '');

        if ($log_id > 0 && $image_id > 0 && $new_basename_raw !== '' && in_array($type, ['furnace', 'water_heater', 'dishwasher', 'washer', 'dryer', 'ac', 'outdoor_work', 'breakers'], true)) {
            $result = $conn->query(
                "SELECT i.filename
                 FROM permanent_maintenance_log_images i
                 INNER JOIN permanent_maintenance_log l ON i.log_id = l.id
                 WHERE i.id=$image_id AND i.log_id=$log_id AND l.house_id=$house_id AND l.item_type='$type'
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $old_filename = $row['filename'];
                $old_ext = strtolower(pathinfo($old_filename, PATHINFO_EXTENSION));
                $basename = hds_perm_log_sanitize_basename($new_basename_raw);
                $new_name = ($old_ext !== '') ? $basename . '.' . $old_ext : $basename;

                if ($basename === '') {
                    $_SESSION['perm_log_photo_error'] = 'Please enter a valid file name.';
                } elseif ($new_name !== $old_filename) {
                    $dir = hds_perm_log_upload_dir();
                    $old_path = $dir . $old_filename;
                    $new_path = $dir . $new_name;
                    if (file_exists($new_path)) {
                        $_SESSION['perm_log_photo_error'] = 'A file with that name already exists.';
                    } elseif (file_exists($old_path) && rename($old_path, $new_path)) {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE permanent_maintenance_log_images SET filename='$safe_name' WHERE id=$image_id AND log_id=$log_id");
                        $_SESSION['perm_log_photo_success'] = 'Photo renamed successfully.';
                    } elseif (file_exists($old_path)) {
                        $_SESSION['perm_log_photo_error'] = 'Could not rename the file on disk.';
                    } else {
                        $safe_name = mysqli_real_escape_string($conn, $new_name);
                        $conn->query("UPDATE permanent_maintenance_log_images SET filename='$safe_name' WHERE id=$image_id AND log_id=$log_id");
                        $_SESSION['perm_log_photo_success'] = 'Photo name updated.';
                    }
                }
            }
        } else {
            $_SESSION['perm_log_photo_error'] = 'Please enter a new file name.';
        }
        header('Location: house.php?id=' . $house_id . '&tab=permanent&open_permanent=' . urlencode($type) . '&saved=1');
        exit;
    }

    // ELECTRIC METER - SAVE ACCOUNT INFO
    if (isset($_POST['save_meter_account'])) {
        $meter_number = mysqli_real_escape_string($conn, $_POST['meter_number'] ?? '');
        $company      = mysqli_real_escape_string($conn, $_POST['company'] ?? '');
        $phone        = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $existing = $conn->query("SELECT id FROM electric_meters WHERE house_id = $house_id LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $conn->query("UPDATE electric_meters SET meter_number='$meter_number', company='$company', phone='$phone' WHERE house_id=$house_id");
        } else {
            $conn->query("INSERT INTO electric_meters (house_id, meter_number, company, phone) VALUES ($house_id, '$meter_number', '$company', '$phone')");
        }
        house_redirect($house_id, 'utility');
    }

    // ELECTRIC METER - SAVE NEW BILL
    if (isset($_POST['save_meter_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                          VALUES ($house_id, 'electric', $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'utility');
    }

    // GENERATOR - SAVE INFO
    if (isset($_POST['save_generator'])) {
        $brand      = mysqli_real_escape_string($conn, $_POST['generator_brand'] ?? '');
        $model      = mysqli_real_escape_string($conn, $_POST['generator_model'] ?? '');
        $sn         = mysqli_real_escape_string($conn, $_POST['generator_sn'] ?? '');
        $efficiency = mysqli_real_escape_string($conn, $_POST['generator_efficiency'] ?? '');
        $kwh        = floatval($_POST['generator_kwh'] ?? 0);
        $fuel_type  = ($_POST['generator_fuel_type'] ?? 'LP') === 'NG' ? 'NG' : 'LP';

        $existing = $conn->query("SELECT id FROM generators WHERE house_id = $house_id LIMIT 1");
        if ($existing && $existing->num_rows > 0) {
            $conn->query("UPDATE generators SET brand='$brand', model='$model', sn='$sn', efficiency='$efficiency', kwh=$kwh, fuel_type='$fuel_type'
                          WHERE house_id=$house_id");
        } else {
            $conn->query("INSERT INTO generators (house_id, brand, model, sn, efficiency, kwh, fuel_type)
                          VALUES ($house_id, '$brand', '$model', '$sn', '$efficiency', $kwh, '$fuel_type')");
        }
        house_redirect($house_id, 'utility');
    }

    // WATER UTILITY - SAVE ACCOUNT INFO
    if (isset($_POST['save_water_account'])) {
        $account_number    = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
        $meter_number      = mysqli_real_escape_string($conn, $_POST['meter_number'] ?? '');
        $billing_frequency = in_array($_POST['billing_frequency'] ?? 'Monthly', ['Monthly', 'Quarterly', 'Annual'], true)
            ? $_POST['billing_frequency'] : 'Monthly';
        $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $conn->query("INSERT INTO water_utilities (house_id, account_number, meter_number, billing_frequency, phone)
                      VALUES ($house_id, '$account_number', '$meter_number', '$billing_frequency', '$phone')
                      ON DUPLICATE KEY UPDATE
                      account_number=VALUES(account_number),
                      meter_number=VALUES(meter_number),
                      billing_frequency=VALUES(billing_frequency),
                      phone=VALUES(phone)");
        house_redirect($house_id, 'utility');
    }

    // WATER UTILITY - SAVE NEW BILL
    if (isset($_POST['save_water_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                          VALUES ($house_id, 'water', $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'utility');
    }

    // UTILITY BILL - TOGGLE PAID STATUS
    if (isset($_POST['toggle_bill_paid'])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $is_paid = isset($_POST['is_paid']) ? 1 : 0;
        if ($bill_id > 0) {
            if (isset($_POST['payment_method'])) {
                $payment_method = $_POST['payment_method'] ?? '';
                $allowed_methods = ['debit', 'credit', 'check', ''];
                if (!in_array($payment_method, $allowed_methods, true)) {
                    $payment_method = '';
                }
                $payment_sql = $payment_method === ''
                    ? 'NULL'
                    : "'" . mysqli_real_escape_string($conn, $payment_method) . "'";
                $conn->query("UPDATE utility_bills SET is_paid = $is_paid, payment_method = $payment_sql WHERE id = $bill_id AND house_id = $house_id");
            } else {
                $conn->query("UPDATE utility_bills SET is_paid = $is_paid WHERE id = $bill_id AND house_id = $house_id");
            }
        }
        house_redirect($house_id, 'utility');
    }

    // UTILITY BILL - DELETE (receipt files removed first)
    if (isset($_POST['delete_bill'])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        if ($bill_id > 0) {
            foreach (['water_receipts', 'propane_receipts'] as $receipt_table) {
                $receipts = $conn->query("SELECT filename FROM $receipt_table WHERE bill_id = $bill_id");
                if ($receipts) {
                    while ($receipt = $receipts->fetch_assoc()) {
                        $path = 'uploads/receipts/' . $receipt['filename'];
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    }
                }
            }
            $conn->query("DELETE FROM utility_bills WHERE id = $bill_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'utility');
    }

    // BREAKER PANEL - ADD
    if (isset($_POST['add_breaker_panel'])) {
        $panel_name = mysqli_real_escape_string($conn, $_POST['new_panel_name'] ?? 'Main Panel');
        $panel_size = intval($_POST['new_panel_size'] ?? 24);
        if (!in_array($panel_size, [6, 12, 24, 28, 30], true)) {
            $panel_size = 24;
        }
        $conn->query("INSERT INTO electric_panels (house_id, name, size) VALUES ($house_id, '$panel_name', $panel_size)");
        house_redirect($house_id, 'permanent', (int)$conn->insert_id, 'open_panel');
    }

    // BREAKER PANEL - SAVE LABELS
    if (isset($_POST['save_breakers'])) {
        $panel_id = intval($_POST['panel_id'] ?? 0);
        $panel = $conn->query("SELECT size FROM electric_panels WHERE id = $panel_id AND house_id = $house_id LIMIT 1")->fetch_assoc();
        if ($panel) {
            $panel_size = intval($panel['size']);
            $max_rows   = (int)ceil($panel_size / 2);
            for ($row = 1; $row <= $max_rows; $row++) {
                $pairs = [
                    [1, mysqli_real_escape_string($conn, $_POST["left_room_$row"] ?? ''), intval($_POST["left_amp_$row"] ?? 0)],
                    [2, mysqli_real_escape_string($conn, $_POST["right_room_$row"] ?? ''), intval($_POST["right_amp_$row"] ?? 0)],
                ];
                foreach ($pairs as [$col, $room, $amp]) {
                    $existing = $conn->query("SELECT id FROM breakers WHERE panel_id=$panel_id AND column_num=$col AND row_num=$row LIMIT 1");
                    if ($existing && $existing->num_rows > 0) {
                        $conn->query("UPDATE breakers SET room='$room', amp=$amp WHERE panel_id=$panel_id AND column_num=$col AND row_num=$row");
                    } else {
                        $conn->query("INSERT INTO breakers (panel_id, column_num, row_num, room, amp) VALUES ($panel_id, $col, $row, '$room', $amp)");
                    }
                }
            }
        }
        house_redirect($house_id, 'permanent', $panel_id, 'open_panel');
    }

    // BREAKER PANEL - DELETE
    if (isset($_POST['delete_panel'])) {
        $panel_id = intval($_POST['panel_id'] ?? 0);
        $panel = $conn->query("SELECT id FROM electric_panels WHERE id = $panel_id AND house_id = $house_id LIMIT 1")->fetch_assoc();
        if ($panel) {
            $conn->query("DELETE FROM breakers WHERE panel_id = $panel_id");
            $conn->query("DELETE FROM electric_panels WHERE id = $panel_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'permanent');
    }

    // HOUSEHOLD ITEMS
    if (isset($_POST['add_household'])) {
        $type  = mysqli_real_escape_string($conn, $_POST['type'] ?? 'TV');
        $brand = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $model = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
        $sn    = mysqli_real_escape_string($conn, $_POST['sn'] ?? '');
        $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        $conn->query("INSERT INTO household_items (house_id, type, brand, model, sn, notes)
                      VALUES ($house_id, '$type', '$brand', '$model', '$sn', '$notes')");
        house_redirect($house_id, 'household');
    }

    if (isset($_POST['update_household'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $brand   = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $model   = mysqli_real_escape_string($conn, $_POST['model'] ?? '');
        $sn      = mysqli_real_escape_string($conn, $_POST['sn'] ?? '');
        $notes   = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
        $conn->query("UPDATE household_items SET brand='$brand', model='$model', sn='$sn', notes='$notes'
                      WHERE id=$item_id AND house_id=$house_id");
        house_redirect($house_id, 'household');
    }

    if (isset($_POST['delete_household'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $conn->query("DELETE FROM household_items WHERE id=$item_id AND house_id=$house_id");
        house_redirect($house_id, 'household');
    }

    // CONTRACTORS
    if (isset($_POST['add_contractor']) && !empty(trim($_POST['contractor_name'] ?? ''))) {
        $name  = mysqli_real_escape_string($conn, trim($_POST['contractor_name']));
        $trade = mysqli_real_escape_string($conn, trim($_POST['contractor_trade'] ?? ''));
        $phone = mysqli_real_escape_string($conn, trim($_POST['contractor_phone'] ?? ''));
        $city  = mysqli_real_escape_string($conn, trim($_POST['contractor_city'] ?? ''));
        $conn->query("INSERT INTO contractors (house_id, name, trade, phone, city)
                      VALUES ($house_id, '$name', '$trade', '$phone', '$city')");
        house_redirect($house_id, 'contractors');
    }
    if (isset($_POST['update_contractor']) && !empty(trim($_POST['contractor_name'] ?? ''))) {
        $contractor_id = intval($_POST['contractor_id'] ?? 0);
        $name  = mysqli_real_escape_string($conn, trim($_POST['contractor_name']));
        $trade = mysqli_real_escape_string($conn, trim($_POST['contractor_trade'] ?? ''));
        $phone = mysqli_real_escape_string($conn, trim($_POST['contractor_phone'] ?? ''));
        $city  = mysqli_real_escape_string($conn, trim($_POST['contractor_city'] ?? ''));
        if ($contractor_id > 0) {
            $conn->query("UPDATE contractors SET name='$name', trade='$trade', phone='$phone', city='$city'
                          WHERE id=$contractor_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'contractors');
    }
    if (isset($_POST['delete_contractor'])) {
        $contractor_id = intval($_POST['contractor_id'] ?? 0);
        if ($contractor_id > 0) {
            $conn->query("DELETE FROM contractors WHERE id=$contractor_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'contractors');
    }

    // HOME LAB — HARDWARE
    $homelab_device_types = array_keys(hds_homelab_device_types());
    $homelab_instance_types = array_keys(hds_homelab_instance_types());

    if (isset($_POST['add_homelab_hardware']) && !empty(trim($_POST['hw_name'] ?? ''))) {
        $device_type = in_array($_POST['hw_device_type'] ?? '', $homelab_device_types, true) ? $_POST['hw_device_type'] : 'server';
        $conn->query("INSERT INTO homelab_hardware
                      (house_id, name, device_type, make_model, cpu, ram, storage, ip_address, mac_address, location, role, serial_number, notes)
                      VALUES ($house_id,
                      '" . hds_homelab_esc($conn, $_POST['hw_name']) . "',
                      '" . hds_homelab_esc($conn, $device_type) . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_make_model'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_cpu'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_ram'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_storage'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_ip'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_mac'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_location'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_role'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_serial'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['hw_notes'] ?? '') . "')");
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=hardware');
        exit;
    }
    if (isset($_POST['update_homelab_hardware']) && !empty(trim($_POST['hw_name'] ?? ''))) {
        $hw_id = intval($_POST['hw_id'] ?? 0);
        $device_type = in_array($_POST['hw_device_type'] ?? '', $homelab_device_types, true) ? $_POST['hw_device_type'] : 'server';
        if ($hw_id > 0) {
            $conn->query("UPDATE homelab_hardware SET
                          name='" . hds_homelab_esc($conn, $_POST['hw_name']) . "',
                          device_type='" . hds_homelab_esc($conn, $device_type) . "',
                          make_model='" . hds_homelab_esc($conn, $_POST['hw_make_model'] ?? '') . "',
                          cpu='" . hds_homelab_esc($conn, $_POST['hw_cpu'] ?? '') . "',
                          ram='" . hds_homelab_esc($conn, $_POST['hw_ram'] ?? '') . "',
                          storage='" . hds_homelab_esc($conn, $_POST['hw_storage'] ?? '') . "',
                          ip_address='" . hds_homelab_esc($conn, $_POST['hw_ip'] ?? '') . "',
                          mac_address='" . hds_homelab_esc($conn, $_POST['hw_mac'] ?? '') . "',
                          location='" . hds_homelab_esc($conn, $_POST['hw_location'] ?? '') . "',
                          role='" . hds_homelab_esc($conn, $_POST['hw_role'] ?? '') . "',
                          serial_number='" . hds_homelab_esc($conn, $_POST['hw_serial'] ?? '') . "',
                          notes='" . hds_homelab_esc($conn, $_POST['hw_notes'] ?? '') . "'
                          WHERE id=$hw_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=hardware');
        exit;
    }
    if (isset($_POST['delete_homelab_hardware'])) {
        $hw_id = intval($_POST['hw_id'] ?? 0);
        if ($hw_id > 0) {
            $conn->query("DELETE FROM homelab_hardware WHERE id=$hw_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=hardware');
        exit;
    }

    // HOME LAB — LXC / VMs
    if (isset($_POST['add_homelab_instance']) && !empty(trim($_POST['inst_name'] ?? ''))) {
        $instance_type = in_array($_POST['inst_type'] ?? '', $homelab_instance_types, true) ? $_POST['inst_type'] : 'lxc';
        $hardware_id = intval($_POST['inst_hardware_id'] ?? 0);
        if ($hardware_id > 0) {
            $hw_check = $conn->query("SELECT id FROM homelab_hardware WHERE id=$hardware_id AND house_id=$house_id LIMIT 1");
            if (!$hw_check || $hw_check->num_rows === 0) {
                $hardware_id = 0;
            }
        }
        $hw_sql = $hardware_id > 0 ? $hardware_id : 'NULL';
        $conn->query("INSERT INTO homelab_instances
                      (house_id, name, instance_type, hardware_id, os, ip_address, cpu_cores, ram, disk, network, ports, purpose, backup_notes, notes)
                      VALUES ($house_id,
                      '" . hds_homelab_esc($conn, $_POST['inst_name']) . "',
                      '" . hds_homelab_esc($conn, $instance_type) . "',
                      $hw_sql,
                      '" . hds_homelab_esc($conn, $_POST['inst_os'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_ip'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_cpu'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_ram'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_disk'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_network'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_ports'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_purpose'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_backup'] ?? '') . "',
                      '" . hds_homelab_esc($conn, $_POST['inst_notes'] ?? '') . "')");
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=instances');
        exit;
    }
    if (isset($_POST['update_homelab_instance']) && !empty(trim($_POST['inst_name'] ?? ''))) {
        $inst_id = intval($_POST['inst_id'] ?? 0);
        $instance_type = in_array($_POST['inst_type'] ?? '', $homelab_instance_types, true) ? $_POST['inst_type'] : 'lxc';
        $hardware_id = intval($_POST['inst_hardware_id'] ?? 0);
        if ($hardware_id > 0) {
            $hw_check = $conn->query("SELECT id FROM homelab_hardware WHERE id=$hardware_id AND house_id=$house_id LIMIT 1");
            if (!$hw_check || $hw_check->num_rows === 0) {
                $hardware_id = 0;
            }
        }
        $hw_sql = $hardware_id > 0 ? $hardware_id : 'NULL';
        if ($inst_id > 0) {
            $conn->query("UPDATE homelab_instances SET
                          name='" . hds_homelab_esc($conn, $_POST['inst_name']) . "',
                          instance_type='" . hds_homelab_esc($conn, $instance_type) . "',
                          hardware_id=$hw_sql,
                          os='" . hds_homelab_esc($conn, $_POST['inst_os'] ?? '') . "',
                          ip_address='" . hds_homelab_esc($conn, $_POST['inst_ip'] ?? '') . "',
                          cpu_cores='" . hds_homelab_esc($conn, $_POST['inst_cpu'] ?? '') . "',
                          ram='" . hds_homelab_esc($conn, $_POST['inst_ram'] ?? '') . "',
                          disk='" . hds_homelab_esc($conn, $_POST['inst_disk'] ?? '') . "',
                          network='" . hds_homelab_esc($conn, $_POST['inst_network'] ?? '') . "',
                          ports='" . hds_homelab_esc($conn, $_POST['inst_ports'] ?? '') . "',
                          purpose='" . hds_homelab_esc($conn, $_POST['inst_purpose'] ?? '') . "',
                          backup_notes='" . hds_homelab_esc($conn, $_POST['inst_backup'] ?? '') . "',
                          notes='" . hds_homelab_esc($conn, $_POST['inst_notes'] ?? '') . "'
                          WHERE id=$inst_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=instances');
        exit;
    }
    if (isset($_POST['delete_homelab_instance'])) {
        $inst_id = intval($_POST['inst_id'] ?? 0);
        if ($inst_id > 0) {
            $conn->query("DELETE FROM homelab_instances WHERE id=$inst_id AND house_id=$house_id");
        }
        header('Location: house.php?id=' . $house_id . '&tab=homelab&open_homelab=instances');
        exit;
    }

    // TOOLS
    if (isset($_POST['add_tool']) && !empty(trim($_POST['tool_name'] ?? ''))) {
        $tool_name        = mysqli_real_escape_string($conn, trim($_POST['tool_name']));
        $tool_description = mysqli_real_escape_string($conn, trim($_POST['tool_description'] ?? ''));
        $power_type       = $_POST['power_type'] ?? 'manual';
        $tool_house_id    = intval($_POST['tool_house_id'] ?? $house_id);
        $allowed_types    = ['battery', 'ac', 'pneumatic', 'manual', 'na'];
        if (!in_array($power_type, $allowed_types, true)) {
            $power_type = 'manual';
        }
        $house_check = $conn->query("SELECT id FROM houses WHERE id = $tool_house_id LIMIT 1");
        if ($house_check && $house_check->num_rows > 0) {
            $conn->query("INSERT INTO tools (house_id, name, power_type, description)
                          VALUES ($tool_house_id, '$tool_name', '$power_type', '$tool_description')");
            if ($tool_house_id === $house_id) {
                house_redirect($house_id, 'tools', (int)$conn->insert_id, 'open_tool');
            }
        }
        house_redirect($house_id, 'tools');
    }

    if (isset($_POST['update_tool'])) {
        $tool_id       = intval($_POST['tool_id'] ?? 0);
        $tool_name        = mysqli_real_escape_string($conn, trim($_POST['tool_name'] ?? ''));
        $tool_description = mysqli_real_escape_string($conn, trim($_POST['tool_description'] ?? ''));
        $power_type       = $_POST['power_type'] ?? 'manual';
        $tool_house_id    = intval($_POST['tool_house_id'] ?? $house_id);
        $allowed_types    = ['battery', 'ac', 'pneumatic', 'manual', 'na'];
        if (!in_array($power_type, $allowed_types, true)) {
            $power_type = 'manual';
        }
        if ($tool_id > 0 && $tool_name !== '') {
            $house_check = $conn->query("SELECT id FROM houses WHERE id = $tool_house_id LIMIT 1");
            if ($house_check && $house_check->num_rows > 0) {
                $conn->query("UPDATE tools SET name='$tool_name', power_type='$power_type', house_id=$tool_house_id, description='$tool_description'
                              WHERE id=$tool_id");
            }
        }
        house_redirect($house_id, 'tools', $tool_id, 'open_tool');
    }

    if (isset($_POST['delete_tool'])) {
        $tool_id = intval($_POST['tool_id'] ?? 0);
        if ($tool_id > 0) {
            $conn->query("DELETE FROM tools WHERE id=$tool_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'tools');
    }

    // MAINTENANCE EQUIPMENT
    $maint_categories = ['atv', 'boat', 'lawnmower', 'other'];
    if (isset($_POST['add_equipment']) && !empty(trim($_POST['equipment_name'] ?? ''))) {
        $eq_name     = mysqli_real_escape_string($conn, trim($_POST['equipment_name']));
        $eq_category = in_array($_POST['equipment_category'] ?? 'other', $maint_categories, true) ? $_POST['equipment_category'] : 'other';
        $eq_notes    = mysqli_real_escape_string($conn, trim($_POST['equipment_notes'] ?? ''));
        $conn->query("INSERT INTO maintenance_equipment (house_id, name, category, notes)
                      VALUES ($house_id, '$eq_name', '$eq_category', '$eq_notes')");
        house_redirect($house_id, 'maintenance', (int)$conn->insert_id);
    }
    if (isset($_POST['update_equipment'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $eq_name      = mysqli_real_escape_string($conn, trim($_POST['equipment_name'] ?? ''));
        $eq_category  = in_array($_POST['equipment_category'] ?? 'other', $maint_categories, true) ? $_POST['equipment_category'] : 'other';
        $eq_notes     = mysqli_real_escape_string($conn, trim($_POST['equipment_notes'] ?? ''));
        if ($equipment_id > 0 && $eq_name !== '') {
            $conn->query("UPDATE maintenance_equipment SET name='$eq_name', category='$eq_category', notes='$eq_notes'
                          WHERE id=$equipment_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_equipment'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($equipment_id > 0) {
            $conn->query("DELETE FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance');
    }
    if (isset($_POST['add_fluid']) && !empty(trim($_POST['fluid_name'] ?? ''))) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $fluid_name   = mysqli_real_escape_string($conn, trim($_POST['fluid_name']));
        $fluid_spec   = mysqli_real_escape_string($conn, trim($_POST['fluid_spec'] ?? ''));
        $fluid_cap    = mysqli_real_escape_string($conn, trim($_POST['fluid_capacity'] ?? ''));
        $fluid_notes  = mysqli_real_escape_string($conn, trim($_POST['fluid_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_fluids (equipment_id, fluid_name, specification, capacity, notes)
                          VALUES ($equipment_id, '$fluid_name', '$fluid_spec', '$fluid_cap', '$fluid_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_fluid'])) {
        $fluid_id     = intval($_POST['fluid_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $fluid_name   = mysqli_real_escape_string($conn, trim($_POST['fluid_name'] ?? ''));
        $fluid_spec   = mysqli_real_escape_string($conn, trim($_POST['fluid_spec'] ?? ''));
        $fluid_cap    = mysqli_real_escape_string($conn, trim($_POST['fluid_capacity'] ?? ''));
        $fluid_notes  = mysqli_real_escape_string($conn, trim($_POST['fluid_notes'] ?? ''));
        if ($fluid_id > 0 && $fluid_name !== '') {
            $conn->query("UPDATE maintenance_fluids f
                          INNER JOIN maintenance_equipment e ON f.equipment_id = e.id
                          SET f.fluid_name='$fluid_name', f.specification='$fluid_spec', f.capacity='$fluid_cap', f.notes='$fluid_notes'
                          WHERE f.id=$fluid_id AND f.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_fluid'])) {
        $fluid_id     = intval($_POST['fluid_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($fluid_id > 0) {
            $conn->query("DELETE f FROM maintenance_fluids f
                          INNER JOIN maintenance_equipment e ON f.equipment_id = e.id
                          WHERE f.id=$fluid_id AND f.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['add_part']) && !empty(trim($_POST['part_name'] ?? ''))) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $part_name    = mysqli_real_escape_string($conn, trim($_POST['part_name']));
        $part_number  = mysqli_real_escape_string($conn, trim($_POST['part_number'] ?? ''));
        $part_notes   = mysqli_real_escape_string($conn, trim($_POST['part_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_parts (equipment_id, part_name, part_number, notes)
                          VALUES ($equipment_id, '$part_name', '$part_number', '$part_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_part'])) {
        $part_id      = intval($_POST['part_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $part_name    = mysqli_real_escape_string($conn, trim($_POST['part_name'] ?? ''));
        $part_number  = mysqli_real_escape_string($conn, trim($_POST['part_number'] ?? ''));
        $part_notes   = mysqli_real_escape_string($conn, trim($_POST['part_notes'] ?? ''));
        if ($part_id > 0 && $part_name !== '') {
            $conn->query("UPDATE maintenance_parts p
                          INNER JOIN maintenance_equipment e ON p.equipment_id = e.id
                          SET p.part_name='$part_name', p.part_number='$part_number', p.notes='$part_notes'
                          WHERE p.id=$part_id AND p.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_part'])) {
        $part_id      = intval($_POST['part_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($part_id > 0) {
            $conn->query("DELETE p FROM maintenance_parts p
                          INNER JOIN maintenance_equipment e ON p.equipment_id = e.id
                          WHERE p.id=$part_id AND p.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['add_log']) && !empty(trim($_POST['log_description'] ?? '')) && !empty($_POST['log_date'])) {
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $log_date     = mysqli_real_escape_string($conn, $_POST['log_date']);
        $log_desc     = mysqli_real_escape_string($conn, trim($_POST['log_description']));
        $log_hours    = mysqli_real_escape_string($conn, trim($_POST['log_hours'] ?? ''));
        $log_notes    = mysqli_real_escape_string($conn, trim($_POST['log_notes'] ?? ''));
        $owned = $conn->query("SELECT id FROM maintenance_equipment WHERE id=$equipment_id AND house_id=$house_id LIMIT 1");
        if ($owned && $owned->num_rows > 0) {
            $conn->query("INSERT INTO maintenance_log (equipment_id, log_date, description, hours_mileage, notes)
                          VALUES ($equipment_id, '$log_date', '$log_desc', '$log_hours', '$log_notes')");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['update_log'])) {
        $log_id       = intval($_POST['log_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        $log_date     = mysqli_real_escape_string($conn, $_POST['log_date'] ?? '');
        $log_desc     = mysqli_real_escape_string($conn, trim($_POST['log_description'] ?? ''));
        $log_hours    = mysqli_real_escape_string($conn, trim($_POST['log_hours'] ?? ''));
        $log_notes    = mysqli_real_escape_string($conn, trim($_POST['log_notes'] ?? ''));
        if ($log_id > 0 && $log_date !== '' && $log_desc !== '') {
            $conn->query("UPDATE maintenance_log l
                          INNER JOIN maintenance_equipment e ON l.equipment_id = e.id
                          SET l.log_date='$log_date', l.description='$log_desc', l.hours_mileage='$log_hours', l.notes='$log_notes'
                          WHERE l.id=$log_id AND l.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }
    if (isset($_POST['delete_log'])) {
        $log_id       = intval($_POST['log_id'] ?? 0);
        $equipment_id = intval($_POST['equipment_id'] ?? 0);
        if ($log_id > 0) {
            $conn->query("DELETE l FROM maintenance_log l
                          INNER JOIN maintenance_equipment e ON l.equipment_id = e.id
                          WHERE l.id=$log_id AND l.equipment_id=$equipment_id AND e.house_id=$house_id");
        }
        house_redirect($house_id, 'maintenance', $equipment_id);
    }

    // WIFI NETWORKS (requires unlocked session)
    if (!empty($_SESSION['wifi_unlocked'])) {
        if (isset($_POST['add_wifi']) && !empty(trim($_POST['network_name'] ?? ''))) {
            $network_name  = mysqli_real_escape_string($conn, trim($_POST['network_name']));
            $wifi_password = mysqli_real_escape_string($conn, $_POST['wifi_password'] ?? '');
            $wifi_notes    = mysqli_real_escape_string($conn, trim($_POST['wifi_notes'] ?? ''));
            $conn->query("INSERT INTO wifi_networks (house_id, network_name, password, notes)
                          VALUES ($house_id, '$network_name', '$wifi_password', '$wifi_notes')");
            house_redirect($house_id, 'wifi');
        }

        if (isset($_POST['update_wifi'])) {
            $wifi_id       = intval($_POST['wifi_id'] ?? 0);
            $network_name  = mysqli_real_escape_string($conn, trim($_POST['network_name'] ?? ''));
            $wifi_password = mysqli_real_escape_string($conn, $_POST['wifi_password'] ?? '');
            $wifi_notes    = mysqli_real_escape_string($conn, trim($_POST['wifi_notes'] ?? ''));
            if ($wifi_id > 0 && $network_name !== '') {
                $conn->query("UPDATE wifi_networks SET network_name='$network_name', password='$wifi_password', notes='$wifi_notes'
                              WHERE id=$wifi_id AND house_id=$house_id");
            }
            house_redirect($house_id, 'wifi');
        }

        if (isset($_POST['delete_wifi'])) {
            $wifi_id = intval($_POST['wifi_id'] ?? 0);
            if ($wifi_id > 0) {
                $conn->query("DELETE FROM wifi_networks WHERE id=$wifi_id AND house_id=$house_id");
            }
            house_redirect($house_id, 'wifi');
        }
    }

    // PROPERTY TAX BILLS
    if (isset($_POST['save_tax_bill'])) {
        if (!empty($_POST['amount_owed']) && !empty($_POST['due_date'])) {
            $amount_owed = floatval($_POST['amount_owed']);
            $due_date    = mysqli_real_escape_string($conn, $_POST['due_date']);
            $conn->query("INSERT INTO property_taxes (house_id, amount_owed, due_date, is_paid)
                          VALUES ($house_id, $amount_owed, '$due_date', 0)");
        }
        house_redirect($house_id, 'map');
    }

    if (isset($_POST['toggle_tax_paid'])) {
        $tax_id       = intval($_POST['tax_id'] ?? 0);
        $is_paid      = isset($_POST['is_paid']) ? 1 : 0;
        $check_number = mysqli_real_escape_string($conn, trim($_POST['check_number'] ?? ''));
        if ($tax_id > 0) {
            $conn->query("UPDATE property_taxes SET is_paid = $is_paid, check_number = '$check_number' WHERE id = $tax_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'map');
    }

    if (isset($_POST['delete_tax_bill'])) {
        $tax_id = intval($_POST['tax_id'] ?? 0);
        if ($tax_id > 0) {
            $conn->query("DELETE FROM property_taxes WHERE id = $tax_id AND house_id = $house_id");
        }
        house_redirect($house_id, 'map');
    }

    // DESIGNS UPLOAD
    if (isset($_POST['upload_designs']) && !empty($_FILES['designs']['name'][0])) {
        include __DIR__ . '/tabs/designs-upload.php';
        house_redirect($house_id, 'designs');
    }

    // DESIGNS DELETE
    if (isset($_POST['delete_design'])) {
        include __DIR__ . '/tabs/designs-delete.php';
        house_redirect($house_id, 'designs');
    }

    // MEDIA/PHOTOS UPLOAD
    if (isset($_POST['upload_photo'])) {
        include __DIR__ . '/tabs/media-upload.php';
        $open_media = preg_replace('/[^a-z0-9\-]/', '', $_POST['open_media_section'] ?? '');
        if ($open_media === '' && ($_POST['section'] ?? '') === 'Walkthrough') {
            $open_media = 'walkthrough';
        }
        house_redirect($house_id, 'media', $open_media, 'open_media');
    }

    // MEDIA/PHOTOS SYNC — register on-disk files missing from database
    if (isset($_POST['sync_media_files'])) {
        include __DIR__ . '/tabs/media-sync.php';
        house_redirect($house_id, 'media');
    }

    // MEDIA/PHOTOS DELETE
    if (isset($_POST['delete_photo'])) {
        $photo_id = intval($_POST['photo_id'] ?? 0);
        $house_id_post = intval($_POST['house_id'] ?? 0);

        if ($photo_id > 0 && $house_id_post == $house_id) {
            $stmt = $conn->prepare("SELECT filename FROM photos WHERE id = ? AND house_id = ?");
            $stmt->bind_param("ii", $photo_id, $house_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $filename = $row['filename'];
                $path = "uploads/photos/" . $filename;

                if (file_exists($path)) {
                    unlink($path);
                }

                $delete_stmt = $conn->prepare("DELETE FROM photos WHERE id = ? AND house_id = ?");
                $delete_stmt->bind_param("ii", $photo_id, $house_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            $stmt->close();
        }

        house_redirect($house_id, 'media');
    }

    // MEDIA/PHOTOS RENAME
    if (isset($_POST['rename_photo'])) {
        $photo_id = intval($_POST['photo_id'] ?? 0);
        $house_id_post = intval($_POST['house_id'] ?? 0);
        $new_basename_raw = trim($_POST['photo_basename'] ?? '');

        if ($photo_id > 0 && $house_id_post === $house_id && $new_basename_raw !== '') {
            $stmt = $conn->prepare("SELECT filename FROM photos WHERE id = ? AND house_id = ?");
            $stmt->bind_param("ii", $photo_id, $house_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $old_filename = $row['filename'];
                $old_ext = strtolower(pathinfo($old_filename, PATHINFO_EXTENSION));
                $basename = basename($new_basename_raw);
                $basename = preg_replace('/[^a-zA-Z0-9._\- ()]/', '_', $basename);
                $basename = trim($basename, '. ');
                if (str_contains($basename, '.')) {
                    $basename = pathinfo($basename, PATHINFO_FILENAME);
                    $basename = trim($basename, '. ');
                }
                $new_name = ($old_ext !== '') ? $basename . '.' . $old_ext : $basename;

                if ($basename !== '') {
                    $old_path = "uploads/photos/" . $old_filename;
                    $new_path = "uploads/photos/" . $new_name;

                    if ($new_name !== $old_filename) {
                        if (file_exists($new_path)) {
                            $_SESSION['media_error'] = 'A file with that name already exists.';
                        } elseif (file_exists($old_path) && rename($old_path, $new_path)) {
                            $safe_name = mysqli_real_escape_string($conn, $new_name);
                            $conn->query("UPDATE photos SET filename='$safe_name' WHERE id=$photo_id AND house_id=$house_id");
                        } elseif (file_exists($old_path)) {
                            $_SESSION['media_error'] = 'Could not rename the file on disk.';
                        } else {
                            $safe_name = mysqli_real_escape_string($conn, $new_name);
                            $conn->query("UPDATE photos SET filename='$safe_name' WHERE id=$photo_id AND house_id=$house_id");
                        }
                    }
                } else {
                    $_SESSION['media_error'] = 'Please enter a valid file name.';
                }
            }
            $stmt->close();
        }

        $open_media = preg_replace('/[^a-z0-9\-]/', '', $_POST['open_media_section'] ?? '');
        house_redirect($house_id, 'media', $open_media, 'open_media');
    }

    // USER MANUALS UPLOAD
    if (isset($_POST['upload_manuals']) && !empty($_FILES['manuals']['name'][0])) {
        $target_dir = "uploads/manuals/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 10;
        $allowed = ['pdf', 'doc', 'docx'];

        foreach ($_FILES['manuals']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['manuals']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['manuals']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO user_manuals (house_id, filename, upload_date) VALUES ($house_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }
        house_redirect($house_id, 'manuals');
    }

    // USER MANUALS DELETE
    if (isset($_POST['delete_manual'])) {
        $manual_id = intval($_POST['manual_id']);

        $stmt = $conn->prepare("SELECT filename FROM user_manuals WHERE id = ? AND house_id = ?");
        $stmt->bind_param("ii", $manual_id, $house_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $filename = $row['filename'];
            $path = "uploads/manuals/" . $filename;

            if (file_exists($path)) {
                unlink($path);
            }

            $delete_stmt = $conn->prepare("DELETE FROM user_manuals WHERE id = ?");
            $delete_stmt->bind_param("i", $manual_id);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        $stmt->close();

        house_redirect($house_id, 'manuals');
    }

    // MAP UPDATE
    if (isset($_POST['update_map'])) {
        $new_address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        $new_lat = floatval($_POST['latitude'] ?? 0);
        $new_lng = floatval($_POST['longitude'] ?? 0);
        $new_tax = mysqli_real_escape_string($conn, $_POST['tax_number'] ?? '');
        $new_zoom = intval($_POST['map_zoom'] ?? 17);
        $new_embed_src = mysqli_real_escape_string($conn, $_POST['google_embed_src'] ?? '');

        $sql = "UPDATE houses SET 
                address = '$new_address',
                latitude = $new_lat,
                longitude = $new_lng,
                tax_number = '$new_tax',
                map_zoom = $new_zoom,
                google_embed_src = '$new_embed_src'
                WHERE id = $house_id";
        $conn->query($sql);

        house_redirect($house_id, 'map');
    }

    // WATER BILL RECEIPT UPLOAD
    if (isset($_POST['upload_receipt']) && !empty($_FILES['receipts']['name'][0])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $target_dir = "uploads/receipts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 5;
        $allowed = ['pdf'];

        foreach ($_FILES['receipts']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['receipts']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['receipts']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO water_receipts (bill_id, filename, upload_date) VALUES ($bill_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }

        house_redirect($house_id, 'utility');
    }

    // PROPANE ACCOUNT SAVE
    if (isset($_POST['save_propane_account'])) {
        $gallons   = floatval($_POST['gallons'] ?? 0);
        $provider  = mysqli_real_escape_string($conn, $_POST['provider'] ?? '');
        $tank_sn   = mysqli_real_escape_string($conn, $_POST['tank_sn'] ?? '');
        $phone     = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

        $sql = "INSERT INTO propane_utilities (house_id, gallons, provider, tank_sn, phone)
                VALUES ($house_id, $gallons, '$provider', '$tank_sn', '$phone')
                ON DUPLICATE KEY UPDATE
                gallons = $gallons, provider = '$provider', tank_sn = '$tank_sn', phone = '$phone'";
        $conn->query($sql);

        house_redirect($house_id, 'utility');
    }

    // PROPANE BILL SAVE
    if (isset($_POST['save_propane_bill'])) {
        $amount_owed = floatval($_POST['amount_owed'] ?? 0);
        $due_date    = mysqli_real_escape_string($conn, $_POST['due_date'] ?? '');

        $sql = "INSERT INTO utility_bills (house_id, utility_type, amount_owed, due_date, is_paid)
                VALUES ($house_id, 'propane', $amount_owed, '$due_date', 0)";
        $conn->query($sql);

        house_redirect($house_id, 'utility');
    }

    // PROPANE BILL RECEIPT UPLOAD
    if (isset($_POST['upload_propane_receipt']) && !empty($_FILES['receipts']['name'][0])) {
        $bill_id = intval($_POST['bill_id'] ?? 0);
        $target_dir = "uploads/receipts/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0775, true);

        $count = 0;
        $max = 5;
        $allowed = ['pdf'];

        foreach ($_FILES['receipts']['tmp_name'] as $k => $tmp) {
            if ($count >= $max) break;
            if ($_FILES['receipts']['error'][$k] !== UPLOAD_ERR_OK) continue;

            $original_name = basename($_FILES['receipts']['name'][$k]);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $name_without_ext = pathinfo($original_name, PATHINFO_FILENAME);

            if (in_array($ext, $allowed)) {
                $new_name = $name_without_ext . '_' . time() . '.' . $ext;
                $target = $target_dir . $new_name;

                if (move_uploaded_file($tmp, $target)) {
                    $sql = "INSERT INTO propane_receipts (bill_id, filename, upload_date) VALUES ($bill_id, '$new_name', NOW())";
                    $conn->query($sql);
                    $count++;
                }
            }
        }

        house_redirect($house_id, 'utility');
    }

    // PROJECT LIST - ADD NEW PROJECT
    if (isset($_POST['add_project'])) {
        $project_name = mysqli_real_escape_string($conn, $_POST['project_name'] ?? '');
        $date_added = date('Y-m-d');

        if (!empty($project_name)) {
            $sql = "INSERT INTO projects (house_id, name, date_added, completed) VALUES ($house_id, '$project_name', '$date_added', 0)";
            $conn->query($sql);
        }

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - MARK COMPLETED
    if (isset($_POST['complete_project'])) {
        $project_id = intval($_POST['project_id']);

        $sql = "UPDATE projects SET completed = 1, date_completed = NOW() WHERE id = $project_id AND house_id = $house_id";
        $conn->query($sql);

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - ADD MATERIAL TO PROJECT
    if (isset($_POST['add_material'])) {
        $project_id = intval($_POST['project_id']);
        $material_name = mysqli_real_escape_string($conn, $_POST['material_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $url = mysqli_real_escape_string($conn, $_POST['url'] ?? '');

        $sql = "INSERT INTO project_materials (project_id, material_name, price, quantity, url) 
                VALUES ($project_id, '$material_name', $price, $quantity, '$url')";
        $conn->query($sql);

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - DELETE PROJECT
    if (isset($_POST['delete_project'])) {
        $project_id = intval($_POST['project_id']);

        $conn->query("DELETE FROM project_materials WHERE project_id = $project_id");
        $conn->query("DELETE FROM projects WHERE id = $project_id AND house_id = $house_id");

        house_redirect($house_id, 'projects');
    }

    // PROJECT LIST - DELETE INDIVIDUAL MATERIAL
    if (isset($_POST['delete_material'])) {
        $material_id = intval($_POST['material_id']);
        $project_id = intval($_POST['project_id']);

        $conn->query("DELETE FROM project_materials WHERE id = $material_id AND project_id = $project_id");

        house_redirect($house_id, 'projects');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $house_name; ?> - Home Documentation System</title>
    <link rel="stylesheet" href="styles.css?v=20260630c">
    <script src="scripts.js?v=20260630c"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="container">

    <header id="pageHeader">
        <div class="header-bar">
            <div class="logo-container">
                <img src="logo.png" alt="Home Documentation System" style="max-width:180px; height:auto;">
                <span class="logo-text">Home Documentation System</span>
            </div>
            <div class="menu-toggle" onclick="toggleMenu()" aria-label="Open menu" role="button" tabindex="0"><i class="fas fa-bars"></i></div>
        </div>
        <div class="header-footer">
            <a href="index.php" class="back-link">← Back to Houses</a>
        </div>
    </header>

    <div class="hds-layout">
        <?php hds_render_sidebar_shell($active_tab, $hds_ui_settings); ?>

        <main class="hds-main">
            <h1 class="hds-page-title"><?php echo $house_name; ?></h1>

            <!-- Tab contents -->
    <?php foreach (hds_ui_registry()['tabs'] as $tab_key => $tab_meta): ?>
        <?php if (!hds_ui_tab_enabled($tab_key, $hds_ui_settings)) continue; ?>
        <div id="<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" class="tab" style="display: <?php echo $active_tab === $tab_key ? 'block' : 'none'; ?>;">
            <?php
            $tab_file = __DIR__ . '/tabs/' . $tab_key . '.php';
            if (file_exists($tab_file)) {
                include $tab_file;
            } else {
                echo "<p class='empty-note'>Tab file is missing on the server: tabs/" . htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8') . ".php</p>";
            }
            ?>
        </div>
    <?php endforeach; ?>

    <div id="admin" class="tab" style="display: <?php echo $active_tab === 'admin' ? 'block' : 'none'; ?>;">
        <?php include __DIR__ . '/tabs/admin.php'; ?>
    </div>

        </main>
    </div>

</div>

<?php include __DIR__ . '/includes/site-footer.php'; ?>

<!-- Close connection at the very end -->
<?php $conn->close(); ?>
<script>window.HDS_HOUSE_ID = <?php echo (int)$house_id; ?>;</script>

</body>
</html>
