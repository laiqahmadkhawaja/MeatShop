<?php
/**
 * Global Sidebar Navigation
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside id="sidebar">
    <div class="brand-header">
        <div class="brand-icon">
            <i class="fa-solid fa-drumstick-bite"></i>
        </div>
        <div class="brand-text overflow-hidden">
            <h6 class="mb-0 text-white fw-bold text-truncate"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></h6>
            <small class="text-white-50 d-block text-truncate" style="font-size: 0.72rem;">Management & POS</small>
        </div>
    </div>

    <div class="py-2">
        <div class="nav-header">MAIN</div>
        <a href="/MeatShop/index.php" class="nav-link <?= ($current_page == 'index.php' && $current_dir == 'MeatShop') ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>
        <a href="/MeatShop/pos/index.php" class="nav-link <?= ($current_dir == 'pos') ? 'active' : '' ?>">
            <i class="fa-solid fa-cash-register text-warning"></i> Fast POS & Billing
        </a>

        <div class="nav-header">MEAT INVENTORY & CUTS</div>
        <a href="/MeatShop/products/index.php" class="nav-link <?= ($current_dir == 'products' && $current_page == 'index.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-meat"></i> Products & Cuts
        </a>
        <a href="/MeatShop/inventory/index.php" class="nav-link <?= ($current_dir == 'inventory') ? 'active' : '' ?>">
            <i class="fa-solid fa-boxes-stacked"></i> Stock & Batches
        </a>
        <a href="/MeatShop/wastage/index.php" class="nav-link <?= ($current_dir == 'wastage') ? 'active' : '' ?>">
            <i class="fa-solid fa-trash-can"></i> Cutting & Wastage
        </a>

        <div class="nav-header">PURCHASE & PROCESSING</div>
        <a href="/MeatShop/purchases/index.php" class="nav-link <?= ($current_dir == 'purchases') ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-flatbed"></i> Meat Purchases
        </a>
        <a href="/MeatShop/processing/index.php" class="nav-link <?= ($current_dir == 'processing') ? 'active' : '' ?>">
            <i class="fa-solid fa-cow"></i> Live Animal & Slaughter
        </a>

        <div class="nav-header">KHATA & LEDGERS</div>
        <a href="/MeatShop/customers/index.php" class="nav-link <?= ($current_dir == 'customers') ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> Customer Khata
        </a>
        <a href="/MeatShop/suppliers/index.php" class="nav-link <?= ($current_dir == 'suppliers') ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-field"></i> Supplier Ledgers
        </a>
        <a href="/MeatShop/orders/index.php" class="nav-link <?= ($current_dir == 'orders') ? 'active' : '' ?>">
            <i class="fa-solid fa-utensils"></i> Restaurant / Bookings
        </a>

        <div class="nav-header">FINANCE & REGISTER</div>
        <a href="/MeatShop/shifts/index.php" class="nav-link <?= ($current_dir == 'shifts') ? 'active' : '' ?>">
            <i class="fa-solid fa-user-clock"></i> Cash Register / Shift
        </a>
        <a href="/MeatShop/expenses/index.php" class="nav-link <?= ($current_dir == 'expenses') ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt"></i> Expenses
        </a>
        <a href="/MeatShop/reports/index.php" class="nav-link <?= ($current_dir == 'reports') ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i> Profit & Loss / Reports
        </a>

        <?php if (has_permission('settings_manage') || get_current_role_name() === 'admin'): ?>
        <div class="nav-header">ADMINISTRATION</div>
        <a href="/MeatShop/settings/index.php" class="nav-link <?= ($current_dir == 'settings') ? 'active' : '' ?>">
            <i class="fa-solid fa-sliders"></i> Master Settings
        </a>
        <a href="/MeatShop/settings/branches.php" class="nav-link <?= ($current_page == 'branches.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-store"></i> Multi-Branch
        </a>
        <a href="/MeatShop/settings/users.php" class="nav-link <?= ($current_page == 'users.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-user-shield"></i> Users & Security
        </a>
        <a href="/MeatShop/settings/backup.php" class="nav-link <?= ($current_page == 'backup.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-database"></i> Database Backup
        </a>
        <?php endif; ?>

        <div class="p-3 mt-3 pb-5">
            <a href="/MeatShop/logout.php" class="btn btn-outline-danger btn-sm w-100 rounded-pill text-white border-danger-subtle shadow-sm">
                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>
</aside>
