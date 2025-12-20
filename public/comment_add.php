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
    
    $comment_id = $pdo->lastInsertId();
    
    // Traiter les pièces jointes si présentes
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $uploadDir = '../uploads/comment_attachments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileCount = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['attachments']['name'][$i];
                $fileSize = $_FILES['attachments']['size'][$i];
                $fileTmp = $_FILES['attachments']['tmp_name'][$i];
                
                // Vérifier la taille (10MB max)
                if ($fileSize > 10 * 1024 * 1024) {
                    continue;
                }
                
                // Générer un nom unique
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueName = uniqid('comment_') . '_' . time() . '.' . $ext;
                $filePath = $uploadDir . $uniqueName;
                
                if (move_uploaded_file($fileTmp, $filePath)) {
                    // Enregistrer dans la base de données
                    $stmt = $pdo->prepare("
                        INSERT INTO comment_attachments (comment_id, file_name, file_path, file_size)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$comment_id, $fileName, $filePath, $fileSize]);
                    
                    $attachments[] = [
                        'id' => $pdo->lastInsertId(),
                        'file_name' => $fileName,
                        'file_size' => $fileSize
                    ];
                }
            }
        }
    }
    
    // Récupérer le commentaire créé avec ses pièces jointes
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $new_comment = $stmt->fetch();
    
    // Log l'activité
    $attachText = count($attachments) > 0 ? ' avec ' . count($attachments) . ' pièce(s) jointe(s)' : '';
    logActivity('Commentaire ajouté sur la tâche #' . $task_id . $attachText, 'comment', $comment_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Commentaire ajouté avec succès',
        'comment' => [
            'id' => $new_comment['id'],
            'user_name' => $new_comment['user_name'],
            'comment' => $new_comment['comment'],
            'created_at' => $new_comment['created_at'],
            'formatted_date' => date('d/m/Y H:i', strtotime($new_comment['created_at'])),
            'attachments' => $attachments
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Comment creation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du commentaire']);
}
