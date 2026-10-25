<?php
/**
 * Create Purchase Entry (Bulk / Farm Intake)
 * Meat Shop Management System
 */

$page_title = 'New Meat Purchase Entry';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $invoice_no = trim($_POST['invoice_no'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    $transport_charges = (float)($_POST['transport_charges'] ?? 0.0);
    $loading_charges = (float)($_POST['loading_charges'] ?? 0.0);
    $other_expenses = (float)($_POST['other_expenses'] ?? 0.0);
    $discount = (float)($_POST['discount'] ?? 0.0);
    $amount_paid = (float)($_POST['amount_paid'] ?? 0.0);
    $payment_method_id = (int)($_POST['payment_method_id'] ?? 1);
    $bank_account_id = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    $items_prod = $_POST['product_id'] ?? [];
    $items_gross = $_POST['gross_weight'] ?? [];
    $items_tare = $_POST['tare_weight'] ?? [];
    $items_net = $_POST['net_weight'] ?? [];
    $items_rate = $_POST['rate_per_kg'] ?? [];

    if (empty($supplier_id) || empty($items_prod)) {
        set_flash_message('danger', 'Please select a supplier and add at least one meat item.');
    } else {
        try {
            $pdo->beginTransaction();

            if (empty($invoice_no)) {
                $seq = $pdo->query("SELECT COUNT(id) FROM purchases")->fetchColumn() + 1;
                $invoice_no = "PUR-" . date('Ymd') . "-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }

            // Calculate item totals & weights
            $subtotal = 0.00;
            $total_gross = 0.000;
            $total_tare = 0.000;
            $total_net = 0.000;
            $valid_items = [];

            for ($i = 0; $i < count($items_prod); $i++) {
                $pid = (int)$items_prod[$i];
                if ($pid <= 0) continue;

                $gross = (float)($items_gross[$i] ?? 0.0);
                $tare = (float)($items_tare[$i] ?? 0.0);
                $net = max(0.001, (float)($items_net[$i] ?? ($gross - $tare)));
                $rate = (float)($items_rate[$i] ?? 0.0);
                $line_total = $net * $rate;

                $subtotal += $line_total;
                $total_gross += $gross;
                $total_tare += $tare;
                $total_net += $net;

                $valid_items[] = [
                    'product_id' => $pid,
                    'gross' => $gross,
                    'tare' => $tare,
                    'net' => $net,
                    'rate' => $rate,
                    'total' => $line_total
                ];
            }

            $additional_charges = $transport_charges + $loading_charges + $other_expenses;
            $grand_total = max(0.00, $subtotal + $additional_charges - $discount);
            $balance_amount = max(0.00, $grand_total - $amount_paid);

            $payment_status = 'unpaid';
            if ($balance_amount == 0 && $grand_total > 0) {
                $payment_status = 'paid';
            } elseif ($amount_paid > 0) {
                $payment_status = 'partial';
            }

            // Insert Purchase
            $stmt = $pdo->prepare("INSERT INTO purchases (branch_id, supplier_id, invoice_no, purchase_date, gross_weight, tare_weight, net_weight, subtotal, transport_charges, loading_charges, other_expenses, discount, grand_total, paid_amount, balance_amount, payment_status, payment_method_id, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $branch_id, $supplier_id, $invoice_no, $purchase_date, $total_gross, $total_tare, $total_net,
                $subtotal, $transport_charges, $loading_charges, $other_expenses, $discount, $grand_total,
                $amount_paid, $balance_amount, $payment_status, $payment_method_id, $notes, get_current_user_id()
            ]);
            $purchase_id = $pdo->lastInsertId();

            // Insert Items & Update Inventory Stock
            // Allocate extra freight/loading charges proportionally into unit cost!
            $cost_overhead_factor = ($total_net > 0) ? ($additional_charges / $total_net) : 0.00;

            $item_ins = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, gross_weight, tare_weight, net_weight, rate_per_kg, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");

            foreach ($valid_items as $vi) {
                $effective_unit_cost = $vi['rate'] + $cost_overhead_factor;

                $item_ins->execute([
                    $purchase_id, $vi['product_id'], $vi['gross'], $vi['tare'], $vi['net'], $vi['rate'], $vi['total']
                ]);

                // Update branch stock (positive change)
                update_product_stock($pdo, $branch_id, $vi['product_id'], $vi['net'], $effective_unit_cost, 'purchase', $purchase_id, 'purchases', "Purchase Invoice #$invoice_no");
            }

            // Update Supplier Ledger:
            // Credit: Full Grand Total (increases what we owe supplier)
            // Debit: Amount Paid immediately (decreases payable)
            record_supplier_transaction($pdo, $supplier_id, $branch_id, 'purchase_invoice', $purchase_id, $invoice_no, $amount_paid, $grand_total, $payment_method_id, "Purchase Invoice #$invoice_no");

            // Deduct bank account if paid via bank
            if ($amount_paid > 0 && $bank_account_id) {
                update_bank_balance($pdo, $bank_account_id, -$amount_paid);
            }

            log_audit('create_purchase', 'purchases', $purchase_id, ['invoice_no' => $invoice_no, 'grand_total' => $grand_total]);

            $pdo->commit();
            set_flash_message('success', 'Purchase invoice #' . $invoice_no . ' saved and stock updated.');
            header("Location: /MeatShop/purchases/view.php?id=" . $purchase_id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Fetch Suppliers, Products, Payment Methods, Bank Accounts
$suppliers = $pdo->query("SELECT id, supplier_name, phone, current_balance FROM suppliers WHERE status = 'active' ORDER BY supplier_name ASC")->fetchAll();
$products = $pdo->query("SELECT id, product_name, product_code, default_purchase_rate, unit_code FROM products p JOIN units u ON p.purchase_unit_id = u.id WHERE p.status = 'active' ORDER BY product_name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC")->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-cart-flatbed text-danger me-2"></i> New Meat Purchase Entry
                </h4>
                <p class="text-muted small mb-0">Record inward carcass/meat delivery with gross, tare, transport & net calculations.</p>
            </div>
            <a href="/MeatShop/purchases/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
            </a>
        </div>

        <form method="POST" action="" id="purchaseForm">
            <div class="row g-4">
                <!-- Supplier & Invoice Meta -->
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold mb-0">Supplier / Livestock Trader <span class="text-danger">*</span></label>
                                    <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal" onclick="window.currentQuickSelectTarget='selSupplier';">+ New Supplier</a>
                                </div>
                                <select name="supplier_id" id="selSupplier" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= $s['id'] ?>">
                                            <?= htmlspecialchars($s['supplier_name']) ?> (<?= htmlspecialchars($s['phone']) ?>) <?= $s['current_balance'] > 0 ? '[Payable: Rs ' . number_format($s['current_balance'], 0) . ']' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-bold">Supplier Invoice / Bilty #</label>
                                <input type="text" name="invoice_no" class="form-control" placeholder="Leave empty for auto-generated">
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-bold">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Items Table -->
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0">Meat Items & Weighing Breakdown</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="addPurchaseRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Another Item
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="purchaseItemsTable" style="min-width: 650px;">
                                <thead class="table-light fs-xs text-uppercase">
                                    <tr>
                                        <th style="width: 32%;">Product / Meat Cut</th>
                                        <th class="text-end" style="width: 15%;">Gross Wt (KG)</th>
                                        <th class="text-end" style="width: 15%;">Tare Wt (KG)</th>
                                        <th class="text-end" style="width: 15%;">Net Wt (KG)</th>
                                        <th class="text-end" style="width: 15%;">Rate / KG</th>
                                        <th class="text-end" style="width: 18%;">Line Total</th>
                                        <th class="text-center" style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="item-row">
                                        <td>
                                            <select name="product_id[]" class="form-select prod-select" onchange="onProdChange(this)" required>
                                                <option value="">Select Meat Product</option>
                                                <?php foreach ($products as $pr): ?>
                                                    <option value="<?= $pr['id'] ?>" data-rate="<?= $pr['default_purchase_rate'] ?>">
                                                        <?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="gross_weight[]" class="form-control text-end row-gross" placeholder="0.000" oninput="calcRow(this)" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="tare_weight[]" class="form-control text-end row-tare" value="0.000" placeholder="0.000" oninput="calcRow(this)">
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="net_weight[]" class="form-control text-end row-net fw-bold text-dark bg-light" readonly>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" name="rate_per_kg[]" class="form-control text-end row-rate fw-bold" placeholder="0.00" oninput="calcRow(this)" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end row-total fw-bold text-danger bg-light" readonly placeholder="0.00">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-light text-danger" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Additional Charges & Payment Breakdown -->
                <div class="col-12 col-lg-6">
                    <div class="card card-custom p-4 h-100">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Freight & Attributable Expenses</h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Transport / Freight</label>
                                <input type="number" step="0.01" name="transport_charges" id="transportCharges" class="form-control" value="0.00" oninput="calcGrandTotal()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Loading / Unloading</label>
                                <input type="number" step="0.01" name="loading_charges" id="loadingCharges" class="form-control" value="0.00" oninput="calcGrandTotal()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Other Handling Charges</label>
                                <input type="number" step="0.01" name="other_expenses" id="otherExpenses" class="form-control" value="0.00" oninput="calcGrandTotal()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Supplier Discount</label>
                                <input type="number" step="0.01" name="discount" id="supplierDiscount" class="form-control text-danger fw-bold" value="0.00" oninput="calcGrandTotal()">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Notes / Vehicle #</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Livestock vehicle # LEB-4521, fresh delivery"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment & Summary -->
                <div class="col-12 col-lg-6">
                    <div class="card card-custom p-4 h-100">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Total Payable & Payment</h6>
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Total Net Meat:</span>
                                <strong id="lblPurchaseNetWt">0.000 KG</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Item Subtotal:</span>
                                <strong id="lblPurchaseSubtotal">Rs 0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Additional Costs:</span>
                                <strong id="lblPurchaseExtra">Rs 0.00</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between fs-5 fw-bold text-dark">
                                <span>Grand Total:</span>
                                <span class="text-danger" id="lblPurchaseGrandTotal">Rs 0.00</span>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Payment Method</label>
                                <select name="payment_method_id" class="form-select">
                                    <?php foreach ($payment_methods as $pm): ?>
                                        <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['method_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Bank Account</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">None (Cash / Direct)</option>
                                    <?php foreach ($bank_accounts as $ba): ?>
                                        <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name']) ?> - <?= htmlspecialchars($ba['account_title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Amount Paid (Rs)</label>
                                <input type="number" step="0.01" name="amount_paid" id="purchaseAmountPaid" class="form-control fw-bold text-success" value="0.00" oninput="calcGrandTotal()">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Remaining Balance Due</label>
                                <input type="text" id="lblRemainingBalance" class="form-control fw-bold text-danger bg-light" readonly value="Rs 0.00">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-check-circle me-1"></i> Save Purchase Invoice & Add Stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function onProdChange(el) {
    const rate = $(el).find('option:selected').data('rate') || 0;
    const row = $(el).closest('tr');
    row.find('.row-rate').val(rate);
    calcRow(row.find('.row-gross')[0]);
}

function calcRow(el) {
    const row = $(el).closest('tr');
    const gross = parseFloat(row.find('.row-gross').val()) || 0;
    const tare = parseFloat(row.find('.row-tare').val()) || 0;
    const net = Math.max(0, gross - tare);
    const rate = parseFloat(row.find('.row-rate').val()) || 0;
    const total = net * rate;

    row.find('.row-net').val(net.toFixed(3));
    row.find('.row-total').val(total.toFixed(2));

    calcGrandTotal();
}

function calcGrandTotal() {
    let subtotal = 0;
    let netWeight = 0;

    $('.item-row').each(function() {
        const net = parseFloat($(this).find('.row-net').val()) || 0;
        const tot = parseFloat($(this).find('.row-total').val()) || 0;
        netWeight += net;
        subtotal += tot;
    });

    const transport = parseFloat($('#transportCharges').val()) || 0;
    const loading = parseFloat($('#loadingCharges').val()) || 0;
    const other = parseFloat($('#otherExpenses').val()) || 0;
    const discount = parseFloat($('#supplierDiscount').val()) || 0;
    const extra = transport + loading + other;

    const grand = Math.max(0, subtotal + extra - discount);
    const paid = parseFloat($('#purchaseAmountPaid').val()) || 0;
    const balance = Math.max(0, grand - paid);

    $('#lblPurchaseNetWt').text(netWeight.toFixed(3) + ' KG');
    $('#lblPurchaseSubtotal').text('Rs ' + subtotal.toFixed(2));
    $('#lblPurchaseExtra').text('Rs ' + extra.toFixed(2));
    $('#lblPurchaseGrandTotal').text('Rs ' + grand.toFixed(2));
    $('#lblRemainingBalance').val('Rs ' + balance.toFixed(2));
}

function addPurchaseRow() {
    const rowHtml = `
        <tr class="item-row">
            <td>
                <select name="product_id[]" class="form-select prod-select" onchange="onProdChange(this)" required>
                    <option value="">Select Meat Product</option>
                    <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>" data-rate="<?= $pr['default_purchase_rate'] ?>">
                            <?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" step="0.001" name="gross_weight[]" class="form-control text-end row-gross" placeholder="0.000" oninput="calcRow(this)" required></td>
            <td><input type="number" step="0.001" name="tare_weight[]" class="form-control text-end row-tare" value="0.000" oninput="calcRow(this)"></td>
            <td><input type="number" step="0.001" name="net_weight[]" class="form-control text-end row-net fw-bold text-dark bg-light" readonly></td>
            <td><input type="number" step="0.01" name="rate_per_kg[]" class="form-control text-end row-rate fw-bold" placeholder="0.00" oninput="calcRow(this)" required></td>
            <td><input type="number" step="0.01" class="form-control text-end row-total fw-bold text-danger bg-light" readonly placeholder="0.00"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-light text-danger" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `;
    $('#purchaseItemsTable tbody').append(rowHtml);
}

function removeRow(btn) {
    if ($('.item-row').length > 1) {
        $(btn).closest('tr').remove();
        calcGrandTotal();
    } else {
        Swal.fire('Notice', 'At least one item is required.', 'info');
    }
}
</script>
