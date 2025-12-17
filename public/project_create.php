<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Créer un Projet';

try {
    $pdo = getDbConnection();
    
    // Récupérer les localisations
    $stmtLoc = $pdo->query("SELECT * FROM locations WHERE type = 'province' ORDER BY name");
    $locations = $stmtLoc->fetchAll();
    
    // Récupérer les utilisateurs pour assignation
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('projects.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $context = trim($_POST['context'] ?? '');
    $location_id = $_POST['location_id'] ?? null;
    $budget_estimated = $_POST['budget_estimated'] ?? null;
    $budget_validated = $_POST['budget_validated'] ?? null;
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $status = $_POST['status'] ?? 'prevu';
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Le titre est obligatoire';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire';
    }
    
    if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
        $errors[] = 'La date de fin doit être postérieure à la date de début';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects (
                    title, description, context, location_id, 
                    budget_estimated, budget_validated, 
                    start_date, end_date, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                $context,
                $location_id ?: null,
                $budget_estimated ?: null,
                $budget_validated ?: null,
                $start_date ?: null,
                $end_date ?: null,
                $status,
                $_SESSION['user_id']
            ]);
            
            $projectId = $pdo->lastInsertId();
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'create', 'project', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $projectId,
                "Création du projet: $title"
            ]);
            
            setFlashMessage('success', 'Projet créé avec succès');
            redirect('project_details.php?id=' . $projectId);
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la création du projet';
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
            <h2><i class="fas fa-plus-circle"></i> Créer un Nouveau Projet</h2>
            <a href="projects.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <!-- Informations de base -->
                        <div class="col-md-12">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Informations de base</h5>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo e($_POST['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="context" class="form-label">Contexte</label>
                            <textarea class="form-control" id="context" name="context" rows="3"><?php echo e($_POST['context'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">Mission, engagement, décret ou promesse</small>
                        </div>
                        
                        <!-- Localisation et Dates -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3"><i class="fas fa-map-marker-alt"></i> Localisation et Planning</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="location_id" class="form-label">Localisation</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Sélectionner une province...</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" 
                                            <?php echo (isset($_POST['location_id']) && $_POST['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="prevu" <?php echo (isset($_POST['status']) && $_POST['status'] === 'prevu') ? 'selected' : ''; ?>>Prévu</option>
                                <option value="en_cours" <?php echo (isset($_POST['status']) && $_POST['status'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                                <option value="suspendu" <?php echo (isset($_POST['status']) && $_POST['status'] === 'suspendu') ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="termine" <?php echo (isset($_POST['status']) && $_POST['status'] === 'termine') ? 'selected' : ''; ?>>Terminé</option>
                                <option value="annule" <?php echo (isset($_POST['status']) && $_POST['status'] === 'annule') ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo e($_POST['start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Date de fin prévue</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo e($_POST['end_date'] ?? ''); ?>">
                        </div>
                        
                        <!-- Budget -->
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3"><i class="fas fa-dollar-sign"></i> Budget</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_estimated" class="form-label">Budget estimé (FC)</label>
                            <input type="number" class="form-control" id="budget_estimated" name="budget_estimated" 
                                   step="0.01" value="<?php echo e($_POST['budget_estimated'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_validated" class="form-label">Budget validé (FC)</label>
                            <input type="number" class="form-control" id="budget_validated" name="budget_validated" 
                                   step="0.01" value="<?php echo e($_POST['budget_validated'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Créer le projet
                        </button>
                        <a href="projects.php" class="btn btn-secondary btn-lg">
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
