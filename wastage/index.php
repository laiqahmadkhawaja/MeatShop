<?php
/**
 * Wastage & Cutting Loss Management
 * Meat Shop Management System
 */

$page_title = 'Meat Wastage & Cutting Loss';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT w.*, p.product_name, p.product_code, u.full_name as reported_by_name, app.full_name as approved_by_name
    FROM wastage_records w
    JOIN products p ON w.product_id = p.id
    JOIN users u ON w.user_id = u.id
    LEFT JOIN users app ON w.approved_by = app.id
    WHERE w.branch_id = ?
    ORDER BY w.id DESC");
$stmt->execute([$branch_id]);
$wastages = $stmt->fetchAll();

// Summary stats
$tot_loss_weight = array_sum(array_column($wastages, 'weight'));
$tot_loss_cost = array_sum(array_column($wastages, 'total_loss_cost'));
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
                    <i class="fa-solid fa-trash-can text-danger me-2"></i> Meat Cutting Loss & Wastage Tracking
                </h4>
                <p class="text-muted small mb-0">Distinguish normal trimming/cutting shrinkage from spoiled or damaged meat.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/wastage/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Record Wastage / Loss
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="card card-custom p-3 border-start border-warning border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Meat Discarded / Loss (KG)</small>
                    <h3 class="fw-extrabold text-dark mb-0 mt-1"><?= format_weight($tot_loss_weight) ?></h3>
                </div>
            </div>
            <div class="col-6">
                <div class="card card-custom p-3 border-start border-danger border-4">
                    <small class="text-muted fw-bold text-uppercase">Total Cost Impact of Loss</small>
                    <h3 class="fw-extrabold text-danger mb-0 mt-1"><?= format_currency($tot_loss_cost) ?></h3>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Meat Product</th>
                            <th class="d-none d-md-table-cell">Category</th>
                            <th>Classification</th>
                            <th class="text-end">Weight</th>
                            <th class="text-end d-none d-md-table-cell">Cost / KG</th>
                            <th class="text-end">Loss Cost</th>
                            <th class="d-none d-lg-table-cell">Reported By</th>
                            <th class="text-center d-none d-md-table-cell">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($wastages)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-circle-check fs-1 d-block mb-2 text-success"></i>
                                    No wastage or shrinkage recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($wastages as $w): ?>
                            <tr>
                                <td><?= date('d-M', strtotime($w['wastage_date'])) ?></td>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($w['product_name']) ?>
                                    <div class="small text-muted d-md-none">
                                        <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size:0.65rem;">
                                            <?= str_replace('_', ' ', $w['wastage_type']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase">
                                        <?= str_replace('_', ' ', $w['wastage_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($w['is_normal_loss']): ?>
                                        <span class="badge bg-info-subtle text-info">Normal Cutting</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Spoilage</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_weight($w['weight']) ?></td>
                                <td class="text-end text-muted d-none d-md-table-cell"><?= format_currency($w['cost_per_kg']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($w['total_loss_cost']) ?></td>
                                <td class="d-none d-lg-table-cell"><small class="text-muted"><?= htmlspecialchars($w['reported_by_name']) ?></small></td>
                                <td class="text-center d-none d-md-table-cell">
                                    <span class="badge badge-subtle-success">Approved</span>
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
