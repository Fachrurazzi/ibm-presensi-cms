<?php
// database/migrations/2024_01_01_000006_create_leaves_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('category')->default('annual'); // annual, sick, emergency, maternity, important
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};