<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lab_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained('lab_tests');
            $table->string('test_code');
            $table->string('test_name');
            $table->string('sourcing', 32);
            $table->unsignedSmallInteger('days_required');
            $table->unsignedTinyInteger('hospital_share');
            $table->unsignedTinyInteger('lab_share');
            $table->unsignedInteger('list_price');
            $table->unsignedInteger('line_discount')->default(0);
            $table->unsignedInteger('line_final_amount');
            $table->unsignedInteger('hospital_share_amount');
            $table->unsignedInteger('lab_share_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lab_tests');
    }
};
