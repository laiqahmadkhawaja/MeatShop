<?php
/**
 * Live Animal Intake & Inventory
 * Meat Shop Management System
 */

$page_title = 'Live Animal Purchase & Slaughter Intake';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$stmt = $pdo->query("SELECT a.*, s.supplier_name, s.phone as supplier_phone 
    FROM live_animals a 
    JOIN suppliers s ON a.supplier_id = s.id 
    ORDER BY a.id DESC");
$animals = $stmt->fetchAll();
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
                    <i class="fa-solid fa-cow text-danger me-2"></i> Live Animal Purchasing & Intake
                </h4>
                <p class="text-muted small mb-0">Record livestock intake (Cows, Bulls, Goats, Sheep), live weighing, and slaughter processing.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/MeatShop/animals/create.php" class="btn btn-meat rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Buy Live Animal
                </a>
                <a href="/MeatShop/processing/create_batch.php" class="btn btn-outline-dark rounded-pill px-3 bg-white shadow-sm">
                    <i class="fa-solid fa-knife me-1 text-danger"></i> Slaughter & Cut Processing
                </a>
            </div>
        </div>

        <div class="card card-custom overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-xs text-uppercase">
                        <tr>
                            <th>Ear Tag / ID</th>
                            <th>Animal Type</th>
                            <th class="d-none d-md-table-cell">Supplier</th>
                            <th class="d-none d-lg-table-cell">Date</th>
                            <th class="text-end">Live Weight</th>
                            <th class="text-end d-none d-md-table-cell">Rate / KG</th>
                            <th class="text-end">Total Cost</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($animals)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-cow fs-1 d-block mb-2 text-black-50"></i>
                                    No live animals recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($animals as $a): ?>
                            <tr>
                                <td class="fw-bold text-danger font-monospace">
                                    <i class="fa-solid fa-tag me-1 text-muted"></i><?= htmlspecialchars($a['tag_id']) ?>
                                    <div class="small text-muted d-md-none fw-normal"><?= htmlspecialchars($a['supplier_name']) ?></div>
                                </td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($a['animal_type']) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <strong><?= htmlspecialchars($a['supplier_name']) ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($a['supplier_phone']) ?></small>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= date('d-M-Y', strtotime($a['purchase_date'])) ?></td>
                                <td class="text-end fw-bold text-dark"><?= format_weight($a['live_weight']) ?></td>
                                <td class="text-end d-none d-md-table-cell"><?= format_currency($a['purchase_rate_per_kg']) ?></td>
                                <td class="text-end fw-bold text-danger"><?= format_currency($a['total_animal_cost']) ?></td>
                                <td class="text-center">
                                    <?php if ($a['status'] === 'purchased'): ?>
                                        <span class="badge badge-subtle-info">Live on Hand</span>
                                    <?php elseif ($a['status'] === 'slaughtered'): ?>
                                        <span class="badge badge-subtle-warning">Slaughtered</span>
                                    <?php else: ?>
                                        <span class="badge badge-subtle-success">Processed & Yielded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($a['status'] === 'purchased'): ?>
                                        <a href="/MeatShop/processing/create_batch.php?animal_id=<?= $a['id'] ?>" class="btn btn-sm btn-meat px-2 py-1" title="Process to Meat Cuts">
                                            <i class="fa-solid fa-knife me-1"></i> Process Meat
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Completed</span>
                                    <?php endif; ?>
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
