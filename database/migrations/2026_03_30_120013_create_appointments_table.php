<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('family_id')->constrained('families');
            $table->foreignId('doctor_id')->constrained('doctors');
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('created_by')->constrained('users');
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->enum('status', ['booked', 'arrived', 'used_by_walkin', 'cancelled'])->default('booked');
            $table->text('notes')->nullable();
            $table->boolean('sms_sent')->default(false);
            $table->timestamps();

            $table->index('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
