<?php
/**
 * Stock Adjustment
 * Meat Shop Management System
 */

$page_title = 'Physical Stock Adjustment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $adjustment_type = ($_POST['adjustment_type'] === 'subtract') ? 'subtract' : 'add';
    $quantity = (float)($_POST['quantity'] ?? 0.0);
    $reason = trim($_POST['reason'] ?? '');

    if ($product_id <= 0 || $quantity <= 0 || empty($reason)) {
        set_flash_message('danger', 'Please select product, positive weight, and provide reason.');
    } else {
        try {
            $pdo->beginTransaction();

            $p_stmt = $pdo->prepare("SELECT p.product_name, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as cost 
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
                WHERE p.id = ?");
            $p_stmt->execute([$branch_id, $product_id]);
            $prod = $p_stmt->fetch();
            $cost = $prod ? (float)$prod['cost'] : 0.00;

            $delta = ($adjustment_type === 'add') ? $quantity : -$quantity;
            $mov_type = ($adjustment_type === 'add') ? 'adjustment_add' : 'adjustment_sub';

            $stmt = $pdo->prepare("INSERT INTO stock_adjustments (branch_id, product_id, adjustment_type, quantity, unit_cost, reason, approved_by, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $branch_id, $product_id, $adjustment_type, $quantity, $cost, $reason, get_current_user_id(), get_current_user_id()
            ]);
            $adj_id = $pdo->lastInsertId();

            update_product_stock($pdo, $branch_id, $product_id, $delta, $cost, $mov_type, $adj_id, 'stock_adjustments', "Adjustment: $reason");

            log_audit('stock_adjustment', 'stock_adjustments', $adj_id, ['product' => $prod['product_name'], 'delta' => $delta, 'reason' => $reason]);

            $pdo->commit();
            set_flash_message('success', 'Stock adjustment of ' . format_weight($delta) . ' applied successfully.');
            header("Location: /MeatShop/inventory/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

$products = $pdo->prepare("SELECT p.id, p.product_name, p.product_code, COALESCE(bs.current_stock, 0) as stock_qty, u.unit_code 
    FROM products p 
    JOIN units u ON p.selling_unit_id = u.id 
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
    WHERE p.status = 'active' ORDER BY p.product_name ASC");
$products->execute([$branch_id]);
$prods = $products->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-sliders text-danger me-2"></i> Physical Stock Count & Adjustment
                </h4>
                <p class="text-muted small mb-0">Reconcile physical butcher scale counts with software stock balances.</p>
            </div>
            <a href="/MeatShop/inventory/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <form method="POST" action="" class="card card-custom p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Meat Product <span class="text-danger">*</span></label>
                        <select name="product_id" id="adjProd" class="form-select" onchange="onAdjProd(this)" required autofocus>
                            <option value="" data-stock="0">Select Product</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock_qty'] ?>" data-unit="<?= $p['unit_code'] ?>">
                                    <?= htmlspecialchars($p['product_name']) ?> (Current: <?= format_weight($p['stock_qty'], $p['unit_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 text-center border">
                        <small class="text-muted text-uppercase fw-bold d-block">System Registered Balance</small>
                        <h4 class="fw-bold text-dark mb-0" id="dispSysStock">0.000 KG</h4>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Adjustment Type <span class="text-danger">*</span></label>
                            <select name="adjustment_type" class="form-select" required>
                                <option value="add">Add Stock (+) (Physical Surplus)</option>
                                <option value="subtract">Subtract Stock (-) (Physical Shortage)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Adjustment Weight (KG) <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" name="quantity" class="form-control fw-bold text-center" placeholder="0.000" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Audit Reason / Justification <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="e.g. End of day physical weigh-in variance reconciliation" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Apply Stock Adjustment
                    </button>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function onAdjProd(el) {
    const stock = parseFloat($(el).find('option:selected').data('stock')) || 0;
    const unit = $(el).find('option:selected').data('unit') || 'KG';
    $('#dispSysStock').text(stock.toFixed(3) + ' ' + unit);
}
</script>
