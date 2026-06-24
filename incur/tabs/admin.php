<?php
// tabs/admin.php — Show/hide tabs and sections for this house

global $conn, $house_id, $hds_ui_settings;

require_once __DIR__ . '/../includes/ui-settings.php';

$admin_unlocked = (session_status() === PHP_SESSION_ACTIVE) && !empty($_SESSION['admin_unlocked']);
$admin_error = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['admin_error'] ?? '') : '';
$admin_saved = !empty($_GET['admin_saved']);
if (session_status() === PHP_SESSION_ACTIVE) {
    unset($_SESSION['admin_error']);
}

$registry = hds_ui_registry();
?>

<h2>Admin Settings</h2>

<?php if (!$admin_unlocked): ?>
    <div class="section-card admin-lock-card">
        <h3><i class="fas fa-lock" aria-hidden="true"></i> Protected Area</h3>
        <p>Enter the admin password to show or hide tabs and sections for this house.</p>
        <?php if ($admin_error): ?>
            <p class="admin-error"><?php echo htmlspecialchars($admin_error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form method="post" class="admin-unlock-form">
            <label for="admin_access_password">Password:</label><br>
            <input type="password" id="admin_access_password" name="admin_access_password" required autocomplete="current-password"><br><br>
            <input type="submit" name="admin_unlock" value="Unlock Admin">
        </form>
    </div>
<?php else: ?>
    <div class="admin-toolbar">
        <form method="post" class="admin-lock-form">
            <input type="submit" name="admin_lock" value="Lock Admin" class="admin-lock-btn">
        </form>
    </div>

    <?php if ($admin_saved): ?>
        <div class="admin-saved-notice">Settings saved. Hidden tabs and sections are no longer shown in the menu.</div>
    <?php endif; ?>

    <p class="admin-intro">Turn off anything you do not use. Disabled tabs disappear from the menu. Disabled sections are hidden inside their tab. The Admin tab always stays available so you can turn things back on.</p>

    <form method="post" class="admin-settings-form">
        <div class="section-card admin-settings-card">
            <h3>Tabs</h3>
            <p class="admin-hint">Uncheck a tab to hide it completely for this house.</p>
            <div class="admin-check-grid">
                <?php foreach ($registry['tabs'] as $tab_key => $tab_meta): ?>
                    <?php $checked = hds_ui_tab_enabled($tab_key, $hds_ui_settings) ? ' checked' : ''; ?>
                    <label class="admin-check-item">
                        <input type="checkbox" name="ui_enabled[]" value="tab:<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $checked; ?>>
                        <span><?php echo htmlspecialchars($tab_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach ($registry['sections'] as $tab_key => $sections): ?>
            <?php
            $tab_label = $registry['tabs'][$tab_key]['label'] ?? ucfirst($tab_key);
            ?>
            <div class="section-card admin-settings-card">
                <h3><?php echo htmlspecialchars($tab_label, ENT_QUOTES, 'UTF-8'); ?> — Sections</h3>
                <p class="admin-hint">These only apply when the tab above is enabled.</p>
                <div class="admin-check-grid">
                    <?php foreach ($sections as $section_key => $section_label): ?>
                        <?php $checked = hds_ui_section_enabled($section_key, $hds_ui_settings) ? ' checked' : ''; ?>
                        <label class="admin-check-item">
                            <input type="checkbox" name="ui_enabled[]" value="section:<?php echo htmlspecialchars($section_key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $checked; ?>>
                            <span><?php echo htmlspecialchars($section_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="admin-form-actions">
            <input type="submit" name="save_ui_settings" value="Save Visibility Settings">
        </div>
    </form>
<?php endif; ?>