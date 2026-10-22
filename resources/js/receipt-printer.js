/**
 * Pencetakan struk langsung ke printer thermal ESC/POS (EPPOS).
 *
 * Tiga jalur cetak:
 *  1. print-agent — http://127.0.0.1:17777 (Windows, kirim byte RAW, paling andal)
 *  2. usb         — WebUSB (Android/Chrome, kabel USB Type-C/OTG)
 *  3. bluetooth   — Web Bluetooth (BLE)
 *
 * PENTING soal user gesture:
 * navigator.usb.requestDevice() dan navigator.bluetooth.requestDevice() WAJIB
 * dipanggil langsung di dalam event klik. Chrome menolak dengan
 * "Must be handling a user gesture" bila didahului await. Karena itu perangkat
 * dipilih LEBIH DULU, baru byte struk diambil dari server.
 */
(() => {
    const AGENT_URL = 'http://127.0.0.1:17777';
    const AGENT_TIMEOUT_MS = 1200;
    const STORAGE_KEY = 'pancor.print.channel';

    // Ukuran paket BLE. 20 byte adalah ukuran yang dijamin diterima semua
    // modul printer; paket lebih besar bisa membuat struk tercetak acak.
    const BLE_CHUNK_SIZE = 20;
    const BLE_WRITE_DELAY_MS = 15;

    /*
     * Pemetaan service → characteristic tulis untuk modul BLE printer thermal
     * yang umum dipakai (Gprinter, Xprinter, Munbyn, GoojPRT, Epson, HM-10).
     * Urutan = prioritas percobaan.
     */
    const BLE_PROFILES = [
        {
            name: 'Generic ESC/POS (18F0)',
            service: '000018f0-0000-1000-8000-00805f9b34fb',
            characteristic: '00002af1-0000-1000-8000-00805f9b34fb',
        },
        {
            name: 'Gprinter/Xprinter/Munbyn (e7810a71)',
            service: 'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
            characteristic: 'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f',
        },
        {
            name: 'Microchip ISSC / GoojPRT (49535343)',
            service: '49535343-fe7d-4ae5-8fa9-9fafd205e455',
            characteristic: '49535343-8841-43f4-a8d4-ecbe34729bb3',
        },
        {
            name: 'HM-10 / Telink (FFE0)',
            service: '0000ffe0-0000-1000-8000-00805f9b34fb',
            characteristic: '0000ffe1-0000-1000-8000-00805f9b34fb',
        },
        {
            name: 'Vendor generik (FF00)',
            service: '0000ff00-0000-1000-8000-00805f9b34fb',
            characteristic: '0000ff02-0000-1000-8000-00805f9b34fb',
        },
        {
            name: 'Vendor generik (FFF0)',
            service: '0000fff0-0000-1000-8000-00805f9b34fb',
            characteristic: '0000fff1-0000-1000-8000-00805f9b34fb',
        },
        {
            name: 'Vendor generik (FEE7)',
            service: '0000fee7-0000-1000-8000-00805f9b34fb',
            characteristic: '0000fee8-0000-1000-8000-00805f9b34fb',
        },
    ];

    // Daftar service yang harus diminta izinnya ke browser. Tanpa ini, service
    // tidak bisa diakses walaupun printer mengiklankannya.
    const BLE_OPTIONAL_SERVICES = [
        ...BLE_PROFILES.map((profile) => profile.service),
        '0000fff1-0000-1000-8000-00805f9b34fb',
        '0000fee9-0000-1000-8000-00805f9b34fb',
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

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

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

    // ------------------------------------------------------------- agent
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

    // --------------------------------------------------------------- USB
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

    /** Harus dipanggil DI DALAM event klik (user gesture). */
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

    // --------------------------------------------------------- Bluetooth
    const isWritable = (characteristic) =>
        Boolean(characteristic.properties.write || characteristic.properties.writeWithoutResponse);

    /**
     * Coba semua pemetaan yang dikenal. Mengembalikan characteristic pertama
     * yang ada dan bisa ditulis.
     */
    async function resolveBleCharacteristic(server) {
        const discovered = [];

        for (const profile of BLE_PROFILES) {
            try {
                const service = await server.getPrimaryService(profile.service);
                let characteristic = null;

                try {
                    characteristic = await service.getCharacteristic(profile.characteristic);
                } catch (error) {
                    // Characteristic bawaan tidak ada; cari yang bisa ditulis.
                    const candidates = await service.getCharacteristics();
                    characteristic = candidates.find(isWritable) ?? null;
                }

                if (!characteristic) {
                    discovered.push(`${profile.name}: service ada, tidak ada characteristic tulis`);
                    continue;
                }

                if (!isWritable(characteristic)) {
                    discovered.push(`${profile.name}: characteristic tidak writable`);
                    continue;
                }

                log(`BLE memakai ${profile.name} → ${characteristic.uuid}`);

                return characteristic;
            } catch (error) {
                // Service tidak tersedia di printer ini, lanjut ke profil berikutnya.
            }
        }

        // Fallback: pindai semua service yang boleh diakses.
        try {
            const services = await server.getPrimaryServices();

            for (const service of services) {
                try {
                    const characteristics = await service.getCharacteristics();
                    const found = characteristics.find(isWritable);

                    if (found) {
                        log(`BLE memakai service ${service.uuid} → ${found.uuid}`);
                        discovered.push(`fallback: ${service.uuid} / ${found.uuid}`);

                        return found;
                    }
                } catch (error) {
                    // abaikan layanan yang tidak boleh diakses
                }
            }
        } catch (error) {
            discovered.push(`getPrimaryServices gagal: ${error.message}`);
        }

        throw new Error(
            'Tidak menemukan layanan cetak yang bisa ditulis. ' +
            (discovered.length ? `Detail: ${discovered.join('; ')}. ` : '') +
            'Printer mungkin memakai Bluetooth Classic (SPP) yang tidak didukung browser — ' +
            'gunakan kabel USB atau print agent.'
        );
    }

    /** Harus dipanggil DI DALAM event klik (user gesture). */
    async function pickBleDevice() {
        if (!('bluetooth' in navigator)) {
            throw new Error(
                'Browser ini tidak mendukung Web Bluetooth. Gunakan Chrome di Android ' +
                'atau Chrome/Edge di Windows.'
            );
        }

        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: BLE_OPTIONAL_SERVICES,
        });

        log(`Perangkat dipilih: ${device.name || '(tanpa nama)'}`);

        const server = await device.gatt.connect();
        const characteristic = await resolveBleCharacteristic(server);

        state.bleDevice = device;
        state.bleCharacteristic = characteristic;

        device.addEventListener('gattserverdisconnected', () => {
            state.bleDevice = null;
            state.bleCharacteristic = null;
            log('Bluetooth terputus');
        });

        return device;
    }

    async function printViaBluetooth(bytes) {
        if (!state.bleDevice || !state.bleCharacteristic || !state.bleDevice.gatt.connected) {
            await pickBleDevice();
        }

        const characteristic = state.bleCharacteristic;
        const withoutResponse = Boolean(characteristic.properties.writeWithoutResponse);

        for (let offset = 0; offset < bytes.length; offset += BLE_CHUNK_SIZE) {
            const chunk = bytes.slice(offset, offset + BLE_CHUNK_SIZE);

            if (withoutResponse) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValueWithResponse(chunk);
            }

            await sleep(BLE_WRITE_DELAY_MS);
        }

        return 'Struk dikirim ke printer via Bluetooth.';
    }

    // -------------------------------------------------------------- publik
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

                // Pilih perangkat DULU (gesture masih aktif), baru ambil byte.
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

                this.status = 'Pilih printer USB...';

                try {
                    await pickUsbDeviceSafe();
                    this.status = await printViaUsb(await fetchReceiptBytes(url));
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

        /**
         * Menampilkan daftar service & characteristic printer ke console.
         * Berguna saat printer tidak mau mencetak.
         */
        async diagnose() {
            this.lastError = '';
            this.status = 'Mendiagnosis printer...';

            try {
                const device = await pickBleDevice();
                const server = await device.gatt.connect();
                const services = await server.getPrimaryServices();
                const report = [];

                for (const service of services) {
                    let characteristics = [];

                    try {
                        characteristics = await service.getCharacteristics();
                    } catch (error) {
                        characteristics = [];
                    }

                    for (const characteristic of characteristics) {
                        report.push({
                            service: service.uuid,
                            characteristic: characteristic.uuid,
                            write: characteristic.properties.write,
                            writeWithoutResponse: characteristic.properties.writeWithoutResponse,
                        });
                    }
                }

                // eslint-disable-next-line no-console
                console.table(report);
                this.status = `Ditemukan ${report.length} characteristic. Detail di console (F12).`;
            } catch (error) {
                this.lastError = error.message || String(error);
                this.status = '';
            }
        },
    };
})();
