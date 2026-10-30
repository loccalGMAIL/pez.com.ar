<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('notes');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->json('reception_photos')->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('reception_photos');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
