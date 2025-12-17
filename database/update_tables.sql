-- Script de mise à jour pour ajouter les tables manquantes
-- Date: 17/12/2025

-- Table stakeholders (avec les bonnes colonnes)
CREATE TABLE IF NOT EXISTS stakeholders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NULL,
    name VARCHAR(255) NOT NULL,
    organization VARCHAR(255),
    role VARCHAR(255),
    type ENUM('interne', 'externe', 'gouvernement', 'prive') DEFAULT 'externe',
    influence INT DEFAULT 3 COMMENT 'De 1 à 5',
    interest INT DEFAULT 3 COMMENT 'De 1 à 5',
    email VARCHAR(100),
    phone VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Table budget_items (avec les bonnes colonnes)
CREATE TABLE IF NOT EXISTS budget_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    planned_amount DECIMAL(15, 2) NOT NULL DEFAULT 0,
    spent_amount DECIMAL(15, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Table resources (avec les bonnes colonnes)
CREATE TABLE IF NOT EXISTS resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type ENUM('humaine', 'materielle', 'financiere') NOT NULL,
    description TEXT,
    quantity INT DEFAULT 1,
    unit VARCHAR(50),
    cost_per_unit DECIMAL(15, 2),
    availability ENUM('disponible', 'assigne', 'maintenance') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
