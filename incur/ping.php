<?php
header('Content-Type: text/plain');
echo "step1-ok\n";

include 'config.php';
echo "step2-config-ok\n";

if ($conn->ping()) {
    echo "step3-db-ok\n";
} else {
    echo "step3-db-fail\n";
}

echo "wifi_tab=" . (file_exists(__DIR__ . '/tabs/wifi.php') ? 'yes' : 'no') . "\n";
echo "session=" . (function_exists('session_start') ? 'yes' : 'no') . "\n";
?>