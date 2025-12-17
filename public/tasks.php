<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Tâches';

try {
    $pdo = getDbConnection();
    
    // Récupérer toutes les tâches avec filtres
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $project_id = $_GET['project_id'] ?? '';
    $assigned_to = $_GET['assigned_to'] ?? '';
    
    $sql = "SELECT t.*, p.title as project_title, u.full_name as assigned_user_name
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    
    if (!empty($priority)) {
        $sql .= " AND t.priority = ?";
        $params[] = $priority;
    }
    
    if (!empty($project_id)) {
        $sql .= " AND t.project_id = ?";
        $params[] = $project_id;
    }
    
    if (!empty($assigned_to)) {
        $sql .= " AND t.assigned_to = ?";
        $params[] = $assigned_to;
    }
    
    $sql .= " ORDER BY t.end_date ASC, t.priority DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Récupérer les projets pour le filtre
    $stmtProjects = $pdo->query("SELECT id, title FROM projects ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Récupérer les utilisateurs pour le filtre
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des tâches');
    $tasks = [];
    $projects = [];
    $users = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tasks"></i> Gestion des Tâches</h2>
    <a href="task_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvelle Tâche
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" name="search" class="form-control" placeholder="Titre ou description..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Projet</label>
                <select name="project_id" class="form-select">
                    <option value="">Tous les projets</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj['id']; ?>" <?php echo $project_id == $proj['id'] ? 'selected' : ''; ?>>
                            <?php echo e($proj['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="non_demarree" <?php echo $status === 'non_demarree' ? 'selected' : ''; ?>>Non démarrée</option>
                    <option value="en_cours" <?php echo $status === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="en_pause" <?php echo $status === 'en_pause' ? 'selected' : ''; ?>>En pause</option>
                    <option value="terminee" <?php echo $status === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                    <option value="annulee" <?php echo $status === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priorité</label>
                <select name="priority" class="form-select">
                    <option value="">Toutes</option>
                    <option value="faible" <?php echo $priority === 'faible' ? 'selected' : ''; ?>>Faible</option>
                    <option value="moyenne" <?php echo $priority === 'moyenne' ? 'selected' : ''; ?>>Moyenne</option>
                    <option value="haute" <?php echo $priority === 'haute' ? 'selected' : ''; ?>>Haute</option>
                    <option value="critique" <?php echo $priority === 'critique' ? 'selected' : ''; ?>>Critique</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Assigné à</label>
                <select name="assigned_to" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $assigned_to == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo e($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des tâches -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Tâche</th>
                        <th>Projet</th>
                        <th>Assigné à</th>
                        <th>Dates</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                Aucune tâche trouvée
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($task['title']); ?></strong>
                                    <?php if ($task['parent_task_id']): ?>
                                        <br><small class="text-muted"><i class="fas fa-level-up-alt fa-rotate-90"></i> Sous-tâche</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="project_details.php?id=<?php echo $task['project_id']; ?>">
                                        <?php echo e($task['project_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($task['assigned_user_name'] ?? 'Non assignée'); ?></td>
                                <td>
                                    <small>
                                        <?php 
                                        $start = $task['start_date'] ? date('d/m/Y', strtotime($task['start_date'])) : 'N/A';
                                        $end = $task['end_date'] ? date('d/m/Y', strtotime($task['end_date'])) : 'N/A';
                                        echo "$start<br>$end";
                                        
                                        // Vérifier si en retard
                                        if ($task['end_date'] && $task['status'] !== 'terminee' && strtotime($task['end_date']) < time()) {
                                            echo '<br><span class="badge bg-danger">En retard</span>';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $priorityColors = [
                                        'faible' => 'info',
                                        'moyenne' => 'warning',
                                        'haute' => 'danger',
                                        'critique' => 'danger'
                                    ];
                                    $priorityLabels = [
                                        'faible' => 'Faible',
                                        'moyenne' => 'Moyenne',
                                        'haute' => 'Haute',
                                        'critique' => 'Critique'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $priorityColors[$task['priority']]; ?>">
                                        <?php echo $priorityLabels[$task['priority']]; ?>
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
                                    <div class="progress" style="height: 20px; min-width: 100px;">
                                        <div class="progress-bar bg-<?php echo $task['progress'] < 30 ? 'danger' : ($task['progress'] < 70 ? 'warning' : 'success'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $task['progress']; ?>%">
                                            <?php echo $task['progress']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="task_details.php?id=<?php echo $task['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="task_edit.php?id=<?php echo $task['id']; ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="task_delete.php?id=<?php echo $task['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           data-confirm-delete
                                           title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
