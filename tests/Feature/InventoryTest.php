<?php

namespace Tests\Feature;

use App\Actions\AdjustStock;
use App\Actions\CompleteWarehouseTransfer;
use App\Actions\IssueStockTransfer;
use App\Actions\ReceiveStockTransfer;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\StockUnit;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_creates_balance_and_auditable_ledger(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $warehouse = app(StockLocationService::class)->warehouse();

        $movement = app(AdjustStock::class)->execute(
            $warehouse,
            $product,
            120,
            'Stok awal gudang',
            $admin,
            'Produksi Pancor Mas',
            true,
        );

        $this->assertSame(120, StockBalance::where('stock_location_id', $warehouse->id)->value('quantity_pieces'));
        $this->assertSame(StockMovementType::ProductionReceipt, $movement->movement_type);
        $this->assertSame(120, $movement->destination_balance_after);
        $this->assertSame('Stok awal gudang', $movement->notes);
        $this->assertSame('Produksi Pancor Mas', $movement->external_source);
    }

    public function test_new_production_receipts_accumulate_in_main_warehouse_and_keep_separate_history(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $mainWarehouse = app(StockLocationService::class)->mainWarehouse();

        app(AdjustStock::class)->execute($mainWarehouse, $product, 120, 'Produksi pertama', $admin, 'Produksi', true);
        app(AdjustStock::class)->execute($mainWarehouse, $product, 60, 'Produksi kedua', $admin, 'Produksi', true);

        $this->assertSame(180, $this->balance($mainWarehouse->id, $product->id));
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(
            [120, 60],
            StockMovement::query()->orderBy('id')->pluck('quantity_pieces')->all(),
        );
    }

    public function test_product_converts_production_input_from_renteng_or_pieces_to_base_pieces(): void
    {
        $product = Product::factory()->make(['pieces_per_renteng' => 12]);

        $this->assertSame(60, $product->convertStockToPieces(5, StockUnit::Renteng));
        $this->assertSame(5, $product->convertStockToPieces(5, StockUnit::Piece));
    }

    public function test_confirmed_transfer_moves_stock_from_warehouse_to_sales(): void
    {
        [$admin, $sales, $product, $transfer] = $this->preparedTransfer(120, 24);

        app(IssueStockTransfer::class)->execute($transfer, $admin);
        $received = app(ReceiveStockTransfer::class)->execute(
            $transfer,
            [['item_id' => $transfer->items->first()->id, 'quantity_received' => 24]],
            $sales,
        );

        $locations = app(StockLocationService::class);
        $this->assertSame(StockTransferStatus::Received, $received->status);
        $this->assertSame(96, $this->balance($locations->adminWarehouse()->id, $product->id));
        $this->assertSame(24, $this->balance($locations->forSales($sales)->id, $product->id));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity_pieces' => 24,
            'movement_type' => StockMovementType::TransferToSales->value,
            'reference_id' => $transfer->id,
        ]);
    }

    public function test_transfer_cannot_be_issued_when_warehouse_stock_is_insufficient(): void
    {
        [$admin, , , $transfer] = $this->preparedTransfer(10, 24);

        $this->expectException(ValidationException::class);

        app(IssueStockTransfer::class)->execute($transfer, $admin);
    }

    public function test_discrepancy_requires_notes_and_moves_actual_received_quantity(): void
    {
        [$admin, $sales, $product, $transfer] = $this->preparedTransfer(100, 24);
        app(IssueStockTransfer::class)->execute($transfer, $admin);
        $itemId = $transfer->items->first()->id;

        try {
            app(ReceiveStockTransfer::class)->execute(
                $transfer,
                [['item_id' => $itemId, 'quantity_received' => 20]],
                $sales,
            );
            $this->fail('Penerimaan berselisih tanpa catatan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discrepancy_notes', $exception->errors());
        }

        $this->assertSame(StockTransferStatus::Issued, $transfer->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 1);

        $received = app(ReceiveStockTransfer::class)->execute(
            $transfer,
            [['item_id' => $itemId, 'quantity_received' => 20]],
            $sales,
            'Kurang 4 bungkus saat diterima.',
        );

        $salesLocation = app(StockLocationService::class)->forSales($sales);
        $this->assertSame(StockTransferStatus::Discrepancy, $received->status);
        $this->assertSame(20, $this->balance($salesLocation->id, $product->id));
        $this->assertDatabaseHas('stock_transfer_items', [
            'id' => $itemId,
            'quantity_received' => 20,
            'discrepancy_quantity' => -4,
        ]);
    }

    public function test_sales_only_sees_own_transfers(): void
    {
        [$admin, $sales, , $transfer] = $this->preparedTransfer(100, 12);
        $otherSales = User::factory()->create();
        app(IssueStockTransfer::class)->execute($transfer, $admin);

        $this->assertTrue($sales->can('view', $transfer));
        $this->assertFalse($otherSales->can('view', $transfer));
        $this->actingAs($sales)->get('/admin/sales-stock-transfers')->assertOk();
        $this->actingAs($sales)->get('/admin/sales-stock-transfers/'.$transfer->id.'/edit')->assertOk();
        $this->actingAs($otherSales)->get('/admin/sales-stock-transfers/'.$transfer->id.'/edit')->assertNotFound();
        $this->actingAs($sales)->get('/admin/stock-transfers/'.$transfer->id.'/edit')->assertNotFound();
        $this->actingAs($admin)->get('/admin/stock-balances')->assertOk();
        $this->actingAs($admin)->get('/admin/stock-movements')->assertOk();
        $this->actingAs($admin)->get('/admin/stock-transfers/create')->assertOk();
    }

    public function test_stock_can_move_between_main_and_admin_warehouse(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $main = $locations->mainWarehouse();
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($main, $product, 100, 'Stok pusat', $admin);

        $transfer = $this->createTransfer(
            StockTransferType::WarehouseTransfer,
            $main->id,
            $adminWarehouse->id,
            null,
            $product->id,
            36,
            $admin->id,
        );

        app(IssueStockTransfer::class)->execute($transfer, $admin);
        $received = app(ReceiveStockTransfer::class)->execute(
            $transfer,
            [['item_id' => $transfer->items->first()->id, 'quantity_received' => 36]],
            $admin,
        );

        $this->assertSame(StockTransferStatus::Received, $received->status);
        $this->assertSame(64, $this->balance($main->id, $product->id));
        $this->assertSame(36, $this->balance($adminWarehouse->id, $product->id));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => StockMovementType::WarehouseTransfer->value,
            'quantity_pieces' => 36,
        ]);
    }

    public function test_transfer_accepts_renteng_and_converts_it_to_product_pieces(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $locations = app(StockLocationService::class);
        $main = $locations->mainWarehouse();
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($main, $product, 120, 'Persiapan', $admin);

        $transfer = StockTransfer::create([
            'transfer_type' => StockTransferType::WarehouseTransfer,
            'source_location_id' => $main->id,
            'destination_location_id' => $adminWarehouse->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $admin->id,
        ]);
        $item = $transfer->items()->create([
            'product_id' => $product->id,
            'quantity_entered' => 5,
            'quantity_unit' => StockUnit::Renteng,
        ]);

        $this->assertSame(5, $item->quantity_entered);
        $this->assertSame(60, $item->quantity_issued);
        $this->assertSame(StockUnit::Renteng, $item->quantity_unit);
    }

    public function test_warehouse_transfer_can_be_completed_immediately_after_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $main = $locations->mainWarehouse();
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($main, $product, 100, 'Persiapan', $admin);

        $transfer = $this->createTransfer(
            StockTransferType::WarehouseTransfer,
            $main->id,
            $adminWarehouse->id,
            null,
            $product->id,
            40,
            $admin->id,
        );

        $completed = app(CompleteWarehouseTransfer::class)->execute($transfer, $admin);

        $this->assertSame(StockTransferStatus::Received, $completed->status);
        $this->assertSame(60, $this->balance($main->id, $product->id));
        $this->assertSame(40, $this->balance($adminWarehouse->id, $product->id));
    }

    public function test_legacy_sales_draft_with_main_warehouse_source_is_normalized_to_admin_warehouse(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $main = $locations->mainWarehouse();
        $salesStock = $locations->forSales($sales);
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($adminWarehouse, $product, 50, 'Persiapan', $admin);

        $transfer = $this->createTransfer(
            StockTransferType::IssueToSales,
            $main->id,
            $salesStock->id,
            $sales->id,
            $product->id,
            10,
            $admin->id,
        );

        $issued = app(IssueStockTransfer::class)->execute($transfer, $admin);

        $this->assertSame(StockTransferStatus::Issued, $issued->status);
        $this->assertSame($adminWarehouse->id, $issued->source_location_id);
        $this->assertSame($salesStock->id, $issued->destination_location_id);
    }

    public function test_sales_remaining_stock_is_returned_and_confirmed_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $salesStock = $locations->forSales($sales);
        $adminWarehouse = $locations->adminWarehouse();
        app(AdjustStock::class)->execute($salesStock, $product, 30, 'Saldo bawaan pengujian', $admin);

        $transfer = $this->createTransfer(
            StockTransferType::ReturnFromSales,
            $salesStock->id,
            $adminWarehouse->id,
            $sales->id,
            $product->id,
            18,
            $admin->id,
        );
        app(IssueStockTransfer::class)->execute($transfer, $admin);

        try {
            app(ReceiveStockTransfer::class)->execute(
                $transfer,
                [['item_id' => $transfer->items->first()->id, 'quantity_received' => 18]],
                $sales,
            );
            $this->fail('Pengembalian stok Sales harus dikonfirmasi Admin.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('authorization', $exception->errors());
        }

        app(ReceiveStockTransfer::class)->execute(
            $transfer,
            [['item_id' => $transfer->items->first()->id, 'quantity_received' => 18]],
            $admin,
        );

        $this->assertSame(12, $this->balance($salesStock->id, $product->id));
        $this->assertSame(18, $this->balance($adminWarehouse->id, $product->id));
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => StockMovementType::SalesReturnToWarehouse->value,
            'quantity_pieces' => 18,
        ]);
    }

    private function preparedTransfer(int $warehouseStock, int $issuedQuantity): array
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $locations = app(StockLocationService::class);
        $warehouse = $locations->adminWarehouse();
        $salesLocation = $locations->forSales($sales);

        app(AdjustStock::class)->execute($warehouse, $product, $warehouseStock, 'Persiapan pengujian', $admin);

        $transfer = StockTransfer::create([
            'transfer_type' => StockTransferType::IssueToSales,
            'source_location_id' => $warehouse->id,
            'destination_location_id' => $salesLocation->id,
            'sales_id' => $sales->id,
            'status' => StockTransferStatus::Draft,
            'created_by' => $admin->id,
        ]);
        $transfer->items()->create([
            'product_id' => $product->id,
            'quantity_issued' => $issuedQuantity,
        ]);
        $transfer->load('items');

        return [$admin, $sales, $product, $transfer];
    }

    private function createTransfer(
        StockTransferType $type,
        int $sourceId,
        int $destinationId,
        ?int $salesId,
        int $productId,
        int $quantity,
        int $adminId,
    ): StockTransfer {
        $transfer = StockTransfer::create([
            'transfer_type' => $type,
            'source_location_id' => $sourceId,
            'destination_location_id' => $destinationId,
            'sales_id' => $salesId,
            'status' => StockTransferStatus::Draft,
            'created_by' => $adminId,
        ]);
        $transfer->items()->create([
            'product_id' => $productId,
            'quantity_issued' => $quantity,
        ]);

        return $transfer->load('items');
    }

    private function balance(int $locationId, int $productId): int
    {
        return (int) StockBalance::query()
            ->where('stock_location_id', $locationId)
            ->where('product_id', $productId)
            ->value('quantity_pieces');
    }
}
