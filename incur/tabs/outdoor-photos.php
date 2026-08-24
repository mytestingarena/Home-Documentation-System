<?php
// tabs/outdoor-photos.php — Trail camera and fishing photo timeline

require_once __DIR__ . '/../includes/outdoor-photos.php';

global $conn, $house_id, $hds_ui_settings;

$albums = hds_outdoor_photos_albums();
$table_ok = hds_outdoor_photos_ensure_table($conn);
$dir_error = hds_outdoor_photos_ensure_dirs();

$enabled_albums = [];
foreach ($albums as $key => $meta) {
    if (hds_ui_section_enabled($meta['section'], $hds_ui_settings)) {
        $enabled_albums[$key] = $meta;
    }
}

$requested_album = (string) ($_GET['album'] ?? 'trail_camera');
if (!isset($enabled_albums[$requested_album])) {
    $requested_album = (string) (array_key_first($enabled_albums) ?? 'trail_camera');
}

$album_counts = $table_ok ? hds_outdoor_photos_album_counts($conn, $house_id) : ['trail_camera' => 0, 'fishing' => 0];
$year_counts = ($table_ok && isset($enabled_albums[$requested_album]))
    ? hds_outdoor_photos_year_counts($conn, $house_id, $requested_album)
    : [];

$requested_year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
if ($requested_year > 0 && !isset($year_counts[$requested_year])) {
    $requested_year = 0;
}

$photos = ($table_ok && isset($enabled_albums[$requested_album]))
    ? hds_outdoor_photos_fetch($conn, $house_id, $requested_album, $requested_year > 0 ? $requested_year : null)
    : [];
$timeline = hds_outdoor_photos_group_timeline($photos);
$album_meta = $enabled_albums[$requested_album] ?? ['label' => 'Outdoor Photos', 'icon' => 'fa-images', 'blurb' => ''];
?>

<h2>Outdoor Photos</h2>
<p class="outdoor-photos-lead">Trail camera and fishing pictures are sorted by when they were taken. The date comes from each photo’s metadata (EXIF). If a file has no date, the file name is tried next, then the upload time.</p>

<?php if (!empty($_SESSION['outdoor_photos_error'])): ?>
    <p class="media-error"><?php echo htmlspecialchars($_SESSION['outdoor_photos_error'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php unset($_SESSION['outdoor_photos_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['outdoor_photos_warning'])): ?>
    <p class="media-warning"><?php echo htmlspecialchars($_SESSION['outdoor_photos_warning'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php unset($_SESSION['outdoor_photos_warning']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['outdoor_photos_success'])): ?>
    <p class="media-success"><?php echo htmlspecialchars($_SESSION['outdoor_photos_success'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php unset($_SESSION['outdoor_photos_success']); ?>
<?php endif; ?>

<?php if (!$table_ok): ?>
    <p class="media-error">The outdoor photos table could not be created. Apply the database migration, then reload this tab.<br><?php echo htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif ($dir_error): ?>
    <p class="media-error"><?php echo htmlspecialchars($dir_error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php elseif (!$enabled_albums): ?>
    <p class="empty-note">Both Outdoor Photos sections are hidden in Admin for this house.</p>
<?php else: ?>

<nav class="outdoor-album-switch" aria-label="Photo albums">
    <?php foreach ($enabled_albums as $key => $meta): ?>
        <?php
        $is_active = $key === $requested_album;
        $count = (int) ($album_counts[$key] ?? 0);
        $href = 'house.php?id=' . (int) $house_id . '&tab=outdoor-photos&album=' . rawurlencode($key);
        ?>
        <a class="outdoor-album-card<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas <?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
            <span class="outdoor-album-card-label"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="outdoor-album-card-count"><?php echo $count; ?> photo<?php echo $count === 1 ? '' : 's'; ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="section-card outdoor-upload-card">
    <h3><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i> Upload to <?php echo htmlspecialchars($album_meta['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <p class="outdoor-upload-hint"><?php echo htmlspecialchars($album_meta['blurb'], ENT_QUOTES, 'UTF-8'); ?> JPG, PNG, GIF, or WebP. Up to 80 photos at a time. HEIC is not supported — export JPG from the phone first.</p>
    <form method="post" enctype="multipart/form-data" class="outdoor-upload-form">
        <input type="hidden" name="outdoor_album" value="<?php echo htmlspecialchars($requested_album, ENT_QUOTES, 'UTF-8'); ?>">
        <label class="outdoor-upload-label">
            <span>Choose photos</span>
            <input type="file" name="outdoor_photos[]" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp" multiple required>
        </label>
        <input type="submit" name="upload_outdoor_photos" value="Upload Photos">
    </form>
</div>

<?php if ($year_counts): ?>
    <div class="outdoor-year-nav" role="navigation" aria-label="Years">
        <?php
        $all_count = array_sum($year_counts);
        $all_href = 'house.php?id=' . (int) $house_id . '&tab=outdoor-photos&album=' . rawurlencode($requested_album);
        ?>
        <a class="outdoor-year-chip<?php echo $requested_year === 0 ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($all_href, ENT_QUOTES, 'UTF-8'); ?>">
            All years
            <span><?php echo (int) $all_count; ?></span>
        </a>
        <?php foreach ($year_counts as $year => $count): ?>
            <?php
            $href = 'house.php?id=' . (int) $house_id . '&tab=outdoor-photos&album=' . rawurlencode($requested_album) . '&year=' . (int) $year;
            $is_year = ((int) $year === $requested_year);
            ?>
            <a class="outdoor-year-chip<?php echo $is_year ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo (int) $year; ?>
                <span><?php echo (int) $count; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$photos): ?>
    <div class="outdoor-empty">
        <i class="fas <?php echo htmlspecialchars($album_meta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
        <p>No <?php echo htmlspecialchars(strtolower($album_meta['label']), ENT_QUOTES, 'UTF-8'); ?> photos<?php echo $requested_year ? ' in ' . (int) $requested_year : ''; ?> yet. Upload a batch and they will line up by date.</p>
    </div>
<?php else: ?>
    <?php $month_index = 0; ?>
    <?php foreach ($timeline as $year => $months): ?>
        <?php foreach ($months as $month_key => $month): ?>
            <?php
            $month_count = 0;
            foreach ($month['days'] as $day) {
                $month_count += count($day['photos']);
            }
            $month_open = $month_index === 0 ? ' open' : '';
            $month_index++;
            ?>
            <details class="section-card collapsible-section outdoor-month-card"<?php echo $month_open; ?>>
                <summary class="collapsible-summary">
                    <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
                    <span class="collapsible-summary-title"><?php echo htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="outdoor-count-pill"><?php echo (int) $month_count; ?></span>
                </summary>
                <div class="collapsible-body">
                    <?php foreach ($month['days'] as $day_key => $day): ?>
                        <section class="outdoor-day" data-lightbox-gallery>
                            <h4 class="outdoor-day-title">
                                <?php echo htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8'); ?>
                                <span class="outdoor-count-pill outdoor-count-pill--quiet"><?php echo count($day['photos']); ?></span>
                            </h4>
                            <div class="outdoor-photo-grid">
                                <?php foreach ($day['photos'] as $photo): ?>
                                    <?php
                                    $photo_id = (int) $photo['id'];
                                    $filename = (string) $photo['filename'];
                                    $fn = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
                                    $original = htmlspecialchars((string) $photo['original_name'], ENT_QUOTES, 'UTF-8');
                                    $thumb_src = htmlspecialchars(hds_outdoor_photos_display_src($photo), ENT_QUOTES, 'UTF-8');
                                    $full_src = htmlspecialchars(hds_outdoor_photos_full_src($photo), ENT_QUOTES, 'UTF-8');
                                    $time_label = hds_outdoor_photos_taken_label($photo);
                                    $source_label = hds_outdoor_photos_date_source_label((string) ($photo['date_source'] ?? 'upload'));
                                    $camera = trim((string) ($photo['camera_make'] ?? '') . ' ' . (string) ($photo['camera_model'] ?? ''));
                                    $caption = trim((string) ($photo['caption'] ?? ''));
                                    $maps = hds_outdoor_photos_maps_url($photo['gps_lat'] ?? null, $photo['gps_lng'] ?? null);
                                    $lightbox_caption = trim($time_label . ($caption !== '' ? ' · ' . $caption : '') . ($camera !== '' ? ' · ' . $camera : ''));
                                    $taken_value = '';
                                    if (!empty($photo['taken_at'])) {
                                        $taken_ts = strtotime((string) $photo['taken_at']);
                                        if ($taken_ts !== false) {
                                            $taken_value = date('Y-m-d\TH:i', $taken_ts);
                                        }
                                    }
                                    ?>
                                    <article class="outdoor-photo-card">
                                        <button type="button" class="media-lightbox-trigger outdoor-photo-thumb" data-src="<?php echo $full_src; ?>" data-caption="<?php echo htmlspecialchars($lightbox_caption !== '' ? $lightbox_caption : $original, ENT_QUOTES, 'UTF-8'); ?>" aria-label="View full size: <?php echo $original !== '' ? $original : $fn; ?>">
                                            <img src="<?php echo $thumb_src; ?>" alt="<?php echo $original !== '' ? $original : $fn; ?>">
                                        </button>
                                        <div class="outdoor-photo-meta">
                                            <p class="outdoor-photo-time"><?php echo htmlspecialchars($time_label, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php if ($caption !== ''): ?>
                                                <p class="outdoor-photo-caption"><?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <?php if ($camera !== ''): ?>
                                                <p class="outdoor-photo-camera"><?php echo htmlspecialchars($camera, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <?php if ($maps): ?>
                                                <p class="outdoor-photo-gps"><a href="<?php echo htmlspecialchars($maps, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Map location</a></p>
                                            <?php endif; ?>
                                            <p class="outdoor-photo-source"><?php echo htmlspecialchars($source_label, ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <details class="outdoor-photo-edit">
                                            <summary>Edit</summary>
                                            <form method="post" class="outdoor-photo-edit-form">
                                                <input type="hidden" name="outdoor_photo_id" value="<?php echo $photo_id; ?>">
                                                <input type="hidden" name="outdoor_album" value="<?php echo htmlspecialchars($requested_album, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="outdoor_year" value="<?php echo (int) $requested_year; ?>">
                                                <label>
                                                    Note
                                                    <input type="text" name="outdoor_caption" maxlength="500" value="<?php echo htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Catch, location, camera site…">
                                                </label>
                                                <label>
                                                    Taken at
                                                    <input type="datetime-local" name="outdoor_taken_at" value="<?php echo htmlspecialchars($taken_value, ENT_QUOTES, 'UTF-8'); ?>">
                                                </label>
                                                <input type="submit" name="save_outdoor_photo" value="Save">
                                            </form>
                                        </details>
                                        <form method="post" class="outdoor-photo-delete-form" onsubmit="return confirm('Delete this photo?');">
                                            <input type="hidden" name="outdoor_photo_id" value="<?php echo $photo_id; ?>">
                                            <input type="hidden" name="outdoor_album" value="<?php echo htmlspecialchars($requested_album, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="outdoor_year" value="<?php echo (int) $requested_year; ?>">
                                            <input type="submit" name="delete_outdoor_photo" value="Delete" class="delete-btn">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div id="outdoorLightbox" class="media-lightbox" hidden aria-hidden="true">
    <div class="media-lightbox-backdrop" data-lightbox-close></div>
    <div class="media-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Image viewer">
        <button type="button" class="media-lightbox-close" data-lightbox-close aria-label="Close">&times;</button>
        <button type="button" class="media-lightbox-nav media-lightbox-prev" aria-label="Previous image">&lsaquo;</button>
        <button type="button" class="media-lightbox-nav media-lightbox-next" aria-label="Next image">&rsaquo;</button>
        <img class="media-lightbox-image" src="" alt="">
        <div class="media-lightbox-caption"></div>
        <div class="media-lightbox-counter"></div>
    </div>
</div>

<?php endif; ?>
