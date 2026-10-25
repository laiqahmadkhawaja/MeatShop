<?php
/**
 * Reports & Business Analytics Hub
 * Meat Shop Management System
 */

$page_title = 'Reports & Analytics Hub';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="mb-4">
            <h4 class="fw-bold mb-1 text-dark">
                <i class="fa-solid fa-chart-pie text-danger me-2"></i> Reports & Business Intelligence Hub
            </h4>
            <p class="text-muted small mb-0">Audited financial statements, accurate inventory costing, P&L, sales, and yield analysis.</p>
        </div>

        <div class="row g-4">
            <!-- 1. Profit & Loss Report -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-success-subtle text-success rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-sack-dollar fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Profit & Loss Statement</h5>
                    <p class="text-muted small">Sales Revenue minus Cost of Goods Sold (COGS) equals Gross Profit, minus Operating Expenses equals Net Operating Profit.</p>
                    <a href="/MeatShop/reports/profit_loss.php" class="btn btn-meat rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> View P&L Report
                    </a>
                </div>
            </div>

            <!-- 2. Sales Report -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-receipt fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Sales & POS Report</h5>
                    <p class="text-muted small">Filter by date range, cashier, meat category, cash vs credit billing, and line-item weight breakdown.</p>
                    <a href="/MeatShop/reports/sales.php" class="btn btn-outline-danger rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> View Sales Report
                    </a>
                </div>
            </div>

            <!-- 3. Inventory Valuation Report -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-primary-subtle text-primary rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-boxes-stacked fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Stock Valuation & Movements</h5>
                    <p class="text-muted small">Current physical stock, moving average costs, cold storage status, and low-inventory warnings.</p>
                    <a href="/MeatShop/reports/inventory.php" class="btn btn-outline-primary rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> Stock Valuation
                    </a>
                </div>
            </div>

            <!-- 4. Customer Khata Balances -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-warning-subtle text-warning rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-users fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Customer Receivables (Khata)</h5>
                    <p class="text-muted small">Complete ledger balances of restaurants, hotels, regular customers, and credit limits.</p>
                    <a href="/MeatShop/customers/index.php" class="btn btn-outline-secondary rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> Customer Balances
                    </a>
                </div>
            </div>

            <!-- 5. Supplier Payables -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-dark-subtle text-dark rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-truck-field fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Supplier Payables Statement</h5>
                    <p class="text-muted small">Livestock farm accounts, slaughterhouse bills, freight costs, and outstanding payables.</p>
                    <a href="/MeatShop/suppliers/index.php" class="btn btn-outline-secondary rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> Supplier Payables
                    </a>
                </div>
            </div>

            <!-- 6. Meat Yield & Wastage Report -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-custom p-4 h-100">
                    <div class="p-3 bg-danger-subtle text-danger rounded-3 mb-3 d-inline-block" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-knife fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Processing Yield & Wastage</h5>
                    <p class="text-muted small">Slaughterhouse yield percentages, recoverable by-products, and cutting shrinkage vs spoiled meat.</p>
                    <a href="/MeatShop/processing/index.php" class="btn btn-outline-dark rounded-pill px-3 mt-auto">
                        <i class="fa-solid fa-arrow-right me-1"></i> Yield Analytics
                    </a>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
