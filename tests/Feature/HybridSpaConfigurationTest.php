<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class HybridSpaConfigurationTest extends TestCase
{
    public function test_safe_pages_use_spa_and_map_destinations_are_excluded(): void
    {
        $this->app->instance('request', Request::create('/admin/products'));

        $panel = Filament::getPanel('admin');
        $exceptions = $panel->getSpaUrlExceptions();

        $this->assertTrue($panel->hasSpaMode());
        $this->assertNotContains('*', $exceptions);
        $this->assertTrue(Str::is($exceptions, 'https://example.test/admin/store-map'));
        $this->assertTrue(Str::is($exceptions, 'https://example.test/admin/stores/42/edit'));
        $this->assertFalse(Str::is($exceptions, 'https://example.test/admin/products'));
    }

    public function test_all_outgoing_spa_navigation_is_disabled_on_a_map_page(): void
    {
        $this->app->instance('request', Request::create('/admin/store-map'));

        $this->assertSame(['*'], Filament::getPanel('admin')->getSpaUrlExceptions());
    }
}
