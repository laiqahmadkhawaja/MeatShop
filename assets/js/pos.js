/**
 * POS Core Billing & Cart Engine
 * Meat Shop Management System
 */

let cart = [];
let heldBills = JSON.parse(localStorage.getItem('held_bills_meatshop') || '[]');

$(document).ready(function() {
    updateHeldCount();
    renderCart();

    // Category Filter Buttons
    $('.cat-filter-btn').on('click', function() {
        $('.cat-filter-btn').removeClass('active btn-danger').addClass('btn-outline-secondary');
        $(this).addClass('active btn-danger').removeClass('btn-outline-secondary');
        
        const cat = $(this).data('cat');
        filterProducts(cat, $('#posProductSearch').val());
    });

    // Search Input Filter
    $('#posProductSearch').on('keyup', function(e) {
        if (e.key === 'Enter') {
            // Check for direct barcode match
            const query = $(this).val().trim();
            let matched = false;
            $('.product-card').each(function() {
                const code = $(this).data('code').toString().toLowerCase();
                const barcode = ($(this).data('barcode') || '').toString().toLowerCase();
                if (query.toLowerCase() === code || query.toLowerCase() === barcode) {
                    addProductToCart(this);
                    matched = true;
                    $('#posProductSearch').val('');
                    return false;
                }
            });
            if (matched) return;
        }

        const activeCat = $('.cat-filter-btn.active').data('cat') || 'all';
        filterProducts(activeCat, $(this).val());
    });

    // Discount change listeners
    $('#cartDiscount, #cartDiscountType').on('input change', function() {
        calculateTotals();
    });

    // Auto-update amount paid when payment method or grand total changes
    $('#cartPaymentMethod').on('change', function() {
        const type = $(this).find('option:selected').data('type');
        if (type !== 'credit') {
            $('#cartAmountPaid').val($('#lblGrandTotal').text().replace(/[^0-9.]/g, ''));
        }
    });

    // Clear Cart
    $('#btnClearCart').on('click', function() {
        if (cart.length === 0) return;
        Swal.fire({
            title: 'Clear Current Bill?',
            text: 'Are you sure you want to discard all items in the current order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Clear'
        }).then((result) => {
            if (result.isConfirmed) {
                cart = [];
                renderCart();
            }
        });
    });

    // Hold Bill
    $('#btnHoldBill').on('click', function() {
        if (cart.length === 0) {
            Swal.fire('Empty Bill', 'Add products to cart before holding.', 'info');
            return;
        }
        const custName = $('#cartCustomerId option:selected').text();
        heldBills.push({
            id: Date.now(),
            customer_id: $('#cartCustomerId').val(),
            customer_name: custName,
            items: cart,
            discount: $('#cartDiscount').val(),
            discount_type: $('#cartDiscountType').val(),
            date: new Date().toLocaleTimeString()
        });
        localStorage.setItem('held_bills_meatshop', JSON.stringify(heldBills));
        updateHeldCount();
        cart = [];
        renderCart();
        Swal.fire('Bill Held', 'Order placed on hold successfully.', 'success');
    });

    // Resume Bill
    $('#btnResumeBill').on('click', function() {
        if (heldBills.length === 0) {
            Swal.fire('No Held Bills', 'There are no orders on hold.', 'info');
            return;
        }

        let optionsHtml = '';
        heldBills.forEach((hb, idx) => {
            optionsHtml += `<button class="btn btn-outline-dark text-start mb-2 w-100 p-2" onclick="recallHeldBill(${idx})">
                <strong>#${idx + 1} - ${hb.customer_name}</strong><br>
                <small class="text-muted">${hb.items.length} items | Held at ${hb.date}</small>
            </button>`;
        });

        Swal.fire({
            title: 'Recall Held Order',
            html: optionsHtml,
            showConfirmButton: false,
            showCancelButton: true
        });
    });

    // Complete Sale Checkout
    $('#btnCompleteSale').on('click', function() {
        submitCheckout();
    });

    // Keyboard Shortcuts
    $(document).on('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            $('#posProductSearch').focus();
        }
        if (e.key === 'F4') {
            e.preventDefault();
            $('#btnCompleteSale').click();
        }
    });

    // Edit Item Modal Calculation
    $('#editItemWeight, #editItemTare, #editItemRate').on('input', function() {
        const w = parseFloat($('#editItemWeight').val()) || 0;
        const t = parseFloat($('#editItemTare').val()) || 0;
        const r = parseFloat($('#editItemRate').val()) || 0;
        const netW = Math.max(0, w - t);
        const total = netW * r;
        $('#editItemLineTotal').text('Rs ' + total.toFixed(2));
    });

    $('#btnSaveItemEdit').on('click', function() {
        const idx = parseInt($('#editItemIndex').val());
        if (cart[idx]) {
            cart[idx].weight = parseFloat($('#editItemWeight').val()) || 0.001;
            cart[idx].tare = parseFloat($('#editItemTare').val()) || 0.000;
            cart[idx].rate = parseFloat($('#editItemRate').val()) || 0.00;
            const netW = Math.max(0, cart[idx].weight - cart[idx].tare);
            cart[idx].total = netW * cart[idx].rate;
            $('#modalEditItem').modal('hide');
            renderCart();
        }
    });
});

function filterProducts(catId, searchText) {
    const q = (searchText || '').toLowerCase();
    $('.product-card').each(function() {
        const pCat = $(this).data('cat').toString();
        const pName = $(this).data('name').toLowerCase();
        const pCode = $(this).data('code').toString().toLowerCase();
        const pBarcode = ($(this).data('barcode') || '').toString().toLowerCase();

        const matchCat = (catId === 'all' || pCat === catId.toString());
        const matchSearch = (!q || pName.includes(q) || pCode.includes(q) || pBarcode.includes(q));

        if (matchCat && matchSearch) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

function updateHeldCount() {
    $('#heldCount').text(heldBills.length);
}

function recallHeldBill(idx) {
    const hb = heldBills.splice(idx, 1)[0];
    localStorage.setItem('held_bills_meatshop', JSON.stringify(heldBills));
    updateHeldCount();
    cart = hb.items;
    $('#cartCustomerId').val(hb.customer_id);
    $('#cartDiscount').val(hb.discount || 0);
    $('#cartDiscountType').val(hb.discount_type || 'fixed');
    renderCart();
    Swal.close();
}

// Add Product To Cart
function addProductToCart(element) {
    const el = $(element);
    const id = parseInt(el.data('id'));
    const name = el.data('name');
    const code = el.data('code');
    const rate = parseFloat(el.data('rate'));
    const unit = el.data('unit');
    const unitId = parseInt(el.data('unit-id'));

    // Check if live scale reading is available
    let scaleWeight = 0;
    if (window.scaleCtrl && window.scaleCtrl.currentNetWeight > 0) {
        scaleWeight = window.scaleCtrl.currentNetWeight;
    } else {
        scaleWeight = 1.000; // default 1 KG
    }

    // Check if item already in cart
    const existingIndex = cart.findIndex(item => item.product_id === id);
    if (existingIndex > -1) {
        // If scale weight is active, ask or increment
        cart[existingIndex].weight += scaleWeight;
        const netW = Math.max(0, cart[existingIndex].weight - cart[existingIndex].tare);
        cart[existingIndex].total = netW * cart[existingIndex].rate;
    } else {
        const tare = 0.000;
        const netW = Math.max(0, scaleWeight - tare);
        cart.push({
            product_id: id,
            name: name,
            code: code,
            weight: scaleWeight,
            tare: tare,
            rate: rate,
            unit: unit,
            unit_id: unitId,
            total: netW * rate
        });
    }

    renderCart();
}

// Render Cart HTML
function renderCart() {
    const container = $('#cartItemsList');
    if (cart.length === 0) {
        container.html(`
            <div class="text-center py-5 text-muted" id="emptyCartNotice">
                <i class="fa-solid fa-basket-shopping fs-1 text-black-50 mb-2"></i>
                <p class="mb-0 small">No items added yet.<br>Click product card or scan barcode.</p>
            </div>
        `);
        calculateTotals();
        return;
    }

    let html = '';
    cart.forEach((item, idx) => {
        const netWeight = Math.max(0, item.weight - item.tare);
        html += `
            <div class="cart-row">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <strong class="text-dark small d-block">${item.name}</strong>
                        <small class="text-muted" style="font-size:0.75rem;">${item.code} | Rs ${item.rate.toFixed(0)}/${item.unit}</small>
                    </div>
                    <div class="text-end">
                        <strong class="text-danger">Rs ${item.total.toFixed(2)}</strong>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-light border px-2 py-0" onclick="adjustWeight(${idx}, -0.250)">-</button>
                        <span class="badge bg-white text-dark border px-2 py-1 fw-bold font-monospace" style="cursor:pointer;" onclick="openEditModal(${idx})" title="Click to custom edit">
                            ${netWeight.toFixed(3)} ${item.unit}
                        </span>
                        <button type="button" class="btn btn-sm btn-light border px-2 py-0" onclick="adjustWeight(${idx}, 0.250)">+</button>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary p-1" onclick="openEditModal(${idx})" title="Edit Price/Weight">
                            <i class="fa-solid fa-pen-to-square" style="font-size:11px;"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="removeCartItem(${idx})" title="Remove Item">
                            <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });

    container.html(html);
    calculateTotals();
}

function adjustWeight(idx, delta) {
    if (cart[idx]) {
        cart[idx].weight = Math.max(0.1, cart[idx].weight + delta);
        const netW = Math.max(0, cart[idx].weight - cart[idx].tare);
        cart[idx].total = netW * cart[idx].rate;
        renderCart();
    }
}

function removeCartItem(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function openEditModal(idx) {
    const item = cart[idx];
    if (!item) return;

    $('#editItemIndex').val(idx);
    $('#editModalItemName').text('Edit ' + item.name);
    $('#editItemWeight').val(item.weight.toFixed(3));
    $('#editItemTare').val(item.tare.toFixed(3));
    $('#editItemRate').val(item.rate.toFixed(2));
    
    const netW = Math.max(0, item.weight - item.tare);
    $('#editItemLineTotal').text('Rs ' + (netW * item.rate).toFixed(2));

    $('#modalEditItem').modal('show');
}

// Calculate Cart Totals
function calculateTotals() {
    let subtotal = 0;
    let totalWeight = 0;

    cart.forEach(item => {
        subtotal += item.total;
        totalWeight += Math.max(0, item.weight - item.tare);
    });

    const discVal = parseFloat($('#cartDiscount').val()) || 0;
    const discType = $('#cartDiscountType').val();
    let discountAmount = 0;

    if (discType === 'percent') {
        discountAmount = (subtotal * discVal) / 100;
    } else {
        discountAmount = discVal;
    }

    const grandTotal = Math.max(0, subtotal - discountAmount);

    $('#lblTotalWeight').text(totalWeight.toFixed(3) + ' KG');
    $('#lblSubtotal').text('Rs ' + subtotal.toFixed(2));
    $('#lblGrandTotal').text('Rs ' + grandTotal.toFixed(2));

    // Update Mobile Views
    $('#mobileCartBadge').text(cart.length);
    $('#floatCartCount').text(cart.length);
    $('#floatCartTotal').text('Rs ' + grandTotal.toFixed(2));

    if (cart.length > 0 && !$('#posCheckoutPanel').hasClass('mobile-active')) {
        $('#mobileFloatingCartBar').removeAttr('style').attr('style', 'display: flex !important; z-index: 1045;');
    } else {
        $('#mobileFloatingCartBar').attr('style', 'display: none !important;');
    }

    // Auto default amount paid
    if ($('#cartPaymentMethod option:selected').data('type') !== 'credit') {
        $('#cartAmountPaid').val(grandTotal.toFixed(2));
    }
}

// Mobile Tab Switchers
function switchToMobileCart() {
    $('#posCatalogPanel').addClass('mobile-hidden');
    $('#posCheckoutPanel').addClass('mobile-active');
    $('#btnMobileShowCart').addClass('active');
    $('#btnMobileShowCatalog').removeClass('active');
    $('#mobileFloatingCartBar').attr('style', 'display: none !important;');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function switchToMobileCatalog() {
    $('#posCatalogPanel').removeClass('mobile-hidden');
    $('#posCheckoutPanel').removeClass('mobile-active');
    $('#btnMobileShowCatalog').addClass('active');
    $('#btnMobileShowCart').removeClass('active');
    if (cart.length > 0) {
        $('#mobileFloatingCartBar').removeAttr('style').attr('style', 'display: flex !important; z-index: 1045;');
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Submit Checkout Order
function submitCheckout() {
    if (cart.length === 0) {
        Swal.fire('Empty Cart', 'Please add meat products before checking out.', 'warning');
        return;
    }

    const customerId = $('#cartCustomerId').val();
    const discountVal = parseFloat($('#cartDiscount').val()) || 0;
    const discountType = $('#cartDiscountType').val();
    const paymentMethodId = $('#cartPaymentMethod').val();
    const amountPaid = parseFloat($('#cartAmountPaid').val()) || 0;

    const payload = {
        customer_id: customerId,
        discount_value: discountVal,
        discount_type: discountType,
        payment_method_id: paymentMethodId,
        amount_paid: amountPaid,
        items: cart
    };

    $('#btnCompleteSale').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...');

    $.ajax({
        url: '/MeatShop/api/pos.php?action=create_sale',
        type: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        success: function(res) {
            $('#btnCompleteSale').prop('disabled', false).html('<span><i class="fa-solid fa-check-circle me-1"></i> COMPLETE SALE (F4)</span><i class="fa-solid fa-arrow-right"></i>');

            if (res.status === 'success') {
                // Clear cart
                cart = [];
                $('#cartDiscount').val(0);
                renderCart();

                // Open Printable Receipt Window
                const receiptUrl = '/MeatShop/pos/receipt.php?id=' + res.sale_id;
                const win = window.open(receiptUrl, '_blank', 'width=450,height=700');
                if (win) {
                    win.focus();
                }

                Swal.fire({
                    title: 'Sale Completed!',
                    text: 'Invoice #' + res.invoice_no + ' created successfully.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to complete sale', 'error');
            }
        },
        error: function(err) {
            $('#btnCompleteSale').prop('disabled', false).html('<span><i class="fa-solid fa-check-circle me-1"></i> COMPLETE SALE (F4)</span><i class="fa-solid fa-arrow-right"></i>');
            Swal.fire('Server Error', 'Failed to communicate with POS server', 'error');
        }
    });
}
