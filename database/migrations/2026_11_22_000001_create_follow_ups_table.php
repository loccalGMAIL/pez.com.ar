<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->date('occurred_on')->comment('Entrega del trabajo o fecha de la venta');
            $table->date('due_on');
            $table->string('status')->default('pendiente');
            $table->timestamp('sent_at')->nullable();
            $table->text('message')->nullable()->comment('Texto enviado por WhatsApp');
            $table->timestamps();

            $table->index(['status', 'due_on']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('skip_follow_ups')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('skip_follow_ups');
        });

        Schema::dropIfExists('follow_ups');
    }
};
