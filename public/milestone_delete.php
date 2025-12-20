<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Jalon non spécifié');
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer le jalon
    $stmt = $pdo->prepare("SELECT * FROM milestones WHERE id = ?");
    $stmt->execute([$id]);
    $milestone = $stmt->fetch();
    
    if (!$milestone) {
        setFlashMessage('error', 'Jalon non trouvé');
        redirect('projects.php');
    }
    
    $project_id = $milestone['project_id'];
    
    // Supprimer le jalon
    $stmt = $pdo->prepare("DELETE FROM milestones WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log l'activité
    logActivity(
        "Jalon supprimé : " . $milestone['title'],
        'milestone',
        $id
    );
    
    setFlashMessage('success', 'Jalon supprimé avec succès');
    redirect('project_details.php?id=' . $project_id);
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors de la suppression du jalon');
    redirect('projects.php');
}
?>
