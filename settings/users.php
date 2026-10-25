<?php
/**
 * User Accounts & Roles
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('users_manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '123456';
    $role_id = (int)($_POST['role_id'] ?? 3);
    $branch_id = (int)($_POST['branch_id'] ?? 1);

    if (!empty($username) && !empty($full_name) && !empty($password)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (branch_id, role_id, username, full_name, email, phone, password_hash, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$branch_id, $role_id, $username, $full_name, $email, $phone, $hash]);
            $uid = $pdo->lastInsertId();

            log_audit('create_user', 'users', $uid, ['username' => $username, 'role_id' => $role_id]);
            set_flash_message('success', 'User "' . htmlspecialchars($full_name) . '" created successfully.');
            header("Location: /MeatShop/settings/users.php");
            exit;
        } catch (Exception $e) {
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

$users = $pdo->query("SELECT u.*, r.display_name as role_title, b.branch_name
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.id ASC")->fetchAll();

$roles = $pdo->query("SELECT id, display_name FROM roles ORDER BY id ASC")->fetchAll();
$branches = $pdo->query("SELECT id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name ASC")->fetchAll();

$page_title = 'User Accounts & Roles';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
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
                    <i class="fa-solid fa-user-shield text-danger me-2"></i> Users & Security Management
                </h4>
                <p class="text-muted small mb-0">System roles (Admin, Shop Manager, Cashier, Inventory Manager, Accountant) and user permissions.</p>
            </div>
            <button class="btn btn-meat rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#newUserModal">
                <i class="fa-solid fa-user-plus me-1"></i> Add New User
            </button>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>User Name / Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Branch Assigned</th>
                            <th>Phone</th>
                            <th>Last Login</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= htmlspecialchars($u['full_name']) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($u['email'] ?: 'No email') ?></small>
                            </td>
                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($u['username']) ?></span></td>
                            <td><span class="badge bg-danger-subtle text-danger fw-bold"><?= htmlspecialchars($u['role_title']) ?></span></td>
                            <td><?= htmlspecialchars($u['branch_name'] ?: 'All Branches') ?></td>
                            <td><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></td>
                            <td><small class="text-muted"><?= $u['last_login'] ? date('d-M-Y h:i A', strtotime($u['last_login'])) : 'Never' ?></small></td>
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

    <!-- Modal: New User -->
    <div class="modal fade" id="newUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Add System User</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="create_user" value="1">
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="e.g. Asif Ali" required autofocus>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control font-monospace" placeholder="e.g. asif_cashier" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">System Role <span class="text-danger">*</span></label>
                                <select name="role_id" class="form-select" required>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Assigned Branch <span class="text-danger">*</span></label>
                                <select name="branch_id" class="form-select" required>
                                    <?php foreach ($branches as $br): ?>
                                        <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="03001234567">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Login Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
