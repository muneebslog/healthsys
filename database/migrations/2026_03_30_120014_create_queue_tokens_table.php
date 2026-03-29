<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedInteger('token_number');
            $table->enum('status', ['reserved', 'waiting', 'serving', 'done', 'skipped'])->default('waiting');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['queue_id', 'token_number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tokens');
    }
};
