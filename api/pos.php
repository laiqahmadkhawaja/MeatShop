<?php
/**
 * POS Sale API & Transaction Handler
 * Meat Shop Management System
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

if (!is_logged_in()) {
    json_response(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';

if ($action === 'create_sale') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['items'])) {
        json_response(['status' => 'error', 'message' => 'Cart is empty or invalid data provided'], 422);
    }

    $branch_id = get_current_branch_id();
    $cashier_id = get_current_user_id();
    $customer_id = (int)($data['customer_id'] ?? 1);
    $discount_value = (float)($data['discount_value'] ?? 0.0);
    $discount_type = ($data['discount_type'] === 'percent') ? 'percent' : 'fixed';
    $payment_method_id = (int)($data['payment_method_id'] ?? 1);
    $amount_paid = (float)($data['amount_paid'] ?? 0.0);
    $items = $data['items'];

    // Get active shift if any
    $shift = get_active_shift($pdo, $branch_id, $cashier_id);
    $shift_id = $shift ? (int)$shift['id'] : null;

    try {
        $pdo->beginTransaction();

        // 1. Generate Invoice Number (e.g. INV-20261025-0001)
        $date_prefix = date('Ymd');
        $seq_stmt = $pdo->prepare("SELECT COUNT(id) FROM sales WHERE invoice_no LIKE ?");
        $seq_stmt->execute(["INV-$date_prefix-%"]);
        $seq = $seq_stmt->fetchColumn() + 1;
        $invoice_no = "INV-$date_prefix-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // 2. Calculate Subtotal, Weights & COGS
        $subtotal = 0.00;
        $total_weight = 0.000;
        $total_cogs = 0.00;
        $processed_items = [];

        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $unit_id = (int)($item['unit_id'] ?? 1);
            $weight_qty = (float)$item['weight'];
            $tare = (float)($item['tare'] ?? 0.000);
            $net_weight = max(0.001, $weight_qty - $tare);
            $rate = (float)$item['rate'];
            $line_total = $net_weight * $rate;

            // Fetch product & current moving average cost
            $cost_stmt = $pdo->prepare("SELECT p.product_name, COALESCE(bs.avg_cost_price, p.default_purchase_rate) as unit_cost 
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ? 
                WHERE p.id = ?");
            $cost_stmt->execute([$branch_id, $product_id]);
            $prod_info = $cost_stmt->fetch();
            $unit_cost = $prod_info ? (float)$prod_info['unit_cost'] : 0.00;
            $line_cogs = $net_weight * $unit_cost;

            $subtotal += $line_total;
            $total_weight += $net_weight;
            $total_cogs += $line_cogs;

            $processed_items[] = [
                'product_id' => $product_id,
                'unit_id' => $unit_id,
                'weight_qty' => $net_weight,
                'tare' => $tare,
                'rate' => $rate,
                'line_total' => $line_total,
                'unit_cost' => $unit_cost,
                'line_cogs' => $line_cogs
            ];
        }

        // 3. Discount & Grand Total Calculation
        $discount_amount = 0.00;
        if ($discount_type === 'percent') {
            $discount_amount = ($subtotal * $discount_value) / 100.0;
        } else {
            $discount_amount = $discount_value;
        }
        $grand_total = max(0.00, $subtotal - $discount_amount);

        // Payment status & balance
        $balance_amount = max(0.00, $grand_total - $amount_paid);

        // Fetch customer details to validate credit policy
        $cust_stmt = $pdo->prepare("SELECT id, customer_name, customer_type, credit_limit, current_balance FROM customers WHERE id = ?");
        $cust_stmt->execute([$customer_id]);
        $customer = $cust_stmt->fetch();
        if (!$customer) {
            json_response(['status' => 'error', 'message' => 'Invalid customer selected.'], 422);
        }

        // Fetch payment method type
        $pm_stmt_type = $pdo->prepare("SELECT method_type FROM payment_methods WHERE id = ?");
        $pm_stmt_type->execute([$payment_method_id]);
        $pm_method_type = $pm_stmt_type->fetchColumn();

        // Enforce rule: Walk-in customers cannot buy on credit or leave an unpaid balance
        if ($customer['customer_type'] === 'walk_in') {
            if ($balance_amount > 0.01 || $pm_method_type === 'credit') {
                json_response([
                    'status' => 'error',
                    'message' => 'Walk-in customers cannot save sales as credit. Full payment of Rs. ' . number_format($grand_total, 2) . ' is required. Please collect full payment or select/register a customer account for credit (Khata) sales.'
                ], 422);
            }
        }

        $payment_status = 'paid';
        if ($balance_amount > 0 && $amount_paid > 0) {
            $payment_status = 'partial';
        } elseif ($balance_amount > 0 && $amount_paid == 0) {
            $payment_status = 'credit';
        }

        // 4. Insert into `sales`
        $sale_stmt = $pdo->prepare("INSERT INTO sales (branch_id, cashier_id, shift_id, customer_id, invoice_no, sale_date, total_weight, subtotal, discount_type, discount_value, discount_amount, grand_total, cogs_total, paid_amount, balance_amount, payment_status, payment_method_id, status) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
        $sale_stmt->execute([
            $branch_id,
            $cashier_id,
            $shift_id,
            $customer_id,
            $invoice_no,
            $total_weight,
            $subtotal,
            $discount_type,
            $discount_value,
            $discount_amount,
            $grand_total,
            $total_cogs,
            $amount_paid,
            $balance_amount,
            $payment_status,
            $payment_method_id
        ]);
        $sale_id = $pdo->lastInsertId();

        // 5. Insert `sale_items` & deduct inventory
        $item_stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, unit_id, weight_qty, rate_per_unit, tare_weight, line_total, unit_cost, cogs_amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($processed_items as $pi) {
            $item_stmt->execute([
                $sale_id,
                $pi['product_id'],
                $pi['unit_id'],
                $pi['weight_qty'],
                $pi['rate'],
                $pi['tare'],
                $pi['line_total'],
                $pi['unit_cost'],
                $pi['line_cogs']
            ]);

            // Deduct stock (negative change)
            update_product_stock($pdo, $branch_id, $pi['product_id'], -$pi['weight_qty'], $pi['unit_cost'], 'sale', $sale_id, 'sales', "Sale Invoice #$invoice_no");
        }

        // 6. Record payment in `sale_payments`
        if ($amount_paid > 0) {
            $pm_stmt = $pdo->prepare("INSERT INTO sale_payments (sale_id, payment_method_id, amount) VALUES (?, ?, ?)");
            $pm_stmt->execute([$sale_id, $payment_method_id, $amount_paid]);

            // If cash and active shift, update shift cash
            if ($shift_id) {
                // Check if payment method is cash
                $pm_check = $pdo->prepare("SELECT method_type FROM payment_methods WHERE id = ?");
                $pm_check->execute([$payment_method_id]);
                if ($pm_check->fetchColumn() === 'cash') {
                    $upd_shift = $pdo->prepare("UPDATE cashier_shifts SET 
                        cash_sales = cash_sales + ?, 
                        expected_closing_cash = expected_closing_cash + ? 
                        WHERE id = ?");
                    $upd_shift->execute([$amount_paid, $amount_paid, $shift_id]);
                }
            }
        }

        // 7. Update Customer Ledger
        // Debit: Full Grand Total (Sale invoice)
        // Credit: Amount Paid immediately
        record_customer_transaction($pdo, $customer_id, $branch_id, 'sale_invoice', $sale_id, $invoice_no, $grand_total, $amount_paid, $payment_method_id, "Sale Invoice #$invoice_no");

        // 8. Log Audit
        log_audit('create_sale', 'sales', $sale_id, ['invoice_no' => $invoice_no, 'grand_total' => $grand_total, 'paid' => $amount_paid]);

        $pdo->commit();

        json_response([
            'status' => 'success',
            'sale_id' => $sale_id,
            'invoice_no' => $invoice_no,
            'message' => 'Sale completed successfully'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

json_response(['status' => 'error', 'message' => 'Invalid action'], 400);
