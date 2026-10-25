<?php
/**
 * Archive / Delete Product
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('products_manage');

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$id]);
    log_audit('delete_product', 'products', $id, 'Product archived');
    set_flash_message('success', 'Product has been deactivated.');
}

header("Location: /MeatShop/products/index.php");
exit;
