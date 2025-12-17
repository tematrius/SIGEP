<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Tâche non trouvée');
    redirect('tasks.php');
}

$pageTitle = 'Détails de la Tâche';

try {
    $pdo = getDbConnection();
    
    // Récupérer la tâche
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title, u.full_name as assigned_user_name,
               pt.title as parent_task_title
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN tasks pt ON t.parent_task_id = pt.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Tâche non trouvée');
        redirect('tasks.php');
    }
    
    // Récupérer les sous-tâches
    $stmtSubtasks = $pdo->prepare("
        SELECT t.*, u.full_name as assigned_user_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.parent_task_id = ?
        ORDER BY t.priority DESC, t.end_date ASC
    ");
    $stmtSubtasks->execute([$id]);
    $subtasks = $stmtSubtasks->fetchAll();
    
    // Récupérer les commentaires
    $stmtComments = $pdo->prepare("
        SELECT c.*, u.full_name as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.task_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmtComments->execute([$id]);
    $comments = $stmtComments->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement de la tâche');
    redirect('tasks.php');
}

// Traitement du commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $comment]);
            setFlashMessage('success', 'Commentaire ajouté');
            redirect('task_details.php?id=' . $id);
        } catch (PDOException $e) {
            setFlashMessage('error', 'Erreur lors de l\'ajout du commentaire');
        }
    }
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tasks"></i> <?php echo e($task['title']); ?></h2>
    <div>
        <a href="task_edit.php?id=<?php echo $task['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Modifier
        </a>
        <a href="project_details.php?id=<?php echo $task['project_id']; ?>" class="btn btn-info">
            <i class="fas fa-folder-open"></i> Voir le projet
        </a>
        <a href="tasks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="row">
    <!-- Informations de la tâche -->
    <div class="col-lg-8 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Informations de la Tâche
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Projet:</strong>
                        <a href="project_details.php?id=<?php echo $task['project_id']; ?>">
                            <?php echo e($task['project_title']); ?>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <strong>Statut:</strong>
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
                        <span class="badge bg-<?php echo $statusColors[$task['status']]; ?> ms-2">
                            <?php echo $statusLabels[$task['status']]; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($task['description']): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Description:</strong>
                        <p class="mt-2"><?php echo nl2br(e($task['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Assigné à:</strong> <?php echo e($task['assigned_user_name'] ?? 'Non assignée'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Priorité:</strong>
                        <?php
                        $priorityColors = ['faible' => 'info', 'moyenne' => 'warning', 'haute' => 'danger', 'critique' => 'danger'];
                        ?>
                        <span class="badge bg-<?php echo $priorityColors[$task['priority']]; ?> ms-2">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($task['parent_task_title']): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Tâche parente:</strong> <?php echo e($task['parent_task_title']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Date de début:</strong> <?php echo $task['start_date'] ? date('d/m/Y', strtotime($task['start_date'])) : 'Non définie'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Date de fin:</strong> 
                        <?php 
                        if ($task['end_date']) {
                            echo date('d/m/Y', strtotime($task['end_date']));
                            if ($task['status'] !== 'terminee' && strtotime($task['end_date']) < time()) {
                                echo ' <span class="badge bg-danger">En retard</span>';
                            }
                        } else {
                            echo 'Non définie';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Progression:</strong>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $task['progress'] < 30 ? 'danger' : ($task['progress'] < 70 ? 'warning' : 'success'); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $task['progress']; ?>%">
                                <?php echo $task['progress']; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Créée le <?php echo date('d/m/Y H:i', strtotime($task['created_at'])); ?>
                            <?php if ($task['updated_at'] != $task['created_at']): ?>
                                | Modifiée le <?php echo date('d/m/Y H:i', strtotime($task['updated_at'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sous-tâches -->
        <?php if (!empty($subtasks)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list"></i> Sous-tâches
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($subtasks as $subtask): ?>
                        <a href="task_details.php?id=<?php echo $subtask['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo e($subtask['title']); ?></h6>
                                    <small class="text-muted">
                                        Assigné à: <?php echo e($subtask['assigned_user_name'] ?? 'Non assignée'); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $statusColors[$subtask['status']]; ?>">
                                        <?php echo $statusLabels[$subtask['status']]; ?>
                                    </span>
                                    <div class="progress mt-2" style="width: 100px; height: 20px;">
                                        <div class="progress-bar" style="width: <?php echo $subtask['progress']; ?>%">
                                            <?php echo $subtask['progress']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Commentaires -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-comments"></i> Commentaires
            </div>
            <div class="card-body">
                <!-- Formulaire d'ajout de commentaire -->
                <form method="POST" action="" class="mb-4">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" placeholder="Ajouter un commentaire..." required></textarea>
                    </div>
                    <button type="submit" name="add_comment" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Ajouter un commentaire
                    </button>
                </form>
                
                <!-- Liste des commentaires -->
                <?php if (empty($comments)): ?>
                    <p class="text-muted text-center">Aucun commentaire pour le moment</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo e($comment['user_name']); ?></strong>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></small>
                            </div>
                            <p class="mt-2 mb-0"><?php echo nl2br(e($comment['comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Actions Rapides
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="task_edit.php?id=<?php echo $task['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Modifier la tâche
                    </a>
                    <?php if (empty($subtasks)): ?>
                        <a href="task_create.php?project_id=<?php echo $task['project_id']; ?>&parent_task_id=<?php echo $task['id']; ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Ajouter une sous-tâche
                        </a>
                    <?php endif; ?>
                    <a href="project_details.php?id=<?php echo $task['project_id']; ?>" class="btn btn-info">
                        <i class="fas fa-folder-open"></i> Voir le projet
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
