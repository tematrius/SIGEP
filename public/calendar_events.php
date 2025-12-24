<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDbConnection();
    
    $projectId = $_GET['project_id'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $type = $_GET['type'] ?? '';
    
    $events = [];
    
    // Récupérer les tâches
    if ($type === '' || $type === 'task') {
        $sql = "
            SELECT 
                t.id,
                t.title,
                t.description,
                t.start_date,
                t.due_date,
                t.status,
                t.progress,
                p.title as project_title,
                p.id as project_id,
                u.full_name as assigned_to
            FROM tasks t
            JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE p.archived = FALSE
        ";
        
        $params = [];
        
        if ($projectId) {
            $sql .= " AND t.project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        if ($userId) {
            $sql .= " AND t.assigned_to = :user_id";
            $params['user_id'] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            $color = '#3788d8'; // Bleu par défaut
            
            // Couleur selon le statut
            switch ($task['status']) {
                case 'completed':
                    $color = '#28a745'; // Vert
                    break;
                case 'in_progress':
                    $color = '#ffc107'; // Jaune
                    break;
                case 'blocked':
                    $color = '#dc3545'; // Rouge
                    break;
            }
            
            // Vérifier si en retard
            if ($task['due_date'] && $task['status'] !== 'completed') {
                $dueDate = new DateTime($task['due_date']);
                $now = new DateTime();
                if ($now > $dueDate) {
                    $color = '#dc3545'; // Rouge pour retard
                }
            }
            
            $events[] = [
                'id' => $task['id'],
                'title' => $task['title'],
                'start' => $task['start_date'],
                'end' => $task['due_date'],
                'color' => $color,
                'extendedProps' => [
                    'type' => 'task',
                    'description' => $task['description'],
                    'status' => $task['status'],
                    'progress' => $task['progress'],
                    'assigned_to' => $task['assigned_to'],
                    'project_title' => $task['project_title'],
                    'project_id' => $task['project_id']
                ]
            ];
        }
    }
    
    // Récupérer les jalons
    if ($type === '' || $type === 'milestone') {
        $sql = "
            SELECT 
                m.id,
                m.title,
                m.description,
                m.due_date,
                m.status,
                m.deliverables,
                p.title as project_title,
                p.id as project_id
            FROM project_milestones m
            JOIN projects p ON m.project_id = p.id
            WHERE p.archived = FALSE
        ";
        
        $params = [];
        
        if ($projectId) {
            $sql .= " AND m.project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($milestones as $milestone) {
            $color = '#28a745'; // Vert par défaut
            
            // Couleur selon le statut
            switch ($milestone['status']) {
                case 'pending':
                    $color = '#6c757d'; // Gris
                    break;
                case 'in_progress':
                    $color = '#17a2b8'; // Cyan
                    break;
                case 'delayed':
                    $color = '#dc3545'; // Rouge
                    break;
            }
            
            $events[] = [
                'id' => $milestone['id'],
                'title' => '🎯 ' . $milestone['title'],
                'start' => $milestone['due_date'],
                'allDay' => true,
                'color' => $color,
                'extendedProps' => [
                    'type' => 'milestone',
                    'description' => $milestone['description'],
                    'status' => $milestone['status'],
                    'deliverables' => $milestone['deliverables'],
                    'project_title' => $milestone['project_title'],
                    'project_id' => $milestone['project_id']
                ]
            ];
        }
    }
    
    echo json_encode($events);
    
} catch (PDOException $e) {
    error_log("Erreur calendar_events: " . $e->getMessage());
    echo json_encode([]);
}
?>
