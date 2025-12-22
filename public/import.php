<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Vérifier les permissions
if (!hasPermission('manage_projects')) {
    setFlashMessage('error', 'Vous n\'avez pas la permission d\'importer des données');
    redirect('dashboard.php');
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-file-import"></i> Import de Données en Masse</h2>
        <p class="text-muted">Importez des projets ou des tâches depuis des fichiers Excel ou CSV</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<!-- Instructions -->
<div class="alert alert-info">
    <h5><i class="fas fa-info-circle"></i> Instructions</h5>
    <ol class="mb-0">
        <li>Téléchargez le template correspondant au type de données à importer</li>
        <li>Remplissez le fichier avec vos données</li>
        <li>Uploadez le fichier rempli</li>
        <li>Vérifiez les données avant la validation finale</li>
    </ol>
</div>

<div class="row">
    <!-- Import Projets -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-folder-open"></i> Import de Projets
            </div>
            <div class="card-body">
                <p>Importez plusieurs projets en une seule fois.</p>
                
                <h6 class="mt-3">Champs disponibles:</h6>
                <ul class="small">
                    <li><strong>title</strong> (obligatoire) - Titre du projet</li>
                    <li><strong>description</strong> - Description détaillée</li>
                    <li><strong>context</strong> - Contexte du projet</li>
                    <li><strong>status</strong> - prevu, en_cours, suspendu, termine, annule</li>
                    <li><strong>priority</strong> - low, medium, high</li>
                    <li><strong>start_date</strong> - Format: YYYY-MM-DD</li>
                    <li><strong>end_date</strong> - Format: YYYY-MM-DD</li>
                    <li><strong>budget_estimated</strong> - Montant en FC</li>
                    <li><strong>budget_validated</strong> - Montant en FC</li>
                    <li><strong>location_province</strong> - Code province (ex: KS pour Kinshasa)</li>
                </ul>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="import_template.php?type=projects&format=excel" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Télécharger Template Excel
                    </a>
                    <a href="import_template.php?type=projects&format=csv" class="btn btn-outline-success">
                        <i class="fas fa-file-csv"></i> Télécharger Template CSV
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importProjectsModal">
                        <i class="fas fa-upload"></i> Importer des Projets
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Tâches -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="fas fa-tasks"></i> Import de Tâches
            </div>
            <div class="card-body">
                <p>Importez plusieurs tâches pour un projet existant.</p>
                
                <h6 class="mt-3">Champs disponibles:</h6>
                <ul class="small">
                    <li><strong>project_id</strong> (obligatoire) - ID du projet parent</li>
                    <li><strong>title</strong> (obligatoire) - Titre de la tâche</li>
                    <li><strong>description</strong> - Description détaillée</li>
                    <li><strong>status</strong> - pending, in_progress, completed, blocked</li>
                    <li><strong>priority</strong> - low, medium, high</li>
                    <li><strong>start_date</strong> - Format: YYYY-MM-DD</li>
                    <li><strong>end_date</strong> - Format: YYYY-MM-DD</li>
                    <li><strong>estimated_hours</strong> - Heures estimées</li>
                    <li><strong>progress</strong> - Progression en % (0-100)</li>
                    <li><strong>assigned_to_email</strong> - Email de l'assigné</li>
                </ul>
                
                <div class="d-grid gap-2 mt-3">
                    <a href="import_template.php?type=tasks&format=excel" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Télécharger Template Excel
                    </a>
                    <a href="import_template.php?type=tasks&format=csv" class="btn btn-outline-success">
                        <i class="fas fa-file-csv"></i> Télécharger Template CSV
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importTasksModal">
                        <i class="fas fa-upload"></i> Importer des Tâches
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des imports -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-history"></i> Historique des Imports
    </div>
    <div class="card-body">
        <?php
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT il.*, u.full_name as imported_by_name
                FROM import_logs il
                LEFT JOIN users u ON il.imported_by = u.id
                ORDER BY il.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $imports = $stmt->fetchAll();
            
            if (empty($imports)):
        ?>
            <p class="text-muted text-center">Aucun import effectué pour le moment</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Fichier</th>
                            <th>Statut</th>
                            <th>Lignes</th>
                            <th>Succès</th>
                            <th>Erreurs</th>
                            <th>Importé par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imports as $import): ?>
                            <tr>
                                <td><small><?php echo date('d/m/Y H:i', strtotime($import['created_at'])); ?></small></td>
                                <td>
                                    <?php if ($import['import_type'] === 'projects'): ?>
                                        <span class="badge bg-primary">Projets</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Tâches</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo e($import['filename']); ?></small></td>
                                <td>
                                    <?php if ($import['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Complété</span>
                                    <?php elseif ($import['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Échoué</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">En cours</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $import['total_rows']; ?></td>
                                <td><span class="text-success"><?php echo $import['success_count']; ?></span></td>
                                <td>
                                    <?php if ($import['error_count'] > 0): ?>
                                        <span class="text-danger"><?php echo $import['error_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo e($import['imported_by_name'] ?? 'N/A'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php 
            endif;
        } catch (PDOException $e) {
            echo '<p class="text-danger">Erreur lors du chargement de l\'historique</p>';
        }
        ?>
    </div>
</div>

<!-- Modal Import Projets -->
<div class="modal fade" id="importProjectsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="import_process.php" method="POST" enctype="multipart/form-data" id="importProjectsForm">
                <input type="hidden" name="import_type" value="projects">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-import"></i> Importer des Projets</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="projectFile" class="form-label">Fichier Excel ou CSV</label>
                        <input type="file" class="form-control" id="projectFile" name="import_file" 
                               accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">Formats acceptés: .xlsx, .xls, .csv (max 5 MB)</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention:</strong> Assurez-vous que votre fichier respecte le format du template téléchargé.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Tâches -->
<div class="modal fade" id="importTasksModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="import_process.php" method="POST" enctype="multipart/form-data" id="importTasksForm">
                <input type="hidden" name="import_type" value="tasks">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-import"></i> Importer des Tâches</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="taskFile" class="form-label">Fichier Excel ou CSV</label>
                        <input type="file" class="form-control" id="taskFile" name="import_file" 
                               accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">Formats acceptés: .xlsx, .xls, .csv (max 5 MB)</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention:</strong> Assurez-vous que les project_id existent dans la base de données.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Import de Données';
include '../views/layouts/main.php';
?>
