<?php
/**
 * Create New Meat Product
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('products_manage');

$branch_id = get_current_branch_id();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $product_code = trim($_POST['product_code'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $meat_type = trim($_POST['meat_type'] ?? 'Beef');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $cut_type_id = !empty($_POST['cut_type_id']) ? (int)$_POST['cut_type_id'] : null;
    $purchase_unit_id = (int)($_POST['purchase_unit_id'] ?? 1);
    $selling_unit_id = (int)($_POST['selling_unit_id'] ?? 1);
    $default_purchase_rate = (float)($_POST['default_purchase_rate'] ?? 0.0);
    $retail_selling_rate = (float)($_POST['retail_selling_rate'] ?? 0.0);
    $wholesale_selling_rate = (float)($_POST['wholesale_selling_rate'] ?? 0.0);
    $minimum_stock_level = (float)($_POST['minimum_stock_level'] ?? 5.0);
    $storage_type = trim($_POST['storage_type'] ?? 'Cold Chiller');
    $is_raw_material = isset($_POST['is_raw_material']) ? 1 : 0;
    $is_processed = isset($_POST['is_processed']) ? 1 : 0;
    $opening_stock = (float)($_POST['opening_stock'] ?? 0.0);

    if (empty($product_name) || empty($product_code) || empty($category_id)) {
        set_flash_message('danger', 'Please provide Product Name, Item Code, and Category.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO products (product_name, product_code, barcode, meat_type, category_id, cut_type_id, purchase_unit_id, selling_unit_id, default_purchase_rate, retail_selling_rate, wholesale_selling_rate, minimum_stock_level, storage_type, is_raw_material, is_processed, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $product_name, $product_code, $barcode, $meat_type, $category_id, $cut_type_id,
                $purchase_unit_id, $selling_unit_id, $default_purchase_rate, $retail_selling_rate,
                $wholesale_selling_rate, $minimum_stock_level, $storage_type, $is_raw_material, $is_processed
            ]);
            $product_id = $pdo->lastInsertId();

            // Set Opening Stock
            if ($opening_stock > 0) {
                update_product_stock($pdo, $branch_id, $product_id, $opening_stock, $default_purchase_rate, 'adjustment_add', $product_id, 'products', 'Initial opening stock entry');
            } else {
                // Initialize branch stock row with 0
                $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, current_stock, avg_cost_price) VALUES (?, ?, 0, ?)")
                    ->execute([$branch_id, $product_id, $default_purchase_rate]);
            }

            log_audit('create_product', 'products', $product_id, ['name' => $product_name, 'code' => $product_code]);

            $pdo->commit();
            set_flash_message('success', 'Product "' . htmlspecialchars($product_name) . '" created successfully.');
            header("Location: /MeatShop/products/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Fetch dynamic options
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();
$cut_types = $pdo->query("SELECT id, cut_name FROM cut_types ORDER BY cut_name ASC")->fetchAll();
$units = $pdo->query("SELECT id, unit_name, unit_code FROM units ORDER BY unit_name ASC")->fetchAll();

// Generate automatic next item code
$count_prods = $pdo->query("SELECT COUNT(id) FROM products")->fetchColumn() + 1;
$auto_code = "MP-" . str_pad($count_prods, 4, '0', STR_PAD_LEFT);

$page_title = 'Add New Meat Product';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-plus-circle text-danger me-2"></i> Add New Meat Product
                </h4>
                <p class="text-muted small mb-0">Define meat cuts, unit prices, storage criteria, and starting inventory.</p>
            </div>
            <a href="/MeatShop/products/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>

        <form method="POST" action="">
            <div class="row g-4">
                <!-- Left Details Column -->
                <div class="col-12 col-lg-8">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Product Basic Information</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" class="form-control" placeholder="e.g. Mutton Shinwari Chops (Chaanp)" required autofocus>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold">Item Code <span class="text-danger">*</span></label>
                                <input type="text" name="product_code" class="form-control font-monospace" value="<?= htmlspecialchars($auto_code) ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold">Barcode</label>
                                <input type="text" name="barcode" class="form-control font-monospace" placeholder="e.g. 89012345">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Meat Type <span class="text-danger">*</span></label>
                                <select name="meat_type" class="form-select">
                                    <option value="Beef">Beef (Bara Gosht)</option>
                                    <option value="Mutton">Mutton (Chota Gosht)</option>
                                    <option value="Chicken">Chicken (Murghi)</option>
                                    <option value="Fish">Fish & Seafood</option>
                                    <option value="Camel">Camel Meat</option>
                                    <option value="Other">Other Meat</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold mb-0">Category <span class="text-danger">*</span></label>
                                    <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddCategoryModal" onclick="window.currentQuickSelectTarget='selCategory';">+ New</a>
                                </div>
                                <select name="category_id" id="selCategory" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-bold mb-0">Cut Type</label>
                                    <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddCutModal" onclick="window.currentQuickSelectTarget='selCutType';">+ New</a>
                                </div>
                                <select name="cut_type_id" id="selCutType" class="form-select">
                                    <option value="">Standard Cut</option>
                                    <?php foreach ($cut_types as $ct): ?>
                                        <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['cut_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Rates -->
                    <div class="card card-custom p-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Rate & Pricing Configuration (PKR)</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Default Purchase Cost (per KG)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">Rs</span>
                                    <input type="number" step="0.01" name="default_purchase_rate" class="form-control" placeholder="0.00" value="0.00">
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Retail Selling Rate (Counter) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-danger fw-bold">Rs</span>
                                    <input type="number" step="0.01" name="retail_selling_rate" class="form-control fw-bold text-danger" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Wholesale / Restaurant Rate</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-dark fw-bold">Rs</span>
                                    <input type="number" step="0.01" name="wholesale_selling_rate" class="form-control fw-bold" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Units, Storage & Inventory -->
                <div class="col-12 col-lg-4">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Units & Storage</h6>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Selling Unit <span class="text-danger">*</span></label>
                                <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddUnitModal" onclick="window.currentQuickSelectTarget='selSellingUnit';">+ New Unit</a>
                            </div>
                            <select name="selling_unit_id" id="selSellingUnit" class="form-select" required>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $u['unit_code'] === 'KG' ? 'selected' : '' ?>><?= htmlspecialchars($u['unit_name']) ?> (<?= htmlspecialchars($u['unit_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Purchase Unit</label>
                            <select name="purchase_unit_id" class="form-select">
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $u['unit_code'] === 'KG' ? 'selected' : '' ?>><?= htmlspecialchars($u['unit_name']) ?> (<?= htmlspecialchars($u['unit_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Storage Method</label>
                            <select name="storage_type" class="form-select">
                                <option value="Cold Chiller (0-4°C)" selected>Cold Chiller (0-4°C)</option>
                                <option value="Deep Freezer (-18°C)">Deep Freezer (-18°C)</option>
                                <option value="Display Counter Chiller">Display Counter Chiller</option>
                                <option value="Room Temperature">Room Temperature</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Minimum Stock Alert Level (KG)</label>
                            <input type="number" step="0.001" name="minimum_stock_level" class="form-control" value="5.000">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Starting Opening Stock (KG)</label>
                            <input type="number" step="0.001" name="opening_stock" class="form-control fw-bold text-success" value="0.000">
                            <small class="text-muted">Enter current physical weight on hand.</small>
                        </div>

                        <hr>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_processed" id="chkProcessed" checked>
                            <label class="form-check-label small fw-semibold" for="chkProcessed">Saleable Finished Cut</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_raw_material" id="chkRaw">
                            <label class="form-check-label small fw-semibold" for="chkRaw">Raw / Unprocessed Carcass</label>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-meat py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> Save Meat Product
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
