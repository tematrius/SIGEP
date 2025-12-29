<?php
require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$project_id = $_GET['project_id'] ?? $_POST['project_id'] ?? null;

if (!$project_id) {
    $_SESSION['error'] = "ID projet manquant";
    redirect('projects.php');
}

try {
    $pdo = getDbConnection();
    
    // Vérifier le projet
    $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE id = ? AND archived = FALSE");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        $_SESSION['error'] = "Projet non trouvé";
        redirect('projects.php');
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $expense_date = $_POST['expense_date'];
        $category = $_POST['category'];
        $description = $_POST['description'];
        $amount = $_POST['amount'];
        $invoice_number = $_POST['invoice_number'] ?? null;
        $supplier = $_POST['supplier'] ?? null;
        $payment_status = $_POST['payment_status'] ?? 'pending';
        $payment_date = $_POST['payment_date'] ?? null;
        $payment_method = $_POST['payment_method'] ?? null;
        $notes = $_POST['notes'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO project_expenses 
            (project_id, expense_date, category, description, amount, invoice_number, 
             supplier, payment_status, payment_date, payment_method, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $project_id,
            $expense_date,
            $category,
            $description,
            $amount,
            $invoice_number,
            $supplier,
            $payment_status,
            $payment_date,
            $payment_method,
            $notes,
            $_SESSION['user_id']
        ]);
        
        logActivity("Dépense ajoutée au projet", 'expense', $pdo->lastInsertId());
        
        $_SESSION['success'] = "Dépense enregistrée avec succès";
        redirect('project_finances.php?id=' . $project_id);
    }
    
} catch (PDOException $e) {
    error_log("Erreur dépense: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de l'enregistrement";
}

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt"></i> Enregistrer une Dépense</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Projet:</strong> <?php echo htmlspecialchars($project['title']); ?>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expense_date" class="form-label">Date de la dépense *</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Catégorie *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="personnel">Personnel</option>
                                    <option value="equipment">Équipement</option>
                                    <option value="materials">Matériaux</option>
                                    <option value="services">Services</option>
                                    <option value="travel">Déplacement</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">Montant (FC) *</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="invoice_number" class="form-label">N° Facture</label>
                                <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="supplier" class="form-label">Fournisseur</label>
                            <input type="text" class="form-control" id="supplier" name="supplier">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="payment_status" class="form-label">Statut Paiement *</label>
                                <select class="form-select" id="payment_status" name="payment_status" required>
                                    <option value="pending">En attente</option>
                                    <option value="paid">Payé</option>
                                    <option value="cancelled">Annulé</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_date" class="form-label">Date de Paiement</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_method" class="form-label">Mode de Paiement</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">-</option>
                                    <option value="cash">Espèces</option>
                                    <option value="check">Chèque</option>
                                    <option value="transfer">Virement</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="project_finances.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('payment_status').addEventListener('change', function() {
    const paymentDate = document.getElementById('payment_date');
    const paymentMethod = document.getElementById('payment_method');
    
    if (this.value === 'paid') {
        paymentDate.required = true;
        paymentMethod.required = true;
        if (!paymentDate.value) {
            paymentDate.value = '<?php echo date('Y-m-d'); ?>';
        }
    } else {
        paymentDate.required = false;
        paymentMethod.required = false;
    }
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = "Enregistrer une Dépense";
include '../views/layouts/main.php';
?>
