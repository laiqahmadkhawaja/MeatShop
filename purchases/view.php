<?php
/**
 * View Purchase Invoice
 * Meat Shop Management System
 */

$page_title = 'Purchase Invoice Details';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$purchase_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, s.supplier_name, s.phone as supplier_phone, s.address as supplier_address, u.full_name as created_by_name, pm.method_name as payment_method
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    JOIN users u ON p.created_by = u.id
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
    WHERE p.id = ?");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    set_flash_message('danger', 'Purchase invoice not found.');
    header("Location: /MeatShop/purchases/index.php");
    exit;
}

$items_stmt = $pdo->prepare("SELECT pi.*, p.product_name, p.product_code, u.unit_code
    FROM purchase_items pi
    JOIN products p ON pi.product_id = p.id
    JOIN units u ON p.purchase_unit_id = u.id
    WHERE pi.purchase_id = ?");
$items_stmt->execute([$purchase_id]);
$items = $items_stmt->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-file-invoice text-danger me-2"></i> Purchase Invoice: <?= htmlspecialchars($purchase['invoice_no']) ?>
                </h4>
                <p class="text-muted small mb-0">Recorded on <?= date('d M Y', strtotime($purchase['purchase_date'])) ?> by <?= htmlspecialchars($purchase['created_by_name']) ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Invoice
                </button>
                <a href="/MeatShop/purchases/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="card card-custom p-4">
            <!-- Header Info -->
            <div class="row g-3 border-bottom pb-3 mb-3">
                <div class="col-12 col-md-6">
                    <h5 class="fw-bold text-danger mb-1"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></h5>
                    <p class="text-muted small mb-0"><?= htmlspecialchars(get_setting('address')) ?></p>
                    <p class="text-muted small mb-0">Tel: <?= htmlspecialchars(get_setting('phone')) ?></p>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <h5 class="fw-bold text-dark mb-1">INVOICE: <?= htmlspecialchars($purchase['invoice_no']) ?></h5>
                    <p class="mb-0 small text-muted">Date: <strong><?= date('d-M-Y', strtotime($purchase['purchase_date'])) ?></strong></p>
                    <span class="badge <?= $purchase['payment_status'] === 'paid' ? 'bg-success' : 'bg-danger' ?> text-uppercase">
                        <?= $purchase['payment_status'] ?>
                    </span>
                </div>
            </div>

            <!-- Supplier Info -->
            <div class="p-3 bg-light rounded-3 mb-4">
                <h6 class="fw-bold text-dark mb-1">Supplier: <?= htmlspecialchars($purchase['supplier_name']) ?></h6>
                <p class="text-muted small mb-0">Phone: <?= htmlspecialchars($purchase['supplier_phone']) ?> | Address: <?= htmlspecialchars($purchase['supplier_address'] ?: 'N/A') ?></p>
            </div>

            <!-- Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>#</th>
                            <th>Meat Item / Cut</th>
                            <th class="text-end">Gross Wt</th>
                            <th class="text-end">Tare Wt</th>
                            <th class="text-end">Net Wt</th>
                            <th class="text-end">Rate / KG</th>
                            <th class="text-end">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $it): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                                <small class="text-muted d-block"><?= htmlspecialchars($it['product_code']) ?></small>
                            </td>
                            <td class="text-end text-muted"><?= format_weight($it['gross_weight']) ?></td>
                            <td class="text-end text-muted"><?= format_weight($it['tare_weight']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= format_weight($it['net_weight']) ?></td>
                            <td class="text-end"><?= format_currency($it['rate_per_kg']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= format_currency($it['total_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary Totals -->
            <div class="row g-3 justify-content-end">
                <div class="col-12 col-md-5">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Subtotal Net Meat:</td>
                                <td class="text-end fw-bold"><?= format_weight($purchase['net_weight']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Items Subtotal:</td>
                                <td class="text-end fw-bold"><?= format_currency($purchase['subtotal']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Transport / Freight:</td>
                                <td class="text-end"><?= format_currency($purchase['transport_charges']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Loading & Other Charges:</td>
                                <td class="text-end"><?= format_currency($purchase['loading_charges'] + $purchase['other_expenses']) ?></td>
                            </tr>
                            <?php if ($purchase['discount'] > 0): ?>
                            <tr>
                                <td class="text-danger">Supplier Discount:</td>
                                <td class="text-end text-danger fw-bold">- <?= format_currency($purchase['discount']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-top border-dark">
                                <td class="fw-bold fs-6">Grand Total:</td>
                                <td class="text-end fw-bold fs-6 text-danger"><?= format_currency($purchase['grand_total']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Amount Paid:</td>
                                <td class="text-end text-success fw-bold"><?= format_currency($purchase['paid_amount']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Remaining Balance:</td>
                                <td class="text-end text-danger fw-bold"><?= format_currency($purchase['balance_amount']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <?php if (!empty($purchase['notes'])): ?>
                <div class="border-top pt-3 mt-3">
                    <small class="text-muted fw-bold d-block">Notes / Observations:</small>
                    <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
