<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SalesDepositReport;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SalesDeposits extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Setoran Sales';

    protected static ?string $title = 'Setoran Sales';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.sales-deposits';

    public string $startDate = '';

    public string $endDate = '';

    public string $salesId = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->setThisMonth(notify: false);
    }

    public function setToday(bool $notify = true): void
    {
        $this->startDate = today()->toDateString();
        $this->endDate = today()->toDateString();

        if ($notify) {
            $this->notifyReportUpdated('Rekap hari ini ditampilkan');
        }
    }

    public function setThisMonth(bool $notify = true): void
    {
        $this->startDate = today()->startOfMonth()->toDateString();
        $this->endDate = today()->endOfMonth()->toDateString();

        if ($notify) {
            $this->notifyReportUpdated('Rekap bulan ini ditampilkan');
        }
    }

    public function applyReport(): void
    {
        $this->validate([
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'salesId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->notifyReportUpdated('Rekap setoran diperbarui');
    }

    public function resetFilters(): void
    {
        $this->salesId = '';
        $this->setThisMonth(notify: false);
        $this->notifyReportUpdated('Filter dikembalikan ke bulan ini');
    }

    public function getViewData(): array
    {
        $filters = $this->filters();
        $report = app(SalesDepositReport::class);
        $rows = $report->rows($filters);

        return [
            'rows' => $rows,
            'summary' => $report->summary($rows),
            'salesOptions' => User::query()->where('role', 'sales')->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'exportUrl' => route('reports.sales-deposits.export', $filters),
        ];
    }

    private function filters(): array
    {
        return [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'sales_id' => $this->salesId ?: null,
        ];
    }

    private function notifyReportUpdated(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
