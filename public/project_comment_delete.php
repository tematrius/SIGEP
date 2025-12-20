<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$comment_id = $_POST['comment_id'] ?? null;

if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => 'ID commentaire manquant']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Vérifier que le commentaire appartient à l'utilisateur ou qu'il est admin
    $stmt = $pdo->prepare("
        SELECT pc.*, p.title as project_title
        FROM project_comments pc
        JOIN projects p ON pc.project_id = p.id
        WHERE pc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Commentaire non trouvé']);
        exit;
    }
    
    // Vérifier les permissions
    if ($comment['user_id'] != $_SESSION['user_id'] && !hasPermission('manage_all_projects')) {
        echo json_encode(['success' => false, 'message' => 'Permission refusée']);
        exit;
    }
    
    // Supprimer le commentaire
    $stmt = $pdo->prepare("DELETE FROM project_comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    
    // Log l'activité
    logActivity(
        "Commentaire supprimé sur le projet : " . $comment['project_title'],
        'project_comment',
        $comment_id
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Commentaire supprimé avec succès'
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur suppression commentaire projet: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
}
?>
