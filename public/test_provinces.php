<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pdo = getDbConnection();

echo "<h1>Test des Provinces</h1>";

// Test 1: Compter toutes les provinces
$stmt = $pdo->query("SELECT COUNT(*) as total FROM locations WHERE type='province'");
$count = $stmt->fetch();
echo "<p><strong>Total provinces dans la table:</strong> " . $count['total'] . "</p>";

// Test 2: Provinces avec coordonnées
$stmt = $pdo->query("SELECT COUNT(*) as total FROM locations WHERE type='province' AND latitude IS NOT NULL AND longitude IS NOT NULL");
$count_with_coords = $stmt->fetch();
echo "<p><strong>Provinces avec coordonnées:</strong> " . $count_with_coords['total'] . "</p>";

// Test 3: Lister toutes les provinces
$stmt = $pdo->query("SELECT id, name, type, latitude, longitude FROM locations WHERE type='province' ORDER BY name");
$provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Liste des provinces:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nom</th><th>Type</th><th>Latitude</th><th>Longitude</th></tr>";

foreach ($provinces as $province) {
    echo "<tr>";
    echo "<td>" . $province['id'] . "</td>";
    echo "<td>" . htmlspecialchars($province['name']) . "</td>";
    echo "<td>" . $province['type'] . "</td>";
    echo "<td>" . ($province['latitude'] ?? 'NULL') . "</td>";
    echo "<td>" . ($province['longitude'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test 4: La requête exacte utilisée dans project_map.php
echo "<h2>Test de la requête project_map.php:</h2>";
$stmt = $pdo->prepare("
    SELECT DISTINCT id, name, latitude, longitude
    FROM locations
    WHERE type = 'province' 
      AND latitude IS NOT NULL 
      AND longitude IS NOT NULL
    ORDER BY name
");
$stmt->execute();
$provinces_filtered = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Résultat de la requête filtrée:</strong> " . count($provinces_filtered) . " provinces</p>";

if (count($provinces_filtered) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Latitude</th><th>Longitude</th></tr>";
    foreach ($provinces_filtered as $prov) {
        echo "<tr>";
        echo "<td>" . $prov['id'] . "</td>";
        echo "<td>" . htmlspecialchars($prov['name']) . "</td>";
        echo "<td>" . $prov['latitude'] . "</td>";
        echo "<td>" . $prov['longitude'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>AUCUNE PROVINCE TROUVÉE avec la requête filtrée!</p>";
}

// Test 5: Générer un dropdown HTML comme dans project_map.php
echo "<h2>Test du dropdown HTML:</h2>";
echo "<select class='form-select' style='width: 300px; padding: 5px;'>";
echo "<option value=''>Toutes les provinces</option>";
if (empty($provinces_filtered)) {
    echo "<option value='' disabled>Aucune province chargée</option>";
} else {
    foreach ($provinces_filtered as $province) {
        echo "<option value='" . htmlspecialchars($province['name']) . "'>";
        echo htmlspecialchars($province['name']);
        echo "</option>";
    }
}
echo "</select>";
?>
