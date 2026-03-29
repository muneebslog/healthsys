<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_services', function (Blueprint $table) {
            $table->foreign('queue_token_id')->references('id')->on('queue_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visit_services', function (Blueprint $table) {
            $table->dropForeign(['queue_token_id']);
        });
    }
};
