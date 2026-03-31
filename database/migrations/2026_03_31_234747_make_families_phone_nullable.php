<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement('CREATE TABLE families__new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, phone VARCHAR(20) NULL, head_id INTEGER NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
            DB::statement('INSERT INTO families__new (id, phone, head_id, created_at, updated_at) SELECT id, phone, head_id, created_at, updated_at FROM families');
            DB::statement('DROP TABLE families');
            DB::statement('ALTER TABLE families__new RENAME TO families');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS families_phone_unique ON families (phone)');

            DB::statement('PRAGMA foreign_keys=on');

            return;
        }

        DB::statement('ALTER TABLE `families` MODIFY `phone` VARCHAR(20) NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("UPDATE families SET phone = 'WALKIN-' || id WHERE phone IS NULL");
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement('CREATE TABLE families__new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, phone VARCHAR(20) NOT NULL, head_id INTEGER NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
            DB::statement('INSERT INTO families__new (id, phone, head_id, created_at, updated_at) SELECT id, phone, head_id, created_at, updated_at FROM families');
            DB::statement('DROP TABLE families');
            DB::statement('ALTER TABLE families__new RENAME TO families');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS families_phone_unique ON families (phone)');

            DB::statement('PRAGMA foreign_keys=on');

            return;
        }

        DB::statement('UPDATE `families` SET `phone` = CONCAT("WALKIN-", `id`) WHERE `phone` IS NULL');
        DB::statement('ALTER TABLE `families` MODIFY `phone` VARCHAR(20) NOT NULL');
    }
};
