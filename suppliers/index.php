<?php
/**
 * Suppliers List & Ledgers Overview
 * Meat Shop Management System
 */

$page_title = 'Suppliers & Khata Ledgers';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->query("SELECT s.*, 
    COUNT(p.id) as total_purchases,
    COALESCE(SUM(p.grand_total), 0) as total_purchase_amount
    FROM suppliers s
    LEFT JOIN purchases p ON s.id = p.supplier_id
    GROUP BY s.id
    ORDER BY s.supplier_name ASC");
$suppliers = $stmt->fetchAll();

// Total payables
$tot_pay = array_sum(array_column($suppliers, 'current_balance'));
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
                    <i class="fa-solid fa-truck-field text-danger me-2"></i> Meat Suppliers & Payables Ledger
                </h4>
                <p class="text-muted small mb-0">Track farm suppliers, slaughterhouses, purchase invoices, and payable balances.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-meat rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Supplier
                </button>
            </div>
        </div>

        <!-- Payable Banner -->
        <div class="card card-custom p-3 mb-4 bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Total Outstanding Supplier Payables</small>
                    <h3 class="fw-extrabold text-danger mb-0"><?= format_currency($tot_pay) ?></h3>
                </div>
                <div class="p-3 bg-danger-subtle text-danger rounded-circle">
                    <i class="fa-solid fa-money-check-dollar fs-3"></i>
                </div>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Supplier / Farm</th>
                            <th class="d-none d-lg-table-cell">Contact</th>
                            <th>Phone</th>
                            <th class="d-none d-md-table-cell">Type</th>
                            <th class="d-none d-lg-table-cell">Address</th>
                            <th class="text-end d-none d-sm-table-cell">Purchases</th>
                            <th class="text-end">Payable Due</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-truck-field fs-1 d-block mb-2 text-black-50"></i>
                                    No suppliers registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($s['supplier_name']) ?></strong>
                                    <small class="text-muted d-md-none text-uppercase" style="font-size:0.68rem;"><?= str_replace('_', ' ', $s['supplier_type']) ?></small>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($s['contact_person'] ?: 'N/A') ?></td>
                                <td><span class="font-monospace fw-semibold small"><?= htmlspecialchars($s['phone']) ?></span></td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size: 0.7rem;">
                                        <?= str_replace('_', ' ', $s['supplier_type']) ?>
                                    </span>
                                </td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($s['address'] ?: 'N/A') ?></small></td>
                                <td class="text-end fw-semibold d-none d-sm-table-cell"><?= format_currency($s['total_purchase_amount']) ?></td>
                                <td class="text-end">
                                    <span class="fw-bold fs-6 <?= $s['current_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= format_currency($s['current_balance']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/MeatShop/suppliers/ledger.php?id=<?= $s['id'] ?>" class="btn btn-outline-dark" title="View Khata Ledger Statement">
                                            <i class="fa-solid fa-book-open me-1"></i> Ledger
                                        </a>
                                        <a href="/MeatShop/suppliers/pay.php?id=<?= $s['id'] ?>" class="btn btn-outline-success" title="Record Payment">
                                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Pay
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
