<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Ressources';

// Filtres
$type_filter = $_GET['type'] ?? '';
$availability_filter = $_GET['availability'] ?? '';

try {
    $pdo = getDbConnection();
    
    // Construire la requête avec filtres
    $sql = "SELECT * FROM resources WHERE 1=1";
    $params = [];
    
    if ($type_filter) {
        $sql .= " AND type = ?";
        $params[] = $type_filter;
    }
    
    if ($availability_filter) {
        $sql .= " AND availability = ?";
        $params[] = $availability_filter;
    }
    
    $sql .= " ORDER BY type, name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
    // Statistiques par type
    $statsStmt = $pdo->query("
        SELECT 
            type,
            COUNT(*) as count,
            SUM(CASE WHEN availability = 'disponible' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN availability = 'assigne' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN availability = 'maintenance' THEN 1 ELSE 0 END) as maintenance
        FROM resources
        GROUP BY type
    ");
    $typeStats = $statsStmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des ressources');
    $resources = [];
    $typeStats = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-boxes"></i> Gestion des Ressources</h2>
    <a href="resource_create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Ajouter une ressource
    </a>
</div>

<!-- Statistiques par type -->
<div class="row mb-4">
    <?php foreach ($typeStats as $stat): ?>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5>
                        <i class="fas fa-<?php 
                            echo $stat['type'] === 'humaine' ? 'users' : 
                                 ($stat['type'] === 'materielle' ? 'laptop' : 'coins'); 
                        ?>"></i>
                        <?php echo ucfirst($stat['type']); ?>
                    </h5>
                    <h3><?php echo $stat['count']; ?> ressource(s)</h3>
                    <div class="mt-2">
                        <span class="badge bg-success me-1"><?php echo $stat['available']; ?> disponible(s)</span>
                        <span class="badge bg-warning me-1"><?php echo $stat['assigned']; ?> assignée(s)</span>
                        <span class="badge bg-danger"><?php echo $stat['maintenance']; ?> en maintenance</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-5">
                <select class="form-select" name="type">
                    <option value="">Tous les types</option>
                    <option value="humaine" <?php echo $type_filter === 'humaine' ? 'selected' : ''; ?>>Humaine</option>
                    <option value="materielle" <?php echo $type_filter === 'materielle' ? 'selected' : ''; ?>>Matérielle</option>
                    <option value="financiere" <?php echo $type_filter === 'financiere' ? 'selected' : ''; ?>>Financière</option>
                </select>
            </div>
            <div class="col-md-5">
                <select class="form-select" name="availability">
                    <option value="">Toutes les disponibilités</option>
                    <option value="disponible" <?php echo $availability_filter === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="assigne" <?php echo $availability_filter === 'assigne' ? 'selected' : ''; ?>>Assignée</option>
                    <option value="maintenance" <?php echo $availability_filter === 'maintenance' ? 'selected' : ''; ?>>En maintenance</option>
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

<!-- Liste des ressources -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-list"></i> Liste des Ressources (<?php echo count($resources); ?>)
    </div>
    <div class="card-body">
        <?php if (empty($resources)): ?>
            <p class="text-center text-muted">Aucune ressource trouvée</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Quantité</th>
                            <th>Unité</th>
                            <th>Coût Unitaire</th>
                            <th>Disponibilité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentType = '';
                        foreach ($resources as $resource): 
                            if ($currentType !== $resource['type']) {
                                $currentType = $resource['type'];
                                ?>
                                <tr class="table-secondary">
                                    <td colspan="8">
                                        <strong>
                                            <i class="fas fa-<?php 
                                                echo $resource['type'] === 'humaine' ? 'users' : 
                                                     ($resource['type'] === 'materielle' ? 'laptop' : 'coins'); 
                                            ?>"></i>
                                            <?php echo strtoupper($resource['type']); ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td><strong><?php echo e($resource['name']); ?></strong></td>
                                <td>
                                    <?php
                                    $typeIcons = [
                                        'humaine' => 'users',
                                        'materielle' => 'laptop',
                                        'financiere' => 'coins'
                                    ];
                                    ?>
                                    <i class="fas fa-<?php echo $typeIcons[$resource['type']]; ?>"></i>
                                    <?php echo ucfirst($resource['type']); ?>
                                </td>
                                <td>
                                    <small><?php echo e($resource['description']); ?></small>
                                </td>
                                <td><?php echo $resource['quantity']; ?></td>
                                <td><?php echo e($resource['unit']); ?></td>
                                <td>
                                    <?php if ($resource['cost_per_unit']): ?>
                                        <?php echo number_format($resource['cost_per_unit'], 0, ',', ' '); ?> FCFA
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $availabilityColors = [
                                        'disponible' => 'success',
                                        'assigne' => 'warning',
                                        'maintenance' => 'danger'
                                    ];
                                    $availabilityLabels = [
                                        'disponible' => 'Disponible',
                                        'assigne' => 'Assignée',
                                        'maintenance' => 'En maintenance'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $availabilityColors[$resource['availability']]; ?>">
                                        <?php echo $availabilityLabels[$resource['availability']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="resource_edit.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="resource_allocate.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-share-square"></i>
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
