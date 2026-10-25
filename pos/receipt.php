<?php
/**
 * Thermal Receipt & A4 Invoice View
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$sale_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT s.*, c.customer_name, c.phone as customer_phone, c.current_balance, u.full_name as cashier_name, b.branch_name, b.phone as branch_phone, b.address as branch_address, pm.method_name as payment_method
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN users u ON s.cashier_id = u.id
    JOIN branches b ON s.branch_id = b.id
    LEFT JOIN payment_methods pm ON s.payment_method_id = pm.id
    WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Sale invoice not found.");
}

// Fetch Items
$items_stmt = $pdo->prepare("SELECT si.*, p.product_name, p.product_code, u.unit_code
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN units u ON si.unit_id = u.id
    WHERE si.sale_id = ?");
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll();

$shop_name = get_setting('shop_name', 'AL-BARAKAH MEAT & BUTCHER SHOP');
$tagline = get_setting('tagline', 'Fresh Halal Beef, Mutton, Chicken & Meat Cuts');
$phone = get_setting('phone', '+92 300 1234567');
$address = get_setting('address', 'Meat Market, Lahore');
$currency = get_setting('currency_symbol', 'Rs.');
$footer_msg = get_setting('receipt_footer_text', 'Thank you for your purchase! 100% Halal & Fresh Meat.');

// WhatsApp Share Text
$wa_msg = "*$shop_name*\n"
        . "Invoice: {$sale['invoice_no']}\n"
        . "Date: " . date('d-M-Y h:i A', strtotime($sale['sale_date'])) . "\n"
        . "Customer: {$sale['customer_name']}\n"
        . "--------------------------\n";
foreach ($items as $it) {
    $wa_msg .= "{$it['product_name']}: " . number_format($it['weight_qty'], 3) . " {$it['unit_code']} @ Rs " . number_format($it['rate_per_unit'], 0) . " = Rs " . number_format($it['line_total'], 2) . "\n";
}
$wa_msg .= "--------------------------\n"
        . "Grand Total: Rs " . number_format($sale['grand_total'], 2) . "\n"
        . "Paid Amount: Rs " . number_format($sale['paid_amount'], 2) . "\n"
        . "Balance: Rs " . number_format($sale['balance_amount'], 2) . "\n"
        . "Current Khata Balance: Rs " . number_format($sale['current_balance'], 2) . "\n\n"
        . "$footer_msg";
$wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $sale['customer_phone']) . "?text=" . urlencode($wa_msg);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($sale['invoice_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
        }

        .receipt-container {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 6px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .receipt-title {
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 2px;
        }

        .receipt-table {
            width: 100%;
            font-size: 0.8rem;
            border-collapse: collapse;
        }

        .receipt-table th {
            border-bottom: 1px dashed #000;
            padding: 4px 0;
        }

        .receipt-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .totals-section {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 6px 0;
            margin: 6px 0;
            font-size: 0.85rem;
        }

        .receipt-footer {
            text-align: center;
            font-size: 0.75rem;
            margin-top: 10px;
        }

        /* Print Specific Styling */
        @media print {
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 4px;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- Action Buttons (Hidden on Print) -->
<div class="container text-center py-3 no-print">
    <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 me-2">
        <i class="fa-solid fa-print me-1"></i> Print Receipt
    </button>
    <?php if (!empty($sale['customer_phone']) && $sale['customer_phone'] !== '03000000000'): ?>
    <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 me-2">
        <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
    </a>
    <?php endif; ?>
    <a href="/MeatShop/pos/index.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
        <i class="fa-solid fa-plus me-1"></i> New Sale
    </a>
</div>

<!-- Receipt Content -->
<div class="receipt-container">
    <div class="receipt-header">
        <div class="receipt-title"><?= htmlspecialchars($shop_name) ?></div>
        <div style="font-size: 0.72rem;"><?= htmlspecialchars($tagline) ?></div>
        <div style="font-size: 0.72rem;"><?= htmlspecialchars($address) ?></div>
        <div style="font-size: 0.72rem;">Tel: <?= htmlspecialchars($phone) ?></div>
    </div>

    <!-- Invoice Meta Details -->
    <div style="font-size: 0.75rem; margin-bottom: 6px;">
        <div class="d-flex justify-content-between">
            <span><strong>Invoice #:</strong> <?= htmlspecialchars($sale['invoice_no']) ?></span>
            <span><?= date('d/m/Y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span><strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier_name']) ?></span>
            <span><strong>Branch:</strong> <?= htmlspecialchars($sale['branch_name']) ?></span>
        </div>
        <div><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
    </div>

    <!-- Table of Meat Items -->
    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width: 48%;">Item</th>
                <th class="text-end" style="width: 26%;">Weight/Rate</th>
                <th class="text-end" style="width: 26%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($it['product_name']) ?></strong>
                    <?php if ($it['tare_weight'] > 0): ?>
                        <br><small style="font-size:0.65rem;">Tare: <?= number_format($it['tare_weight'], 3) ?> KG</small>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?= number_format($it['weight_qty'], 3) ?> <?= htmlspecialchars($it['unit_code']) ?><br>
                    @ <?= number_format($it['rate_per_unit'], 0) ?>
                </td>
                <td class="text-end fw-bold">
                    <?= number_format($it['line_total'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="d-flex justify-content-between">
            <span>Total Weight:</span>
            <strong><?= number_format($sale['total_weight'], 3) ?> KG</strong>
        </div>
        <div class="d-flex justify-content-between">
            <span>Subtotal:</span>
            <span><?= $currency ?> <?= number_format($sale['subtotal'], 2) ?></span>
        </div>
        <?php if ($sale['discount_amount'] > 0): ?>
        <div class="d-flex justify-content-between">
            <span>Discount:</span>
            <span>- <?= $currency ?> <?= number_format($sale['discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between fw-bold fs-6 mt-1 pt-1 border-top border-dark">
            <span>GRAND TOTAL:</span>
            <span><?= $currency ?> <?= number_format($sale['grand_total'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <span>Paid (<?= htmlspecialchars($sale['payment_method'] ?? 'Cash') ?>):</span>
            <span><?= $currency ?> <?= number_format($sale['paid_amount'], 2) ?></span>
        </div>
        <?php if ($sale['balance_amount'] > 0): ?>
        <div class="d-flex justify-content-between text-danger fw-bold">
            <span>Balance Due:</span>
            <span><?= $currency ?> <?= number_format($sale['balance_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mt-1 pt-1 border-top" style="font-size: 0.75rem;">
            <span>Customer Khata Bal:</span>
            <strong><?= $currency ?> <?= number_format($sale['current_balance'], 2) ?></strong>
        </div>
    </div>

    <!-- Receipt Footer -->
    <div class="receipt-footer">
        <p class="mb-1"><?= htmlspecialchars($footer_msg) ?></p>
        <p class="mb-0 text-muted" style="font-size: 0.65rem;">Software by Antigravity Meat Shop POS</p>
    </div>
</div>

<script>
// Auto print if not already printed
window.addEventListener('load', () => {
    // window.print();
});
</script>

</body>
</html>
