<?php
/**
 * Inventory & Live Meat Stock Overview
 * Meat Shop Management System
 */

$page_title = 'Live Meat Inventory & Batches';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT p.*, c.category_name, u.unit_code, 
    COALESCE(bs.current_stock, 0) as current_stock,
    COALESCE(bs.avg_cost_price, p.default_purchase_rate) as avg_cost
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN units u ON p.selling_unit_id = u.id
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
    WHERE p.status = 'active'
    ORDER BY c.category_name ASC, p.product_name ASC");
$stmt->execute([$branch_id]);
$stocks = $stmt->fetchAll();

$total_weight_kg = 0;
$total_valuation = 0;
$low_stock_count = 0;

foreach ($stocks as $st) {
    $total_weight_kg += (float)$st['current_stock'];
    $total_valuation += ((float)$st['current_stock'] * (float)$st['avg_cost']);
    if ($st['current_stock'] <= $st['minimum_stock_level']) {
        $low_stock_count++;
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
                    <i class="fa-solid fa-boxes-stacked text-danger me-2"></i> Live Meat Stock & Inventory Valuation
                </h4>
                <p class="text-muted small mb-0">Weight-based stock levels, moving average cost valuation, and branch inventory audits.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/inventory/adjust.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
                </a>
                <a href="/MeatShop/inventory/transfer.php" class="btn btn-outline-dark rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-arrow-right-arrow-left me-1 text-danger"></i> Branch Transfer
                </a>
                <a href="/MeatShop/inventory/movements.php" class="btn btn-outline-secondary rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-timeline me-1"></i> Stock Audit Trail
                </a>
            </div>
        </div>

        <!-- Inventory KPI Widgets -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 border-start border-success border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Meat Available in Stock</small>
                    <h3 class="fw-extrabold text-success mb-0 mt-1"><?= format_weight($total_weight_kg) ?></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 border-start border-primary border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Stock Valuation (Cost)</small>
                    <h3 class="fw-extrabold text-primary mb-0 mt-1"><?= format_currency($total_valuation) ?></h3>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 border-start border-danger border-4">
                    <small class="text-muted fw-bold text-uppercase">Low Stock Alert Products</small>
                    <h3 class="fw-extrabold <?= $low_stock_count > 0 ? 'text-danger' : 'text-dark' ?> mb-0 mt-1">
                        <?= $low_stock_count ?> Products
                    </h3>
                </div>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Meat Product</th>
                            <th class="d-none d-sm-table-cell">Code</th>
                            <th class="d-none d-md-table-cell">Category</th>
                            <th class="d-none d-lg-table-cell">Storage</th>
                            <th class="text-end d-none d-md-table-cell">Avg Cost</th>
                            <th class="text-end">Retail Rate</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-end d-none d-sm-table-cell">Valuation</th>
                            <th class="text-center">Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stocks)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No products configured in inventory.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stocks as $st): 
                                $is_low = $st['current_stock'] <= $st['minimum_stock_level'];
                                $val = $st['current_stock'] * $st['avg_cost'];
                            ?>
                            <tr>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($st['product_name']) ?></strong>
                                    <small class="text-muted d-sm-none font-monospace"><?= htmlspecialchars($st['product_code']) ?> &bull; </small>
                                    <small class="text-muted"><?= $st['is_raw_material'] ? 'Raw Carcass' : 'Processed Cut' ?></small>
                                </td>
                                <td class="d-none d-sm-table-cell"><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($st['product_code']) ?></span></td>
                                <td class="d-none d-md-table-cell"><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($st['category_name']) ?></span></td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($st['storage_type']) ?></small></td>
                                <td class="text-end text-muted d-none d-md-table-cell"><?= format_currency($st['avg_cost']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($st['retail_selling_rate']) ?></td>
                                <td class="text-end fw-bold fs-6 <?= $is_low ? 'text-danger' : 'text-success' ?>">
                                    <?= format_weight($st['current_stock'], $st['unit_code']) ?>
                                </td>
                                <td class="text-end fw-bold text-dark d-none d-sm-table-cell"><?= format_currency($val) ?></td>
                                <td class="text-center">
                                    <?php if ($st['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($is_low): ?>
                                        <span class="badge bg-warning text-dark">Low Stock Alert</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Optimal</span>
                                    <?php endif; ?>
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
