<?php
/**
 * Edit Meat Product
 * Meat Shop Management System
 */

$page_title = 'Edit Meat Product';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();
$product_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash_message('danger', 'Product not found.');
    header("Location: /MeatShop/products/index.php");
    exit;
}

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
    $status = trim($_POST['status'] ?? 'active');

    if (empty($product_name) || empty($product_code) || empty($category_id)) {
        set_flash_message('danger', 'Please provide Product Name, Item Code, and Category.');
    } else {
        try {
            $upd = $pdo->prepare("UPDATE products SET 
                product_name = ?, product_code = ?, barcode = ?, meat_type = ?, category_id = ?, 
                cut_type_id = ?, purchase_unit_id = ?, selling_unit_id = ?, default_purchase_rate = ?, 
                retail_selling_rate = ?, wholesale_selling_rate = ?, minimum_stock_level = ?, 
                storage_type = ?, is_raw_material = ?, is_processed = ?, status = ?
                WHERE id = ?");
            $upd->execute([
                $product_name, $product_code, $barcode, $meat_type, $category_id, $cut_type_id,
                $purchase_unit_id, $selling_unit_id, $default_purchase_rate, $retail_selling_rate,
                $wholesale_selling_rate, $minimum_stock_level, $storage_type, $is_raw_material, $is_processed,
                $status, $product_id
            ]);

            log_audit('update_product', 'products', $product_id, ['name' => $product_name]);
            set_flash_message('success', 'Product updated successfully.');
            header("Location: /MeatShop/products/index.php");
            exit;
        } catch (Exception $e) {
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Dynamic options
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();
$cut_types = $pdo->query("SELECT id, cut_name FROM cut_types ORDER BY cut_name ASC")->fetchAll();
$units = $pdo->query("SELECT id, unit_name, unit_code FROM units ORDER BY unit_name ASC")->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-pen-to-square text-danger me-2"></i> Edit Product: <?= htmlspecialchars($product['product_name']) ?>
                </h4>
                <p class="text-muted small mb-0">Modify meat cuts, pricing rates, and unit assignments.</p>
            </div>
            <a href="/MeatShop/products/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>

        <form method="POST" action="">
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Product Basic Details</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-bold">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($product['product_name']) ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold">Item Code <span class="text-danger">*</span></label>
                                <input type="text" name="product_code" class="form-control font-monospace" value="<?= htmlspecialchars($product['product_code']) ?>" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-bold">Barcode</label>
                                <input type="text" name="barcode" class="form-control font-monospace" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Meat Type</label>
                                <select name="meat_type" class="form-select">
                                    <option value="Beef" <?= $product['meat_type'] === 'Beef' ? 'selected' : '' ?>>Beef (Bara Gosht)</option>
                                    <option value="Mutton" <?= $product['meat_type'] === 'Mutton' ? 'selected' : '' ?>>Mutton (Chota Gosht)</option>
                                    <option value="Chicken" <?= $product['meat_type'] === 'Chicken' ? 'selected' : '' ?>>Chicken (Murghi)</option>
                                    <option value="Fish" <?= $product['meat_type'] === 'Fish' ? 'selected' : '' ?>>Fish & Seafood</option>
                                    <option value="Camel" <?= $product['meat_type'] === 'Camel' ? 'selected' : '' ?>>Camel Meat</option>
                                    <option value="Other" <?= $product['meat_type'] === 'Other' ? 'selected' : '' ?>>Other Meat</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Cut Type</label>
                                <select name="cut_type_id" class="form-select">
                                    <option value="">Standard Cut</option>
                                    <?php foreach ($cut_types as $ct): ?>
                                        <option value="<?= $ct['id'] ?>" <?= $product['cut_type_id'] == $ct['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ct['cut_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card card-custom p-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Rate & Pricing (PKR)</h6>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Default Purchase Cost (per KG)</label>
                                <input type="number" step="0.01" name="default_purchase_rate" class="form-control" value="<?= $product['default_purchase_rate'] ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Retail Selling Rate <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="retail_selling_rate" class="form-control fw-bold text-danger" value="<?= $product['retail_selling_rate'] ?>" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small fw-bold">Wholesale Selling Rate</label>
                                <input type="number" step="0.01" name="wholesale_selling_rate" class="form-control fw-bold" value="<?= $product['wholesale_selling_rate'] ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Settings & Status</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Selling Unit</label>
                            <select name="selling_unit_id" class="form-select" required>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $product['selling_unit_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['unit_name']) ?> (<?= htmlspecialchars($u['unit_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Purchase Unit</label>
                            <select name="purchase_unit_id" class="form-select">
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $product['purchase_unit_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['unit_name']) ?> (<?= htmlspecialchars($u['unit_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Storage Method</label>
                            <select name="storage_type" class="form-select">
                                <option value="Cold Chiller (0-4°C)" <?= $product['storage_type'] === 'Cold Chiller (0-4°C)' ? 'selected' : '' ?>>Cold Chiller (0-4°C)</option>
                                <option value="Deep Freezer (-18°C)" <?= $product['storage_type'] === 'Deep Freezer (-18°C)' ? 'selected' : '' ?>>Deep Freezer (-18°C)</option>
                                <option value="Display Counter Chiller" <?= $product['storage_type'] === 'Display Counter Chiller' ? 'selected' : '' ?>>Display Counter Chiller</option>
                                <option value="Room Temperature" <?= $product['storage_type'] === 'Room Temperature' ? 'selected' : '' ?>>Room Temperature</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Min Stock Alert Level (KG)</label>
                            <input type="number" step="0.001" name="minimum_stock_level" class="form-control" value="<?= $product['minimum_stock_level'] ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Archived)</option>
                            </select>
                        </div>

                        <hr>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="is_processed" id="chkProcessed" <?= $product['is_processed'] ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="chkProcessed">Saleable Finished Cut</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_raw_material" id="chkRaw" <?= $product['is_raw_material'] ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-semibold" for="chkRaw">Raw / Unprocessed Carcass</label>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-meat py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> Update Meat Product
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
