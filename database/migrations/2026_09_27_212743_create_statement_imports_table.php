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
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('format', 10); // csv|ofx
            $table->string('source', 30)->default('nubank');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['source', 'format']);
            $table->index('checksum');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_imports');
    }
};
