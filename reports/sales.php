<?php
/**
 * Detailed Sales & Invoices Report
 * Meat Shop Management System
 */

$page_title = 'Sales Report & Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$payment_status = $_GET['payment_status'] ?? '';

$sql = "SELECT s.*, c.customer_name, c.phone as customer_phone, u.full_name as cashier_name, pm.method_name as payment_method
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN users u ON s.cashier_id = u.id
    LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id
    WHERE s.branch_id = ? AND DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$branch_id, $start_date, $end_date];

if (!empty($payment_status)) {
    $sql .= " AND s.payment_status = ?";
    $params[] = $payment_status;
}

$sql .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$total_rev = array_sum(array_column($sales, 'grand_total'));
$total_cash = array_sum(array_column($sales, 'paid_amount'));
$total_credit = array_sum(array_column($sales, 'balance_amount'));
$total_wt = array_sum(array_column($sales, 'total_weight'));
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-receipt text-danger me-2"></i> Detailed Sales & Counter Invoices Log
                </h4>
                <p class="text-muted small mb-0">From <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print
                </button>
                <a href="/MeatShop/reports/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card card-custom p-3 mb-4 no-print">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-bold mb-1">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                        <option value="partial" <?= $payment_status === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="credit" <?= $payment_status === 'credit' ? 'selected' : '' ?>>Credit Sale</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-meat flex-fill">Filter Log</button>
                    <a href="?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a>
                </div>
            </form>
        </div>

        <!-- Summary Totals -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Sales</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($total_rev) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Cash Collected</small>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_currency($total_cash) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Credit / Outstanding</small>
                    <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($total_credit) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Meat Sold</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_weight($total_wt) ?></h4>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Invoice</th>
                            <th class="d-none d-md-table-cell">Date</th>
                            <th>Customer</th>
                            <th class="d-none d-lg-table-cell">Cashier</th>
                            <th class="text-end d-none d-sm-table-cell">Weight</th>
                            <th class="text-end">Total</th>
                            <th class="text-end d-none d-md-table-cell">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-print">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">No sales invoices found for selected criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="fw-bold text-danger">
                                    <?= htmlspecialchars($s['invoice_no']) ?>
                                    <div class="small text-muted d-md-none"><?= date('d-M h:i A', strtotime($s['sale_date'])) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= date('d-M-Y h:i A', strtotime($s['sale_date'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($s['customer_name']) ?></strong>
                                    <div class="small text-muted d-sm-none"><?= format_weight($s['total_weight']) ?></div>
                                </td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($s['cashier_name']) ?></small></td>
                                <td class="text-end fw-semibold d-none d-sm-table-cell"><?= format_weight($s['total_weight']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= format_currency($s['grand_total']) ?></td>
                                <td class="text-end text-success fw-semibold d-none d-md-table-cell"><?= format_currency($s['paid_amount']) ?></td>
                                <td class="text-end fw-bold <?= $s['balance_amount'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= format_currency($s['balance_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $s['payment_status'] === 'paid' ? 'badge-subtle-success' : 'badge-subtle-danger' ?> text-uppercase">
                                        <?= $s['payment_status'] ?>
                                    </span>
                                </td>
                                <td class="text-center no-print">
                                    <a href="/MeatShop/pos/receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-light border px-2">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
