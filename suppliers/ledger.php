<?php
/**
 * Supplier Khata Ledger Statement
 * Meat Shop Management System
 */

$page_title = 'Supplier Ledger Statement';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$supplier_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash_message('danger', 'Supplier not found.');
    header("Location: /MeatShop/suppliers/index.php");
    exit;
}

// Fetch Transactions
$tx_stmt = $pdo->prepare("SELECT st.*, pm.method_name as payment_method, u.full_name as created_by_name
    FROM supplier_transactions st
    LEFT JOIN payment_methods pm ON st.payment_method_id = pm.id
    JOIN users u ON st.created_by = u.id
    WHERE st.supplier_id = ?
    ORDER BY st.id ASC");
$tx_stmt->execute([$supplier_id]);
$transactions = $tx_stmt->fetchAll();

$total_debit = array_sum(array_column($transactions, 'debit')); // Payments made
$total_credit = array_sum(array_column($transactions, 'credit')); // Purchases billed
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-book-open text-danger me-2"></i> Supplier Khata: <?= htmlspecialchars($supplier['supplier_name']) ?>
                </h4>
                <p class="text-muted small mb-0">Phone: <?= htmlspecialchars($supplier['phone']) ?> | Type: <?= ucfirst($supplier['supplier_type']) ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Statement
                </button>
                <a href="/MeatShop/suppliers/pay.php?id=<?= $supplier['id'] ?>" class="btn btn-success rounded-pill px-3">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Record Payment
                </a>
                <a href="/MeatShop/suppliers/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Supplier Balance Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Inward Purchases (Credit)</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($total_credit) ?></h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Payments Paid (Debit)</small>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_currency($total_debit) ?></h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center bg-danger-subtle border-danger">
                    <small class="text-danger fw-bold text-uppercase">Net Outstanding Balance (Payable)</small>
                    <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($supplier['current_balance']) ?></h4>
                </div>
            </div>
        </div>

        <!-- Ledger Transactions Table -->
        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="d-none d-md-table-cell">Ref #</th>
                            <th class="d-none d-lg-table-cell">Payment Method / Details</th>
                            <th class="text-end text-success">Debit</th>
                            <th class="text-end text-danger">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    No ledger entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d-M-y', strtotime($t['transaction_date'])) ?></strong>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($t['transaction_date'])) ?></div>
                                    <div class="d-md-none small text-muted">Ref: <?= htmlspecialchars($t['reference_no'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $t['debit'] > 0 ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> text-uppercase" style="font-size: 0.7rem;">
                                        <?= str_replace('_', ' ', $t['transaction_type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold d-none d-md-table-cell"><?= htmlspecialchars($t['reference_no'] ?: '-') ?></td>
                                <td class="d-none d-lg-table-cell">
                                    <small><?= htmlspecialchars($t['notes'] ?: ($t['payment_method'] ?? 'General')) ?></small>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= $t['debit'] > 0 ? format_currency($t['debit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    <?= $t['credit'] > 0 ? format_currency($t['credit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    <?= format_currency($t['balance_after']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end d-none d-md-table-cell">Total Sum:</td>
                            <td colspan="2" class="text-end d-md-none">Total:</td>
                            <td class="text-end text-success"><?= format_currency($total_debit) ?></td>
                            <td class="text-end text-danger"><?= format_currency($total_credit) ?></td>
                            <td class="text-end text-dark"><?= format_currency($supplier['current_balance']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
