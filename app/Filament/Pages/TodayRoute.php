<?php

namespace App\Filament\Pages;

use App\Actions\OptimizeDailyRoute;
use App\Actions\RecordStoreTransaction;
use App\Actions\RecordStoreVisit;
use App\Data\Coordinate;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\Product;
use App\Models\Store;
use App\Services\NearbyStoreService;
use App\Services\ActiveSalesRoute;
use App\Services\StoreSettlementCalculator;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class TodayRoute extends Page
{
    /**
     * Akurasi di atas nilai ini (meter) dianggap bukan GPS asli, misalnya
     * lokasi berbasis jaringan/IP. Pada akurasi seburuk itu jarak ke toko tidak
     * dapat dipercaya, sehingga aturan jarak tidak dipakai.
     */
    public const IMPRECISE_ACCURACY_METERS = 1000;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Transaksi Toko';

    protected static ?string $title = 'Transaksi Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Aktivitas Sales';

    protected string $view = 'filament.pages.today-route';

    public array $nearbyStores = [];

    public ?float $currentLatitude = null;

    public ?float $currentLongitude = null;

    public ?float $currentAccuracy = null;

    /**
     * Diisi bila posisi tidak berasal dari GPS perangkat, misalnya karena
     * browser memblokir geolocation pada halaman non-HTTPS. Nilainya ikut
     * dicatat sebagai exception_reason pada kunjungan agar tetap terlacak.
     */
    public ?string $locationNote = null;

    public array $transactions = [];

    public ?int $selectedStoreId = null;

    public ?int $lastVisitId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSales() ?? false;
    }

    public function loadNearby(float $latitude, float $longitude, ?float $accuracy = null, ?string $note = null): void
    {
        $this->currentLatitude = $latitude;
        $this->currentLongitude = $longitude;
        $this->currentAccuracy = $accuracy;

        // Akurasi di atas ambang ini bukan GPS asli (biasanya lokasi berbasis
        // jaringan/IP dengan galat puluhan kilometer). Jarak ke toko jadi tidak
        // bermakna, jadi dicatat sebagai catatan lokasi agar transaksi tidak
        // ditolak oleh aturan jarak.
        if ($accuracy !== null && $accuracy > self::IMPRECISE_ACCURACY_METERS) {
            $kilometers = round($accuracy / 1000, 1);
            $this->locationNote = "Akurasi GPS rendah (±{$kilometers} km); lokasi tidak presisi";
        } else {
            $this->locationNote = $accuracy === null ? ($note ?? 'Lokasi diambil tanpa GPS perangkat') : $note;
        }

        $this->nearbyStores = app(NearbyStoreService::class)
            ->find(auth()->user(), new Coordinate($latitude, $longitude))
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'address' => $store->address,
                'distance_meters' => $store->distance_meters,
                'in_today_route' => $store->in_today_route,
                'visited' => $this->route?->routeStores
                    ->firstWhere('store_id', $store->id)?->latestVisit !== null,
            ])->all();
    }

    /**
     * Fallback ketika GPS perangkat benar-benar tidak dapat dipakai (mis. halaman
     * diakses lewat http:// sehingga browser memblokir geolocation). Koordinat
     * toko dipakai sebagai posisi, dan alasannya dicatat pada kunjungan supaya
     * data tidak diam-diam dianggap berasal dari GPS.
     */
    public function useStoreLocation(int $storeId): void
    {
        $store = $this->route?->routeStores->firstWhere('store_id', $storeId)?->store
            ?? Store::query()->find($storeId);

        if (! $store || $store->latitude === null || $store->longitude === null) {
            Notification::make()
                ->title('Koordinat toko belum tersedia')
                ->body('Minta Admin melengkapi koordinat toko ini.')
                ->danger()
                ->send();

            return;
        }

        $this->loadNearby(
            (float) $store->latitude,
            (float) $store->longitude,
            null,
            'GPS perangkat tidak tersedia; memakai koordinat toko',
        );

        Notification::make()
            ->title('Memakai koordinat toko')
            ->body('GPS perangkat tidak dapat dipakai. Transaksi akan ditandai memakai koordinat toko.')
            ->warning()
            ->send();
    }

    public function selectStore(int $storeId): void
    {
        $routeStore = $this->route?->routeStores->firstWhere('store_id', $storeId);

        if (! $routeStore || $routeStore->latestVisit) {
            Notification::make()->title('Toko tidak tersedia untuk transaksi')->warning()->send();

            return;
        }

        $this->selectedStoreId = $storeId;
        $this->dispatch('store-selected');
    }

    public function markVisit(int $storeId, string $status, ?string $reason = null): void
    {
        abort_if($this->currentLatitude === null || $this->currentLongitude === null, 422);
        app(RecordStoreVisit::class)->execute(
            auth()->user(),
            Store::findOrFail($storeId),
            new Coordinate($this->currentLatitude, $this->currentLongitude),
            VisitStatus::from($status),
            [
                'location_accuracy' => $this->currentAccuracy,
                'exception_reason' => $reason ?? $this->locationNote,
            ],
        );
        $this->selectedStoreId = null;
        $this->dispatch('store-visited', storeId: $storeId);
        $this->loadNearby($this->currentLatitude, $this->currentLongitude, $this->currentAccuracy, $this->locationNote);
        Notification::make()->title('Hasil kunjungan tersimpan')->success()->send();
    }

    public function saveTransaction(int $storeId): void
    {
        abort_if($this->currentLatitude === null || $this->currentLongitude === null, 422);

        $payment = data_get($this->transactions, "{$storeId}.payment_amount");
        $debtPayment = data_get($this->transactions, "{$storeId}.debt_payment_amount", 0);

        if (($payment === null || $payment === '') && $this->transactionSummary($storeId)['amount_due'] === 0) {
            $payment = 0;
        }

        if ($payment === null || $payment === '' || ! is_numeric($payment)) {
            Notification::make()
                ->title('Nominal pembayaran belum diisi')
                ->body('Masukkan uang yang benar-benar diterima dari toko. Isi 0 jika toko belum membayar.')
                ->warning()
                ->send();

            return;
        }

        if ($debtPayment === '' || $debtPayment === null) {
            $debtPayment = 0;
        }

        if (! is_numeric($debtPayment)) {
            Notification::make()->title('Nominal pembayaran hutang tidak valid')->warning()->send();

            return;
        }

        try {
            $visit = app(RecordStoreTransaction::class)->execute(
                auth()->user(),
                Store::findOrFail($storeId),
                new Coordinate($this->currentLatitude, $this->currentLongitude),
                $this->transactions[$storeId]['products'] ?? [],
                $this->currentAccuracy,
                (int) $payment,
                (int) $debtPayment,
                $this->locationNote,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Transaksi tidak dapat disimpan')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        unset($this->transactions[$storeId]);
        $this->lastVisitId = $visit->id;
        $this->selectedStoreId = null;
        $this->dispatch('store-visited', storeId: $storeId);
        $this->loadNearby($this->currentLatitude, $this->currentLongitude, $this->currentAccuracy, $this->locationNote);
        Notification::make()->title('Transaksi toko dan kunjungan tersimpan')->success()->send();
    }

    public function transactionPreview(int $storeId, int $productId): array
    {
        $sold = (int) data_get($this->transactions, "{$storeId}.products.{$productId}.sold", 0);
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return ['store_fee' => 0, 'amount_due' => 0];
        }

        try {
            return app(StoreSettlementCalculator::class)->calculate($product, $sold);
        } catch (ValidationException $exception) {
            // Produk tanpa harga aktif TIDAK boleh membuat seluruh halaman gagal
            // dimuat. Tampilkan pratinjau nol; pesan asli muncul saat menyimpan.
            return [
                'store_fee' => 0,
                'amount_due' => 0,
                'price_missing' => collect($exception->errors())->flatten()->first(),
            ];
        }
    }

    public function transactionSummary(int $storeId): array
    {
        $amountDue = $this->products->sum(fn (Product $product) => $this->transactionPreview($storeId, $product->id)['amount_due']);
        $payment = data_get($this->transactions, "{$storeId}.payment_amount");
        $debtPayment = data_get($this->transactions, "{$storeId}.debt_payment_amount");
        $amountPaid = is_numeric($payment) ? max(0, (int) $payment) : 0;
        $oldDebtPaid = is_numeric($debtPayment) ? max(0, (int) $debtPayment) : 0;

        // outstanding_debt adalah accessor yang membaca tabel store_account_entries.
        // Bila tabel/kolom itu belum ada (migrasi server tertinggal), halaman tidak
        // boleh gagal dimuat total; tampilkan hutang lama sebagai 0.
        try {
            $openingDebt = Store::query()->find($storeId)?->outstanding_debt ?? 0;
        } catch (QueryException $exception) {
            report($exception);
            $openingDebt = 0;
        }

        return [
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'opening_debt' => $openingDebt,
            'debt_payment_amount' => $oldDebtPaid,
            'total_paid' => $amountPaid + $oldDebtPaid,
            'debt_amount' => max(0, $amountDue - $amountPaid),
            'closing_debt' => max(0, $openingDebt + $amountDue - $amountPaid - $oldDebtPaid),
        ];
    }

    public function setFullPayment(int $storeId): void
    {
        $this->transactions[$storeId]['payment_amount'] = $this->transactionSummary($storeId)['amount_due'];
    }

    public function setFullDebtPayment(int $storeId): void
    {
        $this->transactions[$storeId]['debt_payment_amount'] = $this->transactionSummary($storeId)['opening_debt'];
    }

    public function recalculateRoute(): void
    {
        abort_if($this->currentLatitude === null || $this->currentLongitude === null, 422);
        $route = $this->route;
        abort_if(! $route, 404);

        app(OptimizeDailyRoute::class)->execute(
            $route,
            auth()->user(),
            new Coordinate($this->currentLatitude, $this->currentLongitude),
        );
        Notification::make()->title('Rute dihitung ulang dari lokasi Anda')->success()->send();
    }

    public function getRouteProperty(): ?DailyRoute
    {
        $route = app(ActiveSalesRoute::class)->find(auth()->user());

        return $route?->load([
            'area',
            'areas',
            'routeStores.store',
            'routeStores.latestVisit.items.product',
        ]);
    }

    public function getProductsProperty()
    {
        return Product::query()->with('currentPrice')->where('is_active', true)->orderBy('name')->get();
    }

    public function getSelectedStoreProperty(): ?Store
    {
        if (! $this->selectedStoreId) {
            return null;
        }

        return $this->route?->routeStores
            ->firstWhere('store_id', $this->selectedStoreId)?->store;
    }

    public function getRouteMapDataProperty(): array
    {
        $route = $this->route;

        if (! $route) {
            return ['features' => [], 'line' => []];
        }

        $features = $route->routeStores->filter(fn ($routeStore) => $routeStore->store->latitude && $routeStore->store->longitude)
            ->values()
            ->map(fn ($routeStore, int $index) => [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $routeStore->store->longitude, (float) $routeStore->store->latitude],
                ],
                'properties' => [
                    'id' => $routeStore->store_id,
                    'name' => $routeStore->store->name,
                    'address' => $routeStore->store->address,
                    'order' => $routeStore->optimized_order ?? $routeStore->planned_order ?? $index + 1,
                    'visited' => (bool) $routeStore->latestVisit,
                ],
            ])->all();

        $line = data_get($route->route_geometry, 'coordinates');

        if (! is_array($line) || $line === []) {
            $line = collect($features)->pluck('geometry.coordinates')->all();
        }

        return ['features' => $features, 'line' => $line];
    }

    public function getReceiptUrlProperty(): ?string
    {
        return $this->lastVisitId
            ? route('sales.receipts.show', ['visit' => $this->lastVisitId])
            : null;
    }

    public function getEscposUrlProperty(): ?string
    {
        return $this->lastVisitId
            ? route('sales.receipts.escpos', ['visit' => $this->lastVisitId])
            : null;
    }
}
