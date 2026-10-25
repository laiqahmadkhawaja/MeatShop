<?php
/**
 * Receive Customer Payment (Khata Collection)
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('customers_manage');

$branch_id = get_current_branch_id();
$pre_customer_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0.0);
    $payment_method_id = (int)($_POST['payment_method_id'] ?? 1);
    $bank_account_id = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Customer payment collection');

    if ($customer_id <= 0 || $amount <= 0) {
        set_flash_message('danger', 'Please select customer and enter valid payment amount.');
    } else {
        try {
            $pdo->beginTransaction();

            if (empty($reference_no)) {
                $reference_no = "REC-" . date('Ymd') . "-" . rand(100, 999);
            }

            // Customer transaction: credit reduces balance
            record_customer_transaction($pdo, $customer_id, $branch_id, 'payment_received', null, $reference_no, 0, $amount, $payment_method_id, $notes);

            // Update bank balance if bank/online
            if ($bank_account_id) {
                update_bank_balance($pdo, $bank_account_id, $amount);
            } else {
                // Cash received into active cashier drawer shift
                $shift = get_active_shift($pdo, $branch_id, get_current_user_id());
                if ($shift) {
                    $upd_shift = $pdo->prepare("UPDATE cashier_shifts SET 
                        cash_customer_payments = cash_customer_payments + ?, 
                        expected_closing_cash = expected_closing_cash + ? 
                        WHERE id = ?");
                    $upd_shift->execute([$amount, $amount, $shift['id']]);
                }
            }

            log_audit('receive_customer_payment', 'customers', $customer_id, ['amount' => $amount, 'ref' => $reference_no]);

            $pdo->commit();
            set_flash_message('success', 'Payment of ' . format_currency($amount) . ' received successfully.');
            header("Location: /MeatShop/customers/ledger.php?id=" . $customer_id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Customers, Payment Methods, Bank Accounts
$customers = $pdo->query("SELECT id, customer_name, phone, current_balance FROM customers WHERE status = 'active' ORDER BY customer_name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$bank_accounts = $pdo->query("SELECT id, bank_name, account_title, current_balance FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC")->fetchAll();

$page_title = 'Receive Customer Payment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-hand-holding-dollar text-danger me-2"></i> Receive Customer Khata Payment
                </h4>
                <p class="text-muted small mb-0">Record cash, bank transfer, JazzCash, or Easypaisa payment received from customer.</p>
            </div>
            <a href="/MeatShop/customers/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Customers
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card card-custom p-4">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="selPayCustomer" class="form-select" onchange="onCustSelect(this)" required autofocus>
                                <option value="" data-balance="0">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" data-balance="<?= $c['current_balance'] ?>" <?= $pre_customer_id == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['phone']) ?>) <?= $c['current_balance'] > 0 ? '[Receivable: Rs ' . number_format($c['current_balance'], 0) . ']' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Current Balance Display -->
                        <div class="p-3 bg-light rounded-3 mb-3 text-center border">
                            <small class="text-muted text-uppercase fw-bold d-block">Current Outstanding Balance</small>
                            <h4 class="fw-bold text-danger mb-0" id="dispCustBal">Rs 0.00</h4>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Payment Amount Received (Rs) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" id="inAmount" class="form-control form-control-lg fw-bold text-success text-center" placeholder="0.00" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Payment Method</label>
                                <select name="payment_method_id" class="form-select">
                                    <?php foreach ($payment_methods as $pm): ?>
                                        <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['method_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Deposit To Account</label>
                                <select name="bank_account_id" class="form-select">
                                    <option value="">Cash Drawer (Shift Register)</option>
                                    <?php foreach ($bank_accounts as $ba): ?>
                                        <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name']) ?> - <?= htmlspecialchars($ba['account_title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Receipt / Transaction Ref #</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Leave empty for auto ref">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Received via JazzCash / Counter cash"></textarea>
                        </div>

                        <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Confirm & Update Customer Khata
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function onCustSelect(el) {
    const bal = parseFloat($(el).find('option:selected').data('balance')) || 0;
    $('#dispCustBal').text('Rs ' + bal.toFixed(2));
    if (bal > 0) {
        $('#inAmount').val(bal.toFixed(2));
    }
}

$(document).ready(function() {
    if ($('#selPayCustomer').val()) {
        onCustSelect($('#selPayCustomer')[0]);
    }
});
</script>
