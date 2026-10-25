<?php
/**
 * Record Payment to Supplier
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('suppliers_manage');

$branch_id = get_current_branch_id();
$supplier_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    set_flash_message('danger', 'Supplier not found.');
    header("Location: /MeatShop/suppliers/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0.0);
    $payment_method_id = (int)($_POST['payment_method_id'] ?? 1);
    $bank_account_id = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Supplier payment voucher');

    if ($amount <= 0) {
        set_flash_message('danger', 'Please enter a valid payment amount greater than zero.');
    } else {
        try {
            $pdo->beginTransaction();

            if (empty($reference_no)) {
                $reference_no = "PAY-" . date('Ymd') . "-" . rand(100, 999);
            }

            // Record transaction in supplier ledger (Debit reduces payable)
            record_supplier_transaction($pdo, $supplier_id, $branch_id, 'payment_paid', null, $reference_no, $amount, 0, $payment_method_id, $notes);

            // Deduct bank account if bank payment
            if ($bank_account_id) {
                update_bank_balance($pdo, $bank_account_id, -$amount);
            } else {
                // If paid from active cashier shift drawer
                $shift = get_active_shift($pdo, $branch_id, get_current_user_id());
                if ($shift) {
                    $upd_shift = $pdo->prepare("UPDATE cashier_shifts SET 
                        cash_supplier_payments = cash_supplier_payments + ?, 
                        expected_closing_cash = expected_closing_cash - ? 
                        WHERE id = ?");
                    $upd_shift->execute([$amount, $amount, $shift['id']]);
                }
            }

            log_audit('supplier_payment', 'suppliers', $supplier_id, ['amount' => $amount, 'ref' => $reference_no]);

            $pdo->commit();
            set_flash_message('success', 'Payment of ' . format_currency($amount) . ' recorded successfully.');
            header("Location: /MeatShop/suppliers/ledger.php?id=" . $supplier_id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Payment Methods & Bank Accounts
$payment_methods = $pdo->query("SELECT id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$bank_accounts = $pdo->query("SELECT id, bank_name, account_title, current_balance FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC")->fetchAll();

$page_title = 'Pay Meat Supplier';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-hand-holding-dollar text-danger me-2"></i> Pay Supplier: <?= htmlspecialchars($supplier['supplier_name']) ?>
                </h4>
                <p class="text-muted small mb-0">Record cash, bank transfer, or cheque payment against outstanding bills.</p>
            </div>
            <a href="/MeatShop/suppliers/ledger.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card card-custom p-4">
                    <div class="p-3 bg-danger-subtle rounded-3 mb-4 text-center">
                        <small class="text-danger fw-bold text-uppercase d-block">Current Outstanding Payable</small>
                        <h3 class="fw-extrabold text-danger mb-0"><?= format_currency($supplier['current_balance']) ?></h3>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Payment Amount (Rs) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-success text-center" placeholder="0.00" value="<?= $supplier['current_balance'] > 0 ? $supplier['current_balance'] : '' ?>" required autofocus>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Payment Method</label>
                                <select name="payment_method_id" class="form-select" id="payMethodSel">
                                    <?php foreach ($payment_methods as $pm): ?>
                                        <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['method_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Bank Account (if bank/online)</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">None (Drawer Cash)</option>
                                    <?php foreach ($bank_accounts as $ba): ?>
                                        <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name']) ?> (Bal: Rs <?= number_format($ba['current_balance'], 0) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Voucher / Cheque / Transaction Ref #</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Leave empty for auto ref">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Payment Remarks / Description</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Cleared bill for livestock batch #45"></textarea>
                        </div>

                        <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Submit Payment Voucher
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
