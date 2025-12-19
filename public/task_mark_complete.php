<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tasks.php');
}

$task_id = $_POST['task_id'] ?? null;

if (!$task_id) {
    setFlashMessage('error', 'Tâche non trouvée');
    redirect('tasks.php');
}

try {
    $pdo = getDbConnection();
    
    // Vérifier que la tâche existe et que l'utilisateur est assigné
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title, p.created_by as manager_id
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Tâche non trouvée');
        redirect('tasks.php');
    }
    
    // Vérifier si l'utilisateur est assigné
    if ($task['assigned_to'] != $_SESSION['user_id'] && 
        !(isset($_SESSION['role']) && in_array($_SESSION['role'], ['Ministre', 'Directeur de Cabinet', 'Secretaire General', 'Chef de Projet']))) {
        setFlashMessage('error', 'Vous n\'êtes pas autorisé à valider cette tâche');
        redirect('task_details.php?id=' . $task_id);
    }
    
    // Vérifier s'il y a au moins un document justificatif
    $doc_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM task_documents WHERE task_id = ?");
    $doc_stmt->execute([$task_id]);
    $doc_count = $doc_stmt->fetch()['count'];
    
    if ($doc_count == 0) {
        setFlashMessage('error', 'Vous devez uploader au moins un document justificatif avant de marquer la tâche comme terminée');
        redirect('task_details.php?id=' . $task_id);
    }
    
    // Mettre à jour la tâche
    $update_stmt = $pdo->prepare("
        UPDATE tasks 
        SET status = 'terminee', progress = 100, updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$task_id]);
    
    // Créer une notification pour le chef de projet
    $notification_stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, related_task_id)
        VALUES (?, 'task', ?, ?, ?)
    ");
    
    $notif_title = 'Tâche marquée comme terminée';
    $notif_message = $_SESSION['user_name'] . ' a marqué la tâche "' . $task['title'] . '" comme terminée';
    
    // Notifier le chef de projet
    if ($task['manager_id'] != $_SESSION['user_id']) {
        $notification_stmt->execute([
            $task['manager_id'],
            $notif_title,
            $notif_message,
            $task_id
        ]);
    }
    
    // Notifier aussi les admins et directeurs
    $admins_stmt = $pdo->prepare("
        SELECT DISTINCT u.id 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.name IN ('admin', 'directeur')
        AND u.id != ?
        AND u.id != ?
        AND u.is_active = 1
    ");
    $admins_stmt->execute([$_SESSION['user_id'], $task['manager_id']]);
    
    foreach ($admins_stmt->fetchAll() as $admin) {
        $notification_stmt->execute([
            $admin['id'],
            $notif_title,
            $notif_message,
            $task_id
        ]);
    }
    
    setFlashMessage('success', 'Tâche marquée comme terminée avec succès');
    redirect('task_details.php?id=' . $task_id);
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors de la validation de la tâche: ' . $e->getMessage());
    redirect('task_details.php?id=' . $task_id);
}
?>
