<?php
// database/migrations/xxxx_create_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes(); // ✅ TAMBAHKAN soft delete
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
