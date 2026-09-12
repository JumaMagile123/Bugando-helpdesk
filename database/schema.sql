-- =========================================================
-- BUGANDO MEDICAL CENTRE - ICT SERVICE REQUEST & HELPDESK SYSTEM
-- Database Schema (MySQL)
-- =========================================================
-- Create database
CREATE DATABASE IF NOT EXISTS bugando_helpdesk
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE bugando_helpdesk;

-- ---------------------------------------------------------
-- Table: departments
-- Holds all hospital departments (e.g. Radiology, Records, Pharmacy)
-- ---------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Table: users
-- Stores every person who can log in: Admin, HelpDesk, Technician, Staff
-- role column controls which dashboard the user is redirected to
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    password_hash VARCHAR(255) NOT NULL,      -- securely hashed password (bcrypt)
    role ENUM('admin', 'helpdesk', 'technician', 'staff') NOT NULL,
    department_id INT NULL,
    status ENUM('active', 'disabled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- ---------------------------------------------------------
-- Table: categories
-- ICT issue categories (Network, Printer, Hardware, Software, Systems...)
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- ---------------------------------------------------------
-- Table: tickets
-- The core table: every ICT issue reported goes here
-- ---------------------------------------------------------
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) NOT NULL UNIQUE,     -- e.g. BMC-2026-0001
    user_id INT NOT NULL,                      -- user who reported the issue
    department_id INT NULL,
    category_id INT NULL,
    location VARCHAR(150) NULL,                -- hospital area or room
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','critical') DEFAULT 'medium',
    status ENUM('pending','assigned','in_progress','escalated','resolved','closed') DEFAULT 'pending',
    assigned_to INT NULL,                      -- assigned technician id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    feedback TEXT NULL,
    feedback_rating TINYINT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ---------------------------------------------------------
-- Table: ticket_updates
-- Progress notes / status changes made by technicians or helpdesk
-- ---------------------------------------------------------
CREATE TABLE ticket_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    updated_by INT NOT NULL,
    note TEXT NULL,
    status ENUM('pending','assigned','in_progress','escalated','resolved','closed') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- ---------------------------------------------------------
-- Table: escalations
-- Records when a ticket is escalated to a supervisor/officer
-- ---------------------------------------------------------
CREATE TABLE escalations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    escalated_by INT NOT NULL,
    escalated_to INT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (escalated_by) REFERENCES users(id),
    FOREIGN KEY (escalated_to) REFERENCES users(id)
);

-- ---------------------------------------------------------
-- Table: audit_trail
-- Logs important actions for accountability (login, assign, close, etc.)
-- ---------------------------------------------------------
CREATE TABLE audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =========================================================
-- SAMPLE DATA (for testing login + dashboards)
-- Default password for all sample users below is: Password123
-- (already hashed with PHP password_hash - bcrypt)
-- =========================================================
INSERT INTO departments (name) VALUES
('ICT Department'), ('Records'), ('Radiology'), ('Pharmacy'), ('Administration');

INSERT INTO categories (name) VALUES
('Network'), ('Printer'), ('Hardware'), ('Software'), ('Systems');

-- password_hash for 'Password123' (bcrypt) - generate fresh hashes in production
INSERT INTO users (full_name, username, email, password_hash, role, department_id) VALUES
('System Administrator', 'admin', 'admin@bugando.go.tz', '$2y$10$GTTcoMglVJpUiOYMBNrlfeTwI0h0WytrSmYNzfnSdNggF8nxgaZsW', 'admin', 1),
('Helpdesk Officer', 'helpdesk1', 'helpdesk@bugando.go.tz', '$2y$10$GTTcoMglVJpUiOYMBNrlfeTwI0h0WytrSmYNzfnSdNggF8nxgaZsW', 'helpdesk', 1),
('John Technician', 'tech1', 'tech1@bugando.go.tz', '$2y$10$GTTcoMglVJpUiOYMBNrlfeTwI0h0WytrSmYNzfnSdNggF8nxgaZsW', 'technician', 1),
('Demo Staff User', 'staff1', 'staff@bugando.go.tz', '$2y$10$GTTcoMglVJpUiOYMBNrlfeTwI0h0WytrSmYNzfnSdNggF8nxgaZsW', 'staff', 2);
