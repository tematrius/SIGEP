<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Projet non trouvé');
    redirect('projects.php');
}

$pageTitle = 'Modifier le Projet';

try {
    $pdo = getDbConnection();
    
    // Récupérer le projet
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        setFlashMessage('error', 'Projet non trouvé');
        redirect('projects.php');
    }
    
    // Récupérer les localisations
    $stmtLoc = $pdo->query("SELECT * FROM locations WHERE type = 'province' ORDER BY name");
    $locations = $stmtLoc->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du projet');
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
    $progress = $_POST['progress'] ?? 0;
    
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
    
    if ($progress < 0 || $progress > 100) {
        $errors[] = 'La progression doit être entre 0 et 100';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE projects SET 
                    title = ?, description = ?, context = ?, location_id = ?, 
                    budget_estimated = ?, budget_validated = ?, 
                    start_date = ?, end_date = ?, status = ?, progress = ?,
                    updated_by = ?
                WHERE id = ?
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
                $progress,
                $_SESSION['user_id'],
                $id
            ]);
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'project', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification du projet: $title"
            ]);
            
            setFlashMessage('success', 'Projet modifié avec succès');
            redirect('project_details.php?id=' . $id);
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la modification du projet';
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
            <h2><i class="fas fa-edit"></i> Modifier le Projet</h2>
            <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="mb-3"><i class="fas fa-info-circle"></i> Informations de base</h5>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Titre du projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo e($project['title']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($project['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="context" class="form-label">Contexte</label>
                            <textarea class="form-control" id="context" name="context" rows="3"><?php echo e($project['context']); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3"><i class="fas fa-map-marker-alt"></i> Localisation et Planning</h5>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="location_id" class="form-label">Localisation</label>
                            <select class="form-select" id="location_id" name="location_id">
                                <option value="">Sélectionner une province...</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" 
                                            <?php echo $project['location_id'] == $location['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="prevu" <?php echo $project['status'] === 'prevu' ? 'selected' : ''; ?>>Prévu</option>
                                <option value="en_cours" <?php echo $project['status'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="suspendu" <?php echo $project['status'] === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                                <option value="termine" <?php echo $project['status'] === 'termine' ? 'selected' : ''; ?>>Terminé</option>
                                <option value="annule" <?php echo $project['status'] === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="progress" class="form-label">Progression (%)</label>
                            <input type="number" class="form-control" id="progress" name="progress" min="0" max="100"
                                   value="<?php echo $project['progress']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $project['start_date']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Date de fin prévue</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $project['end_date']; ?>">
                        </div>
                        
                        <div class="col-md-12 mt-4">
                            <h5 class="mb-3"><i class="fas fa-dollar-sign"></i> Budget</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_estimated" class="form-label">Budget estimé (FC)</label>
                            <input type="number" class="form-control" id="budget_estimated" name="budget_estimated" 
                                   step="0.01" value="<?php echo $project['budget_estimated']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="budget_validated" class="form-label">Budget validé (FC)</label>
                            <input type="number" class="form-control" id="budget_validated" name="budget_validated" 
                                   step="0.01" value="<?php echo $project['budget_validated']; ?>">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary btn-lg">
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
