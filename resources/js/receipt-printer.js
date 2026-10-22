/**
 * Pencetakan struk langsung ke printer thermal ESC/POS (EPPOS).
 *
 * Tiga jalur, dipilih otomatis atau manual:
 *  1. print-agent — http://127.0.0.1:17777 (Windows, paling andal, kirim RAW)
 *  2. usb         — WebUSB (Android/Chrome, USB Type-C/OTG)
 *  3. bluetooth   — Web Bluetooth (hanya printer BLE)
 *
 * PENTING soal user gesture:
 * navigator.usb.requestDevice() dan navigator.bluetooth.requestDevice() WAJIB
 * dipanggil langsung di dalam event klik. Chrome akan menolak dengan
 * "Must be handling a user gesture" bila didahului await. Karena itu, pada
 * jalur USB/Bluetooth pemilihan perangkat dilakukan SEBELUM mengambil byte
 * struk dari server.
 *
 * Catatan Bluetooth: Web Bluetooth hanya mendukung BLE. Printer thermal yang
 * memakai Bluetooth Classic (SPP) tidak akan muncul di dialog pemilihan.
 */
(() => {
    const AGENT_URL = 'http://127.0.0.1:17777';
    const AGENT_TIMEOUT_MS = 1200;
    const STORAGE_KEY = 'pancor.print.channel';

    // Layanan BLE yang umum dipakai printer thermal ESC/POS.
    const BLE_SERVICES = [
        '000018f0-0000-1000-8000-00805f9b34fb', // ESC/POS generik
        '0000ff00-0000-1000-8000-00805f9b34fb',
        '0000ffe0-0000-1000-8000-00805f9b34fb', // modul HM-10
        '0000fff0-0000-1000-8000-00805f9b34fb',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455', // Microchip transparent UART
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
    ];

    const state = {
        usbDevice: null,
        usbEndpoint: null,
        bleDevice: null,
        bleCharacteristic: null,
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

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    // ---------------------------------------------------------------- agent
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

    // ------------------------------------------------------------------ USB
    async function findUsbEndpoint(device) {
        if (!device.opened) {
            await device.open();
        }

        if (device.configuration === null) {
            await device.selectConfiguration(1);
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

        throw new Error('Printer tidak punya endpoint output. Pastikan perangkat ESC/POS.');
    }

    /**
     * Harus dipanggil DI DALAM event klik (user gesture).
     */
    async function pickUsbDevice() {
        if (!('usb' in navigator)) {
            throw new Error(
                'Browser ini tidak mendukung WebUSB. Di Windows gunakan print agent, ' +
                'atau buka lewat Chrome di Android.'
            );
        }

        const device = await navigator.usb.requestDevice({
            filters: [
                { vendorId: 0x0416 }, // Winbond / chip printer thermal umum
                { vendorId: 0x0483 }, // STMicroelectronics
                { vendorId: 0x1a86 }, // QinHeng / CH340
                { vendorId: 0x0fe6 }, // ICS Advent
                { vendorId: 0x28e9 }, // GDMicroelectronics
                { vendorId: 0x6868 }, // printer POS generik
                { vendorId: 0x04b8 }, // Epson
                { vendorId: 0x0519 }, // Star Micronics
                { classCode: 7 },     // kelas USB printer
            ],
        });

        state.usbDevice = device;
        state.usbEndpoint = await findUsbEndpoint(device);

        log(`WebUSB terhubung: ${device.productName || 'printer'}`);

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
        const CHUNK = 512;

        for (let offset = 0; offset < bytes.length; offset += CHUNK) {
            await device.transferOut(endpointNumber, bytes.slice(offset, offset + CHUNK));
        }

        return 'Struk dikirim ke printer via USB.';
    }

    // ------------------------------------------------------------ Bluetooth
    /**
     * Harus dipanggil DI DALAM event klik (user gesture).
     */
    async function pickBleDevice() {
        if (!('bluetooth' in navigator)) {
            throw new Error(
                'Browser ini tidak mendukung Web Bluetooth. Gunakan Chrome di Android ' +
                'atau Chrome/Edge di Windows.'
            );
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_SERVICES,
        });

        const server = await device.gatt.connect();
        const characteristic = await findWritableCharacteristic(server, device);

        state.bleDevice = device;
        state.bleCharacteristic = characteristic;

        device.addEventListener('gattserverdisconnected', () => {
            state.bleDevice = null;
            state.bleCharacteristic = null;
            log('Bluetooth terputus');
        });

        log(`BLE terhubung: ${device.name || 'printer'} (${characteristic.uuid})`);

        return device;
    }

    async function findWritableCharacteristic(server, device) {
        const knownCharacteristics = [
            '00002af1-0000-1000-8000-00805f9b34fb',
            '0000ff02-0000-1000-8000-00805f9b34fb',
            '0000ffe1-0000-1000-8000-00805f9b34fb',
            '49535343-8841-43f4-a8d4-ecbe34729bb3',
        ];

        const writable = (characteristic) =>
            characteristic.properties.write || characteristic.properties.writeWithoutResponse;

        // Pindai seluruh layanan yang boleh diakses.
        let services = [];

        try {
            services = await server.getPrimaryServices();
        } catch (error) {
            services = [];
        }

        // 1) Utamakan characteristic yang dikenal printer thermal.
        for (const service of services) {
            try {
                const characteristics = await service.getCharacteristics();

                for (const characteristic of characteristics) {
                    if (!writable(characteristic)) {
                        continue;
                    }

                    if (knownCharacteristics.includes(characteristic.uuid.toLowerCase())) {
                        return characteristic;
                    }
                }
            } catch (error) {
                // sebagian layanan tidak boleh diakses, abaikan
            }
        }

        // 2) Ambil characteristic writable pertama yang ditemukan.
        for (const service of services) {
            try {
                const characteristics = await service.getCharacteristics();
                const found = characteristics.find(writable);

                if (found) {
                    return found;
                }
            } catch (error) {
                // abaikan
            }
        }

        throw new Error(
            `Tidak menemukan characteristic yang bisa ditulis di ${device.name || 'perangkat'}. ` +
            'Printer mungkin memakai Bluetooth Classic (SPP), yang tidak didukung browser. ' +
            'Gunakan kabel USB atau print agent.'
        );
    }

    async function printViaBluetooth(bytes) {
        if (!state.bleDevice || !state.bleCharacteristic || !state.bleDevice.gatt.connected) {
            await pickBleDevice();
        }

        const characteristic = state.bleCharacteristic;
        const withoutResponse = characteristic.properties.writeWithoutResponse;

        // BLE punya paket kecil. 20 byte paling aman, 100–180 byte lebih cepat
        // pada printer yang sudah menegosiasikan MTU.
        const chunks = [];
        const size = withoutResponse ? 180 : 20;

        for (let offset = 0; offset < bytes.length; offset += size) {
            chunks.push(bytes.slice(offset, offset + size));
        }

        for (const chunk of chunks) {
            if (withoutResponse) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValueWithResponse(chunk);
            }

            await sleep(20);
        }

        return 'Struk dikirim ke printer via Bluetooth.';
    }

    // -------------------------------------------------------------- publik
    window.pancorPrinter = {
        busy: false,
        status: '',
        lastError: '',
        channel: (() => {
            try {
                return localStorage.getItem(STORAGE_KEY) || 'auto';
            } catch (error) {
                return 'auto';
            }
        })(),

        setChannel(value) {
            this.channel = value;

            try {
                localStorage.setItem(STORAGE_KEY, value);
            } catch (error) {
                // localStorage bisa diblokir; abaikan
            }
        },

        /**
         * @param {string} url    endpoint ESC/POS
         * @param {string} forced 'agent' | 'usb' | 'bluetooth' (opsional)
         */
        async print(url, forced) {
            if (this.busy) {
                return;
            }

            const channel = forced || this.channel;

            this.busy = true;
            this.status = 'Menyiapkan struk...';
            this.lastError = '';

            try {
                // Print agent tidak butuh user gesture dan paling andal di Windows.
                if (channel === 'auto' || channel === 'agent') {
                    if (await agentAvailable()) {
                        this.status = 'Mengirim ke print agent...';
                        this.status = await printViaAgent(await fetchReceiptBytes(url));
                        return;
                    }

                    if (channel === 'agent') {
                        throw new Error('Print agent tidak berjalan. Jalankan tools/print-agent/agent.ps1.');
                    }
                }

                // Jalur USB/Bluetooth: pilih perangkat DULU (user gesture masih aktif),
                // baru ambil byte struk. Kalau dibalik, Chrome menolak dengan
                // "Must be handling a user gesture".
                if (channel === 'usb') {
                    this.status = 'Pilih printer USB...';
                    await pickUsbDeviceSafe();
                    this.status = await printViaUsb(await fetchReceiptBytes(url));
                    return;
                }

                if (channel === 'bluetooth') {
                    this.status = 'Pilih printer Bluetooth...';
                    await pickBleSafe();
                    this.status = await printViaBluetooth(await fetchReceiptBytes(url));
                    return;
                }

                // auto: coba USB, lalu Bluetooth.
                this.status = 'Pilih printer USB...';

                try {
                    await pickUsbDeviceSafe();
                    this.status = await printViaUsb(await fetchReceiptBytes(url));
                    return;
                } catch (usbError) {
                    if (!('bluetooth' in navigator)) {
                        throw usbError;
                    }

                    this.status = 'Mencoba Bluetooth...';
                    await pickBleSafe();
                    this.status = await printViaBluetooth(await fetchReceiptBytes(url));
                }
            } catch (error) {
                this.lastError = error.message || String(error);
                this.status = '';
            } finally {
                this.busy = false;
            }
        },

        async connect(channel) {
            this.lastError = '';

            try {
                if (channel === 'usb') {
                    await pickUsbDevice();
                    this.status = 'Printer USB siap.';
                } else if (channel === 'bluetooth') {
                    await pickBleDevice();
                    this.status = 'Printer Bluetooth siap.';
                } else {
                    const ok = await agentAvailable();
                    this.status = ok ? 'Print agent terdeteksi.' : 'Print agent tidak berjalan.';
                }
            } catch (error) {
                this.lastError = error.message || String(error);
                this.status = '';
            }
        },
    };

    // Pembungkus kecil: membedakan "dibatalkan pengguna" agar tidak dianggap error.
    async function pickUsbDeviceSafe() {
        try {
            return await pickUsbDevice();
        } catch (error) {
            if (error.name === 'NotFoundError') {
                throw new Error('Pemilihan printer USB dibatalkan.');
            }

            throw error;
        }
    }

    async function pickBleSafe() {
        try {
            return await pickBleDevice();
        } catch (error) {
            if (error.name === 'NotFoundError') {
                throw new Error('Pemilihan printer Bluetooth dibatalkan.');
            }

            throw error;
        }
    }
})();
