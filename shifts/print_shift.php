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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Report #<?= $shift['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
        }
        .report-box {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .report-box { box-shadow: none; width: 100%; margin: 0; padding: 4px; }
        }
    </style>
</head>
<body>

<div class="text-center py-3 no-print">
    <button onclick="window.print()" class="btn btn-dark btn-sm rounded-pill px-4 me-2">
        <i class="fa-solid fa-print me-1"></i> Print Shift Report
    </button>
    <a href="/MeatShop/shifts/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-arrow-left me-1"></i> Back
    </a>
</div>

<div class="report-box">
    <div class="text-center border-bottom pb-2 mb-2">
        <h5 class="fw-bold mb-0"><?= htmlspecialchars(get_setting('shop_name', 'AL-BARAKAH MEAT')) ?></h5>
        <div style="font-size:0.8rem;"><?= htmlspecialchars($shift['branch_name']) ?></div>
        <div class="fw-bold fs-6 mt-1">CASHIER SHIFT CLOSING REPORT</div>
        <div style="font-size:0.75rem;">Shift #<?= $shift['id'] ?> | Status: <?= strtoupper($shift['status']) ?></div>
    </div>

    <div style="font-size:0.75rem;" class="mb-2">
        <div><strong>Cashier:</strong> <?= htmlspecialchars($shift['cashier_name']) ?></div>
        <div><strong>Opened:</strong> <?= date('d-M-Y h:i A', strtotime($shift['opening_time'])) ?></div>
        <div><strong>Closed:</strong> <?= $shift['closing_time'] ? date('d-M-Y h:i A', strtotime($shift['closing_time'])) : 'Active' ?></div>
    </div>

    <div class="border-top border-bottom py-2 my-2" style="font-size:0.8rem;">
        <div class="d-flex justify-content-between mb-1">
            <span>Invoices Count:</span>
            <strong><?= (int)$sales_summary['count'] ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
            <span>Total Meat Sold:</span>
            <strong><?= number_format($sales_summary['total_weight'], 3) ?> KG</strong>
        </div>
        <div class="d-flex justify-content-between mb-1">
            <span>Total Sales Billed:</span>
            <strong>Rs <?= number_format($sales_summary['total'], 2) ?></strong>
        </div>
    </div>

    <div class="border-bottom pb-2 mb-2" style="font-size:0.8rem;">
        <div class="d-flex justify-content-between">
            <span>Opening Drawer Cash:</span>
            <span>Rs <?= number_format($shift['opening_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(+) Cash Sales:</span>
            <span>Rs <?= number_format($shift['cash_sales'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(+) Customer Collections:</span>
            <span>Rs <?= number_format($shift['cash_customer_payments'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(-) Supplier Payments:</span>
            <span>Rs <?= number_format($shift['cash_supplier_payments'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>(-) Cash Expenses Paid:</span>
            <span>Rs <?= number_format($shift['cash_expenses'], 2) ?></span>
        </div>
        <hr class="my-1">
        <div class="d-flex justify-content-between fw-bold">
            <span>EXPECTED DRAWER CASH:</span>
            <span>Rs <?= number_format($shift['expected_closing_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between fw-bold text-success">
            <span>ACTUAL COUNTED CASH:</span>
            <span>Rs <?= number_format($shift['actual_closing_cash'], 2) ?></span>
        </div>
        <div class="d-flex justify-content-between fw-bold <?= $shift['cash_difference'] < 0 ? 'text-danger' : 'text-primary' ?>">
            <span>CASH DISCREPANCY:</span>
            <span>Rs <?= number_format($shift['cash_difference'], 2) ?></span>
        </div>
    </div>

    <?php if (!empty($shift['closing_notes'])): ?>
    <div style="font-size:0.75rem;" class="mb-3">
        <strong>Notes / Discrepancy Reason:</strong><br>
        <?= nl2br(htmlspecialchars($shift['closing_notes'])) ?>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between pt-4 text-center" style="font-size:0.7rem;">
        <div style="border-top:1px solid #000; width:45%;">Cashier Signature</div>
        <div style="border-top:1px solid #000; width:45%;">Manager Signature</div>
    </div>
</div>

</body>
</html>
