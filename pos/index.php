<?php
/**
 * POS & Fast Billing Screen
 * Meat Shop Management System
 */

define('IN_POS', true);

$page_title = 'POS Terminal & Weight-Based Billing';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';

require_login();
require_permission('pos_access');

$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

// Verify active shift
$active_shift = get_active_shift($pdo, $branch_id, $user_id);

// Fetch categories for filter pills
$categories = $pdo->query("SELECT id, category_name, icon FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();

// Fetch payment methods
$payment_methods = $pdo->query("SELECT id, method_name, method_type FROM payment_methods WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

// Fetch bank accounts
$bank_accounts = $pdo->query("SELECT id, bank_name, account_title, account_number FROM bank_accounts WHERE status = 'active' ORDER BY bank_name ASC")->fetchAll();

// Fetch units
$units = $pdo->query("SELECT id, unit_name, unit_code, conversion_factor, is_weight FROM units ORDER BY id ASC")->fetchAll();

// Fetch customers
$customers = $pdo->query("SELECT id, customer_name, phone, customer_type, current_balance, credit_limit FROM customers WHERE status = 'active' ORDER BY customer_name ASC")->fetchAll();

// Settings
$default_tare = (float)get_setting('default_tare_weight', 0.050);
$currency = get_setting('currency_symbol', 'Rs.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS Terminal | <?= htmlspecialchars(get_setting('shop_name', 'Meat Shop System')) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --meat-crimson: #991b1b;
            --meat-accent: #dc2626;
            --pos-header-height: 56px;
        }

        .btn-meat {
            background-color: #dc2626 !important;
            border-color: #dc2626 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
        }
        .btn-meat:hover, .btn-meat:focus, .btn-meat:active {
            background-color: #b91c1c !important;
            border-color: #b91c1c !important;
            color: #ffffff !important;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            overflow-x: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        .pos-header {
            height: var(--pos-header-height);
            background: #1e1b18;
            color: white;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
        }

        /* POS Layout */
        .pos-container {
            display: flex;
            flex: 1;
            height: calc(100vh - var(--pos-header-height));
            overflow: hidden;
        }

        /* Left Catalog Panel */
        .catalog-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-right: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        /* Right Cart & Checkout Panel */
        .checkout-panel {
            width: 440px;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            box-shadow: -4px 0 15px rgba(0,0,0,0.05);
            z-index: 999;
        }

        /* Product Cards Grid */
        .products-grid {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            grid-gap: 12px;
            align-content: start;
        }

        .product-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            user-select: none;
        }
        .product-card:hover {
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220, 38, 38, 0.12);
        }
        .product-card:active {
            transform: scale(0.98);
        }

        .product-icon-wrap {
            width: 42px;
            height: 42px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 8px;
        }

        /* Cart Items List */
        .cart-items-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .cart-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
            transition: background 0.15s;
        }
        .cart-row:hover {
            background: #f1f5f9;
        }

        /* Weigh Scale Visual Display */
        .scale-indicator-box {
            background: #0f172a;
            color: #38bdf8;
            font-family: 'Courier New', Courier, monospace;
            border-radius: 10px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 2px solid #1e293b;
        }
        .scale-live-weight {
            font-size: 1.8rem;
            font-weight: 800;
            color: #4ade80;
            text-shadow: 0 0 10px rgba(74, 222, 128, 0.4);
        }

        /* Mobile Segmented Toggle Header */
        .mobile-pos-nav {
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
        }
        .mobile-pos-nav .btn {
            border-radius: 0;
            font-weight: 700;
            font-size: 0.88rem;
            color: #64748b;
            border-bottom: 3px solid transparent;
        }
        .mobile-pos-nav .btn.active {
            color: #dc2626;
            border-bottom: 3px solid #dc2626;
            background: #fef2f2;
        }

        /* Floating Sticky Cart Bar for Mobile */
        #mobileFloatingCartBar {
            box-shadow: 0 -4px 16px rgba(0,0,0,0.2);
            transition: all 0.25s ease-in-out;
        }

        /* Camera Barcode Viewfinder & Reticle Styling */
        .scanner-viewport-wrapper {
            position: relative;
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            border-radius: 14px;
            overflow: hidden;
            background: #090d16;
            aspect-ratio: 4/3;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }

        #barcodeScannerView {
            width: 100% !important;
            height: 100% !important;
            overflow: hidden;
        }

        #barcodeScannerView video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 12px;
        }

        /* Custom Overlaid Aiming Reticle */
        .scanner-reticle {
            position: absolute;
            width: 82%;
            height: 64%;
            border: 2px dashed rgba(255, 255, 255, 0.45);
            border-radius: 12px;
            pointer-events: none;
            z-index: 10;
            transition: all 0.25s ease;
        }

        .scanner-reticle.scan-matched {
            border-color: #22c55e !important;
            background: rgba(34, 197, 94, 0.2) !important;
            box-shadow: 0 0 25px rgba(34, 197, 94, 0.6);
        }

        .scanner-reticle.scan-failed {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.25) !important;
        }

        /* Corner Reticle Brackets */
        .reticle-corner {
            position: absolute;
            width: 22px;
            height: 22px;
            border-color: #dc2626;
            border-style: solid;
            pointer-events: none;
        }
        .reticle-corner.top-left { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-top-left-radius: 6px; }
        .reticle-corner.top-right { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-top-right-radius: 6px; }
        .reticle-corner.bottom-left { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-bottom-left-radius: 6px; }
        .reticle-corner.bottom-right { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-bottom-right-radius: 6px; }

        /* Animated Laser Scan Line */
        .scanner-laser {
            position: absolute;
            left: 4%;
            right: 4%;
            height: 3px;
            background: linear-gradient(90deg, rgba(220,38,38,0) 0%, #dc2626 50%, rgba(220,38,38,0) 100%);
            box-shadow: 0 0 12px #dc2626, 0 0 24px rgba(220, 38, 38, 0.8);
            animation: laserScan 1.8s infinite ease-in-out alternate;
            pointer-events: none;
        }

        @keyframes laserScan {
            0% { top: 8%; opacity: 0.9; }
            50% { opacity: 1; }
            100% { top: 90%; opacity: 0.9; }
        }

        /* Responsive Breakpoints */
        @media (max-width: 991.98px) {
            body {
                height: auto;
                min-height: 100vh;
            }
            .pos-container {
                display: block;
                height: auto;
                overflow: visible;
                padding-bottom: 75px;
            }
            .catalog-panel {
                width: 100%;
                border-right: none;
            }
            .checkout-panel {
                width: 100%;
                display: none;
                min-height: calc(100vh - 110px);
            }
            .checkout-panel.mobile-active {
                display: flex !important;
            }
            .catalog-panel.mobile-hidden {
                display: none !important;
            }
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                padding: 0.75rem;
                grid-gap: 8px;
            }
            .pos-header {
                padding: 0 0.5rem;
            }
            .pos-header h5 {
                font-size: 0.95rem;
            }
            .scale-indicator-box {
                padding: 6px 10px;
            }
            .scale-live-weight {
                font-size: 1.4rem;
            }
            .scale-indicator-box .btn {
                font-size: 0.75rem;
                padding: 2px 6px;
            }
            /* Form input mobile zoom prevention */
            input, select, textarea {
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>

<!-- Top POS Header Bar -->
<header class="pos-header">
    <div class="d-flex align-items-center gap-3">
        <a href="/MeatShop/index.php" class="btn btn-outline-light btn-sm rounded-pill px-3 py-1">
            <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
        </a>
        <h5 class="mb-0 fw-bold d-none d-sm-inline">
            <i class="fa-solid fa-drumstick-bite text-danger me-2"></i> POS Weighing & Billing
        </h5>
    </div>

    <!-- Active Shift, Scale & Camera Scanner Status -->
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-meat rounded-pill px-3 py-1 shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#barcodeScannerModal" onclick="window.barcodeScanner && window.barcodeScanner.openModal()" title="Open Camera Barcode Scanner (F3)">
            <i class="fa-solid fa-camera"></i> <span class="d-none d-sm-inline">Scan Barcode</span>
        </button>

        <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 py-1" id="connectScaleBtn">
            <i class="fa-solid fa-scale-balanced me-1"></i> Connect Scale
        </button>

        <?php if ($active_shift): ?>
            <span class="badge bg-success py-2 px-3 rounded-pill">
                <i class="fa-solid fa-circle-check me-1"></i> Shift #<?= $active_shift['id'] ?>
            </span>
        <?php else: ?>
            <button class="btn btn-sm btn-danger rounded-pill px-3 py-1" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> Shift Closed (Click to Open)
            </button>
        <?php endif; ?>

        <a href="/MeatShop/reports/sales.php" target="_blank" class="btn btn-sm btn-light rounded-pill px-3 py-1 d-none d-md-inline">
            <i class="fa-solid fa-history me-1"></i> Sales Log
        </a>
    </div>
</header>

<!-- Mobile Segmented Navigation Bar -->
<div class="mobile-pos-nav d-lg-none d-flex shadow-sm">
    <button type="button" class="btn flex-fill py-2 active" id="btnMobileShowCatalog" onclick="switchToMobileCatalog()">
        <i class="fa-solid fa-drumstick-bite me-1"></i> Meat Products
    </button>
    <button type="button" class="btn flex-fill py-2 position-relative" id="btnMobileShowCart" onclick="switchToMobileCart()">
        <i class="fa-solid fa-cart-shopping me-1"></i> Bill & Cart
        <span class="badge bg-danger rounded-pill ms-1" id="mobileCartBadge">0</span>
    </button>
</div>

<div class="pos-container">
    <!-- LEFT PANEL: Category Tabs, Search Bar, Meat Product Cards -->
    <div class="catalog-panel" id="posCatalogPanel">
        <!-- Search & Tare Bar -->
        <div class="p-3 bg-white border-bottom shadow-sm">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="posProductSearch" class="form-control border-start-0 border-end-0" placeholder="Search meat or barcode (F2)..." autofocus>
                        <button class="btn btn-meat d-flex align-items-center gap-1 px-3" type="button" id="btnOpenBarcodeScanner" data-bs-toggle="modal" data-bs-target="#barcodeScannerModal" onclick="window.barcodeScanner && window.barcodeScanner.openModal()" title="Scan Barcode via Camera (F3)">
                            <i class="fa-solid fa-camera"></i> <span class="d-none d-sm-inline fw-semibold">Scan</span>
                        </button>
                    </div>
                </div>
                <!-- Scale Simulator / Live Scale Controls -->
                <div class="col-12 col-md-6">
                    <div class="scale-indicator-box">
                        <div>
                            <span class="d-block small text-muted text-uppercase" style="font-size: 0.65rem;">Live Scale (KG)</span>
                            <span class="scale-live-weight" id="scaleDisplayVal">0.000</span>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary text-white py-1 px-2" id="btnTareScale" title="Tare Tray Weight">
                                Tare (<?= $default_tare ?> KG)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning py-1 px-2" id="btnZeroScale" title="Zero Scale">
                                Zero
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success py-1 px-2" id="btnSimulateScale" title="Simulate Scale Input">
                                + Sim
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="d-flex gap-2 overflow-x-auto pt-2 pb-1" style="white-space: nowrap;">
                <button class="btn btn-sm btn-danger rounded-pill px-3 active cat-filter-btn" data-cat="all">
                    All Meats
                </button>
                <?php foreach ($categories as $cat): ?>
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 cat-filter-btn" data-cat="<?= $cat['id'] ?>">
                    <i class="fa-solid <?= htmlspecialchars($cat['icon'] ?: 'fa-drumstick-bite') ?> me-1"></i>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </button>
                <?php endforeach; ?>
                <!-- Quick add category button -->
                <button class="btn btn-sm btn-outline-danger rounded-pill px-2" data-bs-toggle="modal" data-bs-target="#quickAddCategoryModal" title="Add New Category Realtime">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
        </div>

        <!-- Product Cards Grid -->
        <div class="products-grid" id="productsGrid">
            <?php
            $prods_stmt = $pdo->prepare("SELECT p.*, c.category_name, u.unit_code, COALESCE(bs.current_stock, 0) as stock_qty
                FROM products p
                JOIN categories c ON p.category_id = c.id
                JOIN units u ON p.selling_unit_id = u.id
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
                WHERE p.status = 'active'
                ORDER BY p.product_name ASC");
            $prods_stmt->execute([$branch_id]);
            $prods = $prods_stmt->fetchAll();
            ?>
            <?php foreach ($prods as $p): ?>
                <div class="product-card" 
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= htmlspecialchars($p['product_name']) ?>"
                     data-code="<?= htmlspecialchars($p['product_code']) ?>"
                     data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>"
                     data-cat="<?= $p['category_id'] ?>"
                     data-rate="<?= $p['retail_selling_rate'] ?>"
                     data-wholesale="<?= $p['wholesale_selling_rate'] ?>"
                     data-unit="<?= htmlspecialchars($p['unit_code']) ?>"
                     data-unit-id="<?= $p['selling_unit_id'] ?>"
                     data-stock="<?= $p['stock_qty'] ?>"
                     onclick="addProductToCart(this)">
                    <div>
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="product-icon-wrap">
                                <i class="fa-solid fa-drumstick-bite"></i>
                            </div>
                            <span class="badge <?= $p['stock_qty'] <= $p['minimum_stock_level'] ? 'bg-danger' : 'bg-success-subtle text-success' ?> small">
                                <?= number_format($p['stock_qty'], 2) ?> <?= htmlspecialchars($p['unit_code']) ?>
                            </span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($p['product_name']) ?></h6>
                        <small class="text-muted d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($p['category_name']) ?> | <?= htmlspecialchars($p['product_code']) ?></small>
                    </div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="fw-extrabold text-danger fs-6"><?= $currency ?> <?= number_format($p['retail_selling_rate'], 0) ?></span>
                        <small class="text-muted">/<?= htmlspecialchars($p['unit_code']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT PANEL: Current Bill / Cart, Customer Select, Split Payments, Checkout -->
    <div class="checkout-panel" id="posCheckoutPanel">
        <!-- Mobile Back to Catalog Button -->
        <div class="p-2 bg-light border-bottom d-lg-none">
            <button type="button" class="btn btn-sm btn-outline-dark w-100 rounded-pill" onclick="switchToMobileCatalog()">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Meat Products Catalog
            </button>
        </div>
        <!-- Customer & Bill Tabs Bar -->
        <div class="p-3 border-bottom bg-white">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-bold text-dark"><i class="fa-solid fa-cart-shopping text-danger me-1"></i> Current Order</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btnHoldBill" title="Hold Current Bill">
                        <i class="fa-solid fa-pause me-1"></i> Hold
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnResumeBill" title="Resume Held Bills">
                        <i class="fa-solid fa-play me-1"></i> Recall (<span id="heldCount">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="btnClearCart" title="Clear Bill">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </div>

            <!-- Customer Selector with Quick Add Button -->
            <div class="d-flex gap-2">
                <div class="flex-grow-1">
                    <select id="cartCustomerId" class="form-select form-select-sm">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-type="<?= $c['customer_type'] ?>" data-balance="<?= $c['current_balance'] ?>" <?= $c['customer_type'] === 'walk_in' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?> <?= $c['current_balance'] > 0 ? '(Bal: Rs ' . number_format($c['current_balance'], 0) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal" onclick="window.currentQuickSelectTarget='cartCustomerId';" title="Add New Customer Realtime">
                    <i class="fa-solid fa-user-plus"></i>
                </button>
            </div>
        </div>

        <!-- Cart Items List Container -->
        <div class="cart-items-wrapper" id="cartItemsList">
            <div class="text-center py-5 text-muted" id="emptyCartNotice">
                <i class="fa-solid fa-basket-shopping fs-1 text-black-50 mb-2"></i>
                <p class="mb-0 small">No items added yet.<br>Click product card or scan barcode.</p>
            </div>
        </div>

        <!-- Totals & Checkout Section -->
        <div class="p-3 bg-light border-top shadow-lg">
            <!-- Subtotal & Discount -->
            <div class="d-flex justify-content-between text-muted small mb-1">
                <span>Subtotal Weight:</span>
                <span class="fw-bold text-dark" id="lblTotalWeight">0.000 KG</span>
            </div>
            <div class="d-flex justify-content-between text-muted small mb-1">
                <span>Subtotal Amount:</span>
                <span class="fw-bold text-dark" id="lblSubtotal">Rs 0.00</span>
            </div>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-tag text-danger"></i></span>
                        <input type="number" step="0.01" id="cartDiscount" class="form-control" placeholder="Discount" value="0">
                    </div>
                </div>
                <div class="col-6">
                    <select id="cartDiscountType" class="form-select form-select-sm">
                        <option value="fixed">Fixed (Rs)</option>
                        <option value="percent">Percent (%)</option>
                    </select>
                </div>
            </div>

            <!-- Grand Total Display -->
            <div class="p-2 rounded-3 bg-dark text-white d-flex justify-content-between align-items-center mb-3">
                <span class="fw-bold small text-uppercase">Payable Total:</span>
                <h4 class="fw-extrabold text-warning mb-0" id="lblGrandTotal">Rs 0.00</h4>
            </div>

            <!-- Payment Method & Amount Paid -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <select id="cartPaymentMethod" class="form-select form-select-sm">
                        <?php foreach ($payment_methods as $pm): ?>
                            <option value="<?= $pm['id'] ?>" data-type="<?= $pm['method_type'] ?>"><?= htmlspecialchars($pm['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <input type="number" step="0.01" id="cartAmountPaid" class="form-control form-control-sm text-end fw-bold" placeholder="Amount Paid">
                </div>
            </div>

            <!-- Checkout Action Buttons -->
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-meat py-2 fw-bold shadow-sm d-flex justify-content-between align-items-center" id="btnCompleteSale">
                    <span><i class="fa-solid fa-check-circle me-1"></i> COMPLETE SALE (F4)</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Custom Weight & Price Edit -->
<div class="modal fade" id="modalEditItem" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold" id="editModalItemName">Edit Weight & Price</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editItemIndex">
                <div class="mb-2">
                    <label class="form-label small fw-bold">Weight / Qty</label>
                    <input type="number" step="0.001" id="editItemWeight" class="form-control form-control-lg fw-bold text-center" autofocus>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Tare Weight (KG)</label>
                    <input type="number" step="0.001" id="editItemTare" class="form-control text-center" value="0.000">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Rate Per KG / Unit</label>
                    <input type="number" step="0.01" id="editItemRate" class="form-control text-center fw-bold">
                </div>
                <div class="p-2 bg-light rounded text-center">
                    <small class="text-muted d-block">Calculated Total</small>
                    <h5 class="fw-bold text-danger mb-0" id="editItemLineTotal">Rs 0.00</h5>
                </div>
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="btnSaveItemEdit">Update</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Split Payment -->
<div class="modal fade" id="modalSplitPayment" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-coins me-2"></i> Split Payment Options</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Split total between Cash, Bank Account, or Mobile Wallets (JazzCash/Easypaisa):</p>
                <div id="splitPaymentRows">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-dark btn-sm px-3" id="btnApplySplitPay">Apply Split</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Camera Barcode Scanner with Auto-Zoom -->
<div class="modal fade" id="barcodeScannerModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-header border-bottom border-secondary py-2 px-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle bg-danger p-2 text-white d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="fa-solid fa-barcode fs-6"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold mb-0">Camera Barcode Scanner</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Auto-Alignment & Dynamic Zoom</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-warning d-none py-1 px-2" id="btnToggleTorch" title="Flashlight / Torch">
                        <i class="fa-solid fa-bolt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light d-none py-1 px-2" id="btnSwitchCamera" title="Switch Front/Back Camera">
                        <i class="fa-solid fa-camera-rotate"></i>
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            <div class="modal-body p-3 text-center">
                <!-- Camera Viewport Box with Targeting Reticle -->
                <div class="scanner-viewport-wrapper mb-2">
                    <div id="barcodeScannerView"></div>
                    
                    <!-- Overlaid Alignment Guide Reticle -->
                    <div class="scanner-reticle" id="scannerReticleBox">
                        <div class="reticle-corner top-left"></div>
                        <div class="reticle-corner top-right"></div>
                        <div class="reticle-corner bottom-left"></div>
                        <div class="reticle-corner bottom-right"></div>
                        <div class="scanner-laser"></div>
                    </div>

                    <!-- Overlay Target Hint -->
                    <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2 px-3 py-1 bg-black bg-opacity-75 rounded-pill text-white-50" style="font-size: 0.72rem; z-index: 15; pointer-events: none;">
                        <i class="fa-solid fa-crosshairs me-1 text-danger"></i> Align meat barcode inside red brackets
                    </div>
                </div>

                <!-- Zoom Alignment Control Bar -->
                <div class="bg-black bg-opacity-50 p-2 rounded-3 mb-2 border border-secondary border-opacity-50">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-white-50"><i class="fa-solid fa-magnifying-glass-plus me-1"></i>Zoom:</span>
                            <strong class="text-warning fw-bold fs-6" id="scannerZoomDisplay">1.0x</strong>
                            <small class="text-danger fw-semibold" id="scannerZoomModeBadge" style="font-size: 0.7rem;">(Auto Aligning)</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-pill px-2 py-0 fw-bold" id="btnToggleAutoZoom" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-arrows-to-eye me-1"></i> Auto-Zoom: ON
                        </button>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-dark border border-secondary text-white py-0 px-2" id="btnZoomOut" title="Zoom Out">-</button>
                        <input type="range" class="form-range flex-fill" id="scannerZoomSlider" min="1.0" max="3.0" step="0.2" value="1.0">
                        <button type="button" class="btn btn-sm btn-dark border border-secondary text-white py-0 px-2" id="btnZoomIn" title="Zoom In">+</button>
                    </div>
                </div>

                <!-- Live Status Feedback Message -->
                <div id="scannerStatusMsg" class="mb-2" style="min-height: 38px;">
                    <span class="text-white-50 small"><i class="fa-solid fa-spinner fa-spin me-1"></i> Starting camera...</span>
                </div>

                <!-- Recent Scanned Result -->
                <div id="scannerLastScannedBadge" style="min-height: 24px;"></div>
            </div>

            <div class="modal-footer border-top border-secondary py-2 px-3 d-flex justify-content-between align-items-center bg-black bg-opacity-25">
                <div class="form-check form-switch mb-0 text-start">
                    <input class="form-check-input" type="checkbox" id="chkContinuousScan">
                    <label class="form-check-label small text-white-50" for="chkContinuousScan">Continuous Scan</label>
                </div>
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Open Shift Trigger -->
<div class="modal fade" id="openShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form action="/MeatShop/api/shift.php?action=open" method="POST">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-lock-open me-2"></i> Open Cashier Shift</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Opening Cash Float</label>
                        <input type="number" step="0.01" name="opening_cash" class="form-control form-control-lg text-center fw-bold" placeholder="0.00" value="0.00" required autofocus>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Start Shift</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating Sticky Bottom Bar for Mobile View when in Catalog -->
<div id="mobileFloatingCartBar" class="d-lg-none position-fixed bottom-0 start-0 end-0 p-2 bg-dark text-white d-flex justify-content-between align-items-center shadow-lg" style="display: none !important; z-index: 1045;">
    <div class="ps-2">
        <span class="badge bg-danger rounded-pill me-1" id="floatCartCount">0</span>
        <span class="small text-white-50">Total:</span>
        <strong class="text-warning fs-6" id="floatCartTotal">Rs 0.00</strong>
    </div>
    <button type="button" class="btn btn-meat btn-sm rounded-pill px-3 py-1 fw-bold" onclick="switchToMobileCart()">
        View Cart & Pay <i class="fa-solid fa-arrow-right ms-1"></i>
    </button>
</div>

<?php 
$extra_scripts = [
    '/MeatShop/assets/js/scale.js',
    '/MeatShop/assets/js/pos.js',
    '/MeatShop/assets/js/html5-qrcode.min.js',
    '/MeatShop/assets/js/barcode-scanner.js'
];
require_once __DIR__ . '/../includes/footer.php'; 
?>
