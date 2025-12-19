<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Analyses et Statistiques';

try {
    $pdo = getDbConnection();
    
    // Statistiques globales
    $stats = [];
    
    // Projets par statut
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM projects
        GROUP BY status
    ");
    $stats['projects_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tâches par statut
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM tasks
        GROUP BY status
    ");
    $stats['tasks_by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Projets par localisation (Top 10)
    $stmt = $pdo->query("
        SELECT l.name, COUNT(p.id) as count
        FROM locations l
        LEFT JOIN projects p ON l.id = p.location_id
        WHERE p.id IS NOT NULL
        GROUP BY l.id, l.name
        ORDER BY count DESC
        LIMIT 10
    ");
    $stats['projects_by_location'] = $stmt->fetchAll();
    
    // Budget par projet (Top 10)
    $stmt = $pdo->query("
        SELECT p.title, 
               p.budget_estimated as total_budget
        FROM projects p
        WHERE p.budget_estimated > 0
        ORDER BY p.budget_estimated DESC
        LIMIT 10
    ");
    $stats['budget_by_project'] = $stmt->fetchAll();
    
    // Évolution des projets par mois (12 derniers mois)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
               COUNT(*) as count
        FROM projects
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stats['projects_timeline'] = $stmt->fetchAll();
    
    // Risques par niveau (basé sur risk_score)
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN risk_score <= 4 THEN 'faible'
                WHEN risk_score <= 9 THEN 'moyen'
                WHEN risk_score <= 16 THEN 'eleve'
                ELSE 'critique'
            END as level,
            COUNT(*) as count
        FROM risks
        GROUP BY level
    ");
    $stats['risks_by_level'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tâches par utilisateur (Top 10)
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(t.id) as task_count,
               SUM(CASE WHEN t.status = 'terminee' THEN 1 ELSE 0 END) as completed
        FROM users u
        LEFT JOIN tasks t ON u.id = t.assigned_to
        WHERE t.id IS NOT NULL AND u.is_active = 1
        GROUP BY u.id, u.full_name
        ORDER BY task_count DESC
        LIMIT 10
    ");
    $stats['tasks_by_user'] = $stmt->fetchAll();
    
    // Performance mensuelle (taux de completion)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(t.created_at, '%Y-%m') as month,
               COUNT(*) as total_tasks,
               SUM(CASE WHEN t.status = 'terminee' THEN 1 ELSE 0 END) as completed_tasks,
               ROUND(SUM(CASE WHEN t.status = 'terminee' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as completion_rate
        FROM tasks t
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stats['monthly_performance'] = $stmt->fetchAll();
    
    // Documents par type
    $stmt = $pdo->query("
        SELECT 
            CASE 
                WHEN file_name LIKE '%.pdf' THEN 'PDF'
                WHEN file_name LIKE '%.doc%' THEN 'Word'
                WHEN file_name LIKE '%.xls%' THEN 'Excel'
                WHEN file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.png' THEN 'Image'
                WHEN file_name LIKE '%.zip' OR file_name LIKE '%.rar' THEN 'Archive'
                ELSE 'Autre'
            END as file_type,
            COUNT(*) as count,
            SUM(file_size) as total_size
        FROM task_documents
        GROUP BY file_type
        ORDER BY count DESC
    ");
    $stats['documents_by_type'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Analytics error: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du chargement des statistiques: ' . $e->getMessage());
    $stats = [
        'projects_by_status' => [],
        'tasks_by_status' => [],
        'projects_by_location' => [],
        'budget_by_project' => [],
        'projects_timeline' => [],
        'risks_by_level' => [],
        'tasks_by_user' => [],
        'monthly_performance' => [],
        'documents_by_type' => []
    ];
}

ob_start();
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-line"></i> Analyses et Statistiques</h2>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimer
            </button>
        </div>
        
        <!-- Projets par Statut -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-project-diagram"></i> Projets par Statut
                    </div>
                    <div class="card-body">
                        <canvas id="projectsStatusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tâches par Statut -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks"></i> Tâches par Statut
                    </div>
                    <div class="card-body">
                        <canvas id="tasksStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Projets par Localisation -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-map-marker-alt"></i> Top 10 des Localisations
                    </div>
                    <div class="card-body">
                        <canvas id="projectsLocationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Budget par Projet -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-coins"></i> Top 10 des Budgets par Projet
                    </div>
                    <div class="card-body">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Évolution Mensuelle -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-area"></i> Évolution des Projets (12 derniers mois)
                    </div>
                    <div class="card-body">
                        <canvas id="timelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Mensuelle -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-percentage"></i> Taux de Complétion Mensuel
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Risques et Documents -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle"></i> Risques par Niveau
                    </div>
                    <div class="card-body">
                        <canvas id="risksChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file"></i> Documents par Type
                    </div>
                    <div class="card-body">
                        <canvas id="documentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance par Utilisateur -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Top 10 des Utilisateurs par Tâches
                    </div>
                    <div class="card-body">
                        <?php if (empty($stats['tasks_by_user'])): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune donnée disponible pour les utilisateurs</p>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Total Tâches</th>
                                        <th>Terminées</th>
                                        <th>Taux de Complétion</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['tasks_by_user'] as $user): ?>
                                        <?php 
                                        $completion = $user['task_count'] > 0 
                                            ? round(($user['completed'] / $user['task_count']) * 100, 1) 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo e($user['full_name']); ?></td>
                                            <td><?php echo $user['task_count']; ?></td>
                                            <td><?php echo $user['completed']; ?></td>
                                            <td><?php echo $completion; ?>%</td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php 
                                                        echo $completion >= 75 ? 'bg-success' : 
                                                             ($completion >= 50 ? 'bg-warning' : 'bg-danger'); 
                                                    ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $completion; ?>%">
                                                        <?php echo $completion; ?>%
                                                    </div>
                                                </div>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Projets par statut
const projectsStatusCtx = document.getElementById('projectsStatusChart').getContext('2d');
new Chart(projectsStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Prévu', 'En cours', 'Suspendu', 'Terminé', 'Annulé'],
        datasets: [{
            data: [
                <?php echo $stats['projects_by_status']['prevu'] ?? 0; ?>,
                <?php echo $stats['projects_by_status']['en_cours'] ?? 0; ?>,
                <?php echo $stats['projects_by_status']['suspendu'] ?? 0; ?>,
                <?php echo $stats['projects_by_status']['termine'] ?? 0; ?>,
                <?php echo $stats['projects_by_status']['annule'] ?? 0; ?>
            ],
            backgroundColor: ['#6c757d', '#0d6efd', '#ffc107', '#198754', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Tâches par statut
const tasksStatusCtx = document.getElementById('tasksStatusChart').getContext('2d');
new Chart(tasksStatusCtx, {
    type: 'pie',
    data: {
        labels: ['À faire', 'En cours', 'En attente', 'Terminée'],
        datasets: [{
            data: [
                <?php echo $stats['tasks_by_status']['a_faire'] ?? 0; ?>,
                <?php echo $stats['tasks_by_status']['en_cours'] ?? 0; ?>,
                <?php echo $stats['tasks_by_status']['en_attente'] ?? 0; ?>,
                <?php echo $stats['tasks_by_status']['terminee'] ?? 0; ?>
            ],
            backgroundColor: ['#dc3545', '#0d6efd', '#ffc107', '#198754']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Projets par localisation
const locationsCtx = document.getElementById('projectsLocationChart').getContext('2d');
new Chart(locationsCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($loc) { return '"' . $loc['name'] . '"'; }, $stats['projects_by_location'])); ?>],
        datasets: [{
            label: 'Nombre de projets',
            data: [<?php echo implode(',', array_column($stats['projects_by_location'], 'count')); ?>],
            backgroundColor: '#0d6efd'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Budget par projet
const budgetCtx = document.getElementById('budgetChart').getContext('2d');
new Chart(budgetCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($p) { return '"' . addslashes($p['title']) . '"'; }, $stats['budget_by_project'])); ?>],
        datasets: [{
            label: 'Budget (FC)',
            data: [<?php echo implode(',', array_column($stats['budget_by_project'], 'total_budget')); ?>],
            backgroundColor: '#198754'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'y',
        scales: {
            x: { beginAtZero: true }
        }
    }
});

// Évolution mensuelle
const timelineCtx = document.getElementById('timelineChart').getContext('2d');
new Chart(timelineCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($m) { return '"' . $m['month'] . '"'; }, $stats['projects_timeline'])); ?>],
        datasets: [{
            label: 'Nouveaux projets',
            data: [<?php echo implode(',', array_column($stats['projects_timeline'], 'count')); ?>],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Performance mensuelle
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
new Chart(performanceCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($m) { return '"' . $m['month'] . '"'; }, $stats['monthly_performance'])); ?>],
        datasets: [{
            label: 'Taux de complétion (%)',
            data: [<?php echo implode(',', array_column($stats['monthly_performance'], 'completion_rate')); ?>],
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: { 
                beginAtZero: true,
                max: 100
            }
        }
    }
});

// Risques par niveau
const risksCtx = document.getElementById('risksChart').getContext('2d');
new Chart(risksCtx, {
    type: 'doughnut',
    data: {
        labels: ['Faible', 'Moyen', 'Élevé', 'Critique'],
        datasets: [{
            data: [
                <?php echo $stats['risks_by_level']['faible'] ?? 0; ?>,
                <?php echo $stats['risks_by_level']['moyen'] ?? 0; ?>,
                <?php echo $stats['risks_by_level']['eleve'] ?? 0; ?>,
                <?php echo $stats['risks_by_level']['critique'] ?? 0; ?>
            ],
            backgroundColor: ['#198754', '#ffc107', '#fd7e14', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});

// Documents par type
const documentsCtx = document.getElementById('documentsChart').getContext('2d');
new Chart(documentsCtx, {
    type: 'pie',
    data: {
        labels: [<?php echo implode(',', array_map(function($d) { return '"' . $d['file_type'] . '"'; }, $stats['documents_by_type'])); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($stats['documents_by_type'], 'count')); ?>],
            backgroundColor: ['#dc3545', '#0d6efd', '#198754', '#ffc107', '#6c757d', '#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true
    }
});
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
