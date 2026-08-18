<?php

namespace Tests\Feature;

use App\Actions\UpdateProductPrice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_code_and_slug_are_normalized(): void
    {
        $product = Product::create([
            'code' => ' p ',
            'name' => 'Kopi Premium Spesial',
            'unit_name' => 'bungkus',
            'pieces_per_renteng' => 12,
            'is_active' => true,
        ]);

        $this->assertSame('P', $product->code);
        $this->assertSame('kopi-premium-spesial', $product->slug);
    }

    public function test_price_change_creates_new_version_without_changing_previous_record(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $action = app(UpdateProductPrice::class);

        $oldPrice = $action->execute($product, 12000, $admin);
        $newPrice = $action->execute($product, 20000, $admin);

        $this->assertNotSame($oldPrice->id, $newPrice->id);
        $this->assertFalse($oldPrice->fresh()->is_active);
        $this->assertTrue($newPrice->fresh()->is_active);
        $this->assertSame(12000, $oldPrice->fresh()->price_per_renteng);
        $this->assertSame(20000, $product->fresh()->currentPrice->price_per_renteng);
        $this->assertDatabaseCount('product_prices', 2);
    }

    public function test_saving_same_price_does_not_create_duplicate_version(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $action = app(UpdateProductPrice::class);

        $first = $action->execute($product, 12000, $admin);
        $second = $action->execute($product, 12000, $admin);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('product_prices', 1);
    }

    public function test_renteng_price_does_not_require_a_piece_price(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['pieces_per_renteng' => 12]);
        $action = app(UpdateProductPrice::class);

        $price = $action->execute($product, 20000, $admin);

        $this->assertSame(20000, $price->price_per_renteng);
        $this->assertFalse(Schema::hasColumn('product_prices', 'price_per_piece'));
        $this->assertDatabaseCount('product_prices', 1);
    }

    public function test_only_admin_can_manage_products_and_prices(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->create();
        $product = Product::factory()->create();
        $price = ProductPrice::factory()->create([
            'product_id' => $product->id,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($admin->can('update', $product));
        $this->assertTrue($admin->can('update', $price));
        $this->assertFalse($sales->can('viewAny', Product::class));
        $this->assertFalse($sales->can('viewAny', ProductPrice::class));
    }

    public function test_admin_manages_price_from_product_page_only(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        app(UpdateProductPrice::class)->execute($product, 12000, $admin);

        $this->actingAs($admin)->get('/admin/products')->assertOk();
        $this->actingAs($admin)->get('/admin/products/create')->assertOk();
        $this->actingAs($admin)->get('/admin/products/'.$product->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/product-prices')->assertNotFound();
    }
}
