<?php
/**
 * SIGEP - Archives des projets
 * Version: 1.8
 * Description: Liste tous les projets archivés avec possibilité de restauration
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Connexion à la base de données
$pdo = getDbConnection();

// Paramètres de pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$location_filter = isset($_GET['location']) ? (int)$_GET['location'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'archived_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Construction de la requête
$where_conditions = ["p.archived = TRUE"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($location_filter > 0) {
    $where_conditions[] = "p.location_id = ?";
    $params[] = $location_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total
$count_sql = "SELECT COUNT(*) FROM projects p WHERE {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_projects = $stmt->fetchColumn();
$total_pages = ceil($total_projects / $per_page);

// Récupérer les projets archivés
$sql = "
    SELECT 
        p.*,
        l.name AS location_name,
        u.full_name AS manager_name,
        archived_user.full_name AS archived_by_name,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id) AS task_count,
        (SELECT COUNT(*) FROM tasks WHERE project_id = p.id AND status = 'completed') AS completed_tasks
    FROM projects p
    LEFT JOIN locations l ON p.location_id = l.id
    LEFT JOIN users u ON p.created_by = u.id
    LEFT JOIN users archived_user ON p.archived_by = archived_user.id
    WHERE {$where_clause}
    ORDER BY {$sort} {$order}
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Récupérer les localisations pour le filtre
$locations_stmt = $pdo->query("SELECT id, name FROM locations WHERE type = 'province' ORDER BY name");
$locations = $locations_stmt->fetchAll();

// Statistiques des archives
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(budget_validated) as total_budget,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM projects
    WHERE archived = TRUE
";
$stats = $pdo->query($stats_sql)->fetch();

$pageTitle = "Archives des projets";
ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-archive"></i> Archives des projets</h2>
                <a href="projects.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Retour aux projets actifs
                </a>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total archivés</h5>
                            <h2><?php echo number_format($stats['total']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Terminés</h5>
                            <h2><?php echo number_format($stats['completed']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5 class="card-title">Annulés</h5>
                            <h2><?php echo number_format($stats['cancelled']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Budget total</h5>
                            <h2><?php echo number_format($stats['total_budget'], 0, ',', ' '); ?> $</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres et recherche -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Rechercher</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nom ou description...">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tous</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Terminé</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="location" class="form-label">Province</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">Toutes</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" 
                                            <?php echo $location_filter == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="sort" class="form-label">Trier par</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="archived_at" <?php echo $sort === 'archived_at' ? 'selected' : ''; ?>>Date d'archivage</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nom</option>
                                <option value="budget_validated" <?php echo $sort === 'budget_validated' ? 'selected' : ''; ?>>Budget</option>
                                <option value="end_date" <?php echo $sort === 'end_date' ? 'selected' : ''; ?>>Date de fin</option>
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

            <!-- Liste des projets archivés -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Projets archivés 
                        <span class="badge bg-secondary"><?php echo $total_projects; ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Aucun projet archivé trouvé.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom du projet</th>
                                        <th>Localisation</th>
                                        <th>Statut</th>
                                        <th>Budget</th>
                                        <th>Tâches</th>
                                        <th>Archivé le</th>
                                        <th>Archivé par</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($project['name']); ?></strong>
                                                <?php if (!empty($project['archive_reason'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-info-circle"></i>
                                                        <?php echo htmlspecialchars(substr($project['archive_reason'], 0, 100)); ?>
                                                        <?php echo strlen($project['archive_reason']) > 100 ? '...' : ''; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($project['location_name'] ?? '-'); ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'secondary',
                                                    'in_progress' => 'primary',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger'
                                                ];
                                                $status_labels = [
                                                    'pending' => 'En attente',
                                                    'in_progress' => 'En cours',
                                                    'completed' => 'Terminé',
                                                    'cancelled' => 'Annulé'
                                                ];
                                                $color = $status_colors[$project['status']] ?? 'secondary';
                                                $label = $status_labels[$project['status']] ?? $project['status'];
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span>
                                            </td>
                                            <td><?php echo number_format($project['budget_validated'], 0, ',', ' '); ?> $</td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $project['completed_tasks']; ?>/<?php echo $project['task_count']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($project['archived_at'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($project['archived_at'])); ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($project['archived_by_name'] ?? '-'); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                                                       class="btn btn-sm btn-info" 
                                                       title="Voir les détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'gestionnaire'): ?>
                                                        <a href="project_restore.php?id=<?php echo $project['id']; ?>" 
                                                           class="btn btn-sm btn-success" 
                                                           title="Restaurer">
                                                            <i class="fas fa-undo"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&location=<?php echo $location_filter; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                                    Précédent
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&location=<?php echo $location_filter; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&location=<?php echo $location_filter; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>">
                                                    Suivant
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    margin: 0 2px;
}
</style>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
