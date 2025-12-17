<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Créer une Tâche';

try {
    $pdo = getDbConnection();
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Récupérer les utilisateurs
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
    // Si un projet est spécifié dans l'URL
    $preselected_project = $_GET['project_id'] ?? '';
    
    // Récupérer les tâches du projet sélectionné pour les tâches parentes
    $parent_tasks = [];
    if (!empty($_POST['project_id']) || !empty($preselected_project)) {
        $project_id = $_POST['project_id'] ?? $preselected_project;
        $stmtTasks = $pdo->prepare("SELECT id, title FROM tasks WHERE project_id = ? AND parent_task_id IS NULL ORDER BY title");
        $stmtTasks->execute([$project_id]);
        $parent_tasks = $stmtTasks->fetchAll();
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
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
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    project_id, title, description, assigned_to, 
                    start_date, end_date, priority, status, parent_task_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $parent_task_id ?: null
            ]);
            
            $taskId = $pdo->lastInsertId();
            
            // Créer une notification pour l'utilisateur assigné
            if ($assigned_to) {
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, related_task_id) 
                    VALUES (?, 'task_assigned', 'Nouvelle tâche assignée', ?, ?)
                ");
                $notifStmt->execute([
                    $assigned_to,
                    "La tâche '$title' vous a été assignée",
                    $taskId
                ]);
            }
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'create', 'task', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $taskId,
                "Création de la tâche: $title"
            ]);
            
            setFlashMessage('success', 'Tâche créée avec succès');
            redirect('task_details.php?id=' . $taskId);
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la création de la tâche';
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
            <h2><i class="fas fa-plus-circle"></i> Créer une Nouvelle Tâche</h2>
            <a href="tasks.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="" id="taskForm">
                    <div class="row">
                        <!-- Informations de base -->
                        <div class="col-md-12">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Informations de base</h5>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="project_id" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="project_id" name="project_id" required onchange="this.form.submit()">
                                <option value="">Sélectionner un projet...</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['id']) || $preselected_project == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Titre de la tâche <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo e($_POST['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="parent_task_id" class="form-label">Tâche parente (optionnel)</label>
                            <select class="form-select" id="parent_task_id" name="parent_task_id">
                                <option value="">Aucune (tâche principale)</option>
                                <?php foreach ($parent_tasks as $ptask): ?>
                                    <option value="<?php echo $ptask['id']; ?>" 
                                            <?php echo (isset($_POST['parent_task_id']) && $_POST['parent_task_id'] == $ptask['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($ptask['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Si c'est une sous-tâche, sélectionner la tâche parente</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="assigned_to" class="form-label">Assigné à</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">Non assignée</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Dates et priorités -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3"><i class="fas fa-calendar-alt"></i> Planning et Priorité</h5>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo e($_POST['start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="end_date" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo e($_POST['end_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="priority" class="form-label">Priorité</label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="faible" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'faible') ? 'selected' : ''; ?>>Faible</option>
                                <option value="moyenne" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'moyenne') ? 'selected' : ''; ?>>Moyenne</option>
                                <option value="haute" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'haute') ? 'selected' : ''; ?>>Haute</option>
                                <option value="critique" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'critique') ? 'selected' : ''; ?>>Critique</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="non_demarree" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'non_demarree') ? 'selected' : ''; ?>>Non démarrée</option>
                                <option value="en_cours" <?php echo (isset($_POST['status']) && $_POST['status'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                                <option value="en_pause" <?php echo (isset($_POST['status']) && $_POST['status'] === 'en_pause') ? 'selected' : ''; ?>>En pause</option>
                                <option value="terminee" <?php echo (isset($_POST['status']) && $_POST['status'] === 'terminee') ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annulee" <?php echo (isset($_POST['status']) && $_POST['status'] === 'annulee') ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Créer la tâche
                        </button>
                        <a href="tasks.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Empêcher la soumission automatique lors du premier chargement
document.getElementById('taskForm').addEventListener('submit', function(e) {
    if (e.submitter && e.submitter.tagName === 'SELECT') {
        // Ne pas soumettre si c'est le changement de projet
        return;
    }
});
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
