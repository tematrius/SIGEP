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

$project_id = $_POST['project_id'] ?? null;
$comment = trim($_POST['comment'] ?? '');

if (!$project_id || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // Vérifier que le projet existe
    $stmt = $pdo->prepare("SELECT id, title, created_by FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Projet non trouvé']);
        exit;
    }
    
    // Extraire les mentions @utilisateur
    preg_match_all('/@(\w+)/', $comment, $matches);
    $mentioned_usernames = $matches[1];
    $mentioned_user_ids = [];
    
    if (!empty($mentioned_usernames)) {
        $placeholders = str_repeat('?,', count($mentioned_usernames) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
        $stmt->execute($mentioned_usernames);
        $mentioned_users = $stmt->fetchAll();
        $mentioned_user_ids = array_column($mentioned_users, 'id');
    }
    
    // Insérer le commentaire
    $stmt = $pdo->prepare("
        INSERT INTO project_comments (project_id, user_id, comment, mentioned_users)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $project_id,
        $_SESSION['user_id'],
        $comment,
        !empty($mentioned_user_ids) ? json_encode($mentioned_user_ids) : null
    ]);
    
    $comment_id = $pdo->lastInsertId();
    
    // Log l'activité
    logActivity(
        "Commentaire ajouté sur le projet : " . $project['title'],
        'project_comment',
        $comment_id
    );
    
    // Créer des notifications pour les utilisateurs mentionnés
    if (!empty($mentioned_user_ids)) {
        $user_name = $_SESSION['full_name'] ?? $_SESSION['username'];
        foreach ($mentioned_user_ids as $mentioned_id) {
            if ($mentioned_id != $_SESSION['user_id']) {
                createNotification(
                    $mentioned_id,
                    'comment_mention',
                    "$user_name vous a mentionné dans un commentaire sur le projet : " . $project['title'],
                    'project',
                    $project_id
                );
            }
        }
    }
    
    // Notifier le créateur du projet (si ce n'est pas l'auteur du commentaire)
    if ($project['created_by'] != $_SESSION['user_id']) {
        $user_name = $_SESSION['full_name'] ?? $_SESSION['username'];
        createNotification(
            $project['created_by'],
            'project_comment',
            "$user_name a commenté sur votre projet : " . $project['title'],
            'project',
            $project_id
        );
    }
    
    // Récupérer le commentaire créé avec les infos utilisateur
    $stmt = $pdo->prepare("
        SELECT pc.*, u.full_name as user_name, u.username
        FROM project_comments pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $created_comment = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Commentaire ajouté avec succès',
        'comment' => [
            'id' => $created_comment['id'],
            'user_name' => $created_comment['user_name'],
            'username' => $created_comment['username'],
            'comment' => $created_comment['comment'],
            'created_at' => $created_comment['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($created_comment['created_at']))
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur ajout commentaire projet: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du commentaire']);
}
?>
