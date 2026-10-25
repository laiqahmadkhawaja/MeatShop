<?php
/**
 * Detailed Inventory Valuation & Stock Movement Report
 * Meat Shop Management System
 */

$page_title = 'Inventory Valuation Report';
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

$total_kg = 0;
$total_cost_val = 0;
$total_retail_val = 0;

foreach ($stocks as $s) {
    $total_kg += (float)$s['current_stock'];
    $total_cost_val += ((float)$s['current_stock'] * (float)$s['avg_cost']);
    $total_retail_val += ((float)$s['current_stock'] * (float)$s['retail_selling_rate']);
}

$potential_profit = $total_retail_val - $total_cost_val;
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i> Meat Inventory Valuation Report
                </h4>
                <p class="text-muted small mb-0">Live stock valuation comparing Moving Average Purchase Cost vs Expected Retail Value.</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Report
                </button>
                <a href="/MeatShop/reports/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-success border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Available Weight</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_weight($total_kg) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-primary border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Inventory Cost</small>
                    <h4 class="fw-bold text-primary mb-0 mt-1"><?= format_currency($total_cost_val) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-danger border-4">
                    <small class="text-muted fw-bold text-uppercase">Expected Retail Value</small>
                    <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($total_retail_val) ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-warning border-4">
                    <small class="text-muted fw-bold text-uppercase">Projected Gross Margin</small>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_currency($potential_profit) ?></h4>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Meat Product</th>
                            <th class="d-none d-md-table-cell">Category</th>
                            <th class="d-none d-lg-table-cell">Storage</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-end d-none d-md-table-cell">Avg Cost</th>
                            <th class="text-end">Cost Valuation</th>
                            <th class="text-end d-none d-lg-table-cell">Retail Rate</th>
                            <th class="text-end d-none d-sm-table-cell">Retail Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stocks as $s): 
                            $c_val = $s['current_stock'] * $s['avg_cost'];
                            $r_val = $s['current_stock'] * $s['retail_selling_rate'];
                        ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <?= htmlspecialchars($s['product_name']) ?>
                                <div class="small text-muted d-md-none"><?= htmlspecialchars($s['category_name']) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($s['category_name']) ?></td>
                            <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($s['storage_type']) ?></small></td>
                            <td class="text-end fw-bold text-dark"><?= format_weight($s['current_stock'], $s['unit_code']) ?></td>
                            <td class="text-end text-muted d-none d-md-table-cell"><?= format_currency($s['avg_cost']) ?></td>
                            <td class="text-end fw-bold text-primary"><?= format_currency($c_val) ?></td>
                            <td class="text-end text-danger d-none d-lg-table-cell"><?= format_currency($s['retail_selling_rate']) ?></td>
                            <td class="text-end fw-bold text-success d-none d-sm-table-cell"><?= format_currency($r_val) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="3" class="text-end d-none d-md-table-cell">Total Valuation:</td>
                            <td class="text-end d-md-none">Total:</td>
                            <td class="text-end"><?= format_weight($total_kg) ?></td>
                            <td class="d-none d-md-table-cell"></td>
                            <td class="text-end text-primary"><?= format_currency($total_cost_val) ?></td>
                            <td class="d-none d-lg-table-cell"></td>
                            <td class="text-end text-success d-none d-sm-table-cell"><?= format_currency($total_retail_val) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
