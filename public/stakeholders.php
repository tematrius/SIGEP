<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Parties Prenantes';

// Filtres
$search = $_GET['search'] ?? '';
$project_filter = $_GET['project_id'] ?? '';

try {
    $pdo = getDbConnection();
    
    // Construire la requête avec filtres
    $sql = "SELECT s.*, p.title as project_title 
            FROM stakeholders s
            LEFT JOIN projects p ON s.project_id = p.id
            WHERE 1=1";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (s.name LIKE ? OR s.organization LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($project_filter) {
        $sql .= " AND s.project_id = ?";
        $params[] = $project_filter;
    }
    
    $sql .= " ORDER BY s.influence DESC, s.interest DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stakeholders = $stmt->fetchAll();
    
    // Récupérer les projets pour le filtre
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des parties prenantes');
    $stakeholders = [];
    $projects = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users"></i> Gestion des Parties Prenantes</h2>
    <a href="stakeholder_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Ajouter une partie prenante
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Rechercher par nom ou organisation..." 
                       value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-5">
                <select class="form-select" name="project_id">
                    <option value="">Tous les projets</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" 
                                <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo e($project['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Matrice Influence/Intérêt -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-scatter"></i> Matrice Influence / Intérêt
    </div>
    <div class="card-body">
        <table class="table table-bordered text-center">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align: middle; width: 100px;">Influence</th>
                    <th colspan="3">Intérêt</th>
                </tr>
                <tr>
                    <th style="width: 30%;">Faible (1-2)</th>
                    <th style="width: 30%;">Moyen (3)</th>
                    <th style="width: 30%;">Élevé (4-5)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Élevé (4-5)</strong></td>
                    <td class="bg-warning bg-opacity-25">
                        <small><strong>Maintenir satisfait</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] >= 4 && $s['interest'] <= 2) {
                                echo '<span class="badge bg-warning mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-warning bg-opacity-50">
                        <small><strong>Gérer étroitement</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] >= 4 && $s['interest'] == 3) {
                                echo '<span class="badge bg-warning mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-danger bg-opacity-25">
                        <small><strong>Acteurs clés</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] >= 4 && $s['interest'] >= 4) {
                                echo '<span class="badge bg-danger mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Moyen (3)</strong></td>
                    <td class="bg-info bg-opacity-10">
                        <small><strong>Surveillance minimale</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] == 3 && $s['interest'] <= 2) {
                                echo '<span class="badge bg-info mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-info bg-opacity-25">
                        <small><strong>Tenir informé</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] == 3 && $s['interest'] == 3) {
                                echo '<span class="badge bg-info mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-warning bg-opacity-25">
                        <small><strong>Tenir informé</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] == 3 && $s['interest'] >= 4) {
                                echo '<span class="badge bg-warning mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Faible (1-2)</strong></td>
                    <td class="bg-success bg-opacity-10">
                        <small><strong>Surveillance minimale</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] <= 2 && $s['interest'] <= 2) {
                                echo '<span class="badge bg-success mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-info bg-opacity-10">
                        <small><strong>Surveillance minimale</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] <= 2 && $s['interest'] == 3) {
                                echo '<span class="badge bg-info mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                    <td class="bg-info bg-opacity-25">
                        <small><strong>Montrer considération</strong></small><br>
                        <?php 
                        $count = 0;
                        foreach ($stakeholders as $s) {
                            if ($s['influence'] <= 2 && $s['interest'] >= 4) {
                                echo '<span class="badge bg-info mb-1">' . e($s['name']) . '</span><br>';
                                $count++;
                            }
                        }
                        if ($count == 0) echo '<small class="text-muted">Aucun</small>';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Liste des parties prenantes -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list"></i> Liste des Parties Prenantes (<?php echo count($stakeholders); ?>)
    </div>
    <div class="card-body">
        <?php if (empty($stakeholders)): ?>
            <p class="text-center text-muted">Aucune partie prenante trouvée</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Organisation</th>
                            <th>Projet</th>
                            <th>Type</th>
                            <th>Influence</th>
                            <th>Intérêt</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stakeholders as $stakeholder): ?>
                            <tr>
                                <td><strong><?php echo e($stakeholder['name']); ?></strong></td>
                                <td><?php echo e($stakeholder['organization'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($stakeholder['project_title']): ?>
                                        <a href="project_details.php?id=<?php echo $stakeholder['project_id']; ?>">
                                            <?php echo e($stakeholder['project_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'interne' => '<span class="badge bg-primary">Interne</span>',
                                        'externe' => '<span class="badge bg-info">Externe</span>',
                                        'gouvernement' => '<span class="badge bg-success">Gouvernement</span>',
                                        'prive' => '<span class="badge bg-warning">Privé</span>'
                                    ];
                                    echo $typeLabels[$stakeholder['type']] ?? $stakeholder['type'];
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $stakeholder['influence'] >= 4 ? 'danger' : ($stakeholder['influence'] >= 3 ? 'warning' : 'success'); ?>">
                                        <?php echo $stakeholder['influence']; ?>/5
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $stakeholder['interest'] >= 4 ? 'danger' : ($stakeholder['interest'] >= 3 ? 'warning' : 'info'); ?>">
                                        <?php echo $stakeholder['interest']; ?>/5
                                    </span>
                                </td>
                                <td>
                                    <?php if ($stakeholder['email']): ?>
                                        <a href="mailto:<?php echo e($stakeholder['email']); ?>">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($stakeholder['phone']): ?>
                                        <a href="tel:<?php echo e($stakeholder['phone']); ?>" class="ms-2">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="stakeholder_edit.php?id=<?php echo $stakeholder['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
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

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
