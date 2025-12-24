-- Table pour le workflow de validation
CREATE TABLE IF NOT EXISTS validation_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('project', 'task', 'budget', 'document', 'resource') NOT NULL,
    entity_id INT NOT NULL,
    workflow_name VARCHAR(100) NOT NULL,
    current_step INT DEFAULT 1,
    total_steps INT NOT NULL,
    status ENUM('pending', 'in_review', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    initiated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (initiated_by) REFERENCES users(id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les étapes de validation
CREATE TABLE IF NOT EXISTS validation_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    step_number INT NOT NULL,
    step_name VARCHAR(100) NOT NULL,
    approver_id INT NOT NULL,
    approver_role ENUM('admin', 'gestionnaire', 'directeur', 'chef_projet', 'validateur') NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'skipped') NOT NULL DEFAULT 'pending',
    comments TEXT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES validation_workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id),
    INDEX idx_workflow (workflow_id),
    INDEX idx_status (status),
    INDEX idx_approver (approver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour l'historique des validations
CREATE TABLE IF NOT EXISTS validation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    step_id INT NOT NULL,
    action ENUM('submitted', 'approved', 'rejected', 'commented', 'cancelled') NOT NULL,
    user_id INT NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES validation_workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (step_id) REFERENCES validation_steps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_workflow (workflow_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
