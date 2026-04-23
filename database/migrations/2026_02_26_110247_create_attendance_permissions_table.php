<?php
// database/migrations/2024_01_01_000007_create_attendance_permissions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['LATE', 'EARLY_LEAVE', 'BUSINESS_TRIP', 'SICK_WITH_CERT']);
            $table->date('date');
            $table->text('reason');
            $table->string('image_proof')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_permissions');
    }
};
