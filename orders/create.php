<?php
/**
 * Create Restaurant Order / Advance Booking
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('orders_manage');

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $order_type = trim($_POST['order_type'] ?? 'wholesale_supply');
    $delivery_datetime = $_POST['delivery_datetime'] ?? date('Y-m-d H:i:s');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $rider_name = trim($_POST['rider_name'] ?? '');
    $delivery_charges = (float)($_POST['delivery_charges'] ?? 0.0);
    $advance_paid = (float)($_POST['advance_paid'] ?? 0.0);
    $notes = trim($_POST['notes'] ?? '');

    $items_prod = $_POST['item_product_id'] ?? [];
    $items_wt = $_POST['item_weight'] ?? [];
    $items_rate = $_POST['item_rate'] ?? [];
    $items_notes = $_POST['item_instructions'] ?? [];

    if ($customer_id <= 0 || empty($items_prod)) {
        set_flash_message('danger', 'Please select customer and at least one meat item.');
    } else {
        try {
            $pdo->beginTransaction();

            $seq = $pdo->query("SELECT COUNT(id) FROM orders")->fetchColumn() + 1;
            $order_no = "ORD-" . date('Ymd') . "-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $subtotal = 0.00;
            $order_items_data = [];

            for ($i = 0; $i < count($items_prod); $i++) {
                $pid = (int)$items_prod[$i];
                if ($pid <= 0) continue;

                $wt = (float)($items_wt[$i] ?? 0.0);
                $rate = (float)($items_rate[$i] ?? 0.0);
                $line_total = $wt * $rate;
                $ins_notes = trim($items_notes[$i] ?? '');

                $subtotal += $line_total;
                $order_items_data[] = [
                    'product_id' => $pid,
                    'weight_qty' => $wt,
                    'unit_rate' => $rate,
                    'line_total' => $line_total,
                    'instructions' => $ins_notes
                ];
            }

            $grand_total = $subtotal + $delivery_charges;
            $balance = max(0.00, $grand_total - $advance_paid);

            $stmt = $pdo->prepare("INSERT INTO orders (order_no, branch_id, customer_id, order_type, order_date, delivery_datetime, delivery_address, rider_name, delivery_charges, subtotal, grand_total, advance_paid, balance_amount, status, notes, created_by)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([
                $order_no, $branch_id, $customer_id, $order_type, $delivery_datetime, $delivery_address,
                $rider_name, $delivery_charges, $subtotal, $grand_total, $advance_paid, $balance, $notes, get_current_user_id()
            ]);
            $order_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, weight_qty, unit_rate, line_total, special_instructions) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($order_items_data as $oi) {
                $stmt_item->execute([$order_id, $oi['product_id'], $oi['weight_qty'], $oi['unit_rate'], $oi['line_total'], $oi['instructions']]);
            }

            // If advance paid, record customer payment
            if ($advance_paid > 0) {
                record_customer_transaction($pdo, $customer_id, $branch_id, 'payment_received', $order_id, "ADV-$order_no", 0, $advance_paid, null, "Advance payment for order #$order_no");
            }

            log_audit('create_order', 'orders', $order_id, ['order_no' => $order_no, 'total' => $grand_total]);

            $pdo->commit();
            set_flash_message('success', 'Order #' . $order_no . ' booked successfully.');
            header("Location: /MeatShop/orders/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Customers & Products
$customers = $pdo->query("SELECT id, customer_name, phone, customer_type FROM customers WHERE status = 'active' ORDER BY customer_name ASC")->fetchAll();
$products = $pdo->query("SELECT id, product_name, product_code, wholesale_selling_rate, retail_selling_rate, unit_code FROM products p JOIN units u ON p.selling_unit_id = u.id WHERE p.status = 'active' ORDER BY product_name ASC")->fetchAll();

$page_title = 'Create Advance Meat Booking';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-plus-circle text-danger me-2"></i> Book Restaurant Supply / Advance Order
                </h4>
                <p class="text-muted small mb-0">Record wholesale meat orders, portioning instructions (e.g. curry pieces, mince), and delivery schedules.</p>
            </div>
            <a href="/MeatShop/orders/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>

        <form method="POST" action="" id="orderForm">
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Order Items & Custom Cutting Instructions</h6>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle" id="orderItemsTable" style="min-width: 620px;">
                                <thead class="table-light fs-xs text-uppercase">
                                    <tr>
                                        <th style="width: 35%;">Meat Item</th>
                                        <th class="text-end" style="width: 15%;">Weight (KG)</th>
                                        <th class="text-end" style="width: 15%;">Rate (Rs)</th>
                                        <th class="text-end" style="width: 15%;">Total</th>
                                        <th style="width: 20%;">Special Cut Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="ord-row">
                                        <td>
                                            <select name="item_product_id[]" class="form-select ord-prod" onchange="onOrdProdChange(this)" required>
                                                <option value="">Select Meat Product</option>
                                                <?php foreach ($products as $pr): ?>
                                                    <option value="<?= $pr['id'] ?>" data-rate="<?= $pr['wholesale_selling_rate'] > 0 ? $pr['wholesale_selling_rate'] : $pr['retail_selling_rate'] ?>">
                                                        <?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="item_weight[]" class="form-control text-end ord-wt fw-bold" placeholder="0.000" oninput="calcOrdRow(this)" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="item_rate[]" class="form-control text-end ord-rate" placeholder="0.00" oninput="calcOrdRow(this)" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end ord-total fw-bold text-danger bg-light" readonly placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="text" name="item_instructions[]" class="form-control form-control-sm" placeholder="e.g. 1.5 inch cubes">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="addOrdRow()">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Item
                        </button>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Delivery & Customer Info</h6>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Customer / Restaurant <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Order Type</label>
                            <select name="order_type" class="form-select">
                                <option value="wholesale_supply" selected>Restaurant / Wholesale Supply</option>
                                <option value="booking">Advance Customer Booking</option>
                                <option value="home_delivery">Home Delivery</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Delivery Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="delivery_datetime" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+4 hours')) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Delivery Address</label>
                            <input type="text" name="delivery_address" class="form-control" placeholder="Street / Restaurant branch address">
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Delivery Fee</label>
                                <input type="number" step="0.01" name="delivery_charges" id="delivFee" class="form-control" value="0.00" oninput="calcOrdTotals()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Advance Paid</label>
                                <input type="number" step="0.01" name="advance_paid" id="advPaid" class="form-control fw-bold text-success" value="0.00" oninput="calcOrdTotals()">
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Items Subtotal:</span>
                                <strong id="lblOrdSubtotal">Rs 0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Grand Total:</span>
                                <strong class="text-danger" id="lblOrdGrandTotal">Rs 0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Balance Due:</span>
                                <strong class="text-dark" id="lblOrdBalance">Rs 0.00</strong>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Confirm & Book Order
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function onOrdProdChange(el) {
    const rate = $(el).find('option:selected').data('rate') || 0;
    const row = $(el).closest('tr');
    row.find('.ord-rate').val(rate);
    calcOrdRow(row.find('.ord-wt')[0]);
}

function calcOrdRow(el) {
    const row = $(el).closest('tr');
    const wt = parseFloat(row.find('.ord-wt').val()) || 0;
    const rate = parseFloat(row.find('.ord-rate').val()) || 0;
    const tot = wt * rate;
    row.find('.ord-total').val(tot.toFixed(2));
    calcOrdTotals();
}

function calcOrdTotals() {
    let sub = 0;
    $('.ord-row').each(function() {
        sub += parseFloat($(this).find('.ord-total').val()) || 0;
    });
    const deliv = parseFloat($('#delivFee').val()) || 0;
    const adv = parseFloat($('#advPaid').val()) || 0;
    const grand = sub + deliv;
    const bal = Math.max(0, grand - adv);

    $('#lblOrdSubtotal').text('Rs ' + sub.toFixed(2));
    $('#lblOrdGrandTotal').text('Rs ' + grand.toFixed(2));
    $('#lblOrdBalance').text('Rs ' + bal.toFixed(2));
}

function addOrdRow() {
    const html = `
        <tr class="ord-row">
            <td>
                <select name="item_product_id[]" class="form-select ord-prod" onchange="onOrdProdChange(this)" required>
                    <option value="">Select Meat Product</option>
                    <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>" data-rate="<?= $pr['wholesale_selling_rate'] > 0 ? $pr['wholesale_selling_rate'] : $pr['retail_selling_rate'] ?>">
                            <?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" step="0.001" name="item_weight[]" class="form-control text-end ord-wt fw-bold" placeholder="0.000" oninput="calcOrdRow(this)" required></td>
            <td><input type="number" step="0.01" name="item_rate[]" class="form-control text-end ord-rate" placeholder="0.00" oninput="calcOrdRow(this)" required></td>
            <td><input type="number" step="0.01" class="form-control text-end ord-total fw-bold text-danger bg-light" readonly placeholder="0.00"></td>
            <td><input type="text" name="item_instructions[]" class="form-control form-control-sm" placeholder="e.g. Small curry pieces"></td>
        </tr>
    `;
    $('#orderItemsTable tbody').append(html);
}
</script>
