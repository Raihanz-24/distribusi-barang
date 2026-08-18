<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    public function next(string $key, string $prefix, int $padding): string
    {
        $number = DB::transaction(function () use ($key): int {
            $sequence = DB::table('document_sequences')
                ->where('sequence_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('document_sequences')->insert([
                    'sequence_key' => $key,
                    'current_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = DB::table('document_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
            }

            $next = (int) $sequence->current_value + 1;
            DB::table('document_sequences')->where('sequence_key', $key)->update([
                'current_value' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });

        return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
