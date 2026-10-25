<?php
/**
 * Cashier Register & Shift Management
 * Meat Shop Management System
 */

$page_title = 'Cashier Shifts & Register';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT cs.*, u.full_name as cashier_name, b.branch_name
    FROM cashier_shifts cs
    JOIN users u ON cs.user_id = u.id
    JOIN branches b ON cs.branch_id = b.id
    WHERE cs.branch_id = ?
    ORDER BY cs.id DESC");
$stmt->execute([$branch_id]);
$shifts = $stmt->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= htmlspecialchars($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-user-clock text-danger me-2"></i> Cashier Register & Shifts
                </h4>
                <p class="text-muted small mb-0">Track shift opening floats, counter cash movements, drawer reconciliation, and cash discrepancies.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($active_shift): ?>
                    <button class="btn btn-dark rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
                        <i class="fa-solid fa-lock me-1"></i> Close Active Shift (#<?= $active_shift['id'] ?>)
                    </button>
                <?php else: ?>
                    <button class="btn btn-meat rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                        <i class="fa-solid fa-lock-open me-1"></i> Open New Cashier Shift
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Shift</th>
                            <th>Cashier</th>
                            <th class="d-none d-md-table-cell">Timing</th>
                            <th class="text-end d-none d-lg-table-cell">Opening Float</th>
                            <th class="text-end d-none d-md-table-cell">Cash Sales</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end d-none d-sm-table-cell">Actual</th>
                            <th class="text-end d-none d-md-table-cell">Diff</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shifts)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-cash-register fs-1 d-block mb-2 text-black-50"></i>
                                    No cashier shifts logged yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shifts as $s): ?>
                            <tr>
                                <td class="fw-bold text-danger">
                                    #<?= $s['id'] ?>
                                    <div class="small text-muted d-md-none"><?= date('d-M h:i A', strtotime($s['opening_time'])) ?></div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($s['cashier_name']) ?></strong>
                                    <div class="small text-muted d-md-none">Sales: <?= format_currency($s['cash_sales']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <div class="small"><?= date('d-M h:i A', strtotime($s['opening_time'])) ?></div>
                                    <div class="small text-muted"><?= $s['closing_time'] ? date('d-M h:i A', strtotime($s['closing_time'])) : '<span class="text-success fw-bold">Active Now</span>' ?></div>
                                </td>
                                <td class="text-end text-muted d-none d-lg-table-cell"><?= format_currency($s['opening_cash']) ?></td>
                                <td class="text-end fw-semibold text-dark d-none d-md-table-cell"><?= format_currency($s['cash_sales']) ?></td>
                                <td class="text-end fw-bold text-dark"><?= format_currency($s['expected_closing_cash']) ?></td>
                                <td class="text-end fw-bold text-success d-none d-sm-table-cell">
                                    <?= $s['status'] === 'closed' ? format_currency($s['actual_closing_cash']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold d-none d-md-table-cell">
                                    <?php if ($s['status'] === 'closed'): ?>
                                        <?php if ($s['cash_difference'] == 0): ?>
                                            <span class="text-success">Rs 0</span>
                                        <?php elseif ($s['cash_difference'] > 0): ?>
                                            <span class="text-success">+<?= format_currency($s['cash_difference']) ?></span>
                                        <?php else: ?>
                                            <span class="text-danger"><?= format_currency($s['cash_difference']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['status'] === 'open'): ?>
                                        <span class="badge badge-subtle-success">Open</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="/MeatShop/shifts/print_shift.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-light border px-2" title="Print Shift Closing Report">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
