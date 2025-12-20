<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Jalon non spécifié');
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer le jalon
    $stmt = $pdo->prepare("
        SELECT m.*, p.title as project_title, p.id as project_id, u.full_name as created_by_name
        FROM milestones m
        JOIN projects p ON m.project_id = p.id
        JOIN users u ON m.created_by = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $milestone = $stmt->fetch();
    
    if (!$milestone) {
        setFlashMessage('error', 'Jalon non trouvé');
        redirect('projects.php');
    }
    
    $project_id = $milestone['project_id'];
    
    // Récupérer le projet pour validation
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? '';
        $status = $_POST['status'] ?? 'pending';
        $completion_date = $_POST['completion_date'] ?? null;
        $deliverables = trim($_POST['deliverables'] ?? '');
        $order_number = intval($_POST['order_number'] ?? 0);
        
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Le titre du jalon est requis';
        }
        
        if (empty($due_date)) {
            $errors[] = 'La date d\'échéance est requise';
        }
        
        // Vérifier les dates
        if (!empty($due_date)) {
            if ($project['start_date'] && $due_date < $project['start_date']) {
                $errors[] = 'La date du jalon ne peut pas être avant le début du projet';
            }
            if ($project['end_date'] && $due_date > $project['end_date']) {
                $errors[] = 'La date du jalon ne peut pas être après la fin du projet';
            }
        }
        
        // Si le statut est completé, la date de complétion est requise
        if ($status === 'completed' && empty($completion_date)) {
            $completion_date = date('Y-m-d');
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE milestones 
                    SET title = ?, 
                        description = ?, 
                        due_date = ?, 
                        status = ?,
                        completion_date = ?,
                        deliverables = ?,
                        order_number = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $title,
                    $description,
                    $due_date,
                    $status,
                    $completion_date,
                    $deliverables,
                    $order_number,
                    $id
                ]);
                
                // Log l'activité
                logActivity(
                    "Jalon modifié : $title (Statut: $status)",
                    'milestone',
                    $id
                );
                
                setFlashMessage('success', 'Jalon modifié avec succès');
                redirect('project_details.php?id=' . $project_id);
                
            } catch (PDOException $e) {
                $errors[] = 'Erreur lors de la modification du jalon';
            }
        }
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
    }
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur de base de données');
    redirect('projects.php');
}

$pageTitle = 'Modifier le Jalon';
ob_start();
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-flag-checkered"></i> Modifier le Jalon</h4>
                <p class="mb-0 text-muted">Projet: <?php echo e($milestone['project_title']); ?></p>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="title" class="form-label">Titre du Jalon *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo e($milestone['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($milestone['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Statut *</label>
                            <select class="form-select" id="status" name="status" required onchange="updateCompletionDate()">
                                <option value="pending" <?php echo $milestone['status'] === 'pending' ? 'selected' : ''; ?>>
                                    En attente
                                </option>
                                <option value="in_progress" <?php echo $milestone['status'] === 'in_progress' ? 'selected' : ''; ?>>
                                    En cours
                                </option>
                                <option value="completed" <?php echo $milestone['status'] === 'completed' ? 'selected' : ''; ?>>
                                    Complété
                                </option>
                                <option value="delayed" <?php echo $milestone['status'] === 'delayed' ? 'selected' : ''; ?>>
                                    En retard
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="due_date" class="form-label">Date d'Échéance *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo $milestone['due_date']; ?>"
                                   min="<?php echo $project['start_date'] ?? ''; ?>"
                                   max="<?php echo $project['end_date'] ?? ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="completion_date" class="form-label">Date de Complétion</label>
                            <input type="date" class="form-control" id="completion_date" name="completion_date" 
                                   value="<?php echo $milestone['completion_date'] ?? ''; ?>">
                            <small class="text-muted">Remplie automatiquement si complété</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="order_number" class="form-label">Ordre d'Affichage</label>
                        <input type="number" class="form-control" id="order_number" name="order_number" 
                               value="<?php echo $milestone['order_number']; ?>" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="deliverables" class="form-label">Livrables Attendus</label>
                        <textarea class="form-control" id="deliverables" name="deliverables" rows="5"><?php echo e($milestone['deliverables']); ?></textarea>
                    </div>
                    
                    <div class="alert alert-secondary">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Créé par <?php echo e($milestone['created_by_name']); ?> 
                            le <?php echo date('d/m/Y à H:i', strtotime($milestone['created_at'])); ?>
                            <?php if ($milestone['updated_at'] != $milestone['created_at']): ?>
                                | Modifié le <?php echo date('d/m/Y à H:i', strtotime($milestone['updated_at'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <a href="milestone_delete.php?id=<?php echo $id; ?>" 
                           class="btn btn-danger ms-auto" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce jalon ?');">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function updateCompletionDate() {
    const status = document.getElementById('status').value;
    const completionDateField = document.getElementById('completion_date');
    
    if (status === 'completed' && !completionDateField.value) {
        completionDateField.value = new Date().toISOString().split('T')[0];
    }
}
</script>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
