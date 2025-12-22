-- Table pour logger les imports de données
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_type ENUM('projects', 'tasks') NOT NULL COMMENT 'Type de données importées',
    filename VARCHAR(255) NOT NULL COMMENT 'Nom du fichier importé',
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing' COMMENT 'Statut de l''import',
    total_rows INT DEFAULT 0 COMMENT 'Nombre total de lignes',
    success_count INT DEFAULT 0 COMMENT 'Nombre de lignes importées avec succès',
    error_count INT DEFAULT 0 COMMENT 'Nombre d''erreurs',
    errors TEXT COMMENT 'Détails des erreurs (JSON)',
    imported_by INT NOT NULL COMMENT 'Utilisateur ayant effectué l''import',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_import_type (import_type),
    INDEX idx_status (status),
    INDEX idx_imported_by (imported_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commentaire sur la table
ALTER TABLE import_logs COMMENT = 'Historique des imports de données en masse';
