<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

header('Content-Type: application/json');

$task_id = $_POST['task_id'] ?? null;
$comment = $_POST['comment'] ?? '';

if (!$task_id || empty(trim($comment))) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Vérifier que la tâche existe
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tâche non trouvée']);
        exit;
    }
    
    // Insérer le commentaire
    $stmt = $pdo->prepare("
        INSERT INTO comments (task_id, user_id, comment)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$task_id, $_SESSION['user_id'], trim($comment)]);
    
    // Récupérer le commentaire créé
    $comment_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $new_comment = $stmt->fetch();
    
    // Log l'activité
    logActivity('comment_created', 'Commentaire ajouté sur la tâche #' . $task_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Commentaire ajouté avec succès',
        'comment' => [
            'id' => $new_comment['id'],
            'user_name' => $new_comment['user_name'],
            'comment' => $new_comment['comment'],
            'created_at' => $new_comment['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($new_comment['created_at']))
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Comment creation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du commentaire']);
}
