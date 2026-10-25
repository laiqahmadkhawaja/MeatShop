<?php
/**
 * Meat Processing & Slaughter Batches
 * Meat Shop Management System
 */

$page_title = 'Meat Processing & Yield Breakdown';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();

$stmt = $pdo->prepare("SELECT pb.*, la.tag_id, la.animal_type
    FROM processing_batches pb
    LEFT JOIN live_animals la ON pb.live_animal_id = la.id
    WHERE pb.branch_id = ?
    ORDER BY pb.id DESC");
$stmt->execute([$branch_id]);
$batches = $stmt->fetchAll();
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
                    <i class="fa-solid fa-knife text-danger me-2"></i> Slaughter & Meat Cut Processing
                </h4>
                <p class="text-muted small mb-0">Track carcass cutting, deboning, mincing, meat yield percentages, and by-product cost allocation.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/processing/create_batch.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> New Cutting / Slaughter Batch
                </a>
                <a href="/MeatShop/animals/index.php" class="btn btn-outline-dark rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-cow me-1 text-danger"></i> Live Animals
                </a>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Batch</th>
                            <th class="d-none d-md-table-cell">Date</th>
                            <th>Source</th>
                            <th class="text-end">Input Wt</th>
                            <th class="text-end">Saleable Meat</th>
                            <th class="text-end">Yield %</th>
                            <th class="text-end d-none d-lg-table-cell">Bone & Organs</th>
                            <th class="text-end d-none d-lg-table-cell">Waste</th>
                            <th class="text-end d-none d-md-table-cell">Cost</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-scissors fs-1 d-block mb-2 text-black-50"></i>
                                    No cutting or processing batches recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                            <tr>
                                <td class="fw-bold text-danger">
                                    <?= htmlspecialchars($b['batch_no']) ?>
                                    <div class="small text-muted d-md-none"><?= date('d-M', strtotime($b['processing_date'])) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= date('d-M-Y', strtotime($b['processing_date'])) ?></td>
                                <td>
                                    <?php if ($b['tag_id']): ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fa-solid fa-cow text-danger me-1"></i> <?= htmlspecialchars($b['animal_type']) ?> (#<?= htmlspecialchars($b['tag_id']) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Bulk Carcass</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-dark"><?= format_weight($b['input_weight']) ?></td>
                                <td class="text-end fw-bold text-success"><?= format_weight($b['saleable_meat_weight']) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-success-subtle text-success fs-6 fw-bold">
                                        <?= number_format($b['yield_percentage'], 1) ?>%
                                    </span>
                                </td>
                                <td class="text-end text-muted d-none d-lg-table-cell"><?= format_weight($b['bone_weight'] + $b['organ_byproduct_weight']) ?></td>
                                <td class="text-end text-danger fw-semibold d-none d-lg-table-cell"><?= format_weight($b['waste_weight']) ?></td>
                                <td class="text-end fw-bold text-dark d-none d-md-table-cell"><?= format_currency($b['total_processing_cost']) ?></td>
                                <td class="text-center">
                                    <a href="/MeatShop/processing/view_batch.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-light border px-2" title="View Batch Yield Details">
                                        <i class="fa-solid fa-eye text-primary"></i>
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
