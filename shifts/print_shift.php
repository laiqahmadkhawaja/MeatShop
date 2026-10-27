<?php
/**
 * Printable Cashier Shift Closing Report
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$shift_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT cs.*, u.full_name as cashier_name, b.branch_name, b.phone as branch_phone
    FROM cashier_shifts cs
    JOIN users u ON cs.user_id = u.id
    JOIN branches b ON cs.branch_id = b.id
    WHERE cs.id = ?");
$stmt->execute([$shift_id]);
$shift = $stmt->fetch();

if (!$shift) {
    die("Shift record not found.");
}

// Fetch sales done in this shift
$sales_stmt = $pdo->prepare("SELECT COUNT(id) as count, COALESCE(SUM(grand_total), 0) as total, COALESCE(SUM(total_weight), 0) as total_weight 
    FROM sales WHERE shift_id = ? AND status != 'cancelled'");
$sales_stmt->execute([$shift_id]);
$sales_summary = $sales_stmt->fetch();
// Thermal roll width handling (58mm -> 48mm printable, 80mm -> 72mm printable)
$selected_width = $_GET['width'] ?? get_setting('receipt_width', '80mm');
if ($selected_width !== '58mm' && $selected_width !== '80mm') {
    $selected_width = '80mm';
}
$printable_width = ($selected_width === '58mm') ? '48mm' : '72mm';
$roll_size = ($selected_width === '58mm') ? '58mm auto' : '80mm auto';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Report #<?= $shift['id'] ?> (<?= $selected_width ?>)</title>
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

        /* Enforce Uniform Bold Weight (700) across all report text for dense thermal burn */
        .report-box,
        .report-box * {
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif !important;
            font-weight: 700 !important;
            color: #000 !important;
            border-color: #000 !important;
            -webkit-font-smoothing: antialiased !important;
            -moz-osx-font-smoothing: grayscale !important;
            text-rendering: geometricPrecision !important;
            box-sizing: border-box;
        }

        .report-box {
            background: #fff !important;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            border-radius: 4px;
            line-height: 1.25;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* 80mm Roll (Printable: 72mm) */
        .report-box.roll-80mm {
            width: 72mm;
            max-width: 72mm;
            padding: 2.5mm 3.5mm;
        }

        /* 58mm Roll (Printable: 48mm) */
        .report-box.roll-58mm {
            width: 48mm;
            max-width: 48mm;
            padding: 1.5mm 2mm;
        }

        /* Hierarchy via Font Size Only: 80mm Roll (72mm Printable) */
        .roll-80mm .report-title { font-size: 16px; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 2px; text-align: center; }
        .roll-80mm .report-sub { font-size: 11px; text-align: center; }
        .roll-80mm .report-heading { font-size: 13px; text-transform: uppercase; text-align: center; margin: 4px 0 2px 0; }
        .roll-80mm .report-meta { font-size: 11px; margin-bottom: 6px; }
        .roll-80mm .report-section { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 5px 0; margin: 5px 0; font-size: 11.5px; }
        .roll-80mm .report-totals { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; font-size: 11.5px; }
        .roll-80mm .report-highlight { font-size: 13.5px; border-top: 1.5px solid #000; padding-top: 3px; margin-top: 3px; }
        .roll-80mm .report-notes { font-size: 10.5px; margin-bottom: 10px; }
        .roll-80mm .report-signatures { font-size: 10px; padding-top: 18px; text-align: center; }

        /* Hierarchy via Font Size Only: 58mm Roll (48mm Printable) */
        .roll-58mm .report-title { font-size: 13.5px; letter-spacing: 0.2px; text-transform: uppercase; margin-bottom: 2px; text-align: center; }
        .roll-58mm .report-sub { font-size: 9.5px; text-align: center; }
        .roll-58mm .report-heading { font-size: 11px; text-transform: uppercase; text-align: center; margin: 3px 0 2px 0; }
        .roll-58mm .report-meta { font-size: 9.5px; margin-bottom: 4px; }
        .roll-58mm .report-section { border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 4px 0; margin: 4px 0; font-size: 9.5px; }
        .roll-58mm .report-totals { border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 4px; font-size: 9.5px; }
        .roll-58mm .report-highlight { font-size: 11.5px; border-top: 1.5px solid #000; padding-top: 2px; margin-top: 2px; }
        .roll-58mm .report-notes { font-size: 9px; margin-bottom: 8px; }
        .roll-58mm .report-signatures { font-size: 8.5px; padding-top: 14px; text-align: center; }

        /* Pure Black Dividers */
        .report-header {
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
            .report-box {
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
<div class="text-center py-3 no-print">
    <div class="d-inline-flex flex-wrap align-items-center justify-content-center gap-2 mb-2">
        <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 fw-bold shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Shift Report
        </button>

        <!-- Roll Width Switcher -->
        <div class="btn-group btn-group-sm rounded-pill shadow-sm" role="group">
            <a href="?id=<?= $shift_id ?>&width=80mm" class="btn <?= $selected_width === '80mm' ? 'btn-dark fw-bold' : 'btn-outline-dark' ?>">
                <i class="fa-solid fa-receipt me-1"></i> 80mm Roll (72mm Print)
            </a>
            <a href="?id=<?= $shift_id ?>&width=58mm" class="btn <?= $selected_width === '58mm' ? 'btn-dark fw-bold' : 'btn-outline-dark' ?>">
                <i class="fa-solid fa-receipt me-1"></i> 58mm Roll (48mm Print)
            </a>
        </div>

        <a href="/MeatShop/shifts/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>
    <div class="text-muted small">
        Thermal Mode: <strong class="text-dark"><?= $selected_width === '58mm' ? '58mm (48mm printable)' : '80mm (72mm printable)' ?></strong> | Monoline Sans-Serif | Uniform Bold 700 | Pure #000
    </div>
</div>

<div class="report-box roll-<?= $selected_width ?>">
    <div class="report-header">
        <div class="report-title"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></div>
        <div class="report-sub"><?= htmlspecialchars($shift['branch_name']) ?></div>
        <div class="report-heading">CASHIER SHIFT CLOSING REPORT</div>
        <div class="report-sub">SHIFT #<?= $shift['id'] ?> | STATUS: <?= strtoupper($shift['status']) ?></div>
    </div>

    <div class="report-meta">
        <div>CASHIER: <?= htmlspecialchars($shift['cashier_name']) ?></div>
        <div>OPENED: <?= date('d-M-Y h:i A', strtotime($shift['opening_time'])) ?></div>
        <div>CLOSED: <?= $shift['closing_time'] ? date('d-M-Y h:i A', strtotime($shift['closing_time'])) : 'ACTIVE' ?></div>
    </div>

    <div class="report-section">
        <div class="d-flex justify-content-between mb-1">
            <span>INVOICES COUNT:</span>
            <span><?= (int)$sales_summary['count'] ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1">
            <span>TOTAL MEAT SOLD:</span>
            <span><?= number_format($sales_summary['total_weight'], 3) ?> KG</span>
        </div>
        <div class="d-flex justify-content-between">
            <span>TOTAL SALES BILLED:</span>
            <span>Rs <?= number_format($sales_summary['total'], 2) ?></span>
        </div>
    </div>

    <div class="report-totals">
        <div class="d-flex justify-content-between">
            <span>OPENING DRAWER CASH:</span>
            <span>Rs <?= number_format($shift['opening_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(+) CASH SALES:</span>
            <span>Rs <?= number_format($shift['cash_sales'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(+) CUSTOMER COLLECTIONS:</span>
            <span>Rs <?= number_format($shift['cash_customer_payments'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(-) SUPPLIER PAYMENTS:</span>
            <span>Rs <?= number_format($shift['cash_supplier_payments'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(-) CASH EXPENSES PAID:</span>
            <span>Rs <?= number_format($shift['cash_expenses'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between report-highlight">
            <span>EXPECTED DRAWER CASH:</span>
            <span>Rs <?= number_format($shift['expected_closing_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mt-1">
            <span>ACTUAL COUNTED CASH:</span>
            <span>Rs <?= number_format($shift['actual_closing_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mt-1 pt-1" style="border-top: 1px dashed #000;">
            <span>CASH DISCREPANCY:</span>
            <span>Rs <?= number_format($shift['cash_difference'], 2) ?></span>
        </div>
    </div>

    <?php if (!empty($shift['closing_notes'])): ?>
    <div class="report-notes">
        <div>NOTES / DISCREPANCY REASON:</div>
        <div><?= nl2br(htmlspecialchars($shift['closing_notes'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between report-signatures">
        <div style="border-top: 1px solid #000; width: 45%;">CASHIER SIGNATURE</div>
        <div style="border-top: 1px solid #000; width: 45%;">MANAGER SIGNATURE</div>
    </div>
</div>

</body>
</html>
