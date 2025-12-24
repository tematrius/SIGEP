-- Table pour les ressources (équipements, véhicules, matériel)
CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('vehicle', 'equipment', 'material', 'human', 'other') NOT NULL DEFAULT 'other',
    description TEXT,
    reference_code VARCHAR(50),
    status ENUM('available', 'in_use', 'maintenance', 'unavailable') NOT NULL DEFAULT 'available',
    acquisition_date DATE,
    cost_per_day DECIMAL(10,2),
    location VARCHAR(255),
    specifications TEXT,
    photo_url VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour l'affectation des ressources aux projets
CREATE TABLE IF NOT EXISTS resource_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    project_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    quantity INT DEFAULT 1,
    daily_cost DECIMAL(10,2),
    notes TEXT,
    status ENUM('planned', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    allocated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (allocated_by) REFERENCES users(id),
    INDEX idx_resource (resource_id),
    INDEX idx_project (project_id),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vue pour voir la disponibilité des ressources
CREATE OR REPLACE VIEW resource_availability AS
SELECT 
    r.id,
    r.name,
    r.type,
    r.status,
    COUNT(ra.id) as active_allocations,
    GROUP_CONCAT(
        CONCAT(p.title, ' (', ra.start_date, ' - ', COALESCE(ra.end_date, 'En cours'), ')')
        ORDER BY ra.start_date
        SEPARATOR '; '
    ) as allocations
FROM resources r
LEFT JOIN resource_allocations ra ON r.id = ra.resource_id AND ra.status = 'active'
LEFT JOIN projects p ON ra.project_id = p.id
GROUP BY r.id, r.name, r.type, r.status;

-- Insérer quelques ressources d'exemple
INSERT INTO resources (name, type, description, reference_code, status, cost_per_day, created_by) VALUES
('Toyota Hilux 4x4', 'vehicle', 'Véhicule tout-terrain pour missions terrain', 'VH-001', 'available', 50000.00, 1),
('Ordinateur Portable HP', 'equipment', 'Laptop HP EliteBook pour travail bureau', 'EQ-001', 'available', 5000.00, 1),
('Groupe Électrogène 10KVA', 'equipment', 'Générateur pour alimentation électrique', 'GE-001', 'available', 15000.00, 1),
('Projecteur LED', 'equipment', 'Projecteur pour présentations', 'PR-001', 'available', 2000.00, 1),
('Ciment (Sac 50kg)', 'material', 'Matériau de construction', 'MAT-001', 'available', 0, 1);
