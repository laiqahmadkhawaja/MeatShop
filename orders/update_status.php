<?php
/**
 * Update Order Delivery Status
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$order_id = (int)($_GET['id'] ?? 0);
$status = trim($_GET['status'] ?? 'delivered');

if ($order_id > 0) {
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    log_audit('update_order_status', 'orders', $order_id, ['status' => $status]);
    set_flash_message('success', 'Order status updated to ' . ucfirst($status) . '.');
}

header("Location: /MeatShop/orders/index.php");
exit;
