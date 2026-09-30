<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default card for auto-linking csv_credit_card imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['user_id', 'is_default']);
        });

        // Backfill: first active card per user becomes default when none set.
        $userIds = DB::table('credit_cards')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $firstActiveId = DB::table('credit_cards')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            if ($firstActiveId === null) {
                continue;
            }

            DB::table('credit_cards')
                ->where('id', $firstActiveId)
                ->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
