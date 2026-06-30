-- 1. Create database
CREATE DATABASE IF NOT EXISTS house_info 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE house_info;

CREATE TABLE IF NOT EXISTS propane_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES utility_bills(id) ON DELETE CASCADE
);
