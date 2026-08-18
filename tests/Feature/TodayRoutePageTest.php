<?php

namespace Tests\Feature;

use App\Filament\Pages\TodayRoute;
use App\Models\DailyRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TodayRoutePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_transaction_workspace_renders_without_runtime_errors(): void
    {
        $sales = User::factory()->create();
        DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
        ]);

        $this->actingAs($sales);

        Livewire::test(TodayRoute::class)
            ->assertOk()
            ->assertSee('Peta Rute Hari Ini')
            ->assertSee('Monitoring Kunjungan');
    }
}
