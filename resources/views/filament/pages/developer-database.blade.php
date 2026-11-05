<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl border-2 border-red-500/30 bg-gradient-to-br from-stone-950 via-red-950 to-stone-900 p-5 text-white shadow-lg md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-[.18em] text-red-300">Khusus Developer</div>
                    <h2 class="mt-2 text-2xl font-black">Pemeliharaan Database</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-red-100/80">Unduh backup sebelum menghapus data. Pengosongan tabel tidak dapat dibatalkan dan dapat memengaruhi data yang saling terhubung.</p>
                </div>
                <a href="{{ route('developer.database.backup') }}" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-xl bg-white px-5 text-sm font-bold text-red-800 shadow hover:bg-red-50">⬇ Unduh Backup SQL</a>
            </div>
        </section>

        <section class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-900 dark:text-amber-200">
            <strong>Pengaman:</strong> tabel <code>users</code> dan <code>migrations</code> tidak dapat dikosongkan dari halaman ini. Akun Developer dan riwayat migrasi tetap dipertahankan.
        </section>

        @if ($selectedTable)
            <section class="rounded-2xl border-2 border-red-500 bg-white p-5 shadow-lg dark:bg-gray-900">
                <h3 class="font-black text-red-700 dark:text-red-300">Kosongkan tabel {{ $selectedTable }}?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Ketik <strong>{{ $selectedTable }}</strong> untuk mengonfirmasi. Semua baris pada tabel ini akan dihapus.</p>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                    <input wire:model="confirmation" class="fi-input min-h-11 flex-1" placeholder="Ketik {{ $selectedTable }}">
                    <button type="button" wire:click="clearSelectedTable" wire:loading.attr="disabled" class="min-h-11 rounded-xl bg-red-600 px-5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50">Hapus seluruh isi</button>
                    <button type="button" wire:click="$set('selectedTable', null)" class="min-h-11 rounded-xl border border-gray-300 px-5 text-sm font-bold dark:border-white/10">Batal</button>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10"><h3 class="font-bold">Daftar Tabel</h3><p class="mt-1 text-xs text-gray-500">Pilih satu tabel untuk dikosongkan. Buat backup terlebih dahulu.</p></div>
            <div class="grid gap-3 p-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->tables as $table)
                    <article class="flex items-center justify-between gap-3 rounded-xl border p-4 {{ $table['protected'] ? 'border-green-500/20 bg-green-500/5' : 'border-gray-200 dark:border-white/10' }}">
                        <div class="min-w-0"><div class="truncate font-mono text-sm font-bold">{{ $table['name'] }}</div><div class="mt-1 text-xs text-gray-500">{{ number_format($table['rows']) }} baris</div></div>
                        @if ($table['protected'])
                            <span class="rounded-full bg-green-500/10 px-2 py-1 text-[10px] font-bold text-green-700 dark:text-green-300">DILINDUNGI</span>
                        @else
                            <button type="button" wire:click="chooseTable(@js($table['name']))" class="rounded-lg bg-red-500/10 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-500/20 dark:text-red-300">Kosongkan</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
