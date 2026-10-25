<?php
/**
 * Profit & Loss Report (Accurate Inventory Costing Method)
 * Meat Shop Management System
 */

$page_title = 'Profit & Loss Statement';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // 1st of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Sales Revenue & COGS in period
$sales_stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(grand_total), 0) as total_revenue,
    COALESCE(SUM(subtotal), 0) as total_subtotal,
    COALESCE(SUM(discount_amount), 0) as total_discount,
    COALESCE(SUM(cogs_total), 0) as total_cogs,
    COALESCE(SUM(total_weight), 0) as total_weight_kg,
    COUNT(id) as total_invoices
    FROM sales 
    WHERE branch_id = ? AND DATE(sale_date) BETWEEN ? AND ? AND status != 'cancelled'");
$sales_stmt->execute([$branch_id, $start_date, $end_date]);
$sales = $sales_stmt->fetch();

$revenue = (float)$sales['total_revenue'];
$cogs = (float)$sales['total_cogs'];
$gross_profit = $revenue - $cogs;
$gross_margin_pct = ($revenue > 0) ? (($gross_profit / $revenue) * 100) : 0.0;

// 2. Expenses breakdown by category
$exp_stmt = $pdo->prepare("SELECT ec.category_name, COALESCE(SUM(e.amount), 0) as cat_amount
    FROM expense_categories ec
    LEFT JOIN expenses e ON ec.id = e.category_id AND e.branch_id = ? AND e.expense_date BETWEEN ? AND ?
    GROUP BY ec.id
    HAVING cat_amount > 0
    ORDER BY cat_amount DESC");
$exp_stmt->execute([$branch_id, $start_date, $end_date]);
$expense_breakdown = $exp_stmt->fetchAll();

$total_expenses = array_sum(array_column($expense_breakdown, 'cat_amount'));
$net_operating_profit = $gross_profit - $total_expenses;
$net_margin_pct = ($revenue > 0) ? (($net_operating_profit / $revenue) * 100) : 0.0;

// 3. Wastage Cost in period
$waste_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_loss_cost), 0) as waste_cost, COALESCE(SUM(weight), 0) as waste_weight 
    FROM wastage_records WHERE branch_id = ? AND wastage_date BETWEEN ? AND ? AND status = 'approved'");
$waste_stmt->execute([$branch_id, $start_date, $end_date]);
$waste = $waste_stmt->fetch();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-sack-dollar text-success me-2"></i> Profit & Loss Financial Statement
                </h4>
                <p class="text-muted small mb-0">Period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?> (Using COGS Moving Average Costing)</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Statement
                </button>
                <a href="/MeatShop/reports/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card card-custom p-3 mb-4 no-print">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-meat flex-fill">Apply Date Range</button>
                    <a href="?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a>
                    <a href="?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Month</a>
                </div>
            </form>
        </div>

        <!-- KPI Performance Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-danger border-4">
                    <small class="text-muted fw-bold text-uppercase">Net Sales Revenue</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($revenue) ?></h4>
                    <small class="text-muted"><?= format_weight($sales['total_weight_kg']) ?> meat sold</small>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-primary border-4">
                    <small class="text-muted fw-bold text-uppercase">Gross Profit</small>
                    <h4 class="fw-bold <?= $gross_profit >= 0 ? 'text-primary' : 'text-danger' ?> mb-0 mt-1"><?= format_currency($gross_profit) ?></h4>
                    <small class="text-muted">Margin: <?= number_format($gross_margin_pct, 1) ?>%</small>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-secondary border-4">
                    <small class="text-muted fw-bold text-uppercase">Operating Expenses</small>
                    <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($total_expenses) ?></h4>
                    <small class="text-muted"><?= count($expense_breakdown) ?> Categories</small>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card card-custom p-3 text-center border-start border-success border-4">
                    <small class="text-muted fw-bold text-uppercase">Net Operating Profit</small>
                    <h4 class="fw-bold <?= $net_operating_profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0 mt-1"><?= format_currency($net_operating_profit) ?></h4>
                    <small class="text-muted">Net Margin: <?= number_format($net_margin_pct, 1) ?>%</small>
                </div>
            </div>
        </div>

        <!-- Detailed Statement Table -->
        <div class="card card-custom p-4">
            <div class="text-center border-bottom pb-3 mb-3">
                <h5 class="fw-bold mb-1 text-danger"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></h5>
                <h6 class="fw-bold text-dark mb-0">STATEMENT OF PROFIT & LOSS</h6>
                <small class="text-muted">For the period from <?= date('d-M-Y', strtotime($start_date)) ?> to <?= date('d-M-Y', strtotime($end_date)) ?></small>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th style="width: 70%;">Account Particulars / Line Item</th>
                            <th class="text-end" style="width: 30%;">Amount (<?= htmlspecialchars(get_setting('currency_code', 'PKR')) ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 1. Revenue -->
                        <tr class="table-light fw-bold">
                            <td colspan="2">1. REVENUE FROM MEAT SALES</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Gross Sales Subtotal</td>
                            <td class="text-end"><?= format_currency($sales['total_subtotal']) ?></td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-danger">Less: Customer Discounts Allowed</td>
                            <td class="text-end text-danger">- <?= format_currency($sales['total_discount']) ?></td>
                        </tr>
                        <tr class="fw-bold">
                            <td class="ps-4 text-dark">NET SALES REVENUE</td>
                            <td class="text-end text-dark"><?= format_currency($revenue) ?></td>
                        </tr>

                        <!-- 2. COGS -->
                        <tr class="table-light fw-bold">
                            <td colspan="2">2. COST OF GOODS SOLD (COGS)</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Inventory Cost of Meat Sold (Moving Avg Purchase Cost)</td>
                            <td class="text-end text-danger"><?= format_currency($cogs) ?></td>
                        </tr>
                        <tr class="fw-bold table-light fs-6">
                            <td>GROSS PROFIT (Net Revenue - COGS)</td>
                            <td class="text-end <?= $gross_profit >= 0 ? 'text-primary' : 'text-danger' ?>">
                                <?= format_currency($gross_profit) ?>
                            </td>
                        </tr>

                        <!-- 3. Expenses -->
                        <tr class="table-light fw-bold">
                            <td colspan="2">3. OPERATIONAL OVERHEAD & EXPENSES</td>
                        </tr>
                        <?php if (empty($expense_breakdown)): ?>
                            <tr>
                                <td class="ps-4 text-muted">No operational expenses recorded in this period</td>
                                <td class="text-end">Rs 0.00</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expense_breakdown as $eb): ?>
                            <tr>
                                <td class="ps-4"><?= htmlspecialchars($eb['category_name']) ?></td>
                                <td class="text-end"><?= format_currency($eb['cat_amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="fw-bold">
                            <td class="ps-4 text-dark">TOTAL OPERATIONAL EXPENSES</td>
                            <td class="text-end text-danger">- <?= format_currency($total_expenses) ?></td>
                        </tr>

                        <!-- Final Net Profit -->
                        <tr class="table-dark text-white fw-bold fs-5">
                            <td>NET OPERATING PROFIT / (LOSS)</td>
                            <td class="text-end <?= $net_operating_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= format_currency($net_operating_profit) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Notes Section -->
            <div class="mt-3 p-3 bg-light rounded-3">
                <small class="text-muted d-block">
                    <strong>Operational Note:</strong> Recorded meat wastage and spoilage loss in this period: <strong><?= format_weight($waste['waste_weight']) ?></strong> valued at <strong><?= format_currency($waste['waste_cost']) ?></strong>.
                </small>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
