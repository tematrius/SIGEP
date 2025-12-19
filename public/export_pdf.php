<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Vérifier les permissions d'export
if (!hasPermission('view_reports')) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

$type = $_GET['type'] ?? 'projects';

try {
    $pdo = getDbConnection();
    $title = '';
    $data = [];
    $headers = [];
    
    switch ($type) {
        case 'projects':
            $title = 'Rapport des Projets';
            $headers = ['ID', 'Titre', 'Statut', 'Localisation', 'Date Début', 'Date Fin', 'Budget', 'Progression'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.title,
                    p.status,
                    l.name as location,
                    p.start_date,
                    p.end_date,
                    COALESCE(p.budget_estimated, 0) as budget,
                    COALESCE(AVG(t.progress), 0) as progress
                FROM projects p
                LEFT JOIN locations l ON p.location_id = l.id
                LEFT JOIN tasks t ON p.id = t.project_id
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $data = $stmt->fetchAll();
            break;
            
        case 'tasks':
            $title = 'Rapport des Tâches';
            $headers = ['ID', 'Titre', 'Projet', 'Statut', 'Priorité', 'Assigné à', 'Progression'];
            
            $stmt = $pdo->query("
                SELECT 
                    t.id,
                    t.title,
                    p.title as project,
                    t.status,
                    t.priority,
                    u.full_name as assigned_to,
                    t.progress
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                ORDER BY t.created_at DESC
            ");
            $data = $stmt->fetchAll();
            break;
            
        case 'budget':
            $title = 'Rapport Budgétaire';
            $headers = ['Projet', 'Budget Estimé', 'Date Début', 'Date Fin'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.title as project,
                    p.budget_estimated,
                    p.start_date,
                    p.end_date
                FROM projects p
                WHERE p.budget_estimated > 0
                ORDER BY p.budget_estimated DESC
            ");
            $data = $stmt->fetchAll();
            break;
            
        case 'risks':
            $title = 'Rapport des Risques';
            $headers = ['Projet', 'Description', 'Catégorie', 'Niveau', 'Probabilité', 'Impact', 'Score'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.title as project,
                    r.description,
                    r.category,
                    CASE 
                        WHEN r.risk_score <= 4 THEN 'Faible'
                        WHEN r.risk_score <= 9 THEN 'Moyen'
                        WHEN r.risk_score <= 16 THEN 'Élevé'
                        ELSE 'Critique'
                    END as level,
                    r.probability,
                    r.impact,
                    r.risk_score
                FROM risks r
                JOIN projects p ON r.project_id = p.id
                ORDER BY r.risk_score DESC
            ");
            $data = $stmt->fetchAll();
            break;
            
        default:
            setFlashMessage('error', 'Type de rapport invalide');
            redirect('reports.php');
    }
    
    // Statistiques globales
    $statsStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM projects WHERE status != 'annule') as total_projects,
            (SELECT COUNT(*) FROM projects WHERE status = 'en_cours') as active_projects,
            (SELECT COUNT(*) FROM tasks) as total_tasks,
            (SELECT COUNT(*) FROM tasks WHERE status = 'terminee') as completed_tasks,
            (SELECT COUNT(*) FROM risks) as total_risks
    ");
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log('PDF Export error: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
    redirect('reports.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - SIGEP</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            .no-print {
                display: none !important;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            background: #fff;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #0d6efd;
        }
        
        .header-logo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #0d6efd;
        }
        
        .header-content {
            flex: 1;
            text-align: center;
            padding: 0 20px;
        }
        
        .header h1 {
            color: #0d6efd;
            font-size: 24pt;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            font-size: 14pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .header .date {
            font-size: 10pt;
            color: #999;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #0d6efd;
        }
        
        .stat-box .label {
            font-size: 10pt;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-box .value {
            font-size: 20pt;
            font-weight: bold;
            color: #0d6efd;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        table thead {
            background: #0d6efd;
            color: white;
        }
        
        table th,
        table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #dee2e6;
            font-size: 10pt;
        }
        
        table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        table tbody tr:hover {
            background: #e9ecef;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .badge-success { background: #198754; color: white; }
        .badge-primary { background: #0d6efd; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 11pt;
            margin: 5px;
        }
        
        .btn:hover {
            background: #0b5ed7;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5c636a;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">
            🖨️ Imprimer / Enregistrer en PDF
        </button>
        <a href="reports.php" class="btn btn-secondary">
            ← Retour
        </a>
    </div>
    
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <img src="../assets/images/ministre.jpg" alt="Logo Ministère" class="header-logo">
            <div class="header-content">
                <h1>🏢 SIGEP</h1>
                <div class="subtitle"><?php echo e($title); ?></div>
                <div class="date">Généré le <?php echo date('d/m/Y à H:i'); ?></div>
            </div>
            <img src="../assets/images/ministre.jpg" alt="Logo Ministère" class="header-logo">
        </div>
        
        <!-- Statistiques globales -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="label">Projets Total</div>
                <div class="value"><?php echo $stats['total_projects'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Projets Actifs</div>
                <div class="value"><?php echo $stats['active_projects'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Tâches Total</div>
                <div class="value"><?php echo $stats['total_tasks'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Tâches Terminées</div>
                <div class="value"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Risques Identifiés</div>
                <div class="value"><?php echo $stats['total_risks'] ?? 0; ?></div>
            </div>
        </div>
        
        <!-- Tableau de données -->
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo e($header); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?php echo count($headers); ?>" style="text-align: center; padding: 30px;">
                            Aucune donnée disponible
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <?php if ($type === 'projects'): ?>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo e($row['title']); ?></td>
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
                                    <span class="badge badge-<?php echo $statusColors[$row['status']] ?? 'secondary'; ?>">
                                        <?php echo $statusLabels[$row['status']] ?? $row['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo e($row['location'] ?? 'N/A'); ?></td>
                                <td><?php echo $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A'; ?></td>
                                <td><?php echo $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : 'N/A'; ?></td>
                                <td><?php echo number_format($row['budget'], 0, ',', ' '); ?> FC</td>
                                <td><?php echo round($row['progress'], 1); ?>%</td>
                            <?php elseif ($type === 'tasks'): ?>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo e($row['title']); ?></td>
                                <td><?php echo e($row['project'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'a_faire' => 'danger',
                                        'en_cours' => 'primary',
                                        'en_attente' => 'warning',
                                        'terminee' => 'success'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statusColors[$row['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $priorityColors = [
                                        'basse' => 'secondary',
                                        'moyenne' => 'primary',
                                        'haute' => 'warning',
                                        'critique' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $priorityColors[$row['priority']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($row['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($row['assigned_to'] ?? 'Non assigné'); ?></td>
                                <td><?php echo $row['progress']; ?>%</td>
                            <?php elseif ($type === 'budget'): ?>
                                <td><?php echo e($row['project']); ?></td>
                                <td><?php echo number_format($row['budget_estimated'], 0, ',', ' '); ?> FC</td>
                                <td><?php echo $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A'; ?></td>
                                <td><?php echo $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : 'N/A'; ?></td>
                            <?php elseif ($type === 'risks'): ?>
                                <td><?php echo e($row['project']); ?></td>
                                <td><?php echo e($row['description']); ?></td>
                                <td><?php echo ucfirst($row['category']); ?></td>
                                <td>
                                    <?php
                                    $levelColors = [
                                        'Faible' => 'success',
                                        'Moyen' => 'warning',
                                        'Élevé' => 'danger',
                                        'Critique' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $levelColors[$row['level']] ?? 'secondary'; ?>">
                                        <?php echo $row['level']; ?>
                                    </span>
                                </td>
                                <td><?php echo $row['probability']; ?></td>
                                <td><?php echo $row['impact']; ?></td>
                                <td><?php echo $row['risk_score']; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pied de page -->
        <div class="footer">
            <p><strong>SIGEP - Système Intégré de Gestion et d'Exécution de Projets</strong></p>
            <p>Document généré automatiquement le <?php echo date('d/m/Y à H:i:s'); ?></p>
            <p>Utilisateur: <?php echo e($_SESSION['full_name']); ?> (<?php echo e($_SESSION['role']); ?>)</p>
        </div>
    </div>
    
    <script>
        // Auto-print si demandé
        if (window.location.search.includes('auto=1')) {
            window.onload = function() {
                window.print();
            };
        }
    </script>
</body>
</html>
