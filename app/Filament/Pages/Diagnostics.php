<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Halaman diagnosa mandiri: menampilkan kondisi lingkungan yang paling sering
 * membuat halaman Filament gagal dimuat di server (migrasi tertinggal, sesi
 * tidak dapat ditulis, aset Vite belum di-build, dsb.).
 */
class Diagnostics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Diagnosa Sistem';

    protected static ?string $title = 'Diagnosa Sistem';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.diagnostics';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /**
     * Halaman ini hanya untuk keperluan pengecekan internal dan tidak
     * ditampilkan pada menu navigasi.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{label: string, value: string, status: string}>
     */
    public function getChecksProperty(): array
    {
        return [
            ...$this->environmentChecks(),
            ...$this->databaseChecks(),
            ...$this->sessionChecks(),
            ...$this->assetChecks(),
            ...$this->dataChecks(),
        ];
    }

    private function check(string $label, string $value, bool $ok, bool $warning = false): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'status' => $ok ? 'ok' : ($warning ? 'warning' : 'error'),
        ];
    }

    private function environmentChecks(): array
    {
        $storageWritable = is_writable(storage_path());
        $logWritable = is_writable(storage_path('logs')) && (is_writable(storage_path('logs/laravel.log')) || ! file_exists(storage_path('logs/laravel.log')));

        return [
            $this->check('PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=')),
            $this->check('Laravel', app()->version(), true),
            $this->check('APP_ENV', (string) app()->environment(), true),
            $this->check('APP_DEBUG', config('app.debug') ? 'aktif' : 'nonaktif', true),
            $this->check('APP_URL', (string) config('app.url'), true),
            $this->check('URL diakses', request()->getSchemeAndHttpHost(), true),
            $this->check('Storage dapat ditulis', $storageWritable ? 'ya' : 'tidak', $storageWritable),
            $this->check('Folder log dapat ditulis', $logWritable ? 'ya' : 'tidak', $logWritable),
            $this->check('HTTPS terdeteksi', $this->viaHttps() ? 'ya' : 'tidak', true),
            $this->check('vendor terpasang', is_dir(base_path('vendor')) ? 'ya' : 'tidak', is_dir(base_path('vendor'))),
        ];
    }

    private function databaseChecks(): array
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks[] = $this->check('Koneksi database', (string) config('database.default'), true);
        } catch (Throwable $exception) {
            $checks[] = $this->check('Koneksi database', $exception->getMessage(), false);

            return $checks;
        }

        try {
            $pending = $this->pendingMigrations();
            $checks[] = $this->check(
                'Migrasi tertinggal',
                $pending === [] ? 'tidak ada' : count($pending).' tertinggal: '.implode(', ', array_slice($pending, 0, 5)),
                $pending === [],
            );
        } catch (Throwable $exception) {
            $checks[] = $this->check('Status migrasi', $exception->getMessage(), false);
        }

        $required = [
            'store_visits' => ['reversed_at', 'reversed_by', 'reversal_reason', 'opening_debt', 'closing_debt'],
            'products' => ['is_active', 'pieces_per_renteng'],
            'product_prices' => ['price_per_renteng', 'is_active'],
            'store_account_entries' => ['store_id', 'entry_type', 'amount'],
            'daily_routes' => ['optimization_status'],
            'sales_locations' => ['latitude', 'longitude'],
            'audit_logs' => ['action'],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $checks[] = $this->check("Tabel {$table}", 'TIDAK ADA', false);

                continue;
            }

            $missing = array_values(array_filter($columns, fn (string $column): bool => ! Schema::hasColumn($table, $column)));

            $checks[] = $this->check(
                "Kolom tabel {$table}",
                $missing === [] ? 'lengkap' : 'hilang: '.implode(', ', $missing),
                $missing === [],
            );
        }

        return $checks;
    }

    /**
     * @return array<int, string>
     */
    private function pendingMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return ['tabel migrations tidak ada'];
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = [];
        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $files[] = basename($file, '.php');
        }

        return array_values(array_diff($files, $ran));
    }

    private function sessionChecks(): array
    {
        $driver = (string) config('session.driver');
        $checks = [
            $this->check('Driver sesi', $driver, true),
            $this->check('SESSION_DOMAIN', var_export(config('session.domain'), true), true),
            $this->check('Cookie secure', config('session.secure') ? 'ya' : 'tidak', true),
            $this->check('SESSION_SAME_SITE', (string) config('session.same_site'), true),
        ];

        if ($driver === 'database') {
            $hasTable = Schema::hasTable('sessions');
            $checks[] = $this->check('Tabel sessions', $hasTable ? 'ada' : 'TIDAK ADA', $hasTable);
        }

        if ($driver === 'file') {
            $path = storage_path('framework/sessions');
            $writable = is_dir($path) && is_writable($path);
            $checks[] = $this->check('Folder sesi dapat ditulis', $writable ? 'ya' : 'tidak', $writable);
        }

        // Sesi harus dapat ditulis; kalau tidak, POST Livewire gagal dengan 419/500
        // sementara GET halaman tetap sukses — gejala "gagal memuat halaman".
        try {
            session()->put('__diagnostics_probe', now()->timestamp);
            session()->save();
            $checks[] = $this->check('Sesi dapat ditulis', 'ya', true);
        } catch (Throwable $exception) {
            $checks[] = $this->check('Sesi dapat ditulis', $exception->getMessage(), false);
        }

        $checks[] = $this->check('Token CSRF tersedia', session()->token() ? 'ya' : 'tidak', (bool) session()->token());

        return $checks;
    }

    private function assetChecks(): array
    {
        $manifest = public_path('build/manifest.json');
        $hot = public_path('hot');
        $checks = [
            $this->check('Manifest Vite', file_exists($manifest) ? 'ada' : 'TIDAK ADA', file_exists($manifest)),
            $this->check('File public/hot', file_exists($hot) ? 'ADA (mode dev)' : 'tidak ada', ! file_exists($hot), file_exists($hot)),
        ];

        if (file_exists($manifest)) {
            $checks[] = $this->check('Manifest terakhir diubah', date('d/m/Y H:i', (int) filemtime($manifest)), true);
        }

        return $checks;
    }

    private function dataChecks(): array
    {
        $checks = [];

        try {
            $total = DB::table('products')->where('is_active', true)->count();

            $tanpaHarga = DB::table('products')
                ->where('products.is_active', true)
                ->whereNotExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('product_prices')
                        ->whereColumn('product_prices.product_id', 'products.id')
                        ->where('product_prices.is_active', true);
                })
                ->pluck('products.name')
                ->all();

            $checks[] = $this->check(
                'Produk aktif tanpa harga aktif',
                $tanpaHarga === [] ? 'tidak ada' : count($tanpaHarga).': '.implode(', ', array_slice($tanpaHarga, 0, 5)),
                $tanpaHarga === [],
                $tanpaHarga !== [],
            );
            $checks[] = $this->check('Total produk aktif', (string) $total, true);
        } catch (Throwable $exception) {
            $checks[] = $this->check('Pemeriksaan produk', $exception->getMessage(), false);
        }

        try {
            $checks[] = $this->check('Rute hari ini', (string) DB::table('daily_routes')->whereDate('route_date', today())->count(), true);
        } catch (Throwable $exception) {
            $checks[] = $this->check('Pemeriksaan rute', $exception->getMessage(), false);
        }

        return $checks;
    }

    private function viaHttps(): bool
    {
        return request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https';
    }
}
