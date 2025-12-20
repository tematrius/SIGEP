<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    setFlashMessage('error', 'Projet non spécifié');
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Vérifier que le projet existe
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        setFlashMessage('error', 'Projet non trouvé');
        redirect('projects.php');
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? '';
        $deliverables = trim($_POST['deliverables'] ?? '');
        $order_number = intval($_POST['order_number'] ?? 0);
        
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Le titre du jalon est requis';
        }
        
        if (empty($due_date)) {
            $errors[] = 'La date d\'échéance est requise';
        }
        
        // Vérifier que la date est dans la période du projet
        if (!empty($due_date)) {
            if ($project['start_date'] && $due_date < $project['start_date']) {
                $errors[] = 'La date du jalon ne peut pas être avant le début du projet';
            }
            if ($project['end_date'] && $due_date > $project['end_date']) {
                $errors[] = 'La date du jalon ne peut pas être après la fin du projet';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO milestones 
                    (project_id, title, description, due_date, deliverables, order_number, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $project_id,
                    $title,
                    $description,
                    $due_date,
                    $deliverables,
                    $order_number,
                    $_SESSION['user_id']
                ]);
                
                $milestone_id = $pdo->lastInsertId();
                
                // Log l'activité
                logActivity(
                    "Jalon créé : $title",
                    'milestone',
                    $milestone_id
                );
                
                // Créer une notification pour le chef de projet
                if ($project['created_by'] != $_SESSION['user_id']) {
                    createNotification(
                        $project['created_by'],
                        'milestone_created',
                        "Nouveau jalon créé : $title",
                        'milestone',
                        $milestone_id
                    );
                }
                
                setFlashMessage('success', 'Jalon créé avec succès');
                redirect('project_details.php?id=' . $project_id);
                
            } catch (PDOException $e) {
                $errors[] = 'Erreur lors de la création du jalon : ' . $e->getMessage();
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

$pageTitle = 'Créer un Jalon';
ob_start();
?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-flag-checkered"></i> Créer un Jalon</h4>
                <p class="mb-0 text-muted">Projet: <?php echo e($project['title']); ?></p>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="title" class="form-label">Titre du Jalon *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo e($_POST['title'] ?? ''); ?>" 
                               placeholder="Ex: Validation de la phase 1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" 
                                  placeholder="Décrivez ce jalon et ses objectifs..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                        <small class="text-muted">Décrivez les objectifs et critères de validation de ce jalon</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Date d'Échéance *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo e($_POST['due_date'] ?? ''); ?>"
                                   min="<?php echo $project['start_date'] ?? ''; ?>"
                                   max="<?php echo $project['end_date'] ?? ''; ?>"
                                   required>
                            <?php if ($project['start_date'] && $project['end_date']): ?>
                                <small class="text-muted">
                                    Entre le <?php echo date('d/m/Y', strtotime($project['start_date'])); ?> 
                                    et le <?php echo date('d/m/Y', strtotime($project['end_date'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="order_number" class="form-label">Ordre d'Affichage</label>
                            <input type="number" class="form-control" id="order_number" name="order_number" 
                                   value="<?php echo e($_POST['order_number'] ?? 0); ?>" min="0">
                            <small class="text-muted">0 = premier, 1 = deuxième, etc.</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deliverables" class="form-label">Livrables Attendus</label>
                        <textarea class="form-control" id="deliverables" name="deliverables" rows="5" 
                                  placeholder="Listez les livrables attendus pour ce jalon..."><?php echo e($_POST['deliverables'] ?? ''); ?></textarea>
                        <small class="text-muted">
                            Un livrable par ligne. Ex:<br>
                            - Rapport d'analyse<br>
                            - Prototype fonctionnel<br>
                            - Documentation technique
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note :</strong> Les jalons représentent les étapes clés de votre projet. 
                        Ils permettent de structurer le projet en phases identifiables et mesurables.
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Créer le Jalon
                        </button>
                        <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
