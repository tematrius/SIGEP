<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer tous les projets actifs
    $stmt = $pdo->query("
        SELECT id, title, status
        FROM projects
        WHERE archived = FALSE
        ORDER BY title
    ");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer tous les utilisateurs
    $stmt = $pdo->query("
        SELECT id, full_name
        FROM users
        WHERE active = TRUE
        ORDER BY full_name
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur calendrier: " . $e->getMessage());
    $projects = $users = [];
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-calendar-alt"></i> Calendrier des Projets</h2>
        <p class="text-muted">Vue calendrier de toutes les tâches et jalons</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filtres
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Projet</label>
                <select class="form-select" id="filterProject">
                    <option value="">Tous les projets</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Utilisateur</label>
                <select class="form-select" id="filterUser">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select class="form-select" id="filterType">
                    <option value="">Tous</option>
                    <option value="task">Tâches</option>
                    <option value="milestone">Jalons</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="button" class="btn btn-primary" onclick="loadEvents()">
                        <i class="fas fa-search"></i> Appliquer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendrier -->
<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal Détails Événement -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">Détails</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Contenu chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" id="eventDetailLink" class="btn btn-primary" target="_blank">Voir détails</a>
            </div>
        </div>
    </div>
</div>

<!-- Légende -->
<div class="card mt-3">
    <div class="card-body">
        <strong class="me-3">Légende:</strong>
        <span class="badge bg-primary me-2"><i class="fas fa-circle"></i> Tâches</span>
        <span class="badge bg-success me-2"><i class="fas fa-circle"></i> Jalons</span>
        <span class="badge bg-warning me-2"><i class="fas fa-circle"></i> En cours</span>
        <span class="badge bg-danger me-2"><i class="fas fa-circle"></i> En retard</span>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/fr.global.min.js"></script>

<script>
let calendar;

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'fr',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: "Aujourd'hui",
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour',
            list: 'Liste'
        },
        height: 'auto',
        editable: true,
        droppable: true,
        eventDrop: function(info) {
            updateEventDate(info);
        },
        eventResize: function(info) {
            updateEventDate(info);
        },
        eventClick: function(info) {
            showEventDetails(info.event);
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            loadEvents(successCallback, failureCallback);
        }
    });
    
    calendar.render();
});

function loadEvents(successCallback, failureCallback) {
    const projectId = document.getElementById('filterProject').value;
    const userId = document.getElementById('filterUser').value;
    const type = document.getElementById('filterType').value;
    
    fetch('calendar_events.php?' + new URLSearchParams({
        project_id: projectId,
        user_id: userId,
        type: type
    }))
    .then(response => response.json())
    .then(data => {
        if (successCallback) {
            successCallback(data);
        } else {
            calendar.removeAllEvents();
            calendar.addEventSource(data);
        }
    })
    .catch(error => {
        console.error('Erreur chargement événements:', error);
        if (failureCallback) {
            failureCallback(error);
        }
    });
}

function updateEventDate(info) {
    const eventId = info.event.id;
    const eventType = info.event.extendedProps.type;
    const newStart = info.event.start.toISOString().split('T')[0];
    const newEnd = info.event.end ? info.event.end.toISOString().split('T')[0] : newStart;
    
    fetch('calendar_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: eventId,
            type: eventType,
            start_date: newStart,
            end_date: newEnd
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Date mise à jour avec succès');
        } else {
            alert('Erreur: ' + data.message);
            info.revert();
        }
    })
    .catch(error => {
        console.error('Erreur mise à jour:', error);
        alert('Erreur lors de la mise à jour');
        info.revert();
    });
}

function showEventDetails(event) {
    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
    const title = event.title;
    const type = event.extendedProps.type;
    const id = event.id;
    
    document.getElementById('eventModalTitle').textContent = title;
    
    let detailsHTML = `
        <div class="mb-3">
            <strong>Type:</strong> ${type === 'task' ? 'Tâche' : 'Jalon'}
        </div>
        <div class="mb-3">
            <strong>Début:</strong> ${event.start ? event.start.toLocaleDateString('fr-FR') : 'N/A'}
        </div>
        <div class="mb-3">
            <strong>Fin:</strong> ${event.end ? event.end.toLocaleDateString('fr-FR') : 'N/A'}
        </div>
    `;
    
    if (event.extendedProps.description) {
        detailsHTML += `
            <div class="mb-3">
                <strong>Description:</strong><br>
                ${event.extendedProps.description}
            </div>
        `;
    }
    
    if (event.extendedProps.status) {
        const statusLabels = {
            'pending': 'En attente',
            'in_progress': 'En cours',
            'completed': 'Complété',
            'blocked': 'Bloqué'
        };
        detailsHTML += `
            <div class="mb-3">
                <strong>Statut:</strong> <span class="badge bg-info">${statusLabels[event.extendedProps.status] || event.extendedProps.status}</span>
            </div>
        `;
    }
    
    if (event.extendedProps.assigned_to) {
        detailsHTML += `
            <div class="mb-3">
                <strong>Assigné à:</strong> ${event.extendedProps.assigned_to}
            </div>
        `;
    }
    
    if (event.extendedProps.progress !== undefined) {
        detailsHTML += `
            <div class="mb-3">
                <strong>Progression:</strong>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar" role="progressbar" style="width: ${event.extendedProps.progress}%">
                        ${event.extendedProps.progress}%
                    </div>
                </div>
            </div>
        `;
    }
    
    document.getElementById('eventModalBody').innerHTML = detailsHTML;
    
    // Lien vers les détails
    const detailLink = type === 'task' 
        ? `task_details.php?id=${id}` 
        : `project_details.php?id=${event.extendedProps.project_id}#milestones`;
    document.getElementById('eventDetailLink').href = detailLink;
    
    modal.show();
}
</script>

<style>
.fc-event {
    cursor: pointer;
}
.fc-event:hover {
    opacity: 0.8;
}
</style>

<?php
$content = ob_get_clean();
$pageTitle = "Calendrier des Projets";
include '../views/layouts/main.php';
?>
