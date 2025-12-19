<?php
/**
 * Configuration générale de l'application
 */

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constantes de l'application
define('APP_NAME', 'SIGEP');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/SIGEP/public/');
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Timezone
date_default_timezone_set('Africa/Kinshasa');

// Inclure la configuration de la base de données
require_once __DIR__ . '/database.php';

/**
 * Fonction pour rediriger
 */
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit();
}

/**
 * Fonction pour vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Fonction pour vérifier le rôle de l'utilisateur
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Fonction pour échapper les données HTML
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Fonction pour afficher un message flash
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * Fonction pour vérifier les permissions
 */
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Les admins ont toutes les permissions
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Vérifier la permission dans la session
    return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
}

/**
 * Fonction pour enregistrer une activité
 */
function logActivity($action, $entity_type = null, $entity_id = null) {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $action, $entity_type, $entity_id]);
    } catch (PDOException $e) {
        // Ignorer les erreurs de log silencieusement
    }
}
