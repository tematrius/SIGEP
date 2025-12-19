<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Rapports';

$report_type = $_GET['type'] ?? 'projects';

try {
    $pdo = getDbConnection();
    
    // Statistiques globales
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM projects WHERE status != 'annule') as total_projects,
            (SELECT COUNT(*) FROM projects WHERE status = 'actif') as active_projects,
            (SELECT COUNT(*) FROM projects WHERE status = 'termine') as completed_projects,
            (SELECT COUNT(*) FROM tasks WHERE status != 'annulee') as total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE status = 'terminee') as completed_tasks,
            (SELECT COUNT(*) FROM risks WHERE status != 'cloture') as active_risks,
            (SELECT SUM(budget_estimated) FROM projects WHERE status != 'annule') as total_budget,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users
    ");
    $globalStats = $statsStmt->fetch();
    
    // Données selon le type de rapport
    $reportData = [];
    
    switch ($report_type) {
        case 'projects':
            $stmt = $pdo->query("
                SELECT p.*, l.name as location_name, u.full_name as creator_name,
                       p.budget_estimated as allocated_budget,
                       COUNT(DISTINCT t.id) as tasks_count,
                       COUNT(DISTINCT CASE WHEN t.status = 'terminee' THEN t.id END) as tasks_completed
                FROM projects p
                LEFT JOIN locations l ON p.location_id = l.id
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN tasks t ON p.id = t.project_id
                WHERE p.status != 'annule'
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $reportData = $stmt->fetchAll();
            break;
            
        case 'tasks':
            $stmt = $pdo->query("
                SELECT t.*, p.title as project_title, u.full_name as assigned_user_name
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.status != 'annulee'
                ORDER BY t.priority DESC, t.end_date ASC
            ");
            $reportData = $stmt->fetchAll();
            break;
            
        case 'risks':
            $stmt = $pdo->query("
                SELECT r.*, p.title as project_title, u.full_name as responsible_name
                FROM risks r
                JOIN projects p ON r.project_id = p.id
                LEFT JOIN users u ON r.responsible_user_id = u.id
                WHERE r.status != 'cloture'
                ORDER BY r.risk_score DESC
            ");
            $reportData = $stmt->fetchAll();
            break;
            
        case 'budget':
            $stmt = $pdo->query("
                SELECT p.title as project_title, p.budget_estimated as allocated_budget,
                       COALESCE(SUM(bi.planned_amount), 0) as planned_total,
                       COALESCE(SUM(bi.spent_amount), 0) as spent_total
                FROM projects p
                LEFT JOIN budget_items bi ON p.id = bi.project_id
                WHERE p.status != 'annule'
                GROUP BY p.id
                ORDER BY p.title
            ");
            $reportData = $stmt->fetchAll();
            break;
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors de la génération du rapport');
    $globalStats = [
        'total_projects' => 0,
        'active_projects' => 0,
        'completed_projects' => 0,
        'total_tasks' => 0,
        'completed_tasks' => 0,
        'active_risks' => 0,
        'total_budget' => 0,
        'active_users' => 0
    ];
    $reportData = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-bar"></i> Rapports et Statistiques</h2>
    <div>
        <a href="analytics.php" class="btn btn-info me-2">
            <i class="fas fa-chart-line"></i> Analyses avancées
        </a>
        <a href="export_pdf.php?type=<?php echo $report_type; ?>" target="_blank" class="btn btn-danger me-2">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <div class="btn-group ms-2">
            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download"></i> Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=<?php echo $report_type; ?>&format=csv">
                    <i class="fas fa-file-csv"></i> Format CSV
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=<?php echo $report_type; ?>&format=excel">
                    <i class="fas fa-file-excel"></i> Format Excel
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques globales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Projets Total</p>
                        <h3 class="mb-0"><?php echo $globalStats['total_projects'] ?? 0; ?></h3>
                        <small class="text-success"><?php echo $globalStats['active_projects'] ?? 0; ?> actifs</small>
                    </div>
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Tâches Total</p>
                        <h3 class="mb-0"><?php echo $globalStats['total_tasks'] ?? 0; ?></h3>
                        <small class="text-success"><?php echo $globalStats['completed_tasks'] ?? 0; ?> terminées</small>
                    </div>
                    <div class="stats-icon bg-info">
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Risques Actifs</p>
                        <h3 class="mb-0"><?php echo $globalStats['active_risks'] ?? 0; ?></h3>
                        <small class="text-muted">À surveiller</small>
                    </div>
                    <div class="stats-icon bg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stats-card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <p class="text-muted mb-1">Budget Total</p>
                        <h3 class="mb-0"><?php echo number_format(($globalStats['total_budget'] ?? 0) / 1000000, 1); ?>M</h3>
                        <small class="text-muted">FC</small>
                    </div>
                    <div class="stats-icon bg-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sélection du type de rapport -->
<div class="card mb-4">
    <div class="card-body">
        <div class="btn-group w-100" role="group">
            <a href="?type=projects" class="btn btn-<?php echo $report_type === 'projects' ? 'primary' : 'outline-primary'; ?>">
                <i class="fas fa-project-diagram"></i> Projets
            </a>
            <a href="?type=tasks" class="btn btn-<?php echo $report_type === 'tasks' ? 'primary' : 'outline-primary'; ?>">
                <i class="fas fa-tasks"></i> Tâches
            </a>
            <a href="?type=risks" class="btn btn-<?php echo $report_type === 'risks' ? 'primary' : 'outline-primary'; ?>">
                <i class="fas fa-exclamation-triangle"></i> Risques
            </a>
            <a href="?type=budget" class="btn btn-<?php echo $report_type === 'budget' ? 'primary' : 'outline-primary'; ?>">
                <i class="fas fa-money-bill-wave"></i> Budget
            </a>
        </div>
    </div>
</div>

<!-- Contenu du rapport -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-table"></i> 
        <?php 
        $titles = [
            'projects' => 'Rapport des Projets',
            'tasks' => 'Rapport des Tâches',
            'risks' => 'Rapport des Risques',
            'budget' => 'Rapport Budgétaire'
        ];
        echo $titles[$report_type];
        ?>
    </div>
    <div class="card-body">
        <div class="table-responsive" id="report-table">
            <?php if ($report_type === 'projects'): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Statut</th>
                            <th>Localisation</th>
                            <th>Budget</th>
                            <th>Tâches</th>
                            <th>Date création</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $project): ?>
                            <tr>
                                <td><?php echo e($project['title']); ?></td>
                                <td><?php echo e($project['status']); ?></td>
                                <td><?php echo e($project['location_name']); ?></td>
                                <td><?php echo number_format($project['allocated_budget'] ?? 0, 0, ',', ' '); ?> FC</td>
                                <td><?php echo $project['tasks_completed']; ?>/<?php echo $project['tasks_count']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($project['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
            <?php elseif ($report_type === 'tasks'): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Projet</th>
                            <th>Assigné à</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Progression</th>
                            <th>Date fin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $task): ?>
                            <tr>
                                <td><?php echo e($task['title']); ?></td>
                                <td><?php echo e($task['project_title']); ?></td>
                                <td><?php echo e($task['assigned_user_name'] ?? 'Non assignée'); ?></td>
                                <td><?php echo e($task['priority']); ?></td>
                                <td><?php echo e($task['status']); ?></td>
                                <td><?php echo $task['progress']; ?>%</td>
                                <td><?php echo $task['end_date'] ? date('d/m/Y', strtotime($task['end_date'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
            <?php elseif ($report_type === 'risks'): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Projet</th>
                            <th>Catégorie</th>
                            <th>Probabilité</th>
                            <th>Impact</th>
                            <th>Score</th>
                            <th>Responsable</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $risk): ?>
                            <tr>
                                <td><?php echo e($risk['title']); ?></td>
                                <td><?php echo e($risk['project_title']); ?></td>
                                <td><?php echo e($risk['category']); ?></td>
                                <td><?php echo $risk['probability']; ?>/5</td>
                                <td><?php echo $risk['impact']; ?>/5</td>
                                <td><strong><?php echo $risk['risk_score']; ?></strong></td>
                                <td><?php echo e($risk['responsible_name'] ?? 'Non assigné'); ?></td>
                                <td><?php echo e($risk['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            
            <?php elseif ($report_type === 'budget'): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Projet</th>
                            <th class="text-end">Budget Alloué</th>
                            <th class="text-end">Budget Planifié</th>
                            <th class="text-end">Budget Dépensé</th>
                            <th class="text-end">Taux d'utilisation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $budget): ?>
                            <?php 
                            $usage = $budget['allocated_budget'] > 0 
                                ? ($budget['spent_total'] / $budget['allocated_budget']) * 100 
                                : 0;
                            ?>
                            <tr>
                                <td><?php echo e($budget['project_title']); ?></td>
                                <td class="text-end"><?php echo number_format($budget['allocated_budget'], 0, ',', ' '); ?> FC</td>
                                <td class="text-end"><?php echo number_format($budget['planned_total'], 0, ',', ' '); ?> FC</td>
                                <td class="text-end"><?php echo number_format($budget['spent_total'], 0, ',', ' '); ?> FC</td>
                                <td class="text-end"><?php echo number_format($usage, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    const table = document.querySelector('#report-table table');
    let csv = [];
    
    // En-têtes
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Données
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
        });
        csv.push(row.join(','));
    });
    
    // Télécharger
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'rapport_<?php echo $report_type; ?>_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
