<?php
/**
 * Operating Expenses List
 * Meat Shop Management System
 */

$page_title = 'Shop Expenses & Overhead';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT e.*, ec.category_name, pm.method_name as payment_method, u.full_name as user_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN payment_methods pm ON e.payment_method_id = pm.id
    JOIN users u ON e.user_id = u.id
    WHERE e.branch_id = ?
    ORDER BY e.expense_date DESC, e.id DESC");
$stmt->execute([$branch_id]);
$expenses = $stmt->fetchAll();

$total_exp = array_sum(array_column($expenses, 'amount'));
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
                    <i class="fa-solid fa-receipt text-danger me-2"></i> Operational Expenses & Overheads
                </h4>
                <p class="text-muted small mb-0">Record shop rent, electricity, ice cooling, butcher salaries, packaging, and utilities.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/expenses/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add New Expense
                </a>
                <button class="btn btn-outline-secondary rounded-pill px-3 bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddExpenseCategoryModal">
                    <i class="fa-solid fa-tags me-1 text-danger"></i> + Category
                </button>
            </div>
        </div>

        <!-- Total Expense Card -->
        <div class="card card-custom p-3 mb-4 bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.72rem;">Total Recorded Expenses</small>
                    <h3 class="fw-extrabold text-danger mb-0"><?= format_currency($total_exp) ?></h3>
                </div>
                <div class="p-3 bg-danger-subtle text-danger rounded-circle">
                    <i class="fa-solid fa-money-bill-transfer fs-3"></i>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Expense Category</th>
                            <th>Description</th>
                            <th>Payment Method</th>
                            <th>Recorded By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-1 d-block mb-2 text-black-50"></i>
                                    No expenses recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                            <tr>
                                <td><?= date('d-M-Y', strtotime($e['expense_date'])) ?></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($e['category_name']) ?></span></td>
                                <td><?= htmlspecialchars($e['description'] ?: '-') ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($e['payment_method']) ?></small></td>
                                <td><small class="text-muted"><?= htmlspecialchars($e['user_name']) ?></small></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($e['amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
