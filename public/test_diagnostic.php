<?php
// Test de diagnostic du système
session_start();
echo "<h1>Diagnostic SIGEP</h1>";

echo "<h2>1. Session Active</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>2. Configuration</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h2>3. Connexion Base de Données</h2>";
try {
    require_once '../config/database.php';
    $pdo = getDbConnection();
    echo "✅ Connexion réussie<br>";
    
    // Test des tables
    $tables = ['users', 'projects', 'tasks', 'roles', 'locations', 'notifications', 'task_documents'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "Table $table: $count enregistrements<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}

echo "<h2>4. Fichiers Config</h2>";
echo "config.php existe: " . (file_exists('../config/config.php') ? '✅' : '❌') . "<br>";
echo "database.php existe: " . (file_exists('../config/database.php') ? '✅' : '❌') . "<br>";

echo "<h2>5. Permissions Utilisateur</h2>";
if (isset($_SESSION['user_id'])) {
    require_once '../config/config.php';
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? 'Non défini') . "<br>";
    echo "hasPermission('manage_users'): " . (hasPermission('manage_users') ? '✅ Oui' : '❌ Non') . "<br>";
    echo "hasPermission('manage_projects'): " . (hasPermission('manage_projects') ? '✅ Oui' : '❌ Non') . "<br>";
} else {
    echo "❌ Utilisateur non connecté";
}

echo "<h2>6. Accès Settings</h2>";
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        echo "✅ Vous pouvez accéder à la page paramètres<br>";
        echo '<a href="settings.php">Accéder aux paramètres</a>';
    } else {
        echo "❌ Votre rôle (" . $_SESSION['role'] . ") ne permet pas d'accéder aux paramètres<br>";
        echo "Seuls les admins peuvent y accéder";
    }
} else {
    echo "❌ Rôle non défini dans la session";
}
?>
