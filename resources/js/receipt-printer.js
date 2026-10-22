/**
 * Pencetakan struk langsung ke printer thermal ESC/POS (EPPOS USB).
 *
 * Dua jalur, dicoba berurutan:
 * 1. Print agent lokal (http://127.0.0.1:17777) — paling andal di Windows karena
 *    mengirim byte mentah langsung ke printer tanpa lewat driver/spooler.
 * 2. WebUSB — dipakai di Android/Chrome (butuh HTTPS), tanpa instal apa pun.
 *
 * Catatan: Web Bluetooth sengaja tidak dipakai karena printer thermal EPPOS
 * umumnya Bluetooth Classic (SPP), sedangkan Web Bluetooth hanya mendukung BLE.
 */
(() => {
    const AGENT_URL = 'http://127.0.0.1:17777';
    const AGENT_TIMEOUT_MS = 1500;

    const state = {
        usbDevice: null,
        usbEndpoint: null,
    };

    const log = (message) => {
        // eslint-disable-next-line no-console
        console.info(`[pancor-print] ${message}`);
    };

    async function fetchReceiptBytes(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/octet-stream' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Gagal mengambil data struk (HTTP ${response.status})`);
        }

        return new Uint8Array(await response.arrayBuffer());
    }

    async function agentAvailable() {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), AGENT_TIMEOUT_MS);

        try {
            const response = await fetch(`${AGENT_URL}/health`, {
                signal: controller.signal,
                cache: 'no-store',
            });

            return response.ok;
        } catch (error) {
            return false;
        } finally {
            clearTimeout(timer);
        }
    }

    async function printViaAgent(bytes) {
        const response = await fetch(`${AGENT_URL}/print`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/octet-stream' },
            body: bytes,
        });

        const text = await response.text();

        if (!response.ok) {
            throw new Error(text || `Print agent menolak permintaan (HTTP ${response.status})`);
        }

        return text;
    }

    async function findUsbEndpoint(device) {
        if (!device.configuration) {
            await device.open();

            if (device.configuration === null) {
                await device.selectConfiguration(1);
            }
        } else if (!device.opened) {
            await device.open();
        }

        for (const iface of device.configuration.interfaces) {
            for (const alternate of iface.alternates) {
                const out = alternate.endpoints.find((e) => e.direction === 'out');
                if (!out) {
                    continue;
                }

                if (!iface.claimed) {
                    await device.claimInterface(iface.interfaceNumber);
                }

                return { interfaceNumber: iface.interfaceNumber, endpointNumber: out.endpointNumber };
            }
        }

        throw new Error('Printer tidak punya endpoint output. Pastikan perangkat ESC/POS/USB.');
    }

    async function pickUsbDevice() {
        if (!('usb' in navigator)) {
            throw new Error('Browser ini tidak mendukung WebUSB. Gunakan Chrome/Edge di Android, atau print agent di Windows.');
        }

        const device = await navigator.usb.requestDevice({
            filters: [
                { vendorId: 0x0416 }, // Winbond / chip umum printer thermal
                { vendorId: 0x0483 }, // STMicroelectronics
                { vendorId: 0x1a86 }, // QinHeng / CH340
                { vendorId: 0x0fe6 }, // ICS Advent
                { vendorId: 0x28e9 }, // GDMicroelectronics
                { vendorId: 0x6868 }, // printer POS generik
                { classCode: 7 },     // kelas USB printer
            ],
        });

        const endpoint = await findUsbEndpoint(device);

        state.usbDevice = device;
        state.usbEndpoint = endpoint;

        log(`Terhubung ke ${device.productName || 'printer'} via WebUSB`);

        return device;
    }

    async function printViaUsb(bytes) {
        if (!state.usbDevice || !state.usbEndpoint) {
            await pickUsbDevice();
        }

        const device = state.usbDevice;

        if (!device.opened) {
            await device.open();
        }

        const { endpointNumber } = state.usbEndpoint;
        const CHUNK = 1024;

        for (let offset = 0; offset < bytes.length; offset += CHUNK) {
            await device.transferOut(endpointNumber, bytes.slice(offset, offset + CHUNK));
        }

        return 'Struk dikirim ke printer via USB.';
    }

    window.pancorPrinter = {
        busy: false,
        status: '',
        lastError: '',

        async print(url) {
            if (this.busy) {
                return;
            }

            this.busy = true;
            this.status = 'Menyiapkan struk...';
            this.lastError = '';

            try {
                const bytes = await fetchReceiptBytes(url);

                if (await agentAvailable()) {
                    this.status = 'Mengirim ke print agent...';
                    this.status = await printViaAgent(bytes);
                    return;
                }

                this.status = 'Meminta izin printer USB...';
                this.status = await printViaUsb(bytes);
            } catch (error) {
                this.lastError = error.message || String(error);
                this.status = '';

                // Fallback terakhir: buka tampilan struk untuk dicetak manual.
                if (confirm(`${this.lastError}\n\nBuka tampilan struk untuk dicetak manual?`)) {
                    window.open(url.replace(/\/escpos$/, ''), '_blank');
                }
            } finally {
                this.busy = false;
            }
        },

        async connect() {
            try {
                this.lastError = '';
                await pickUsbDevice();
                this.status = 'Printer USB siap dipakai.';
            } catch (error) {
                this.lastError = error.message || String(error);
            }
        },
    };
})();
