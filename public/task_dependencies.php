<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$task_id = $_GET['task_id'] ?? null;

if (!$task_id) {
    setFlashMessage('error', 'Tâche non spécifiée');
    redirect('tasks.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer les informations de la tâche
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title, p.id as project_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Tâche non trouvée');
        redirect('tasks.php');
    }
    
    // Récupérer les tâches du même projet (sauf la tâche actuelle)
    $stmt = $pdo->prepare("
        SELECT id, title, start_date, end_date, status
        FROM tasks
        WHERE project_id = ? AND id != ?
        ORDER BY start_date ASC, title ASC
    ");
    $stmt->execute([$task['project_id'], $task_id]);
    $available_tasks = $stmt->fetchAll();
    
    // Récupérer les dépendances existantes
    $stmt = $pdo->prepare("
        SELECT td.*, t.title as task_title
        FROM task_dependencies td
        JOIN tasks t ON td.depends_on_task_id = t.id
        WHERE td.task_id = ?
    ");
    $stmt->execute([$task_id]);
    $existing_dependencies = $stmt->fetchAll();
    
    // Traitement de l'ajout de dépendance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dependency'])) {
        $depends_on_task_id = $_POST['depends_on_task_id'] ?? null;
        $dependency_type = $_POST['dependency_type'] ?? 'finish_to_start';
        
        if ($depends_on_task_id) {
            try {
                // Vérifier qu'on ne crée pas une dépendance circulaire
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM task_dependencies
                    WHERE task_id = ? AND depends_on_task_id = ?
                ");
                $stmt->execute([$depends_on_task_id, $task_id]);
                $circular = $stmt->fetch();
                
                if ($circular['count'] > 0) {
                    setFlashMessage('error', 'Impossible de créer une dépendance circulaire');
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$task_id, $depends_on_task_id, $dependency_type]);
                    
                    logActivity(
                        "Dépendance ajoutée pour la tâche : " . $task['title'],
                        'task_dependency',
                        $task_id
                    );
                    
                    setFlashMessage('success', 'Dépendance ajoutée avec succès');
                    redirect('task_dependencies.php?task_id=' . $task_id);
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    setFlashMessage('error', 'Cette dépendance existe déjà');
                } else {
                    setFlashMessage('error', 'Erreur lors de l\'ajout de la dépendance');
                }
            }
        }
    }
    
    // Traitement de la suppression
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_dependency'])) {
        $dependency_id = $_POST['dependency_id'] ?? null;
        
        if ($dependency_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM task_dependencies WHERE id = ? AND task_id = ?");
                $stmt->execute([$dependency_id, $task_id]);
                
                logActivity(
                    "Dépendance supprimée pour la tâche : " . $task['title'],
                    'task_dependency',
                    $task_id
                );
                
                setFlashMessage('success', 'Dépendance supprimée avec succès');
                redirect('task_dependencies.php?task_id=' . $task_id);
            } catch (PDOException $e) {
                setFlashMessage('error', 'Erreur lors de la suppression');
            }
        }
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('tasks.php');
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-project-diagram"></i> Dépendances de la Tâche</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                <li class="breadcrumb-item"><a href="projects.php">Projets</a></li>
                <li class="breadcrumb-item"><a href="project_details.php?id=<?php echo $task['project_id']; ?>"><?php echo e($task['project_title']); ?></a></li>
                <li class="breadcrumb-item"><a href="task_details.php?id=<?php echo $task_id; ?>"><?php echo e($task['title']); ?></a></li>
                <li class="breadcrumb-item active">Dépendances</li>
            </ol>
        </nav>
    </div>
    <a href="task_details.php?id=<?php echo $task_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour à la tâche
    </a>
</div>

<!-- Informations de la tâche -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> Tâche: <?php echo e($task['title']); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Projet:</strong> <?php echo e($task['project_title']); ?>
            </div>
            <div class="col-md-4">
                <strong>Dates:</strong> 
                <?php echo $task['start_date'] ? date('d/m/Y', strtotime($task['start_date'])) : 'N/A'; ?>
                →
                <?php echo $task['end_date'] ? date('d/m/Y', strtotime($task['end_date'])) : 'N/A'; ?>
            </div>
            <div class="col-md-4">
                <strong>Statut:</strong>
                <?php
                $statusColors = [
                    'pending' => 'secondary',
                    'in_progress' => 'primary',
                    'completed' => 'success',
                    'blocked' => 'danger'
                ];
                $statusLabels = [
                    'pending' => 'En attente',
                    'in_progress' => 'En cours',
                    'completed' => 'Terminé',
                    'blocked' => 'Bloqué'
                ];
                ?>
                <span class="badge bg-<?php echo $statusColors[$task['status']]; ?>">
                    <?php echo $statusLabels[$task['status']]; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Ajouter une dépendance -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-plus"></i> Ajouter une Dépendance
    </div>
    <div class="card-body">
        <?php if (empty($available_tasks)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Aucune autre tâche disponible dans ce projet pour créer des dépendances.
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="depends_on_task_id" class="form-label">Cette tâche dépend de:</label>
                            <select class="form-select" id="depends_on_task_id" name="depends_on_task_id" required>
                                <option value="">-- Sélectionner une tâche --</option>
                                <?php foreach ($available_tasks as $avail_task): ?>
                                    <option value="<?php echo $avail_task['id']; ?>">
                                        <?php echo e($avail_task['title']); ?>
                                        (<?php echo $avail_task['start_date'] ? date('d/m/Y', strtotime($avail_task['start_date'])) : 'Sans date'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                Cette tâche ne pourra commencer que lorsque la tâche sélectionnée sera terminée
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="dependency_type" class="form-label">Type de dépendance:</label>
                            <select class="form-select" id="dependency_type" name="dependency_type">
                                <option value="finish_to_start">Fin → Début (par défaut)</option>
                                <option value="start_to_start">Début → Début</option>
                                <option value="finish_to_finish">Fin → Fin</option>
                                <option value="start_to_finish">Début → Fin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" name="add_dependency" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des dépendances existantes -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list"></i> Dépendances Existantes (<?php echo count($existing_dependencies); ?>)
    </div>
    <div class="card-body">
        <?php if (empty($existing_dependencies)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-project-diagram fa-3x mb-3"></i>
                <p>Aucune dépendance définie pour cette tâche</p>
                <small>Les dépendances permettent de définir l'ordre d'exécution des tâches dans le projet</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tâche Prérequise</th>
                            <th>Type de Dépendance</th>
                            <th>Date de Création</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($existing_dependencies as $dep): ?>
                            <tr>
                                <td>
                                    <a href="task_details.php?id=<?php echo $dep['depends_on_task_id']; ?>">
                                        <?php echo e($dep['task_title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'finish_to_start' => 'Fin → Début',
                                        'start_to_start' => 'Début → Début',
                                        'finish_to_finish' => 'Fin → Fin',
                                        'start_to_finish' => 'Début → Fin'
                                    ];
                                    ?>
                                    <span class="badge bg-info">
                                        <?php echo $typeLabels[$dep['dependency_type']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y H:i', strtotime($dep['created_at'])); ?></small>
                                </td>
                                <td>
                                    <form method="POST" action="" style="display: inline;" 
                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette dépendance ?');">
                                        <input type="hidden" name="dependency_id" value="<?php echo $dep['id']; ?>">
                                        <button type="submit" name="delete_dependency" class="btn btn-sm btn-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aide -->
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <i class="fas fa-question-circle"></i> À propos des Dépendances
    </div>
    <div class="card-body">
        <h6>Types de dépendances:</h6>
        <ul>
            <li><strong>Fin → Début (Finish-to-Start):</strong> La tâche dépendante ne peut commencer avant que la tâche prérequise soit terminée. C'est le type le plus courant.</li>
            <li><strong>Début → Début (Start-to-Start):</strong> Les deux tâches doivent commencer en même temps.</li>
            <li><strong>Fin → Fin (Finish-to-Finish):</strong> Les deux tâches doivent se terminer en même temps.</li>
            <li><strong>Début → Fin (Start-to-Finish):</strong> La tâche dépendante ne peut se terminer avant que la tâche prérequise ait commencé.</li>
        </ul>
        
        <h6 class="mt-3">Utilité:</h6>
        <p class="mb-0">
            Les dépendances sont essentielles pour:
        </p>
        <ul>
            <li>Visualiser l'ordre logique d'exécution des tâches</li>
            <li>Afficher correctement le diagramme de Gantt</li>
            <li>Identifier le chemin critique du projet</li>
            <li>Planifier efficacement les ressources</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Dépendances - ' . $task['title'];
include '../views/layouts/main.php';
?>
