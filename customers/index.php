<?php
/**
 * Customer Management & Khata Overview
 * Meat Shop Management System
 */

$page_title = 'Customer Khata & Receivables';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->query("SELECT c.*, 
    COUNT(s.id) as total_invoices,
    COALESCE(SUM(s.grand_total), 0) as total_sales_amount
    FROM customers c
    LEFT JOIN sales s ON c.id = s.customer_id AND s.status != 'cancelled'
    GROUP BY c.id
    ORDER BY c.customer_name ASC");
$customers = $stmt->fetchAll();

$total_receivables = 0;
foreach ($customers as $c) {
    if ($c['current_balance'] > 0) {
        $total_receivables += $c['current_balance'];
    }
}
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-users text-danger me-2"></i> Customer Khata & Receivables Ledger
                </h4>
                <p class="text-muted small mb-0">Manage retail customers, restaurant supplies, credit balances, and payment collections.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-meat rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal">
                    <i class="fa-solid fa-user-plus me-1"></i> Add New Customer
                </button>
                <a href="/MeatShop/customers/receive_payment.php" class="btn btn-outline-success rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                </a>
            </div>
        </div>

        <!-- Receivables Banner -->
        <div class="card card-custom p-3 mb-4 bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Total Outstanding Customer Receivables (Khata)</small>
                    <h3 class="fw-extrabold text-danger mb-0"><?= format_currency($total_receivables) ?></h3>
                </div>
                <div class="p-3 bg-danger-subtle text-danger rounded-circle">
                    <i class="fa-solid fa-book-bookmark fs-3"></i>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th class="d-none d-md-table-cell">Type</th>
                            <th class="d-none d-lg-table-cell">Address</th>
                            <th class="text-end d-none d-lg-table-cell">Credit Limit</th>
                            <th class="text-end d-none d-sm-table-cell">Total Billed</th>
                            <th class="text-end">Khata Balance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    No customers registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($c['customer_name']) ?></strong>
                                    <small class="text-muted d-md-none text-uppercase" style="font-size:0.68rem;"><?= str_replace('_', ' ', $c['customer_type']) ?></small>
                                </td>
                                <td><span class="font-monospace fw-semibold small"><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></span></td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size:0.7rem;">
                                        <?= str_replace('_', ' ', $c['customer_type']) ?>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($c['address'] ?: 'Counter Walk-in') ?></small></td>
                                <td class="text-end text-muted d-none d-lg-table-cell"><?= $c['credit_limit'] > 0 ? format_currency($c['credit_limit']) : 'None' ?></td>
                                <td class="text-end fw-semibold d-none d-sm-table-cell"><?= format_currency($c['total_sales_amount']) ?></td>
                                <td class="text-end">
                                    <span class="fw-bold fs-6 <?= $c['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= format_currency($c['current_balance']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/MeatShop/customers/ledger.php?id=<?= $c['id'] ?>" class="btn btn-outline-dark" title="View Customer Khata">
                                            <i class="fa-solid fa-book-open me-1"></i> Khata
                                        </a>
                                        <a href="/MeatShop/customers/receive_payment.php?id=<?= $c['id'] ?>" class="btn btn-outline-success" title="Receive Cash/Bank Payment">
                                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive
                                        </a>
                                    </div>
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
