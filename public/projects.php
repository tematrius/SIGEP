<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Projets';

try {
    $pdo = getDbConnection();
    
    // Récupérer tous les projets avec filtres
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $location = $_GET['location'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $sort_by = $_GET['sort_by'] ?? 'created_at';
    $sort_order = $_GET['sort_order'] ?? 'DESC';
    
    $sql = "SELECT p.*, l.name as location_name, u.full_name as creator_name,
            (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) as task_count,
            COALESCE((SELECT AVG(progress) FROM tasks WHERE project_id = p.id), 0) as calculated_progress
            FROM projects p
            LEFT JOIN locations l ON p.location_id = l.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status)) {
        $sql .= " AND p.status = ?";
        $params[] = $status;
    }
    
    if (!empty($location)) {
        $sql .= " AND p.location_id = ?";
        $params[] = $location;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND p.start_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND p.end_date <= ?";
        $params[] = $date_to;
    }
    
    // Tri sécurisé
    $allowedSort = ['created_at', 'title', 'status', 'start_date', 'end_date'];
    if (!in_array($sort_by, $allowedSort)) {
        $sort_by = 'created_at';
    }
    $sort_order = $sort_order === 'ASC' ? 'ASC' : 'DESC';
    
    $sql .= " ORDER BY p.$sort_by $sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    
    // Récupérer les localisations pour le filtre
    $stmtLoc = $pdo->query("SELECT * FROM locations WHERE type = 'province' ORDER BY name");
    $locations = $stmtLoc->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des projets');
    $projects = [];
    $locations = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-folder-open"></i> Gestion des Projets</h2>
    <a href="project_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouveau Projet
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres</h5>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
            <i class="fas fa-cog"></i> Filtres avancés
        </button>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" name="search" class="form-control" placeholder="Titre ou description..." value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous</option>
                    <option value="prevu" <?php echo $status === 'prevu' ? 'selected' : ''; ?>>Prévu</option>
                    <option value="en_cours" <?php echo $status === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="suspendu" <?php echo $status === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                    <option value="termine" <?php echo $status === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                    <option value="annule" <?php echo $status === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Localisation</label>
                <select name="location" class="form-select">
                    <option value="">Toutes</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo $loc['id']; ?>" <?php echo $location == $loc['id'] ? 'selected' : ''; ?>>
                            <?php echo e($loc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Trier par</label>
                <select name="sort_by" class="form-select">
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date création</option>
                    <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Titre</option>
                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Statut</option>
                    <option value="start_date" <?php echo $sort_by === 'start_date' ? 'selected' : ''; ?>>Date début</option>
                    <option value="end_date" <?php echo $sort_by === 'end_date' ? 'selected' : ''; ?>>Date fin</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="btn-group w-100">
                    <button type="submit" name="sort_order" value="<?php echo $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                </div>
            </div>
            
            <!-- Filtres avancés (collapsible) -->
            <div class="collapse col-12" id="advancedFilters">
                <hr>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date début (de)</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date fin (à)</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="projects.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong><?php echo count($projects); ?></strong> projet(s) trouvé(s)
            <?php if ($search || $status || $location || $date_from || $date_to): ?>
                - <a href="projects.php" class="alert-link">Effacer les filtres</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Liste des projets -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Localisation</th>
                        <th>Budget</th>
                        <th>Dates</th>
                        <th>Statut</th>
                        <th>Progression</th>
                        <th>Tâches</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                Aucun projet trouvé
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($project['title']); ?></strong>
                                    <br>
                                    <small class="text-muted">Par <?php echo e($project['creator_name']); ?></small>
                                </td>
                                <td><?php echo e($project['location_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($project['budget_validated']): ?>
                                        <?php echo number_format($project['budget_validated'], 0, ',', ' '); ?> FC
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'N/A'; ?>
                                        <br>
                                        <?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'N/A'; ?>
                                    </small>
                                </td>
                                <td>
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
                                    <span class="badge bg-<?php echo $statusColors[$project['status']]; ?>">
                                        <?php echo $statusLabels[$project['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $progress = round($project['calculated_progress']); ?>
                                    <div class="progress" style="height: 20px; min-width: 100px;">
                                        <div class="progress-bar bg-<?php echo $progress < 30 ? 'danger' : ($progress < 70 ? 'warning' : 'success'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $progress; ?>%">
                                            <?php echo $progress; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo $project['task_count']; ?> tâches
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="project_edit.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="project_delete.php?id=<?php echo $project['id']; ?>" 
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
