<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('statement_import_id')
                ->constrained('statement_imports')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('external_id', 120)->nullable();
            $table->date('occurred_on');
            $table->string('description', 500);
            $table->decimal('amount', 14, 2); // absolute >= 0; sign via type
            $table->string('type', 10); // credit|debit
            $table->char('unique_hash', 64);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique('unique_hash');
            $table->index('occurred_on');
            $table->index('type');
            $table->index('category_id');
            $table->index(['occurred_on', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
