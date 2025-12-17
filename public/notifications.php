<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Notifications';

try {
    $pdo = getDbConnection();
    
    // Récupérer toutes les notifications de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des notifications');
    $notifications = [];
}

// Marquer comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        setFlashMessage('success', 'Toutes les notifications ont été marquées comme lues');
        redirect('notifications.php');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Erreur lors de la mise à jour');
    }
}

// Marquer une notification comme lue
if (isset($_GET['mark_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['mark_read'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Ignorer l'erreur
    }
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-bell"></i> Notifications</h2>
    <form method="POST" action="">
        <button type="submit" name="mark_all_read" class="btn btn-primary">
            <i class="fas fa-check-double"></i> Tout marquer comme lu
        </button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                <p class="text-muted">Aucune notification</p>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notif): ?>
                    <div class="list-group-item <?php echo !$notif['is_read'] ? 'list-group-item-primary' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <?php
                                    $typeIcons = [
                                        'task_assigned' => 'fa-tasks text-primary',
                                        'task_updated' => 'fa-edit text-info',
                                        'project_created' => 'fa-folder-plus text-success',
                                        'project_updated' => 'fa-folder text-warning',
                                        'risk_identified' => 'fa-exclamation-triangle text-danger',
                                        'comment_added' => 'fa-comment text-info',
                                        'deadline_approaching' => 'fa-clock text-warning'
                                    ];
                                    $icon = $typeIcons[$notif['type']] ?? 'fa-bell text-secondary';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                    <h6 class="mb-0">
                                        <?php echo e($notif['title']); ?>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge bg-primary ms-2">Nouveau</span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                
                                <p class="mb-2"><?php echo e($notif['message']); ?></p>
                                
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?>
                                </small>
                                
                                <!-- Liens contextuels -->
                                <?php if ($notif['related_project_id']): ?>
                                    <a href="project_details.php?id=<?php echo $notif['related_project_id']; ?>&mark_read=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fas fa-folder-open"></i> Voir le projet
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($notif['related_task_id']): ?>
                                    <a href="task_details.php?id=<?php echo $notif['related_task_id']; ?>&mark_read=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fas fa-tasks"></i> Voir la tâche
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$notif['is_read']): ?>
                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline-success ms-3">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
