<?php

namespace Tests\Feature;

use App\Enums\VisitStatus;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAccountEntry;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\StockLocationService;
use App\Services\StoreTransactionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTransactionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_contains_all_visits_in_latest_order_and_current_store_stock(): void
    {
        [$admin, $sales, $store, $product, $olderVisit, $latestVisit] = $this->reportFixture();
        $data = app(StoreTransactionReport::class)->data($store);

        $this->assertCount(2, $data['visits']);
        $this->assertSame($latestVisit->id, $data['visits']->first()->id);
        $this->assertSame($olderVisit->id, $data['visits']->last()->id);
        $this->assertSame(18, $data['stockTotal']);
        $this->assertSame(18, $data['stockBalances']->first()->quantity_pieces);
        $this->assertSame(5_000, $data['outstandingDebt']);
    }

    public function test_admin_can_export_an_individual_store_report(): void
    {
        [$admin, , $store] = $this->reportFixture();
        $response = $this->actingAs($admin)->get(route('reports.stores.export', $store));

        $response->assertOk();
        $response->assertDownload('laporan-toko-'.$store->code.'-'.now()->format('Y-m-d').'.xlsx');
    }

    public function test_unassigned_sales_cannot_export_another_store_report(): void
    {
        [, , $store] = $this->reportFixture();
        $this->actingAs(User::factory()->create())
            ->get(route('reports.stores.export', $store))
            ->assertForbidden();
    }

    public function test_store_page_shows_latest_transaction_and_collapsed_previous_history(): void
    {
        [$admin, , $store] = $this->reportFixture();
        $this->actingAs($admin)
            ->get(StoreResource::getUrl('view', ['record' => $store]))
            ->assertOk()
            ->assertSee('Export Excel Toko')
            ->assertSee('Sisa stok di toko')
            ->assertSee('Transaksi terakhir')
            ->assertSee('Riwayat sebelumnya')
            ->assertSee('Klik transaksi untuk melihat detail');
    }

    private function reportFixture(): array
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $store = Store::factory()->create(['created_by' => $admin->id]);
        $product = Product::factory()->create([
            'name' => 'Kopi Premium',
            'code' => 'KOPI-PREMIUM',
            'pieces_per_renteng' => 12,
        ]);
        app(StockLocationService::class)->forStore($store)->balances()->create([
            'product_id' => $product->id,
            'quantity_pieces' => 18,
        ]);

        $olderVisit = $this->createVisit($store, $sales, $product, 1, now()->subDay(), 20_000, 20_000, 12);
        $latestVisit = $this->createVisit($store, $sales, $product, 2, now(), 10_000, 5_000, 6);

        foreach ([['charge', 10_000], ['payment', -5_000]] as [$type, $amount]) {
            StoreAccountEntry::query()->create([
                'store_id' => $store->id,
                'store_visit_id' => $latestVisit->id,
                'sales_id' => $sales->id,
                'entry_type' => $type,
                'amount' => $amount,
                'notes' => 'Pengujian laporan toko',
                'occurred_at' => now(),
                'recorded_by' => $sales->id,
            ]);
        }

        return [$admin, $sales, $store, $product, $olderVisit, $latestVisit];
    }

    private function createVisit(Store $store, User $sales, Product $product, int $number, $completedAt, int $due, int $paid, int $sold): StoreVisit
    {
        $visit = StoreVisit::query()->create([
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'visit_number' => $number,
            'visit_status' => VisitStatus::Visited,
            'amount_due' => $due,
            'amount_paid' => $paid,
            'debt_amount' => max(0, $due - $paid),
            'arrived_at' => $completedAt->copy()->subMinutes(10),
            'completed_at' => $completedAt,
            'latitude' => -7.75,
            'longitude' => 113.42,
        ]);
        $visit->items()->create([
            'product_id' => $product->id,
            'sold_pieces' => $sold,
            'deposited_pieces' => 12,
            'withdrawn_pieces' => $number === 2 ? 2 : 0,
            'retail_price_per_piece' => 2_000,
            'price_per_renteng' => 20_000,
            'store_fee' => $sold === 12 ? 4_000 : 2_000,
            'amount_due' => $due,
        ]);

        return $visit;
    }
}
