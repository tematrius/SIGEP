<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Vérifier les permissions d'export
if (!hasPermission('view_reports')) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

$type = $_GET['type'] ?? 'projects';
$format = $_GET['format'] ?? 'csv';

try {
    $pdo = getDbConnection();
    $filename = '';
    $data = [];
    $headers = [];
    
    switch ($type) {
        case 'projects':
            $filename = 'projets_' . date('Y-m-d');
            $headers = ['ID', 'Titre', 'Description', 'Statut', 'Localisation', 'Date Début', 'Date Fin', 'Budget Estimé', 'Progression', 'Créé le'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.title,
                    p.description,
                    p.status,
                    l.name as location,
                    p.start_date,
                    p.end_date,
                    COALESCE(p.budget_estimated, 0) as total_budget,
                    COALESCE(AVG(t.progress), 0) as progress,
                    p.created_at
                FROM projects p
                LEFT JOIN locations l ON p.location_id = l.id
                LEFT JOIN tasks t ON p.id = t.project_id
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $statusLabels = [
                    'prevu' => 'Prévu',
                    'en_cours' => 'En cours',
                    'suspendu' => 'Suspendu',
                    'termine' => 'Terminé',
                    'annule' => 'Annulé'
                ];
                
                $data[] = [
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $statusLabels[$row['status']] ?? $row['status'],
                    $row['location'] ?? 'N/A',
                    $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A',
                    $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : 'N/A',
                    number_format($row['total_budget'], 0, ',', ' ') . ' FC',
                    round($row['progress'], 1) . '%',
                    date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            break;
            
        case 'tasks':
            $filename = 'taches_' . date('Y-m-d');
            $headers = ['ID', 'Titre', 'Projet', 'Statut', 'Priorité', 'Assigné à', 'Date Début', 'Date Fin', 'Progression', 'Créé le'];
            
            $stmt = $pdo->query("
                SELECT 
                    t.id,
                    t.title,
                    p.title as project,
                    t.status,
                    t.priority,
                    u.full_name as assigned_to,
                    t.start_date,
                    t.end_date,
                    t.progress,
                    t.created_at
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                ORDER BY t.created_at DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $statusLabels = [
                    'a_faire' => 'À faire',
                    'en_cours' => 'En cours',
                    'en_attente' => 'En attente',
                    'terminee' => 'Terminée'
                ];
                
                $priorityLabels = [
                    'basse' => 'Basse',
                    'moyenne' => 'Moyenne',
                    'haute' => 'Haute',
                    'critique' => 'Critique'
                ];
                
                $data[] = [
                    $row['id'],
                    $row['title'],
                    $row['project'] ?? 'N/A',
                    $statusLabels[$row['status']] ?? $row['status'],
                    $priorityLabels[$row['priority']] ?? $row['priority'],
                    $row['assigned_to'] ?? 'Non assigné',
                    $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A',
                    $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : 'N/A',
                    $row['progress'] . '%',
                    date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            break;
            
        case 'budget':
            $filename = 'budget_' . date('Y-m-d');
            $headers = ['Projet', 'Budget Estimé', 'Date Début', 'Date Fin'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.title as project,
                    p.budget_estimated,
                    p.start_date,
                    p.end_date
                FROM projects p
                WHERE p.budget_estimated > 0
                ORDER BY p.budget_estimated DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['project'],
                    number_format($row['budget_estimated'], 0, ',', ' ') . ' FC',
                    $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : 'N/A',
                    $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : 'N/A'
                ];
            }
            break;
            
        case 'risks':
            $filename = 'risques_' . date('Y-m-d');
            $headers = ['Projet', 'Description', 'Catégorie', 'Niveau', 'Probabilité', 'Impact', 'Score', 'Statut', 'Responsable', 'Date'];
            
            $stmt = $pdo->query("
                SELECT 
                    p.title as project,
                    r.description,
                    r.category,
                    CASE 
                        WHEN r.risk_score <= 4 THEN 'faible'
                        WHEN r.risk_score <= 9 THEN 'moyen'
                        WHEN r.risk_score <= 16 THEN 'eleve'
                        ELSE 'critique'
                    END as level,
                    r.probability,
                    r.impact,
                    r.risk_score,
                    r.status,
                    u.full_name as responsible,
                    r.created_at
                FROM risks r
                JOIN projects p ON r.project_id = p.id
                LEFT JOIN users u ON r.responsible_user_id = u.id
                ORDER BY r.risk_score DESC, r.created_at DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $levelLabels = [
                    'faible' => 'Faible',
                    'moyen' => 'Moyen',
                    'eleve' => 'Élevé',
                    'critique' => 'Critique'
                ];
                
                $categoryLabels = [
                    'financier' => 'Financier',
                    'technique' => 'Technique',
                    'organisationnel' => 'Organisationnel',
                    'externe' => 'Externe'
                ];
                
                $statusLabels = [
                    'identifie' => 'Identifié',
                    'en_traitement' => 'En traitement',
                    'mitige' => 'Mitigé',
                    'realise' => 'Réalisé'
                ];
                
                $data[] = [
                    $row['project'],
                    $row['description'],
                    $categoryLabels[$row['category']] ?? $row['category'],
                    $levelLabels[$row['level']] ?? $row['level'],
                    $row['probability'],
                    $row['impact'],
                    $row['risk_score'],
                    $statusLabels[$row['status']] ?? $row['status'],
                    $row['responsible'] ?? 'N/A',
                    date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            break;
            
        case 'users':
            // Vérifier permission admin
            if (!hasPermission('manage_users')) {
                setFlashMessage('error', 'Accès non autorisé');
                redirect('dashboard.php');
            }
            
            $filename = 'utilisateurs_' . date('Y-m-d');
            $headers = ['ID', 'Nom complet', 'Email', 'Nom d\'utilisateur', 'Rôle', 'Actif', 'Créé le'];
            
            $stmt = $pdo->query("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.username,
                    r.name as role,
                    u.is_active,
                    u.created_at
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                ORDER BY u.created_at DESC
            ");
            
            while ($row = $stmt->fetch()) {
                $data[] = [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['username'],
                    $row['role'] ?? 'N/A',
                    $row['is_active'] ? 'Oui' : 'Non',
                    date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            break;
            
        default:
            setFlashMessage('error', 'Type d\'export invalide');
            redirect('reports.php');
    }
    
    // Nettoyer tous les buffers de sortie avant l'export
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Générer le fichier selon le format
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes
        fputcsv($output, $headers, ';');
        
        // Données
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
    } else {
        // Format Excel (HTML)
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
        echo '<head>';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo '<style>table { border-collapse: collapse; } th, td { border: 1px solid #000; padding: 5px; }</style>';
        echo '</head>';
        echo '<body>';
        echo '<table>';
        
        // En-têtes
        echo '<thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr></thead>';
        
        // Données
        echo '<tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        
        echo '</table>';
        echo '</body>';
        echo '</html>';
    }
    
    // Log l'export
    logActivity('export_data', 'Export ' . $type . ' au format ' . $format);
    
    exit;
    
} catch (PDOException $e) {
    error_log('Export error: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors de l\'export: ' . $e->getMessage());
    redirect('reports.php');
} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    setFlashMessage('error', 'Erreur lors de l\'export: ' . $e->getMessage());
    redirect('reports.php');
}
