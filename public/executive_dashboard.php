<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Vérifier si l'utilisateur est admin ou gestionnaire
if (!in_array($_SESSION['role'], ['admin', 'gestionnaire'])) {
    $_SESSION['error'] = "Accès non autorisé. Seuls les administrateurs et gestionnaires peuvent accéder au tableau de bord exécutif.";
    redirect('dashboard.php');
}

try {
    $pdo = getDbConnection();
    
    // 1. KPIs Généraux
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_projects,
            COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as active_projects,
            COUNT(CASE WHEN status = 'termine' THEN 1 END) as completed_projects,
            COUNT(CASE WHEN status = 'en_retard' OR end_date < CURDATE() AND status NOT IN ('termine', 'annule') THEN 1 END) as delayed_projects,
            SUM(budget_validated) as total_budget,
            SUM(CASE WHEN status = 'termine' THEN budget_validated ELSE 0 END) as completed_budget
        FROM projects
        WHERE archived = FALSE
    ");
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Statistiques des tâches
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
            COUNT(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 END) as overdue_tasks,
            ROUND(AVG(progress), 2) as avg_progress
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE p.archived = FALSE
    ");
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 3. Projets critiques (en retard ou à risque élevé)
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.title,
            p.status,
            p.start_date,
            p.end_date,
            p.budget_validated,
            l.name as location_name,
            u.full_name as manager_name,
            COUNT(t.id) as total_tasks,
            COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 END) as overdue_tasks,
            COALESCE(AVG(t.progress), 0) as progress
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.archived = FALSE
          AND (p.end_date < CURDATE() AND p.status NOT IN ('termine', 'annule')
               OR EXISTS (SELECT 1 FROM risks r WHERE r.project_id = p.id AND r.severity = 'high'))
        GROUP BY p.id
        ORDER BY p.end_date ASC
        LIMIT 10
    ");
    $critical_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Performance par province
    $stmt = $pdo->query("
        SELECT 
            l.name as province,
            COUNT(DISTINCT p.id) as project_count,
            COUNT(DISTINCT CASE WHEN p.status = 'termine' THEN p.id END) as completed_count,
            SUM(p.budget_validated) as total_budget,
            ROUND(AVG(CASE 
                WHEN p.status = 'termine' THEN 100
                ELSE (SELECT COALESCE(AVG(t.progress), 0) FROM tasks t WHERE t.project_id = p.id)
            END), 2) as avg_progress
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        WHERE p.archived = FALSE AND l.type = 'province'
        GROUP BY l.id, l.name
        HAVING project_count > 0
        ORDER BY project_count DESC
        LIMIT 10
    ");
    $province_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Budget consommé par mois (6 derniers mois)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as project_count,
            SUM(budget_validated) as budget
        FROM projects
        WHERE archived = FALSE
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_budget = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Top 10 projets par budget
    $stmt = $pdo->query("
        SELECT 
            p.title,
            p.budget_validated,
            p.status,
            l.name as location_name,
            COALESCE(AVG(t.progress), 0) as progress
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.archived = FALSE AND p.budget_validated IS NOT NULL
        GROUP BY p.id
        ORDER BY p.budget_validated DESC
        LIMIT 10
    ");
    $top_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. Risques par niveau
    $stmt = $pdo->query("
        SELECT 
            severity,
            COUNT(*) as count
        FROM risks r
        JOIN projects p ON r.project_id = p.id
        WHERE p.archived = FALSE
        GROUP BY severity
    ");
    $risk_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 8. Performance des utilisateurs (top 10)
    $stmt = $pdo->query("
        SELECT 
            u.full_name,
            COUNT(DISTINCT t.id) as assigned_tasks,
            COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
            ROUND(COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) * 100.0 / COUNT(DISTINCT t.id), 2) as completion_rate
        FROM users u
        JOIN tasks t ON u.id = t.assigned_to
        JOIN projects p ON t.project_id = p.id
        WHERE p.archived = FALSE
        GROUP BY u.id
        HAVING assigned_tasks > 0
        ORDER BY completion_rate DESC, completed_tasks DESC
        LIMIT 10
    ");
    $user_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur dashboard exécutif: " . $e->getMessage());
    $kpis = $task_stats = [];
    $critical_projects = $province_stats = $monthly_budget = $top_projects = $risk_stats = $user_performance = [];
}

// Calculs dérivés
$completion_rate = $kpis['total_projects'] > 0 ? round(($kpis['completed_projects'] / $kpis['total_projects']) * 100, 2) : 0;
$task_completion_rate = $task_stats['total_tasks'] > 0 ? round(($task_stats['completed_tasks'] / $task_stats['total_tasks']) * 100, 2) : 0;
$budget_completion_rate = $kpis['total_budget'] > 0 ? round(($kpis['completed_budget'] / $kpis['total_budget']) * 100, 2) : 0;

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-chart-line"></i> Tableau de Bord Exécutif</h2>
        <p class="text-muted">Vue d'ensemble stratégique de tous les projets du ministère</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button class="btn btn-success" onclick="exportToPDF()">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<!-- KPIs Principaux -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Projets</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($kpis['total_projects']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> <?php echo $kpis['active_projects']; ?></span>
                            <span>En cours</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Taux de Complétion</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $completion_rate; ?>%</div>
                        <div class="mt-2">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completion_rate; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Budget Total</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($kpis['total_budget'], 0, ',', ' '); ?> FC</div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span><?php echo $budget_completion_rate; ?>% dépensé</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-danger shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Projets en Retard</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $kpis['delayed_projects']; ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span class="text-danger mr-2"><i class="fas fa-exclamation-triangle"></i></span>
                            <span>Nécessitent attention</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KPIs Tâches -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-left-info shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Tâches</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($task_stats['total_tasks']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Tâches Complétées</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($task_stats['completed_tasks']); ?></div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <span><?php echo $task_completion_rate; ?>%</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-double fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">En Cours</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($task_stats['in_progress_tasks']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-spinner fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card border-left-danger shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Tâches en Retard</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($task_stats['overdue_tasks']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Graphique: Évolution Budget par Mois -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Évolution Budget (6 derniers mois)</h6>
            </div>
            <div class="card-body">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique: Top 10 Projets par Budget -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-success">Top 10 Projets par Budget</h6>
            </div>
            <div class="card-body">
                <canvas id="topProjectsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Graphique: Performance par Province -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-info">Performance par Province</h6>
            </div>
            <div class="card-body">
                <canvas id="provinceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique: Risques par Niveau -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-warning">Risques par Niveau</h6>
            </div>
            <div class="card-body">
                <canvas id="riskChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Projets Critiques -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-exclamation-triangle"></i> Projets Critiques (Nécessitent Attention Immédiate)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($critical_projects)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Aucun projet critique. Tous les projets sont sur la bonne voie !
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Projet</th>
                                    <th>Province</th>
                                    <th>Manager</th>
                                    <th>Échéance</th>
                                    <th>Budget</th>
                                    <th>Progression</th>
                                    <th>Tâches</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($critical_projects as $project): 
                                    $health = 'danger';
                                    if ($project['progress'] >= 75) $health = 'success';
                                    elseif ($project['progress'] >= 50) $health = 'warning';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($project['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($project['location_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($project['manager_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $end_date = new DateTime($project['end_date']);
                                        $now = new DateTime();
                                        $diff = $now->diff($end_date);
                                        $is_overdue = $now > $end_date;
                                        ?>
                                        <span class="badge badge-<?php echo $is_overdue ? 'danger' : 'warning'; ?>">
                                            <?php echo $end_date->format('d/m/Y'); ?>
                                            <?php if ($is_overdue): ?>
                                                <br><small>(<?php echo $diff->days; ?> jours de retard)</small>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($project['budget_validated'], 0, ',', ' '); ?> FC</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $health; ?>" role="progressbar" 
                                                 style="width: <?php echo $project['progress']; ?>%"
                                                 aria-valuenow="<?php echo $project['progress']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo round($project['progress'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $project['completed_tasks']; ?>/<?php echo $project['total_tasks']; ?></span>
                                        <?php if ($project['overdue_tasks'] > 0): ?>
                                            <br><span class="badge badge-danger"><?php echo $project['overdue_tasks']; ?> en retard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_labels = [
                                            'prevu' => 'Prévu',
                                            'en_cours' => 'En cours',
                                            'suspendu' => 'Suspendu',
                                            'termine' => 'Terminé',
                                            'annule' => 'Annulé'
                                        ];
                                        $status_colors = [
                                            'prevu' => 'secondary',
                                            'en_cours' => 'primary',
                                            'suspendu' => 'warning',
                                            'termine' => 'success',
                                            'annule' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $status_colors[$project['status']] ?? 'secondary'; ?>">
                                            <?php echo $status_labels[$project['status']] ?? $project['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Performance des Utilisateurs -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-users"></i> Top 10 Performance des Utilisateurs</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Utilisateur</th>
                                <th>Tâches Assignées</th>
                                <th>Tâches Complétées</th>
                                <th>Taux de Complétion</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($user_performance as $user): 
                                $rate = $user['completion_rate'];
                                $perf_class = 'success';
                                $perf_label = 'Excellent';
                                if ($rate < 50) {
                                    $perf_class = 'danger';
                                    $perf_label = 'À améliorer';
                                } elseif ($rate < 75) {
                                    $perf_class = 'warning';
                                    $perf_label = 'Moyen';
                                } elseif ($rate < 90) {
                                    $perf_class = 'info';
                                    $perf_label = 'Bon';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo $rank++; ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo $user['assigned_tasks']; ?></td>
                                <td><?php echo $user['completed_tasks']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $perf_class; ?>" role="progressbar" 
                                             style="width: <?php echo $rate; ?>%">
                                            <?php echo $rate; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $perf_class; ?>"><?php echo $perf_label; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

@media print {
    .btn {
        display: none;
    }
    .card {
        page-break-inside: avoid;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// Données pour les graphiques
const monthlyBudgetData = <?php echo json_encode($monthly_budget); ?>;
const topProjectsData = <?php echo json_encode($top_projects); ?>;
const provinceData = <?php echo json_encode($province_stats); ?>;
const riskData = <?php echo json_encode($risk_stats); ?>;

// Graphique: Évolution Budget
const budgetCtx = document.getElementById('budgetChart').getContext('2d');
new Chart(budgetCtx, {
    type: 'line',
    data: {
        labels: monthlyBudgetData.map(d => {
            const [year, month] = d.month.split('-');
            return new Date(year, month - 1).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short' });
        }),
        datasets: [{
            label: 'Budget (FC)',
            data: monthlyBudgetData.map(d => d.budget),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('fr-FR') + ' FC';
                    }
                }
            }
        }
    }
});

// Graphique: Top Projets
const topProjectsCtx = document.getElementById('topProjectsChart').getContext('2d');
new Chart(topProjectsCtx, {
    type: 'bar',
    data: {
        labels: topProjectsData.map(p => p.title.substring(0, 20) + '...'),
        datasets: [{
            label: 'Budget (FC)',
            data: topProjectsData.map(p => p.budget_validated),
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('fr-FR');
                    }
                }
            }
        }
    }
});

// Graphique: Performance par Province
const provinceCtx = document.getElementById('provinceChart').getContext('2d');
new Chart(provinceCtx, {
    type: 'bar',
    data: {
        labels: provinceData.map(p => p.province),
        datasets: [{
            label: 'Nombre de Projets',
            data: provinceData.map(p => p.project_count),
            backgroundColor: 'rgba(255, 159, 64, 0.5)',
            borderColor: 'rgba(255, 159, 64, 1)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: 'Progression Moyenne (%)',
            data: provinceData.map(p => p.avg_progress),
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                max: 100,
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

// Graphique: Risques
const riskCtx = document.getElementById('riskChart').getContext('2d');
const riskCounts = {
    low: 0,
    medium: 0,
    high: 0,
    critical: 0
};
riskData.forEach(r => {
    riskCounts[r.severity] = parseInt(r.count);
});

new Chart(riskCtx, {
    type: 'doughnut',
    data: {
        labels: ['Faible', 'Moyen', 'Élevé', 'Critique'],
        datasets: [{
            data: [riskCounts.low, riskCounts.medium, riskCounts.high, riskCounts.critical],
            backgroundColor: [
                'rgba(75, 192, 192, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(255, 99, 132, 0.7)'
            ],
            borderColor: [
                'rgba(75, 192, 192, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(255, 159, 64, 1)',
                'rgba(255, 99, 132, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Export PDF
function exportToPDF() {
    window.print();
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Tableau de Bord Exécutif";
include '../views/layouts/main.php';
?>
