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
        Schema::create('transaction_aliases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('match_type', 20)->default('contains'); // exact|contains|starts_with|regex
            $table->string('match_pattern', 255);
            $table->string('display_name', 255);

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->unsignedSmallInteger('priority')->default(100); // lower = higher priority
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'match_type', 'match_pattern']);
            $table->index(['user_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_aliases');
    }
};
