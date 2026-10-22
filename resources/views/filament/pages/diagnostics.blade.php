<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">Diagnosa Sistem</h2>
            <p class="mt-1 text-sm text-gray-500">
                Halaman ini memeriksa kondisi yang paling sering membuat halaman Filament gagal dimuat di server.
                Kirim tangkapan layar halaman ini bila terjadi error agar penyebabnya dapat diketahui langsung.
            </p>
        </div>

        @php
            $checks = $this->checks;
            $errors = collect($checks)->where('status', 'error')->count();
            $warnings = collect($checks)->where('status', 'warning')->count();
        @endphp

        <div class="rounded-2xl border p-4 shadow-sm {{ $errors > 0 ? 'border-red-300 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10' : ($warnings > 0 ? 'border-amber-300 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10' : 'border-green-300 bg-green-50 dark:border-green-500/30 dark:bg-green-500/10') }}">
            @if ($errors > 0)
                <div class="font-bold text-red-700 dark:text-red-400">⚠️ {{ $errors }} masalah ditemukan{{ $warnings ? " · {$warnings} peringatan" : '' }}</div>
                <p class="mt-1 text-sm text-red-700/80 dark:text-red-400">Periksa baris berwarna merah di bawah.</p>
            @elseif ($warnings > 0)
                <div class="font-bold text-amber-700 dark:text-amber-400">✓ Tidak ada masalah fatal · {{ $warnings }} peringatan</div>
            @else
                <div class="font-bold text-green-700 dark:text-green-400">✓ Semua pemeriksaan lulus</div>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-2">Pemeriksaan</th>
                        <th class="px-4 py-2">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($checks as $check)
                        <tr class="{{ $check['status'] === 'error' ? 'bg-red-50 dark:bg-red-500/10' : ($check['status'] === 'warning' ? 'bg-amber-50 dark:bg-amber-500/10' : '') }}">
                            <td class="px-4 py-2 font-medium text-gray-700 dark:text-gray-300">
                                @if ($check['status'] === 'error') ⛔
                                @elseif ($check['status'] === 'warning') ⚠️
                                @else ✓
                                @endif
                                {{ $check['label'] }}
                            </td>
                            <td class="px-4 py-2 break-all {{ $check['status'] === 'error' ? 'font-semibold text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ $check['value'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="font-bold text-gray-950 dark:text-white">Bila halaman Transaksi Toko tetap gagal</h3>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-gray-600 dark:text-gray-400">
                <li>Pastikan baris <strong>Migrasi tertinggal</strong> di atas bernilai <em>tidak ada</em>.</li>
                <li>Pastikan <strong>Sesi dapat ditulis</strong> bernilai <em>ya</em>.</li>
                <li>Pastikan <strong>Manifest Vite</strong> bernilai <em>ada</em> (jalankan <code>npm ci &amp;&amp; npm run build</code>).</li>
                <li>Jalankan <code>php artisan optimize:clear</code> di server setelah <code>git pull</code>.</li>
                <li>Kirim isi <code>storage/logs/laravel.log</code> bagian paling akhir bila masih gagal.</li>
            </ol>
        </div>
    </div>
</x-filament-panels::page>
