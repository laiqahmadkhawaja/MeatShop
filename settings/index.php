<?php
/**
 * Master Settings & Dynamic Realtime Master Data Manager
 * Meat Shop Management System
 */

$page_title = 'Master Settings & Dynamic Setup';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

require_permission('settings_manage');

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop_settings'])) {
    $keys = [
        'shop_name', 'tagline', 'phone', 'email', 'address', 'currency_symbol',
        'currency_code', 'decimal_precision', 'weight_precision', 'default_tare_weight',
        'receipt_footer_text', 'low_stock_threshold'
    ];

    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            set_setting($k, trim($_POST[$k]));
        }
    }

    log_audit('update_settings', 'settings', null, 'Shop configuration updated');
    set_flash_message('success', 'Shop settings updated successfully.');
    header("Location: /MeatShop/settings/index.php");
    exit;
}

// Fetch all dynamic entities for tab management
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
$cut_types = $pdo->query("SELECT * FROM cut_types ORDER BY id ASC")->fetchAll();
$units = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT * FROM payment_methods ORDER BY id ASC")->fetchAll();
$bank_accounts = $pdo->query("SELECT * FROM bank_accounts ORDER BY id ASC")->fetchAll();
$expense_categories = $pdo->query("SELECT * FROM expense_categories ORDER BY id ASC")->fetchAll();
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-sliders text-danger me-2"></i> Master Settings & Dynamic Configuration
                </h4>
                <p class="text-muted small mb-0">Customize butcher shop profile, receipt designs, scales, categories, cuts, units, and banks.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/settings/backup.php" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-database me-1"></i> Database Backup
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4 gap-2" id="settingsTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active rounded-pill px-3 py-2 fw-semibold" id="shop-tab" data-bs-toggle="pill" data-bs-target="#tabShop" type="button">
                    <i class="fa-solid fa-store me-1"></i> Shop & Hardware Profile
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="cats-tab" data-bs-toggle="pill" data-bs-target="#tabCategories" type="button">
                    <i class="fa-solid fa-layer-group me-1"></i> Meat Categories (<?= count($categories) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="cuts-tab" data-bs-toggle="pill" data-bs-target="#tabCuts" type="button">
                    <i class="fa-solid fa-knife me-1"></i> Meat Cut Types (<?= count($cut_types) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="units-tab" data-bs-toggle="pill" data-bs-target="#tabUnits" type="button">
                    <i class="fa-solid fa-scale-balanced me-1"></i> Measuring Units (<?= count($units) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="pay-tab" data-bs-toggle="pill" data-bs-target="#tabPaymentMethods" type="button">
                    <i class="fa-solid fa-wallet me-1"></i> Payment Methods (<?= count($payment_methods) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="banks-tab" data-bs-toggle="pill" data-bs-target="#tabBankAccounts" type="button">
                    <i class="fa-solid fa-building-columns me-1"></i> Bank Accounts (<?= count($bank_accounts) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="exp-tab" data-bs-toggle="pill" data-bs-target="#tabExpenseCategories" type="button">
                    <i class="fa-solid fa-receipt me-1"></i> Expense Categories (<?= count($expense_categories) ?>)
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- 1. Shop Profile -->
            <div class="tab-pane fade show active" id="tabShop" role="tabpanel">
                <form method="POST" action="" class="card card-custom p-4">
                    <input type="hidden" name="update_shop_settings" value="1">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Shop Branding & Contact Info</h6>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Shop / Business Name</label>
                            <input type="text" name="shop_name" class="form-control fw-bold text-danger" value="<?= htmlspecialchars(get_setting('shop_name')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Tagline / Motto</label>
                            <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars(get_setting('tagline')) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Contact Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars(get_setting('phone')) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars(get_setting('email')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Shop Physical Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars(get_setting('address')) ?>" required>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <h6 class="fw-bold text-dark mb-0">Currency & Weighing Scale Configurations</h6>

                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Currency Symbol</label>
                            <input type="text" name="currency_symbol" class="form-control font-monospace" value="<?= htmlspecialchars(get_setting('currency_symbol', 'Rs.')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Currency Code</label>
                            <input type="text" name="currency_code" class="form-control font-monospace" value="<?= htmlspecialchars(get_setting('currency_code', 'PKR')) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Price Decimal Precision</label>
                            <input type="number" name="decimal_precision" class="form-control" value="<?= (int)get_setting('decimal_precision', 2) ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Default Tray Tare Weight (KG)</label>
                            <input type="number" step="0.001" name="default_tare_weight" class="form-control fw-bold text-primary" value="<?= htmlspecialchars(get_setting('default_tare_weight', '0.050')) ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Receipt Footer Thank You Message</label>
                            <textarea name="receipt_footer_text" class="form-control" rows="2"><?= htmlspecialchars(get_setting('receipt_footer_text')) ?></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-meat px-4 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-save me-1"></i> Save System Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 2. Categories -->
            <div class="tab-pane fade" id="tabCategories" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Meat Categories (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddCategoryModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Category
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Icon</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td>#<?= $c['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($c['category_name']) ?></td>
                                    <td><i class="fa-solid <?= htmlspecialchars($c['icon']) ?> text-danger me-2"></i><?= htmlspecialchars($c['icon']) ?></td>
                                    <td><span class="badge badge-subtle-success">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. Cut Types -->
            <div class="tab-pane fade" id="tabCuts" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Meat Cut Types (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddCutModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Cut Type
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Cut Name</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cut_types as $ct): ?>
                                <tr>
                                    <td>#<?= $ct['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($ct['cut_name']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($ct['description'] ?: '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. Units -->
            <div class="tab-pane fade" id="tabUnits" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Measuring Units & Conversion Factors (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddUnitModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Unit
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Unit Name</th>
                                    <th>Code</th>
                                    <th class="text-end">Conversion to 1 KG</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($units as $u): ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($u['unit_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($u['unit_code']) ?></span></td>
                                    <td class="text-end fw-bold"><?= number_format($u['conversion_factor'], 4) ?></td>
                                    <td><?= $u['is_weight'] ? 'Weight-based' : 'Piece / Count' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 5. Payment Methods -->
            <div class="tab-pane fade" id="tabPaymentMethods" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Payment Methods (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddPaymentMethodModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Payment Method
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Method Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_methods as $pm): ?>
                                <tr>
                                    <td>#<?= $pm['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($pm['method_name']) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary text-uppercase"><?= $pm['method_type'] ?></span></td>
                                    <td><span class="badge badge-subtle-success">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 6. Bank Accounts -->
            <div class="tab-pane fade" id="tabBankAccounts" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Bank & Mobile Wallet Accounts (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddBankAccountModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Bank Account
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>Bank / Wallet</th>
                                    <th>Account Title</th>
                                    <th>Account # / IBAN</th>
                                    <th class="text-end">Current Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bank_accounts as $ba): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($ba['bank_name']) ?></td>
                                    <td><?= htmlspecialchars($ba['account_title']) ?></td>
                                    <td class="font-monospace"><span class="badge bg-light text-dark border"><?= htmlspecialchars($ba['account_number']) ?></span></td>
                                    <td class="text-end fw-bold text-success"><?= format_currency($ba['current_balance']) ?></td>
                                    <td><span class="badge badge-subtle-success">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 7. Expense Categories -->
            <div class="tab-pane fade" id="tabExpenseCategories" role="tabpanel">
                <div class="card card-custom p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">Expense Categories (Realtime Add/Edit)</h6>
                        <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickAddExpenseCategoryModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Expense Category
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light fs-xs text-uppercase">
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expense_categories as $ec): ?>
                                <tr>
                                    <td>#<?= $ec['id'] ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($ec['category_name']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($ec['description'] ?: '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
