<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lab_tests')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if ($this->mysqlColumnIsNullable('lab_tests', 'test_code')) {
                return;
            }

            Schema::table('lab_tests', function (Blueprint $table): void {
                $table->dropUnique(['test_code']);
            });

            DB::statement('ALTER TABLE lab_tests MODIFY test_code VARCHAR(64) NULL');

            Schema::table('lab_tests', function (Blueprint $table): void {
                $table->unique('test_code');
            });

            return;
        }

        if ($driver === 'sqlite') {
            if ($this->sqliteColumnIsNullable('lab_tests', 'test_code')) {
                return;
            }

            $this->sqliteRebuildLabTestsWithNullableTestCode();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('lab_tests')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::table('lab_tests')->whereNull('test_code')->update([
                'test_code' => DB::raw("CONCAT('LEGACY-', id)"),
            ]);

            Schema::table('lab_tests', function (Blueprint $table): void {
                $table->dropUnique(['test_code']);
            });

            DB::statement('ALTER TABLE lab_tests MODIFY test_code VARCHAR(64) NOT NULL');

            Schema::table('lab_tests', function (Blueprint $table): void {
                $table->unique('test_code');
            });

            return;
        }

        if ($driver === 'sqlite') {
            DB::table('lab_tests')->where(function ($q): void {
                $q->whereNull('test_code')->orWhere('test_code', '');
            })->update([
                'test_code' => DB::raw("'LEGACY-' || id"),
            ]);

            $this->sqliteRebuildLabTestsWithNotNullTestCode();
        }
    }

    private function mysqlColumnIsNullable(string $table, string $column): bool
    {
        $database = DB::getDatabaseName();

        $row = DB::selectOne(
            'SELECT IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, $column]
        );

        return $row && strtoupper((string) $row->nullable) === 'YES';
    }

    private function sqliteColumnIsNullable(string $table, string $column): bool
    {
        $info = collect(DB::select('PRAGMA table_info('.$table.')'))
            ->firstWhere('name', $column);

        if (! $info) {
            return true;
        }

        return (int) $info->notnull === 0;
    }

    /**
     * Rebuilds {@code lab_tests} so {@code test_code} allows NULL (SQLite cannot ALTER COLUMN NOT NULL off in one step).
     */
    private function sqliteRebuildLabTestsWithNullableTestCode(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement('CREATE TABLE lab_tests__nullable (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR NOT NULL,
            test_code VARCHAR(64) NULL,
            sourcing VARCHAR(32) NOT NULL,
            days_required INTEGER NOT NULL DEFAULT 0,
            price INTEGER NOT NULL,
            hospital_share INTEGER NOT NULL,
            lab_share INTEGER NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        DB::statement('INSERT INTO lab_tests__nullable SELECT * FROM lab_tests');
        Schema::drop('lab_tests');
        DB::statement('ALTER TABLE lab_tests__nullable RENAME TO lab_tests');

        Schema::table('lab_tests', function (Blueprint $table): void {
            $table->unique('test_code');
        });

        Schema::enableForeignKeyConstraints();
    }

    private function sqliteRebuildLabTestsWithNotNullTestCode(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement('CREATE TABLE lab_tests__notnull (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR NOT NULL,
            test_code VARCHAR(64) NOT NULL,
            sourcing VARCHAR(32) NOT NULL,
            days_required INTEGER NOT NULL DEFAULT 0,
            price INTEGER NOT NULL,
            hospital_share INTEGER NOT NULL,
            lab_share INTEGER NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        DB::statement('INSERT INTO lab_tests__notnull SELECT * FROM lab_tests');
        Schema::drop('lab_tests');
        DB::statement('ALTER TABLE lab_tests__notnull RENAME TO lab_tests');

        Schema::table('lab_tests', function (Blueprint $table): void {
            $table->unique('test_code');
        });

        Schema::enableForeignKeyConstraints();
    }
};
