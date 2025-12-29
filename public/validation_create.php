<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$entity_type = $_GET['type'] ?? $_POST['entity_type'] ?? null;
$entity_id = $_GET['id'] ?? $_POST['entity_id'] ?? null;

if (!$entity_type || !$entity_id) {
    $_SESSION['error'] = "Paramètres manquants";
    redirect('dashboard.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer les informations de l'entité
    $entity_info = [];
    switch ($entity_type) {
        case 'project':
            $stmt = $pdo->prepare("SELECT id, title as name FROM projects WHERE id = ?");
            $stmt->execute([$entity_id]);
            $entity_info = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'task':
            $stmt = $pdo->prepare("SELECT id, title as name FROM tasks WHERE id = ?");
            $stmt->execute([$entity_id]);
            $entity_info = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'budget':
            $stmt = $pdo->prepare("SELECT id, description as name FROM budget_items WHERE id = ?");
            $stmt->execute([$entity_id]);
            $entity_info = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
    
    if (!$entity_info) {
        $_SESSION['error'] = "Élément non trouvé";
        redirect('dashboard.php');
    }
    
    // Récupérer les utilisateurs pouvant valider
    $stmt = $pdo->query("
        SELECT id, full_name, role
        FROM users
        WHERE active = TRUE AND role IN ('admin', 'gestionnaire', 'directeur')
        ORDER BY role, full_name
    ");
    $validators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $workflow_name = $_POST['workflow_name'];
        $approvers = $_POST['approvers'] ?? [];
        
        if (empty($approvers)) {
            $_SESSION['error'] = "Veuillez sélectionner au moins un approbateur";
        } else {
            // Créer le workflow
            $stmt = $pdo->prepare("
                INSERT INTO validation_workflows 
                (entity_type, entity_id, workflow_name, total_steps, initiated_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $entity_type,
                $entity_id,
                $workflow_name,
                count($approvers),
                $_SESSION['user_id']
            ]);
            
            $workflow_id = $pdo->lastInsertId();
            
            // Créer les étapes
            $step_number = 1;
            foreach ($approvers as $approver_id) {
                $stmt = $pdo->prepare("
                    SELECT full_name, role FROM users WHERE id = ?
                ");
                $stmt->execute([$approver_id]);
                $approver = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("
                    INSERT INTO validation_steps 
                    (workflow_id, step_number, step_name, approver_id, approver_role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $workflow_id,
                    $step_number,
                    "Validation par " . $approver['full_name'],
                    $approver_id,
                    $approver['role']
                ]);
                
                $step_number++;
            }
            
            // Enregistrer dans l'historique
            $first_step_id = $pdo->lastInsertId() - count($approvers) + 1;
            $stmt = $pdo->prepare("
                INSERT INTO validation_history (workflow_id, step_id, action, user_id, comments)
                VALUES (?, ?, 'submitted', ?, ?)
            ");
            $stmt->execute([
                $workflow_id,
                $first_step_id,
                $_SESSION['user_id'],
                'Workflow de validation initié'
            ]);
            
            // Notifier le premier approbateur
            createNotification(
                $approvers[0],
                "Nouvelle demande de validation pour : " . $entity_info['name'],
                $entity_type,
                $entity_id
            );
            
            logActivity("Workflow de validation créé", $entity_type, $entity_id);
            
            $_SESSION['success'] = "Demande de validation créée avec succès";
            redirect('validation_track.php?id=' . $workflow_id);
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur validation: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la création du workflow";
}

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-check-double"></i> Créer un Workflow de Validation</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Élément à valider:</strong> <?php echo htmlspecialchars($entity_info['name']); ?>
                        <br><strong>Type:</strong> <?php echo ucfirst($entity_type); ?>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($entity_type); ?>">
                        <input type="hidden" name="entity_id" value="<?php echo htmlspecialchars($entity_id); ?>">

                        <div class="mb-3">
                            <label for="workflow_name" class="form-label">Nom du Workflow *</label>
                            <input type="text" class="form-control" id="workflow_name" name="workflow_name" 
                                   value="Validation - <?php echo htmlspecialchars($entity_info['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Approbateurs (dans l'ordre) *</label>
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted small">Sélectionnez les approbateurs dans l'ordre où ils doivent valider</p>
                                    <div id="approvers-container">
                                        <?php foreach ($validators as $validator): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input approver-check" type="checkbox" 
                                                       name="approvers[]" value="<?php echo $validator['id']; ?>"
                                                       id="approver_<?php echo $validator['id']; ?>"
                                                       data-name="<?php echo htmlspecialchars($validator['full_name']); ?>"
                                                       data-role="<?php echo $validator['role']; ?>">
                                                <label class="form-check-label" for="approver_<?php echo $validator['id']; ?>">
                                                    <?php echo htmlspecialchars($validator['full_name']); ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($validator['role']); ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ordre de validation</label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <ol id="validation-order" class="mb-0">
                                        <li class="text-muted">Sélectionnez des approbateurs...</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" onclick="history.back()">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane"></i> Soumettre pour Validation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const checkboxes = document.querySelectorAll('.approver-check');
const orderList = document.getElementById('validation-order');

checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', updateOrder);
});

function updateOrder() {
    const selected = Array.from(checkboxes)
        .filter(cb => cb.checked)
        .map(cb => ({
            name: cb.getAttribute('data-name'),
            role: cb.getAttribute('data-role')
        }));
    
    if (selected.length === 0) {
        orderList.innerHTML = '<li class="text-muted">Sélectionnez des approbateurs...</li>';
    } else {
        orderList.innerHTML = selected.map((item, index) => 
            `<li><strong>Étape ${index + 1}:</strong> ${item.name} <span class="badge bg-secondary">${item.role}</span></li>`
        ).join('');
    }
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Créer Workflow de Validation";
include '../views/layouts/main.php';
?>
