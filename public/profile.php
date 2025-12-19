<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Mon Profil';

try {
    $pdo = getDbConnection();
    
    // Récupérer l'utilisateur
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Statistiques de l'utilisateur
    $stats = [];
    
    // Projets créés
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE created_by = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['projects_created'] = $stmt->fetch()['count'];
    
    // Tâches assignées
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['tasks_assigned'] = $stmt->fetch()['count'];
    
    // Tâches terminées
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'terminee'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['tasks_completed'] = $stmt->fetch()['count'];
    
    // Taux de complétion
    if ($stats['tasks_assigned'] > 0) {
        $stats['completion_rate'] = round(($stats['tasks_completed'] / $stats['tasks_assigned']) * 100);
    } else {
        $stats['completion_rate'] = 0;
    }
    
    // Tâches en cours
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.assigned_to = ? AND t.status IN ('non_demarree', 'en_cours')
        ORDER BY t.end_date ASC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $active_tasks = $stmt->fetchAll();
    
    // Activités récentes
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du profil');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $new_password_confirm = $_POST['new_password_confirm'] ?? '';
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Le nom complet est obligatoire';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Un email valide est obligatoire';
    }
    
    // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet email est déjà utilisé';
        }
    }
    
    // Vérification du changement de mot de passe
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Le mot de passe actuel est requis';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Le mot de passe actuel est incorrect';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
        } elseif ($new_password !== $new_password_confirm) {
            $errors[] = 'Les nouveaux mots de passe ne correspondent pas';
        }
    }
    
    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = ?, email = ?, phone = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone ?: null, $hashed_password, $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET full_name = ?, email = ?, phone = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone ?: null, $_SESSION['user_id']]);
            }
            
            // Mettre à jour la session
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            setFlashMessage('success', 'Profil mis à jour avec succès');
            redirect('profile.php');
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la mise à jour du profil';
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
    <!-- Statistiques personnelles -->
    <div class="col-12 mb-4">
        <h2 class="mb-4"><i class="fas fa-user-circle"></i> Mon Profil</h2>
        
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-folder fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['projects_created']; ?></h3>
                        <small>Projets créés</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-tasks fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['tasks_assigned']; ?></h3>
                        <small>Tâches assignées</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['tasks_completed']; ?></h3>
                        <small>Tâches terminées</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-percentage fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['completion_rate']; ?>%</h3>
                        <small>Taux de complétion</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informations du profil -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Informations du Compte
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Nom d'utilisateur:</strong></label>
                            <input type="text" class="form-control" value="<?php echo e($user['username']); ?>" disabled>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Rôle:</strong></label>
                            <input type="text" class="form-control" value="<?php echo e($user['role_name']); ?>" disabled>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="full_name" class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?php echo e($user['full_name']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo e($user['email']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo e($user['phone']); ?>">
                        </div>
                        
                        <div class="col-md-12 mt-4">
                            <h5><i class="fas fa-key"></i> Changer le mot de passe</h5>
                            <p class="text-muted small">Laissez vide si vous ne souhaitez pas changer le mot de passe</p>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="new_password_confirm" class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i> Informations de Compte
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <strong>Dernière connexion:</strong><br>
                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong>Compte créé le:</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar avec tâches et activités -->
    <div class="col-lg-4">
        <!-- Mes tâches en cours -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tasks"></i> Mes Tâches en Cours
            </div>
            <div class="card-body">
                <?php if (empty($active_tasks)): ?>
                    <p class="text-center text-muted py-3">Aucune tâche en cours</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($active_tasks as $task): ?>
                            <li class="list-group-item px-0">
                                <h6 class="mb-1">
                                    <a href="task_details.php?id=<?php echo $task['id']; ?>">
                                        <?php echo e($task['title']); ?>
                                    </a>
                                </h6>
                                <small class="text-muted"><?php echo e($task['project_title']); ?></small>
                                <div class="progress mt-2" style="height: 15px;">
                                    <div class="progress-bar" style="width: <?php echo $task['progress']; ?>%">
                                        <?php echo $task['progress']; ?>%
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-3 text-center">
                        <a href="tasks.php" class="btn btn-sm btn-outline-primary">
                            Voir toutes mes tâches
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Activités récentes -->
        <?php if (!empty($recent_activities)): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Activités Récentes
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <?php foreach ($recent_activities as $activity): ?>
                        <li class="mb-3">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-circle text-primary" style="font-size: 8px;"></i>
                                </div>
                                <div class="flex-grow-1 ms-2">
                                    <small class="text-muted"><?php echo e($activity['action']); ?></small><br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
