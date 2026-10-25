<?php
/**
 * Dashboard & Business Overview
 * Meat Shop Management System
 */

$page_title = 'Dashboard & Business Overview';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$branch_id = get_current_branch_id();
$today = date('Y-m-d');

// 1. Today's Sales KPIs
$sales_stmt = $pdo->prepare("SELECT 
    COUNT(id) as total_invoices,
    COALESCE(SUM(grand_total), 0) as today_sales,
    COALESCE(SUM(paid_amount), 0) as today_cash_collected,
    COALESCE(SUM(balance_amount), 0) as today_credit_sales,
    COALESCE(SUM(cogs_total), 0) as today_cogs,
    COALESCE(SUM(total_weight), 0) as today_meat_sold_kg
    FROM sales 
    WHERE branch_id = ? AND DATE(sale_date) = ? AND status != 'cancelled'");
$sales_stmt->execute([$branch_id, $today]);
$sales_kpi = $sales_stmt->fetch();

// 2. Today's Purchases
$purch_stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(grand_total), 0) as today_purchases,
    COALESCE(SUM(net_weight), 0) as today_purchased_kg
    FROM purchases 
    WHERE branch_id = ? AND purchase_date = ?");
$purch_stmt->execute([$branch_id, $today]);
$purch_kpi = $purch_stmt->fetch();

// 3. Today's Expenses
$exp_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as today_expenses FROM expenses WHERE branch_id = ? AND expense_date = ?");
$exp_stmt->execute([$branch_id, $today]);
$exp_kpi = $exp_stmt->fetch();

// 4. Profit calculations
$today_sales = (float)$sales_kpi['today_sales'];
$today_cogs = (float)$sales_kpi['today_cogs'];
$today_expenses = (float)$exp_kpi['today_expenses'];
$today_gross_profit = $today_sales - $today_cogs;
$today_net_profit = $today_gross_profit - $today_expenses;

// 5. Total Customer Outstanding (Receivables)
$cust_stmt = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) as total_receivable FROM customers WHERE current_balance > 0");
$total_receivable = (float)$cust_stmt->fetchColumn();

// 6. Total Supplier Outstanding (Payables)
$supp_stmt = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) as total_payable FROM suppliers WHERE current_balance > 0");
$total_payable = (float)$supp_stmt->fetchColumn();

// 7. Inventory Value & Total Meat Available (KG)
$inv_stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(bs.current_stock), 0) as total_stock_kg,
    COALESCE(SUM(bs.current_stock * bs.avg_cost_price), 0) as total_stock_value,
    COUNT(CASE WHEN bs.current_stock <= p.minimum_stock_level THEN 1 END) as low_stock_count
    FROM branch_stock bs
    JOIN products p ON bs.product_id = p.id
    WHERE bs.branch_id = ?");
$inv_stmt->execute([$branch_id]);
$inv_kpi = $inv_stmt->fetch();

// 8. Today's Wastage
$waste_stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(weight), 0) as today_waste_kg,
    COALESCE(SUM(total_loss_cost), 0) as today_waste_cost
    FROM wastage_records 
    WHERE branch_id = ? AND wastage_date = ? AND status = 'approved'");
$waste_stmt->execute([$branch_id, $today]);
$waste_kpi = $waste_stmt->fetch();

// 9. Cash in Drawer (Current active shift)
$active_shift = get_active_shift($pdo, $branch_id, get_current_user_id());
$cash_in_drawer = $active_shift ? (float)$active_shift['expected_closing_cash'] : 0.00;

// 10. Low Stock Products list
$low_stock_list_stmt = $pdo->prepare("SELECT p.product_name, p.product_code, bs.current_stock, p.minimum_stock_level, u.unit_code
    FROM branch_stock bs
    JOIN products p ON bs.product_id = p.id
    JOIN units u ON p.selling_unit_id = u.id
    WHERE bs.branch_id = ? AND bs.current_stock <= p.minimum_stock_level
    ORDER BY bs.current_stock ASC LIMIT 5");
$low_stock_list_stmt->execute([$branch_id]);
$low_stock_items = $low_stock_list_stmt->fetchAll();

// 11. Chart Data: Sales by Category
$cat_sales_stmt = $pdo->prepare("SELECT c.category_name, COALESCE(SUM(si.line_total), 0) as total_sales
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE s.branch_id = ? AND s.status != 'cancelled'
    GROUP BY c.id
    ORDER BY total_sales DESC LIMIT 6");
$cat_sales_stmt->execute([$branch_id]);
$cat_sales_data = $cat_sales_stmt->fetchAll();

$cat_labels = [];
$cat_values = [];
foreach ($cat_sales_data as $cs) {
    $cat_labels[] = $cs['category_name'];
    $cat_values[] = (float)$cs['total_sales'];
}

// 12. Chart Data: Last 7 Days Sales Trend
$trend_stmt = $pdo->prepare("SELECT DATE(sale_date) as sdate, COALESCE(SUM(grand_total), 0) as daily_total
    FROM sales
    WHERE branch_id = ? AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status != 'cancelled'
    GROUP BY DATE(sale_date)
    ORDER BY sdate ASC");
$trend_stmt->execute([$branch_id]);
$trend_data = $trend_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_days = [];
$chart_sales = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_days[] = date('M d', strtotime($d));
    $chart_sales[] = (float)($trend_data[$d] ?? 0);
}
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <!-- Flash Alert Message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner & Quick Action Buttons -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-chart-line text-danger me-2"></i> Business Overview & Performance
                </h4>
                <p class="text-muted small mb-0">Real-time butcher shop analytics, sales, live inventory, and profit management.</p>
            </div>
            <!-- Quick Actions Toolbar -->
            <div class="d-flex flex-wrap gap-2">
                <a href="/MeatShop/pos/index.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-cash-register me-1"></i> New Sale (POS)
                </a>
                <a href="/MeatShop/purchases/create.php" class="btn btn-outline-dark rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-truck-ramp-box me-1 text-danger"></i> New Purchase
                </a>
                <a href="/MeatShop/customers/receive_payment.php" class="btn btn-outline-success rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                </a>
                <a href="/MeatShop/wastage/create.php" class="btn btn-outline-warning rounded-pill px-3 bg-white shadow-sm text-dark">
                    <i class="fa-solid fa-trash-can me-1 text-warning"></i> Record Wastage
                </a>
            </div>
        </div>

        <!-- Row 1: Primary KPIs (Sales, Gross Profit, Net Profit, Cash In Drawer) -->
        <div class="row g-3 mb-4">
            <!-- Today's Total Sales -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card card-custom p-3 border-start border-danger border-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.72rem;">Today's Sales</small>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($today_sales) ?></h4>
                            <small class="text-success fw-semibold"><i class="fa-solid fa-weight-scale me-1"></i><?= format_weight($sales_kpi['today_meat_sold_kg']) ?> sold</small>
                        </div>
                        <div class="p-2 rounded-circle bg-danger-subtle text-danger" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-receipt fs-5"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between small text-muted">
                        <span>Cash: <strong><?= format_currency($sales_kpi['today_cash_collected']) ?></strong></span>
                        <span>Credit: <strong class="text-danger"><?= format_currency($sales_kpi['today_credit_sales']) ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Today's Gross Profit -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card card-custom p-3 border-start border-primary border-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.72rem;">Today's Gross Profit</small>
                            <h4 class="fw-bold <?= $today_gross_profit >= 0 ? 'text-primary' : 'text-danger' ?> mb-0 mt-1">
                                <?= format_currency($today_gross_profit) ?>
                            </h4>
                            <small class="text-muted">COGS: <?= format_currency($today_cogs) ?></small>
                        </div>
                        <div class="p-2 rounded-circle bg-primary-subtle text-primary" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-chart-pie fs-5"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between small text-muted">
                        <span>Margin: <strong><?= $today_sales > 0 ? round(($today_gross_profit / $today_sales) * 100, 1) : 0 ?>%</strong></span>
                        <span>Invoices: <strong><?= (int)$sales_kpi['total_invoices'] ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Today's Net Operating Profit -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card card-custom p-3 border-start border-success border-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.72rem;">Today's Net Profit</small>
                            <h4 class="fw-bold <?= $today_net_profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0 mt-1">
                                <?= format_currency($today_net_profit) ?>
                            </h4>
                            <small class="text-muted">Expenses: <?= format_currency($today_expenses) ?></small>
                        </div>
                        <div class="p-2 rounded-circle bg-success-subtle text-success" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-sack-dollar fs-5"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between small text-muted">
                        <span>Purchases: <strong><?= format_currency($purch_kpi['today_purchases']) ?></strong></span>
                        <a href="/MeatShop/reports/profit_loss.php" class="text-success text-decoration-none fw-bold">Report &rarr;</a>
                    </div>
                </div>
            </div>

            <!-- Cash In Drawer / Shift -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card card-custom p-3 border-start border-warning border-4">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.72rem;">Cash in Drawer</small>
                            <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($cash_in_drawer) ?></h4>
                            <small class="<?= $active_shift ? 'text-success' : 'text-danger' ?>">
                                <i class="fa-solid fa-circle me-1" style="font-size: 8px;"></i>
                                <?= $active_shift ? 'Shift #' . $active_shift['id'] . ' Active' : 'No Open Shift' ?>
                            </small>
                        </div>
                        <div class="p-2 rounded-circle bg-warning-subtle text-warning" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-money-bill-wave fs-5"></i>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between small text-muted">
                        <a href="/MeatShop/shifts/index.php" class="text-dark text-decoration-none fw-bold">Manage Shift &rarr;</a>
                        <a href="/MeatShop/expenses/create.php" class="text-danger text-decoration-none fw-bold">+ Expense</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Secondary KPIs (Customer Khata, Supplier Payables, Live Meat Stock, Wastage) -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card card-custom p-3">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Customer Khata (Receivables)</small>
                    <h5 class="fw-bold text-danger mb-1 mt-1"><?= format_currency($total_receivable) ?></h5>
                    <a href="/MeatShop/customers/index.php" class="small text-danger text-decoration-none fw-semibold">View Customer Balances &rarr;</a>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-custom p-3">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Supplier Payables (Outstanding)</small>
                    <h5 class="fw-bold text-dark mb-1 mt-1"><?= format_currency($total_payable) ?></h5>
                    <a href="/MeatShop/suppliers/index.php" class="small text-dark text-decoration-none fw-semibold">View Supplier Ledgers &rarr;</a>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-custom p-3">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Total Available Meat</small>
                    <h5 class="fw-bold text-success mb-1 mt-1"><?= format_weight($inv_kpi['total_stock_kg']) ?></h5>
                    <small class="text-muted">Value: <?= format_currency($inv_kpi['total_stock_value']) ?></small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card card-custom p-3">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Today's Wastage & Loss</small>
                    <h5 class="fw-bold text-warning mb-1 mt-1"><?= format_weight($waste_kpi['today_waste_kg']) ?></h5>
                    <small class="text-muted">Loss: <?= format_currency($waste_kpi['today_waste_cost']) ?></small>
                </div>
            </div>
        </div>

        <!-- Row 3: Charts & Inventory Alerts -->
        <div class="row g-3 mb-4">
            <!-- 7-Day Sales Trend Chart -->
            <div class="col-12 col-lg-8">
                <div class="card card-custom h-100">
                    <div class="card-header-clean d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-area text-danger me-2"></i> Weekly Sales Trend</h6>
                        <span class="badge bg-light text-muted border">Last 7 Days</span>
                    </div>
                    <div class="card-body">
                        <canvas id="salesTrendChart" style="min-height: 250px; max-height: 280px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sales by Meat Category Chart -->
            <div class="col-12 col-lg-4">
                <div class="card card-custom h-100">
                    <div class="card-header-clean d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-pie text-danger me-2"></i> Sales by Meat Type</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <canvas id="categorySalesChart" style="max-height: 230px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Low Stock Alerts & Recent Invoices -->
        <div class="row g-3">
            <!-- Low Stock Table -->
            <div class="col-12 col-lg-6">
                <div class="card card-custom h-100">
                    <div class="card-header-clean d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Low Stock Alerts
                        </h6>
                        <a href="/MeatShop/inventory/index.php" class="btn btn-sm btn-outline-danger rounded-pill">View All Stock</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th class="text-end">Current Stock</th>
                                    <th class="text-end">Min Threshold</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($low_stock_items)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fa-solid fa-circle-check text-success fs-3 d-block mb-2"></i>
                                            All meat stocks are at healthy operational levels!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($low_stock_items as $item): ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['product_code']) ?></span></td>
                                        <td class="text-end fw-bold text-danger"><?= format_weight($item['current_stock'], $item['unit_code']) ?></td>
                                        <td class="text-end text-muted"><?= format_weight($item['minimum_stock_level'], $item['unit_code']) ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-subtle-danger">Low Stock</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices Table -->
            <div class="col-12 col-lg-6">
                <div class="card card-custom h-100">
                    <div class="card-header-clean d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-clock-rotate-left text-danger me-2"></i> Recent Counter Sales
                        </h6>
                        <a href="/MeatShop/reports/sales.php" class="btn btn-sm btn-outline-secondary rounded-pill">Sales History</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th class="text-end">Weight</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_sales_stmt = $pdo->prepare("SELECT s.*, c.customer_name 
                                    FROM sales s
                                    JOIN customers c ON s.customer_id = c.id
                                    WHERE s.branch_id = ?
                                    ORDER BY s.id DESC LIMIT 5");
                                $recent_sales_stmt->execute([$branch_id]);
                                $recent_sales = $recent_sales_stmt->fetchAll();
                                ?>
                                <?php if (empty($recent_sales)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No sales invoices recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_sales as $rs): ?>
                                    <tr>
                                        <td class="fw-bold text-danger"><?= htmlspecialchars($rs['invoice_no']) ?></td>
                                        <td><?= htmlspecialchars($rs['customer_name']) ?></td>
                                        <td class="text-end fw-semibold"><?= format_weight($rs['total_weight']) ?></td>
                                        <td class="text-end fw-bold"><?= format_currency($rs['grand_total']) ?></td>
                                        <td class="text-center">
                                            <a href="/MeatShop/pos/receipt.php?id=<?= $rs['id'] ?>" target="_blank" class="btn btn-sm btn-light border p-1 px-2" title="Print Receipt">
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
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Chart.js Library (Dashboard only) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- Chart.js Initialization -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Weekly Sales Trend Chart
    const trendCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_days) ?>,
            datasets: [{
                label: 'Sales (<?= htmlspecialchars(get_setting('currency_code', 'PKR')) ?>)',
                data: <?= json_encode($chart_sales) ?>,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#991b1b',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        callback: function(value) { return 'Rs ' + value; }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // 2. Sales by Category Chart
    const catCtx = document.getElementById('categorySalesChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($cat_labels) ?>,
            datasets: [{
                data: <?= json_encode($cat_values) ?>,
                backgroundColor: [
                    '#991b1b', '#dc2626', '#f87171', '#d97706', '#fbbf24', '#0284c7'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            },
            cutout: '65%'
        }
    });
});
</script>
