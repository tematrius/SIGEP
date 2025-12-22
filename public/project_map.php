<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer tous les projets avec leur localisation
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.title,
            p.description,
            p.status,
            p.priority,
            p.start_date,
            p.end_date,
            p.budget_validated,
            l.name as location_name,
            l.latitude,
            l.longitude,
            l.province,
            u.full_name as creator_name,
            COALESCE(AVG(t.progress), 0) as calculated_progress,
            COUNT(DISTINCT t.id) as task_count
        FROM projects p
        LEFT JOIN locations l ON p.location_id = l.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE l.latitude IS NOT NULL AND l.longitude IS NOT NULL
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $projects = $stmt->fetchAll();
    
    // Récupérer les statistiques par province
    $stmt = $pdo->prepare("
        SELECT 
            l.id as province_id,
            l.name as province_name,
            COUNT(DISTINCT p.id) as project_count,
            SUM(p.budget_validated) as total_budget
        FROM locations l
        LEFT JOIN projects p ON p.location_id = l.id
        WHERE l.type = 'province'
          AND l.latitude IS NOT NULL 
          AND l.longitude IS NOT NULL
        GROUP BY l.id, l.name
        HAVING project_count > 0
        ORDER BY project_count DESC
    ");
    $stmt->execute();
    $stats_by_province = $stmt->fetchAll();
    
    // Récupérer toutes les provinces pour les filtres
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, name, latitude, longitude
        FROM locations
        WHERE type = 'province' 
          AND latitude IS NOT NULL 
          AND longitude IS NOT NULL
        ORDER BY name
    ");
    $stmt->execute();
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug temporaire
    if (empty($provinces)) {
        error_log("ATTENTION: Aucune province trouvée!");
        // Essayer une requête plus simple
        $stmt_test = $pdo->query("SELECT COUNT(*) as total FROM locations WHERE type='province'");
        $count = $stmt_test->fetch();
        error_log("Total provinces dans la table: " . ($count['total'] ?? 'erreur'));
        
        // Essayer de récupérer directement
        $stmt_direct = $pdo->query("SELECT id, name FROM locations WHERE type='province' AND latitude IS NOT NULL LIMIT 3");
        $test_provinces = $stmt_direct->fetchAll(PDO::FETCH_ASSOC);
        error_log("Test direct: " . print_r($test_provinces, true));
    } else {
        error_log("SUCCESS: " . count($provinces) . " provinces chargées");
    }
    
} catch (PDOException $e) {
    $projects = [];
    $stats_by_province = [];
    $provinces = [];
    error_log("Erreur carte projets: " . $e->getMessage());
}

// Debug: afficher le nombre de provinces chargées
// error_log("Nombre de provinces chargées: " . count($provinces));

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-map-marked-alt"></i> Carte des Projets</h2>
        <p class="text-muted">Visualisation géographique de tous les projets du ministère</p>
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
                <label class="form-label">Statut</label>
                <select class="form-select" id="filterStatus">
                    <option value="">Tous les statuts</option>
                    <option value="prevu">Prévu</option>
                    <option value="en_cours">En cours</option>
                    <option value="suspendu">Suspendu</option>
                    <option value="termine">Terminé</option>
                    <option value="annule">Annulé</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priorité</label>
                <select class="form-select" id="filterPriority">
                    <option value="">Toutes les priorités</option>
                    <option value="high">Haute</option>
                    <option value="medium">Moyenne</option>
                    <option value="low">Basse</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Province (<?php echo count($provinces); ?> disponibles)</label>
                <select class="form-select" id="filterProvince" onchange="handleProvinceChange()">
                    <option value="">Toutes les provinces</option>
                    <?php 
                    if (empty($provinces)) {
                        echo '<option value="" disabled>Aucune province chargée</option>';
                    } else {
                        foreach ($provinces as $province): 
                    ?>
                        <option value="<?php echo htmlspecialchars($province['name']); ?>" 
                                data-lat="<?php echo htmlspecialchars($province['latitude'] ?? ''); ?>"
                                data-lng="<?php echo htmlspecialchars($province['longitude'] ?? ''); ?>">
                            <?php echo htmlspecialchars($province['name']); ?>
                        </option>
                    <?php 
                        endforeach;
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="button" class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Appliquer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Carte -->
    <div class="col-lg-9 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-globe"></i> Carte Interactive</span>
                <div>
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-map-marker-alt"></i> <?php echo count($projects); ?> projet(s)
                    </span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="resetView()">
                        <i class="fas fa-sync-alt"></i> Réinitialiser
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="map" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
        
        <!-- Légende -->
        <div class="card mt-3">
            <div class="card-body">
                <strong class="me-3">Légende:</strong>
                <span class="badge bg-secondary me-2"><i class="fas fa-circle"></i> Prévu</span>
                <span class="badge bg-primary me-2"><i class="fas fa-circle"></i> En cours</span>
                <span class="badge bg-warning me-2"><i class="fas fa-circle"></i> Suspendu</span>
                <span class="badge bg-success me-2"><i class="fas fa-circle"></i> Terminé</span>
                <span class="badge bg-danger me-2"><i class="fas fa-circle"></i> Annulé</span>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="col-lg-3 mb-4">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-pie"></i> Statistiques
            </div>
            <div class="card-body">
                <h6>Total Projets</h6>
                <h3 class="text-primary"><?php echo count($projects); ?></h3>
                
                <hr>
                
                <h6>Budget Total</h6>
                <p class="mb-0">
                    <?php 
                    $total_budget = array_sum(array_column($projects, 'budget_validated'));
                    echo number_format($total_budget, 0, ',', ' ') . ' FC'; 
                    ?>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-map"></i> Par Province
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($stats_by_province)): ?>
                    <p class="text-muted text-center">Aucune donnée</p>
                <?php else: ?>
                    <?php foreach ($stats_by_province as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo e($stat['province_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo number_format($stat['total_budget'] ?? 0, 0, ',', ' '); ?> FC
                                </small>
                            </div>
                            <span class="badge bg-primary rounded-pill">
                                <?php echo $stat['project_count']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<!-- MarkerCluster JS -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<script>
// Données des projets
const projects = <?php echo json_encode($projects); ?>;

// Initialiser la carte centrée sur la RDC
const map = L.map('map').setView([-4.0383, 21.7587], 5);

// Ajouter le fond de carte OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// Créer un groupe de clusters pour les marqueurs
const markers = L.markerClusterGroup({
    maxClusterRadius: 50,
    spiderfyOnMaxZoom: true,
    showCoverageOnHover: false,
    zoomToBoundsOnClick: true
});

// Stocker tous les marqueurs
let allMarkers = [];

// Couleurs selon le statut
const statusColors = {
    'prevu': '#6c757d',
    'en_cours': '#0d6efd',
    'suspendu': '#ffc107',
    'termine': '#28a745',
    'annule': '#dc3545'
};

const statusLabels = {
    'prevu': 'Prévu',
    'en_cours': 'En cours',
    'suspendu': 'Suspendu',
    'termine': 'Terminé',
    'annule': 'Annulé'
};

const priorityLabels = {
    'high': 'Haute',
    'medium': 'Moyenne',
    'low': 'Basse'
};

// Créer une icône personnalisée par statut
function createIcon(status) {
    const color = statusColors[status] || '#6c757d';
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="
            background-color: ${color};
            width: 30px;
            height: 30px;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            border: 3px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        "></div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 30],
        popupAnchor: [0, -30]
    });
}

// Ajouter les marqueurs pour chaque projet
projects.forEach(project => {
    if (project.latitude && project.longitude) {
        const marker = L.marker(
            [parseFloat(project.latitude), parseFloat(project.longitude)],
            { icon: createIcon(project.status) }
        );
        
        // Créer le contenu du popup
        const popupContent = `
            <div style="min-width: 250px;">
                <h6 class="mb-2">
                    <a href="project_details.php?id=${project.id}" target="_blank">
                        ${project.title}
                    </a>
                </h6>
                <p class="small mb-2">${project.description ? project.description.substring(0, 100) + '...' : 'Pas de description'}</p>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Statut:</td>
                        <td><span class="badge" style="background-color: ${statusColors[project.status]}">${statusLabels[project.status]}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Priorité:</td>
                        <td>${priorityLabels[project.priority] || 'N/A'}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Localisation:</td>
                        <td>${project.location_name}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Progression:</td>
                        <td>
                            <div class="progress" style="height: 15px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: ${Math.round(project.calculated_progress)}%"
                                     aria-valuenow="${Math.round(project.calculated_progress)}" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    ${Math.round(project.calculated_progress)}%
                                </div>
                            </div>
                        </td>
                    </tr>
                    ${project.budget_validated ? `
                    <tr>
                        <td class="text-muted">Budget:</td>
                        <td>${new Intl.NumberFormat('fr-FR').format(project.budget_validated)} FC</td>
                    </tr>
                    ` : ''}
                    <tr>
                        <td class="text-muted">Tâches:</td>
                        <td>${project.task_count} tâche(s)</td>
                    </tr>
                </table>
                <div class="mt-2">
                    <a href="project_details.php?id=${project.id}" class="btn btn-sm btn-primary" target="_blank">
                        <i class="fas fa-eye"></i> Voir détails
                    </a>
                </div>
            </div>
        `;
        
        marker.bindPopup(popupContent, {
            maxWidth: 300
        });
        
        // Stocker les données du projet avec le marqueur
        marker.projectData = project;
        allMarkers.push(marker);
        markers.addLayer(marker);
    }
});

// Ajouter le groupe de marqueurs à la carte
map.addLayer(markers);

// Ajuster la vue pour montrer tous les marqueurs
if (allMarkers.length > 0) {
    const group = new L.featureGroup(allMarkers);
    map.fitBounds(group.getBounds().pad(0.1));
}

// Fonction de filtrage
function applyFilters() {
    const filterStatus = document.getElementById('filterStatus').value;
    const filterPriority = document.getElementById('filterPriority').value;
    const filterProvince = document.getElementById('filterProvince').value;
    
    // Supprimer tous les marqueurs
    markers.clearLayers();
    
    // Ajouter seulement les marqueurs filtrés
    let filteredCount = 0;
    const filteredMarkers = [];
    
    allMarkers.forEach(marker => {
        const project = marker.projectData;
        let include = true;
        
        if (filterStatus && project.status !== filterStatus) {
            include = false;
        }
        if (filterPriority && project.priority !== filterPriority) {
            include = false;
        }
        if (filterProvince && project.location_name !== filterProvince) {
            include = false;
        }
        
        if (include) {
            markers.addLayer(marker);
            filteredMarkers.push(marker);
            filteredCount++;
        }
    });
    
    // Ajuster la vue si des marqueurs sont affichés
    if (filteredCount > 0 && filteredMarkers.length > 0) {
        const group = new L.featureGroup(filteredMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
    
    // Afficher un message si aucun résultat
    if (filteredCount === 0) {
        alert('Aucun projet ne correspond aux critères de filtrage.');
    }
    
    console.log(`Filtrage appliqué: ${filteredCount} projet(s) trouvé(s)`);
}

// Gérer le changement de province pour zoomer automatiquement
function handleProvinceChange() {
    const select = document.getElementById('filterProvince');
    const selectedOption = select.options[select.selectedIndex];
    
    if (select.value) {
        // Appliquer le filtre
        applyFilters();
    } else {
        // Si "Toutes les provinces" est sélectionné, réinitialiser
        resetView();
    }
}

// Réinitialiser la vue
function resetView() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterPriority').value = '';
    document.getElementById('filterProvince').value = '';
    
    markers.clearLayers();
    allMarkers.forEach(marker => markers.addLayer(marker));
    
    if (allMarkers.length > 0) {
        const group = new L.featureGroup(allMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}
</script>

<style>
.custom-marker {
    background: transparent;
    border: none;
}

.leaflet-popup-content {
    margin: 10px;
}

.leaflet-popup-content h6 {
    color: #333;
    font-weight: bold;
}

.leaflet-popup-content a {
    color: #0d6efd;
    text-decoration: none;
}

.leaflet-popup-content a:hover {
    text-decoration: underline;
}

.leaflet-popup-content .table {
    font-size: 0.85rem;
}

.leaflet-popup-content .table td {
    padding: 0.25rem 0.5rem;
}
</style>

<?php
$content = ob_get_clean();
$pageTitle = 'Carte des Projets';
include '../views/layouts/main.php';
?>
