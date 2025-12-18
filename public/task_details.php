<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Tâche non trouvée');
    redirect('tasks.php');
}

$pageTitle = 'Détails de la Tâche';

try {
    $pdo = getDbConnection();
    
    // Récupérer la tâche
    $stmt = $pdo->prepare("
        SELECT t.*, p.title as project_title, u.full_name as assigned_user_name,
               pt.title as parent_task_title
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN tasks pt ON t.parent_task_id = pt.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        setFlashMessage('error', 'Tâche non trouvée');
        redirect('tasks.php');
    }
    
    // Récupérer les sous-tâches
    $stmtSubtasks = $pdo->prepare("
        SELECT t.*, u.full_name as assigned_user_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.parent_task_id = ?
        ORDER BY t.priority DESC, t.end_date ASC
    ");
    $stmtSubtasks->execute([$id]);
    $subtasks = $stmtSubtasks->fetchAll();
    
    // Récupérer les commentaires
    $stmtComments = $pdo->prepare("
        SELECT c.*, u.full_name as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.task_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmtComments->execute([$id]);
    $comments = $stmtComments->fetchAll();
    
    // Récupérer les documents justificatifs
    $stmtDocuments = $pdo->prepare("
        SELECT td.*, u.full_name as uploader_name
        FROM task_documents td
        JOIN users u ON td.uploaded_by = u.id
        WHERE td.task_id = ?
        ORDER BY td.created_at DESC
    ");
    $stmtDocuments->execute([$id]);
    $documents = $stmtDocuments->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement de la tâche');
    redirect('tasks.php');
}

// Traitement du commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    
    if (!empty($comment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO comments (task_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $comment]);
            setFlashMessage('success', 'Commentaire ajouté');
            redirect('task_details.php?id=' . $id);
        } catch (PDOException $e) {
            setFlashMessage('error', 'Erreur lors de l\'ajout du commentaire');
        }
    }
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tasks"></i> <?php echo e($task['title']); ?></h2>
    <div>
        <a href="task_edit.php?id=<?php echo $task['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Modifier
        </a>
        <a href="project_details.php?id=<?php echo $task['project_id']; ?>" class="btn btn-info">
            <i class="fas fa-folder-open"></i> Voir le projet
        </a>
        <a href="tasks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<div class="row">
    <!-- Informations de la tâche -->
    <div class="col-lg-8 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Informations de la Tâche
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Projet:</strong>
                        <a href="project_details.php?id=<?php echo $task['project_id']; ?>">
                            <?php echo e($task['project_title']); ?>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <strong>Statut:</strong>
                        <?php
                        $statusColors = [
                            'non_demarree' => 'secondary',
                            'en_cours' => 'primary',
                            'en_pause' => 'warning',
                            'terminee' => 'success',
                            'annulee' => 'danger'
                        ];
                        $statusLabels = [
                            'non_demarree' => 'Non démarrée',
                            'en_cours' => 'En cours',
                            'en_pause' => 'En pause',
                            'terminee' => 'Terminée',
                            'annulee' => 'Annulée'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $statusColors[$task['status']]; ?> ms-2">
                            <?php echo $statusLabels[$task['status']]; ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($task['description']): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Description:</strong>
                        <p class="mt-2"><?php echo nl2br(e($task['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Assigné à:</strong> <?php echo e($task['assigned_user_name'] ?? 'Non assignée'); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Priorité:</strong>
                        <?php
                        $priorityColors = ['faible' => 'info', 'moyenne' => 'warning', 'haute' => 'danger', 'critique' => 'danger'];
                        ?>
                        <span class="badge bg-<?php echo $priorityColors[$task['priority']]; ?> ms-2">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($task['parent_task_title']): ?>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Tâche parente:</strong> <?php echo e($task['parent_task_title']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Date de début:</strong> <?php echo $task['start_date'] ? date('d/m/Y', strtotime($task['start_date'])) : 'Non définie'; ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Date de fin:</strong> 
                        <?php 
                        if ($task['end_date']) {
                            echo date('d/m/Y', strtotime($task['end_date']));
                            if ($task['status'] !== 'terminee' && strtotime($task['end_date']) < time()) {
                                echo ' <span class="badge bg-danger">En retard</span>';
                            }
                        } else {
                            echo 'Non définie';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Progression:</strong>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $task['progress'] < 30 ? 'danger' : ($task['progress'] < 70 ? 'warning' : 'success'); ?>" 
                                 role="progressbar" 
                                 style="width: <?php echo $task['progress']; ?>%">
                                <?php echo $task['progress']; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Créée le <?php echo date('d/m/Y H:i', strtotime($task['created_at'])); ?>
                            <?php if ($task['updated_at'] != $task['created_at']): ?>
                                | Modifiée le <?php echo date('d/m/Y H:i', strtotime($task['updated_at'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Validation de la tâche -->
        <?php if ($task['assigned_to'] == $_SESSION['user_id'] && $task['status'] !== 'terminee'): ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-check-circle"></i> Validation de la Tâche
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Pour marquer cette tâche comme terminée, vous devez d'abord uploader au moins un document justificatif.
                </div>
                
                <!-- Formulaire d'upload de documents multiples -->
                <form method="POST" action="task_upload_document.php" enctype="multipart/form-data" class="mb-3" id="uploadForm">
                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                    
                    <div id="filesContainer">
                        <!-- Premier fichier -->
                        <div class="file-upload-item border rounded p-3 mb-3" data-index="0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="fas fa-file"></i> Document 1</h6>
                                <button type="button" class="btn btn-sm btn-danger remove-file" style="display: none;" onclick="removeFileItem(0)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Fichier *</label>
                                <input type="file" class="form-control" name="documents[]" required>
                                <small class="text-muted">
                                    Formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP, RAR (Max: 10 MB)
                                </small>
                            </div>
                            
                            <div class="mb-0">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description du document (optionnel)"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="addFileInput()">
                            <i class="fas fa-plus"></i> Ajouter un autre fichier
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Uploader les documents
                        </button>
                    </div>
                </form>
                
                <script>
                let fileIndex = 1;
                
                function addFileInput() {
                    const container = document.getElementById('filesContainer');
                    const newItem = document.createElement('div');
                    newItem.className = 'file-upload-item border rounded p-3 mb-3';
                    newItem.setAttribute('data-index', fileIndex);
                    
                    newItem.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-file"></i> Document ${fileIndex + 1}</h6>
                            <button type="button" class="btn btn-sm btn-danger remove-file" onclick="removeFileItem(${fileIndex})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Fichier *</label>
                            <input type="file" class="form-control" name="documents[]" required>
                            <small class="text-muted">
                                Formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP, RAR (Max: 10 MB)
                            </small>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="descriptions[]" rows="2" placeholder="Description du document (optionnel)"></textarea>
                        </div>
                    `;
                    
                    container.appendChild(newItem);
                    fileIndex++;
                    
                    // Afficher le bouton de suppression sur tous les items s'il y en a plus d'un
                    updateRemoveButtons();
                }
                
                function removeFileItem(index) {
                    const item = document.querySelector(`[data-index="${index}"]`);
                    if (item) {
                        item.remove();
                        updateRemoveButtons();
                        updateFileNumbers();
                    }
                }
                
                function updateRemoveButtons() {
                    const items = document.querySelectorAll('.file-upload-item');
                    const removeButtons = document.querySelectorAll('.remove-file');
                    
                    if (items.length > 1) {
                        removeButtons.forEach(btn => btn.style.display = 'block');
                    } else {
                        removeButtons.forEach(btn => btn.style.display = 'none');
                    }
                }
                
                function updateFileNumbers() {
                    const items = document.querySelectorAll('.file-upload-item');
                    items.forEach((item, index) => {
                        const title = item.querySelector('h6');
                        if (title) {
                            title.innerHTML = `<i class="fas fa-file"></i> Document ${index + 1}`;
                        }
                    });
                }
                </script>
                
                <!-- Bouton de validation (si documents présents) -->
                <?php if (!empty($documents)): ?>
                <hr>
                <form method="POST" action="task_mark_complete.php" onsubmit="return confirm('Êtes-vous sûr de vouloir marquer cette tâche comme terminée ?');">
                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-check-double"></i> Marquer la tâche comme terminée
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Documents justificatifs -->
        <?php if (!empty($documents)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-file-alt"></i> Documents Justificatifs
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($documents as $doc): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <i class="fas fa-file-<?php echo $doc['file_type'] === 'pdf' ? 'pdf' : ($doc['file_type'] === 'doc' || $doc['file_type'] === 'docx' ? 'word' : ($doc['file_type'] === 'xls' || $doc['file_type'] === 'xlsx' ? 'excel' : 'alt')); ?>"></i>
                                        <?php echo e($doc['file_name']); ?>
                                    </h6>
                                    <?php if ($doc['description']): ?>
                                        <p class="mb-1"><?php echo nl2br(e($doc['description'])); ?></p>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Uploadé par <?php echo e($doc['uploader_name']); ?> 
                                        le <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                                        (<?php echo round($doc['file_size'] / 1024, 2); ?> KB)
                                    </small>
                                </div>
                                <div class="ms-3">
                                    <a href="<?php echo BASE_URL . $doc['file_path']; ?>" class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Sous-tâches -->
        <?php if (!empty($subtasks)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list"></i> Sous-tâches
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($subtasks as $subtask): ?>
                        <a href="task_details.php?id=<?php echo $subtask['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo e($subtask['title']); ?></h6>
                                    <small class="text-muted">
                                        Assigné à: <?php echo e($subtask['assigned_user_name'] ?? 'Non assignée'); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $statusColors[$subtask['status']]; ?>">
                                        <?php echo $statusLabels[$subtask['status']]; ?>
                                    </span>
                                    <div class="progress mt-2" style="width: 100px; height: 20px;">
                                        <div class="progress-bar" style="width: <?php echo $subtask['progress']; ?>%">
                                            <?php echo $subtask['progress']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Commentaires -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-comments"></i> Commentaires
            </div>
            <div class="card-body">
                <!-- Formulaire d'ajout de commentaire -->
                <form method="POST" action="" class="mb-4">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" placeholder="Ajouter un commentaire..." required></textarea>
                    </div>
                    <button type="submit" name="add_comment" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Ajouter un commentaire
                    </button>
                </form>
                
                <!-- Liste des commentaires -->
                <?php if (empty($comments)): ?>
                    <p class="text-muted text-center">Aucun commentaire pour le moment</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo e($comment['user_name']); ?></strong>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></small>
                            </div>
                            <p class="mt-2 mb-0"><?php echo nl2br(e($comment['comment'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Actions Rapides
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="task_edit.php?id=<?php echo $task['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Modifier la tâche
                    </a>
                    <?php if (empty($subtasks)): ?>
                        <a href="task_create.php?project_id=<?php echo $task['project_id']; ?>&parent_task_id=<?php echo $task['id']; ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Ajouter une sous-tâche
                        </a>
                    <?php endif; ?>
                    <a href="project_details.php?id=<?php echo $task['project_id']; ?>" class="btn btn-info">
                        <i class="fas fa-folder-open"></i> Voir le projet
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
