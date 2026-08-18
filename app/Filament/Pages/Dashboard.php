<?php

namespace App\Filament\Pages;

use App\Enums\StockLocationType;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Filament\Resources\DailyRoutes\DailyRouteResource;
use App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource;
use App\Filament\Resources\StockBalances\StockBalanceResource;
use App\Models\DailyRoute;
use App\Models\DailySalesSettlement;
use App\Models\StockLocation;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreAccountEntry;
use App\Models\StoreVisit;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected string $view = 'filament.pages.dashboard';

    public function getViewData(): array
    {
        return auth()->user()->isAdmin()
            ? $this->adminData()
            : $this->salesData();
    }

    private function adminData(): array
    {
        $routes = $this->todayRoutes();
        $visits = StoreVisit::query()
            ->with(['store:id,name', 'sales:id,name', 'items.product:id,name,pieces_per_renteng'])
            ->whereDate('completed_at', today())
            ->whereNull('reversed_at')
            ->latest('completed_at')
            ->get();
        $warehouses = StockLocation::query()
            ->with(['balances.product:id,name,pieces_per_renteng'])
            ->whereIn('type', [StockLocationType::MainWarehouse, StockLocationType::AdminWarehouse])
            ->orderBy('type')
            ->get();
        $debt = max(0, (int) StoreAccountEntry::query()->sum('amount'));
        $pendingReturns = StockTransfer::query()
            ->where('transfer_type', StockTransferType::ReturnFromSales)
            ->where('status', StockTransferStatus::PendingApproval)
            ->count();

        return [
            'mode' => 'admin',
            'routes' => $routes,
            'visits' => $visits,
            'warehouses' => $warehouses,
            'metrics' => [
                'collection' => (int) $visits->sum('amount_paid'),
                'debt' => $debt,
                'active_stores' => Store::query()->where('is_active', true)->count(),
                'finished_visits' => $visits->count(),
            ],
            'alerts' => [
                ['label' => 'Pengembalian menunggu konfirmasi', 'value' => $pendingReturns, 'tone' => $pendingReturns > 0 ? 'warning' : 'success'],
                ['label' => 'Rute masih berjalan', 'value' => $routes->filter(fn (DailyRoute $route) => $route->completed_stores_count < $route->route_stores_count)->count(), 'tone' => 'info'],
                ['label' => 'Toko memiliki hutang', 'value' => StoreAccountEntry::query()->select('store_id')->groupBy('store_id')->havingRaw('SUM(amount) > 0')->get()->count(), 'tone' => $debt > 0 ? 'danger' : 'success'],
            ],
            'quickActions' => [
                ['label' => 'Buat jadwal rute', 'description' => 'Tugaskan area dan toko kepada Sales', 'url' => DailyRouteResource::getUrl('create'), 'icon' => 'route'],
                ['label' => 'Kirim stok ke Sales', 'description' => 'Buat transaksi distribusi harian', 'url' => SalesStockTransferResource::getUrl('create'), 'icon' => 'truck'],
                ['label' => 'Pantau semua toko', 'description' => 'Buka peta persebaran dan status toko', 'url' => StoreMap::getUrl(), 'icon' => 'map'],
                ['label' => 'Lihat stok saat ini', 'description' => 'Periksa saldo setiap lokasi stok', 'url' => StockBalanceResource::getUrl(), 'icon' => 'stock'],
                ['label' => 'Rekap setoran Sales', 'description' => 'Hitung setoran, fee, dan ekspor Excel', 'url' => SalesDeposits::getUrl(), 'icon' => 'money'],
            ],
        ];
    }

    private function salesData(): array
    {
        $salesId = auth()->id();
        $routes = $this->todayRoutes($salesId);
        $route = $routes->first();
        $visits = StoreVisit::query()
            ->with(['store:id,name', 'items.product:id,name,pieces_per_renteng'])
            ->where('sales_id', $salesId)
            ->whereDate('completed_at', today())
            ->whereNull('reversed_at')
            ->latest('completed_at')
            ->get();
        $salesLocation = StockLocation::query()
            ->with(['balances.product:id,name,pieces_per_renteng'])
            ->where('type', StockLocationType::Sales)
            ->where('reference_id', $salesId)
            ->first();
        $monthFee = (int) DailySalesSettlement::query()
            ->where('sales_id', $salesId)
            ->whereYear('settlement_date', now()->year)
            ->whereMonth('settlement_date', now()->month)
            ->sum('sales_fee');

        return [
            'mode' => 'sales',
            'routes' => $routes,
            'visits' => $visits,
            'warehouses' => collect($salesLocation ? [$salesLocation] : []),
            'metrics' => [
                'collection' => (int) $visits->sum('amount_paid'),
                'debt' => (int) $visits->sum('debt_amount'),
                'active_stores' => (int) ($route?->route_stores_count ?? 0),
                'finished_visits' => (int) ($route?->completed_stores_count ?? 0),
                'month_fee' => $monthFee,
            ],
            'alerts' => [],
            'quickActions' => [
                ['label' => 'Mulai transaksi toko', 'description' => 'Buka peta dan daftar toko hari ini', 'url' => TodayRoute::getUrl(), 'icon' => 'route'],
                ['label' => 'Status distribusi', 'description' => 'Periksa pengiriman dan pengajuan Anda', 'url' => SalesStockTransferResource::getUrl(), 'icon' => 'truck'],
            ],
        ];
    }

    private function todayRoutes(?int $salesId = null): Collection
    {
        return DailyRoute::query()
            ->with(['sales:id,name', 'area:id,name', 'areas:id,name'])
            ->withCount('routeStores')
            ->withCount([
                'routeStores as completed_stores_count' => fn ($query) => $query->whereHas('latestVisit'),
            ])
            ->whereDate('route_date', today())
            ->when($salesId, fn ($query) => $query->where('sales_id', $salesId))
            ->orderBy('created_at')
            ->get();
    }
}
