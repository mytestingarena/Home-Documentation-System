<?php
// tabs/media-sync.php — Register on-disk media files missing from the database

global $conn, $house_id;

require_once __DIR__ . '/../includes/media-video.php';

$result = hds_media_sync_orphans($conn, $house_id);

if ($result['registered'] > 0) {
    $_SESSION['media_success'] = 'Registered ' . $result['registered'] . ' file'
        . ($result['registered'] === 1 ? '' : 's')
        . ' from the server folder into the media library.';
    if ($result['skipped'] > 0) {
        $_SESSION['media_success'] .= ' (' . $result['skipped'] . ' already in database.)';
    }
} else {
    $_SESSION['media_warning'] = 'No new files found to register. Files already in the database: '
        . (int)$result['skipped'] . '.';
}
?>