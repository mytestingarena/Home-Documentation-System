<?php
// includes/ui-settings.php — Tab/section visibility registry and helpers (per house)

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
    foreach (array_keys(hds_ui_registry()['tabs']) as $tab) {
        if (hds_ui_tab_enabled($tab, $settings)) {
            return $tab;
        }
    }
    return 'permanent';
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