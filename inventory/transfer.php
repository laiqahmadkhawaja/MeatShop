<?php
/**
 * Branch Inventory Transfer
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('inventory_manage');

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_branch_id = (int)($_POST['to_branch_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0.0);
    $notes = trim($_POST['notes'] ?? '');

    if ($to_branch_id <= 0 || $to_branch_id === $branch_id || $product_id <= 0 || $quantity <= 0) {
        set_flash_message('danger', 'Please choose a different destination branch, product, and positive weight.');
    } else {
        try {
            $pdo->beginTransaction();

            $seq = $pdo->query("SELECT COUNT(id) FROM stock_transfers")->fetchColumn() + 1;
            $transfer_no = "TRF-" . date('Ymd') . "-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // Fetch unit cost
            $c_stmt = $pdo->prepare("SELECT p.product_name, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as cost, bs.current_stock
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
                WHERE p.id = ?");
            $c_stmt->execute([$branch_id, $product_id]);
            $prod = $c_stmt->fetch();
            $cost = $prod ? (float)$prod['cost'] : 0.00;

            // Insert Transfer
            $stmt = $pdo->prepare("INSERT INTO stock_transfers (transfer_no, from_branch_id, to_branch_id, transfer_date, received_date, status, total_items, total_weight, notes, dispatched_by, received_by)
                VALUES (?, ?, ?, CURDATE(), CURDATE(), 'received', 1, ?, ?, ?, ?)");
            $stmt->execute([
                $transfer_no, $branch_id, $to_branch_id, $quantity, $notes, get_current_user_id(), get_current_user_id()
            ]);
            $trf_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
            $stmt_item->execute([$trf_id, $product_id, $quantity, $cost]);

            // Deduct from Source Branch
            update_product_stock($pdo, $branch_id, $product_id, -$quantity, $cost, 'transfer_out', $trf_id, 'stock_transfers', "Transfer to Branch #$to_branch_id ($transfer_no)");

            // Add to Destination Branch
            update_product_stock($pdo, $to_branch_id, $product_id, $quantity, $cost, 'transfer_in', $trf_id, 'stock_transfers', "Transfer from Branch #$branch_id ($transfer_no)");

            log_audit('branch_transfer', 'stock_transfers', $trf_id, ['from' => $branch_id, 'to' => $to_branch_id, 'qty' => $quantity]);

            $pdo->commit();
            set_flash_message('success', 'Transfer #' . $transfer_no . ' executed! Dispatched ' . format_weight($quantity) . ' to target branch.');
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

// Other branches
$other_branches = $pdo->prepare("SELECT id, branch_name, branch_code FROM branches WHERE id != ? AND status = 'active' ORDER BY branch_name ASC");
$other_branches->execute([$branch_id]);
$branches_list = $other_branches->fetchAll();

// Products
$products = $pdo->prepare("SELECT p.id, p.product_name, p.product_code, COALESCE(bs.current_stock, 0) as stock_qty, u.unit_code 
    FROM products p 
    JOIN units u ON p.selling_unit_id = u.id 
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
    WHERE p.status = 'active' ORDER BY p.product_name ASC");
$products->execute([$branch_id]);
$prods = $products->fetchAll();

$page_title = 'Multi-Branch Meat Transfer';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-arrow-right-arrow-left text-danger me-2"></i> Multi-Branch Meat Stock Transfer
                </h4>
                <p class="text-muted small mb-0">Dispatch fresh meat stock from current branch to another retail branch.</p>
            </div>
            <a href="/MeatShop/inventory/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <form method="POST" action="" class="card card-custom p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Destination Branch <span class="text-danger">*</span></label>
                        <select name="to_branch_id" class="form-select" required autofocus>
                            <option value="">Select Target Branch</option>
                            <?php foreach ($branches_list as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?> (<?= htmlspecialchars($b['branch_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Meat Product to Transfer <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach ($prods as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['product_name']) ?> (Available: <?= format_weight($p['stock_qty'], $p['unit_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Transfer Quantity / Weight (KG) <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" name="quantity" class="form-control form-control-lg fw-bold text-center text-danger" placeholder="0.000" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Transfer Notes / Vehicle #</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Sent via Chiller Van # LZC-1090"></textarea>
                    </div>

                    <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-paper-plane me-1"></i> Dispatch & Transfer Stock
                    </button>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
