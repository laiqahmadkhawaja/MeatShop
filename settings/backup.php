<?php
/**
 * Database Backup & Restore Utility
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('settings_manage');

// Handle download backup request
if (isset($_GET['download']) && $_GET['download'] === '1') {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $backup_sql = "-- MEAT SHOP MANAGEMENT SYSTEM DATABASE BACKUP\n";
    $backup_sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup_sql .= "-- Server OS: Windows / WAMP\n";
    $backup_sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        $create_stmt = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
        $backup_sql .= "DROP TABLE IF EXISTS `$t`;\n";
        $backup_sql .= $create_stmt['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $cols = array_keys($rows[0]);
            $col_list = implode('`, `', $cols);
            $backup_sql .= "INSERT INTO `$t` (`$col_list`) VALUES \n";

            $row_lines = [];
            foreach ($rows as $r) {
                $escaped_vals = array_map(function($val) use ($pdo) {
                    if ($val === null) return "NULL";
                    return $pdo->quote($val);
                }, array_values($r));
                $row_lines[] = "(" . implode(', ', $escaped_vals) . ")";
            }
            $backup_sql .= implode(",\n", $row_lines) . ";\n\n";
        }
    }

    $backup_sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $filename = "meatshop_backup_" . date('Y-m-d_His') . ".sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup_sql));
    echo $backup_sql;
    exit;
}

$page_title = 'Database Backup';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-database text-danger me-2"></i> Database Backup & Data Protection
                </h4>
                <p class="text-muted small mb-0">Export complete transactional ledgers, inventory movements, and system configuration.</p>
            </div>
            <a href="/MeatShop/settings/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Settings
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card card-custom p-4 text-center">
                    <div class="p-3 bg-danger-subtle text-danger rounded-circle mx-auto mb-3" style="width: 70px; height: 70px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa-solid fa-file-export fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark">Export Full Database Backup</h5>
                    <p class="text-muted small">Download a complete, self-contained SQL dump of all products, stock batches, sales invoices, supplier khata, customer ledgers, and cashier shift records.</p>
                    
                    <div class="p-3 bg-light rounded-3 mb-4 text-start font-monospace small">
                        <div>Database Name: <strong>meatshop_db</strong></div>
                        <div>Date & Time: <strong><?= date('d-M-Y H:i:s') ?></strong></div>
                        <div>Format: <strong>Standard MySQL SQL Dump</strong></div>
                    </div>

                    <a href="?download=1" class="btn btn-meat py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-download me-2"></i> Download SQL Backup Now
                    </a>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
