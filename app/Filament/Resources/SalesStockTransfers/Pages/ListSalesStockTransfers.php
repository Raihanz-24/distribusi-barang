<?php

namespace App\Filament\Resources\SalesStockTransfers\Pages;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource;
use App\Livewire\Admin\DailyStoreMonitoringTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListSalesStockTransfers extends ListRecords
{
    protected static string $resource = SalesStockTransferResource::class;

    public function getTabs(): array
    {
        return [
            'draft' => Tab::make('Draf')
                ->icon('heroicon-o-document')
                ->badge(fn () => $this->tabCount(status: StockTransferStatus::Draft))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StockTransferStatus::Draft)),
            'pickup' => Tab::make('Pengiriman')
                ->icon('heroicon-o-truck')
                ->badge(fn () => $this->tabCount(type: StockTransferType::IssueToSales))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('transfer_type', StockTransferType::IssueToSales)
                    ->where('status', '!=', StockTransferStatus::Draft)
                    ->whereDoesntHave('returnTransfer')),
            'proposal' => Tab::make('Pengajuan')
                ->icon('heroicon-o-clipboard-document-check')
                ->badge(fn () => $this->tabCount(status: StockTransferStatus::PendingApproval))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('transfer_type', StockTransferType::ReturnFromSales)
                    ->where('status', StockTransferStatus::PendingApproval)),
            'return' => Tab::make('Pengembalian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->badge(fn () => $this->tabCount(type: StockTransferType::ReturnFromSales))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('transfer_type', StockTransferType::ReturnFromSales)
                    ->whereIn('status', [StockTransferStatus::Received, StockTransferStatus::Discrepancy])),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            View::make('filament.stock-transfers.mobile-distribution-cards')
                ->viewData(fn () => ['records' => $this->getMobileDistributionRecords()])
                ->extraAttributes(['class' => 'distribution-mobile-cards']),
            Group::make([EmbeddedTable::make()])
                ->extraAttributes(['class' => 'distribution-desktop-table']),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ...(auth()->user()->isAdmin()
                ? [Livewire::make(DailyStoreMonitoringTable::class)->key('daily-store-monitoring')]
                : []),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Transaksi')
                ->visible(fn () => auth()->user()->isAdmin()),
        ];
    }

    private function tabCount(?StockTransferStatus $status = null, ?StockTransferType $type = null): int
    {
        return SalesStockTransferResource::getEloquentQuery()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($type, function (Builder $query) use ($type): void {
                $query->where('transfer_type', $type)
                    ->where('status', '!=', StockTransferStatus::Draft);

                if ($type === StockTransferType::IssueToSales) {
                    $query->whereDoesntHave('returnTransfer');
                }
            })
            ->count();
    }

    public function getMobileDistributionRecords()
    {
        return SalesStockTransferResource::getEloquentQuery()
            ->with([
                'sales.latestLocation', 'dailyRoute', 'items.product', 'returnSettlement', 'pickupSettlement',
                'pickupTransfer.items.product', 'returnTransfer.items.product',
            ])
            ->when($this->activeTab === 'draft', fn (Builder $query) => $query->where('status', StockTransferStatus::Draft))
            ->when($this->activeTab === 'pickup', fn (Builder $query) => $query
                ->where('transfer_type', StockTransferType::IssueToSales)
                ->where('status', '!=', StockTransferStatus::Draft)
                ->whereDoesntHave('returnTransfer'))
            ->when($this->activeTab === 'proposal', fn (Builder $query) => $query
                ->where('transfer_type', StockTransferType::ReturnFromSales)
                ->where('status', StockTransferStatus::PendingApproval))
            ->when($this->activeTab === 'return', fn (Builder $query) => $query
                ->where('transfer_type', StockTransferType::ReturnFromSales)
                ->whereIn('status', [StockTransferStatus::Received, StockTransferStatus::Discrepancy]))
            ->latest()
            ->limit(25)
            ->get();
    }
}
