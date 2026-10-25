<?php
/**
 * Global Helper Functions & Reusable Logic
 * Meat Shop Management System
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

// Fetch single setting value
function get_setting($key, $default = '') {
    global $pdo;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return $settings_cache[$key] ?? $default;
}

// Update single setting
function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

// Format currency
function format_currency($amount) {
    $symbol = get_setting('currency_symbol', 'Rs.');
    $decimals = (int)get_setting('decimal_precision', 2);
    return $symbol . ' ' . number_format((float)$amount, $decimals);
}

// Format weight
function format_weight($weight, $unit_code = 'KG') {
    $decimals = (int)get_setting('weight_precision', 3);
    return number_format((float)$weight, $decimals) . ' ' . $unit_code;
}

// Standard JSON Response helper for AJAX
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Audit Trail Logger
function log_audit($action, $module, $record_id = null, $details = null) {
    global $pdo;
    try {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $action,
            $module,
            $record_id,
            is_array($details) ? json_encode($details) : (string)$details,
            $ip
        ]);
    } catch (Exception $e) {
        // Silently continue so audit logging never crashes core transactions
    }
}

// Stock Movement & Branch Stock Updater
function update_product_stock($pdo, $branch_id, $product_id, $qty_change, $unit_cost, $movement_type, $ref_id = null, $ref_table = null, $notes = '') {
    // 1. Fetch current stock
    $stmt = $pdo->prepare("SELECT current_stock, avg_cost_price FROM branch_stock WHERE branch_id = ? AND product_id = ? FOR UPDATE");
    $stmt->execute([$branch_id, $product_id]);
    $current = $stmt->fetch();

    $stock_before = $current ? (float)$current['current_stock'] : 0.000;
    $stock_after = $stock_before + (float)$qty_change;

    // 2. Insert or update branch_stock
    if ($current) {
        // Recalculate moving average cost if positive inventory addition
        $new_avg_cost = (float)$current['avg_cost_price'];
        if ($qty_change > 0 && $stock_after > 0 && $unit_cost > 0) {
            $total_val_before = $stock_before * $new_avg_cost;
            $incoming_val = (float)$qty_change * (float)$unit_cost;
            $new_avg_cost = ($total_val_before + $incoming_val) / $stock_after;
        }

        $upd = $pdo->prepare("UPDATE branch_stock SET current_stock = ?, avg_cost_price = ? WHERE branch_id = ? AND product_id = ?");
        $upd->execute([$stock_after, $new_avg_cost, $branch_id, $product_id]);
    } else {
        $ins = $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, current_stock, avg_cost_price) VALUES (?, ?, ?, ?)");
        $ins->execute([$branch_id, $product_id, $stock_after, $unit_cost]);
    }

    // 3. Log movement audit
    $mov = $pdo->prepare("INSERT INTO stock_movements (branch_id, product_id, movement_type, quantity, stock_before, stock_after, unit_cost, reference_id, reference_table, notes, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $mov->execute([
        $branch_id,
        $product_id,
        $movement_type,
        $qty_change,
        $stock_before,
        $stock_after,
        $unit_cost,
        $ref_id,
        $ref_table,
        $notes,
        get_current_user_id() ?? 1
    ]);

    return $stock_after;
}

// Customer Khata Ledger Transaction Recorder
function record_customer_transaction($pdo, $customer_id, $branch_id, $transaction_type, $reference_id, $reference_no, $debit, $credit, $payment_method_id = null, $notes = '') {
    // 1. Fetch current customer balance
    $stmt = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
    $stmt->execute([$customer_id]);
    $cust = $stmt->fetch();
    $current_balance = $cust ? (float)$cust['current_balance'] : 0.00;

    // Balance calculation:
    // Debit increases balance (receivable from customer)
    // Credit decreases balance (payment received from customer)
    $new_balance = $current_balance + (float)$debit - (float)$credit;

    // 2. Update customer record
    $upd = $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?");
    $upd->execute([$new_balance, $customer_id]);

    // 3. Record in customer_transactions table
    $ins = $pdo->prepare("INSERT INTO customer_transactions (customer_id, branch_id, transaction_type, reference_id, reference_no, transaction_date, debit, credit, balance_after, payment_method_id, notes, created_by)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        $customer_id,
        $branch_id,
        $transaction_type,
        $reference_id,
        $reference_no,
        $debit,
        $credit,
        $new_balance,
        $payment_method_id,
        $notes,
        get_current_user_id() ?? 1
    ]);

    return $new_balance;
}

// Supplier Ledger Transaction Recorder
function record_supplier_transaction($pdo, $supplier_id, $branch_id, $transaction_type, $reference_id, $reference_no, $debit, $credit, $payment_method_id = null, $notes = '') {
    // 1. Fetch current supplier balance
    $stmt = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
    $stmt->execute([$supplier_id]);
    $supp = $stmt->fetch();
    $current_balance = $supp ? (float)$supp['current_balance'] : 0.00;

    // Supplier balance logic:
    // Credit increases payable (Purchase invoice)
    // Debit decreases payable (Payment paid to supplier)
    $new_balance = $current_balance + (float)$credit - (float)$debit;

    // 2. Update supplier record
    $upd = $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?");
    $upd->execute([$new_balance, $supplier_id]);

    // 3. Record in supplier_transactions table
    $ins = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, branch_id, transaction_type, reference_id, reference_no, transaction_date, debit, credit, balance_after, payment_method_id, notes, created_by)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        $supplier_id,
        $branch_id,
        $transaction_type,
        $reference_id,
        $reference_no,
        $debit,
        $credit,
        $new_balance,
        $payment_method_id,
        $notes,
        get_current_user_id() ?? 1
    ]);

    return $new_balance;
}

// Bank Account Balance Updater
function update_bank_balance($pdo, $bank_account_id, $amount_change) {
    if (!$bank_account_id) return;
    $stmt = $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?");
    $stmt->execute([(float)$amount_change, $bank_account_id]);
}

// Get active cashier shift for current user
function get_active_shift($pdo, $branch_id, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM cashier_shifts WHERE branch_id = ? AND user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$branch_id, $user_id]);
    return $stmt->fetch();
}

// Flash Message Helpers
function set_flash_message($type, $msg) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'msg' => $msg
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}
