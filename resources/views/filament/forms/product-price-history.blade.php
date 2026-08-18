@php
    $product = $getRecord();
    $prices = $product?->prices()->with('creator:id,name')->limit(20)->get() ?? collect();
@endphp

<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Histori perubahan harga</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Record lama dipertahankan untuk audit dan snapshot transaksi.</p>
        </div>
        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
            {{ $prices->count() }} versi
        </span>
    </div>

    @if (! $product)
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/15">
            Histori harga tersedia setelah produk pertama kali disimpan.
        </div>
    @elseif ($prices->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/15">
            Belum ada histori harga.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="pb-2 pr-4">Versi</th>
                        <th class="pb-2 pr-4">Per renteng</th>
                        <th class="pb-2 pr-4">Diubah</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($prices as $price)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-gray-700 dark:text-gray-200">#{{ $price->id }}</td>
                            <td class="py-3 pr-4 font-semibold text-gray-950 dark:text-white">Rp {{ number_format($price->price_per_renteng, 0, ',', '.') }}</td>
                            <td class="py-3 pr-4 text-gray-600 dark:text-gray-300">
                                {{ $price->created_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}
                                <span class="block text-xs text-gray-500">{{ $price->creator?->name ?? '-' }}</span>
                            </td>
                            <td class="py-3">
                                <span @class([
                                    'rounded-full px-2 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300' => $price->is_active,
                                    'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-400' => ! $price->is_active,
                                ])>
                                    {{ $price->is_active ? 'Saat ini' : 'Sebelumnya' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
