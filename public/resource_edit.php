<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Ressource non trouvée');
    redirect('resources.php');
}

$pageTitle = 'Modifier la Ressource';

try {
    $pdo = getDbConnection();
    
    // Récupérer la ressource
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch();
    
    if (!$resource) {
        setFlashMessage('error', 'Ressource non trouvée');
        redirect('resources.php');
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement de la ressource');
    redirect('resources.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'materielle';
    $description = trim($_POST['description'] ?? '');
    $quantity = $_POST['quantity'] ?? 1;
    $unit = trim($_POST['unit'] ?? '');
    $cost_per_unit = $_POST['cost_per_unit'] ?? null;
    $availability = $_POST['availability'] ?? 'disponible';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Le nom est obligatoire';
    }
    
    if ($quantity < 0) {
        $errors[] = 'La quantité doit être positive';
    }
    
    if ($cost_per_unit !== null && $cost_per_unit < 0) {
        $errors[] = 'Le coût unitaire doit être positif';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE resources SET 
                    name = ?, type = ?, description = ?, quantity = ?,
                    unit = ?, cost_per_unit = ?, availability = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $type,
                $description,
                $quantity,
                $unit ?: null,
                $cost_per_unit ?: null,
                $availability,
                $id
            ]);
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'resource', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification de la ressource: $name"
            ]);
            
            setFlashMessage('success', 'Ressource modifiée avec succès');
            redirect('resources.php');
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la modification';
        }
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

ob_start();
?>

<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> Modifier la Ressource</h2>
            <a href="resources.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Nom de la ressource <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?php echo e($resource['name']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="humaine" <?php echo $resource['type'] === 'humaine' ? 'selected' : ''; ?>>Humaine</option>
                                <option value="materielle" <?php echo $resource['type'] === 'materielle' ? 'selected' : ''; ?>>Matérielle</option>
                                <option value="financiere" <?php echo $resource['type'] === 'financiere' ? 'selected' : ''; ?>>Financière</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($resource['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="quantity" class="form-label">Quantité</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   min="0" value="<?php echo $resource['quantity']; ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="unit" class="form-label">Unité</label>
                            <input type="text" class="form-control" id="unit" name="unit"
                                   value="<?php echo e($resource['unit']); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="cost_per_unit" class="form-label">Coût unitaire (FC)</label>
                            <input type="number" class="form-control" id="cost_per_unit" name="cost_per_unit" 
                                   step="0.01" min="0" value="<?php echo $resource['cost_per_unit']; ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="availability" class="form-label">Disponibilité</label>
                            <select class="form-select" id="availability" name="availability">
                                <option value="disponible" <?php echo $resource['availability'] === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                                <option value="assigne" <?php echo $resource['availability'] === 'assigne' ? 'selected' : ''; ?>>Assignée</option>
                                <option value="maintenance" <?php echo $resource['availability'] === 'maintenance' ? 'selected' : ''; ?>>En maintenance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="resources.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
