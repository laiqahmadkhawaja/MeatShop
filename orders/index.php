<?php
/**
 * Restaurant & Advance Orders
 * Meat Shop Management System
 */

$page_title = 'Restaurant Supplies & Order Bookings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT o.*, c.customer_name, c.phone as customer_phone, u.full_name as created_by_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN users u ON o.created_by = u.id
    WHERE o.branch_id = ?
    ORDER BY o.delivery_datetime ASC, o.id DESC");
$stmt->execute([$branch_id]);
$orders = $stmt->fetchAll();
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
                    <i class="fa-solid fa-utensils text-danger me-2"></i> Restaurant Supplies & Advance Bookings
                </h4>
                <p class="text-muted small mb-0">Manage scheduled restaurant deliveries, caterer bookings, custom cuts, and home delivery orders.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/orders/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> New Order Booking
                </a>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Order</th>
                            <th>Customer / Restaurant</th>
                            <th class="d-none d-md-table-cell">Type</th>
                            <th>Scheduled Delivery</th>
                            <th class="d-none d-lg-table-cell">Address / Rider</th>
                            <th class="text-end">Total</th>
                            <th class="text-end d-none d-md-table-cell">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-utensils fs-1 d-block mb-2 text-black-50"></i>
                                    No advance bookings or restaurant supply orders.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="fw-bold text-danger">
                                    <?= htmlspecialchars($o['order_no']) ?>
                                    <div class="d-md-none small">
                                        <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size:0.65rem;">
                                            <?= str_replace('_', ' ', $o['order_type']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= htmlspecialchars($o['customer_name']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars($o['customer_phone']) ?></small>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size:0.7rem;">
                                        <?= str_replace('_', ' ', $o['order_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= date('d-M', strtotime($o['delivery_datetime'])) ?></strong>
                                    <small class="text-muted d-block"><?= date('h:i A', strtotime($o['delivery_datetime'])) ?></small>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <small class="text-muted d-block"><?= htmlspecialchars($o['delivery_address'] ?: 'Shop Pickup') ?></small>
                                    <?php if ($o['rider_name']): ?>
                                        <small class="text-primary"><i class="fa-solid fa-motorcycle me-1"></i><?= htmlspecialchars($o['rider_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_currency($o['grand_total']) ?></td>
                                <td class="text-end text-success fw-semibold d-none d-md-table-cell"><?= format_currency($o['advance_paid']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($o['balance_amount']) ?></td>
                                <td class="text-center">
                                    <?php 
                                    $st_class = [
                                        'pending' => 'bg-warning text-dark',
                                        'preparing' => 'bg-info text-white',
                                        'ready' => 'bg-primary text-white',
                                        'delivered' => 'bg-success text-white',
                                        'cancelled' => 'bg-danger text-white'
                                    ][$o['status']] ?? 'bg-secondary text-white';
                                    ?>
                                    <span class="badge <?= $st_class ?> text-uppercase"><?= $o['status'] ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="/MeatShop/orders/update_status.php?id=<?= $o['id'] ?>&status=delivered" class="btn btn-sm btn-outline-success p-1 px-2" title="Mark as Delivered">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
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
