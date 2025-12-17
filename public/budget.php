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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-money-bill-wave"></i> Gestion du Budget</h2>
    <a href="budget_item_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Ajouter une ligne budgétaire
    </a>
</div>

<!-- Statistiques globales -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stats-card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Budget Total Planifié</p>
                        <h3 class="mb-0"><?php echo number_format($stats['total_planned'], 0, ',', ' '); ?> FCFA</h3>
                    </div>
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stats-card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Budget Total Dépensé</p>
                        <h3 class="mb-0"><?php echo number_format($stats['total_spent'], 0, ',', ' '); ?> FCFA</h3>
                    </div>
                    <div class="stats-icon bg-success">
                        <i class="fas fa-money-bill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stats-card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1">Reste à Utiliser</p>
                        <?php 
                        $remaining = $stats['total_planned'] - $stats['total_spent'];
                        $color = $remaining < 0 ? 'danger' : 'success';
                        ?>
                        <h3 class="mb-0 text-<?php echo $color; ?>">
                            <?php echo number_format($remaining, 0, ',', ' '); ?> FCFA
                        </h3>
                    </div>
                    <div class="stats-icon bg-warning">
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
                        <th class="text-end">Budget Alloué</th>
                        <th class="text-end">Budget Planifié</th>
                        <th class="text-end">Budget Dépensé</th>
                        <th class="text-center">Utilisation</th>
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
                                <?php echo number_format($project['allocated_budget'], 0, ',', ' '); ?> FCFA
                            </td>
                            <td class="text-end">
                                <?php echo number_format($project['planned_total'], 0, ',', ' '); ?> FCFA
                            </td>
                            <td class="text-end">
                                <?php echo number_format($project['spent_total'], 0, ',', ' '); ?> FCFA
                            </td>
                            <td>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min($usage_percent, 100); ?>%">
                                        <?php echo number_format($usage_percent, 1); ?>%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="budget.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="budget_item_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i>
                                </a>
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
                            <th>Article</th>
                            <th>Description</th>
                            <th class="text-end">Montant Planifié</th>
                            <th class="text-end">Montant Dépensé</th>
                            <th class="text-center">Statut</th>
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
                                </td>
                                <td><strong><?php echo e($item['item_name']); ?></strong></td>
                                <td>
                                    <small><?php echo e($item['description']); ?></small>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($item['planned_amount'], 0, ',', ' '); ?> FCFA
                                </td>
                                <td class="text-end">
                                    <span class="text-<?php echo $item_color; ?>">
                                        <?php echo number_format($item['spent_amount'], 0, ',', ' '); ?> FCFA
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $item_color; ?>">
                                        <?php echo number_format($item_percent, 0); ?>%
                                    </span>
                                </td>
                                <td>
                                    <a href="budget_item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
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
