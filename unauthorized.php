<?php
$page_title = 'Access Denied';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<div id="content-wrapper">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>
    <div class="container-fluid p-4">
        <div class="card card-custom text-center p-5 mx-auto" style="max-width: 600px;">
            <div class="text-danger mb-3" style="font-size: 4rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h3 class="fw-bold text-dark">Access Denied</h3>
            <p class="text-muted">You do not have permission to access this module or execute this action. Please contact the store administrator.</p>
            <div class="mt-3">
                <a href="/MeatShop/index.php" class="btn btn-meat px-4"><i class="fa-solid fa-house me-2"></i> Return to Dashboard</a>
            </div>
        </div>
    </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
