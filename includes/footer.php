<?php
/**
 * Global Footer & Realtime Modals Component
 */
?>
<?php 
$footer_current_page = basename($_SERVER['PHP_SELF'] ?? '');
$footer_current_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$is_pos_page = defined('IN_POS') || $footer_current_dir === 'pos' || strpos($_SERVER['REQUEST_URI'] ?? '', '/pos/') !== false;
if (!$is_pos_page): 
?>
        <footer class="mt-auto py-3 bg-white border-top text-center text-muted fs-sm">
            <div class="container-fluid d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(get_setting('shop_name', 'Meat Shop System')) ?></strong>. All rights reserved.</span>
                <span class="d-none d-sm-inline">Halal Meat Inventory & POS System v2.0 | Currency: <?= htmlspecialchars(get_setting('currency_code', 'PKR')) ?></span>
            </div>
        </footer>
    </div> <!-- /#content-wrapper -->
<?php endif; ?>

    <?php if (!$is_pos_page): ?>
    <!-- Mobile Bottom Quick Navigation Bar -->
    <nav class="mobile-bottom-nav d-lg-none position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg d-flex justify-content-around py-2" style="z-index: 1030;">
        <a href="/MeatShop/index.php" class="text-center text-decoration-none <?= ($footer_current_page == 'index.php' && $footer_current_dir == 'MeatShop') ? 'text-danger fw-bold' : 'text-muted' ?>">
            <i class="fa-solid fa-gauge-high fs-5 d-block"></i>
            <span style="font-size: 0.68rem;">Dashboard</span>
        </a>
        <a href="/MeatShop/pos/index.php" class="text-center text-decoration-none text-danger fw-bold">
            <div class="rounded-circle bg-danger text-white d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px; margin-top: -14px;">
                <i class="fa-solid fa-cash-register fs-6"></i>
            </div>
            <span class="d-block" style="font-size: 0.68rem;">POS</span>
        </a>
        <a href="/MeatShop/inventory/index.php" class="text-center text-decoration-none <?= ($footer_current_dir == 'inventory') ? 'text-danger fw-bold' : 'text-muted' ?>">
            <i class="fa-solid fa-boxes-stacked fs-5 d-block"></i>
            <span style="font-size: 0.68rem;">Stock</span>
        </a>
        <a href="/MeatShop/customers/index.php" class="text-center text-decoration-none <?= ($footer_current_dir == 'customers') ? 'text-danger fw-bold' : 'text-muted' ?>">
            <i class="fa-solid fa-users fs-5 d-block"></i>
            <span style="font-size: 0.68rem;">Khata</span>
        </a>
        <a href="javascript:void(0)" class="text-center text-decoration-none text-muted" id="mobileBottomMenuBtn">
            <i class="fa-solid fa-bars fs-5 d-block"></i>
            <span style="font-size: 0.68rem;">Menu</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- JQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5.3 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Global Layout & Realtime Dynamic Adding Script -->
    <script>
    $(document).ready(function() {
        // Universal Sidebar Toggle (Desktop collapse & Mobile drawer)
        $('#sidebarToggle, #mobileBottomMenuBtn').on('click', function(e) {
            e.preventDefault();
            if ($(window).width() < 992) {
                $('#sidebar').toggleClass('show');
                $('#sidebarOverlay').toggleClass('show');
            } else {
                $('body').toggleClass('sidebar-collapsed');
                try {
                    localStorage.setItem('sidebar_collapsed_meatshop', $('body').hasClass('sidebar-collapsed') ? '1' : '0');
                } catch(e) {}
            }
        });

        $('#sidebarOverlay').on('click', function() {
            $('#sidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
        });

        // Auto close sidebar on mobile when nav link is clicked
        if ($(window).width() < 992) {
            $('#sidebar .nav-link').on('click', function() {
                $('#sidebar').removeClass('show');
                $('#sidebarOverlay').removeClass('show');
            });
        }

        // Restore desktop collapse preference
        try {
            if ($(window).width() >= 992 && localStorage.getItem('sidebar_collapsed_meatshop') === '1') {
                $('body').addClass('sidebar-collapsed');
            }
        } catch(e) {}

        // Auto hide alerts after 4 seconds
        setTimeout(function() {
            $('.alert-dismissible').fadeOut('slow');
        }, 4000);
    });

    // Helper Toast notification
    function showToast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
    }

    // Generic Realtime Dynamic Entity Creation (Category, Unit, Cut Type, Bank, Payment Method, Supplier, Customer, etc.)
    function submitQuickAdd(formId, targetSelectId, apiUrl) {
        const form = $('#' + formId);
        const data = form.serialize();

        $.ajax({
            url: apiUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    showToast('success', res.message || 'Added successfully');
                    
                    // If target select exists, append and select
                    if (targetSelectId && $('#' + targetSelectId).length) {
                        const newOption = new Option(res.data.text, res.data.id, true, true);
                        $('#' + targetSelectId).append(newOption).trigger('change');
                    }

                    // Reset form and close modal
                    form[0].reset();
                    form.closest('.modal').modal('hide');
                } else {
                    Swal.fire('Error', res.message || 'Failed to save', 'error');
                }
            },
            error: function(err) {
                Swal.fire('Error', 'Server communication failure', 'error');
            }
        });
    }
    </script>

    <!-- ==================== REALTIME MODALS (NO HARDCODING) ==================== -->

    <!-- 1. Realtime Modal: Add Category -->
    <div class="modal fade" id="quickAddCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Meat Category</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickCategory" onsubmit="event.preventDefault(); submitQuickAdd('formQuickCategory', window.currentQuickSelectTarget || 'category_id', '/MeatShop/api/quick_add.php?entity=category');">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" class="form-control" placeholder="e.g. Camel Meat, Duck, Rabbit" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Icon (FontAwesome)</label>
                            <input type="text" name="icon" class="form-control" value="fa-drumstick-bite" placeholder="fa-drumstick-bite">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. Realtime Modal: Add Cut Type -->
    <div class="modal fade" id="quickAddCutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Meat Cut Type</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickCut" onsubmit="event.preventDefault(); submitQuickAdd('formQuickCut', window.currentQuickSelectTarget || 'cut_type_id', '/MeatShop/api/quick_add.php?entity=cut_type');">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Cut Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="cut_name" class="form-control" placeholder="e.g. Ribeye, Shank, Strip, Brain" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Brief note...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Add Cut Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Realtime Modal: Add Unit -->
    <div class="modal fade" id="quickAddUnitModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Measuring Unit</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickUnit" onsubmit="event.preventDefault(); submitQuickAdd('formQuickUnit', window.currentQuickSelectTarget || 'unit_id', '/MeatShop/api/quick_add.php?entity=unit');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Unit Name <span class="text-danger">*</span></label>
                            <input type="text" name="unit_name" class="form-control" placeholder="e.g. Pound, Ounce, Tray" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Unit Code <span class="text-danger">*</span></label>
                            <input type="text" name="unit_code" class="form-control" placeholder="e.g. LBS, OZ, TRY" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Conversion to 1 KG</label>
                            <input type="number" step="0.0001" name="conversion_factor" class="form-control" value="1.0000" placeholder="1.0000" required>
                            <small class="text-muted">Ratio relative to 1 KG (e.g. 1 Lb = 0.4536)</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Add Unit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 4. Realtime Modal: Add Payment Method -->
    <div class="modal fade" id="quickAddPaymentMethodModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Payment Method</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickPayMethod" onsubmit="event.preventDefault(); submitQuickAdd('formQuickPayMethod', window.currentQuickSelectTarget || 'payment_method_id', '/MeatShop/api/quick_add.php?entity=payment_method');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Method Name <span class="text-danger">*</span></label>
                            <input type="text" name="method_name" class="form-control" placeholder="e.g. SadaPay, Nayapay, Raast" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Type</label>
                            <select name="method_type" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="mobile_wallet">Mobile Wallet</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Save Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 5. Realtime Modal: Add Bank Account -->
    <div class="modal fade" id="quickAddBankAccountModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Bank / Wallet Account</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickBank" onsubmit="event.preventDefault(); submitQuickAdd('formQuickBank', window.currentQuickSelectTarget || 'bank_account_id', '/MeatShop/api/quick_add.php?entity=bank_account');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Bank / Wallet Name <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. Allied Bank, SadaPay, MCB" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Account Title <span class="text-danger">*</span></label>
                            <input type="text" name="account_title" class="form-control" placeholder="e.g. Al-Barakah Meat Store" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <label class="form-label small fw-bold">Account / IBAN Number <span class="text-danger">*</span></label>
                                <input type="text" name="account_number" class="form-control" placeholder="Account # / IBAN" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold">Branch Code</label>
                                <input type="text" name="branch_code" class="form-control" placeholder="0123">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Initial Balance</label>
                            <input type="number" step="0.01" name="current_balance" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Add Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 6. Realtime Modal: Add Expense Category -->
    <div class="modal fade" id="quickAddExpenseCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-1"></i> New Expense Category</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickExpCat" onsubmit="event.preventDefault(); submitQuickAdd('formQuickExpCat', window.currentQuickSelectTarget || 'category_id', '/MeatShop/api/quick_add.php?entity=expense_category');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" class="form-control" placeholder="e.g. Knife Sharpening, Generator Fuel" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Details...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 7. Realtime Modal: Add Customer -->
    <div class="modal fade" id="quickAddCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-1"></i> Add New Customer (Khata)</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickCustomer" onsubmit="event.preventDefault(); submitQuickAdd('formQuickCustomer', window.currentQuickSelectTarget || 'customer_id', '/MeatShop/api/quick_add.php?entity=customer');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Customer / Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" placeholder="e.g. Shinwari Dera, Imran Khan" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" placeholder="03001234567" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Customer Type</label>
                                <select name="customer_type" class="form-select">
                                    <option value="walk_in">Walk-in</option>
                                    <option value="regular" selected>Regular VIP</option>
                                    <option value="restaurant">Restaurant</option>
                                    <option value="hotel">Hotel</option>
                                    <option value="caterer">Caterer</option>
                                    <option value="wholesale">Wholesale</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Credit Limit (<?= htmlspecialchars(get_setting('currency_symbol', 'Rs.')) ?>)</label>
                                <input type="number" step="0.01" name="credit_limit" class="form-control" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Opening Balance</label>
                                <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Address / Location</label>
                            <input type="text" name="address" class="form-control" placeholder="Street / Market address...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 8. Realtime Modal: Add Supplier -->
    <div class="modal fade" id="quickAddSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-truck-field me-1"></i> Add New Meat Supplier</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formQuickSupplier" onsubmit="event.preventDefault(); submitQuickAdd('formQuickSupplier', window.currentQuickSelectTarget || 'supplier_id', '/MeatShop/api/quick_add.php?entity=supplier');">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Supplier / Farm Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" placeholder="e.g. Kasur Cattle Farm, Malik Meat Trader" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" placeholder="03001234567" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Supplier Type</label>
                                <select name="supplier_type" class="form-select">
                                    <option value="farm">Livestock Farm</option>
                                    <option value="slaughterhouse">Slaughterhouse</option>
                                    <option value="wholesaler" selected>Wholesaler Trader</option>
                                    <option value="feed_trader">Feed / Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Opening Balance (Owed to Supplier)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Farm / City address...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-3">Save Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($extra_scripts) && is_array($extra_scripts)): ?>
        <?php foreach ($extra_scripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
