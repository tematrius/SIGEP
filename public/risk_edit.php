<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Risque non trouvé');
    redirect('risks.php');
}

$pageTitle = 'Modifier le Risque';

try {
    $pdo = getDbConnection();
    
    // Récupérer le risque
    $stmt = $pdo->prepare("SELECT * FROM risks WHERE id = ?");
    $stmt->execute([$id]);
    $risk = $stmt->fetch();
    
    if (!$risk) {
        setFlashMessage('error', 'Risque non trouvé');
        redirect('risks.php');
    }
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
    // Récupérer les utilisateurs
    $stmtUsers = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmtUsers->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du risque');
    redirect('risks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'technique';
    $probability = $_POST['probability'] ?? 3;
    $impact = $_POST['impact'] ?? 3;
    $risk_score = $probability * $impact;
    $status = $_POST['status'] ?? 'identifie';
    $mitigation_plan = trim($_POST['mitigation_plan'] ?? '');
    $responsible_user_id = $_POST['responsible_user_id'] ?? null;
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Le titre est obligatoire';
    }
    
    if (empty($project_id)) {
        $errors[] = 'Le projet est obligatoire';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE risks SET 
                    project_id = ?, title = ?, description = ?, category = ?,
                    probability = ?, impact = ?, risk_score = ?, status = ?,
                    mitigation_plan = ?, responsible_user_id = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $project_id,
                $title,
                $description,
                $category,
                $probability,
                $impact,
                $risk_score,
                $status,
                $mitigation_plan,
                $responsible_user_id ?: null,
                $id
            ]);
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'risk', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification du risque: $title (Score: $risk_score)"
            ]);
            
            setFlashMessage('success', 'Risque modifié avec succès');
            redirect('risks.php');
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la modification du risque';
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
            <h2><i class="fas fa-edit"></i> Modifier le Risque</h2>
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
                                            <?php echo $risk['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">Titre du risque <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo e($risk['title']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($risk['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Catégorie</label>
                            <select class="form-select" id="category" name="category">
                                <option value="technique" <?php echo $risk['category'] === 'technique' ? 'selected' : ''; ?>>Technique</option>
                                <option value="financier" <?php echo $risk['category'] === 'financier' ? 'selected' : ''; ?>>Financier</option>
                                <option value="organisationnel" <?php echo $risk['category'] === 'organisationnel' ? 'selected' : ''; ?>>Organisationnel</option>
                                <option value="externe" <?php echo $risk['category'] === 'externe' ? 'selected' : ''; ?>>Externe</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="identifie" <?php echo $risk['status'] === 'identifie' ? 'selected' : ''; ?>>Identifié</option>
                                <option value="en_cours" <?php echo $risk['status'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="mitige" <?php echo $risk['status'] === 'mitige' ? 'selected' : ''; ?>>Mitigé</option>
                                <option value="cloture" <?php echo $risk['status'] === 'cloture' ? 'selected' : ''; ?>>Clôturé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="probability" class="form-label">Probabilité (1-5)</label>
                            <select class="form-select" id="probability" name="probability" onchange="updateRiskScore()">
                                <option value="1" <?php echo $risk['probability'] == 1 ? 'selected' : ''; ?>>1 - Rare</option>
                                <option value="2" <?php echo $risk['probability'] == 2 ? 'selected' : ''; ?>>2 - Peu probable</option>
                                <option value="3" <?php echo $risk['probability'] == 3 ? 'selected' : ''; ?>>3 - Possible</option>
                                <option value="4" <?php echo $risk['probability'] == 4 ? 'selected' : ''; ?>>4 - Probable</option>
                                <option value="5" <?php echo $risk['probability'] == 5 ? 'selected' : ''; ?>>5 - Très probable</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="impact" class="form-label">Impact (1-5)</label>
                            <select class="form-select" id="impact" name="impact" onchange="updateRiskScore()">
                                <option value="1" <?php echo $risk['impact'] == 1 ? 'selected' : ''; ?>>1 - Négligeable</option>
                                <option value="2" <?php echo $risk['impact'] == 2 ? 'selected' : ''; ?>>2 - Mineur</option>
                                <option value="3" <?php echo $risk['impact'] == 3 ? 'selected' : ''; ?>>3 - Modéré</option>
                                <option value="4" <?php echo $risk['impact'] == 4 ? 'selected' : ''; ?>>4 - Majeur</option>
                                <option value="5" <?php echo $risk['impact'] == 5 ? 'selected' : ''; ?>>5 - Critique</option>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info" id="risk-score-display">
                                <strong>Score du risque:</strong> <span id="risk-score"><?php echo $risk['risk_score']; ?></span> / 25
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="mitigation_plan" class="form-label">Plan de mitigation</label>
                            <textarea class="form-control" id="mitigation_plan" name="mitigation_plan" rows="4"><?php echo e($risk['mitigation_plan']); ?></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="responsible_user_id" class="form-label">Responsable</label>
                            <select class="form-select" id="responsible_user_id" name="responsible_user_id">
                                <option value="">Non assigné</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $risk['responsible_user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
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
function updateRiskScore() {
    const probability = parseInt(document.getElementById('probability').value) || 3;
    const impact = parseInt(document.getElementById('impact').value) || 3;
    const score = probability * impact;
    
    document.getElementById('risk-score').textContent = score;
    
    const alertDiv = document.getElementById('risk-score-display');
    alertDiv.className = 'alert ';
    
    if (score < 6) {
        alertDiv.className += 'alert-success';
    } else if (score < 12) {
        alertDiv.className += 'alert-info';
    } else if (score < 18) {
        alertDiv.className += 'alert-warning';
    } else {
        alertDiv.className += 'alert-danger';
    }
}

// Mettre à jour au chargement
updateRiskScore();
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
