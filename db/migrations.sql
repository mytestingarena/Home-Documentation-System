-- Run once on an existing house_info database to align schema with the app.

USE house_info;

-- Map / location columns (safe if already present)
ALTER TABLE houses
    ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tax_number VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS map_zoom TINYINT DEFAULT 16,
    ADD COLUMN IF NOT EXISTS google_embed_src VARCHAR(1000) DEFAULT NULL;

-- Permanent items: allow AC and future types
ALTER TABLE permanent_items
    MODIFY COLUMN item_type VARCHAR(20) NOT NULL;

-- Photos: allow walkthrough media section
ALTER TABLE photos
    MODIFY COLUMN section ENUM('Interior','Exterior','Walkthrough') DEFAULT NULL;

-- Household items: allow additional categories
ALTER TABLE household_items
    MODIFY COLUMN type ENUM('TV','Server','Other') DEFAULT 'TV';

-- Electric panels: ensure size column exists
ALTER TABLE electric_panels
    ADD COLUMN IF NOT EXISTS size INT NOT NULL DEFAULT 24 AFTER name;

-- Utility tables used by the Utility tab
CREATE TABLE IF NOT EXISTS utility_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    utility_type ENUM('electric','water','propane') NOT NULL DEFAULT 'electric',
    amount_owed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    payment_method ENUM('debit','credit','check') DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS water_utilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    meter_number VARCHAR(50) DEFAULT NULL,
    billing_frequency ENUM('Monthly','Quarterly','Annual') DEFAULT 'Monthly',
    phone VARCHAR(20) DEFAULT NULL,
    UNIQUE KEY unique_house (house_id),
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS propane_utilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    gallons DECIMAL(10,1) DEFAULT 0.0,
    provider VARCHAR(100) DEFAULT NULL,
    tank_sn VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    UNIQUE KEY unique_house (house_id),
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS water_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES utility_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS propane_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES utility_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_taxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    parcel_id VARCHAR(50) DEFAULT NULL,
    amount_owed DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date DATE NOT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    check_number VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_manuals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    date_added DATE NOT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    date_completed DATETIME DEFAULT NULL,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    material_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantity INT NOT NULL DEFAULT 1,
    url VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Property taxes: check number for paid bills
ALTER TABLE property_taxes
    ADD COLUMN IF NOT EXISTS check_number VARCHAR(50) DEFAULT NULL AFTER is_paid;

-- Water utility bills: how the bill was paid
ALTER TABLE utility_bills
    ADD COLUMN IF NOT EXISTS payment_method ENUM('debit','credit','check') DEFAULT NULL AFTER is_paid;

-- Tools inventory (per house)
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    power_type ENUM('battery','ac','pneumatic','manual','na') NOT NULL DEFAULT 'manual',
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tools
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL AFTER power_type;

ALTER TABLE tools
    MODIFY COLUMN power_type ENUM('battery','ac','pneumatic','manual','na') NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS generators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    model VARCHAR(100) DEFAULT NULL,
    sn VARCHAR(100) DEFAULT NULL,
    efficiency VARCHAR(50) DEFAULT NULL,
    kwh DECIMAL(6,2) DEFAULT 0.00,
    fuel_type ENUM('LP', 'NG') DEFAULT 'LP',
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wifi_networks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    network_name VARCHAR(255) NOT NULL,
    password VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    category ENUM('atv','boat','lawnmower','other') NOT NULL DEFAULT 'other',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_fluids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    fluid_name VARCHAR(100) NOT NULL,
    specification VARCHAR(255) DEFAULT NULL,
    capacity VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (equipment_id) REFERENCES maintenance_equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    part_name VARCHAR(100) NOT NULL,
    part_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (equipment_id) REFERENCES maintenance_equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    log_date DATE NOT NULL,
    description TEXT NOT NULL,
    hours_mileage VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES maintenance_equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Maintenance log per permanent item section (furnace, water heater, breakers, etc.)
CREATE TABLE IF NOT EXISTS permanent_maintenance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    item_type VARCHAR(20) NOT NULL,
    log_date DATE NOT NULL,
    part_number VARCHAR(100) DEFAULT NULL,
    completed_by ENUM('homeowner', 'contractor') NOT NULL DEFAULT 'homeowner',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_permanent_log (house_id, item_type, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outdoor_work_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    work_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    date_completed DATE DEFAULT NULL,
    contractor VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_outdoor_work_house (house_id, date_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outdoor_work_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    outdoor_work_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (outdoor_work_id) REFERENCES outdoor_work_items(id) ON DELETE CASCADE,
    INDEX idx_outdoor_work_images (outdoor_work_id, upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE permanent_maintenance_log
    ADD COLUMN IF NOT EXISTS contractor_price DECIMAL(10,2) DEFAULT NULL AFTER completed_by,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('debit', 'cc', 'check') DEFAULT NULL AFTER contractor_price,
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL AFTER payment_method;

CREATE TABLE IF NOT EXISTS homelab_hardware (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    device_type VARCHAR(30) NOT NULL DEFAULT 'server',
    make_model VARCHAR(255) DEFAULT NULL,
    cpu VARCHAR(255) DEFAULT NULL,
    ram VARCHAR(100) DEFAULT NULL,
    storage VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(50) DEFAULT NULL,
    mac_address VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    role VARCHAR(255) DEFAULT NULL,
    serial_number VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_homelab_hardware_house (house_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homelab_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    instance_type ENUM('lxc', 'vm') NOT NULL DEFAULT 'lxc',
    hardware_id INT DEFAULT NULL,
    os VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(50) DEFAULT NULL,
    cpu_cores VARCHAR(50) DEFAULT NULL,
    ram VARCHAR(100) DEFAULT NULL,
    disk VARCHAR(100) DEFAULT NULL,
    network VARCHAR(255) DEFAULT NULL,
    ports VARCHAR(255) DEFAULT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    backup_notes TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (hardware_id) REFERENCES homelab_hardware(id) ON DELETE SET NULL,
    INDEX idx_homelab_instances_house (house_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contractors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    trade VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_contractors_house (house_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-house tab and section visibility (Admin settings)
CREATE TABLE IF NOT EXISTS house_ui_settings (
    house_id INT NOT NULL,
    setting_key VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (house_id, setting_key),
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_work_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_id INT NOT NULL,
    work_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    date_completed DATE DEFAULT NULL,
    contractor VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    INDEX idx_house_work_house (house_id, date_completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_work_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    house_work_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (house_work_id) REFERENCES house_work_items(id) ON DELETE CASCADE,
    INDEX idx_house_work_images (house_work_id, upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permanent_maintenance_log_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (log_id) REFERENCES permanent_maintenance_log(id) ON DELETE CASCADE,
    INDEX idx_perm_log_images (log_id, upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE permanent_maintenance_log
    MODIFY COLUMN payment_method ENUM('debit', 'cc', 'check', 'cash') DEFAULT NULL;

ALTER TABLE house_work_items
    ADD COLUMN IF NOT EXISTS completed_by ENUM('homeowner', 'contractor') NOT NULL DEFAULT 'homeowner' AFTER contractor,
    ADD COLUMN IF NOT EXISTS contractor_price DECIMAL(10,2) DEFAULT NULL AFTER completed_by,
    ADD COLUMN IF NOT EXISTS payment_method ENUM('debit', 'cc', 'check', 'cash') DEFAULT NULL AFTER contractor_price,
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) DEFAULT NULL AFTER payment_method;
