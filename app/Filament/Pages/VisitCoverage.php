<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VisitCoverage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Cakupan Kunjungan';

    protected static ?string $title = 'Cakupan Kunjungan Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Peta & Rute';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.visit-coverage';

    public string $period = 'this_month';

    public string $status = 'all';

    public string $search = '';

    public ?int $salesId = null;

    public function mount(): void
    {
        if (auth()->user()->isSales()) {
            $this->salesId = auth()->id();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return ($user?->is_active ?? false) && ($user->isAdmin() || $user->isSales());
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        return match ($this->period) {
            'today' => [today()->startOfDay(), today()->endOfDay()],
            'this_week' => [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function visitConstraint($query)
    {
        [$from, $until] = $this->range();

        return $query->whereNull('reversed_at')
            ->whereBetween('arrived_at', [$from, $until])
            ->when($this->salesId, fn (Builder $query) => $query->where('sales_id', $this->salesId));
    }

    public function getRowsProperty()
    {
        return Store::query()
            ->with([
                'latestVisit.sales:id,name',
                'visits' => fn ($query) => $this->visitConstraint($query)
                    ->with('sales:id,name')
                    ->latest('arrived_at'),
            ])
            ->where('is_active', true)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = trim($this->search);
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('village', 'like', "%{$term}%")
                    ->orWhere('district', 'like', "%{$term}%"));
            })
            ->when($this->status === 'visited', fn (Builder $query) => $query->whereHas('visits', fn (Builder $visit) => $this->visitConstraint($visit)))
            ->when($this->status === 'unvisited', fn (Builder $query) => $query->whereDoesntHave('visits', fn (Builder $visit) => $this->visitConstraint($visit)))
            ->when($this->status === 'never', fn (Builder $query) => $query->whereDoesntHave('visits', fn (Builder $visit) => $visit->whereNull('reversed_at')))
            ->orderBy('name')
            ->limit(250)
            ->get();
    }

    public function getSummaryProperty(): array
    {
        $base = Store::query()->where('is_active', true);
        $total = (clone $base)->count();
        $visited = (clone $base)->whereHas('visits', fn (Builder $visit) => $this->visitConstraint($visit))->count();

        return [
            'total' => $total,
            'visited' => $visited,
            'unvisited' => max(0, $total - $visited),
            'coverage' => $total > 0 ? (int) round(($visited / $total) * 100) : 0,
        ];
    }

    public function getSalesOptionsProperty(): array
    {
        if (! auth()->user()->isAdmin()) {
            return [];
        }

        return User::query()->where('role', 'sales')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }
}
