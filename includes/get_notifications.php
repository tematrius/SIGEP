<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

try {
    $pdo = getDbConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    echo json_encode(['unread_count' => (int)$result['count']]);
    
} catch (PDOException $e) {
    echo json_encode(['unread_count' => 0]);
}
?>
