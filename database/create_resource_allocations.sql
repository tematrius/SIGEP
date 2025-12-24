-- Table pour l'affectation des ressources aux projets (si elle n'existe pas)
CREATE TABLE IF NOT EXISTS resource_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    project_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    quantity INT DEFAULT 1,
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
