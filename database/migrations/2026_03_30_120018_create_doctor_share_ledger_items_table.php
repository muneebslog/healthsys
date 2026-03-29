<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_share_ledger_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_id')->constrained('doctor_share_ledger')->cascadeOnDelete();
            $table->foreignId('invoice_service_id')->constrained('invoice_services');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_share_ledger_items');
    }
};
