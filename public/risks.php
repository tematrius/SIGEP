<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Risques';

try {
    $pdo = getDbConnection();
    
    // Récupérer tous les risques avec filtres
    $project_id = $_GET['project_id'] ?? '';
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT r.*, p.title as project_title, u.full_name as responsible_name
            FROM risks r
            JOIN projects p ON r.project_id = p.id
            LEFT JOIN users u ON r.responsible_user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($project_id)) {
        $sql .= " AND r.project_id = ?";
        $params[] = $project_id;
    }
    
    if (!empty($category)) {
        $sql .= " AND r.category = ?";
        $params[] = $category;
    }
    
    if (!empty($status)) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY r.risk_score DESC, r.identified_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $risks = $stmt->fetchAll();
    
    // Récupérer les projets pour le filtre
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des risques');
    $risks = [];
    $projects = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-exclamation-triangle"></i> Gestion des Risques</h2>
    <a href="risk_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouveau Risque
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Projet</label>
                <select name="project_id" class="form-select">
                    <option value="">Tous les projets</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?php echo $proj['id']; ?>" <?php echo $project_id == $proj['id'] ? 'selected' : ''; ?>>
                            <?php echo e($proj['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Catégorie</label>
                <select name="category" class="form-select">
                    <option value="">Toutes</option>
                    <option value="financier" <?php echo $category === 'financier' ? 'selected' : ''; ?>>Financier</option>
                    <option value="technique" <?php echo $category === 'technique' ? 'selected' : ''; ?>>Technique</option>
                    <option value="organisationnel" <?php echo $category === 'organisationnel' ? 'selected' : ''; ?>>Organisationnel</option>
                    <option value="externe" <?php echo $category === 'externe' ? 'selected' : ''; ?>>Externe</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous</option>
                    <option value="identifie" <?php echo $status === 'identifie' ? 'selected' : ''; ?>>Identifié</option>
                    <option value="en_traitement" <?php echo $status === 'en_traitement' ? 'selected' : ''; ?>>En traitement</option>
                    <option value="mitige" <?php echo $status === 'mitige' ? 'selected' : ''; ?>>Mitigé</option>
                    <option value="realise" <?php echo $status === 'realise' ? 'selected' : ''; ?>>Réalisé</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Matrice des risques -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-chart-scatter"></i> Matrice des Risques
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered text-center">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle">Impact →<br>Probabilité ↓</th>
                        <th>Faible (1)</th>
                        <th>Moyen (2-3)</th>
                        <th>Élevé (4-5)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>Très probable (5)</th>
                        <td class="bg-warning">Score: 5-15</td>
                        <td class="bg-danger text-white">Score: 10-15</td>
                        <td class="bg-danger text-white">Score: 20-25</td>
                    </tr>
                    <tr>
                        <th>Probable (4)</th>
                        <td class="bg-warning">Score: 4-12</td>
                        <td class="bg-warning">Score: 8-12</td>
                        <td class="bg-danger text-white">Score: 16-20</td>
                    </tr>
                    <tr>
                        <th>Possible (3)</th>
                        <td class="bg-info">Score: 3-9</td>
                        <td class="bg-warning">Score: 6-9</td>
                        <td class="bg-warning">Score: 12-15</td>
                    </tr>
                    <tr>
                        <th>Peu probable (2)</th>
                        <td class="bg-info">Score: 2-6</td>
                        <td class="bg-info">Score: 4-6</td>
                        <td class="bg-warning">Score: 8-10</td>
                    </tr>
                    <tr>
                        <th>Rare (1)</th>
                        <td class="bg-success text-white">Score: 1-3</td>
                        <td class="bg-info">Score: 2-3</td>
                        <td class="bg-info">Score: 4-5</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            <small>
                <span class="badge bg-success">Faible (1-5)</span>
                <span class="badge bg-info">Moyen (6-11)</span>
                <span class="badge bg-warning">Élevé (12-17)</span>
                <span class="badge bg-danger">Critique (18-25)</span>
            </small>
        </div>
    </div>
</div>

<!-- Liste des risques -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Projet</th>
                        <th>Description</th>
                        <th>Catégorie</th>
                        <th>Probabilité</th>
                        <th>Impact</th>
                        <th>Score</th>
                        <th>Statut</th>
                        <th>Responsable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($risks)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                Aucun risque identifié
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($risks as $risk): ?>
                            <tr>
                                <td>
                                    <a href="project_details.php?id=<?php echo $risk['project_id']; ?>">
                                        <?php echo e($risk['project_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo e(substr($risk['description'], 0, 100)) . (strlen($risk['description']) > 100 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($risk['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo $risk['probability']; ?>/5</td>
                                <td><?php echo $risk['impact']; ?>/5</td>
                                <td>
                                    <?php
                                    $scoreColor = 'success';
                                    if ($risk['risk_score'] >= 18) $scoreColor = 'danger';
                                    elseif ($risk['risk_score'] >= 12) $scoreColor = 'warning';
                                    elseif ($risk['risk_score'] >= 6) $scoreColor = 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $scoreColor; ?>">
                                        <?php echo $risk['risk_score']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'identifie' => 'secondary',
                                        'en_traitement' => 'warning',
                                        'mitige' => 'success',
                                        'realise' => 'danger'
                                    ];
                                    $statusLabels = [
                                        'identifie' => 'Identifié',
                                        'en_traitement' => 'En traitement',
                                        'mitige' => 'Mitigé',
                                        'realise' => 'Réalisé'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $statusColors[$risk['status']]; ?>">
                                        <?php echo $statusLabels[$risk['status']]; ?>
                                    </span>
                                </td>
                                <td><?php echo e($risk['responsible_name'] ?? 'Non assigné'); ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="risk_details.php?id=<?php echo $risk['id']; ?>" 
                                           class="btn btn-sm btn-info" 
                                           title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="risk_edit.php?id=<?php echo $risk['id']; ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
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
