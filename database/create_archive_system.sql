-- ============================================================================
-- SIGEP - Système d'archivage des projets (v1.8)
-- Date: 23 décembre 2024
-- Description: Ajout des fonctionnalités d'archivage pour les projets
-- ============================================================================

-- Ajout des colonnes d'archivage à la table projects
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS archived BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS archived_by INT(11) NULL,
ADD COLUMN IF NOT EXISTS archive_reason TEXT NULL;

-- Ajout d'une clé étrangère pour l'utilisateur qui a archivé
ALTER TABLE projects
ADD CONSTRAINT fk_archived_by
FOREIGN KEY (archived_by) REFERENCES users(id)
ON DELETE SET NULL;

-- Index pour optimiser les requêtes sur les projets archivés
CREATE INDEX idx_archived ON projects(archived, archived_at);

-- Vue pour les projets actifs (non archivés)
CREATE OR REPLACE VIEW active_projects AS
SELECT * FROM projects
WHERE archived = FALSE;

-- Vue pour les projets archivés
CREATE OR REPLACE VIEW archived_projects AS
SELECT * FROM projects
WHERE archived = TRUE
ORDER BY archived_at DESC;

-- Procédure stockée pour archiver un projet
DELIMITER //
CREATE PROCEDURE archive_project(
    IN p_project_id INT,
    IN p_user_id INT,
    IN p_reason TEXT
)
BEGIN
    -- Vérifier que le projet existe et n'est pas déjà archivé
    IF EXISTS (SELECT 1 FROM projects WHERE id = p_project_id AND archived = FALSE) THEN
        -- Archiver le projet
        UPDATE projects
        SET archived = TRUE,
            archived_at = NOW(),
            archived_by = p_user_id,
            archive_reason = p_reason
        WHERE id = p_project_id;
        
        -- Créer une notification
        INSERT INTO notifications (user_id, type, message, entity_type, entity_id, created_at)
        SELECT 
            u.id,
            'archive',
            CONCAT('Le projet "', p.name, '" a été archivé'),
            'project',
            p_project_id,
            NOW()
        FROM users u
        CROSS JOIN projects p
        WHERE p.id = p_project_id
          AND u.is_active = TRUE;
        
        SELECT 'SUCCESS' AS status, 'Projet archivé avec succès' AS message;
    ELSE
        SELECT 'ERROR' AS status, 'Projet introuvable ou déjà archivé' AS message;
    END IF;
END //
DELIMITER ;

-- Procédure stockée pour restaurer un projet
DELIMITER //
CREATE PROCEDURE restore_project(
    IN p_project_id INT,
    IN p_user_id INT
)
BEGIN
    -- Vérifier que le projet existe et est archivé
    IF EXISTS (SELECT 1 FROM projects WHERE id = p_project_id AND archived = TRUE) THEN
        -- Restaurer le projet
        UPDATE projects
        SET archived = FALSE,
            archived_at = NULL,
            archived_by = NULL,
            archive_reason = NULL
        WHERE id = p_project_id;
        
        -- Créer une notification
        INSERT INTO notifications (user_id, type, message, entity_type, entity_id, created_at)
        SELECT 
            u.id,
            'restore',
            CONCAT('Le projet "', p.name, '" a été restauré'),
            'project',
            p_project_id,
            NOW()
        FROM users u
        CROSS JOIN projects p
        WHERE p.id = p_project_id
          AND u.is_active = TRUE;
        
        SELECT 'SUCCESS' AS status, 'Projet restauré avec succès' AS message;
    ELSE
        SELECT 'ERROR' AS status, 'Projet introuvable ou non archivé' AS message;
    END IF;
END //
DELIMITER ;

-- Fonction pour obtenir le nombre de projets archivés par utilisateur
DELIMITER //
CREATE FUNCTION get_archived_count_by_user(p_user_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE archived_count INT;
    
    SELECT COUNT(*)
    INTO archived_count
    FROM projects
    WHERE archived_by = p_user_id;
    
    RETURN archived_count;
END //
DELIMITER ;

-- Vue statistique des archives
CREATE OR REPLACE VIEW archive_statistics AS
SELECT 
    DATE_FORMAT(archived_at, '%Y-%m') AS archive_month,
    COUNT(*) AS total_archived,
    SUM(budget) AS total_budget,
    COUNT(DISTINCT archived_by) AS unique_archivers
FROM projects
WHERE archived = TRUE
GROUP BY DATE_FORMAT(archived_at, '%Y-%m')
ORDER BY archive_month DESC;

-- Commentaires sur les nouvelles colonnes
ALTER TABLE projects 
MODIFY COLUMN archived BOOLEAN DEFAULT FALSE COMMENT 'Indique si le projet est archivé',
MODIFY COLUMN archived_at TIMESTAMP NULL COMMENT 'Date et heure d''archivage',
MODIFY COLUMN archived_by INT(11) NULL COMMENT 'ID de l''utilisateur qui a archivé',
MODIFY COLUMN archive_reason TEXT NULL COMMENT 'Raison de l''archivage';

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================
