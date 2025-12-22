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
    
    // Récupérer les jalons (milestones)
    $stmtMilestones = $pdo->prepare("
        SELECT m.*, u.full_name as created_by_name
        FROM milestones m
        JOIN users u ON m.created_by = u.id
        WHERE m.project_id = ?
        ORDER BY m.order_number ASC, m.due_date ASC
    ");
    $stmtMilestones->execute([$id]);
    $milestones = $stmtMilestones->fetchAll();
    
    // Récupérer les commentaires du projet
    $stmtComments = $pdo->prepare("
        SELECT pc.*, u.full_name as user_name, u.username
        FROM project_comments pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.project_id = ?
        ORDER BY pc.created_at DESC
    ");
    $stmtComments->execute([$id]);
    $project_comments = $stmtComments->fetchAll();
    
    // Récupérer tous les utilisateurs pour les mentions
    $stmtUsers = $pdo->prepare("SELECT id, username, full_name FROM users WHERE is_active = 1");
    $stmtUsers->execute();
    $all_users = $stmtUsers->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du projet');
    redirect('projects.php');
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-folder-open"></i> <?php echo e($project['title']); ?></h2>
    <div>
        <a href="project_gantt.php?id=<?php echo $project['id']; ?>" class="btn btn-primary">
            <i class="fas fa-chart-bar"></i> Gantt
        </a>
        <a href="project_timeline.php?id=<?php echo $project['id']; ?>" class="btn btn-info">
            <i class="fas fa-history"></i> Timeline
        </a>
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

<!-- Jalons du Projet (Milestones) -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-flag-checkered"></i> Jalons du Projet</span>
        <a href="milestone_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-success">
            <i class="fas fa-plus"></i> Ajouter un Jalon
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($milestones)): ?>
            <p class="text-center text-muted py-3">
                <i class="fas fa-flag-checkered fa-3x mb-3 d-block"></i>
                Aucun jalon défini pour ce projet
            </p>
        <?php else: ?>
            <div class="milestone-timeline">
                <?php foreach ($milestones as $index => $milestone): ?>
                    <?php
                    $statusClasses = [
                        'pending' => 'secondary',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'delayed' => 'danger'
                    ];
                    $statusLabels = [
                        'pending' => 'En attente',
                        'in_progress' => 'En cours',
                        'completed' => 'Complété',
                        'delayed' => 'En retard'
                    ];
                    $statusClass = $statusClasses[$milestone['status']];
                    $isOverdue = $milestone['status'] !== 'completed' && strtotime($milestone['due_date']) < time();
                    ?>
                    
                    <div class="milestone-item border-start border-<?php echo $statusClass; ?> border-3 ps-4 pb-4 position-relative">
                        <!-- Icône du jalon -->
                        <div class="milestone-icon position-absolute" style="left: -12px; top: 0;">
                            <span class="badge rounded-pill bg-<?php echo $statusClass; ?>" style="width: 24px; height: 24px; padding: 6px;">
                                <?php if ($milestone['status'] === 'completed'): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <?php echo $index + 1; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <!-- Contenu du jalon -->
                        <div class="milestone-content">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">
                                        <?php echo e($milestone['title']); ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="badge bg-danger ms-2">
                                                <i class="fas fa-exclamation-triangle"></i> En retard
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> 
                                        Échéance: <?php echo date('d/m/Y', strtotime($milestone['due_date'])); ?>
                                        <?php if ($milestone['completion_date']): ?>
                                            | <i class="fas fa-check-circle text-success"></i> 
                                            Complété le <?php echo date('d/m/Y', strtotime($milestone['completion_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$milestone['status']]; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($milestone['description']): ?>
                                <p class="text-muted mb-2"><?php echo nl2br(e($milestone['description'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($milestone['deliverables']): ?>
                                <div class="mt-2">
                                    <strong class="text-muted small">Livrables attendus:</strong>
                                    <div class="ms-3 small text-muted">
                                        <?php echo nl2br(e($milestone['deliverables'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-2">
                                <a href="milestone_edit.php?id=<?php echo $milestone['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

<!-- Commentaires du Projet -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-comments"></i> Commentaires et Discussions
    </div>
    <div class="card-body">
        <!-- Formulaire d'ajout de commentaire -->
        <div class="mb-4">
            <form id="commentForm" onsubmit="return submitComment(event);">
                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                
                <div class="mb-3">
                    <label for="comment" class="form-label">Ajouter un commentaire</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3" 
                              placeholder="Écrivez votre commentaire... Utilisez @username pour mentionner un utilisateur" 
                              required></textarea>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Mentionnez un utilisateur avec @username pour lui envoyer une notification
                    </small>
                </div>
                
                <!-- Suggestions de mentions -->
                <div id="mentionSuggestions" class="list-group position-absolute" style="display: none; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Publier le commentaire
                </button>
            </form>
        </div>
        
        <!-- Liste des commentaires -->
        <div id="commentsList">
            <?php if (empty($project_comments)): ?>
                <div class="text-center text-muted py-4" id="noCommentsMsg">
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <p>Aucun commentaire pour le moment. Soyez le premier à commenter!</p>
                </div>
            <?php else: ?>
                <?php foreach ($project_comments as $comment): ?>
                    <div class="comment-item border-bottom pb-3 mb-3" data-comment-id="<?php echo $comment['id']; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="avatar-circle me-2">
                                        <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo e($comment['user_name']); ?></strong>
                                        <small class="text-muted ms-2">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="comment-text">
                                    <?php 
                                    // Convertir les mentions @username en liens
                                    $comment_text = e($comment['comment']);
                                    $comment_text = preg_replace(
                                        '/@(\w+)/', 
                                        '<span class="mention">@$1</span>', 
                                        $comment_text
                                    );
                                    echo nl2br($comment_text); 
                                    ?>
                                </div>
                            </div>
                            <?php if ($comment['user_id'] == $_SESSION['user_id'] || hasPermission('manage_all_projects')): ?>
                                <button class="btn btn-sm btn-outline-danger ms-2" 
                                        onclick="deleteComment(<?php echo $comment['id']; ?>)"
                                        title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Variables globales
const users = <?php echo json_encode($all_users); ?>;
let mentionStart = -1;

// Gestion des mentions
document.getElementById('comment').addEventListener('input', function(e) {
    const textarea = e.target;
    const text = textarea.value;
    const cursorPos = textarea.selectionStart;
    
    // Chercher le @ avant le curseur
    const textBeforeCursor = text.substring(0, cursorPos);
    const lastAtPos = textBeforeCursor.lastIndexOf('@');
    
    if (lastAtPos !== -1) {
        const searchTerm = textBeforeCursor.substring(lastAtPos + 1).toLowerCase();
        
        // Vérifier qu'il n'y a pas d'espace après le @
        if (searchTerm.indexOf(' ') === -1) {
            mentionStart = lastAtPos;
            showMentionSuggestions(searchTerm, textarea);
            return;
        }
    }
    
    hideMentionSuggestions();
});

function showMentionSuggestions(searchTerm, textarea) {
    const suggestions = users.filter(user => 
        user.username.toLowerCase().includes(searchTerm) ||
        user.full_name.toLowerCase().includes(searchTerm)
    ).slice(0, 5);
    
    if (suggestions.length === 0) {
        hideMentionSuggestions();
        return;
    }
    
    const suggestionsDiv = document.getElementById('mentionSuggestions');
    suggestionsDiv.innerHTML = '';
    
    suggestions.forEach(user => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action';
        item.innerHTML = `<strong>@${user.username}</strong> - ${user.full_name}`;
        item.onclick = () => insertMention(user.username, textarea);
        suggestionsDiv.appendChild(item);
    });
    
    // Positionner les suggestions
    const rect = textarea.getBoundingClientRect();
    suggestionsDiv.style.top = (rect.bottom + 5) + 'px';
    suggestionsDiv.style.left = rect.left + 'px';
    suggestionsDiv.style.width = rect.width + 'px';
    suggestionsDiv.style.display = 'block';
}

function hideMentionSuggestions() {
    document.getElementById('mentionSuggestions').style.display = 'none';
}

function insertMention(username, textarea) {
    const text = textarea.value;
    const before = text.substring(0, mentionStart);
    const after = text.substring(textarea.selectionStart);
    
    textarea.value = before + '@' + username + ' ' + after;
    textarea.focus();
    
    const newCursorPos = before.length + username.length + 2;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    
    hideMentionSuggestions();
}

// Soumettre le commentaire
function submitComment(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = document.getElementById('submitBtn');
    const formData = new FormData(form);
    
    // Désactiver le bouton
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
    
    fetch('project_comment_add.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Ajouter le commentaire à la liste
            addCommentToList(data.comment);
            
            // Réinitialiser le formulaire
            form.reset();
            
            // Masquer le message "aucun commentaire"
            const noCommentsMsg = document.getElementById('noCommentsMsg');
            if (noCommentsMsg) {
                noCommentsMsg.remove();
            }
            
            // Afficher un message de succès
            showToast('Commentaire publié avec succès', 'success');
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur lors de l\'ajout du commentaire', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publier le commentaire';
    });
    
    return false;
}

function addCommentToList(comment) {
    const commentsList = document.getElementById('commentsList');
    
    // Créer l'élément commentaire
    const commentDiv = document.createElement('div');
    commentDiv.className = 'comment-item border-bottom pb-3 mb-3';
    commentDiv.setAttribute('data-comment-id', comment.id);
    
    // Convertir les mentions
    let commentText = comment.comment.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    commentText = commentText.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
    commentText = commentText.replace(/\n/g, '<br>');
    
    commentDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar-circle me-2">
                        ${comment.user_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <strong>${comment.user_name}</strong>
                        <small class="text-muted ms-2">
                            <i class="fas fa-clock"></i> ${comment.formatted_date}
                        </small>
                    </div>
                </div>
                <div class="comment-text">
                    ${commentText}
                </div>
            </div>
            <button class="btn btn-sm btn-outline-danger ms-2" 
                    onclick="deleteComment(${comment.id})"
                    title="Supprimer">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    // Insérer en haut de la liste
    commentsList.insertBefore(commentDiv, commentsList.firstChild);
}

function deleteComment(commentId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('comment_id', commentId);
    
    fetch('project_comment_delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Supprimer l'élément du DOM
            const commentEl = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (commentEl) {
                commentEl.remove();
            }
            
            // Si plus de commentaires, afficher le message
            const commentsList = document.getElementById('commentsList');
            if (commentsList.children.length === 0) {
                commentsList.innerHTML = `
                    <div class="text-center text-muted py-4" id="noCommentsMsg">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <p>Aucun commentaire pour le moment. Soyez le premier à commenter!</p>
                    </div>
                `;
            }
            
            showToast('Commentaire supprimé', 'success');
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur lors de la suppression', 'error');
    });
}

function showToast(message, type) {
    // Utiliser le système de flash messages existant ou créer une alerte
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Fermer les suggestions en cliquant ailleurs
document.addEventListener('click', function(e) {
    if (!e.target.closest('#comment') && !e.target.closest('#mentionSuggestions')) {
        hideMentionSuggestions();
    }
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Détails du Projet - ' . $project['title'];
include '../views/layouts/main.php';
?>
