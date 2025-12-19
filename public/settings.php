<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Seuls les admins peuvent accéder aux paramètres
if (!hasPermission('manage_users')) {
    setFlashMessage('error', 'Accès non autorisé');
    redirect('dashboard.php');
}

$pageTitle = 'Paramètres du Système';

try {
    $pdo = getDbConnection();
    
    // Statistiques système
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    $stats['total_projects'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $stats['total_tasks'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM task_documents");
    $stats['total_documents'] = $stmt->fetch()['count'];
    
    // Taille des uploads
    $uploadDir = '../uploads/task_documents/';
    $totalSize = 0;
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
    }
    $stats['storage_used'] = round($totalSize / (1024 * 1024), 2); // En MB
    
    // Récupérer les rôles
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll();
    
    // Récupérer les provinces
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM locations WHERE type = 'province'");
    $stats['provinces'] = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement des paramètres');
    redirect('dashboard.php');
}

ob_start();
?>

<div class="row">
    <div class="col-lg-12">
        <h2 class="mb-4"><i class="fas fa-cog"></i> Paramètres du Système</h2>
        
        <!-- Statistiques système -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['active_users']; ?></h3>
                        <small>Utilisateurs actifs</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-folder fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['total_projects']; ?></h3>
                        <small>Projets total</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-tasks fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['total_tasks']; ?></h3>
                        <small>Tâches total</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-file-alt fa-2x mb-2"></i>
                        <h3 class="mb-0"><?php echo $stats['total_documents']; ?></h3>
                        <small>Documents</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Informations Système
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td><strong>Version de l'application:</strong></td>
                                <td><?php echo APP_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Nom de l'application:</strong></td>
                                <td><?php echo APP_NAME; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Version PHP:</strong></td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Serveur Web:</strong></td>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Base de données:</strong></td>
                                <td>MySQL/MariaDB</td>
                            </tr>
                            <tr>
                                <td><strong>Espace de stockage utilisé:</strong></td>
                                <td><?php echo $stats['storage_used']; ?> MB</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-tag"></i> Rôles du Système
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($roles as $role): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-circle text-primary" style="font-size: 8px;"></i>
                                        <strong><?php echo e($role['name']); ?></strong>
                                        <?php if ($role['description']): ?>
                                            <br><small class="text-muted"><?php echo e($role['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuration -->
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sliders-h"></i> Configuration
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6>Localisation</h6>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo $stats['provinces']; ?> provinces de la RDC configurées
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6>Devise</h6>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-money-bill"></i> Franc Congolais (FC)
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6>Fuseau horaire</h6>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-clock"></i> <?php echo date_default_timezone_get(); ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6>Taille maximale de fichier</h6>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-file-upload"></i> 10 MB par fichier
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions administrateur -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tools"></i> Actions Administrateur
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-users"></i> Gérer les utilisateurs
                            </a>
                            <a href="projects.php" class="btn btn-success">
                                <i class="fas fa-folder"></i> Gérer les projets
                            </a>
                            <a href="reports.php" class="btn btn-info">
                                <i class="fas fa-chart-bar"></i> Voir les rapports
                            </a>
                            <button class="btn btn-warning" onclick="if(confirm('Voulez-vous vraiment vider le cache?')) alert('Cache vidé (fonctionnalité à implémenter)');">
                                <i class="fas fa-broom"></i> Vider le cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../views/layouts/main.php';
?>
