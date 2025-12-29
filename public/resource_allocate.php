<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$resource_id = $_GET['resource_id'] ?? null;
$project_id = $_GET['project_id'] ?? null;

if (!$resource_id && !$project_id) {
    $_SESSION['error'] = "Paramètres manquants";
    redirect('resources.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer les informations de la ressource si spécifiée
    if ($resource_id) {
        $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
        $stmt->execute([$resource_id]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resource) {
            $_SESSION['error'] = "Ressource non trouvée";
            redirect('resources.php');
        }
    }
    
    // Récupérer tous les projets actifs
    $stmt = $pdo->query("
        SELECT id, title, start_date, end_date, status
        FROM projects
        WHERE archived = FALSE AND status IN ('prevu', 'en_cours')
        ORDER BY title
    ");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer toutes les ressources disponibles
    $stmt = $pdo->query("
        SELECT id, name, type, availability, quantity
        FROM resources
        WHERE availability IN ('disponible', 'assigne')
        ORDER BY name
    ");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $resource_id_post = $_POST['resource_id'];
        $project_id_post = $_POST['project_id'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        $quantity = $_POST['quantity'] ?? 1;
        $notes = $_POST['notes'] ?? null;
        
        // Vérifier la disponibilité
        $stmt = $pdo->prepare("
            SELECT quantity FROM resources WHERE id = ?
        ");
        $stmt->execute([$resource_id_post]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($quantity > $res['quantity']) {
            $_SESSION['error'] = "Quantité demandée supérieure à la disponibilité";
        } else {
            // Créer l'allocation
            $stmt = $pdo->prepare("
                INSERT INTO resource_allocations 
                (resource_id, project_id, start_date, end_date, quantity, notes, status, allocated_by)
                VALUES (?, ?, ?, ?, ?, ?, 'planned', ?)
            ");
            
            $stmt->execute([
                $resource_id_post,
                $project_id_post,
                $start_date,
                $end_date,
                $quantity,
                $notes,
                $_SESSION['user_id']
            ]);
            
            // Mettre à jour le statut de la ressource
            $stmt = $pdo->prepare("
                UPDATE resources 
                SET availability = 'assigne'
                WHERE id = ?
            ");
            $stmt->execute([$resource_id_post]);
            
            logActivity("Ressource allouée au projet", 'resource_allocation', $pdo->lastInsertId());
            
            $_SESSION['success'] = "Ressource allouée avec succès";
            redirect('project_details.php?id=' . $project_id_post);
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur allocation ressource: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de l'allocation";
    redirect('resources.php');
}

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link"></i> Allouer une Ressource</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="resource_id" class="form-label">Ressource *</label>
                            <select class="form-select" id="resource_id" name="resource_id" required>
                                <option value="">Sélectionner une ressource</option>
                                <?php foreach ($resources as $res): ?>
                                    <option value="<?php echo $res['id']; ?>" 
                                            <?php echo ($resource_id && $resource_id == $res['id']) ? 'selected' : ''; ?>
                                            data-quantity="<?php echo $res['quantity']; ?>">
                                        <?php echo htmlspecialchars($res['name']); ?> 
                                        (<?php echo ucfirst($res['type']); ?>) - 
                                        Disponible: <?php echo $res['quantity']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="project_id" class="form-label">Projet *</label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">Sélectionner un projet</option>
                                <?php foreach ($projects as $proj): ?>
                                    <option value="<?php echo $proj['id']; ?>"
                                            <?php echo ($project_id && $project_id == $proj['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Date de début *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantité *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" required>
                            <small class="text-muted">Quantité disponible: <span id="max_quantity">-</span></small>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" onclick="history.back()">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Allouer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('resource_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const maxQty = selectedOption.getAttribute('data-quantity');
    document.getElementById('max_quantity').textContent = maxQty || '-';
    document.getElementById('quantity').max = maxQty || 999;
});

// Déclencher l'événement au chargement si une ressource est pré-sélectionnée
if (document.getElementById('resource_id').value) {
    document.getElementById('resource_id').dispatchEvent(new Event('change'));
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Allouer une Ressource";
include '../views/layouts/main.php';
?>
