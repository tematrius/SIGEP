<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $type = $input['type'] ?? null;
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    
    if (!$id || !$type || !$startDate) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }
    
    $pdo = getDbConnection();
    
    if ($type === 'task') {
        // Vérifier les permissions
        $stmt = $pdo->prepare("
            SELECT t.*, p.created_by as project_owner
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Tâche non trouvée']);
            exit;
        }
        
        // Vérifier si l'utilisateur a les droits
        $canEdit = $_SESSION['role'] === 'admin' || 
                   $_SESSION['role'] === 'gestionnaire' ||
                   $task['assigned_to'] == $_SESSION['user_id'] ||
                   $task['project_owner'] == $_SESSION['user_id'];
        
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'Permission refusée']);
            exit;
        }
        
        // Mettre à jour la tâche
        $stmt = $pdo->prepare("
            UPDATE tasks
            SET start_date = :start_date,
                due_date = :due_date,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'id' => $id,
            'start_date' => $startDate,
            'due_date' => $endDate
        ]);
        
        // Logger l'activité
        logActivity("Date de tâche modifiée via calendrier", 'task', $id);
        
        echo json_encode(['success' => true, 'message' => 'Tâche mise à jour']);
        
    } elseif ($type === 'milestone') {
        // Vérifier les permissions
        $stmt = $pdo->prepare("
            SELECT m.*, p.created_by as project_owner
            FROM project_milestones m
            JOIN projects p ON m.project_id = p.id
            WHERE m.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $milestone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$milestone) {
            echo json_encode(['success' => false, 'message' => 'Jalon non trouvé']);
            exit;
        }
        
        // Vérifier si l'utilisateur a les droits
        $canEdit = $_SESSION['role'] === 'admin' || 
                   $_SESSION['role'] === 'gestionnaire' ||
                   $milestone['project_owner'] == $_SESSION['user_id'];
        
        if (!$canEdit) {
            echo json_encode(['success' => false, 'message' => 'Permission refusée']);
            exit;
        }
        
        // Mettre à jour le jalon
        $stmt = $pdo->prepare("
            UPDATE project_milestones
            SET due_date = :due_date,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'id' => $id,
            'due_date' => $startDate
        ]);
        
        // Logger l'activité
        logActivity("Date de jalon modifiée via calendrier", 'milestone', $id);
        
        echo json_encode(['success' => true, 'message' => 'Jalon mis à jour']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Type invalide']);
    }
    
} catch (PDOException $e) {
    error_log("Erreur calendar_update: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
