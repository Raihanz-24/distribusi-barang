<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 50)->nullable()->after('name')->unique();
        });

        DB::table('users')->orderBy('id')->get()->each(function (object $user): void {
            $base = Str::lower(Str::slug(Str::before((string) $user->email, '@'), '_')) ?: 'user';
            $username = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $username)->exists()) {
                $username = $base.'_'.++$suffix;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
