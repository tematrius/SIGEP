<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Ajouter une Ligne Budgétaire';

$project_id = $_GET['project_id'] ?? '';

try {
    $pdo = getDbConnection();
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('budget.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $planned_amount = $_POST['planned_amount'] ?? 0;
    $spent_amount = $_POST['spent_amount'] ?? 0;
    
    // Validation
    $errors = [];
    
    if (empty($project_id)) {
        $errors[] = 'Le projet est obligatoire';
    }
    
    if (empty($item_name)) {
        $errors[] = 'Le nom de la ligne est obligatoire';
    }
    
    if (empty($category)) {
        $errors[] = 'La catégorie est obligatoire';
    }
    
    if ($planned_amount < 0) {
        $errors[] = 'Le montant planifié doit être positif';
    }
    
    if ($spent_amount < 0) {
        $errors[] = 'Le montant dépensé doit être positif';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO budget_items 
                (project_id, item_name, category, description, planned_amount, spent_amount) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $project_id,
                $item_name,
                $category,
                $description,
                $planned_amount,
                $spent_amount
            ]);
            
            $budget_item_id = $pdo->lastInsertId();
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'create', 'budget_item', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $budget_item_id,
                "Ajout d'une ligne budgétaire: $item_name"
            ]);
            
            setFlashMessage('success', 'Ligne budgétaire ajoutée avec succès');
            redirect('budget.php?project_id=' . $project_id);
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de l\'ajout de la ligne budgétaire';
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
            <h2><i class="fas fa-plus-circle"></i> Ajouter une Ligne Budgétaire</h2>
            <a href="budget.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="project_id" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">Sélectionner un projet...</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label for="item_name" class="form-label">Nom de la ligne <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="item_name" name="item_name" required
                                   placeholder="Ex: Acquisition de matériel informatique">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="category" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category" name="category" required
                                   placeholder="Ex: Équipement">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Description détaillée de la ligne budgétaire"></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="planned_amount" class="form-label">Montant planifié (FC) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="planned_amount" name="planned_amount" 
                                   step="0.01" min="0" value="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="spent_amount" class="form-label">Montant dépensé (FC)</label>
                            <input type="number" class="form-control" id="spent_amount" name="spent_amount" 
                                   step="0.01" min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="budget.php" class="btn btn-secondary btn-lg">
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
