<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa H §1.1 — cadastro de cartões de crédito (multi-tenant user_id).
 *
 * Soft deletes omitted (align with goals). Unique (user_id, name); case folding
 * for display names is enforced in FormRequests / app layer when needed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name', 120);
            $table->decimal('limit_amount', 14, 2)->nullable();
            $table->char('currency', 3)->default('BRL');
            $table->unsignedTinyInteger('closing_day'); // 1–31
            $table->unsignedTinyInteger('due_day'); // 1–31
            $table->string('last_four', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
