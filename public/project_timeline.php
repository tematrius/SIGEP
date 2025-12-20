<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Projet non trouvé');
    redirect('projects.php');
}

$pageTitle = 'Timeline du Projet';

try {
    $pdo = getDbConnection();
    
    // Récupérer le projet
    $stmt = $pdo->prepare("
        SELECT p.*, l.name as location_name, u.full_name as creator_name
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        setFlashMessage('error', 'Projet non trouvé');
        redirect('projects.php');
    }
    
    // Récupérer tous les événements de la timeline
    $timeline = [];
    
    // Création du projet
    $timeline[] = [
        'date' => $project['created_at'],
        'type' => 'project_created',
        'title' => 'Projet créé',
        'description' => 'Le projet a été créé par ' . $project['creator_name'],
        'icon' => 'fa-plus-circle',
        'color' => 'primary'
    ];
    
    // Tâches créées
    $stmt = $pdo->prepare("
        SELECT t.*, u1.full_name as creator_name, u2.full_name as assigned_name
        FROM tasks t
        LEFT JOIN users u1 ON t.created_by = u1.id
        LEFT JOIN users u2 ON t.assigned_to = u2.id
        WHERE t.project_id = ?
        ORDER BY t.created_at
    ");
    $stmt->execute([$id]);
    $tasks = $stmt->fetchAll();
    
    foreach ($tasks as $task) {
        $timeline[] = [
            'date' => $task['created_at'],
            'type' => 'task_created',
            'title' => 'Tâche créée',
            'description' => 'Tâche "' . $task['title'] . '" assignée à ' . ($task['assigned_name'] ?? 'Non assignée'),
            'icon' => 'fa-tasks',
            'color' => 'info',
            'link' => 'task_details.php?id=' . $task['id']
        ];
        
        // Tâche terminée
        if ($task['status'] === 'terminee') {
            $timeline[] = [
                'date' => $task['updated_at'],
                'type' => 'task_completed',
                'title' => 'Tâche terminée',
                'description' => 'Tâche "' . $task['title'] . '" marquée comme terminée',
                'icon' => 'fa-check-circle',
                'color' => 'success',
                'link' => 'task_details.php?id=' . $task['id']
            ];
        }
    }
    
    // Documents uploadés
    $stmt = $pdo->prepare("
        SELECT td.*, t.title as task_title, u.full_name as uploader_name
        FROM task_documents td
        JOIN tasks t ON td.task_id = t.id
        JOIN users u ON td.uploaded_by = u.id
        WHERE t.project_id = ?
        ORDER BY td.created_at
    ");
    $stmt->execute([$id]);
    $documents = $stmt->fetchAll();
    
    foreach ($documents as $doc) {
        $timeline[] = [
            'date' => $doc['created_at'],
            'type' => 'document_uploaded',
            'title' => 'Document uploadé',
            'description' => $doc['uploader_name'] . ' a ajouté "' . $doc['file_name'] . '" à la tâche "' . $doc['task_title'] . '"',
            'icon' => 'fa-file-upload',
            'color' => 'warning',
            'link' => 'task_details.php?id=' . $doc['task_id']
        ];
    }
    
    // Risques identifiés
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as identifier_name
        FROM risks r
        LEFT JOIN users u ON r.identified_by = u.id
        WHERE r.project_id = ?
        ORDER BY r.created_at
    ");
    $stmt->execute([$id]);
    $risks = $stmt->fetchAll();
    
    foreach ($risks as $risk) {
        $timeline[] = [
            'date' => $risk['created_at'],
            'type' => 'risk_identified',
            'title' => 'Risque identifié',
            'description' => 'Risque "' . $risk['title'] . '" identifié par ' . ($risk['identifier_name'] ?? 'N/A'),
            'icon' => 'fa-exclamation-triangle',
            'color' => 'danger',
            'link' => 'risks.php'
        ];
    }
    
    // Commentaires
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as user_name, t.title as task_title
        FROM comments c
        JOIN users u ON c.user_id = u.id
        JOIN tasks t ON c.task_id = t.id
        WHERE t.project_id = ?
        ORDER BY c.created_at
    ");
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();
    
    foreach ($comments as $comment) {
        $timeline[] = [
            'date' => $comment['created_at'],
            'type' => 'comment_added',
            'title' => 'Commentaire ajouté',
            'description' => $comment['user_name'] . ' a commenté sur "' . $comment['task_title'] . '"',
            'icon' => 'fa-comment',
            'color' => 'secondary',
            'link' => 'task_details.php?id=' . $comment['task_id']
        ];
    }
    
    // Jalons (Milestones)
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name as creator_name
        FROM milestones m
        JOIN users u ON m.created_by = u.id
        WHERE m.project_id = ?
        ORDER BY m.created_at
    ");
    $stmt->execute([$id]);
    $milestones = $stmt->fetchAll();
    
    foreach ($milestones as $milestone) {
        // Création du jalon
        $timeline[] = [
            'date' => $milestone['created_at'],
            'type' => 'milestone_created',
            'title' => 'Jalon créé',
            'description' => 'Jalon "' . $milestone['title'] . '" créé par ' . $milestone['creator_name'] . ' (Échéance: ' . date('d/m/Y', strtotime($milestone['due_date'])) . ')',
            'icon' => 'fa-flag-checkered',
            'color' => 'primary'
        ];
        
        // Complétion du jalon
        if ($milestone['status'] === 'completed' && $milestone['completion_date']) {
            $timeline[] = [
                'date' => $milestone['completion_date'] . ' ' . date('H:i:s', strtotime($milestone['updated_at'])),
                'type' => 'milestone_completed',
                'title' => 'Jalon complété',
                'description' => 'Jalon "' . $milestone['title'] . '" marqué comme complété',
                'icon' => 'fa-check-circle',
                'color' => 'success'
            ];
        }
    }
    
    // Changements de statut
    if ($project['updated_at'] != $project['created_at']) {
        $timeline[] = [
            'date' => $project['updated_at'],
            'type' => 'project_updated',
            'title' => 'Projet mis à jour',
            'description' => 'Les informations du projet ont été modifiées',
            'icon' => 'fa-edit',
            'color' => 'info'
        ];
    }
    
    // Trier par date décroissante
    usort($timeline, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement de la timeline');
    redirect('projects.php');
}

ob_start();
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-history"></i> Timeline - <?php echo e($project['title']); ?></h2>
            <div>
                <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour au projet
                </a>
            </div>
        </div>
        
        <!-- Informations du projet -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Statut:</strong>
                        <?php
                        $statusColors = [
                            'prevu' => 'secondary',
                            'en_cours' => 'primary',
                            'suspendu' => 'warning',
                            'termine' => 'success',
                            'annule' => 'danger'
                        ];
                        $statusLabels = [
                            'prevu' => 'Prévu',
                            'en_cours' => 'En cours',
                            'suspendu' => 'Suspendu',
                            'termine' => 'Terminé',
                            'annule' => 'Annulé'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $statusColors[$project['status']]; ?> ms-2">
                            <?php echo $statusLabels[$project['status']]; ?>
                        </span>
                    </div>
                    <div class="col-md-3">
                        <strong>Localisation:</strong> <?php echo e($project['location_name'] ?? 'N/A'); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Date début:</strong> <?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'N/A'; ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Date fin:</strong> <?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Timeline -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-stream"></i> Historique des Activités (<?php echo count($timeline); ?> événements)
            </div>
            <div class="card-body">
                <?php if (empty($timeline)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-4x text-muted mb-3"></i>
                        <p class="text-muted">Aucune activité enregistrée</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($timeline as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?php echo $event['color']; ?>">
                                    <i class="fas <?php echo $event['icon']; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <h6 class="mb-1"><?php echo e($event['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($event['date'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo e($event['description']); ?></p>
                                    <?php if (isset($event['link'])): ?>
                                        <a href="<?php echo $event['link']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> Voir détails
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    padding-left: 70px;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: 15px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    z-index: 1;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.timeline-header {
    margin-bottom: 10px;
}
</style>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
