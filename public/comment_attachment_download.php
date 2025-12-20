<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Pièce jointe non trouvée');
    redirect('tasks.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer l'information sur la pièce jointe
    $stmt = $pdo->prepare("
        SELECT ca.*, c.task_id
        FROM comment_attachments ca
        JOIN comments c ON ca.comment_id = c.id
        WHERE ca.id = ?
    ");
    $stmt->execute([$id]);
    $attachment = $stmt->fetch();
    
    if (!$attachment) {
        setFlashMessage('error', 'Pièce jointe non trouvée');
        redirect('tasks.php');
    }
    
    $filePath = $attachment['file_path'];
    
    if (!file_exists($filePath)) {
        setFlashMessage('error', 'Fichier introuvable sur le serveur');
        redirect('task_details.php?id=' . $attachment['task_id']);
    }
    
    // Log le téléchargement
    logActivity('Téléchargement de la pièce jointe: ' . $attachment['file_name'], 'attachment', $id);
    
    // Forcer le téléchargement
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    readfile($filePath);
    exit;
    
} catch (PDOException $e) {
    error_log('Attachment download error: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors du téléchargement de la pièce jointe');
    redirect('tasks.php');
}
