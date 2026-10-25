<?php
/**
 * Login Screen
 * Meat Shop Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

if (is_logged_in()) {
    header("Location: /MeatShop/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['branch_id'] = $user['branch_id'] ?? 1;

            // Fetch user permissions
            $perm_stmt = $pdo->prepare("SELECT p.perm_key FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id = ?");
            $perm_stmt->execute([$user['role_id']]);
            $_SESSION['permissions'] = $perm_stmt->fetchAll(PDO::FETCH_COLUMN);

            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            log_audit('login', 'users', $user['id'], 'User logged in successfully');

            header("Location: /MeatShop/index.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars(get_setting('shop_name', 'Meat Shop System')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1e1b18 0%, #3f0d0c 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            max-width: 440px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #991b1b, #dc2626);
            color: white;
            padding: 2.5rem 2rem 2rem;
            text-align: center;
        }
        .brand-badge {
            width: 65px;
            height: 65px;
            background: white;
            color: #dc2626;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            box-shadow: 0 8px 18px rgba(0,0,0,0.2);
        }
        .btn-meat {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            border: none;
            padding: 0.85rem;
            font-weight: 700;
            border-radius: 12px;
            transition: all 0.25s ease;
        }
        .btn-meat:hover {
            background: linear-gradient(135deg, #b91c1c, #7f1d1d);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(220, 38, 38, 0.35);
            color: white;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
        }
        .form-control:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }
        .demo-credential-pill {
            cursor: pointer;
            transition: all 0.2s;
        }
        .demo-credential-pill:hover {
            background: #e2e8f0 !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="brand-badge">
            <i class="fa-solid fa-drumstick-bite"></i>
        </div>
        <h4 class="fw-bold mb-1"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></h4>
        <p class="mb-0 text-white-50 small"><?= htmlspecialchars(get_setting('tagline', 'Fresh Halal Beef, Mutton, Chicken & Meat Cuts')) ?></p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                    <input type="text" id="usernameInput" name="username" class="form-control border-start-0" placeholder="Enter username" value="admin" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" id="passwordInput" name="password" class="form-control border-start-0" placeholder="Enter password" value="admin123" required>
                </div>
            </div>

            <button type="submit" class="btn btn-meat w-100 mb-4">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to POS System
            </button>
        </form>

        <div class="border-top pt-3">
            <small class="text-muted d-block fw-bold mb-2 text-center">Quick Demo Accounts (Click to fill):</small>
            <div class="d-flex gap-2">
                <div class="demo-credential-pill flex-fill text-center bg-light p-2 rounded border" onclick="fillCreds('admin', 'admin123')">
                    <span class="d-block fw-bold text-dark small"><i class="fa-solid fa-user-shield text-danger me-1"></i> Admin</span>
                    <small class="text-muted" style="font-size: 0.7rem;">admin / admin123</small>
                </div>
                <div class="demo-credential-pill flex-fill text-center bg-light p-2 rounded border" onclick="fillCreds('cashier', 'cashier123')">
                    <span class="d-block fw-bold text-dark small"><i class="fa-solid fa-cash-register text-success me-1"></i> Cashier</span>
                    <small class="text-muted" style="font-size: 0.7rem;">cashier / cashier123</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(u, p) {
    document.getElementById('usernameInput').value = u;
    document.getElementById('passwordInput').value = p;
}
</script>
</body>
</html>
