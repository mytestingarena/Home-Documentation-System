<?php
// includes/sidebar-nav.php — grouped sidebar shell (included from house.php)

function hds_render_sidebar_shell(string $active_tab, array $settings): void {
    ?>
    <div class="hds-sidebar-backdrop" id="hdsSidebarBackdrop" onclick="toggleMenu()" aria-hidden="true"></div>
    <aside id="hdsSidebar" class="hds-sidebar" aria-label="Main navigation">
        <div class="hds-sidebar-header">
            <span class="hds-sidebar-title">Sections</span>
            <button type="button" class="hds-sidebar-close" onclick="toggleMenu()" aria-label="Close menu">&times;</button>
        </div>
        <?php hds_ui_render_sidebar_nav($active_tab, $settings); ?>
    </aside>
    <?php
}