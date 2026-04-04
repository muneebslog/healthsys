<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number');
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->string('operation_name');
            $table->unsignedInteger('package_price');
            $table->string('room_number')->nullable();
            $table->date('procedure_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->dateTime('admission_at')->nullable();
            $table->dateTime('discharge_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
