<?php
// tabs/outdoor-photos-upload.php — Outdoor photos upload handler (called from house.php)

require_once __DIR__ . '/../includes/outdoor-photos.php';

global $conn, $house_id;

hds_outdoor_photos_handle_upload($conn, $house_id);
