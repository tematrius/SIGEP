<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pageTitle = 'Gestion des Utilisateurs';

// Vérifier les permissions (seulement Ministre, Directeur de Cabinet, Secrétaire Général)
$allowed_roles = ['Ministre', 'Directeur de Cabinet', 'Secrétaire Général'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer tous les utilisateurs avec leurs rôles
    $stmt = $pdo->query("
        SELECT u.*, r.name as role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.full_name
    ");
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des utilisateurs');
    $users = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users"></i> Gestion des Utilisateurs</h2>
    <a href="user_create.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Nouvel Utilisateur
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nom complet</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Téléphone</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Aucun utilisateur trouvé
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($user['full_name']); ?></strong>
                                </td>
                                <td><?php echo e($user['username']); ?></td>
                                <td><?php echo e($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo e($user['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo e($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="user_toggle.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-<?php echo $user['is_active'] ? 'secondary' : 'success'; ?>" 
                                               title="<?php echo $user['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
