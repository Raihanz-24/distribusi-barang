<?php

namespace Tests\Feature;

use App\Filament\Resources\RouteAreas\Pages\CreateRouteArea;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateRouteAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_map_field_is_not_written_as_database_column(): void
    {
        $admin = User::factory()->admin()->create();
        $store = Store::factory()->create(['created_by' => $admin->id]);
        $this->actingAs($admin);

        Livewire::test(CreateRouteArea::class)
            ->fillForm([
                'name' => 'Area Kraksaan',
                'description' => 'Area pengujian',
                'is_active' => true,
                'stores' => [$store->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('route_areas', [
            'code' => 'AREA-0001',
            'name' => 'Area Kraksaan',
        ]);
        $this->assertDatabaseHas('route_area_store', ['store_id' => $store->id]);
    }
}
