/**
 * Live Weighing Scale Integration & Simulation Engine
 * Meat Shop Management System
 */

class WeighingScaleController {
    constructor() {
        this.port = null;
        this.reader = null;
        this.isConnected = false;
        this.currentGrossWeight = 0.000;
        this.tareWeight = 0.050; // Default 50g tare
        this.currentNetWeight = 0.000;
        this.isTareApplied = false;

        this.init();
    }

    init() {
        // UI Bindings
        const display = document.getElementById('scaleDisplayVal');
        const btnTare = document.getElementById('btnTareScale');
        const btnZero = document.getElementById('btnZeroScale');
        const btnSim = document.getElementById('btnSimulateScale');
        const btnConnect = document.getElementById('connectScaleBtn');

        if (btnTare) {
            btnTare.addEventListener('click', () => this.toggleTare());
        }
        if (btnZero) {
            btnZero.addEventListener('click', () => this.zeroScale());
        }
        if (btnSim) {
            btnSim.addEventListener('click', () => this.simulateWeightPrompt());
        }
        if (btnConnect) {
            btnConnect.addEventListener('click', () => this.connectHardwareSerial());
        }

        this.updateDisplay();
    }

    // Set Live Weight from Hardware or Simulator
    setWeight(grossKg) {
        this.currentGrossWeight = Math.max(0, parseFloat(grossKg) || 0);
        this.calculateNet();
        this.updateDisplay();
    }

    calculateNet() {
        if (this.isTareApplied) {
            this.currentNetWeight = Math.max(0, this.currentGrossWeight - this.tareWeight);
        } else {
            this.currentNetWeight = this.currentGrossWeight;
        }
    }

    toggleTare() {
        this.isTareApplied = !this.isTareApplied;
        this.calculateNet();
        this.updateDisplay();
        const btn = document.getElementById('btnTareScale');
        if (btn) {
            if (this.isTareApplied) {
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-danger');
            } else {
                btn.classList.add('btn-outline-secondary');
                btn.classList.remove('btn-danger');
            }
        }
    }

    zeroScale() {
        this.currentGrossWeight = 0.000;
        this.isTareApplied = false;
        this.calculateNet();
        this.updateDisplay();
        const btn = document.getElementById('btnTareScale');
        if (btn) {
            btn.classList.add('btn-outline-secondary');
            btn.classList.remove('btn-danger');
        }
    }

    simulateWeightPrompt() {
        Swal.fire({
            title: 'Simulate Scale Weight',
            input: 'number',
            inputLabel: 'Place meat on scale (KG):',
            inputValue: (Math.random() * (3.5 - 0.5) + 0.5).toFixed(3),
            inputAttributes: {
                step: '0.001',
                min: '0.000'
            },
            showCancelButton: true,
            confirmButtonText: 'Set Scale Weight',
            confirmButtonColor: '#dc2626'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                this.setWeight(parseFloat(result.value));
            }
        });
    }

    updateDisplay() {
        const display = document.getElementById('scaleDisplayVal');
        if (display) {
            display.innerText = this.currentNetWeight.toFixed(3);
        }
    }

    getNetWeight() {
        return this.currentNetWeight > 0 ? this.currentNetWeight : 1.000; // default 1 KG if 0
    }

    // Web Serial Hardware Communication
    async connectHardwareSerial() {
        if (!("serial" in navigator)) {
            Swal.fire('Web Serial Unsupported', 'Your browser does not support Web Serial API. Please use Google Chrome or Edge, or use the interactive scale simulator button (+ Sim).', 'info');
            return;
        }

        try {
            this.port = await navigator.serial.requestPort();
            await this.port.open({ baudRate: 9600 });
            this.isConnected = true;

            const btnConnect = document.getElementById('connectScaleBtn');
            if (btnConnect) {
                btnConnect.classList.remove('btn-outline-info');
                btnConnect.classList.add('btn-success');
                btnConnect.innerHTML = '<i class="fa-solid fa-link me-1"></i> Scale Connected';
            }

            this.readSerialLoop();
        } catch (err) {
            console.error('Serial Scale Connection Error:', err);
        }
    }

    async readSerialLoop() {
        const textDecoder = new TextDecoderStream();
        const readableStreamClosed = this.port.readable.pipeTo(textDecoder.writable);
        this.reader = textDecoder.readable.getReader();

        let buffer = '';
        while (this.isConnected) {
            try {
                const { value, done } = await this.reader.read();
                if (done) break;
                if (value) {
                    buffer += value;
                    const lines = buffer.split('\n');
                    buffer = lines.pop(); // Keep last partial line
                    for (const line of lines) {
                        // Extract numeric float from scale stream format (e.g. "+  1.250 kg" or "ST,GS,  1.250kg")
                        const match = line.match(/[-+]?\s*([0-9]+\.?[0-9]*)/);
                        if (match && match[1]) {
                            this.setWeight(parseFloat(match[1]));
                        }
                    }
                }
            } catch (error) {
                console.warn('Serial Read Error:', error);
                break;
            }
        }
    }
}

// Global instance
window.scaleCtrl = new WeighingScaleController();
