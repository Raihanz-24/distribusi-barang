<?php

namespace Tests\Feature;

use App\Enums\SalesSettlementStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailyRoute;
use App\Models\DailySalesSettlement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\SalesDepositReport;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDepositReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_reconciles_expected_and_actual_deposit_without_displaying_fee(): void
    {
        [$admin, $settlement] = $this->makeSettlement();
        $this->makeSettlement(SalesSettlementStatus::Pending);
        $report = app(SalesDepositReport::class);
        $rows = $report->rows([
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame([
            'records' => 1,
            'expected' => 100_000,
            'actual' => 100_000,
            'difference' => 0,
            'debt' => 0,
            'stores' => 5,
            'stock_shortage' => 0,
        ], $report->summary($rows));
        $this->assertSame($settlement->id, $rows->first()->id);

        $filename = 'rekap-setoran-sales-'.today()->toDateString().'-'.today()->toDateString().'.xlsx';
        $this->actingAs($admin)
            ->get(route('reports.sales-deposits.export', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertDownload($filename);
    }

    public function test_sales_cannot_export_the_admin_deposit_report(): void
    {
        $sales = User::factory()->create();

        $this->actingAs($sales)
            ->get(route('reports.sales-deposits.export', [
                'start_date' => today()->toDateString(),
                'end_date' => today()->toDateString(),
            ]))
            ->assertForbidden();
    }

    private function makeSettlement(SalesSettlementStatus $status = SalesSettlementStatus::Approved): array
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $locations = app(StockLocationService::class);
        $pickup = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $locations->adminWarehouse()->id,
            'destination_location_id' => $locations->forSales($sales)->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $admin->id,
        ]);
        $return = StockTransfer::create([
            'transfer_type' => StockTransferType::ReturnFromSales,
            'pickup_transfer_id' => $pickup->id,
            'source_location_id' => $locations->forSales($sales)->id,
            'destination_location_id' => $locations->adminWarehouse()->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::Received,
            'created_by' => $sales->id,
        ]);
        $route = DailyRoute::factory()->create([
            'sales_id' => $sales->id,
            'route_date' => today(),
            'created_by' => $admin->id,
        ]);
        $settlement = DailySalesSettlement::create([
            'pickup_transfer_id' => $pickup->id,
            'return_transfer_id' => $return->id,
            'daily_route_id' => $route->id,
            'sales_id' => $sales->id,
            'settlement_date' => today(),
            'status' => $status,
            'total_stores' => 5,
            'total_sold_pieces' => 60,
            'total_collection' => 100_000,
            'equivalent_renteng' => 5,
            'sales_fee' => 17_500,
            'submitted_at' => now(),
        ]);

        return [$admin, $settlement];
    }
}
