<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureUserIsActive;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('Kopi Pancor Mas')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('3rem')
            ->sidebarWidth('18.5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->spa()
            ->spaUrlExceptions(function (): array {
                $unsafePaths = [
                    'admin/login',
                    'admin/store-map',
                    'admin/today-route',
                    'admin/stores/create',
                    'admin/stores/*/edit',
                    'admin/route-areas/create',
                    'admin/route-areas/*/edit',
                    'admin/daily-routes/create',
                    'admin/daily-routes/*/edit',
                ];

                // Map/GPS pages also use full reloads when navigating away.
                if (request()->is($unsafePaths)) {
                    return ['*'];
                }

                return array_map(
                    static fn (string $path): string => "*/{$path}*",
                    $unsafePaths,
                );
            })
            ->colors([
                'primary' => Color::hex('#c97832'),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.pwa-head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.sales-location-tracker'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureUserIsActive::class,
            ]);
    }
}
