<?php
/**
 * View Processing Batch Details & Yield Report
 * Meat Shop Management System
 */

$page_title = 'Batch Yield Report';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$batch_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT pb.*, la.tag_id, la.animal_type, b.branch_name
    FROM processing_batches pb
    LEFT JOIN live_animals la ON pb.live_animal_id = la.id
    JOIN branches b ON pb.branch_id = b.id
    WHERE pb.id = ?");
$stmt->execute([$batch_id]);
$batch = $stmt->fetch();

if (!$batch) {
    set_flash_message('danger', 'Batch not found.');
    header("Location: /MeatShop/processing/index.php");
    exit;
}

$out_stmt = $pdo->prepare("SELECT po.*, p.product_name, p.product_code, u.unit_code
    FROM processing_outputs po
    JOIN products p ON po.product_id = p.id
    JOIN units u ON p.selling_unit_id = u.id
    WHERE po.processing_batch_id = ?");
$out_stmt->execute([$batch_id]);
$outputs = $out_stmt->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-chart-pie text-danger me-2"></i> Batch Yield Breakdown: <?= htmlspecialchars($batch['batch_no']) ?>
                </h4>
                <p class="text-muted small mb-0">Processed on <?= date('d M Y', strtotime($batch['processing_date'])) ?> | Butcher: <?= htmlspecialchars($batch['responsible_employee'] ?: 'General') ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Yield Report
                </button>
                <a href="/MeatShop/processing/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="card card-custom p-4 mb-4">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-light rounded-3">
                        <small class="text-muted d-block text-uppercase fw-bold">Live Input Weight</small>
                        <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_weight($batch['input_weight']) ?></h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-success-subtle rounded-3">
                        <small class="text-success d-block text-uppercase fw-bold">Saleable Meat Yield</small>
                        <h4 class="fw-bold text-success mb-0 mt-1"><?= format_weight($batch['saleable_meat_weight']) ?> (<?= number_format($batch['yield_percentage'], 1) ?>%)</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-light rounded-3">
                        <small class="text-muted d-block text-uppercase fw-bold">Bones & By-products</small>
                        <h4 class="fw-bold text-dark mb-0 mt-1"><?= format_weight($batch['bone_weight'] + $batch['organ_byproduct_weight']) ?></h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 bg-danger-subtle rounded-3">
                        <small class="text-danger d-block text-uppercase fw-bold">Shrinkage / Waste Loss</small>
                        <h4 class="fw-bold text-danger mb-0 mt-1"><?= format_weight($batch['waste_weight']) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="card-header-clean">
                <h6 class="fw-bold text-dark mb-0">Produced Meat Cuts & Cost Allocation Breakdown</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>#</th>
                            <th>Product / Cut Name</th>
                            <th>Cut Category</th>
                            <th class="text-end">Weight Yielded</th>
                            <th class="text-end">Allocated Cost / KG</th>
                            <th class="text-end">Total Line Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($outputs as $op): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($op['product_name']) ?> (<?= htmlspecialchars($op['product_code']) ?>)</td>
                            <td>
                                <span class="badge <?= $op['output_type'] === 'saleable_meat' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> text-uppercase">
                                    <?= str_replace('_', ' ', $op['output_type']) ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold text-dark"><?= format_weight($op['output_weight'], $op['unit_code']) ?></td>
                            <td class="text-end fw-semibold text-danger"><?= format_currency($op['allocated_cost_per_kg']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= format_currency($op['total_allocated_cost']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
