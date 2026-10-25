<?php
/**
 * Global Top Navbar
 */
?>
<nav class="top-navbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <!-- Sidebar Toggle (Mobile & Desktop) -->
        <button class="btn btn-light border shadow-sm" id="sidebarToggle" type="button" title="Toggle Sidebar (Expand/Collapse)">
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- Current Branch Selector -->
        <div class="dropdown d-none d-sm-block">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1 shadow-sm" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-store text-danger"></i>
                <span class="fw-semibold">
                    <?php 
                    $curr_bname = 'Branch';
                    foreach ($all_branches as $b) {
                        if ($b['id'] == $current_branch_id) { $curr_bname = $b['branch_name']; break; }
                    }
                    echo htmlspecialchars($curr_bname);
                    ?>
                </span>
            </button>
            <ul class="dropdown-menu shadow border-0">
                <li class="dropdown-header text-uppercase fs-xs">Switch Branch</li>
                <?php foreach ($all_branches as $b): ?>
                <li>
                    <a class="dropdown-item d-flex justify-content-between align-items-center <?= ($b['id'] == $current_branch_id) ? 'active' : '' ?>" href="/MeatShop/api/switch_branch.php?branch_id=<?= $b['id'] ?>">
                        <span><?= htmlspecialchars($b['branch_name']) ?></span>
                        <span class="badge bg-secondary ms-2"><?= htmlspecialchars($b['branch_code']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Scale Integration Status Badge -->
        <div class="d-none d-md-flex align-items-center gap-2 bg-light border rounded-pill px-3 py-1 text-muted fs-sm">
            <i class="fa-solid fa-scale-balanced text-primary"></i>
            <span id="globalScaleStatusText">Weigh Scale: <strong class="text-success">Ready</strong></span>
        </div>
    </div>

    <!-- Right Side Actions -->
    <div class="d-flex align-items-center gap-2">
        <!-- Fast POS Link -->
        <a href="/MeatShop/pos/index.php" class="btn btn-sm btn-meat rounded-pill px-3 py-1 d-flex align-items-center gap-2 shadow-sm">
            <i class="fa-solid fa-cash-register"></i>
            <span class="d-none d-md-inline">POS Billing</span>
        </a>

        <!-- Shift Status Indicator -->
        <?php if ($active_shift): ?>
            <button class="btn btn-sm badge-subtle-success rounded-pill px-3 py-1 border-0" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
                <i class="fa-solid fa-circle-check text-success me-1"></i> Shift Open (#<?= $active_shift['id'] ?>)
            </button>
        <?php else: ?>
            <button class="btn btn-sm badge-subtle-danger rounded-pill px-3 py-1 border-0" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                <i class="fa-solid fa-circle-exclamation text-danger me-1"></i> Shift Closed
            </button>
        <?php endif; ?>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light rounded-circle border shadow-sm p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-user-tie text-secondary"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="px-3 py-2 border-bottom">
                    <p class="mb-0 fw-bold"><?= htmlspecialchars(get_current_user_fullname()) ?></p>
                    <small class="text-muted text-uppercase" style="font-size: 0.72rem;"><?= htmlspecialchars(get_current_role_name()) ?></small>
                </li>
                <li><a class="dropdown-item py-2" href="/MeatShop/settings/users.php"><i class="fa-solid fa-id-badge me-2 text-muted"></i> My Profile</a></li>
                <li><a class="dropdown-item py-2" href="/MeatShop/settings/index.php"><i class="fa-solid fa-gear me-2 text-muted"></i> Settings</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger" href="/MeatShop/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Sign Out</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Global Shift Modals (Open & Close) -->
<!-- Modal: Open Shift -->
<div class="modal fade" id="openShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form action="/MeatShop/api/shift.php?action=open" method="POST">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-lock-open me-2"></i> Open Cashier Shift</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Opening Cash Float (<?= htmlspecialchars(get_setting('currency_symbol', 'Rs.')) ?>)</label>
                        <input type="number" step="0.01" name="opening_cash" class="form-control form-control-lg text-center fw-bold" placeholder="0.00" value="0.00" required autofocus>
                        <small class="text-muted">Enter cash present in the drawer at start.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3"><i class="fa-solid fa-check me-1"></i> Start Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Close Shift -->
<?php if ($active_shift): ?>
<div class="modal fade" id="closeShiftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="/MeatShop/api/shift.php?action=close" method="POST">
                <input type="hidden" name="shift_id" value="<?= $active_shift['id'] ?>">
                <div class="modal-header bg-dark text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-lock me-2"></i> End Shift (#<?= $active_shift['id'] ?>)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Opening Cash</small>
                                <span class="fw-bold"><?= format_currency($active_shift['opening_cash']) ?></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Expected Cash</small>
                                <span class="fw-bold text-primary"><?= format_currency($active_shift['expected_closing_cash']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Actual Counted Cash In Drawer</label>
                        <input type="number" step="0.01" name="actual_cash" class="form-control form-control-lg text-center fw-bold text-success" placeholder="0.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Closing Notes / Discrepancy Reason</label>
                        <textarea name="closing_notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark btn-sm px-3"><i class="fa-solid fa-calculator me-1"></i> Close & Print Shift Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
