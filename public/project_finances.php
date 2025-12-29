<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    $_SESSION['error'] = "ID projet manquant";
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Récupérer les informations du projet
    $stmt = $pdo->prepare("SELECT * FROM project_financial_summary WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $financial_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$financial_summary) {
        $_SESSION['error'] = "Projet non trouvé";
        redirect('projects.php');
    }
    
    // Récupérer toutes les dépenses
    $stmt = $pdo->prepare("
        SELECT 
            pe.*,
            u.full_name as created_by_name
        FROM project_expenses pe
        LEFT JOIN users u ON pe.created_by = u.id
        WHERE pe.project_id = ?
        ORDER BY pe.expense_date DESC
    ");
    $stmt->execute([$project_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les factures
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN users u ON i.created_by = u.id
        WHERE i.project_id = ?
        ORDER BY i.invoice_date DESC
    ");
    $stmt->execute([$project_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques par catégorie
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count,
            SUM(amount) as total
        FROM project_expenses
        WHERE project_id = ?
        GROUP BY category
        ORDER BY total DESC
    ");
    $stmt->execute([$project_id]);
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur finances: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du chargement";
    redirect('projects.php');
}

$budget_status = 'success';
if ($financial_summary['budget_consumed_percent'] >= 90) {
    $budget_status = 'danger';
} elseif ($financial_summary['budget_consumed_percent'] >= 75) {
    $budget_status = 'warning';
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-dollar-sign"></i> Gestion Financière</h2>
        <p class="text-muted"><?php echo htmlspecialchars($financial_summary['project_title']); ?></p>
    </div>
    <div>
        <a href="expense_create.php?project_id=<?php echo $project_id; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Nouvelle Dépense
        </a>
        <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
</div>

<!-- Résumé Financier -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Budget Estimé</div>
                <div class="h5 mb-0 font-weight-bold"><?php echo number_format($financial_summary['budget_estimated'], 0, ',', ' '); ?> FC</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Budget Validé</div>
                <div class="h5 mb-0 font-weight-bold"><?php echo number_format($financial_summary['budget_validated'], 0, ',', ' '); ?> FC</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-warning shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Dépenses Totales</div>
                <div class="h5 mb-0 font-weight-bold"><?php echo number_format($financial_summary['total_expenses'], 0, ',', ' '); ?> FC</div>
                <div class="mt-2">
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-<?php echo $budget_status; ?>" role="progressbar" 
                             style="width: <?php echo min($financial_summary['budget_consumed_percent'], 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-left-<?php echo $budget_status; ?> shadow h-100">
            <div class="card-body">
                <div class="text-xs font-weight-bold text-<?php echo $budget_status; ?> text-uppercase mb-1">Budget Restant</div>
                <div class="h5 mb-0 font-weight-bold"><?php echo number_format($financial_summary['remaining_budget'], 0, ',', ' '); ?> FC</div>
                <small class="text-muted"><?php echo number_format($financial_summary['budget_consumed_percent'], 1); ?>% consommé</small>
            </div>
        </div>
    </div>
</div>

<!-- Graphique Dépenses par Catégorie -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Dépenses par Catégorie</h6>
            </div>
            <div class="card-body">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-success">Répartition du Budget</h6>
            </div>
            <div class="card-body">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Liste des Dépenses -->
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-success">
            <i class="fas fa-list"></i> Historique des Dépenses (<?php echo count($expenses); ?>)
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($expenses)): ?>
            <div class="alert alert-info">
                Aucune dépense enregistrée pour ce projet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Catégorie</th>
                            <th>Description</th>
                            <th>Fournisseur</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Créé par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): 
                            $status_colors = [
                                'pending' => 'warning',
                                'paid' => 'success',
                                'cancelled' => 'danger'
                            ];
                            $status_labels = [
                                'pending' => 'En attente',
                                'paid' => 'Payé',
                                'cancelled' => 'Annulé'
                            ];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                            <td><span class="badge bg-secondary"><?php echo ucfirst($expense['category']); ?></span></td>
                            <td><?php echo htmlspecialchars(substr($expense['description'], 0, 50)); ?>...</td>
                            <td><?php echo htmlspecialchars($expense['supplier'] ?? '-'); ?></td>
                            <td class="text-end"><strong><?php echo number_format($expense['amount'], 0, ',', ' '); ?> FC</strong></td>
                            <td>
                                <span class="badge bg-<?php echo $status_colors[$expense['payment_status']]; ?>">
                                    <?php echo $status_labels[$expense['payment_status']]; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['created_by_name']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewExpense(<?php echo $expense['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="4" class="text-end">TOTAL:</th>
                            <th class="text-end"><?php echo number_format($financial_summary['total_expenses'], 0, ',', ' '); ?> FC</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des Factures -->
<div class="card shadow mb-4">
    <div class="card-header">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-file-invoice"></i> Factures (<?php echo count($invoices); ?>)
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($invoices)): ?>
            <div class="alert alert-info">
                Aucune facture enregistrée pour ce projet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>N° Facture</th>
                            <th>Date</th>
                            <th>Échéance</th>
                            <th>Fournisseur</th>
                            <th>Montant</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): 
                            $status_colors = [
                                'draft' => 'secondary',
                                'sent' => 'info',
                                'paid' => 'success',
                                'overdue' => 'danger',
                                'cancelled' => 'dark'
                            ];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                            <td><?php echo htmlspecialchars($invoice['supplier']); ?></td>
                            <td class="text-end"><strong><?php echo number_format($invoice['total_amount'], 0, ',', ' '); ?> FC</strong></td>
                            <td>
                                <span class="badge bg-<?php echo $status_colors[$invoice['status']]; ?>">
                                    <?php echo ucfirst($invoice['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.border-left-primary { border-left: 0.25rem solid #4e73df !important; }
.border-left-success { border-left: 0.25rem solid #1cc88a !important; }
.border-left-warning { border-left: 0.25rem solid #f6c23e !important; }
.border-left-danger { border-left: 0.25rem solid #e74a3b !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Données
const categoryData = <?php echo json_encode($category_stats); ?>;

// Graphique Catégories
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.category),
        datasets: [{
            data: categoryData.map(c => c.total),
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Graphique Budget
const budgetCtx = document.getElementById('budgetChart').getContext('2d');
new Chart(budgetCtx, {
    type: 'pie',
    data: {
        labels: ['Dépensé', 'Restant'],
        datasets: [{
            data: [
                <?php echo $financial_summary['total_expenses']; ?>,
                <?php echo max(0, $financial_summary['remaining_budget']); ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(75, 192, 192, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

function viewExpense(id) {
    // TODO: Modal détails dépense
    alert('Détails dépense ID: ' + id);
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Gestion Financière - " . $financial_summary['project_title'];
include '../views/layouts/main.php';
?>
