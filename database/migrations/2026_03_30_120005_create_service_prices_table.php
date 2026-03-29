<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('doctor_share')->default(0);
            $table->unsignedTinyInteger('hospital_share')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
