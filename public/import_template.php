<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasPermission('manage_projects')) {
    setFlashMessage('error', 'Permission refusée');
    redirect('dashboard.php');
}

$type = $_GET['type'] ?? 'projects';
$format = $_GET['format'] ?? 'excel';

// Définir les en-têtes selon le type
if ($type === 'projects') {
    $headers = [
        'title',
        'description',
        'context',
        'status',
        'priority',
        'start_date',
        'end_date',
        'budget_estimated',
        'budget_validated',
        'location_province'
    ];
    
    $exampleData = [
        'Construction Centre de Santé',
        'Construction d\'un centre de santé moderne dans la province',
        'Engagement ministériel 2025',
        'prevu',
        'high',
        '2025-01-15',
        '2025-12-31',
        '500000000',
        '450000000',
        'KS'
    ];
} else {
    $headers = [
        'project_id',
        'title',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'estimated_hours',
        'progress',
        'assigned_to_email'
    ];
    
    $exampleData = [
        '1',
        'Études préliminaires',
        'Réaliser les études techniques et environnementales',
        'pending',
        'high',
        '2025-01-15',
        '2025-02-15',
        '160',
        '0',
        'technicien@example.com'
    ];
}

if ($format === 'excel') {
    // Générer un fichier Excel simple (format HTML qui sera interprété par Excel)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="template_' . $type . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo '<Worksheet ss:Name="' . ucfirst($type) . '">' . "\n";
    echo '<Table>' . "\n";
    
    // En-têtes
    echo '<Row>' . "\n";
    foreach ($headers as $header) {
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Ligne d'exemple
    echo '<Row>' . "\n";
    foreach ($exampleData as $data) {
        echo '  <Cell><Data ss:Type="String">' . htmlspecialchars($data) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Quelques lignes vides
    for ($i = 0; $i < 5; $i++) {
        echo '<Row>' . "\n";
        foreach ($headers as $header) {
            echo '  <Cell><Data ss:Type="String"></Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
    
} else {
    // Générer un fichier CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_' . $type . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    
    // UTF-8 BOM pour Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, $headers, ';');
    
    // Ligne d'exemple
    fputcsv($output, $exampleData, ';');
    
    // Quelques lignes vides
    for ($i = 0; $i < 5; $i++) {
        fputcsv($output, array_fill(0, count($headers), ''), ';');
    }
    
    fclose($output);
}

exit;
?>
