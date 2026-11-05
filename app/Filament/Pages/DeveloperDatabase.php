<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\DatabaseMaintenance;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class DeveloperDatabase extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Database Developer';

    protected static ?string $title = 'Database Developer';

    protected static string|\UnitEnum|null $navigationGroup = 'Developer';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.developer-database';

    public ?string $selectedTable = null;

    public string $confirmation = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isDeveloper() ?? false;
    }

    public function getTablesProperty(): array
    {
        return app(DatabaseMaintenance::class)->tables();
    }

    public function chooseTable(string $table): void
    {
        $found = collect($this->tables)->firstWhere('name', $table);

        if (! $found || $found['protected']) {
            Notification::make()->title('Tabel ini dilindungi')->danger()->send();

            return;
        }

        $this->selectedTable = $table;
        $this->confirmation = '';
    }

    public function clearSelectedTable(): void
    {
        if (! $this->selectedTable || $this->confirmation !== $this->selectedTable) {
            Notification::make()
                ->title('Konfirmasi belum sesuai')
                ->body('Ketik nama tabel persis seperti yang ditampilkan.')
                ->danger()
                ->send();

            return;
        }

        $table = $this->selectedTable;

        try {
            $deleted = app(DatabaseMaintenance::class)->clear($table);
        } catch (ValidationException $exception) {
            Notification::make()->title('Tabel tidak dapat dikosongkan')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()->send();

            return;
        }

        if ($table !== 'audit_logs') {
            app(AuditLogger::class)->record(
                'developer.table_cleared',
                actor: auth()->user(),
                newValues: ['table' => $table, 'deleted_rows' => $deleted],
                description: "Developer mengosongkan tabel {$table} ({$deleted} baris).",
            );
        }

        $this->selectedTable = null;
        $this->confirmation = '';

        Notification::make()
            ->title("Tabel {$table} dikosongkan")
            ->body(number_format($deleted).' baris telah dihapus.')
            ->success()
            ->send();
    }
}
