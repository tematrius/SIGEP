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
        SELECT t.*, p.title as project_title 
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
    
    // Vérifier si l'utilisateur est autorisé (assigné ou gestionnaire de projet)
    $isAuthorized = ($task['assigned_to'] == $_SESSION['user_id']) || hasPermission('manage_all_projects');
    
    if (!$isAuthorized) {
        setFlashMessage('error', 'Vous n\'êtes pas autorisé à uploader des documents pour cette tâche');
        redirect('task_details.php?id=' . $task_id);
    }
    
    // Traiter l'upload des fichiers
    if (!isset($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
        setFlashMessage('error', 'Veuillez sélectionner au moins un fichier');
        redirect('task_details.php?id=' . $task_id);
    }
    
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
    $max_size = 10 * 1024 * 1024; // 10 MB
    
    // Créer le dossier de destination s'il n'existe pas
    $upload_dir = '../uploads/task_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $descriptions = $_POST['descriptions'] ?? [];
    $uploaded_count = 0;
    $errors = [];
    
    // Traiter chaque fichier
    foreach ($_FILES['documents']['name'] as $key => $file_name) {
        // Vérifier si le fichier a été uploadé sans erreur
        if ($_FILES['documents']['error'][$key] !== UPLOAD_ERR_OK) {
            if ($_FILES['documents']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                $errors[] = "Erreur lors de l'upload de '{$file_name}'";
            }
            continue;
        }
        
        $file_size = $_FILES['documents']['size'][$key];
        $file_tmp = $_FILES['documents']['tmp_name'][$key];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $description = $descriptions[$key] ?? '';
        
        // Vérifier l'extension
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Type de fichier non autorisé pour '{$file_name}'. Extensions autorisées: " . implode(', ', $allowed_extensions);
            continue;
        }
        
        // Vérifier la taille
        if ($file_size > $max_size) {
            $errors[] = "Le fichier '{$file_name}' est trop volumineux. Taille maximale: 10 MB";
            continue;
        }
        
        // Générer un nom de fichier unique
        $new_file_name = 'task_' . $task_id . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $new_file_name;
        
        // Déplacer le fichier uploadé
        if (!move_uploaded_file($file_tmp, $file_path)) {
            $errors[] = "Erreur lors de l'enregistrement de '{$file_name}'";
            continue;
        }
        
        // Enregistrer dans la base de données
        $stmt = $pdo->prepare("
            INSERT INTO task_documents (task_id, uploaded_by, file_name, file_path, file_size, file_type, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task_id,
            $_SESSION['user_id'],
            $file_name,
            'uploads/task_documents/' . $new_file_name,
            $file_size,
            $file_ext,
            trim($description)
        ]);
        
        $uploaded_count++;
    }
    
    // Afficher les messages appropriés
    if ($uploaded_count > 0) {
        $message = $uploaded_count . ' document(s) uploadé(s) avec succès';
        if (!empty($errors)) {
            $message .= '. Cependant, certains fichiers n\'ont pas pu être uploadés: ' . implode('; ', $errors);
        }
        setFlashMessage($uploaded_count > 0 ? 'success' : 'warning', $message);
    } else {
        setFlashMessage('error', 'Aucun fichier n\'a pu être uploadé. ' . implode('; ', $errors));
        redirect('task_details.php?id=' . $task_id);
    }
    
    // Créer une notification pour le chef de projet (seulement si au moins un fichier a été uploadé)
    if ($uploaded_count > 0) {
        $project_managers_stmt = $pdo->prepare("
            SELECT DISTINCT u.id 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.name IN ('chef_projet', 'admin', 'directeur')
            AND u.id != ?
            AND u.is_active = 1
        ");
        $project_managers_stmt->execute([$_SESSION['user_id']]);
        
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_task_id)
            VALUES (?, 'task', ?, ?, ?)
        ");
        
        $notif_title = $uploaded_count > 1 ? 'Documents ajoutés à une tâche' : 'Document ajouté à une tâche';
        $notif_message = $_SESSION['user_name'] . ' a ajouté ' . $uploaded_count . ' document(s) justificatif(s) pour la tâche "' . $task['title'] . '"';
        
        foreach ($project_managers_stmt->fetchAll() as $manager) {
            $notification_stmt->execute([
                $manager['id'],
                $notif_title,
                $notif_message,
                $task_id
            ]);
        }
    }
    
    redirect('task_details.php?id=' . $task_id);
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors de l\'upload du document: ' . $e->getMessage());
    redirect('task_details.php?id=' . $task_id);
}
?>
