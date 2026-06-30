<?php
// includes/ui-settings.php — Tab/section visibility registry and helpers (per house)

function hds_ui_nav_groups(): array {
    return [
        'property' => [
            'label' => 'Property',
            'tabs' => ['permanent', 'utility', 'map', 'household'],
        ],
        'equipment' => [
            'label' => 'Equipment',
            'tabs' => ['tools', 'maintenance', 'homelab', 'contractors'],
        ],
        'media' => [
            'label' => 'Media & Files',
            'tabs' => ['media', 'manuals', 'designs'],
        ],
        'planning' => [
            'label' => 'Planning',
            'tabs' => ['projects'],
        ],
        'system' => [
            'label' => 'System',
            'tabs' => ['wifi', 'admin'],
        ],
    ];
}

function hds_ui_registry(): array {
    return [
        'tabs' => [
            'permanent'   => ['label' => 'Permanent Items',   'icon' => 'fa-tools'],
            'utility'     => ['label' => 'Utility Services',  'icon' => 'fa-bolt'],
            'household'   => ['label' => 'Household Items',   'icon' => 'fa-home'],
            'contractors' => ['label' => 'Contractors',       'icon' => 'fa-hard-hat'],
            'homelab'     => ['label' => 'Home Lab',          'icon' => 'fa-server'],
            'tools'       => ['label' => 'Tools',             'icon' => 'fa-toolbox'],
            'maintenance' => ['label' => 'Maintenance',       'icon' => 'fa-oil-can'],
            'media'       => ['label' => 'Media',             'icon' => 'fa-images'],
            'designs'     => ['label' => 'Designs',           'icon' => 'fa-drafting-compass'],
            'manuals'     => ['label' => 'User Manuals',    'icon' => 'fa-book'],
            'map'         => ['label' => 'Map Location',      'icon' => 'fa-map-marker-alt'],
            'wifi'        => ['label' => 'WiFi',              'icon' => 'fa-wifi'],
            'projects'    => ['label' => 'Project List',      'icon' => 'fa-tasks'],
        ],
        'sections' => [
            'permanent' => [
                'permanent-furnace'      => 'Furnace',
                'permanent-water_heater' => 'Water Heater',
                'permanent-dishwasher'   => 'Dishwasher',
                'permanent-washer'       => 'Washer',
                'permanent-dryer'        => 'Dryer',
                'permanent-ac'           => 'Air Conditioner',
                'permanent-outdoor_work' => 'Outdoor Work',
                'permanent-house_work'   => 'House Work',
                'permanent-breakers'     => 'Breaker Panels',
            ],
            'utility' => [
                'utility-electric'   => 'Electric Meter',
                'utility-generator'  => 'Generator',
                'utility-water'      => 'Water Utility',
                'utility-propane'    => 'Propane',
            ],
            'media' => [
                'media-interior'    => 'Regular Interior Photos',
                'media-walkthrough' => 'Site Walkthrough',
                'media-exterior'    => 'Regular Exterior Photos',
                'media-ir-interior' => 'IR Interior Scans',
                'media-ir-exterior' => 'IR Exterior Scans',
            ],
            'map' => [
                'map-property' => 'Property Details & Map',
                'map-taxes'    => 'Property Taxes',
            ],
            'manuals' => [
                'manuals-upload' => 'Upload Manuals',
                'manuals-list'   => 'Uploaded Manuals',
            ],
            'designs' => [
                'designs-upload' => 'Upload Design Files',
                'designs-list'   => 'Uploaded Designs',
            ],
            'projects' => [
                'projects-active'    => 'Active Projects',
                'projects-completed' => 'Completed Projects Tracker',
            ],
            'homelab' => [
                'homelab-hardware'  => 'Hardware',
                'homelab-instances' => 'LXC / VMs',
            ],
        ],
    ];
}

function hds_ui_load_settings(mysqli $conn, int $house_id): array {
    $settings = [];
    $result = @$conn->query("SELECT setting_key, enabled FROM house_ui_settings WHERE house_id = $house_id");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = (int)$row['enabled'];
        }
    }
    return $settings;
}

function hds_ui_setting_enabled(string $key, array $settings, bool $default = true): bool {
    if (!array_key_exists($key, $settings)) {
        return $default;
    }
    return (bool)$settings[$key];
}

function hds_ui_tab_enabled(string $tab, array $settings): bool {
    return hds_ui_setting_enabled('tab:' . $tab, $settings);
}

function hds_ui_section_enabled(string $section, array $settings): bool {
    return hds_ui_setting_enabled('section:' . $section, $settings);
}

function hds_ui_first_enabled_tab(array $settings): string {
    foreach (hds_ui_nav_groups() as $group) {
        foreach ($group['tabs'] as $tab) {
            if ($tab === 'admin') {
                continue;
            }
            if (hds_ui_tab_enabled($tab, $settings)) {
                return $tab;
            }
        }
    }
    return 'permanent';
}

function hds_ui_render_sidebar_nav(string $active_tab, array $settings): void {
    $registry = hds_ui_registry();
    $tabs = $registry['tabs'];

    echo '<nav class="hds-sidebar-nav" aria-label="House documentation sections">';
    foreach (hds_ui_nav_groups() as $group_key => $group) {
        $group_has_items = false;
        ob_start();
        foreach ($group['tabs'] as $tab_key) {
            if ($tab_key === 'admin') {
                $label = 'Admin';
                $icon = 'fa-sliders';
                $is_active = $active_tab === 'admin';
                $extra_class = ' hds-nav-link--admin';
            } else {
                if (!hds_ui_tab_enabled($tab_key, $settings)) {
                    continue;
                }
                if (!isset($tabs[$tab_key])) {
                    continue;
                }
                $meta = $tabs[$tab_key];
                $label = $meta['label'];
                $icon = $meta['icon'];
                $is_active = $active_tab === $tab_key;
                $extra_class = '';
            }
            $group_has_items = true;
            $active_class = $is_active ? ' active' : '';
            $tab_esc = htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8');
            $label_esc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $icon_esc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
            echo "<button type='button' class='hds-nav-link$extra_class$active_class' data-tab='$tab_esc' onclick='openTab(event, \"$tab_esc\")'>";
            echo "<i class='fas $icon_esc' aria-hidden='true'></i>";
            echo "<span>$label_esc</span>";
            echo "</button>";
        }
        $group_html = ob_get_clean();
        if (!$group_has_items) {
            continue;
        }
        $group_label = htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8');
        echo "<div class='hds-nav-group' data-nav-group='$group_key'>";
        echo "<h3 class='hds-nav-group-label'>$group_label</h3>";
        echo $group_html;
        echo "</div>";
    }
    echo '</nav>';
}

function hds_ui_all_setting_keys(): array {
    $keys = [];
    $registry = hds_ui_registry();
    foreach (array_keys($registry['tabs']) as $tab) {
        $keys[] = 'tab:' . $tab;
    }
    foreach ($registry['sections'] as $sections) {
        foreach (array_keys($sections) as $section) {
            $keys[] = 'section:' . $section;
        }
    }
    return $keys;
}