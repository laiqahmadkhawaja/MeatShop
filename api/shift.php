<?php
/**
 * Cashier Shift Management API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();

$action = $_GET['action'] ?? '';
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

if ($action === 'open') {
    $opening_cash = (float)($_POST['opening_cash'] ?? 0.0);

    // Check if shift is already open
    $existing = get_active_shift($pdo, $branch_id, $user_id);
    if ($existing) {
        set_flash_message('warning', 'You already have an open shift (#'.$existing['id'].').');
    } else {
        $stmt = $pdo->prepare("INSERT INTO cashier_shifts (branch_id, user_id, opening_time, opening_cash, expected_closing_cash, status) 
            VALUES (?, ?, NOW(), ?, ?, 'open')");
        $stmt->execute([$branch_id, $user_id, $opening_cash, $opening_cash]);
        $shift_id = $pdo->lastInsertId();
        log_audit('open_shift', 'cashier_shifts', $shift_id, ['opening_cash' => $opening_cash]);
        set_flash_message('success', 'Shift #' . $shift_id . ' started successfully with opening cash ' . format_currency($opening_cash));
    }
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/MeatShop/pos/index.php'));
    exit;
}

if ($action === 'close') {
    $shift_id = (int)($_POST['shift_id'] ?? 0);
    $actual_cash = (float)($_POST['actual_cash'] ?? 0.0);
    $closing_notes = trim($_POST['closing_notes'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM cashier_shifts WHERE id = ? AND branch_id = ? AND user_id = ?");
    $stmt->execute([$shift_id, $branch_id, $user_id]);
    $shift = $stmt->fetch();

    if ($shift) {
        $expected = (float)$shift['expected_closing_cash'];
        $diff = $actual_cash - $expected;

        $upd = $pdo->prepare("UPDATE cashier_shifts SET 
            closing_time = NOW(), 
            actual_closing_cash = ?, 
            cash_difference = ?, 
            closing_notes = ?, 
            status = 'closed' 
            WHERE id = ?");
        $upd->execute([$actual_cash, $diff, $closing_notes, $shift_id]);
        log_audit('close_shift', 'cashier_shifts', $shift_id, ['expected' => $expected, 'actual' => $actual_cash, 'diff' => $diff]);

        // Redirect to printable shift closing report
        header("Location: /MeatShop/shifts/print_shift.php?id=" . $shift_id);
        exit;
    } else {
        set_flash_message('danger', 'Active shift not found.');
        header("Location: /MeatShop/shifts/index.php");
        exit;
    }
}
