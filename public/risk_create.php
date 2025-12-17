<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Créer un Risque';

try {
    $pdo = getDbConnection();
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Récupérer les utilisateurs
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
    $preselected_project = $_GET['project_id'] ?? '';
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('risks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'technique';
    $probability = $_POST['probability'] ?? 3;
    $impact = $_POST['impact'] ?? 3;
    $status = $_POST['status'] ?? 'identifie';
    $mitigation_plan = trim($_POST['mitigation_plan'] ?? '');
    $responsible_user_id = $_POST['responsible_user_id'] ?? null;
    $identified_date = $_POST['identified_date'] ?? date('Y-m-d');
    
    // Validation
    $errors = [];
    
    if (empty($project_id)) {
        $errors[] = 'Le projet est obligatoire';
    }
    
    if (empty($description)) {
        $errors[] = 'La description du risque est obligatoire';
    }
    
    if ($probability < 1 || $probability > 5) {
        $errors[] = 'La probabilité doit être entre 1 et 5';
    }
    
    if ($impact < 1 || $impact > 5) {
        $errors[] = "L'impact doit être entre 1 et 5";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO risks (
                    project_id, description, category, probability, impact,
                    status, mitigation_plan, responsible_user_id, identified_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $project_id,
                $description,
                $category,
                $probability,
                $impact,
                $status,
                $mitigation_plan,
                $responsible_user_id ?: null,
                $identified_date
            ]);
            
            $riskId = $pdo->lastInsertId();
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'create', 'risk', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $riskId,
                "Identification d'un nouveau risque"
            ]);
            
            setFlashMessage('success', 'Risque créé avec succès');
            redirect('risks.php');
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la création du risque';
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
            <h2><i class="fas fa-exclamation-triangle"></i> Identifier un Nouveau Risque</h2>
            <a href="risks.php" class="btn btn-secondary">
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
                                            <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['id']) || $preselected_project == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description du risque <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Catégorie</label>
                            <select class="form-select" id="category" name="category">
                                <option value="financier" <?php echo (isset($_POST['category']) && $_POST['category'] === 'financier') ? 'selected' : ''; ?>>Financier</option>
                                <option value="technique" <?php echo (!isset($_POST['category']) || $_POST['category'] === 'technique') ? 'selected' : ''; ?>>Technique</option>
                                <option value="organisationnel" <?php echo (isset($_POST['category']) && $_POST['category'] === 'organisationnel') ? 'selected' : ''; ?>>Organisationnel</option>
                                <option value="externe" <?php echo (isset($_POST['category']) && $_POST['category'] === 'externe') ? 'selected' : ''; ?>>Externe</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="identified_date" class="form-label">Date d'identification</label>
                            <input type="date" class="form-control" id="identified_date" name="identified_date" 
                                   value="<?php echo e($_POST['identified_date'] ?? date('Y-m-d')); ?>">
                        </div>
                        
                        <div class="col-md-12 mt-3">
                            <h5 class="mb-3"><i class="fas fa-chart-line"></i> Évaluation du Risque</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="probability" class="form-label">Probabilité (1-5) <span class="text-danger">*</span></label>
                            <select class="form-select" id="probability" name="probability" required>
                                <option value="1" <?php echo (isset($_POST['probability']) && $_POST['probability'] == 1) ? 'selected' : ''; ?>>1 - Rare</option>
                                <option value="2" <?php echo (isset($_POST['probability']) && $_POST['probability'] == 2) ? 'selected' : ''; ?>>2 - Peu probable</option>
                                <option value="3" <?php echo (!isset($_POST['probability']) || $_POST['probability'] == 3) ? 'selected' : ''; ?>>3 - Possible</option>
                                <option value="4" <?php echo (isset($_POST['probability']) && $_POST['probability'] == 4) ? 'selected' : ''; ?>>4 - Probable</option>
                                <option value="5" <?php echo (isset($_POST['probability']) && $_POST['probability'] == 5) ? 'selected' : ''; ?>>5 - Très probable</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="impact" class="form-label">Impact (1-5) <span class="text-danger">*</span></label>
                            <select class="form-select" id="impact" name="impact" required>
                                <option value="1" <?php echo (isset($_POST['impact']) && $_POST['impact'] == 1) ? 'selected' : ''; ?>>1 - Négligeable</option>
                                <option value="2" <?php echo (isset($_POST['impact']) && $_POST['impact'] == 2) ? 'selected' : ''; ?>>2 - Mineur</option>
                                <option value="3" <?php echo (!isset($_POST['impact']) || $_POST['impact'] == 3) ? 'selected' : ''; ?>>3 - Modéré</option>
                                <option value="4" <?php echo (isset($_POST['impact']) && $_POST['impact'] == 4) ? 'selected' : ''; ?>>4 - Majeur</option>
                                <option value="5" <?php echo (isset($_POST['impact']) && $_POST['impact'] == 5) ? 'selected' : ''; ?>>5 - Critique</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info">
                                <strong>Score de risque calculé automatiquement:</strong> <span id="risk-score">9</span> (Probabilité × Impact)
                            </div>
                        </div>
                        
                        <div class="col-md-12 mt-3">
                            <h5 class="mb-3"><i class="fas fa-shield-alt"></i> Mitigation</h5>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="mitigation_plan" class="form-label">Plan de mitigation</label>
                            <textarea class="form-control" id="mitigation_plan" name="mitigation_plan" rows="3"><?php echo e($_POST['mitigation_plan'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="responsible_user_id" class="form-label">Responsable du suivi</label>
                            <select class="form-select" id="responsible_user_id" name="responsible_user_id">
                                <option value="">Non assigné</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo (isset($_POST['responsible_user_id']) && $_POST['responsible_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="identifie" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'identifie') ? 'selected' : ''; ?>>Identifié</option>
                                <option value="en_traitement" <?php echo (isset($_POST['status']) && $_POST['status'] === 'en_traitement') ? 'selected' : ''; ?>>En traitement</option>
                                <option value="mitige" <?php echo (isset($_POST['status']) && $_POST['status'] === 'mitige') ? 'selected' : ''; ?>>Mitigé</option>
                                <option value="realise" <?php echo (isset($_POST['status']) && $_POST['status'] === 'realise') ? 'selected' : ''; ?>>Réalisé</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Créer le risque
                        </button>
                        <a href="risks.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Calcul automatique du score de risque
function updateRiskScore() {
    const probability = parseInt(document.getElementById('probability').value) || 3;
    const impact = parseInt(document.getElementById('impact').value) || 3;
    const score = probability * impact;
    document.getElementById('risk-score').textContent = score;
    
    // Changer la couleur selon le score
    const scoreElement = document.getElementById('risk-score').parentElement;
    scoreElement.className = 'alert';
    if (score >= 18) {
        scoreElement.classList.add('alert-danger');
    } else if (score >= 12) {
        scoreElement.classList.add('alert-warning');
    } else if (score >= 6) {
        scoreElement.classList.add('alert-info');
    } else {
        scoreElement.classList.add('alert-success');
    }
}

document.getElementById('probability').addEventListener('change', updateRiskScore);
document.getElementById('impact').addEventListener('change', updateRiskScore);

// Calcul initial
updateRiskScore();
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
