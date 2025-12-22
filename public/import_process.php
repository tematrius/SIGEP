<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasPermission('manage_projects')) {
    setFlashMessage('error', 'Permission refusée');
    redirect('dashboard.php');
}

$import_type = $_POST['import_type'] ?? null;

if (!$import_type || !in_array($import_type, ['projects', 'tasks'])) {
    setFlashMessage('error', 'Type d\'import invalide');
    redirect('import.php');
}

// Vérifier le fichier
if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    setFlashMessage('error', 'Erreur lors de l\'upload du fichier');
    redirect('import.php');
}

$file = $_FILES['import_file'];
$filename = $file['name'];
$tmp_name = $file['tmp_name'];
$file_size = $file['size'];

// Vérifier la taille (5 MB max)
if ($file_size > 5 * 1024 * 1024) {
    setFlashMessage('error', 'Le fichier est trop volumineux (max 5 MB)');
    redirect('import.php');
}

// Vérifier l'extension
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($extension, ['csv', 'xls', 'xlsx'])) {
    setFlashMessage('error', 'Format de fichier non supporté');
    redirect('import.php');
}

try {
    $pdo = getDbConnection();
    
    // Créer un log d'import
    $stmt = $pdo->prepare("
        INSERT INTO import_logs (import_type, filename, status, imported_by)
        VALUES (?, ?, 'processing', ?)
    ");
    $stmt->execute([$import_type, $filename, $_SESSION['user_id']]);
    $import_log_id = $pdo->lastInsertId();
    
    // Lire le fichier
    $data = [];
    
    if ($extension === 'csv') {
        // Lire CSV
        $handle = fopen($tmp_name, 'r');
        if ($handle) {
            // Lire les en-têtes
            $headers = fgetcsv($handle, 1000, ';');
            if (!$headers) {
                $headers = fgetcsv($handle, 1000, ',');
                rewind($handle);
                fgetcsv($handle, 1000, ','); // Skip header
            }
            
            // Nettoyer les en-têtes (enlever BOM UTF-8 si présent)
            $headers = array_map(function($h) {
                return trim(str_replace("\xEF\xBB\xBF", '', $h));
            }, $headers);
            
            // Lire les données
            while (($row = fgetcsv($handle, 1000, ';')) !== false) {
                if (count($row) < count($headers)) {
                    $row = fgetcsv($handle, 1000, ',');
                }
                
                if ($row && !empty(array_filter($row))) {
                    $data[] = array_combine($headers, $row);
                }
            }
            fclose($handle);
        }
    } else {
        // Pour Excel, on va le convertir en CSV temporairement
        // Note: En production, utilisez PhpSpreadsheet pour un meilleur support Excel
        setFlashMessage('warning', 'Les fichiers Excel sont convertis en CSV. Pour un meilleur support, veuillez utiliser le format CSV.');
        
        // Tentative de lecture simple (ne fonctionne que pour les .xls simples)
        $handle = fopen($tmp_name, 'r');
        if ($handle) {
            $content = fread($handle, $file_size);
            fclose($handle);
            
            // Essayer d'extraire les données (méthode simplifiée)
            $lines = explode("\n", $content);
            if (count($lines) > 0) {
                setFlashMessage('error', 'Veuillez utiliser le format CSV pour l\'import. Les fichiers Excel nécessitent une bibliothèque supplémentaire.');
                redirect('import.php');
            }
        }
    }
    
    if (empty($data)) {
        throw new Exception('Aucune donnée trouvée dans le fichier');
    }
    
    $total_rows = count($data);
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Traiter selon le type
    if ($import_type === 'projects') {
        foreach ($data as $index => $row) {
            $line_num = $index + 2; // +2 car on commence à 1 et la première ligne est l'en-tête
            
            try {
                // Validation des champs obligatoires
                if (empty($row['title'])) {
                    throw new Exception("Titre manquant");
                }
                
                // Validation du statut
                $valid_statuses = ['prevu', 'en_cours', 'suspendu', 'termine', 'annule'];
                $status = !empty($row['status']) ? $row['status'] : 'prevu';
                if (!in_array($status, $valid_statuses)) {
                    throw new Exception("Statut invalide: $status");
                }
                
                // Validation de la priorité
                $valid_priorities = ['low', 'medium', 'high'];
                $priority = !empty($row['priority']) ? $row['priority'] : 'medium';
                if (!in_array($priority, $valid_priorities)) {
                    throw new Exception("Priorité invalide: $priority");
                }
                
                // Validation des dates
                $start_date = !empty($row['start_date']) ? $row['start_date'] : null;
                $end_date = !empty($row['end_date']) ? $row['end_date'] : null;
                
                if ($start_date && !strtotime($start_date)) {
                    throw new Exception("Date de début invalide");
                }
                if ($end_date && !strtotime($end_date)) {
                    throw new Exception("Date de fin invalide");
                }
                
                // Récupérer l'ID de la localisation
                $location_id = null;
                if (!empty($row['location_province'])) {
                    $stmt = $pdo->prepare("SELECT id FROM locations WHERE code = ?");
                    $stmt->execute([strtoupper(trim($row['location_province']))]);
                    $location = $stmt->fetch();
                    if ($location) {
                        $location_id = $location['id'];
                    }
                }
                
                // Insérer le projet
                $stmt = $pdo->prepare("
                    INSERT INTO projects (
                        title, description, context, status, priority,
                        start_date, end_date, budget_estimated, budget_validated,
                        location_id, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    trim($row['title']),
                    trim($row['description'] ?? ''),
                    trim($row['context'] ?? ''),
                    $status,
                    $priority,
                    $start_date,
                    $end_date,
                    !empty($row['budget_estimated']) ? floatval($row['budget_estimated']) : null,
                    !empty($row['budget_validated']) ? floatval($row['budget_validated']) : null,
                    $location_id,
                    $_SESSION['user_id']
                ]);
                
                $success_count++;
                
                // Log l'activité
                logActivity(
                    "Projet importé : " . trim($row['title']),
                    'project',
                    $pdo->lastInsertId()
                );
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Ligne $line_num: " . $e->getMessage();
            }
        }
        
    } else { // tasks
        foreach ($data as $index => $row) {
            $line_num = $index + 2;
            
            try {
                // Validation des champs obligatoires
                if (empty($row['project_id'])) {
                    throw new Exception("ID du projet manquant");
                }
                if (empty($row['title'])) {
                    throw new Exception("Titre manquant");
                }
                
                // Vérifier que le projet existe
                $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
                $stmt->execute([intval($row['project_id'])]);
                if (!$stmt->fetch()) {
                    throw new Exception("Projet #" . $row['project_id'] . " introuvable");
                }
                
                // Validation du statut
                $valid_statuses = ['pending', 'in_progress', 'completed', 'blocked'];
                $status = !empty($row['status']) ? $row['status'] : 'pending';
                if (!in_array($status, $valid_statuses)) {
                    throw new Exception("Statut invalide: $status");
                }
                
                // Validation de la priorité
                $valid_priorities = ['low', 'medium', 'high'];
                $priority = !empty($row['priority']) ? $row['priority'] : 'medium';
                if (!in_array($priority, $valid_priorities)) {
                    throw new Exception("Priorité invalide: $priority");
                }
                
                // Validation des dates
                $start_date = !empty($row['start_date']) ? $row['start_date'] : null;
                $end_date = !empty($row['end_date']) ? $row['end_date'] : null;
                
                if ($start_date && !strtotime($start_date)) {
                    throw new Exception("Date de début invalide");
                }
                if ($end_date && !strtotime($end_date)) {
                    throw new Exception("Date de fin invalide");
                }
                
                // Récupérer l'utilisateur assigné
                $assigned_to = null;
                if (!empty($row['assigned_to_email'])) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                    $stmt->execute([trim($row['assigned_to_email']), trim($row['assigned_to_email'])]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $assigned_to = $user['id'];
                    }
                }
                
                // Validation du progrès
                $progress = !empty($row['progress']) ? intval($row['progress']) : 0;
                if ($progress < 0 || $progress > 100) {
                    throw new Exception("Progression invalide (doit être entre 0 et 100)");
                }
                
                // Insérer la tâche
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (
                        project_id, title, description, status, priority,
                        start_date, end_date, estimated_hours, progress, assigned_to
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    intval($row['project_id']),
                    trim($row['title']),
                    trim($row['description'] ?? ''),
                    $status,
                    $priority,
                    $start_date,
                    $end_date,
                    !empty($row['estimated_hours']) ? floatval($row['estimated_hours']) : null,
                    $progress,
                    $assigned_to
                ]);
                
                $success_count++;
                
                // Log l'activité
                logActivity(
                    "Tâche importée : " . trim($row['title']),
                    'task',
                    $pdo->lastInsertId()
                );
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Ligne $line_num: " . $e->getMessage();
            }
        }
    }
    
    // Mettre à jour le log d'import
    $status = ($error_count === 0) ? 'completed' : (($success_count === 0) ? 'failed' : 'completed');
    $stmt = $pdo->prepare("
        UPDATE import_logs 
        SET status = ?, total_rows = ?, success_count = ?, error_count = ?, errors = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $status,
        $total_rows,
        $success_count,
        $error_count,
        !empty($errors) ? json_encode($errors) : null,
        $import_log_id
    ]);
    
    // Messages de résultat
    if ($success_count > 0) {
        setFlashMessage('success', "$success_count élément(s) importé(s) avec succès");
    }
    
    if ($error_count > 0) {
        $error_msg = "$error_count erreur(s) rencontrée(s):<br>";
        $error_msg .= implode('<br>', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $error_msg .= "<br>... et " . (count($errors) - 5) . " autre(s) erreur(s)";
        }
        setFlashMessage('error', $error_msg);
    }
    
    // Log l'import
    logActivity(
        "Import " . $import_type . " : $success_count succès, $error_count erreurs",
        'import',
        $import_log_id
    );
    
    redirect('import.php');
    
} catch (Exception $e) {
    error_log("Erreur import: " . $e->getMessage());
    setFlashMessage('error', 'Erreur lors de l\'import: ' . $e->getMessage());
    redirect('import.php');
}
?>
