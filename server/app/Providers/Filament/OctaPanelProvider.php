<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Octa\Widgets\RecentOrders;
use App\Filament\Octa\Widgets\VendorOverview;
use App\Filament\Support\BrandLogo;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The vendor panel. Same `users` table/guard as Atlas — User::canAccessPanel()
 * is what actually keeps non-vendors out; only vendor resources are
 * registered here (app/Filament/Octa/Resources), scoped to the logged-in
 * vendor's own data.
 */
class OctaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('octa')
            ->path('octa')
            ->brandName('Octa')
            ->brandLogo(fn () => BrandLogo::html('Octa'))
            ->brandLogoHeight('2.5rem')
            ->login(Login::class)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Octa/Resources'), for: 'App\\Filament\\Octa\\Resources')
            ->pages([
                Pages\Dashboard::class,
            ])
            // No AccountWidget: it's just a "Welcome, {name}" card with a
            // logout link, and logout already lives in the topbar profile menu.
            ->widgets([
                VendorOverview::class,
                RecentOrders::class,
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
            ]);
    }
}
