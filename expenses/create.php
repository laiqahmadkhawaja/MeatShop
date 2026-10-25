<?php
/**
 * Create Operational Expense
 * Meat Shop Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('expenses_manage');

$branch_id = get_current_branch_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0.0);
    $payment_method_id = (int)($_POST['payment_method_id'] ?? 1);
    $bank_account_id = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
    $reference_no = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_id <= 0 || $amount <= 0) {
        set_flash_message('danger', 'Please select expense category and enter valid amount.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO expenses (branch_id, category_id, expense_date, amount, payment_method_id, bank_account_id, reference_no, description, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $branch_id, $category_id, $expense_date, $amount, $payment_method_id, $bank_account_id,
                $reference_no, $description, get_current_user_id()
            ]);
            $exp_id = $pdo->lastInsertId();

            // Deduct bank if bank account
            if ($bank_account_id) {
                update_bank_balance($pdo, $bank_account_id, -$amount);
            } else {
                // If paid from active cashier shift drawer
                $shift = get_active_shift($pdo, $branch_id, get_current_user_id());
                if ($shift) {
                    $upd_shift = $pdo->prepare("UPDATE cashier_shifts SET 
                        cash_expenses = cash_expenses + ?, 
                        expected_closing_cash = expected_closing_cash - ? 
                        WHERE id = ?");
                    $upd_shift->execute([$amount, $amount, $shift['id']]);
                }
            }

            log_audit('create_expense', 'expenses', $exp_id, ['amount' => $amount, 'category' => $category_id]);

            $pdo->commit();
            set_flash_message('success', 'Expense of ' . format_currency($amount) . ' recorded successfully.');
            header("Location: /MeatShop/expenses/index.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Fetch Categories, Payment Methods, Banks
$categories = $pdo->query("SELECT id, category_name FROM expense_categories ORDER BY category_name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT id, method_name FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$bank_accounts = $pdo->query("SELECT id, bank_name, account_title, current_balance FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC")->fetchAll();

$page_title = 'Record Operational Expense';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-receipt text-danger me-2"></i> Record Operating Expense
                </h4>
                <p class="text-muted small mb-0">Record shop rent, commercial electricity, cooling gas, butcher wages, or packaging.</p>
            </div>
            <a href="/MeatShop/expenses/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Expenses
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <form method="POST" action="" class="card card-custom p-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Expense Category <span class="text-danger">*</span></label>
                            <a href="javascript:void(0)" class="quick-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddExpenseCategoryModal" onclick="window.currentQuickSelectTarget='selExpCat';">+ New Category</a>
                        </div>
                        <select name="category_id" id="selExpCat" class="form-select" required autofocus>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $ec): ?>
                                <option value="<?= $ec['id'] ?>"><?= htmlspecialchars($ec['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Amount (Rs) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold text-danger text-center" placeholder="0.00" required>
                        </div>
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
                            <label class="form-label small fw-bold">Paid From Account</label>
                            <select name="bank_account_id" class="form-select">
                                <option value="">Cash Drawer (Shift Register)</option>
                                <?php foreach ($bank_accounts as $ba): ?>
                                    <option value="<?= $ba['id'] ?>"><?= htmlspecialchars($ba['bank_name']) ?> (Bal: Rs <?= number_format($ba['current_balance'], 0) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Receipt / Voucher Ref #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. Utility bill # / voucher #">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Expense Description / Purpose</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. Purchased 2 ice blocks for mutton chiller"></textarea>
                    </div>

                    <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-1"></i> Save Expense Record
                    </button>
                </form>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
