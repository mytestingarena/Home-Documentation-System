<?php
// tabs/homelab.php — Home lab hardware and LXC/VM documentation

global $conn, $house_id, $hds_ui_settings;

require_once __DIR__ . '/../includes/homelab.php';

$open_homelab = preg_replace('/[^a-z_]/', '', $_GET['open_homelab'] ?? '');
$hardware_hosts = hds_homelab_hardware_options($conn, $house_id);
?>

<h2>Home Lab</h2>
<p class="homelab-intro">Document physical hardware and the LXC containers or VMs running on your lab hosts.</p>

<div class="collapsible-list-toolbar">
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.homelab-stack .collapsible-section', true)">Expand all</button>
    <button type="button" class="small-btn" onclick="collapsibleExpandAll('.homelab-stack .collapsible-section', false)">Collapse all</button>
</div>

<div class="homelab-stack">

<?php if (hds_ui_section_enabled('homelab-hardware', $hds_ui_settings)): ?>
<details class="homelab-block homelab-block--hardware collapsible-section" id="homelab-hardware"<?php echo $open_homelab === 'hardware' ? ' open' : ''; ?>>
    <summary class="collapsible-summary">
        <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
        <i class="fas fa-server homelab-icon" aria-hidden="true"></i>
        <h3>Hardware</h3>
    </summary>
    <div class="collapsible-body">
        <form method="post" class="homelab-add-form">
            <h4>Add Hardware</h4>
            <div class="homelab-form-grid">
                <?php
                hds_homelab_form_field('Name / Hostname', 'hw_name', '', 'e.g. proxmox-01', true);
                echo "<div class='homelab-field'><label>Type</label>";
                hds_render_homelab_device_type_select('hw_device_type', 'server');
                echo "</div>";
                hds_homelab_form_field('Make / Model', 'hw_make_model', '', 'Dell R720, Synology DS920+');
                hds_homelab_form_field('CPU', 'hw_cpu', '', 'e.g. Xeon E5-2680, i7-12700');
                hds_homelab_form_field('RAM', 'hw_ram', '', 'e.g. 64 GB');
                hds_homelab_form_field('Storage', 'hw_storage', '', 'e.g. 2x 1TB SSD, 4TB RAID');
                hds_homelab_form_field('IP Address', 'hw_ip', '', '192.168.1.10');
                hds_homelab_form_field('MAC Address', 'hw_mac', '', 'Optional');
                hds_homelab_form_field('Location', 'hw_location', '', 'Rack, closet, office');
                hds_homelab_form_field('Role', 'hw_role', '', 'Proxmox host, NAS, router');
                hds_homelab_form_field('Serial Number', 'hw_serial', '', 'Optional');
                hds_homelab_form_textarea('Notes', 'hw_notes', '', 'Firmware, warranty, purchase date, etc.');
                ?>
                <div class="homelab-field homelab-field--submit">
                    <input type="submit" name="add_homelab_hardware" value="Add Hardware" class="small-btn">
                </div>
            </div>
        </form>

        <div class="homelab-list">
        <?php
        $hardware = $conn->query("SELECT * FROM homelab_hardware WHERE house_id = $house_id ORDER BY name ASC, id ASC");
        if ($hardware && $hardware->num_rows > 0) {
            while ($row = $hardware->fetch_assoc()) {
                $hw_id = (int)$row['id'];
                $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $type_label = htmlspecialchars(hds_homelab_device_type_label($row['device_type'] ?? 'other'), ENT_QUOTES, 'UTF-8');
                $device_type = $row['device_type'] ?? 'server';

                echo "<div data-view-edit class='hds-ve-block hds-ve-block--card homelab-entry'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header hds-ve-header--split'>";
                echo "<div><strong class='homelab-entry-title'>$name</strong><span class='homelab-entry-badge'>$type_label</span></div>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this hardware entry?\");'>";
                echo "<input type='hidden' name='hw_id' value='$hw_id'>";
                echo "<input type='submit' name='delete_homelab_hardware' value='Delete' class='small-btn delete-btn'>";
                echo "</form></div></div>";
                hds_homelab_detail_grid_open();
                hds_homelab_detail_field('Make / Model', $row['make_model'] ?? '');
                hds_homelab_detail_field('CPU', $row['cpu'] ?? '');
                hds_homelab_detail_field('RAM', $row['ram'] ?? '');
                hds_homelab_detail_field('Storage', $row['storage'] ?? '');
                hds_homelab_detail_field('IP Address', $row['ip_address'] ?? '');
                hds_homelab_detail_field('MAC Address', $row['mac_address'] ?? '');
                hds_homelab_detail_field('Location', $row['location'] ?? '');
                hds_homelab_detail_field('Role', $row['role'] ?? '');
                hds_homelab_detail_field('Serial Number', $row['serial_number'] ?? '');
                if (trim($row['notes'] ?? '') !== '') {
                    hds_homelab_detail_field('Notes', $row['notes'] ?? '');
                }
                hds_homelab_detail_grid_close();
                echo "</div>";

                echo "<div data-view-edit-form hidden><form method='post'><input type='hidden' name='hw_id' value='$hw_id'>";
                echo "<div class='homelab-form-grid'>";
                hds_homelab_form_field('Name / Hostname', 'hw_name', $row['name'] ?? '', '', true);
                echo "<div class='homelab-field'><label>Type</label>";
                hds_render_homelab_device_type_select('hw_device_type', $device_type);
                echo "</div>";
                hds_homelab_form_field('Make / Model', 'hw_make_model', $row['make_model'] ?? '');
                hds_homelab_form_field('CPU', 'hw_cpu', $row['cpu'] ?? '');
                hds_homelab_form_field('RAM', 'hw_ram', $row['ram'] ?? '');
                hds_homelab_form_field('Storage', 'hw_storage', $row['storage'] ?? '');
                hds_homelab_form_field('IP Address', 'hw_ip', $row['ip_address'] ?? '');
                hds_homelab_form_field('MAC Address', 'hw_mac', $row['mac_address'] ?? '');
                hds_homelab_form_field('Location', 'hw_location', $row['location'] ?? '');
                hds_homelab_form_field('Role', 'hw_role', $row['role'] ?? '');
                hds_homelab_form_field('Serial Number', 'hw_serial', $row['serial_number'] ?? '');
                hds_homelab_form_textarea('Notes', 'hw_notes', $row['notes'] ?? '');
                echo "</div><div class='hds-ve-edit-actions'>";
                echo "<input type='submit' name='update_homelab_hardware' value='Save'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div></form></div></div>";
            }
        } else {
            echo "<p class='empty-note'>No hardware documented yet.</p>";
        }
        ?>
        </div>
    </div>
</details>
<?php endif; ?>

<?php if (hds_ui_section_enabled('homelab-instances', $hds_ui_settings)): ?>
<details class="homelab-block homelab-block--instances collapsible-section" id="homelab-instances"<?php echo $open_homelab === 'instances' ? ' open' : ''; ?>>
    <summary class="collapsible-summary">
        <i class="fas fa-chevron-right collapsible-chevron" aria-hidden="true"></i>
        <i class="fas fa-box homelab-icon" aria-hidden="true"></i>
        <h3>LXC / VMs</h3>
    </summary>
    <div class="collapsible-body">
        <form method="post" class="homelab-add-form">
            <h4>Add LXC or VM</h4>
            <div class="homelab-form-grid">
                <?php
                hds_homelab_form_field('Name', 'inst_name', '', 'e.g. hds, pihole, homeassistant', true);
                echo "<div class='homelab-field'><label>Type</label>";
                hds_render_homelab_instance_type_select('inst_type', 'lxc');
                echo "</div>";
                echo "<div class='homelab-field'><label>Host</label>";
                hds_render_homelab_host_select('inst_hardware_id', $hardware_hosts);
                echo "</div>";
                hds_homelab_form_field('OS', 'inst_os', '', 'Debian 12, Ubuntu 24.04');
                hds_homelab_form_field('IP Address', 'inst_ip', '', '192.168.1.110');
                hds_homelab_form_field('CPU Cores', 'inst_cpu', '', 'e.g. 2');
                hds_homelab_form_field('RAM', 'inst_ram', '', 'e.g. 4 GB');
                hds_homelab_form_field('Disk', 'inst_disk', '', 'e.g. 32 GB');
                hds_homelab_form_field('VLAN / Network', 'inst_network', '', 'e.g. vmbr0, VLAN 20');
                hds_homelab_form_field('Ports / Services', 'inst_ports', '', '80, 443, 22');
                hds_homelab_form_field('Purpose', 'inst_purpose', '', 'Home docs app, DNS, etc.');
                hds_homelab_form_textarea('Backup Notes', 'inst_backup', '', 'Snapshot schedule, backup location');
                hds_homelab_form_textarea('Notes', 'inst_notes', '', 'VMID, CT ID, credentials location, etc.');
                ?>
                <div class="homelab-field homelab-field--submit">
                    <input type="submit" name="add_homelab_instance" value="Add LXC / VM" class="small-btn">
                </div>
            </div>
        </form>

        <div class="homelab-list">
        <?php
        $instances = $conn->query(
            "SELECT i.*, h.name AS host_name
             FROM homelab_instances i
             LEFT JOIN homelab_hardware h ON i.hardware_id = h.id
             WHERE i.house_id = $house_id
             ORDER BY i.name ASC, i.id ASC"
        );
        if ($instances && $instances->num_rows > 0) {
            while ($row = $instances->fetch_assoc()) {
                $inst_id = (int)$row['id'];
                $name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $type_label = htmlspecialchars(hds_homelab_instance_type_label($row['instance_type'] ?? 'lxc'), ENT_QUOTES, 'UTF-8');
                $instance_type = $row['instance_type'] ?? 'lxc';
                $hardware_id = isset($row['hardware_id']) ? (int)$row['hardware_id'] : null;

                echo "<div data-view-edit class='hds-ve-block hds-ve-block--card homelab-entry'>";
                echo "<div data-view-edit-view>";
                echo "<div class='hds-ve-header hds-ve-header--split'>";
                echo "<div><strong class='homelab-entry-title'>$name</strong><span class='homelab-entry-badge'>$type_label</span></div>";
                echo "<div class='hds-ve-actions'>";
                echo "<button type='button' class='small-btn' data-view-edit-open>Edit</button>";
                echo "<form method='post' class='hds-ve-delete-form' onsubmit='return confirm(\"Delete this LXC/VM entry?\");'>";
                echo "<input type='hidden' name='inst_id' value='$inst_id'>";
                echo "<input type='submit' name='delete_homelab_instance' value='Delete' class='small-btn delete-btn'>";
                echo "</form></div></div>";
                hds_homelab_detail_grid_open();
                hds_homelab_detail_field('Host', $row['host_name'] ?? '');
                hds_homelab_detail_field('OS', $row['os'] ?? '');
                hds_homelab_detail_field('IP Address', $row['ip_address'] ?? '');
                hds_homelab_detail_field('CPU Cores', $row['cpu_cores'] ?? '');
                hds_homelab_detail_field('RAM', $row['ram'] ?? '');
                hds_homelab_detail_field('Disk', $row['disk'] ?? '');
                hds_homelab_detail_field('VLAN / Network', $row['network'] ?? '');
                hds_homelab_detail_field('Ports / Services', $row['ports'] ?? '');
                hds_homelab_detail_field('Purpose', $row['purpose'] ?? '');
                if (trim($row['backup_notes'] ?? '') !== '') {
                    hds_homelab_detail_field('Backup Notes', $row['backup_notes'] ?? '');
                }
                if (trim($row['notes'] ?? '') !== '') {
                    hds_homelab_detail_field('Notes', $row['notes'] ?? '');
                }
                hds_homelab_detail_grid_close();
                echo "</div>";

                echo "<div data-view-edit-form hidden><form method='post'><input type='hidden' name='inst_id' value='$inst_id'>";
                echo "<div class='homelab-form-grid'>";
                hds_homelab_form_field('Name', 'inst_name', $row['name'] ?? '', '', true);
                echo "<div class='homelab-field'><label>Type</label>";
                hds_render_homelab_instance_type_select('inst_type', $instance_type);
                echo "</div>";
                echo "<div class='homelab-field'><label>Host</label>";
                hds_render_homelab_host_select('inst_hardware_id', $hardware_hosts, $hardware_id ?: null);
                echo "</div>";
                hds_homelab_form_field('OS', 'inst_os', $row['os'] ?? '');
                hds_homelab_form_field('IP Address', 'inst_ip', $row['ip_address'] ?? '');
                hds_homelab_form_field('CPU Cores', 'inst_cpu', $row['cpu_cores'] ?? '');
                hds_homelab_form_field('RAM', 'inst_ram', $row['ram'] ?? '');
                hds_homelab_form_field('Disk', 'inst_disk', $row['disk'] ?? '');
                hds_homelab_form_field('VLAN / Network', 'inst_network', $row['network'] ?? '');
                hds_homelab_form_field('Ports / Services', 'inst_ports', $row['ports'] ?? '');
                hds_homelab_form_field('Purpose', 'inst_purpose', $row['purpose'] ?? '');
                hds_homelab_form_textarea('Backup Notes', 'inst_backup', $row['backup_notes'] ?? '');
                hds_homelab_form_textarea('Notes', 'inst_notes', $row['notes'] ?? '');
                echo "</div><div class='hds-ve-edit-actions'>";
                echo "<input type='submit' name='update_homelab_instance' value='Save'>";
                echo "<button type='button' class='small-btn' data-view-edit-cancel>Cancel</button>";
                echo "</div></form></div></div>";
            }
        } else {
            echo "<p class='empty-note'>No LXC containers or VMs documented yet.</p>";
        }
        ?>
        </div>
    </div>
</details>
<?php endif; ?>

</div>