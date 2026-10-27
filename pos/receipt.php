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

// Thermal roll width handling (58mm -> 48mm printable, 80mm -> 72mm printable)
$selected_width = $_GET['width'] ?? get_setting('receipt_width', '80mm');
if ($selected_width !== '58mm' && $selected_width !== '80mm') {
    $selected_width = '80mm';
}
$printable_width = ($selected_width === '58mm') ? '48mm' : '72mm';
$roll_size = ($selected_width === '58mm') ? '58mm auto' : '80mm auto';

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
    <title>Receipt #<?= htmlspecialchars($sale['invoice_no']) ?> (<?= $selected_width ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Thermal Optimization Rules: Monoline Sans-Serif, Pure #000, Anti-Aliasing */
        html, body {
            background-color: #f1f5f9;
            color: #000 !important;
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
            margin: 0;
            padding: 0;
        }

        /* Enforce Uniform Bold Weight (700) across all receipt text for dense thermal burn */
        .receipt-container,
        .receipt-container * {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            color: #000 !important;
            border-color: #000 !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
            box-sizing: border-box;
        }

        /* Container Layout */
        .receipt-container {
            background: #fff !important;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            border-radius: 4px;
            line-height: 1.25;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* 80mm Roll (Printable: 72mm) */
        .receipt-container.roll-80mm {
            width: 72mm;
            max-width: 72mm;
            padding: 2.5mm 3.5mm;
        }

        /* 58mm Roll (Printable: 48mm) */
        .receipt-container.roll-58mm {
            width: 48mm;
            max-width: 48mm;
            padding: 1.5mm 2mm;
        }

        /* Hierarchy via Font Size Only: 80mm Roll (72mm Printable) */
        .roll-80mm .receipt-title { font-size: 16px; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 2px; text-align: center; }
        .roll-80mm .receipt-sub { font-size: 11px; text-align: center; margin-bottom: 1px; }
        .roll-80mm .receipt-meta { font-size: 11px; margin-bottom: 6px; }
        .roll-80mm .receipt-table { width: 100%; font-size: 11px; border-collapse: collapse; }
        .roll-80mm .receipt-table th { border-bottom: 1px dashed #000; padding: 4px 0; font-size: 11px; text-align: left; }
        .roll-80mm .receipt-table td { padding: 4px 0; vertical-align: top; }
        .roll-80mm .tare-info { font-size: 9.5px; }
        .roll-80mm .totals-section { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 5px 0; margin: 5px 0; font-size: 12px; }
        .roll-80mm .grand-total-row { font-size: 15px; border-top: 1.5px solid #000; padding-top: 3px; margin-top: 3px; }
        .roll-80mm .receipt-footer { text-align: center; font-size: 10px; margin-top: 8px; }
        .roll-80mm .software-credit { font-size: 8.5px; margin-top: 3px; text-align: center; }

        /* Hierarchy via Font Size Only: 58mm Roll (48mm Printable) */
        .roll-58mm .receipt-title { font-size: 13.5px; letter-spacing: 0.2px; text-transform: uppercase; margin-bottom: 2px; text-align: center; }
        .roll-58mm .receipt-sub { font-size: 9.5px; text-align: center; margin-bottom: 1px; }
        .roll-58mm .receipt-meta { font-size: 9.5px; margin-bottom: 4px; }
        .roll-58mm .receipt-table { width: 100%; font-size: 9.5px; border-collapse: collapse; }
        .roll-58mm .receipt-table th { border-bottom: 1px dashed #000; padding: 3px 0; font-size: 9.5px; text-align: left; }
        .roll-58mm .receipt-table td { padding: 3px 0; vertical-align: top; }
        .roll-58mm .tare-info { font-size: 8.5px; }
        .roll-58mm .totals-section { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 4px 0; margin: 4px 0; font-size: 10px; }
        .roll-58mm .grand-total-row { font-size: 13px; border-top: 1.5px solid #000; padding-top: 2px; margin-top: 2px; }
        .roll-58mm .receipt-footer { text-align: center; font-size: 9px; margin-top: 6px; }
        .roll-58mm .software-credit { font-size: 8px; margin-top: 2px; text-align: center; }

        /* Pure Black Dividers */
        .receipt-header {
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        /* Print Specific Styling */
        @media print {
            @page {
                size: <?= $roll_size ?>;
                margin: 0;
            }
            html, body {
                width: <?= $printable_width ?> !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                margin: 0 auto !important;
                padding: 1mm 2mm !important;
                width: <?= $printable_width ?> !important;
                max-width: <?= $printable_width ?> !important;
                border-radius: 0 !important;
            }
        }
    </style>
</head>
<body>

<!-- Action & Roll-Selector Bar (Hidden on Print) -->
<div class="container text-center py-3 no-print">
    <div class="d-inline-flex flex-wrap align-items-center justify-content-center gap-2 mb-2">
        <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Receipt
        </button>

        <!-- Roll Width Switcher -->
        <div class="btn-group btn-group-sm rounded-pill shadow-sm" role="group">
            <a href="?id=<?= $sale_id ?>&width=80mm" class="btn <?= $selected_width === '80mm' ? 'btn-dark fw-bold' : 'btn-outline-dark' ?>">
                <i class="fa-solid fa-receipt me-1"></i> 80mm Roll (72mm Print)
            </a>
            <a href="?id=<?= $sale_id ?>&width=58mm" class="btn <?= $selected_width === '58mm' ? 'btn-dark fw-bold' : 'btn-outline-dark' ?>">
                <i class="fa-solid fa-receipt me-1"></i> 58mm Roll (48mm Print)
            </a>
        </div>

        <?php if (!empty($sale['customer_phone']) && $sale['customer_phone'] !== '03000000000'): ?>
        <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm fw-bold">
            <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
        </a>
        <?php endif; ?>

        <a href="/MeatShop/pos/index.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">
            <i class="fa-solid fa-plus me-1"></i> New Sale
        </a>
    </div>
    <div class="text-muted small">
        Thermal Mode: <strong class="text-dark"><?= $selected_width === '58mm' ? '58mm (48mm printable)' : '80mm (72mm printable)' ?></strong> | Monoline Sans-Serif | Uniform Bold 700 | Pure #000
    </div>
</div>

<!-- Receipt Content Container -->
<div class="receipt-container roll-<?= $selected_width ?>">
    <!-- Shop Header -->
    <div class="receipt-header">
        <div class="receipt-title"><?= htmlspecialchars($shop_name) ?></div>
        <?php if (!empty($tagline)): ?>
            <div class="receipt-sub"><?= htmlspecialchars($tagline) ?></div>
        <?php endif; ?>
        <div class="receipt-sub"><?= htmlspecialchars($address) ?></div>
        <div class="receipt-sub">TEL: <?= htmlspecialchars($phone) ?></div>
    </div>

    <!-- Invoice Meta Details -->
    <div class="receipt-meta">
        <div class="d-flex justify-content-between">
            <span>INV #: <?= htmlspecialchars($sale['invoice_no']) ?></span>
            <span><?= date('d/m/Y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>CASHIER: <?= htmlspecialchars($sale['cashier_name']) ?></span>
            <span>BRANCH: <?= htmlspecialchars($sale['branch_name']) ?></span>
        </div>
        <div>CUSTOMER: <?= htmlspecialchars($sale['customer_name']) ?></div>
    </div>

    <!-- Table of Meat Items -->
    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width: 48%;">ITEM</th>
                <th class="text-end" style="width: 27%;">WT/RATE</th>
                <th class="text-end" style="width: 25%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td>
                    <div><?= htmlspecialchars($it['product_name']) ?></div>
                    <?php if ($it['tare_weight'] > 0): ?>
                        <div class="tare-info">Tare: <?= number_format($it['tare_weight'], 3) ?> KG</div>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <?= number_format($it['weight_qty'], 3) ?> <?= htmlspecialchars($it['unit_code']) ?><br>
                    @ <?= number_format($it['rate_per_unit'], 0) ?>
                </td>
                <td class="text-end">
                    <?= number_format($it['line_total'], 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Section -->
    <div class="totals-section">
        <div class="d-flex justify-content-between">
            <span>TOTAL WEIGHT:</span>
            <span><?= number_format($sale['total_weight'], 3) ?> KG</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>SUBTOTAL:</span>
            <span><?= $currency ?> <?= number_format($sale['subtotal'], 2) ?></span>
        </div>
        <?php if ($sale['discount_amount'] > 0): ?>
        <div class="d-flex justify-content-between">
            <span>DISCOUNT:</span>
            <span>- <?= $currency ?> <?= number_format($sale['discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between grand-total-row">
            <span>GRAND TOTAL:</span>
            <span><?= $currency ?> <?= number_format($sale['grand_total'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <span>PAID (<?= strtoupper(htmlspecialchars($sale['payment_method'] ?? 'CASH')) ?>):</span>
            <span><?= $currency ?> <?= number_format($sale['paid_amount'], 2) ?></span>
        </div>
        <?php if ($sale['balance_amount'] > 0): ?>
        <div class="d-flex justify-content-between">
            <span>BALANCE DUE:</span>
            <span><?= $currency ?> <?= number_format($sale['balance_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mt-1 pt-1" style="border-top: 1px dashed #000;">
            <span>CUSTOMER KHATA BAL:</span>
            <span><?= $currency ?> <?= number_format($sale['current_balance'], 2) ?></span>
        </div>
    </div>

    <!-- Receipt Footer -->
    <div class="receipt-footer">
        <div><?= htmlspecialchars($footer_msg) ?></div>
        <div class="software-credit">*** Meat Shop Management POS ***</div>
    </div>
</div>

</body>
</html>
