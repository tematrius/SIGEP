<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion du Budget';

// Filtres
$project_filter = $_GET['project_id'] ?? '';

try {
    $pdo = getDbConnection();
    
    // Statistiques globales
    $statsStmt = $pdo->query("
        SELECT 
            SUM(planned_amount) as total_planned,
            SUM(spent_amount) as total_spent
        FROM budget_items
        WHERE 1=1
    ");
    $stats = $statsStmt->fetch();
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title, budget_estimated FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Budget par projet
    $budgetByProjectStmt = $pdo->query("
        SELECT 
            p.id, p.title, p.budget_estimated as allocated_budget,
            COALESCE(SUM(bi.planned_amount), 0) as planned_total,
            COALESCE(SUM(bi.spent_amount), 0) as spent_total,
            COUNT(bi.id) as items_count
        FROM projects p
        LEFT JOIN budget_items bi ON p.id = bi.project_id
        WHERE p.status != 'annule'
        GROUP BY p.id
        ORDER BY p.title
    ");
    $budgetByProject = $budgetByProjectStmt->fetchAll();
    
    // Détails par projet sélectionné
    $projectBudgetItems = [];
    if ($project_filter) {
        $itemsStmt = $pdo->prepare("
            SELECT * FROM budget_items 
            WHERE project_id = ?
            ORDER BY category, item_name
        ");
        $itemsStmt->execute([$project_filter]);
        $projectBudgetItems = $itemsStmt->fetchAll();
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données budgétaires');
    $stats = ['total_planned' => 0, 'total_spent' => 0];
    $projects = [];
    $budgetByProject = [];
}

ob_start();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
    <h2 class="mb-0"><i class="fas fa-money-bill-wave"></i> Gestion du Budget</h2>
    <a href="budget_item_create.php" class="btn btn-primary btn-block-mobile">
        <i class="fas fa-plus"></i> Ajouter une ligne
    </a>
</div>

<!-- Statistiques globales -->
<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card stats-card border-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Budget Total Planifié</p>
                        <h4 class="mb-0"><?php echo number_format($stats['total_planned'], 0, ',', ' '); ?> FC</h4>
                    </div>
                    <div class="stats-icon bg-primary d-none d-md-block">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card stats-card border-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Budget Total Dépensé</p>
                        <h4 class="mb-0"><?php echo number_format($stats['total_spent'], 0, ',', ' '); ?> FC</h4>
                    </div>
                    <div class="stats-icon bg-success d-none d-md-block">
                        <i class="fas fa-money-bill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stats-card border-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Reste à Utiliser</p>
                        <?php 
                        $remaining = $stats['total_planned'] - $stats['total_spent'];
                        $color = $remaining < 0 ? 'danger' : 'success';
                        ?>
                        <h4 class="mb-0 text-<?php echo $color; ?>">
                            <?php echo number_format($remaining, 0, ',', ' '); ?> FC
                        </h4>
                    </div>
                    <div class="stats-icon bg-warning d-none d-md-block">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Budget par projet -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-project-diagram"></i> Budget par Projet
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Projet</th>
                        <th class="text-end">Alloué</th>
                        <th class="text-end">Planifié</th>
                        <th class="text-end">Dépensé</th>
                        <th class="text-center d-none d-lg-table-cell">Utilisation</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgetByProject as $project): ?>
                        <?php 
                        $usage_percent = $project['allocated_budget'] > 0 
                            ? ($project['spent_total'] / $project['allocated_budget']) * 100 
                            : 0;
                        $color = $usage_percent > 90 ? 'danger' : ($usage_percent > 70 ? 'warning' : 'success');
                        ?>
                        <tr>
                            <td>
                                <a href="project_details.php?id=<?php echo $project['id']; ?>">
                                    <strong><?php echo e($project['title']); ?></strong>
                                </a>
                                <br>
                                <small class="text-muted"><?php echo $project['items_count']; ?> ligne(s)</small>
                            </td>
                            <td class="text-end">
                                <span class="d-none d-md-inline"><?php echo number_format($project['allocated_budget'], 0, ',', ' '); ?> FC</span>
                                <span class="d-md-none"><?php echo number_format($project['allocated_budget'] / 1000, 0); ?>K</span>
                            </td>
                            <td class="text-end">
                                <span class="d-none d-md-inline"><?php echo number_format($project['planned_total'], 0, ',', ' '); ?> FC</span>
                                <span class="d-md-none"><?php echo number_format($project['planned_total'] / 1000, 0); ?>K</span>
                            </td>
                            <td class="text-end">
                                <span class="d-none d-md-inline"><?php echo number_format($project['spent_total'], 0, ',', ' '); ?> FC</span>
                                <span class="d-md-none"><?php echo number_format($project['spent_total'] / 1000, 0); ?>K</span>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min($usage_percent, 100); ?>%">
                                        <?php echo number_format($usage_percent, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="budget.php?project_id=<?php echo $project['id']; ?>" class="btn btn-info" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="budget_item_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-success" title="Ajouter">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Détails par projet -->
<?php if ($project_filter && !empty($projectBudgetItems)): ?>
    <?php
    $selectedProject = array_filter($budgetByProject, function($p) use ($project_filter) {
        return $p['id'] == $project_filter;
    });
    $selectedProject = reset($selectedProject);
    ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> Lignes Budgétaires - <?php echo e($selectedProject['title']); ?>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th class="d-none d-md-table-cell">Article</th>
                            <th class="d-none d-lg-table-cell">Description</th>
                            <th class="text-end">Planifié</th>
                            <th class="text-end">Dépensé</th>
                            <th class="text-center d-none d-md-table-cell">Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectBudgetItems as $item): ?>
                            <?php 
                            $item_percent = $item['planned_amount'] > 0 
                                ? ($item['spent_amount'] / $item['planned_amount']) * 100 
                                : 0;
                            $item_color = $item_percent > 100 ? 'danger' : ($item_percent > 80 ? 'warning' : 'success');
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo e($item['category']); ?></span>
                                    <div class="d-md-none mt-1">
                                        <small><strong><?php echo e($item['item_name']); ?></strong></small>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><strong><?php echo e($item['item_name']); ?></strong></td>
                                <td class="d-none d-lg-table-cell">
                                    <small><?php echo e($item['description']); ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="d-none d-md-inline"><?php echo number_format($item['planned_amount'], 0, ',', ' '); ?> FC</span>
                                    <span class="d-md-none"><?php echo number_format($item['planned_amount'] / 1000, 0); ?>K</span>
                                </td>
                                <td class="text-end">
                                    <span class="text-<?php echo $item_color; ?>">
                                        <span class="d-none d-md-inline"><?php echo number_format($item['spent_amount'], 0, ',', ' '); ?> FC</span>
                                        <span class="d-md-none"><?php echo number_format($item['spent_amount'] / 1000, 0); ?>K</span>
                                    </span>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="badge bg-<?php echo $item_color; ?>">
                                        <?php echo number_format($item_percent, 0); ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="budget_item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
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
