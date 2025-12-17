<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Utilisateur non trouvé');
    redirect('users.php');
}

// Vérifier les permissions
$allowed_roles = ['Ministre', 'Directeur de Cabinet', 'Secrétaire Général'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

$pageTitle = 'Modifier l\'Utilisateur';

try {
    $pdo = getDbConnection();
    
    // Récupérer l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Utilisateur non trouvé');
        redirect('users.php');
    }
    
    // Récupérer tous les rôles
    $stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmtRoles->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des données');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role_id = $_POST['role_id'] ?? null;
    $phone = trim($_POST['phone'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Le nom d'utilisateur est obligatoire";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Un email valide est obligatoire';
    }
    
    // Vérifier le mot de passe seulement s'il est fourni
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caractères';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        }
    }
    
    if (empty($full_name)) {
        $errors[] = 'Le nom complet est obligatoire';
    }
    
    if (empty($role_id)) {
        $errors[] = 'Le rôle est obligatoire';
    }
    
    // Vérifier si username existe déjà (sauf pour cet utilisateur)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Ce nom d'utilisateur existe déjà";
        }
    }
    
    // Vérifier si email existe déjà (sauf pour cet utilisateur)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet email existe déjà';
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                // Mise à jour avec nouveau mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        username = ?, email = ?, password = ?, full_name = ?, 
                        role_id = ?, phone = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username, $email, $hashed_password, $full_name,
                    $role_id, $phone ?: null, $is_active, $id
                ]);
            } else {
                // Mise à jour sans changer le mot de passe
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                        username = ?, email = ?, full_name = ?, 
                        role_id = ?, phone = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username, $email, $full_name,
                    $role_id, $phone ?: null, $is_active, $id
                ]);
            }
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                VALUES (?, 'update', 'user', ?, ?)
            ");
            $logStmt->execute([
                $_SESSION['user_id'],
                $id,
                "Modification de l'utilisateur: $username"
            ]);
            
            setFlashMessage('success', 'Utilisateur modifié avec succès');
            redirect('users.php');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la modification de l'utilisateur";
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
    <div class="col-lg-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-edit"></i> Modifier l'Utilisateur</h2>
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   value="<?php echo e($user['username']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo e($user['email']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="full_name" class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required 
                                   value="<?php echo e($user['full_name']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">Laisser vide pour ne pas changer</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Sélectionner un rôle...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" 
                                            <?php echo $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo e($user['phone']); ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="users.php" class="btn btn-secondary btn-lg">
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
