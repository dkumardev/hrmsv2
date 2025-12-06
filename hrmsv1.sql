CREATE DATABASE property_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE property_mgmt;

CREATE TABLE owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(100) NOT NULL,      -- plain text (as you requested)
    full_name  VARCHAR(100) NULL,
    email      VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO owners (username, password, full_name, email)
VALUES ('owner', 'owner123', 'Main Owner', 'owner@example.com');

CREATE TABLE buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    unit_name VARCHAR(100) NOT NULL,
    floor VARCHAR(50) NULL,
    unit_type ENUM('Room','Shop','Flat') DEFAULT 'Room',
    amenities VARCHAR(255) NULL,
    status ENUM('Vacant','Occupied') DEFAULT 'Vacant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_units_building
        FOREIGN KEY (building_id) REFERENCES buildings(id)
        ON DELETE CASCADE
);

CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    father_name     VARCHAR(150) NOT NULL,
    phone_number    VARCHAR(20)  NOT NULL,
    current_address VARCHAR(255) NULL,
    id_proof_type   VARCHAR(50)  NULL,          -- e.g. Aadhar, PAN, Voter ID
    id_proof_number VARCHAR(100) NULL,
    id_proof_file   VARCHAR(255) NULL,          -- e.g. id_proofs/deepak_1234567898.pdf
    tenant_status   ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE unit_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    unit_id INT NOT NULL,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,                      -- NULL = active
    monthly_rent DECIMAL(10,2) NOT NULL,
    advance_deposit DECIMAL(10,2) NOT NULL DEFAULT 0,
    initial_meter_reading INT NULL,
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assign_building
        FOREIGN KEY (building_id) REFERENCES buildings(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assign_unit
        FOREIGN KEY (unit_id) REFERENCES units(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_assign_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id)
        ON DELETE RESTRICT
);


