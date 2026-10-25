<?php
/**
 * Multi-Branch Management
 * Meat Shop Management System
 */

$page_title = 'Multi-Branch Stores';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

require_permission('settings_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_branch'])) {
    $branch_name = trim($_POST['branch_name'] ?? '');
    $branch_code = trim($_POST['branch_code'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($branch_name) && !empty($branch_code)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO branches (branch_name, branch_code, phone, address, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$branch_name, $branch_code, $phone, $address]);
            $new_bid = $pdo->lastInsertId();

            // Auto-initialize zero inventory for all products in new branch
            $prods = $pdo->query("SELECT id, default_purchase_rate FROM products")->fetchAll();
            $bs_stmt = $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, current_stock, avg_cost_price) VALUES (?, ?, 0, ?)");
            foreach ($prods as $p) {
                $bs_stmt->execute([$new_bid, $p['id'], $p['default_purchase_rate']]);
            }

            log_audit('create_branch', 'branches', $new_bid, ['name' => $branch_name, 'code' => $branch_code]);
            set_flash_message('success', 'Branch "' . htmlspecialchars($branch_name) . '" created successfully.');
            header("Location: /MeatShop/settings/branches.php");
            exit;
        } catch (Exception $e) {
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

$branches = $pdo->query("SELECT b.*, 
    COUNT(DISTINCT u.id) as user_count,
    COALESCE(SUM(bs.current_stock), 0) as total_stock_kg
    FROM branches b
    LEFT JOIN users u ON b.id = u.branch_id
    LEFT JOIN branch_stock bs ON b.id = bs.branch_id
    GROUP BY b.id
    ORDER BY b.is_main DESC, b.branch_name ASC")->fetchAll();
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
                    <i class="fa-solid fa-store text-danger me-2"></i> Multi-Branch Stores Management
                </h4>
                <p class="text-muted small mb-0">Manage retail butcher shops, centralized stock distribution, and branch performance.</p>
            </div>
            <button class="btn btn-meat rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#newBranchModal">
                <i class="fa-solid fa-plus me-1"></i> Add New Branch
            </button>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Branch Name</th>
                            <th>Code</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th class="text-end">Assigned Users</th>
                            <th class="text-end">Current Stock (KG)</th>
                            <th class="text-center">Branch Type</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $b): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($b['branch_name']) ?></strong>
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($b['branch_code']) ?></span></td>
                            <td><?= htmlspecialchars($b['phone'] ?: 'N/A') ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($b['address'] ?: 'N/A') ?></small></td>
                            <td class="text-end fw-semibold"><?= (int)$b['user_count'] ?> Users</td>
                            <td class="text-end fw-bold text-success"><?= format_weight($b['total_stock_kg']) ?></td>
                            <td class="text-center">
                                <?= $b['is_main'] ? '<span class="badge bg-danger">Main Head Office</span>' : '<span class="badge bg-secondary-subtle text-secondary">Retail Branch</span>' ?>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-subtle-success">Active</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal: New Branch -->
    <div class="modal fade" id="newBranchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-store me-1"></i> Add New Branch Store</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="create_branch" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" name="branch_name" class="form-control" placeholder="e.g. DHA Phase 5 Branch" required autofocus>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Branch Code <span class="text-danger">*</span></label>
                                <input type="text" name="branch_code" class="form-control font-monospace" placeholder="e.g. BR-02" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="03001234567">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Market / Plaza location">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Create Branch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
