<?php
/**
 * SIGEP - Restauration de projet archivé
 * Version: 1.8
 * Description: Permet de restaurer un projet précédemment archivé
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier les permissions
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'gestionnaire') {
    $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour restaurer des projets.";
    header('Location: archives.php');
    exit;
}

// Récupérer l'ID du projet
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id === 0) {
    $_SESSION['error'] = "ID de projet invalide.";
    header('Location: archives.php');
    exit;
}

// Traitement de la restauration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérifier que le projet existe et est archivé
        $stmt = $pdo->prepare("
            SELECT id, name, archived 
            FROM projects 
            WHERE id = ?
        ");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            throw new Exception("Projet introuvable.");
        }
        
        if (!$project['archived']) {
            throw new Exception("Ce projet n'est pas archivé.");
        }
        
        // Restaurer le projet
        $stmt = $pdo->prepare("
            UPDATE projects
            SET archived = FALSE,
                archived_at = NULL,
                archived_by = NULL,
                archive_reason = NULL
            WHERE id = ?
        ");
        $stmt->execute([$project_id]);
        
        // Créer une notification
        createNotification(
            $_SESSION['user_id'],
            'restore',
            "Le projet \"{$project['name']}\" a été restauré depuis les archives",
            'project',
            $project_id
        );
        
        // Log de l'action
        error_log("Projet restauré - ID: {$project_id} - Par: {$_SESSION['user_id']}");
        
        $_SESSION['success'] = "Le projet a été restauré avec succès.";
        header('Location: project_details.php?id=' . $project_id);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la restauration : " . $e->getMessage();
        header('Location: archives.php');
        exit;
    }
}

// Récupérer les détails du projet pour confirmation
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            l.name AS location_name,
            u.full_name AS manager_name,
            archived_user.full_name AS archived_by_name,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) AS task_count,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') AS completed_tasks
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN users archived_user ON p.archived_by = archived_user.id
        WHERE p.id = ? AND p.archived = TRUE
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        throw new Exception("Projet introuvable ou non archivé.");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: archives.php');
    exit;
}

// Démarrer le buffer de sortie
ob_start();
$pageTitle = "Restaurer le projet - " . htmlspecialchars($project['name']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SIGEP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .info-box {
            background-color: #d1ecf1;
            border: 2px solid #0dcaf0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .project-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .archive-info {
            background-color: #fff3cd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-undo"></i> Restaurer le projet
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Message d'information -->
                        <div class="info-box">
                            <h5 class="text-info">
                                <i class="fas fa-info-circle"></i> Restauration
                            </h5>
                            <p class="mb-0">
                                La restauration d'un projet le rendra à nouveau visible dans la liste des projets actifs. 
                                Toutes les données du projet (tâches, documents, commentaires) seront accessibles normalement.
                            </p>
                        </div>
                        
                        <!-- Informations d'archivage -->
                        <div class="archive-info">
                            <h6 class="mb-2"><i class="fas fa-history"></i> Informations d'archivage</h6>
                            <p class="mb-1">
                                <strong>Archivé le:</strong> 
                                <?php echo date('d/m/Y à H:i', strtotime($project['archived_at'])); ?>
                            </p>
                            <p class="mb-1">
                                <strong>Archivé par:</strong> 
                                <?php echo htmlspecialchars($project['archived_by_name'] ?? 'Inconnu'); ?>
                            </p>
                            <?php if (!empty($project['archive_reason'])): ?>
                            <p class="mb-0">
                                <strong>Raison:</strong> 
                                <?php echo htmlspecialchars($project['archive_reason']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Informations du projet -->
                        <div class="project-info">
                            <h5 class="mb-3">Informations du projet</h5>
                            
                            <div class="info-row">
                                <span class="info-label">Nom:</span>
                                <span><?php echo htmlspecialchars($project['name']); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Statut:</span>
                                <span>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'secondary',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $status_labels = [
                                        'pending' => 'En attente',
                                        'in_progress' => 'En cours',
                                        'completed' => 'Terminé',
                                        'cancelled' => 'Annulé'
                                    ];
                                    $color = $status_colors[$project['status']] ?? 'secondary';
                                    $label = $status_labels[$project['status']] ?? $project['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> status-badge"><?php echo $label; ?></span>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Localisation:</span>
                                <span><?php echo htmlspecialchars($project['location_name'] ?? 'Non spécifiée'); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Gestionnaire:</span>
                                <span><?php echo htmlspecialchars($project['manager_name'] ?? 'Non assigné'); ?></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Budget:</span>
                                <span><?php echo number_format($project['budget'], 2, ',', ' '); ?> USD</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Période:</span>
                                <span>
                                    <?php 
                                    echo date('d/m/Y', strtotime($project['start_date']));
                                    echo ' - ';
                                    echo date('d/m/Y', strtotime($project['end_date']));
                                    ?>
                                </span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">Tâches:</span>
                                <span>
                                    <?php echo $project['completed_tasks']; ?> / <?php echo $project['task_count']; ?> terminée(s)
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($project['description'])): ?>
                        <div class="mb-3">
                            <h6>Description</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Formulaire de restauration -->
                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir restaurer ce projet ?');">
                            <div class="d-flex justify-content-between mt-4">
                                <a href="archives.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-undo"></i> Restaurer le projet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
