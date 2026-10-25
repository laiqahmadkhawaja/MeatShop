/**
 * Barcode Camera Scanner with Auto-Alignment & Auto-Zoom
 * Meat Shop Management System
 */

(function(window, $) {
    'use strict';

    // Audio Feedback for POS Scanner
    const ScannerAudio = {
        ctx: null,
        init: function() {
            if (!this.ctx && (window.AudioContext || window.webkitAudioContext)) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                this.ctx = new AudioCtx();
            }
        },
        playSuccess: function() {
            try {
                this.init();
                if (!this.ctx) return;
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1400, this.ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1800, this.ctx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.3, this.ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.01, this.ctx.currentTime + 0.12);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start();
                osc.stop(this.ctx.currentTime + 0.12);
            } catch (e) {
                console.warn('Audio play error', e);
            }
        },
        playFail: function() {
            try {
                this.init();
                if (!this.ctx) return;
                if (this.ctx.state === 'suspended') {
                    this.ctx.resume();
                }
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(320, this.ctx.currentTime);
                gain.gain.setValueAtTime(0.2, this.ctx.currentTime);
                gain.gain.linearRampToValueAtTime(0.01, this.ctx.currentTime + 0.22);
                osc.connect(gain);
                gain.connect(this.ctx.destination);
                osc.start();
                osc.stop(this.ctx.currentTime + 0.22);
            } catch (e) {
                console.warn('Audio play error', e);
            }
        }
    };

    class BarcodeScannerController {
        constructor() {
            this.html5QrCode = null;
            this.isScanning = false;
            this.cameras = [];
            this.currentCameraIndex = 0;
            this.videoTrack = null;
            this.supportsHardwareZoom = false;
            this.torchSupported = false;
            this.torchActive = false;

            // Auto-Zoom State
            this.autoZoomEnabled = true;
            this.currentZoom = 1.0;
            this.minZoom = 1.0;
            this.maxZoom = 3.0;
            this.zoomStep = 0.4;
            this.autoZoomInterval = null;
            this.autoZoomCycle = [1.0, 1.4, 1.9, 2.5, 3.0, 1.8, 1.0];
            this.autoZoomIndex = 0;

            // Debounce / Continuous mode
            this.continuousScan = false;
            this.lastScannedCode = '';
            this.lastScanTime = 0;

            this.initEvents();
        }

        initEvents() {
            const self = this;

            // Dedicated Open Scanner Button Handlers (delegated)
            $(document).on('click', '#btnOpenBarcodeScanner', function(e) {
                e.preventDefault();
                self.openModal();
            });

            // Native DOM and jQuery Modal Lifecycle Listeners
            $(document).on('shown.bs.modal', '#barcodeScannerModal', function() {
                self.startScanner();
            });

            $(document).on('hidden.bs.modal', '#barcodeScannerModal', function() {
                self.stopScanner();
            });

            // Direct native DOM listener fallback
            const modalEl = document.getElementById('barcodeScannerModal');
            if (modalEl) {
                modalEl.addEventListener('shown.bs.modal', function() {
                    self.startScanner();
                });
                modalEl.addEventListener('hidden.bs.modal', function() {
                    self.stopScanner();
                });
            }

            // Toggle Auto-Zoom Button
            $(document).on('click', '#btnToggleAutoZoom', function() {
                self.autoZoomEnabled = !self.autoZoomEnabled;
                self.updateAutoZoomUI();
            });

            // Manual Zoom Controls
            $(document).on('click', '#btnZoomIn', function() {
                self.autoZoomEnabled = false;
                self.updateAutoZoomUI();
                self.setZoom(Math.min(self.maxZoom, self.currentZoom + 0.3));
            });

            $(document).on('click', '#btnZoomOut', function() {
                self.autoZoomEnabled = false;
                self.updateAutoZoomUI();
                self.setZoom(Math.max(self.minZoom, self.currentZoom - 0.3));
            });

            $(document).on('input', '#scannerZoomSlider', function() {
                self.autoZoomEnabled = false;
                self.updateAutoZoomUI();
                self.setZoom(parseFloat($(this).val()));
            });

            // Torch Toggle Button
            $(document).on('click', '#btnToggleTorch', function() {
                self.toggleTorch();
            });

            // Switch Camera Button
            $(document).on('click', '#btnSwitchCamera', function() {
                self.switchCamera();
            });

            // Continuous Scan Checkbox
            $(document).on('change', '#chkContinuousScan', function() {
                self.continuousScan = $(this).is(':checked');
            });
        }

        openModal() {
            const modalEl = document.getElementById('barcodeScannerModal');
            if (modalEl) {
                if (window.bootstrap && window.bootstrap.Modal) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                } else if (typeof $ !== 'undefined' && typeof $.fn.modal === 'function') {
                    $(modalEl).modal('show');
                } else {
                    // Direct CSS fallback if modal JS isn't ready
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            }
        }

        closeModal() {
            const modalEl = document.getElementById('barcodeScannerModal');
            if (modalEl) {
                if (window.bootstrap && window.bootstrap.Modal) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                } else if (typeof $ !== 'undefined' && typeof $.fn.modal === 'function') {
                    $(modalEl).modal('hide');
                } else {
                    modalEl.classList.remove('show');
                    modalEl.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            }
        }

        async startScanner() {
            const self = this;
            const containerId = "barcodeScannerView";
            const statusEl = $('#scannerStatusMsg');

            statusEl.html('<span class="text-white-50"><i class="fa-solid fa-spinner fa-spin me-1 text-danger"></i> Initializing camera feed...</span>');
            $('#scannerReticleBox').removeClass('scan-matched scan-failed');

            if (!window.Html5Qrcode) {
                statusEl.html('<span class="text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i> Barcode engine not loaded. Please refresh.</span>');
                return;
            }

            try {
                if (!this.html5QrCode) {
                    this.html5QrCode = new Html5Qrcode(containerId);
                }

                this.cameras = await Html5Qrcode.getCameras();
                if (!this.cameras || this.cameras.length === 0) {
                    statusEl.html('<span class="text-danger"><i class="fa-solid fa-video-slash me-1"></i> No video camera found on this device.</span>');
                    return;
                }

                // Show/hide camera switch button if multiple cameras exist
                if (this.cameras.length > 1) {
                    $('#btnSwitchCamera').removeClass('d-none');
                } else {
                    $('#btnSwitchCamera').addClass('d-none');
                }

                // Select preferred camera (prefer back/environment camera on phones, first camera on laptop/PC)
                let selectedCamId = this.cameras[0].id;
                for (let i = 0; i < this.cameras.length; i++) {
                    const label = (this.cameras[i].label || '').toLowerCase();
                    if (label.includes('back') || label.includes('rear') || label.includes('environment')) {
                        selectedCamId = this.cameras[i].id;
                        this.currentCameraIndex = i;
                        break;
                    }
                }

                const scanConfig = {
                    fps: 20,
                    qrbox: function(viewfinderWidth, viewfinderHeight) {
                        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        const width = Math.floor(viewfinderWidth * 0.85);
                        const height = Math.floor(Math.min(viewfinderHeight * 0.65, minEdge * 0.8));
                        return { width: Math.max(220, width), height: Math.max(160, height) };
                    },
                    aspectRatio: 1.333
                };

                // Add format support if enum is available
                if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
                    scanConfig.formatsToSupport = [
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.QR_CODE
                    ];
                }

                // Attempt start with specific camera device ID, with graceful facingMode fallback
                try {
                    await this.html5QrCode.start(
                        selectedCamId,
                        scanConfig,
                        (decodedText, decodedResult) => self.handleBarcodeDetected(decodedText, decodedResult),
                        (errorMessage) => {}
                    );
                } catch (startErr) {
                    console.warn("Retrying camera start with facingMode constraint:", startErr);
                    await this.html5QrCode.start(
                        { facingMode: "environment" },
                        scanConfig,
                        (decodedText, decodedResult) => self.handleBarcodeDetected(decodedText, decodedResult),
                        (errorMessage) => {}
                    );
                }

                this.isScanning = true;
                statusEl.html('<span class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Camera live. Align barcode inside red brackets.</span>');

                // Inspect track capabilities for hardware optical zoom & torch
                this.inspectCameraCapabilities();

                // Start Automatic Alignment Zoom Engine
                this.startAutoZoomEngine();

            } catch (err) {
                console.error("Scanner start error:", err);
                statusEl.html(`
                    <div class="text-danger small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Camera access error: ${err.message || 'Permission denied or unsupported'}.
                        <button class="btn btn-xs btn-outline-light rounded-pill ms-2 py-0" onclick="window.barcodeScanner && window.barcodeScanner.startScanner()">Retry</button>
                    </div>
                `);
            }
        }

        inspectCameraCapabilities() {
            try {
                const videoEl = document.querySelector('#barcodeScannerView video');
                if (videoEl && videoEl.srcObject) {
                    const stream = videoEl.srcObject;
                    const tracks = stream.getVideoTracks();
                    if (tracks && tracks.length > 0) {
                        this.videoTrack = tracks[0];
                        if (typeof this.videoTrack.getCapabilities === 'function') {
                            const capabilities = this.videoTrack.getCapabilities();

                            // Hardware Zoom
                            if (capabilities.zoom) {
                                this.supportsHardwareZoom = true;
                                this.minZoom = capabilities.zoom.min || 1.0;
                                this.maxZoom = Math.min(5.0, capabilities.zoom.max || 3.0);
                                this.zoomStep = capabilities.zoom.step || 0.2;
                                $('#scannerZoomSlider').attr({
                                    min: this.minZoom,
                                    max: this.maxZoom,
                                    step: this.zoomStep
                                });
                            } else {
                                this.supportsHardwareZoom = false;
                                this.minZoom = 1.0;
                                this.maxZoom = 3.0;
                                this.zoomStep = 0.2;
                            }

                            // Torch / Flashlight
                            if (capabilities.torch) {
                                this.torchSupported = true;
                                $('#btnToggleTorch').removeClass('d-none');
                            } else {
                                this.torchSupported = false;
                                $('#btnToggleTorch').addClass('d-none');
                            }
                        }
                    }
                }
            } catch (e) {
                console.warn('Capability inspection error', e);
            }
        }

        // Automatic Alignment & Dynamic Zoom Routine
        startAutoZoomEngine() {
            const self = this;
            this.clearAutoZoomTimer();
            this.autoZoomIndex = 0;

            this.autoZoomInterval = setInterval(() => {
                if (!self.isScanning || !self.autoZoomEnabled) return;

                // Cycle through dynamic zoom focus stages
                self.autoZoomIndex = (self.autoZoomIndex + 1) % self.autoZoomCycle.length;
                const nextZoom = self.autoZoomCycle[self.autoZoomIndex];

                self.setZoom(nextZoom, true);
            }, 1600);
        }

        clearAutoZoomTimer() {
            if (this.autoZoomInterval) {
                clearInterval(this.autoZoomInterval);
                this.autoZoomInterval = null;
            }
        }

        setZoom(zoomVal, isAuto = false) {
            this.currentZoom = parseFloat(zoomVal);
            const zoomFixed = this.currentZoom.toFixed(1);

            // Update UI
            $('#scannerZoomDisplay').text(`${zoomFixed}x`);
            $('#scannerZoomSlider').val(this.currentZoom);
            if (isAuto) {
                $('#scannerZoomModeBadge').text('(Auto Aligning)').removeClass('text-muted').addClass('text-danger');
            } else {
                $('#scannerZoomModeBadge').text('(Manual)').removeClass('text-danger').addClass('text-muted');
            }

            // 1. Hardware Zoom (if supported by camera)
            if (this.supportsHardwareZoom && this.videoTrack) {
                try {
                    this.videoTrack.applyConstraints({
                        advanced: [{ zoom: this.currentZoom }]
                    }).catch(err => {
                        this.applyDigitalZoom(this.currentZoom);
                    });
                } catch (e) {
                    this.applyDigitalZoom(this.currentZoom);
                }
            } else {
                // 2. Digital Viewfinder Auto-Zoom
                this.applyDigitalZoom(this.currentZoom);
            }
        }

        applyDigitalZoom(scaleFactor) {
            const videoEl = document.querySelector('#barcodeScannerView video');
            if (videoEl) {
                videoEl.style.transform = `scale(${scaleFactor})`;
                videoEl.style.transformOrigin = 'center center';
                videoEl.style.transition = 'transform 0.35s ease-out';
            }
        }

        updateAutoZoomUI() {
            const btn = $('#btnToggleAutoZoom');
            if (this.autoZoomEnabled) {
                btn.removeClass('btn-outline-secondary').addClass('btn-danger');
                btn.html('<i class="fa-solid fa-arrows-to-eye me-1"></i> Auto-Zoom: ON');
                this.startAutoZoomEngine();
            } else {
                btn.removeClass('btn-danger').addClass('btn-outline-secondary');
                btn.html('<i class="fa-solid fa-arrows-to-eye me-1"></i> Auto-Zoom: OFF');
                this.clearAutoZoomTimer();
                $('#scannerZoomModeBadge').text('(Manual)').removeClass('text-danger').addClass('text-muted');
            }
        }

        toggleTorch() {
            if (!this.torchSupported || !this.videoTrack) return;
            this.torchActive = !this.torchActive;
            try {
                this.videoTrack.applyConstraints({
                    advanced: [{ torch: this.torchActive }]
                });
                $('#btnToggleTorch').toggleClass('btn-warning btn-outline-warning');
            } catch (e) {
                console.warn('Torch toggle error', e);
            }
        }

        async switchCamera() {
            if (this.cameras.length < 2) return;
            this.currentCameraIndex = (this.currentCameraIndex + 1) % this.cameras.length;
            const nextCam = this.cameras[this.currentCameraIndex];

            try {
                if (this.html5QrCode && this.isScanning) {
                    await this.html5QrCode.stop();
                    this.clearAutoZoomTimer();

                    const scanConfig = {
                        fps: 20,
                        aspectRatio: 1.333
                    };
                    const self = this;
                    await this.html5QrCode.start(
                        nextCam.id,
                        scanConfig,
                        (text, res) => self.handleBarcodeDetected(text, res)
                    );
                    this.inspectCameraCapabilities();
                    this.startAutoZoomEngine();
                }
            } catch (e) {
                console.error('Camera switch error', e);
            }
        }

        // Handle decoded barcode
        handleBarcodeDetected(decodedText, decodedResult) {
            const now = Date.now();
            const text = (decodedText || '').trim();
            if (!text) return;

            // Debounce if same barcode within 1.4 seconds
            if (text === this.lastScannedCode && (now - this.lastScanTime) < 1400) {
                return;
            }

            this.lastScannedCode = text;
            this.lastScanTime = now;

            const reticle = $('#scannerReticleBox');
            const statusEl = $('#scannerStatusMsg');

            // Find matching product in POS catalog
            let matchedCard = null;
            let embeddedWeight = null;

            // 1. Check direct match by barcode or product code
            $('.product-card').each(function() {
                const code = ($(this).data('code') || '').toString().toLowerCase();
                const barcode = ($(this).data('barcode') || '').toString().toLowerCase();
                const searchCode = text.toLowerCase();

                if (barcode === searchCode || code === searchCode) {
                    matchedCard = this;
                    return false; // Break loop
                }
            });

            // 2. Check Scale-Embedded Barcode (Standard Retail EAN-13 starting with 20-29 or 02)
            if ((text.length === 13 || text.length === 12) && /^(2[0-9]|02)/.test(text)) {
                const skuPart = text.substring(2, 7);
                const wtGrams = parseInt(text.substring(7, 12), 10);
                if (!isNaN(wtGrams) && wtGrams > 0) {
                    embeddedWeight = wtGrams / 1000.0;
                }

                if (!matchedCard) {
                    $('.product-card').each(function() {
                        const code = ($(this).data('code') || '').toString().toLowerCase();
                        const barcode = ($(this).data('barcode') || '').toString().toLowerCase();
                        if (code.includes(skuPart.toLowerCase()) || barcode.includes(skuPart.toLowerCase()) || barcode.startsWith(text.substring(0, 7))) {
                            matchedCard = this;
                            return false;
                        }
                    });
                }
            }

            if (matchedCard) {
                // If embedded scale weight decoded from barcode, update live scale weight
                if (embeddedWeight !== null && window.scaleCtrl) {
                    window.scaleCtrl.setWeight(embeddedWeight);
                }

                // Success Audio Beep
                ScannerAudio.playSuccess();

                // Flash reticle green
                reticle.removeClass('scan-failed').addClass('scan-matched');
                setTimeout(() => reticle.removeClass('scan-matched'), 700);

                const prodName = $(matchedCard).data('name');
                const prodCode = $(matchedCard).data('code');

                // Add to POS Cart
                if (typeof window.addProductToCart === 'function') {
                    window.addProductToCart(matchedCard);
                }

                statusEl.html(`
                    <div class="alert alert-success py-1 px-3 mb-0 d-inline-flex align-items-center animate__animated animate__fadeIn">
                        <i class="fa-solid fa-circle-check fs-5 me-2"></i>
                        <div class="text-start">
                            <strong>${prodName}</strong> (${prodCode})
                            <div class="small">Added to bill with current live weight!</div>
                        </div>
                    </div>
                `);

                // Update recent scans badge
                $('#scannerLastScannedBadge').html(`
                    <span class="badge bg-success py-1 px-2">
                        <i class="fa-solid fa-check me-1"></i> ${text} &bull; ${prodName}
                    </span>
                `);

                // If single scan mode, close scanner modal after quick confirmation
                if (!this.continuousScan) {
                    setTimeout(() => {
                        this.closeModal();
                    }, 500);
                }

            } else {
                // Not found audio buzz
                ScannerAudio.playFail();

                reticle.removeClass('scan-matched').addClass('scan-failed');
                setTimeout(() => reticle.removeClass('scan-failed'), 700);

                statusEl.html(`
                    <div class="alert alert-warning py-1 px-3 mb-0 d-inline-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                        <span>Barcode <strong>${text}</strong> not found in active meat cuts.</span>
                    </div>
                `);

                $('#scannerLastScannedBadge').html(`
                    <span class="badge bg-secondary py-1 px-2">
                        <i class="fa-solid fa-question me-1"></i> ${text} (Unrecognized)
                    </span>
                `);
            }
        }

        async stopScanner() {
            this.clearAutoZoomTimer();
            this.isScanning = false;
            this.torchActive = false;

            if (this.html5QrCode) {
                try {
                    await this.html5QrCode.stop();
                } catch (e) {
                    // Ignore stop errors if already stopped
                }
            }
        }
    }

    // Expose global instance immediately on window
    const scannerInstance = new BarcodeScannerController();
    window.barcodeScanner = scannerInstance;

})(window, window.jQuery || window.$);
