<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_sample_slip_counters', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_serial')->default(0);
        });

        DB::table('lab_sample_slip_counters')->insert([
            'id' => 1,
            'last_serial' => 0,
        ]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('lab_sample_slip_serial')
                ->nullable()
                ->unique()
                ->after('lab_case_invoice_url');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['lab_sample_slip_serial']);
            $table->dropColumn('lab_sample_slip_serial');
        });

        Schema::dropIfExists('lab_sample_slip_counters');
    }
};
