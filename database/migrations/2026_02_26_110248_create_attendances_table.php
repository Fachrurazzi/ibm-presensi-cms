<?php
// database/migrations/2024_01_01_000005_create_attendances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // Relasi
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->onDelete('set null');
            $table->foreignId('attendance_permission_id')->nullable()->constrained('attendance_permissions')->onDelete('set null');

            // Schedule info (snapshot dari schedule saat absen)
            $table->double('schedule_latitude');
            $table->double('schedule_longitude');
            $table->datetime('schedule_start_time');  // Pakai datetime, bukan time!
            $table->datetime('schedule_end_time');

            // Lokasi absen masuk
            $table->double('start_latitude');
            $table->double('start_longitude');
            $table->datetime('start_time');

            // Lokasi absen pulang (nullable karena belum tentu pulang)
            $table->double('end_latitude')->nullable();
            $table->double('end_longitude')->nullable();
            $table->datetime('end_time')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes untuk performa
            $table->index(['user_id', 'start_time']);
            $table->index('schedule_id');
            $table->index('attendance_permission_id');
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
