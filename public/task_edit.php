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

$pageTitle = 'Modifier la Tâche';

try {
    $pdo = getDbConnection();
    
    // Récupérer la tâche
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Tâche non trouvée');
        redirect('tasks.php');
    }
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Récupérer les utilisateurs
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
    // Récupérer les tâches parentes possibles
    $stmtTasks = $pdo->prepare("SELECT id, title FROM tasks WHERE project_id = ? AND parent_task_id IS NULL AND id != ? ORDER BY title");
    $stmtTasks->execute([$task['project_id'], $id]);
    $parent_tasks = $stmtTasks->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement de la tâche');
    redirect('tasks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_id = $_POST['project_id'] ?? null;
    $assigned_to = $_POST['assigned_to'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $priority = $_POST['priority'] ?? 'moyenne';
    $status = $_POST['status'] ?? 'non_demarree';
    $progress = $_POST['progress'] ?? 0;
    $parent_task_id = $_POST['parent_task_id'] ?? null;
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Le titre est obligatoire';
    }
    
    if (empty($project_id)) {
        $errors[] = 'Le projet est obligatoire';
    }
    
    if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
        $errors[] = 'La date de fin doit être postérieure à la date de début';
    }
    
    if ($progress < 0 || $progress > 100) {
        $errors[] = 'La progression doit être entre 0 et 100';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE tasks SET 
                    project_id = ?, title = ?, description = ?, assigned_to = ?,
                    start_date = ?, end_date = ?, priority = ?, status = ?, 
                    progress = ?, parent_task_id = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $project_id,
                $title,
                $description,
                $assigned_to ?: null,
                $start_date ?: null,
                $end_date ?: null,
                $priority,
                $status,
                $progress,
                $parent_task_id ?: null,
                $id
            ]);
            
            // Notification si assignation changée
            if ($assigned_to && $assigned_to != $task['assigned_to']) {
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, related_task_id) 
                    VALUES (?, 'task_updated', 'Tâche mise à jour', ?, ?)
                ");
                $notifStmt->execute([
                    $assigned_to,
                    "La tâche '$title' vous a été assignée",
                    $id
                ]);
            }
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'task', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification de la tâche: $title"
            ]);
            
            setFlashMessage('success', 'Tâche modifiée avec succès');
            redirect('task_details.php?id=' . $id);
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la modification de la tâche';
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

ob_start();
?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> Modifier la Tâche</h2>
            <a href="task_details.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="project_id" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">Sélectionner un projet...</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo $task['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Titre de la tâche <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo e($task['title']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($task['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="parent_task_id" class="form-label">Tâche parente (optionnel)</label>
                            <select class="form-select" id="parent_task_id" name="parent_task_id">
                                <option value="">Aucune (tâche principale)</option>
                                <?php foreach ($parent_tasks as $ptask): ?>
                                    <option value="<?php echo $ptask['id']; ?>" 
                                            <?php echo $task['parent_task_id'] == $ptask['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($ptask['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="assigned_to" class="form-label">Assigné à</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">Non assignée</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $task['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $task['start_date']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="end_date" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $task['end_date']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="priority" class="form-label">Priorité</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="faible" <?php echo $task['priority'] === 'faible' ? 'selected' : ''; ?>>Faible</option>
                                <option value="moyenne" <?php echo $task['priority'] === 'moyenne' ? 'selected' : ''; ?>>Moyenne</option>
                                <option value="haute" <?php echo $task['priority'] === 'haute' ? 'selected' : ''; ?>>Haute</option>
                                <option value="critique" <?php echo $task['priority'] === 'critique' ? 'selected' : ''; ?>>Critique</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="non_demarree" <?php echo $task['status'] === 'non_demarree' ? 'selected' : ''; ?>>Non démarrée</option>
                                <option value="en_cours" <?php echo $task['status'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="en_pause" <?php echo $task['status'] === 'en_pause' ? 'selected' : ''; ?>>En pause</option>
                                <option value="terminee" <?php echo $task['status'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annulee" <?php echo $task['status'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="progress" class="form-label">Progression (%)</label>
                            <input type="number" class="form-control" id="progress" name="progress" min="0" max="100"
                                   value="<?php echo $task['progress']; ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="task_details.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
