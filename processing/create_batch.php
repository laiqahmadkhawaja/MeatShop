<?php
/**
 * Create Processing & Slaughter Breakdown Batch
 * Meat Shop Management System
 */

$page_title = 'Slaughter & Cutting Yield Breakdown';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$branch_id = get_current_branch_id();
$pre_animal_id = (int)($_GET['animal_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_no = trim($_POST['batch_no'] ?? '');
    $live_animal_id = !empty($_POST['live_animal_id']) ? (int)$_POST['live_animal_id'] : null;
    $input_weight = (float)($_POST['input_weight'] ?? 0.0);
    $processing_date = $_POST['processing_date'] ?? date('Y-m-d');
    $labor_charges = (float)($_POST['labor_charges'] ?? 0.0);
    $other_costs = (float)($_POST['other_costs'] ?? 0.0);
    $responsible_employee = trim($_POST['responsible_employee'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $out_prod = $_POST['out_product_id'] ?? [];
    $out_type = $_POST['out_type'] ?? [];
    $out_weight = $_POST['out_weight'] ?? [];

    if ($input_weight <= 0 || empty($out_prod)) {
        set_flash_message('danger', 'Please enter input weight and at least one cut output.');
    } else {
        try {
            $pdo->beginTransaction();

            if (empty($batch_no)) {
                $seq = $pdo->query("SELECT COUNT(id) FROM processing_batches")->fetchColumn() + 1;
                $batch_no = "PROC-" . date('Ymd') . "-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }

            // Determine animal base cost
            $animal_base_cost = 0.00;
            if ($live_animal_id) {
                $a_stmt = $pdo->prepare("SELECT total_animal_cost FROM live_animals WHERE id = ?");
                $a_stmt->execute([$live_animal_id]);
                $animal_base_cost = (float)$a_stmt->fetchColumn();
            }

            $total_processing_cost = $animal_base_cost + $labor_charges + $other_costs;

            // Compute output weights and categories
            $total_output_weight = 0.000;
            $saleable_meat_weight = 0.000;
            $bone_weight = 0.000;
            $organ_weight = 0.000;
            $waste_weight = 0.000;

            $valid_outputs = [];

            for ($i = 0; $i < count($out_prod); $i++) {
                $pid = (int)$out_prod[$i];
                if ($pid <= 0) continue;

                $type = $out_type[$i] ?? 'saleable_meat';
                $wt = (float)($out_weight[$i] ?? 0.0);

                $total_output_weight += $wt;
                if ($type === 'saleable_meat') {
                    $saleable_meat_weight += $wt;
                } elseif ($type === 'bone') {
                    $bone_weight += $wt;
                } elseif ($type === 'organ') {
                    $organ_weight += $wt;
                } elseif ($type === 'waste') {
                    $waste_weight += $wt;
                }

                $valid_outputs[] = [
                    'product_id' => $pid,
                    'type' => $type,
                    'weight' => $wt
                ];
            }

            // Calculate yield percentage
            $yield_pct = ($input_weight > 0) ? (($saleable_meat_weight / $input_weight) * 100.0) : 0.00;

            // Insert Batch
            $stmt = $pdo->prepare("INSERT INTO processing_batches (batch_no, branch_id, live_animal_id, input_weight, processing_date, labor_charges, other_costs, total_processing_cost, total_output_weight, saleable_meat_weight, bone_weight, organ_byproduct_weight, waste_weight, yield_percentage, responsible_employee, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->execute([
                $batch_no, $branch_id, $live_animal_id, $input_weight, $processing_date, $labor_charges,
                $other_costs, $total_processing_cost, $total_output_weight, $saleable_meat_weight, $bone_weight,
                $organ_weight, $waste_weight, $yield_pct, $responsible_employee, $notes
            ]);
            $batch_id = $pdo->lastInsertId();

            // Cost Allocation across outputs:
            // Recoverable by-products (bones/organs) take standard nominal rate, remaining total animal cost is allocated to prime saleable meat!
            $out_ins = $pdo->prepare("INSERT INTO processing_outputs (processing_batch_id, product_id, output_type, output_weight, allocated_cost_per_kg, total_allocated_cost)
                VALUES (?, ?, ?, ?, ?, ?)");

            // Effective cost per KG of saleable meat:
            $cost_per_saleable_kg = ($saleable_meat_weight > 0) ? ($total_processing_cost / $saleable_meat_weight) : 0.00;

            foreach ($valid_outputs as $vo) {
                $unit_cost = ($vo['type'] === 'saleable_meat') ? $cost_per_saleable_kg : ($cost_per_saleable_kg * 0.3); // Nominal for bones/byproducts
                $line_alloc = $vo['weight'] * $unit_cost;

                $out_ins->execute([
                    $batch_id, $vo['product_id'], $vo['type'], $vo['weight'], $unit_cost, $line_alloc
                ]);

                // Auto credit stock to product
                if ($vo['type'] !== 'waste') {
                    update_product_stock($pdo, $branch_id, $vo['product_id'], $vo['weight'], $unit_cost, 'processing_in', $batch_id, 'processing_batches', "Batch #$batch_no yield");
                }
            }

            // Update live animal status if source was animal
            if ($live_animal_id) {
                $pdo->prepare("UPDATE live_animals SET status = 'processed' WHERE id = ?")->execute([$live_animal_id]);
            }

            log_audit('create_processing_batch', 'processing_batches', $batch_id, ['batch_no' => $batch_no, 'yield' => $yield_pct]);

            $pdo->commit();
            set_flash_message('success', "Batch #$batch_no completed! Yield calculated at " . number_format($yield_pct, 1) . "%. Stock updated.");
            header("Location: /MeatShop/processing/view_batch.php?id=" . $batch_id);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Fetch available live animals
$animals = $pdo->query("SELECT id, tag_id, animal_type, live_weight, total_animal_cost FROM live_animals WHERE status = 'purchased' ORDER BY id DESC")->fetchAll();

// Fetch products for outputs
$products = $pdo->query("SELECT id, product_name, product_code, unit_code FROM products p JOIN units u ON p.selling_unit_id = u.id WHERE p.status = 'active' ORDER BY product_name ASC")->fetchAll();
?>

<div id="content-wrapper">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-fluid p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="fa-solid fa-knife text-danger me-2"></i> Slaughter & Meat Cutting Yield Breakdown
                </h4>
                <p class="text-muted small mb-0">Record animal slaughter, individual cut weights, bones, organs, and automated yield %.</p>
            </div>
            <a href="/MeatShop/processing/index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>

        <form method="POST" action="" id="processingForm">
            <div class="row g-4">
                <!-- Batch Header & Source -->
                <div class="col-12 col-lg-4">
                    <div class="card card-custom p-4 mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Intake Source & Batch Info</h6>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Live Animal Source (Optional)</label>
                            <select name="live_animal_id" id="selLiveAnimal" class="form-select" onchange="onAnimalSelect(this)">
                                <option value="" data-weight="0" data-cost="0">None (Bulk Carcass)</option>
                                <?php foreach ($animals as $an): ?>
                                    <option value="<?= $an['id'] ?>" data-weight="<?= $an['live_weight'] ?>" data-cost="<?= $an['total_animal_cost'] ?>" <?= $pre_animal_id == $an['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($an['animal_type']) ?> - Tag #<?= htmlspecialchars($an['tag_id']) ?> (<?= format_weight($an['live_weight']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Total Input Live/Carcass Weight (KG) <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" name="input_weight" id="batchInputWeight" class="form-control form-control-lg fw-bold text-center" placeholder="e.g. 250.000" oninput="recalcYield()" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Processing Date</label>
                            <input type="date" name="processing_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Butcher Labor (Rs)</label>
                                <input type="number" step="0.01" name="labor_charges" id="procLabor" class="form-control" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Other Fees (Rs)</label>
                                <input type="number" step="0.01" name="other_costs" id="procOther" class="form-control" value="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Responsible Head Butcher</label>
                            <input type="text" name="responsible_employee" class="form-control" placeholder="e.g. Master Butcher Asif">
                        </div>
                    </div>

                    <!-- Live Yield % Analytics Widget -->
                    <div class="card card-custom p-4 bg-dark text-white text-center">
                        <small class="text-white-50 text-uppercase fw-bold d-block mb-1">Calculated Saleable Meat Yield</small>
                        <h2 class="fw-extrabold text-success mb-1" id="liveYieldDisplay">0.0%</h2>
                        <small class="text-muted d-block" id="yieldFormulaDesc">Formula: Saleable Meat / Live Weight × 100</small>
                        <hr class="border-secondary my-3">
                        <div class="d-flex justify-content-between small text-white-50">
                            <span>Input Weight: <strong class="text-white" id="statInput">0.000 KG</strong></span>
                            <span>Output Meat: <strong class="text-white" id="statSaleable">0.000 KG</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Meat Cuts Output Table -->
                <div class="col-12 col-lg-8">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Yielded Meat Cuts & Recoverable By-products</h6>
                                <small class="text-muted">Record weights for prime meat, bone cuts, organs, and wastage.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="addOutputRow()">
                                <i class="fa-solid fa-plus me-1"></i> Add Cut Row
                            </button>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle" id="outputTable" style="min-width: 560px;">
                                <thead class="table-light fs-xs text-uppercase">
                                    <tr>
                                        <th style="width: 42%;">Target Meat Cut / Product</th>
                                        <th style="width: 28%;">Output Category</th>
                                        <th class="text-end" style="width: 22%;">Weight (KG)</th>
                                        <th class="text-center" style="width: 8%;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="out-row">
                                        <td>
                                            <select name="out_product_id[]" class="form-select" required>
                                                <option value="">Select Target Product</option>
                                                <?php foreach ($products as $pr): ?>
                                                    <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="out_type[]" class="form-select out-type" onchange="recalcYield()">
                                                <option value="saleable_meat" selected>Saleable Meat (Prime Cut)</option>
                                                <option value="bone">Bone / Ribs (Recoverable)</option>
                                                <option value="organ">Organ / Liver / Paya</option>
                                                <option value="waste">Fat Trimming / Scrap</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" step="0.001" name="out_weight[]" class="form-control text-end out-wt fw-bold text-dark" placeholder="0.000" oninput="recalcYield()" required>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-light text-danger" onclick="removeOutRow(this)"><i class="fa-solid fa-trash"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Reconciliation Bar -->
                        <div class="p-3 bg-light rounded-3 mb-4">
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <small class="text-muted d-block">Total Recorded Output</small>
                                    <strong class="fs-6 text-dark" id="lblTotalOutWt">0.000 KG</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Prime Meat Yielded</small>
                                    <strong class="fs-6 text-success" id="lblSaleableWt">0.000 KG</strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted d-block">Unaccounted Shrinkage / Loss</small>
                                    <strong class="fs-6 text-danger" id="lblLossWt">0.000 KG</strong>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-meat w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-check-double me-1"></i> Finalize Batch & Credit Meat Inventory
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function onAnimalSelect(el) {
    const wt = $(el).find('option:selected').data('weight') || 0;
    if (wt > 0) {
        $('#batchInputWeight').val(wt);
        recalcYield();
    }
}

function recalcYield() {
    const inputWt = parseFloat($('#batchInputWeight').val()) || 0;
    let totalOut = 0;
    let saleable = 0;

    $('.out-row').each(function() {
        const type = $(this).find('.out-type').val();
        const wt = parseFloat($(this).find('.out-wt').val()) || 0;
        totalOut += wt;
        if (type === 'saleable_meat') {
            saleable += wt;
        }
    });

    const yieldPct = (inputWt > 0) ? ((saleable / inputWt) * 100) : 0;
    const loss = Math.max(0, inputWt - totalOut);

    $('#liveYieldDisplay').text(yieldPct.toFixed(1) + '%');
    $('#statInput').text(inputWt.toFixed(3) + ' KG');
    $('#statSaleable').text(saleable.toFixed(3) + ' KG');
    $('#lblTotalOutWt').text(totalOut.toFixed(3) + ' KG');
    $('#lblSaleableWt').text(saleable.toFixed(3) + ' KG');
    $('#lblLossWt').text(loss.toFixed(3) + ' KG');
}

function addOutputRow() {
    const html = `
        <tr class="out-row">
            <td>
                <select name="out_product_id[]" class="form-select" required>
                    <option value="">Select Target Product</option>
                    <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['product_name']) ?> (<?= htmlspecialchars($pr['product_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="out_type[]" class="form-select out-type" onchange="recalcYield()">
                    <option value="saleable_meat">Saleable Meat (Prime Cut)</option>
                    <option value="bone">Bone / Ribs (Recoverable)</option>
                    <option value="organ">Organ / Liver / Paya</option>
                    <option value="waste">Fat Trimming / Scrap</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.001" name="out_weight[]" class="form-control text-end out-wt fw-bold text-dark" placeholder="0.000" oninput="recalcYield()" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-light text-danger" onclick="removeOutRow(this)"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>
    `;
    $('#outputTable tbody').append(html);
}

function removeOutRow(btn) {
    if ($('.out-row').length > 1) {
        $(btn).closest('tr').remove();
        recalcYield();
    } else {
        Swal.fire('Notice', 'At least one output cut is required.', 'info');
    }
}

$(document).ready(function() {
    recalcYield();
});
</script>
