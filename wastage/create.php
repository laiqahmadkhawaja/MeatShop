<?php
/**
 * Record Meat Wastage & Spoilage Entry
 * Meat Shop Management System
 */

$page_title = 'Record Meat Wastage & Spoilage';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $wastage_date = $_POST['wastage_date'] ?? date('Y-m-d');
    $weight = (float)($_POST['weight'] ?? 0.0);
    $wastage_type = trim($_POST['wastage_type'] ?? 'cutting_loss');
    $is_normal_loss = in_array($wastage_type, ['cutting_loss', 'trimming_loss']) ? 1 : 0;
    $reason = trim($_POST['reason'] ?? '');

    if ($product_id <= 0 || $weight <= 0) {
        set_flash_message('danger', 'Please select a product and enter valid weight.');
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch product cost
            $p_stmt = $pdo->prepare("SELECT p.product_name, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as cost 
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
                WHERE p.id = ?");
            $p_stmt->execute([$branch_id, $product_id]);
            $prod = $p_stmt->fetch();
            $cost_per_kg = $prod ? (float)$prod['cost'] : 0.00;
            $total_loss_cost = $weight * $cost_per_kg;

            $stmt = $pdo->prepare("INSERT INTO wastage_records (branch_id, product_id, wastage_date, weight, wastage_type, is_normal_loss, cost_per_kg, total_loss_cost, reason, user_id, approved_by, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
            $stmt->execute([
                $branch_id, $product_id, $wastage_date, $weight, $wastage_type, $is_normal_loss,
                $cost_per_kg, $total_loss_cost, $reason, get_current_user_id(), get_current_user_id()
            ]);
            $waste_id = $pdo->lastInsertId();

            // Deduct from stock
            update_product_stock($pdo, $branch_id, $product_id, -$weight, $cost_per_kg, 'wastage', $waste_id, 'wastage_records', "Wastage: $wastage_type ($reason)");

            log_audit('record_wastage', 'wastage_records', $waste_id, ['product' => $prod['product_name'], 'weight' => $weight, 'type' => $wastage_type]);

            $pdo->commit();
            set_flash_message('success', 'Wastage of ' . format_weight($weight) . ' recorded and stock deducted.');
            header("Location: /MeatShop/wastage/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Fetch products
$products = $pdo->prepare("SELECT p.id, p.product_name, p.product_code, COALESCE(bs.current_stock, 0) as stock_qty, u.unit_code, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as cost
    FROM products p
    JOIN units u ON p.selling_unit_id = u.id
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
    WHERE p.status = 'active'
    ORDER BY p.product_name ASC");
$products->execute([$branch_id]);
$prods = $products->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-trash-can text-danger me-2"></i> Record Meat Wastage / Spoilage
                </h4>
                <p class="text-muted small mb-0">Record cutting loss, trimming, freezer burn, handling loss, or spoilage discard.</p>
            </div>
            <a href="/MeatShop/wastage/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Wastage
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <form method="POST" action="" class="card card-custom p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Meat Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="wasteProdSelect" class="form-select" onchange="onWasteProdChange(this)" required autofocus>
                            <option value="" data-stock="0" data-cost="0">Select Product</option>
                            <?php foreach ($prods as $pr): ?>
                                <option value="<?= $pr['id'] ?>" data-stock="<?= $pr['stock_qty'] ?>" data-cost="<?= $pr['cost'] ?>" data-unit="<?= $pr['unit_code'] ?>">
                                    <?= htmlspecialchars($pr['product_name']) ?> (Stock: <?= format_weight($pr['stock_qty'], $pr['unit_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Wastage Date</label>
                            <input type="date" name="wastage_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Wastage Category <span class="text-danger">*</span></label>
                            <select name="wastage_type" class="form-select" required>
                                <optgroup label="Normal Operational Shrinkage">
                                    <option value="cutting_loss" selected>Cutting Loss (Bone / Saw dust)</option>
                                    <option value="trimming_loss">Trimming Loss (Fat removal)</option>
                                </optgroup>
                                <optgroup label="Spoilage & Failure Loss">
                                    <option value="spoilage">Spoiled Meat (Odor / Discoloration)</option>
                                    <option value="storage_loss">Storage Loss (Freezer Burn / Chiller Breakdown)</option>
                                    <option value="damaged">Damaged Stock / Dropped</option>
                                    <option value="handling_loss">Handling Loss</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Discarded / Wastage Weight (KG) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" name="weight" id="wasteWeight" class="form-control form-control-lg fw-bold text-center text-danger" placeholder="0.000" oninput="calcWasteCost()" required>
                    </div>

                    <!-- Valuation Box -->
                    <div class="p-3 bg-light rounded-3 mb-3 text-center border">
                        <small class="text-muted d-block text-uppercase fw-bold">Calculated Loss Financial Value</small>
                        <h4 class="fw-bold text-danger mb-0" id="lblWasteCost">Rs 0.00</h4>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Reason & Discard Action</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Unusable fat trims removed during boneless beef portioning"></textarea>
                    </div>

                    <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Confirm Wastage & Deduct Stock
                    </button>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let currentProdCost = 0;

function onWasteProdChange(el) {
    currentProdCost = parseFloat($(el).find('option:selected').data('cost')) || 0;
    calcWasteCost();
}

function calcWasteCost() {
    const wt = parseFloat($('#wasteWeight').val()) || 0;
    const total = wt * currentProdCost;
    $('#lblWasteCost').text('Rs ' + total.toFixed(2));
}
</script>
