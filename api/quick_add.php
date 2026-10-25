<?php
/**
 * Realtime Dynamic Entity Creation API
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    json_response(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$entity = $_GET['entity'] ?? '';

try {
    switch ($entity) {
        case 'category':
            $name = trim($_POST['category_name'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-drumstick-bite');
            if (empty($name)) {
                json_response(['status' => 'error', 'message' => 'Category name is required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO categories (category_name, icon) VALUES (?, ?)");
            $stmt->execute([$name, $icon]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'categories', $id, ['name' => $name]);
            json_response(['status' => 'success', 'message' => 'Category added successfully', 'data' => ['id' => $id, 'text' => $name]]);

        case 'cut_type':
            $name = trim($_POST['cut_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (empty($name)) {
                json_response(['status' => 'error', 'message' => 'Cut type name is required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO cut_types (cut_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'cut_types', $id, ['name' => $name]);
            json_response(['status' => 'success', 'message' => 'Cut type added successfully', 'data' => ['id' => $id, 'text' => $name]]);

        case 'unit':
            $name = trim($_POST['unit_name'] ?? '');
            $code = strtoupper(trim($_POST['unit_code'] ?? ''));
            $factor = (float)($_POST['conversion_factor'] ?? 1.0);
            if (empty($name) || empty($code)) {
                json_response(['status' => 'error', 'message' => 'Unit name and code are required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO units (unit_name, unit_code, conversion_factor) VALUES (?, ?, ?)");
            $stmt->execute([$name, $code, $factor]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'units', $id, ['name' => $name, 'code' => $code]);
            json_response(['status' => 'success', 'message' => 'Unit added successfully', 'data' => ['id' => $id, 'text' => "$name ($code)"]]);

        case 'payment_method':
            $name = trim($_POST['method_name'] ?? '');
            $type = trim($_POST['method_type'] ?? 'cash');
            if (empty($name)) {
                json_response(['status' => 'error', 'message' => 'Method name is required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO payment_methods (method_name, method_type) VALUES (?, ?)");
            $stmt->execute([$name, $type]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'payment_methods', $id, ['name' => $name]);
            json_response(['status' => 'success', 'message' => 'Payment method added successfully', 'data' => ['id' => $id, 'text' => $name]]);

        case 'bank_account':
            $bank_name = trim($_POST['bank_name'] ?? '');
            $title = trim($_POST['account_title'] ?? '');
            $number = trim($_POST['account_number'] ?? '');
            $b_code = trim($_POST['branch_code'] ?? '');
            $balance = (float)($_POST['current_balance'] ?? 0.0);
            if (empty($bank_name) || empty($title) || empty($number)) {
                json_response(['status' => 'error', 'message' => 'Bank name, title and account number are required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_title, account_number, branch_code, current_balance) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$bank_name, $title, $number, $b_code, $balance]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'bank_accounts', $id, ['title' => $title, 'bank' => $bank_name]);
            json_response(['status' => 'success', 'message' => 'Bank account added successfully', 'data' => ['id' => $id, 'text' => "$bank_name - $title ($number)"]]);

        case 'expense_category':
            $name = trim($_POST['category_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (empty($name)) {
                json_response(['status' => 'error', 'message' => 'Expense category name is required'], 422);
            }
            $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $id = $pdo->lastInsertId();
            log_audit('create', 'expense_categories', $id, ['name' => $name]);
            json_response(['status' => 'success', 'message' => 'Expense category added successfully', 'data' => ['id' => $id, 'text' => $name]]);

        case 'customer':
            $name = trim($_POST['customer_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $type = trim($_POST['customer_type'] ?? 'regular');
            $credit_limit = (float)($_POST['credit_limit'] ?? 0.0);
            $opening_balance = (float)($_POST['opening_balance'] ?? 0.0);
            $address = trim($_POST['address'] ?? '');

            if (empty($name) || empty($phone)) {
                json_response(['status' => 'error', 'message' => 'Customer name and phone are required'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, customer_type, credit_limit, opening_balance, current_balance, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $type, $credit_limit, $opening_balance, $opening_balance, $address]);
            $id = $pdo->lastInsertId();

            if ($opening_balance > 0) {
                record_customer_transaction($pdo, $id, get_current_branch_id(), 'opening_balance', $id, 'OPB-' . $id, $opening_balance, 0, null, 'Opening Balance Setup');
            }

            log_audit('create', 'customers', $id, ['name' => $name, 'phone' => $phone]);
            json_response(['status' => 'success', 'message' => 'Customer added successfully', 'data' => ['id' => $id, 'text' => "$name ($phone)"]]);

        case 'supplier':
            $name = trim($_POST['supplier_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $type = trim($_POST['supplier_type'] ?? 'wholesaler');
            $opening_balance = (float)($_POST['opening_balance'] ?? 0.0);
            $address = trim($_POST['address'] ?? '');

            if (empty($name) || empty($phone)) {
                json_response(['status' => 'error', 'message' => 'Supplier name and phone are required'], 422);
            }

            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, supplier_type, opening_balance, current_balance, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $type, $opening_balance, $opening_balance, $address]);
            $id = $pdo->lastInsertId();

            if ($opening_balance > 0) {
                record_supplier_transaction($pdo, $id, get_current_branch_id(), 'opening_balance', $id, 'OPB-' . $id, 0, $opening_balance, null, 'Opening Balance Setup');
            }

            log_audit('create', 'suppliers', $id, ['name' => $name, 'phone' => $phone]);
            json_response(['status' => 'success', 'message' => 'Supplier added successfully', 'data' => ['id' => $id, 'text' => "$name ($phone)"]]);

        default:
            json_response(['status' => 'error', 'message' => 'Invalid entity type'], 400);
    }
} catch (Exception $e) {
    json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}
