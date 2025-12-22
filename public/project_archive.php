<?php
/**
 * SIGEP - Archivage de projet
 * Version: 1.8
 * Description: Permet d'archiver un projet terminé ou annulé
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
    $_SESSION['error'] = "Vous n'avez pas les permissions nécessaires pour archiver des projets.";
    header('Location: projects.php');
    exit;
}

// Récupérer l'ID du projet
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id === 0) {
    $_SESSION['error'] = "ID de projet invalide.";
    header('Location: projects.php');
    exit;
}

// Traitement de l'archivage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    try {
        // Vérifier que le projet existe et n'est pas déjà archivé
        $stmt = $pdo->prepare("
            SELECT id, name, status, archived 
            FROM projects 
            WHERE id = ?
        ");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            throw new Exception("Projet introuvable.");
        }
        
        if ($project['archived']) {
            throw new Exception("Ce projet est déjà archivé.");
        }
        
        // Vérifier que le projet est terminé ou annulé
        if ($project['status'] !== 'completed' && $project['status'] !== 'cancelled') {
            throw new Exception("Seuls les projets terminés ou annulés peuvent être archivés.");
        }
        
        // Archiver le projet
        $stmt = $pdo->prepare("
            UPDATE projects
            SET archived = TRUE,
                archived_at = NOW(),
                archived_by = ?,
                archive_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $reason, $project_id]);
        
        // Créer une notification pour tous les utilisateurs concernés
        createNotification(
            $_SESSION['user_id'],
            'archive',
            "Le projet \"{$project['name']}\" a été archivé",
            'project',
            $project_id
        );
        
        // Log de l'action
        error_log("Projet archivé - ID: {$project_id} - Par: {$_SESSION['user_id']} - Raison: {$reason}");
        
        $_SESSION['success'] = "Le projet a été archivé avec succès.";
        header('Location: projects.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'archivage : " . $e->getMessage();
        header('Location: project_details.php?id=' . $project_id);
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
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) AS task_count,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status != 'completed') AS incomplete_tasks
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        throw new Exception("Projet introuvable.");
    }
    
    if ($project['archived']) {
        throw new Exception("Ce projet est déjà archivé.");
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: projects.php');
    exit;
}

// Démarrer le buffer de sortie
ob_start();
$pageTitle = "Archiver le projet - " . htmlspecialchars($project['name']);
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
        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
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
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .btn-cancel {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-archive"></i> Archiver le projet
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- Message d'avertissement -->
                        <div class="warning-box">
                            <h5 class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i> Attention
                            </h5>
                            <p class="mb-0">
                                L'archivage d'un projet le masquera de la liste principale des projets actifs. 
                                Le projet et toutes ses données (tâches, documents, commentaires) resteront 
                                accessibles dans la section "Archives" et pourront être restaurés à tout moment.
                            </p>
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
                                    <?php echo $project['task_count']; ?> tâche(s)
                                    <?php if ($project['incomplete_tasks'] > 0): ?>
                                        <span class="text-warning ms-2">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?php echo $project['incomplete_tasks']; ?> non terminée(s)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($project['incomplete_tasks'] > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Remarque:</strong> Ce projet contient encore des tâches non terminées. 
                            Assurez-vous que cela est intentionnel avant de l'archiver.
                        </div>
                        <?php endif; ?>
                        
                        <!-- Formulaire d'archivage -->
                        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir archiver ce projet ?');">
                            <div class="mb-3">
                                <label for="reason" class="form-label">
                                    Raison de l'archivage <span class="text-muted">(optionnel)</span>
                                </label>
                                <textarea 
                                    class="form-control" 
                                    id="reason" 
                                    name="reason" 
                                    rows="4"
                                    placeholder="Ex: Projet terminé avec succès, tous les livrables validés..."
                                ></textarea>
                                <div class="form-text">
                                    Expliquez brièvement pourquoi ce projet est archivé.
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-secondary btn-cancel">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-archive"></i> Archiver le projet
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
