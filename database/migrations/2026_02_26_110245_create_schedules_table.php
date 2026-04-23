    <?php
    // database/migrations/2024_01_01_000004_create_schedules_table.php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();

                // Relasi
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');
                $table->foreignId('office_id')->constrained('offices')->onDelete('cascade');

                // ========== RENTANG TANGGAL (UNTUK MUTASI & ROLLING) ==========
                // start_date: tanggal mulai berlaku schedule
                // end_date: tanggal berakhir schedule (NULL = masih berlaku)
                $table->date('start_date');
                $table->date('end_date')->nullable();

                // Status schedule
                $table->boolean('is_wfa')->default(false);
                $table->boolean('is_banned')->default(false);
                $table->text('banned_reason')->nullable();

                $table->timestamps();

                // Index untuk performa query
                $table->index(['user_id', 'start_date', 'end_date']);
                $table->index('start_date');
                $table->index('end_date');
                $table->index('is_wfa');
                $table->index('is_banned');
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('schedules');
        }
    };
