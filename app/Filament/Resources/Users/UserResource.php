<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas dan akses')
                ->description('Gunakan satu akun untuk satu orang. Nonaktifkan akun ketika orang tersebut tidak lagi bertugas.')
                ->schema([
                    TextInput::make('name')->label('Nama lengkap')->required()->maxLength(255),
                    TextInput::make('username')
                        ->label('Username')
                        ->required()
                        ->alphaDash()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText('Gunakan huruf kecil, angka, tanda hubung, atau garis bawah.'),
                    TextInput::make('email')
                        ->label('Email pemulihan')
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Opsional, tetapi dianjurkan untuk pemulihan akun.'),
                    Select::make('role')
                        ->label('Peran')
                        ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()]))
                        ->required()
                        ->native(false),
                    Toggle::make('is_active')->label('Akun aktif')->default(true),
                    TextInput::make('phone')->label('Nomor telepon')->tel()->maxLength(30),
                    Textarea::make('address')->label('Alamat')->rows(3)->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 2]),
            Section::make('Kata sandi')
                ->description('Pada halaman edit, kosongkan jika kata sandi tidak ingin diubah.')
                ->schema([
                    TextInput::make('password')
                        ->label('Kata sandi baru')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->rule(\Illuminate\Validation\Rules\Password::min(8)->letters()->numbers())
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state)),
                    TextInput::make('password_confirmation')
                        ->label('Ulangi kata sandi')
                        ->password()
                        ->revealable()
                        ->same('password')
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(false),
                ])
                ->columns(['default' => 1, 'md' => 2]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('username')->label('Username')->searchable()->copyable(),
                TextColumn::make('role')->label('Peran')->badge()->formatStateUsing(fn (UserRole $state) => $state->label()),
                TextColumn::make('phone')->label('Telepon')->placeholder('-'),
                TextColumn::make('is_active')->label('Status')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peran')->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])),
                TernaryFilter::make('is_active')->label('Status akun'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
