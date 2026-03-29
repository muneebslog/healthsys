<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('service_price_id')->constrained('service_prices');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->unsignedInteger('price');
            $table->unsignedInteger('doctor_share_amount')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('final_amount');
            $table->boolean('doctor_share_paid')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_services');
    }
};
