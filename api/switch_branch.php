<?php
/**
 * Switch Active Branch
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$branch_id = (int)($_GET['branch_id'] ?? 1);
if ($branch_id > 0) {
    set_current_branch($branch_id);
    set_flash_message('success', 'Switched branch successfully.');
}

$ref = $_SERVER['HTTP_REFERER'] ?? '/MeatShop/index.php';
header("Location: " . $ref);
exit;
