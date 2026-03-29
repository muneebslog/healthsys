<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('shift_id')->constrained('shifts');
            $table->unsignedInteger('total_amount')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('final_amount')->default(0);
            $table->enum('status', ['draft', 'paid', 'cancelled'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
