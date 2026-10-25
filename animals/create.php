<?php
/**
 * Create Live Animal Purchase
 * Meat Shop Management System
 */

$page_title = 'Purchase Live Animal';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag_id = trim($_POST['tag_id'] ?? '');
    $animal_type = trim($_POST['animal_type'] ?? 'Bull');
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    $live_weight = (float)($_POST['live_weight'] ?? 0.0);
    $purchase_rate_per_kg = (float)($_POST['purchase_rate_per_kg'] ?? 0.0);
    $transport_charges = (float)($_POST['transport_charges'] ?? 0.0);
    $slaughter_charges = (float)($_POST['slaughter_charges'] ?? 0.0);
    $other_expenses = (float)($_POST['other_expenses'] ?? 0.0);
    $notes = trim($_POST['notes'] ?? '');

    if (empty($tag_id) || empty($supplier_id) || $live_weight <= 0) {
        set_flash_message('danger', 'Please provide Tag ID, Supplier, and valid Live Weight.');
    } else {
        try {
            $pdo->beginTransaction();

            $animal_cost = $live_weight * $purchase_rate_per_kg;
            $total_cost = $animal_cost + $transport_charges + $slaughter_charges + $other_expenses;

            $stmt = $pdo->prepare("INSERT INTO live_animals (tag_id, animal_type, supplier_id, purchase_date, live_weight, purchase_rate_per_kg, animal_cost, transport_charges, slaughter_charges, other_expenses, total_animal_cost, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'purchased', ?)");
            $stmt->execute([
                $tag_id, $animal_type, $supplier_id, $purchase_date, $live_weight, $purchase_rate_per_kg,
                $animal_cost, $transport_charges, $slaughter_charges, $other_expenses, $total_cost, $notes
            ]);
            $animal_id = $pdo->lastInsertId();

            // Record into supplier ledger as purchase
            record_supplier_transaction($pdo, $supplier_id, $branch_id, 'purchase_invoice', $animal_id, "ANIMAL-$tag_id", 0, $animal_cost, null, "Live Animal Purchase ($animal_type Tag #$tag_id)");

            log_audit('purchase_animal', 'live_animals', $animal_id, ['tag' => $tag_id, 'cost' => $total_cost]);

            $pdo->commit();
            set_flash_message('success', 'Live animal #' . $tag_id . ' recorded successfully.');
            header("Location: /MeatShop/animals/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

$suppliers = $pdo->query("SELECT id, supplier_name, phone FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC")->fetchAll();
$auto_tag = "TAG-" . date('ym') . "-" . rand(1000, 9999);
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-cow text-danger me-2"></i> Record Live Animal Intake
                </h4>
                <p class="text-muted small mb-0">Record animal ear tag, farm source, live weighing, and freight/slaughter costs.</p>
            </div>
            <a href="/MeatShop/animals/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Animals
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <form method="POST" action="" class="card card-custom p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Ear Tag / Identification # <span class="text-danger">*</span></label>
                            <input type="text" name="tag_id" class="form-control font-monospace fw-bold" value="<?= htmlspecialchars($auto_tag) ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Animal Type <span class="text-danger">*</span></label>
                            <select name="animal_type" class="form-select">
                                <option value="Bull / Ox">Bull / Ox (Bail)</option>
                                <option value="Cow">Cow (Gai)</option>
                                <option value="Heifer">Heifer (Bachhi)</option>
                                <option value="Goat">Goat (Bakra)</option>
                                <option value="Sheep">Sheep (Chhatra / Dumbba)</option>
                                <option value="Camel">Camel (Oonth)</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold">Purchase Date</label>
                            <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Supplier / Cattle Farm <span class="text-danger">*</span></label>
                                <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal" onclick="window.currentQuickSelectTarget='selAnimalSupplier';">+ New Supplier</a>
                            </div>
                            <select name="supplier_id" id="selAnimalSupplier" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?> (<?= htmlspecialchars($s['phone']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Live Weight (KG) <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" name="live_weight" id="liveWeight" class="form-control fw-bold text-dark" placeholder="e.g. 250.000" oninput="recalcAnimal()" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Rate / KG Live Weight <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="purchase_rate_per_kg" id="liveRate" class="form-control fw-bold text-danger" placeholder="e.g. 600.00" oninput="recalcAnimal()" required>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>

                        <!-- Attributable Expenses -->
                        <div class="col-4">
                            <label class="form-label small fw-bold">Transportation Charges</label>
                            <input type="number" step="0.01" name="transport_charges" id="transportExp" class="form-control" value="0.00" oninput="recalcAnimal()">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">Slaughtering Charges</label>
                            <input type="number" step="0.01" name="slaughter_charges" id="slaughterExp" class="form-control" value="0.00" oninput="recalcAnimal()">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">Other Handling Fees</label>
                            <input type="number" step="0.01" name="other_expenses" id="otherExp" class="form-control" value="0.00" oninput="recalcAnimal()">
                        </div>

                        <!-- Cost Display Box -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <small class="text-muted text-uppercase fw-bold d-block">Total Attributable Cost for Meat Allocation</small>
                                <h3 class="fw-extrabold text-danger mb-0" id="totalAnimalCostDisplay">Rs 0.00</h3>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Health / Veterinary Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Vaccinated, healthy tooth examination passed"></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-check me-1"></i> Confirm Animal Intake
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function recalcAnimal() {
    const wt = parseFloat($('#liveWeight').val()) || 0;
    const rate = parseFloat($('#liveRate').val()) || 0;
    const base = wt * rate;
    const trans = parseFloat($('#transportExp').val()) || 0;
    const slau = parseFloat($('#slaughterExp').val()) || 0;
    const oth = parseFloat($('#otherExp').val()) || 0;

    const total = base + trans + slau + oth;
    $('#totalAnimalCostDisplay').text('Rs ' + total.toFixed(2));
}
</script>
