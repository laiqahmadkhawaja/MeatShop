<?php
/**
 * Meat Purchases List
 * Meat Shop Management System
 */

$page_title = 'Meat Purchases & Inward Stock';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT p.*, s.supplier_name, s.phone as supplier_phone, u.full_name as created_by_name
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    JOIN users u ON p.created_by = u.id
    WHERE p.branch_id = ?
    ORDER BY p.id DESC");
$stmt->execute([$branch_id]);
$purchases = $stmt->fetchAll();
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
                    <i class="fa-solid fa-truck-ramp-box text-danger me-2"></i> Meat Purchases & Supplier Invoices
                </h4>
                <p class="text-muted small mb-0">Record livestock/carcass intake, gross & tare weights, freight charges, and payments.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/purchases/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> New Purchase Entry
                </a>
                <button class="btn btn-outline-secondary rounded-pill px-3 bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddSupplierModal">
                    <i class="fa-solid fa-truck-field me-1 text-danger"></i> + Supplier
                </button>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Invoice #</th>
                            <th class="d-none d-sm-table-cell">Date</th>
                            <th>Supplier</th>
                            <th class="text-end d-none d-md-table-cell">Gross Wt</th>
                            <th class="text-end">Net Wt</th>
                            <th class="text-end">Total</th>
                            <th class="text-end d-none d-sm-table-cell">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-1 d-block mb-2 text-black-50"></i>
                                    No purchase entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="fw-bold text-danger">
                                    <?= htmlspecialchars($p['invoice_no']) ?>
                                    <small class="text-muted d-block d-sm-none"><?= date('d-M', strtotime($p['purchase_date'])) ?></small>
                                </td>
                                <td class="d-none d-sm-table-cell"><?= date('d-M-Y', strtotime($p['purchase_date'])) ?></td>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($p['supplier_name']) ?></strong>
                                    <small class="text-muted d-none d-sm-inline"><?= htmlspecialchars($p['supplier_phone']) ?></small>
                                </td>
                                <td class="text-end text-muted d-none d-md-table-cell"><?= format_weight($p['gross_weight']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= format_weight($p['net_weight']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= format_currency($p['grand_total']) ?></td>
                                <td class="text-end text-success fw-semibold d-none d-sm-table-cell"><?= format_currency($p['paid_amount']) ?></td>
                                <td class="text-end fw-bold <?= $p['balance_amount'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= format_currency($p['balance_amount']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['payment_status'] === 'paid'): ?>
                                        <span class="badge badge-subtle-success">Paid</span>
                                    <?php elseif ($p['payment_status'] === 'partial'): ?>
                                        <span class="badge badge-subtle-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge badge-subtle-danger">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="/MeatShop/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-light border px-2" title="View Purchase Details">
                                        <i class="fa-solid fa-eye text-primary"></i>
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
