<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->decimal('last_latitude', 10, 8)->nullable();
            $table->decimal('last_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'last_latitude', 'last_longitude', 'last_location_at']);
        });
    }
};
