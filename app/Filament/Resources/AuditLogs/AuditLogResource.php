<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Audit Aktivitas';

    protected static ?string $modelLabel = 'Audit Aktivitas';

    protected static ?string $pluralModelLabel = 'Audit Aktivitas';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y, H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Pelaku')->placeholder('Sistem')->searchable(),
                TextColumn::make('action')->label('Aktivitas')->badge()->searchable(),
                TextColumn::make('subject_type')->label('Objek')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
                TextColumn::make('subject_id')->label('ID')->placeholder('-'),
                TextColumn::make('description')->label('Keterangan')->wrap()->limit(80)->placeholder('-'),
                TextColumn::make('ip_address')->label('IP')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Pelaku')->relationship('user', 'name')->searchable()->preload(),
                SelectFilter::make('action')->label('Aktivitas')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
