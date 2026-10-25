<?php
/**
 * Stock Movement Audit Trail
 * Meat Shop Management System
 */

$page_title = 'Stock Movement Audit History';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT sm.*, p.product_name, p.product_code, u.full_name as user_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    WHERE sm.branch_id = ?
    ORDER BY sm.id DESC LIMIT 100");
$stmt->execute([$branch_id]);
$movements = $stmt->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-timeline text-danger me-2"></i> Stock Movement Audit Trail
                </h4>
                <p class="text-muted small mb-0">Trace every gram of meat entering, sold, processed, adjusted, or discarded.</p>
            </div>
            <a href="/MeatShop/inventory/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Date & Time</th>
                            <th>Meat Product</th>
                            <th>Movement Type</th>
                            <th class="text-end">Stock Before</th>
                            <th class="text-end">Qty Changed</th>
                            <th class="text-end">Stock After</th>
                            <th>Reason / Reference</th>
                            <th>Logged By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No stock movements logged.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($movements as $m): 
                                $is_pos = $m['quantity'] > 0;
                            ?>
                            <tr>
                                <td><?= date('d-M-Y h:i A', strtotime($m['created_at'])) ?></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($m['product_name']) ?></td>
                                <td>
                                    <span class="badge <?= $is_pos ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> text-uppercase">
                                        <?= str_replace('_', ' ', $m['movement_type']) ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted"><?= format_weight($m['stock_before']) ?></td>
                                <td class="text-end fw-bold <?= $is_pos ? 'text-success' : 'text-danger' ?>">
                                    <?= $is_pos ? '+' : '' ?><?= format_weight($m['quantity']) ?>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_weight($m['stock_after']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($m['notes'] ?: '-') ?></small></td>
                                <td><small class="text-muted"><?= htmlspecialchars($m['user_name']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
