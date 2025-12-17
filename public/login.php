<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        setFlashMessage('error', 'Veuillez remplir tous les champs');
        redirect('login.php');
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role'] = $user['role_name'];
            
            // Mise à jour de la dernière connexion
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Log de l'activité
            $logStmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, 'login', 'Connexion réussie', ?, ?)
            ");
            $logStmt->execute([
                $user['id'], 
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            setFlashMessage('success', 'Bienvenue, ' . $user['full_name'] . ' !');
            redirect('dashboard.php');
        } else {
            setFlashMessage('error', 'Identifiant ou mot de passe incorrect');
            redirect('login.php');
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Erreur de connexion. Veuillez réessayer.');
        redirect('login.php');
    }
}

// Affichage du formulaire de connexion
$pageTitle = 'Connexion';
ob_start();
?>

<div class="login-container">
    <div class="col-md-4">
        <div class="card login-card">
            <div class="card-header text-center">
                <h3 class="mb-0"><i class="fas fa-project-diagram"></i> SIGEP</h3>
                <p class="mb-0 mt-2">Système de Gestion des Projets Ministériels</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Nom d'utilisateur
                        </label>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Mot de passe
                        </label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Se souvenir de moi</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </button>
                </form>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Identifiants par défaut: admin / admin123
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
