<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id || $id == $_SESSION['user_id']) {
    setFlashMessage('error', 'Action non autorisée');
    redirect('users.php');
}

// Vérifier les permissions
$allowed_roles = ['Ministre', 'Directeur de Cabinet', 'Secrétaire Général'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

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
    
    // Inverser le statut
    $new_status = $user['is_active'] ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $id]);
    
    // Log de l'activité
    $action = $new_status ? 'activé' : 'désactivé';
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
        VALUES (?, 'toggle_status', 'user', ?, ?)
    ");
    $logStmt->execute([
        $_SESSION['user_id'],
        $id,
        "Utilisateur {$user['username']} $action"
    ]);
    
    setFlashMessage('success', "Utilisateur " . ($new_status ? 'activé' : 'désactivé') . " avec succès");
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors de la modification du statut');
}

redirect('users.php');
?>
