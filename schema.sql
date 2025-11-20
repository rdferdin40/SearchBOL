-- BOLSearch Database Schema
-- MariaDB / MySQL 8.0+

CREATE DATABASE IF NOT EXISTS bolsearch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bolsearch;

-- Documents table: stores core file-level info
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(512) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    folder VARCHAR(100) DEFAULT 'root',
    year INT NULL,
    file_size BIGINT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    text_content LONGTEXT NULL,
    ocr_status ENUM('none', 'done', 'failed', 'retry_pending') DEFAULT 'none',
    created_at DATETIME NOT NULL,
    modified_at DATETIME NOT NULL,
    indexed_at DATETIME NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    UNIQUE KEY idx_file_path (file_path),
    INDEX idx_sha256 (sha256),
    INDEX idx_year (year),
    INDEX idx_folder (folder),
    INDEX idx_ocr_status (ocr_status),
    INDEX idx_is_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BOL Metadata table
CREATE TABLE IF NOT EXISTS metadata_bol (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    bol_number VARCHAR(100) NULL,
    carrier VARCHAR(255) NULL,
    shipper VARCHAR(255) NULL,
    consignee VARCHAR(255) NULL,
    trailer_number VARCHAR(100) NULL,
    plant VARCHAR(100) NULL,
    ship_date DATE NULL,
    weight DECIMAL(10,2) NULL,
    pallet_count INT NULL,
    seal_number VARCHAR(100) NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_bol_number (bol_number),
    INDEX idx_carrier (carrier),
    INDEX idx_shipper (shipper(100)),
    INDEX idx_consignee (consignee(100)),
    INDEX idx_trailer_number (trailer_number),
    INDEX idx_plant (plant),
    INDEX idx_ship_date (ship_date),
    INDEX idx_seal_number (seal_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingestion jobs table
CREATE TABLE IF NOT EXISTS ingestion_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type ENUM('full', 'folder', 'single') NOT NULL,
    folder VARCHAR(255) NULL,
    file_path VARCHAR(512) NULL,
    status ENUM('pending', 'running', 'done', 'error') DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    error_message TEXT NULL,
    INDEX idx_status (status),
    INDEX idx_job_type (job_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingestion errors table
CREATE TABLE IF NOT EXISTS ingestion_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NULL,
    file_path VARCHAR(512) NOT NULL,
    error_type ENUM('unreadable', 'ocr_failed', 'indexing_error', 'other') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved TINYINT(1) DEFAULT 0,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_error_type (error_type),
    INDEX idx_resolved (resolved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
-- Username: admin
-- Password: admin123 (CHANGE THIS IMMEDIATELY!)
INSERT INTO admin_users (username, password_hash, created_at, is_active)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), 1)
ON DUPLICATE KEY UPDATE username=username;
