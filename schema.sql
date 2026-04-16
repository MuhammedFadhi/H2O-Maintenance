-- Create Database
CREATE DATABASE IF NOT EXISTS H2O_warranty;
USE H2O_warranty;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Warranties Table
CREATE TABLE IF NOT EXISTS warranties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL UNIQUE,
    product_type VARCHAR(100) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filter_expiry TIMESTAMP NULL,
    service_expiry TIMESTAMP NULL,
    warranty_expiry TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Products Metadata Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_type VARCHAR(100) NOT NULL UNIQUE,
    product_image VARCHAR(255),
    description TEXT
);

-- Service History Table
CREATE TABLE IF NOT EXISTS service_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warranty_id INT NOT NULL,
    service_type ENUM('activation', 'filter', 'service', 'other') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warranty_id) REFERENCES warranties(id) ON DELETE CASCADE
);

-- Initial Product Data
INSERT IGNORE INTO products (product_type, product_image, description) VALUES 
('a1', 'assets/img/p-a1-uv.png', 'A high-performance multi-stage water purifier designed to deliver clean, safe, and great-tasting drinking water. It features sediment, carbon, and advanced filtration stages to effectively remove impurities, odors, and contaminants. Built with durable materials and an efficient design, it ensures reliable purification for homes and small businesses.');

