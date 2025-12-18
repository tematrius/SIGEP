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

$pageTitle = 'Détails du Projet';

try {
    $pdo = getDbConnection();
    
    // Récupérer le projet avec progression calculée
    $stmt = $pdo->prepare("
        SELECT p.*, l.name as location_name, 
               u1.full_name as creator_name, u2.full_name as updater_name,
               COALESCE((SELECT AVG(progress) FROM tasks WHERE project_id = p.id), 0) as calculated_progress
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u1 ON p.created_by = u1.id
        LEFT JOIN users u2 ON p.updated_by = u2.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        setFlashMessage('error', 'Projet non trouvé');
        redirect('projects.php');
    }
    
    // Récupérer les tâches du projet
    $stmtTasks = $pdo->prepare("
        SELECT t.*, u.full_name as assigned_user_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.project_id = ?
        ORDER BY t.priority DESC, t.end_date ASC
    ");
    $stmtTasks->execute([$id]);
    $tasks = $stmtTasks->fetchAll();
    
    // Statistiques des tâches
    $taskStats = [
        'total' => count($tasks),
        'non_demarree' => 0,
        'en_cours' => 0,
        'terminee' => 0,
        'en_retard' => 0
    ];
    
    foreach ($tasks as $task) {
        $taskStats[$task['status']]++;
        if ($task['end_date'] && $task['status'] !== 'terminee' && strtotime($task['end_date']) < time()) {
            $taskStats['en_retard']++;
        }
    }
    
    // Récupérer les risques
    $stmtRisks = $pdo->prepare("
        SELECT r.*, u.full_name as responsible_name
        FROM risks r
        LEFT JOIN users u ON r.responsible_user_id = u.id
        WHERE r.project_id = ?
        ORDER BY r.risk_score DESC
    ");
    $stmtRisks->execute([$id]);
    $risks = $stmtRisks->fetchAll();
    
    // Récupérer le budget
    $stmtBudget = $pdo->prepare("
        SELECT * FROM budget_items WHERE project_id = ?
    ");
    $stmtBudget->execute([$id]);
    $budget_items = $stmtBudget->fetchAll();
    
    $total_planned = array_sum(array_column($budget_items, 'planned_amount'));
    $total_spent = array_sum(array_column($budget_items, 'spent_amount'));
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du projet');
    redirect('projects.php');
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-folder-open"></i> <?php echo e($project['title']); ?></h2>
    <div>
        <a href="task_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Nouvelle Tâche
        </a>
        <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Modifier
        </a>
        <a href="projects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="row">
    <!-- Informations du projet -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Informations du Projet
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
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
                    <div class="col-md-6">
                        <strong>Localisation:</strong> <?php echo e($project['location_name'] ?? 'Non définie'); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Description:</strong>
                        <p class="mt-2"><?php echo nl2br(e($project['description'])); ?></p>
                    </div>
                </div>
                
                <?php if ($project['context']): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Contexte:</strong>
                        <p class="mt-2"><?php echo nl2br(e($project['context'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Date de début:</strong> <?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'Non définie'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Date de fin:</strong> <?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'Non définie'; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Budget estimé:</strong> <?php echo $project['budget_estimated'] ? number_format($project['budget_estimated'], 0, ',', ' ') . ' FC' : 'Non défini'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Budget validé:</strong> <?php echo $project['budget_validated'] ? number_format($project['budget_validated'], 0, ',', ' ') . ' FC' : 'Non défini'; ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Progression:</strong>
                        <?php $progress = round($project['calculated_progress']); ?>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $progress < 30 ? 'danger' : ($progress < 70 ? 'warning' : 'success'); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $progress; ?>%">
                                <?php echo $progress; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> Créé par <?php echo e($project['creator_name']); ?>
                            <br>
                            <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($project['created_at'])); ?>
                        </small>
                    </div>
                    <?php if ($project['updated_at'] != $project['created_at']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-edit"></i> Modifié le <?php echo date('d/m/Y H:i', strtotime($project['updated_at'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="col-lg-4 mb-4">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> Statistiques des Tâches
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Total:</span>
                    <strong><?php echo $taskStats['total']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Non démarrées:</span>
                    <span class="badge bg-secondary"><?php echo $taskStats['non_demarree']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>En cours:</span>
                    <span class="badge bg-primary"><?php echo $taskStats['en_cours']; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Terminées:</span>
                    <span class="badge bg-success"><?php echo $taskStats['terminee']; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>En retard:</span>
                    <span class="badge bg-danger"><?php echo $taskStats['en_retard']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-dollar-sign"></i> Budget
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Planifié:</span>
                    <strong><?php echo number_format($total_planned, 0, ',', ' '); ?> FC</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Dépensé:</span>
                    <strong class="text-danger"><?php echo number_format($total_spent, 0, ',', ' '); ?> FC</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Restant:</span>
                    <strong class="text-success"><?php echo number_format($total_planned - $total_spent, 0, ',', ' '); ?> FC</strong>
                </div>
                <?php if ($total_planned > 0): ?>
                <div class="progress mt-3" style="height: 20px;">
                    <div class="progress-bar bg-info" 
                         role="progressbar" 
                         style="width: <?php echo min(100, ($total_spent / $total_planned) * 100); ?>%">
                        <?php echo round(($total_spent / $total_planned) * 100, 1); ?>%
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tâches -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tasks"></i> Tâches du Projet</span>
        <a href="task_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-plus"></i> Ajouter
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <p class="text-center text-muted py-3">Aucune tâche pour ce projet</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tâche</th>
                            <th>Assigné à</th>
                            <th>Échéance</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Progression</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?php echo e($task['title']); ?></td>
                                <td><?php echo e($task['assigned_user_name'] ?? 'Non assignée'); ?></td>
                                <td>
                                    <?php 
                                    if ($task['end_date']) {
                                        echo date('d/m/Y', strtotime($task['end_date']));
                                        if ($task['status'] !== 'terminee' && strtotime($task['end_date']) < time()) {
                                            echo '<br><span class="badge bg-danger">En retard</span>';
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $priorityColors = ['faible' => 'info', 'moyenne' => 'warning', 'haute' => 'danger', 'critique' => 'danger'];
                                    ?>
                                    <span class="badge bg-<?php echo $priorityColors[$task['priority']]; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'non_demarree' => 'secondary',
                                        'en_cours' => 'primary',
                                        'en_pause' => 'warning',
                                        'terminee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $statusLabels = [
                                        'non_demarree' => 'Non démarrée',
                                        'en_cours' => 'En cours',
                                        'en_pause' => 'En pause',
                                        'terminee' => 'Terminée',
                                        'annulee' => 'Annulée'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $statusColors[$task['status']]; ?>">
                                        <?php echo $statusLabels[$task['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px; min-width: 80px;">
                                        <div class="progress-bar bg-<?php echo $task['progress'] < 30 ? 'danger' : ($task['progress'] < 70 ? 'warning' : 'success'); ?>" 
                                             style="width: <?php echo $task['progress']; ?>%">
                                            <?php echo $task['progress']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="task_details.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Risques -->
<?php if (!empty($risks)): ?>
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-exclamation-triangle"></i> Risques Identifiés
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Catégorie</th>
                        <th>Score</th>
                        <th>Statut</th>
                        <th>Responsable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($risks as $risk): ?>
                        <tr>
                            <td><?php echo e($risk['description']); ?></td>
                            <td><?php echo ucfirst($risk['category']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $risk['risk_score'] >= 15 ? 'danger' : ($risk['risk_score'] >= 10 ? 'warning' : 'info'); ?>">
                                    <?php echo $risk['risk_score']; ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $risk['status'])); ?></td>
                            <td><?php echo e($risk['responsible_name'] ?? 'Non assigné'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
