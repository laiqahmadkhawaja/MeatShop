<?php
/**
 * Customer Khata Ledger Statement
 * Meat Shop Management System
 */

$page_title = 'Customer Khata Ledger';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$customer_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash_message('danger', 'Customer not found.');
    header("Location: /MeatShop/customers/index.php");
    exit;
}

// Fetch transactions
$tx_stmt = $pdo->prepare("SELECT ct.*, pm.method_name as payment_method, u.full_name as created_by_name
    FROM customer_transactions ct
    LEFT JOIN payment_methods pm ON ct.payment_method_id = pm.id
    JOIN users u ON ct.created_by = u.id
    WHERE ct.customer_id = ?
    ORDER BY ct.id ASC");
$tx_stmt->execute([$customer_id]);
$transactions = $tx_stmt->fetchAll();

$total_debit = array_sum(array_column($transactions, 'debit')); // Sales billed
$total_credit = array_sum(array_column($transactions, 'credit')); // Payments received

// WhatsApp statement text
$shop_name = get_setting('shop_name', 'AL-BARAKAH MEAT SHOP');
$wa_text = "*$shop_name - Customer Khata Statement*\n"
    . "Customer: {$customer['customer_name']}\n"
    . "Date: " . date('d-M-Y') . "\n"
    . "Total Purchases Billed: Rs " . number_format($total_debit, 2) . "\n"
    . "Total Payments Received: Rs " . number_format($total_credit, 2) . "\n"
    . "--------------------------\n"
    . "*Net Outstanding Balance Due: Rs " . number_format($customer['current_balance'], 2) . "*\n\n"
    . "Thank you for your business!";
$wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $customer['phone']) . "?text=" . urlencode($wa_text);
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-book-open text-danger me-2"></i> Khata: <?= htmlspecialchars($customer['customer_name']) ?>
                </h4>
                <p class="text-muted small mb-0">Phone: <?= htmlspecialchars($customer['phone']) ?> | Type: <?= ucfirst(str_replace('_', ' ', $customer['customer_type'])) ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Statement
                </button>
                <?php if (!empty($customer['phone']) && $customer['phone'] !== '03000000000'): ?>
                <a href="<?= $wa_url ?>" target="_blank" class="btn btn-success rounded-pill px-3">
                    <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp Reminder
                </a>
                <?php endif; ?>
                <a href="/MeatShop/customers/receive_payment.php?id=<?= $customer['id'] ?>" class="btn btn-meat rounded-pill px-3">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                </a>
                <a href="/MeatShop/customers/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Khata Balance Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Meat Sales (Debit)</small>
                    <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($total_debit) ?></h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center">
                    <small class="text-muted fw-bold text-uppercase">Total Payments Received (Credit)</small>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_currency($total_credit) ?></h4>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-custom p-3 text-center bg-danger-subtle border-danger">
                    <small class="text-danger fw-bold text-uppercase">Outstanding Balance (Receivable)</small>
                    <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($customer['current_balance']) ?></h4>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th class="d-none d-md-table-cell">Ref #</th>
                            <th class="d-none d-lg-table-cell">Payment Method / Details</th>
                            <th class="text-end text-danger">Debit</th>
                            <th class="text-end text-success">Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    No ledger entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d-M-y', strtotime($t['transaction_date'])) ?></strong>
                                    <div class="small text-muted"><?= date('h:i A', strtotime($t['transaction_date'])) ?></div>
                                    <div class="d-md-none small text-muted">Ref: <?= htmlspecialchars($t['reference_no'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <span class="badge <?= $t['debit'] > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> text-uppercase" style="font-size: 0.7rem;">
                                        <?= str_replace('_', ' ', $t['transaction_type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold d-none d-md-table-cell"><?= htmlspecialchars($t['reference_no'] ?: '-') ?></td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($t['notes'] ?: ($t['payment_method'] ?? 'Counter Sale')) ?></small></td>
                                <td class="text-end fw-bold text-danger">
                                    <?= $t['debit'] > 0 ? format_currency($t['debit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= $t['credit'] > 0 ? format_currency($t['credit']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    <?= format_currency($t['balance_after']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="4" class="text-end">Total Sum:</td>
                            <td class="text-end text-danger"><?= format_currency($total_debit) ?></td>
                            <td class="text-end text-success"><?= format_currency($total_credit) ?></td>
                            <td class="text-end text-dark"><?= format_currency($customer['current_balance']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
