<?php
/**
 * Products & Meat Cuts Management
 * Meat Shop Management System
 */

$page_title = 'Meat Products & Cuts';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

// Filters
$cat_filter = $_GET['cat'] ?? '';
$search = trim($_GET['q'] ?? '');

$query = "SELECT p.*, c.category_name, ct.cut_name, u.unit_code, COALESCE(bs.current_stock, 0) as stock_qty, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as current_cost
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN cut_types ct ON p.cut_type_id = ct.id
    JOIN units u ON p.selling_unit_id = u.id
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
    WHERE 1=1";
$params = [$branch_id];

if (!empty($cat_filter)) {
    $query .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}
if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.category_id ASC, p.product_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories for filter
$categories = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();
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
                    <i class="fa-solid fa-meat text-danger me-2"></i> Meat Products & Cut Variants
                </h4>
                <p class="text-muted small mb-0">Manage beef, mutton, chicken cuts, purchase rates, and retail/wholesale pricing.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/products/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add New Product
                </a>
                <button class="btn btn-outline-secondary rounded-pill px-3 bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddCategoryModal">
                    <i class="fa-solid fa-layer-group me-1 text-danger"></i> + Category
                </button>
                <button class="btn btn-outline-secondary rounded-pill px-3 bg-white shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddCutModal">
                    <i class="fa-solid fa-knife me-1 text-danger"></i> + Cut Type
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card card-custom p-3 mb-4">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="q" class="form-control border-start-0" placeholder="Search by name, item code, or barcode..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <select name="cat" class="form-select" onchange="this.form.submit()">
                        <option value="">All Meat Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $cat_filter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100">Filter</button>
                    <?php if (!empty($search) || !empty($cat_filter)): ?>
                        <a href="/MeatShop/products/index.php" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Product Name</th>
                            <th class="d-none d-sm-table-cell">Code</th>
                            <th class="d-none d-md-table-cell">Category</th>
                            <th class="d-none d-lg-table-cell">Cut Type</th>
                            <th class="text-end d-none d-md-table-cell">Cost Price</th>
                            <th class="text-end">Retail Rate</th>
                            <th class="text-end d-none d-lg-table-cell">Wholesale</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-center d-none d-sm-table-cell">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fs-1 d-block mb-2 text-black-50"></i>
                                    No products found matching criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded bg-danger-subtle text-danger" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;">
                                            <i class="fa-solid fa-drumstick-bite"></i>
                                        </div>
                                        <div>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($p['product_name']) ?></strong>
                                            <small class="text-muted d-sm-none"><?= htmlspecialchars($p['product_code']) ?> &bull; </small>
                                            <small class="text-muted"><?= htmlspecialchars($p['storage_type']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['product_code']) ?></span>
                                    <?php if ($p['barcode']): ?>
                                        <small class="text-muted d-block font-monospace"><?= htmlspecialchars($p['barcode']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell"><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($p['category_name']) ?></span></td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($p['cut_name'] ?: 'Standard') ?></td>
                                <td class="text-end text-muted d-none d-md-table-cell"><?= format_currency($p['current_cost']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($p['retail_selling_rate']) ?> <small class="text-muted">/<?= $p['unit_code'] ?></small></td>
                                <td class="text-end fw-semibold text-dark d-none d-lg-table-cell"><?= format_currency($p['wholesale_selling_rate']) ?></td>
                                <td class="text-end">
                                    <span class="fw-bold <?= $p['stock_qty'] <= $p['minimum_stock_level'] ? 'text-danger' : 'text-success' ?>">
                                        <?= format_weight($p['stock_qty'], $p['unit_code']) ?>
                                    </span>
                                </td>
                                <td class="text-center d-none d-sm-table-cell">
                                    <span class="badge <?= $p['status'] === 'active' ? 'badge-subtle-success' : 'badge-subtle-danger' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="/MeatShop/products/edit.php?id=<?= $p['id'] ?>" class="btn btn-light border" title="Edit Product">
                                            <i class="fa-solid fa-pen text-primary"></i>
                                        </a>
                                        <button type="button" class="btn btn-light border text-danger" onclick="confirmDeleteProduct(<?= $p['id'] ?>)" title="Delete Product">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
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

<script>
function confirmDeleteProduct(id) {
    Swal.fire({
        title: 'Delete Meat Product?',
        text: 'This will archive the product from active sales catalog.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, Delete'
    }).then((res) => {
        if (res.isConfirmed) {
            window.location.href = '/MeatShop/products/delete.php?id=' + id;
        }
    });
}
</script>
