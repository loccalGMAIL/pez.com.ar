<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->unique()->after('id');
        });

        // Receipts for payments recorded before numbering existed, in order.
        $next = (int) config('pez.payments.first_number', 1);
        foreach (DB::table('payments')->whereNull('number')->orderBy('paid_on')->orderBy('id')->pluck('id') as $id) {
            DB::table('payments')->where('id', $id)->update(['number' => $next++]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }
};
