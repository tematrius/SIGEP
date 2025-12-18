<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Partie prenante non trouvée');
    redirect('stakeholders.php');
}

$pageTitle = 'Modifier la Partie Prenante';

try {
    $pdo = getDbConnection();
    
    // Récupérer la partie prenante
    $stmt = $pdo->prepare("SELECT * FROM stakeholders WHERE id = ?");
    $stmt->execute([$id]);
    $stakeholder = $stmt->fetch();
    
    if (!$stakeholder) {
        setFlashMessage('error', 'Partie prenante non trouvée');
        redirect('stakeholders.php');
    }
    
    // Récupérer les projets
    $stmtProjects = $pdo->query("SELECT id, title FROM projects WHERE status != 'annule' ORDER BY title");
    $projects = $stmtProjects->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('stakeholders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $type = $_POST['type'] ?? 'externe';
    $influence = $_POST['influence'] ?? 3;
    $interest = $_POST['interest'] ?? 3;
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Le nom est obligatoire';
    }
    
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE stakeholders SET 
                    project_id = ?, name = ?, organization = ?, role = ?, 
                    type = ?, influence = ?, interest = ?, email = ?, phone = ?, notes = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $project_id ?: null,
                $name,
                $organization,
                $role,
                $type,
                $influence,
                $interest,
                $email ?: null,
                $phone ?: null,
                $notes,
                $id
            ]);
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'stakeholder', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification de la partie prenante: $name"
            ]);
            
            setFlashMessage('success', 'Partie prenante modifiée avec succès');
            redirect('stakeholders.php');
            
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
            <h2><i class="fas fa-user-edit"></i> Modifier la Partie Prenante</h2>
            <a href="stakeholders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="project_id" class="form-label">Projet (optionnel)</label>
                            <select class="form-select" id="project_id" name="project_id">
                                <option value="">Toute l'organisation</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo $stakeholder['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?php echo e($stakeholder['name']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="organization" class="form-label">Organisation</label>
                            <input type="text" class="form-control" id="organization" name="organization"
                                   value="<?php echo e($stakeholder['organization']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Fonction/Rôle</label>
                            <input type="text" class="form-control" id="role" name="role"
                                   value="<?php echo e($stakeholder['role']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="interne" <?php echo $stakeholder['type'] === 'interne' ? 'selected' : ''; ?>>Interne</option>
                                <option value="externe" <?php echo $stakeholder['type'] === 'externe' ? 'selected' : ''; ?>>Externe</option>
                                <option value="gouvernement" <?php echo $stakeholder['type'] === 'gouvernement' ? 'selected' : ''; ?>>Gouvernement</option>
                                <option value="prive" <?php echo $stakeholder['type'] === 'prive' ? 'selected' : ''; ?>>Privé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="influence" class="form-label">Niveau d'influence (1-5)</label>
                            <select class="form-select" id="influence" name="influence">
                                <option value="1" <?php echo $stakeholder['influence'] == 1 ? 'selected' : ''; ?>>1 - Très faible</option>
                                <option value="2" <?php echo $stakeholder['influence'] == 2 ? 'selected' : ''; ?>>2 - Faible</option>
                                <option value="3" <?php echo $stakeholder['influence'] == 3 ? 'selected' : ''; ?>>3 - Moyen</option>
                                <option value="4" <?php echo $stakeholder['influence'] == 4 ? 'selected' : ''; ?>>4 - Élevé</option>
                                <option value="5" <?php echo $stakeholder['influence'] == 5 ? 'selected' : ''; ?>>5 - Très élevé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="interest" class="form-label">Niveau d'intérêt (1-5)</label>
                            <select class="form-select" id="interest" name="interest">
                                <option value="1" <?php echo $stakeholder['interest'] == 1 ? 'selected' : ''; ?>>1 - Très faible</option>
                                <option value="2" <?php echo $stakeholder['interest'] == 2 ? 'selected' : ''; ?>>2 - Faible</option>
                                <option value="3" <?php echo $stakeholder['interest'] == 3 ? 'selected' : ''; ?>>3 - Moyen</option>
                                <option value="4" <?php echo $stakeholder['interest'] == 4 ? 'selected' : ''; ?>>4 - Élevé</option>
                                <option value="5" <?php echo $stakeholder['interest'] == 5 ? 'selected' : ''; ?>>5 - Très élevé</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo e($stakeholder['email']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo e($stakeholder['phone']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo e($stakeholder['notes']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="stakeholders.php" class="btn btn-secondary btn-lg">
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
