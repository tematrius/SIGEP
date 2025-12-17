<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Déconnecter l'utilisateur
$userId = $_SESSION['user_id'];

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
        VALUES (?, 'logout', 'Déconnexion', ?, ?)
    ");
    $stmt->execute([
        $userId,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
} catch (PDOException $e) {
    // Ignorer les erreurs de log
}

// Détruire la session
session_destroy();
setFlashMessage('success', 'Vous avez été déconnecté avec succès');
redirect('login.php');
?>
