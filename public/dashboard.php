<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Tableau de bord';

try {
    $pdo = getDbConnection();
    $userId = $_SESSION['user_id'];
    
    // Statistiques globales
    $stats = [];
    
    // Total des projets
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects");
    $stats['total_projects'] = $stmt->fetch()['total'];
    
    // Projets en cours
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE status = 'en_cours'");
    $stats['active_projects'] = $stmt->fetch()['total'];
    
    // Projets terminés
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE status = 'termine'");
    $stats['completed_projects'] = $stmt->fetch()['total'];
    
    // Projets en retard
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projects WHERE status = 'en_cours' AND end_date < CURDATE()");
    $stats['delayed_projects'] = $stmt->fetch()['total'];
    
    // Tâches en attente
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status IN ('non_demarree', 'en_cours')");
    $stats['pending_tasks'] = $stmt->fetch()['total'];
    
    // Projets récents avec progression calculée
    $stmt = $pdo->prepare("
        SELECT p.*, l.name as location_name, u.full_name as creator_name,
               COALESCE(AVG(t.progress), 0) as calculated_progress
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_projects = $stmt->fetchAll();
    
    // Projets par statut (pour graphique)
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM projects 
        GROUP BY status
    ");
    $projects_by_status = $stmt->fetchAll();
    
    // Tâches urgentes
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title, u.full_name as assigned_user
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status IN ('non_demarree', 'en_cours')
        AND t.end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY t.end_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $urgent_tasks = $stmt->fetchAll();
    
    // Budget total et consommé
    $stmt = $pdo->query("
        SELECT 
            SUM(planned_amount) as total_planned,
            SUM(spent_amount) as total_spent
        FROM budget_items
    ");
    $budget_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    $stats = [
        'total_projects' => 0,
        'active_projects' => 0,
        'completed_projects' => 0,
        'delayed_projects' => 0,
        'pending_tasks' => 0
    ];
    $recent_projects = [];
    $projects_by_status = [];
    $urgent_tasks = [];
    $budget_stats = ['total_planned' => 0, 'total_spent' => 0];
}

ob_start();
?>

<div class="row">
    <!-- Stats Cards -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <p class="text-muted mb-1">Total Projets</p>
                        <h3 class="mb-0"><?php echo $stats['total_projects']; ?></h3>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-folder-open stats-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card success">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <p class="text-muted mb-1">En Cours</p>
                        <h3 class="mb-0"><?php echo $stats['active_projects']; ?></h3>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-spinner stats-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card warning">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <p class="text-muted mb-1">En Retard</p>
                        <h3 class="mb-0"><?php echo $stats['delayed_projects']; ?></h3>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle stats-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stats-card danger">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <p class="text-muted mb-1">Tâches Actives</p>
                        <h3 class="mb-0"><?php echo $stats['pending_tasks']; ?></h3>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-tasks stats-icon text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Projets par statut -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Répartition des Projets
            </div>
            <div class="card-body">
                <canvas id="projectsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Budget -->
    <div class="col-xl-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-dollar-sign"></i> Performance Budgétaire
            </div>
            <div class="card-body">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Projets récents -->
    <div class="col-xl-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-folder-open"></i> Projets Récents</span>
                <a href="projects.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Projet</th>
                                <th>Localisation</th>
                                <th>Statut</th>
                                <th>Progression</th>
                                <th>Date de fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_projects)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Aucun projet disponible
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_projects as $project): ?>
                                    <tr>
                                        <td>
                                            <a href="project_details.php?id=<?php echo $project['id']; ?>">
                                                <?php echo e($project['title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo e($project['location_name'] ?? 'N/A'); ?></td>
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
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $progress < 30 ? 'danger' : ($progress < 70 ? 'warning' : 'success'); ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $progress; ?>%">
                                                    <?php echo $progress; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tâches urgentes -->
    <div class="col-xl-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-circle"></i> Tâches Urgentes
            </div>
            <div class="card-body">
                <?php if (empty($urgent_tasks)): ?>
                    <p class="text-center text-muted py-3">Aucune tâche urgente</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($urgent_tasks as $task): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo e($task['title']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo e($task['project_title']); ?>
                                        </small>
                                        <br>
                                        <small class="text-danger">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('d/m/Y', strtotime($task['end_date'])); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php 
                                        $priorities = ['faible' => 'info', 'moyenne' => 'warning', 'haute' => 'danger', 'critique' => 'danger'];
                                        echo $priorities[$task['priority']];
                                    ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// JavaScript pour les graphiques
$extraJS = '<script>
// Graphique des projets par statut
const projectsCtx = document.getElementById("projectsChart");
if (projectsCtx) {
    new Chart(projectsCtx, {
        type: "doughnut",
        data: {
            labels: ' . json_encode(array_map(function($item) {
                $labels = [
                    'prevu' => 'Prévu',
                    'en_cours' => 'En cours',
                    'suspendu' => 'Suspendu',
                    'termine' => 'Terminé',
                    'annule' => 'Annulé'
                ];
                return $labels[$item['status']] ?? $item['status'];
            }, $projects_by_status)) . ',
            datasets: [{
                data: ' . json_encode(array_column($projects_by_status, 'count')) . ',
                backgroundColor: ["#6c757d", "#0d6efd", "#ffc107", "#198754", "#dc3545"]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Graphique du budget
const budgetCtx = document.getElementById("budgetChart");
if (budgetCtx) {
    new Chart(budgetCtx, {
        type: "bar",
        data: {
            labels: ["Budget"],
            datasets: [{
                label: "Planifié",
                data: [' . ($budget_stats['total_planned'] ?? 0) . '],
                backgroundColor: "#0d6efd"
            }, {
                label: "Dépensé",
                data: [' . ($budget_stats['total_spent'] ?? 0) . '],
                backgroundColor: "#198754"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}
</script>';

include '../views/layouts/main.php';
?>
