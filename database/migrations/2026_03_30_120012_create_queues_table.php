<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->enum('status', ['active', 'closed', 'finished'])->default('active');
            $table->unsignedInteger('current_token')->default(0);
            $table->unsignedInteger('current_flow_token')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'doctor_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
