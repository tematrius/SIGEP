<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$id = $_GET['id'] ?? null;

if (!$id) {
    setFlashMessage('error', 'Projet non spécifié');
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer les informations du projet
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as creator_name,
               COALESCE(AVG(t.progress), 0) as calculated_progress
        FROM projects p
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        setFlashMessage('error', 'Projet non trouvé');
        redirect('projects.php');
    }
    
    // Récupérer toutes les tâches avec leurs dépendances
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.title,
            t.start_date,
            t.end_date,
            t.progress,
            t.status,
            t.priority,
            u.full_name as assigned_to_name,
            GROUP_CONCAT(td.depends_on_task_id) as dependencies
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN task_dependencies td ON t.id = td.task_id
        WHERE t.project_id = ?
        GROUP BY t.id
        ORDER BY t.start_date ASC, t.priority DESC
    ");
    $stmt->execute([$id]);
    $tasks = $stmt->fetchAll();
    
    // Récupérer les jalons
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.title,
            m.due_date,
            m.status,
            m.order_number
        FROM milestones m
        WHERE m.project_id = ?
        ORDER BY m.order_number ASC, m.due_date ASC
    ");
    $stmt->execute([$id]);
    $milestones = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Erreur lors du chargement du projet');
    redirect('projects.php');
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-chart-bar"></i> Diagramme de Gantt</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                <li class="breadcrumb-item"><a href="projects.php">Projets</a></li>
                <li class="breadcrumb-item"><a href="project_details.php?id=<?php echo $project['id']; ?>"><?php echo e($project['title']); ?></a></li>
                <li class="breadcrumb-item active">Diagramme de Gantt</li>
            </ol>
        </nav>
    </div>
    <a href="project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour au projet
    </a>
</div>

<!-- Informations du projet -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-info-circle"></i> <?php echo e($project['title']); ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Statut:</strong>
                <?php
                $statusColors = [
                    'prevu' => 'secondary',
                    'en_cours' => 'primary',
                    'suspendu' => 'warning',
                    'termine' => 'success',
                    'annule' => 'danger'
                ];
                $statusLabels = [
                    'prevu' => 'Prévu',
                    'en_cours' => 'En cours',
                    'suspendu' => 'Suspendu',
                    'termine' => 'Terminé',
                    'annule' => 'Annulé'
                ];
                ?>
                <span class="badge bg-<?php echo $statusColors[$project['status']]; ?> ms-2">
                    <?php echo $statusLabels[$project['status']]; ?>
                </span>
            </div>
            <div class="col-md-3">
                <strong>Progression:</strong>
                <span class="ms-2"><?php echo round($project['calculated_progress']); ?>%</span>
            </div>
            <div class="col-md-3">
                <strong>Début:</strong>
                <span class="ms-2"><?php echo $project['start_date'] ? date('d/m/Y', strtotime($project['start_date'])) : 'N/A'; ?></span>
            </div>
            <div class="col-md-3">
                <strong>Fin prévue:</strong>
                <span class="ms-2"><?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'N/A'; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Options d'affichage -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                <label class="form-label mb-2">Vue:</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="viewMode" id="viewDay" value="Day" checked>
                    <label class="btn btn-outline-primary" for="viewDay">Jour</label>
                    
                    <input type="radio" class="btn-check" name="viewMode" id="viewWeek" value="Week">
                    <label class="btn btn-outline-primary" for="viewWeek">Semaine</label>
                    
                    <input type="radio" class="btn-check" name="viewMode" id="viewMonth" value="Month">
                    <label class="btn btn-outline-primary" for="viewMonth">Mois</label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showMilestones" checked>
                    <label class="form-check-label" for="showMilestones">
                        Afficher les jalons
                    </label>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success" onclick="exportGanttToPNG()">
                    <i class="fas fa-download"></i> Exporter PNG
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Légende -->
<div class="card mb-4">
    <div class="card-body">
        <strong class="me-3">Légende:</strong>
        <span class="badge bg-success me-2"><i class="fas fa-circle"></i> Terminé</span>
        <span class="badge bg-primary me-2"><i class="fas fa-circle"></i> En cours</span>
        <span class="badge bg-warning me-2"><i class="fas fa-circle"></i> En attente</span>
        <span class="badge bg-danger me-2"><i class="fas fa-circle"></i> En retard</span>
        <span class="badge bg-secondary me-2"><i class="fas fa-flag"></i> Jalon</span>
    </div>
</div>

<!-- Diagramme de Gantt -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-tasks"></i> Diagramme de Gantt
    </div>
    <div class="card-body">
        <div id="gantt" style="overflow-x: auto; overflow-y: auto;"></div>
        
        <?php if (empty($tasks) && empty($milestones)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                <p>Aucune tâche ou jalon défini pour ce projet</p>
                <a href="task_create.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Créer une tâche
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Inclure Frappe Gantt CSS et JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.css">
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// Préparer les données pour le Gantt
const tasks = <?php echo json_encode($tasks); ?>;
const milestones = <?php echo json_encode($milestones); ?>;

// Convertir les tâches au format Frappe Gantt
const ganttTasks = [];

// Ajouter les tâches
tasks.forEach(task => {
    const startDate = task.start_date || '<?php echo $project['start_date']; ?>';
    const endDate = task.end_date || '<?php echo $project['end_date']; ?>';
    
    // Déterminer la couleur selon le statut
    let customClass = '';
    if (task.status === 'completed') {
        customClass = 'bar-success';
    } else if (task.status === 'in_progress') {
        customClass = 'bar-primary';
    } else if (task.status === 'pending') {
        customClass = 'bar-warning';
    }
    
    // Vérifier si la tâche est en retard
    const today = new Date();
    const taskEndDate = new Date(endDate);
    if (task.status !== 'completed' && taskEndDate < today) {
        customClass = 'bar-danger';
    }
    
    const ganttTask = {
        id: 'task-' + task.id,
        name: task.title,
        start: startDate,
        end: endDate,
        progress: parseInt(task.progress) || 0,
        dependencies: task.dependencies ? task.dependencies.split(',').map(dep => 'task-' + dep).join(',') : '',
        custom_class: customClass
    };
    
    ganttTasks.push(ganttTask);
});

// Ajouter les jalons si activés
function addMilestones() {
    if (document.getElementById('showMilestones').checked) {
        milestones.forEach(milestone => {
            const dueDate = milestone.due_date;
            
            // Les jalons sont représentés comme des tâches d'un jour
            const ganttMilestone = {
                id: 'milestone-' + milestone.id,
                name: '🎯 ' + milestone.title,
                start: dueDate,
                end: dueDate,
                progress: milestone.status === 'completed' ? 100 : 0,
                dependencies: '',
                custom_class: 'bar-milestone'
            };
            
            ganttTasks.push(ganttMilestone);
        });
    }
}

addMilestones();

// Initialiser le Gantt
let gantt = null;

function initGantt(viewMode = 'Week') {
    if (ganttTasks.length === 0) {
        return;
    }
    
    const ganttContainer = document.getElementById('gantt');
    ganttContainer.innerHTML = ''; // Nettoyer
    
    try {
        gantt = new Gantt("#gantt", ganttTasks, {
            view_mode: viewMode,
            language: 'fr',
            date_format: 'DD/MM/YYYY',
            header_height: 50,
            column_width: 30,
            step: 24,
            bar_height: 30,
            bar_corner_radius: 3,
            arrow_curve: 5,
            padding: 18,
            view_modes: ['Day', 'Week', 'Month'],
            popup_trigger: 'click',
            custom_popup_html: function(task) {
                const isMilestone = task.id.startsWith('milestone-');
                const progress = task.progress;
                
                let html = `
                    <div class="gantt-popup">
                        <h5>${task.name}</h5>
                        <p><strong>Début:</strong> ${task._start.toLocaleDateString('fr-FR')}</p>
                        <p><strong>Fin:</strong> ${task._end.toLocaleDateString('fr-FR')}</p>
                `;
                
                if (!isMilestone) {
                    html += `<p><strong>Progression:</strong> ${progress}%</p>`;
                }
                
                const duration = Math.ceil((task._end - task._start) / (1000 * 60 * 60 * 24));
                html += `<p><strong>Durée:</strong> ${duration} jour(s)</p>`;
                
                html += `</div>`;
                return html;
            }
        });
    } catch (error) {
        console.error('Erreur initialisation Gantt:', error);
        ganttContainer.innerHTML = '<div class="alert alert-danger">Erreur lors de l\'affichage du diagramme</div>';
    }
}

// Initialiser au chargement
if (ganttTasks.length > 0) {
    initGantt('Week');
}

// Gérer le changement de vue
document.querySelectorAll('input[name="viewMode"]').forEach(radio => {
    radio.addEventListener('change', function() {
        initGantt(this.value);
    });
});

// Gérer l'affichage des jalons
document.getElementById('showMilestones').addEventListener('change', function() {
    // Recharger les données
    ganttTasks.length = 0;
    
    // Ré-ajouter les tâches
    tasks.forEach(task => {
        const startDate = task.start_date || '<?php echo $project['start_date']; ?>';
        const endDate = task.end_date || '<?php echo $project['end_date']; ?>';
        
        let customClass = '';
        if (task.status === 'completed') {
            customClass = 'bar-success';
        } else if (task.status === 'in_progress') {
            customClass = 'bar-primary';
        } else if (task.status === 'pending') {
            customClass = 'bar-warning';
        }
        
        const today = new Date();
        const taskEndDate = new Date(endDate);
        if (task.status !== 'completed' && taskEndDate < today) {
            customClass = 'bar-danger';
        }
        
        ganttTasks.push({
            id: 'task-' + task.id,
            name: task.title,
            start: startDate,
            end: endDate,
            progress: parseInt(task.progress) || 0,
            dependencies: task.dependencies ? task.dependencies.split(',').map(dep => 'task-' + dep).join(',') : '',
            custom_class: customClass
        });
    });
    
    // Ajouter les jalons si cochés
    addMilestones();
    
    // Réinitialiser le Gantt
    const currentViewMode = document.querySelector('input[name="viewMode"]:checked').value;
    initGantt(currentViewMode);
});

// Fonction d'export PNG
function exportGanttToPNG() {
    const ganttElement = document.getElementById('gantt');
    
    if (!ganttElement || ganttTasks.length === 0) {
        alert('Aucun diagramme à exporter');
        return;
    }
    
    // Utiliser html2canvas pour capturer le diagramme
    html2canvas(ganttElement, {
        backgroundColor: '#ffffff',
        scale: 2
    }).then(canvas => {
        // Créer un lien de téléchargement
        const link = document.createElement('a');
        link.download = 'gantt-<?php echo e($project['title']); ?>-' + new Date().toISOString().split('T')[0] + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }).catch(error => {
        console.error('Erreur export PNG:', error);
        alert('Erreur lors de l\'export');
    });
}
</script>

<style>
/* Styles personnalisés pour le Gantt */
.gantt .bar {
    transition: all 0.3s ease;
}

.gantt .bar:hover {
    opacity: 0.8;
    cursor: pointer;
}

.gantt .bar-success {
    fill: #28a745 !important;
}

.gantt .bar-primary {
    fill: #0d6efd !important;
}

.gantt .bar-warning {
    fill: #ffc107 !important;
}

.gantt .bar-danger {
    fill: #dc3545 !important;
}

.gantt .bar-milestone {
    fill: #6c757d !important;
}

.gantt-popup {
    padding: 15px;
    min-width: 250px;
}

.gantt-popup h5 {
    margin-bottom: 10px;
    font-size: 1rem;
    font-weight: bold;
    color: #333;
}

.gantt-popup p {
    margin: 5px 0;
    font-size: 0.9rem;
}

#gantt {
    min-height: 400px;
    max-height: 800px;
}

/* Styles responsive */
@media (max-width: 768px) {
    #gantt {
        font-size: 12px;
    }
    
    .gantt .bar {
        height: 20px !important;
    }
}
</style>

<?php
$content = ob_get_clean();
$pageTitle = 'Diagramme de Gantt - ' . $project['title'];
include '../views/layouts/main.php';
?>
