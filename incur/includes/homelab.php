<?php
// includes/homelab.php — Home lab hardware and LXC/VM helpers

function hds_homelab_device_types(): array
{
    return [
        'proxmox_host' => 'Proxmox Host',
        'server' => 'Server',
        'nas' => 'NAS',
        'network_switch' => 'Network Switch',
        'router' => 'Router / Firewall',
        'access_point' => 'Access Point',
        'ups' => 'UPS',
        'storage' => 'Storage',
        'other' => 'Other',
    ];
}

function hds_homelab_device_type_label(string $type): string
{
    return hds_homelab_device_types()[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function hds_homelab_instance_types(): array
{
    return [
        'lxc' => 'LXC Container',
        'vm' => 'Virtual Machine',
    ];
}

function hds_homelab_instance_type_label(string $type): string
{
    return hds_homelab_instance_types()[$type] ?? strtoupper($type);
}

function hds_homelab_esc($conn, ?string $value): string
{
    return mysqli_real_escape_string($conn, trim((string)$value));
}

function hds_homelab_hardware_options(mysqli $conn, int $house_id): array
{
    $options = [];
    $result = $conn->query("SELECT id, name FROM homelab_hardware WHERE house_id = $house_id ORDER BY name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[(int)$row['id']] = $row['name'];
        }
    }
    return $options;
}

function hds_render_homelab_device_type_select(string $name, string $selected = 'server'): void
{
    echo "<select name='$name' required>";
    foreach (hds_homelab_device_types() as $value => $label) {
        $sel = $value === $selected ? ' selected' : '';
        echo "<option value='$value'$sel>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
    }
    echo "</select>";
}

function hds_render_homelab_instance_type_select(string $name, string $selected = 'lxc'): void
{
    echo "<select name='$name' required>";
    foreach (hds_homelab_instance_types() as $value => $label) {
        $sel = $value === $selected ? ' selected' : '';
        echo "<option value='$value'$sel>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
    }
    echo "</select>";
}

function hds_render_homelab_host_select(string $name, array $hosts, ?int $selected_id = null, bool $required = false): void
{
    $req = $required ? ' required' : '';
    echo "<select name='$name'$req>";
    echo "<option value=''" . ($selected_id ? '' : ' selected') . ">— Not linked —</option>";
    foreach ($hosts as $id => $host_name) {
        $sel = ((int)$id === (int)$selected_id) ? ' selected' : '';
        $label = htmlspecialchars($host_name, ENT_QUOTES, 'UTF-8');
        echo "<option value='$id'$sel>$label</option>";
    }
    echo "</select>";
}

function hds_homelab_detail_grid_open(): void
{
    echo "<div class='homelab-details'>";
}

function hds_homelab_detail_grid_close(): void
{
    echo "</div>";
}

function hds_homelab_detail_field(string $label, ?string $value): void
{
    echo "<p class='hds-ve-field'><span class='hds-ve-label'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ":</span> ";
    echo hds_ve_display($value ?? '');
    echo "</p>";
}

function hds_homelab_form_field(string $label, string $name, ?string $value = '', string $placeholder = '', bool $required = false): void
{
    $value_esc = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    $placeholder_esc = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    $req = $required ? ' required' : '';
    echo "<div class='homelab-field'>";
    echo "<label>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</label>";
    echo "<input type='text' name='$name' value=\"$value_esc\" placeholder='$placeholder_esc'$req>";
    echo "</div>";
}

function hds_homelab_form_textarea(string $label, string $name, ?string $value = '', string $placeholder = ''): void
{
    $value_esc = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    $placeholder_esc = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
    echo "<div class='homelab-field homelab-field--wide'>";
    echo "<label>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</label>";
    echo "<textarea name='$name' rows='2' placeholder='$placeholder_esc'>$value_esc</textarea>";
    echo "</div>";
}