<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\StoreVisitItem;
use App\Models\User;
use App\Services\EscPos\ReceiptEscPosBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptEscPosTest extends TestCase
{
    use RefreshDatabase;

    private function makeVisit(): StoreVisit
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sales Satu']);
        $store = Store::factory()->create(['name' => 'Toko Maju Jaya']);

        $visit = StoreVisit::query()->create([
            'store_id' => $store->id,
            'sales_id' => $sales->id,
            'visit_number' => 1,
            'visit_status' => VisitStatus::Visited,
            'arrived_at' => now(),
            'completed_at' => now(),
            'latitude' => -7.75,
            'longitude' => 113.41,
            'amount_due' => 50000,
            'amount_paid' => 40000,
            'current_payment_amount' => 40000,
            'opening_debt' => 10000,
            'debt_payment_amount' => 5000,
            'closing_debt' => 15000,
        ]);

        $product = Product::factory()->create([
            'name' => 'Kopi Pancor Mas Spesial',
            'pieces_per_renteng' => 10,
        ]);

        StoreVisitItem::query()->create([
            'store_visit_id' => $visit->id,
            'product_id' => $product->id,
            'sold_pieces' => 25,
            'deposited_pieces' => 5,
            'withdrawn_pieces' => 3,
            'retail_price_per_piece' => 2000,
            'price_per_renteng' => 20000,
            'store_fee' => 2500,
            'amount_due' => 50000,
        ]);

        return $visit->fresh(['store', 'sales', 'items.product']);
    }

    public function test_starts_with_printer_init_command(): void
    {
        $bytes = app(ReceiptEscPosBuilder::class)->build($this->makeVisit());

        $this->assertStringStartsWith("\x1B@", $bytes, 'Harus diawali ESC @ (inisialisasi printer).');
    }

    public function test_ends_with_cut_command(): void
    {
        $bytes = app(ReceiptEscPosBuilder::class)->build($this->makeVisit());

        $this->assertStringEndsWith("\x1DV\x00", $bytes, 'Harus diakhiri perintah potong kertas.');
    }

    public function test_contains_expected_receipt_content(): void
    {
        $bytes = app(ReceiptEscPosBuilder::class)->build($this->makeVisit());

        $this->assertStringContainsString('PANCOR MAS', $bytes);
        $this->assertStringContainsString('Toko Maju Jaya', $bytes);
        $this->assertStringContainsString('Sales Satu', $bytes);
        $this->assertStringContainsString('KJN-000001', $bytes);
        $this->assertStringContainsString('DIBAYARKAN', $bytes);
        $this->assertStringContainsString('Rp 40.000', $bytes);
    }

    public function test_lines_never_exceed_print_area_for_58mm(): void
    {
        config(['sales.receipt.paper_width_mm' => 58]);

        $bytes = app(ReceiptEscPosBuilder::class)->build($this->makeVisit());

        foreach (explode("\n", $bytes) as $line) {
            // Abaikan baris yang mengandung byte kontrol ESC/POS.
            if (str_contains($line, "\x1B") || str_contains($line, "\x1D")) {
                continue;
            }

            $this->assertLessThanOrEqual(
                32,
                strlen($line),
                "Baris melebihi 32 kolom (area cetak 58mm): {$line}",
            );
        }
    }

    public function test_lines_never_exceed_print_area_for_80mm(): void
    {
        config(['sales.receipt.paper_width_mm' => 80]);

        $bytes = app(ReceiptEscPosBuilder::class)->build($this->makeVisit());

        foreach (explode("\n", $bytes) as $line) {
            if (str_contains($line, "\x1B") || str_contains($line, "\x1D")) {
                continue;
            }

            $this->assertLessThanOrEqual(
                48,
                strlen($line),
                "Baris melebihi 48 kolom (area cetak 80mm): {$line}",
            );
        }
    }

    public function test_long_product_name_is_wrapped_not_truncated(): void
    {
        config(['sales.receipt.paper_width_mm' => 58]);

        $visit = $this->makeVisit();
        $visit->items->first()->product->update([
            'name' => 'Kopi Bubuk Pancor Mas Robusta Premium Kemasan Ekonomis',
        ]);

        $bytes = app(ReceiptEscPosBuilder::class)->build($visit->fresh(['store', 'sales', 'items.product']));

        // Nama harus muncul utuh, tidak terpotong.
        $flat = preg_replace('/\s+/', ' ', strip_tags($bytes));
        $this->assertStringContainsString('Kemasan Ekonomis', $flat);
    }

    public function test_non_ascii_characters_are_transliterated(): void
    {
        $visit = $this->makeVisit();
        $visit->store->update(['name' => 'Toko “Maju” Jaya – Cabang']);

        $bytes = app(ReceiptEscPosBuilder::class)->build($visit->fresh(['store', 'sales', 'items.product']));

        // Tidak boleh ada byte UTF-8 multi-byte yang merusak printer CP437.
        $this->assertDoesNotMatchRegularExpression('/[\x80-\xFF]/', $bytes);
    }

    public function test_raw_endpoint_returns_octet_stream(): void
    {
        $visit = $this->makeVisit();

        $response = $this->actingAs($visit->sales)
            ->get("/sales/receipts/{$visit->id}/escpos");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringStartsWith("\x1B@", $response->getContent());
    }

    public function test_raw_endpoint_is_forbidden_for_other_sales(): void
    {
        $visit = $this->makeVisit();

        $this->actingAs(User::factory()->create(['role' => UserRole::Sales]))
            ->get("/sales/receipts/{$visit->id}/escpos")
            ->assertForbidden();
    }

    public function test_raw_endpoint_redirects_guest_to_login(): void
    {
        $visit = $this->makeVisit();

        $this->get("/sales/receipts/{$visit->id}/escpos")
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
