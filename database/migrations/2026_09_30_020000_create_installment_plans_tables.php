<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcelamentos: plano (série) + items (parcelas 1..N).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('credit_card_id')
                ->nullable()
                ->constrained('credit_cards')
                ->nullOnDelete();

            $table->foreignId('debtor_id')
                ->nullable()
                ->constrained('debtors')
                ->nullOnDelete();

            $table->string('title', 255);
            $table->unsignedSmallInteger('total_count');
            $table->decimal('installment_amount', 14, 2);
            $table->char('currency', 3)->default('BRL');
            $table->string('status', 32)->default('open'); // open|partial|paid|cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'credit_card_id']);
            $table->index(['user_id', 'debtor_id']);
        });

        Schema::create('installment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('installment_plan_id')
                ->constrained('installment_plans')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('number');
            $table->decimal('amount', 14, 2);
            $table->date('due_on')->nullable();
            $table->string('status', 32)->default('open'); // open|paid|cancelled
            $table->timestamp('paid_at')->nullable();

            $table->foreignId('transaction_id')
                ->nullable()
                ->unique()
                ->constrained('transactions')
                ->nullOnDelete();

            $table->foreignId('loan_id')
                ->nullable()
                ->constrained('loans')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['installment_plan_id', 'number']);
            $table->index(['installment_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_items');
        Schema::dropIfExists('installment_plans');
    }
};
