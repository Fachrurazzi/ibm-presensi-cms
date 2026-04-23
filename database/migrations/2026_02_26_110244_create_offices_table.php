<?php
// database/migrations/2024_01_01_000003_create_offices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supervisor_name')->nullable();
            $table->double('latitude');
            $table->double('longitude');
            $table->integer('radius')->default(100); // Radius dalam meter
            $table->timestamps();
            $table->softDeletes();

            // Index untuk performance query koordinat
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
