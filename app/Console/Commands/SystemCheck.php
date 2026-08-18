<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemCheck extends Command
{
    protected $signature = 'pancor:check';

    protected $description = 'Memeriksa integritas stok, harga aktif, akun Admin, dan koneksi database';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->error('Database tidak dapat diakses: '.$exception->getMessage());

            return self::FAILURE;
        }

        $issues = [];
        $ledger = [];

        StockMovement::query()
            ->select(['product_id', 'source_location_id', 'destination_location_id', 'quantity_pieces'])
            ->orderBy('id')
            ->each(function (StockMovement $movement) use (&$ledger): void {
                if ($movement->source_location_id) {
                    $key = $movement->source_location_id.':'.$movement->product_id;
                    $ledger[$key] = ($ledger[$key] ?? 0) - $movement->quantity_pieces;
                }

                if ($movement->destination_location_id) {
                    $key = $movement->destination_location_id.':'.$movement->product_id;
                    $ledger[$key] = ($ledger[$key] ?? 0) + $movement->quantity_pieces;
                }
            });

        StockBalance::query()->with(['location:id,name', 'product:id,name'])->each(
            function (StockBalance $balance) use (&$issues, &$ledger): void {
                $key = $balance->stock_location_id.':'.$balance->product_id;
                $expected = $ledger[$key] ?? 0;

                if ($balance->quantity_pieces < 0) {
                    $issues[] = "Stok negatif: {$balance->location?->name} / {$balance->product?->name}.";
                }

                if ($expected !== $balance->quantity_pieces) {
                    $issues[] = "Saldo tidak cocok: {$balance->location?->name} / {$balance->product?->name}; saldo {$balance->quantity_pieces}, mutasi {$expected}.";
                }

                unset($ledger[$key]);
            },
        );

        foreach ($ledger as $key => $quantity) {
            if ($quantity !== 0) {
                $issues[] = "Mutasi {$key} bernilai {$quantity} belum memiliki baris saldo.";
            }
        }

        Product::query()->where('is_active', true)->withCount([
            'prices as active_prices_count' => fn ($query) => $query->where('is_active', true),
        ])->each(function (Product $product) use (&$issues): void {
            if ($product->active_prices_count !== 1) {
                $issues[] = "Produk {$product->name} memiliki {$product->active_prices_count} harga aktif; seharusnya tepat satu.";
            }
        });

        if (! User::query()->where('role', UserRole::Admin)->where('is_active', true)->exists()) {
            $issues[] = 'Tidak ada akun Admin aktif.';
        }

        if ($issues !== []) {
            $this->error('Pemeriksaan menemukan '.count($issues).' masalah:');
            foreach ($issues as $issue) {
                $this->line(' - '.$issue);
            }

            return self::FAILURE;
        }

        $this->info('Sistem sehat: database, stok, harga aktif, dan akun Admin konsisten.');

        return self::SUCCESS;
    }
}
