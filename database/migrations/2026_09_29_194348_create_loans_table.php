<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa H §1.2 — empréstimos / cobranças a terceiros (multi-tenant user_id).
 *
 * Domain rules (enforced in FormRequest/Service, not DB):
 * - kind=card_limit → credit_card_id required and same user_id
 * - kind=cash → credit_card_id must be null
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('credit_card_id')
                ->nullable()
                ->constrained('credit_cards')
                ->nullOnDelete();

            $table->string('debtor_name', 160);
            $table->string('kind', 32); // cash|card_limit
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('BRL');
            $table->date('lent_on');
            $table->date('due_on');
            $table->string('status', 32)->default('open'); // open|partial|paid|cancelled
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_on']);
            $table->index(['user_id', 'debtor_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
