<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (['main_warehouse', 'admin_warehouse'] as $type) {
                $locationIds = DB::table('stock_locations')
                    ->where('type', $type)
                    ->orderBy('id')
                    ->pluck('id');

                if ($locationIds->isEmpty()) {
                    continue;
                }

                $canonicalId = (int) $locationIds->first();

                foreach ($locationIds->slice(1) as $duplicateId) {
                    $balances = DB::table('stock_balances')
                        ->where('stock_location_id', $duplicateId)
                        ->get();

                    foreach ($balances as $balance) {
                        $existing = DB::table('stock_balances')
                            ->where('stock_location_id', $canonicalId)
                            ->where('product_id', $balance->product_id)
                            ->first();

                        if ($existing) {
                            DB::table('stock_balances')->where('id', $existing->id)->increment(
                                'quantity_pieces',
                                $balance->quantity_pieces,
                            );
                            DB::table('stock_balances')->where('id', $balance->id)->delete();
                        } else {
                            DB::table('stock_balances')->where('id', $balance->id)->update([
                                'stock_location_id' => $canonicalId,
                            ]);
                        }
                    }

                    DB::table('stock_movements')->where('source_location_id', $duplicateId)
                        ->update(['source_location_id' => $canonicalId]);
                    DB::table('stock_movements')->where('destination_location_id', $duplicateId)
                        ->update(['destination_location_id' => $canonicalId]);
                    DB::table('stock_transfers')->where('source_location_id', $duplicateId)
                        ->update(['source_location_id' => $canonicalId]);
                    DB::table('stock_transfers')->where('destination_location_id', $duplicateId)
                        ->update(['destination_location_id' => $canonicalId]);
                    DB::table('stock_locations')->where('id', $duplicateId)->delete();
                }

                DB::table('stock_locations')->where('id', $canonicalId)->update(['reference_id' => 0]);
            }

            DB::table('stock_locations')
                ->where('type', 'quarantine')
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('stock_balances')
                    ->whereColumn('stock_balances.stock_location_id', 'stock_locations.id')
                    ->where('quantity_pieces', '>', 0))
                ->delete();
        });
    }

    public function down(): void
    {
        DB::table('stock_locations')->whereIn('type', ['main_warehouse', 'admin_warehouse'])
            ->update(['reference_id' => null]);
    }
};
