<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DatabaseMaintenance
{
    private const PROTECTED_TABLES = ['migrations', 'users'];

    /** @return array<int, array{name: string, rows: int, protected: bool}> */
    public function tables(): array
    {
        $driver = DB::getDriverName();
        $names = match ($driver) {
            'mysql', 'mariadb' => collect(DB::select('SHOW TABLES'))->map(fn (object $row) => (string) array_values((array) $row)[0]),
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))->pluck('name'),
            default => throw new RuntimeException("Alat database belum mendukung driver {$driver}."),
        };

        return $names->sort()->values()->map(fn (string $name): array => [
            'name' => $name,
            'rows' => (int) DB::table($name)->count(),
            'protected' => in_array($name, self::PROTECTED_TABLES, true),
        ])->all();
    }

    public function clear(string $table): int
    {
        $knownTables = collect($this->tables())->pluck('name');

        if (! $knownTables->contains($table)) {
            throw ValidationException::withMessages(['table' => 'Tabel tidak ditemukan.']);
        }

        if (in_array($table, self::PROTECTED_TABLES, true)) {
            throw ValidationException::withMessages([
                'table' => 'Tabel users dan migrations dilindungi agar akses Developer dan struktur aplikasi tidak hilang.',
            ]);
        }

        $count = (int) DB::table($table)->count();
        $driver = DB::getDriverName();

        try {
            Schema::disableForeignKeyConstraints();
            DB::table($table)->delete();

            if ($driver === 'sqlite') {
                DB::table('sqlite_sequence')->where('name', $table)->delete();
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE `'.str_replace('`', '``', $table).'` AUTO_INCREMENT = 1');
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $count;
    }

    /** @param resource $handle */
    public function writeBackup($handle): void
    {
        $driver = DB::getDriverName();
        fwrite($handle, "-- Pancor Mas database backup\n-- Generated: ".now()->toIso8601String()."\n\n");
        fwrite($handle, $driver === 'sqlite' ? "PRAGMA foreign_keys = OFF;\n\n" : "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($this->tables() as $tableInfo) {
            $table = $tableInfo['name'];
            $quotedTable = $this->quoteIdentifier($table, $driver);
            $createSql = $this->createTableSql($table, $driver);

            fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n{$createSql};\n\n");

            DB::table($table)->orderBy($this->orderColumn($table))->chunk(250, function ($rows) use ($handle, $quotedTable, $driver): void {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $columns = collect(array_keys($data))->map(fn (string $column) => $this->quoteIdentifier($column, $driver))->join(', ');
                    $values = collect(array_values($data))->map(fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value))->join(', ');
                    fwrite($handle, "INSERT INTO {$quotedTable} ({$columns}) VALUES ({$values});\n");
                }
            });

            fwrite($handle, "\n");
        }

        fwrite($handle, $driver === 'sqlite' ? "PRAGMA foreign_keys = ON;\n" : "SET FOREIGN_KEY_CHECKS = 1;\n");
    }

    private function createTableSql(string $table, string $driver): string
    {
        if ($driver === 'sqlite') {
            return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');
        }

        $result = (array) DB::selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table, $driver));

        return (string) ($result['Create Table'] ?? array_values($result)[1]);
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        return $driver === 'sqlite'
            ? '"'.str_replace('"', '""', $identifier).'"'
            : '`'.str_replace('`', '``', $identifier).'`';
    }

    private function orderColumn(string $table): string
    {
        $columns = Schema::getColumnListing($table);

        return in_array('id', $columns, true) ? 'id' : $columns[0];
    }
}
