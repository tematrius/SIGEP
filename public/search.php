<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Recherche';
$query = trim($_GET['q'] ?? '');
$results = [
    'projects' => [],
    'tasks' => [],
    'users' => [],
    'documents' => []
];

if (!empty($query)) {
    try {
        $pdo = getDbConnection();
        $searchTerm = "%$query%";
        
        // Rechercher dans les projets
        $stmt = $pdo->prepare("
            SELECT p.*, l.name as location_name, u.full_name as creator_name
            FROM projects p
            LEFT JOIN locations l ON p.location_id = l.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.title LIKE ? OR p.description LIKE ? OR p.context LIKE ?
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $results['projects'] = $stmt->fetchAll();
        
        // Rechercher dans les tâches
        $stmt = $pdo->prepare("
            SELECT t.*, p.title as project_title, u.full_name as assigned_user
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.title LIKE ? OR t.description LIKE ?
            ORDER BY t.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $results['tasks'] = $stmt->fetchAll();
        
        // Rechercher dans les utilisateurs
        if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Ministre', 'Directeur de Cabinet', 'Secretaire General', 'Chef de Projet'])) {
            $stmt = $pdo->prepare("
                SELECT u.*, r.name as role_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?
                LIMIT 10
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $results['users'] = $stmt->fetchAll();
        }
        
        // Rechercher dans les documents
        $stmt = $pdo->prepare("
            SELECT td.*, t.title as task_title, u.full_name as uploader
            FROM task_documents td
            JOIN tasks t ON td.task_id = t.id
            JOIN users u ON td.uploaded_by = u.id
            WHERE td.file_name LIKE ? OR td.description LIKE ?
            ORDER BY td.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
        $results['documents'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        setFlashMessage('error', 'Erreur lors de la recherche');
    }
}

$totalResults = count($results['projects']) + count($results['tasks']) + 
                count($results['users']) + count($results['documents']);

ob_start();
?>

<div class="row">
    <div class="col-lg-12">
        <h2 class="mb-4"><i class="fas fa-search"></i> Recherche Globale</h2>
        
        <!-- Formulaire de recherche -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="input-group input-group-lg">
                        <input type="text" name="q" class="form-control" 
                               placeholder="Rechercher des projets, tâches, utilisateurs..." 
                               value="<?php echo e($query); ?>" autofocus>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($query)): ?>
            <div class="mb-4">
                <h5>Résultats pour "<?php echo e($query); ?>" : <span class="text-muted"><?php echo $totalResults; ?> résultat(s)</span></h5>
            </div>
            
            <?php if ($totalResults === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aucun résultat trouvé pour votre recherche.
                </div>
            <?php endif; ?>
            
            <!-- Projets -->
            <?php if (!empty($results['projects'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-folder-open"></i> Projets (<?php echo count($results['projects']); ?>)
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($results['projects'] as $project): ?>
                            <a href="project_details.php?id=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo e($project['title']); ?></h6>
                                        <p class="mb-1 text-muted small"><?php echo e(substr($project['description'], 0, 150)); ?>...</p>
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo e($project['creator_name']); ?> |
                                            <i class="fas fa-map-marker-alt"></i> <?php echo e($project['location_name'] ?? 'N/A'); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        $colors = ['prevu' => 'secondary', 'en_cours' => 'primary', 'termine' => 'success'];
                                        echo $colors[$project['status']] ?? 'secondary';
                                    ?>">
                                        <?php echo e($project['status']); ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Tâches -->
            <?php if (!empty($results['tasks'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i> Tâches (<?php echo count($results['tasks']); ?>)
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($results['tasks'] as $task): ?>
                            <a href="task_details.php?id=<?php echo $task['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo e($task['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-folder"></i> <?php echo e($task['project_title']); ?> |
                                            <i class="fas fa-user"></i> <?php echo e($task['assigned_user'] ?? 'Non assignée'); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php 
                                            $colors = ['faible' => 'info', 'moyenne' => 'warning', 'haute' => 'danger'];
                                            echo $colors[$task['priority']] ?? 'info';
                                        ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Utilisateurs -->
            <?php if (!empty($results['users'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Utilisateurs (<?php echo count($results['users']); ?>)
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($results['users'] as $user): ?>
                            <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo e($user['full_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-envelope"></i> <?php echo e($user['email']); ?> |
                                            <i class="fas fa-user-tag"></i> <?php echo e($user['role_name']); ?>
                                        </small>
                                    </div>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactif</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Documents -->
            <?php if (!empty($results['documents'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-file-alt"></i> Documents (<?php echo count($results['documents']); ?>)
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($results['documents'] as $doc): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-file-<?php echo $doc['file_type']; ?>"></i>
                                            <?php echo e($doc['file_name']); ?>
                                        </h6>
                                        <?php if ($doc['description']): ?>
                                            <p class="mb-1 text-muted small"><?php echo e($doc['description']); ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="fas fa-tasks"></i> <?php echo e($doc['task_title']); ?> |
                                            <i class="fas fa-user"></i> <?php echo e($doc['uploader']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="<?php echo BASE_URL . '../' . $doc['file_path']; ?>" 
                                           class="btn btn-sm btn-primary" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="task_details.php?id=<?php echo $doc['task_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
