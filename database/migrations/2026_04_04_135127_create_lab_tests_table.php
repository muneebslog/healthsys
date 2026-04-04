<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('test_code', 64)->nullable();
            $table->string('sourcing', 32);
            $table->unsignedSmallInteger('days_required')->default(0);
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('hospital_share');
            $table->unsignedTinyInteger('lab_share');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('test_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};
