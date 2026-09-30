<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa H §1.3 — vínculo opcional de transações a cartão e/ou empréstimo.
 * Sem backfill: campos novos default null.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('credit_card_id')
                ->nullable()
                ->after('category_id')
                ->constrained('credit_cards')
                ->nullOnDelete();

            $table->foreignId('loan_id')
                ->nullable()
                ->after('credit_card_id')
                ->constrained('loans')
                ->nullOnDelete();

            $table->index(['user_id', 'credit_card_id']);
            $table->index(['user_id', 'loan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'credit_card_id']);
            $table->dropIndex(['user_id', 'loan_id']);
            $table->dropForeign(['credit_card_id']);
            $table->dropForeign(['loan_id']);
            $table->dropColumn(['credit_card_id', 'loan_id']);
        });
    }
};
