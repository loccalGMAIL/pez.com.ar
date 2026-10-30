<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('valid_until');
            $table->string('status')->default('borrador')->index();
            $table->unsignedBigInteger('subtotal')->default(0)->comment('ARS en centavos');
            $table->unsignedBigInteger('discount')->default(0)->comment('ARS en centavos');
            $table->unsignedBigInteger('total')->default(0)->comment('ARS en centavos');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('unit_price')->comment('ARS en centavos');
            $table->unsignedBigInteger('subtotal')->comment('ARS en centavos');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
