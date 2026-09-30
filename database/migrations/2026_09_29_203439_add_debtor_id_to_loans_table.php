<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Link loans → debtors; backfill from distinct debtor_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('debtor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('debtors')
                ->nullOnDelete();

            $table->index(['user_id', 'debtor_id']);
        });

        $pairs = DB::table('loans')
            ->select('user_id', 'debtor_name')
            ->whereNotNull('debtor_name')
            ->where('debtor_name', '!=', '')
            ->distinct()
            ->get();

        $now = now();

        foreach ($pairs as $pair) {
            $name = trim((string) $pair->debtor_name);
            if ($name === '') {
                continue;
            }

            $debtorId = DB::table('debtors')->where([
                'user_id' => $pair->user_id,
                'name' => $name,
            ])->value('id');

            if ($debtorId === null) {
                $debtorId = DB::table('debtors')->insertGetId([
                    'user_id' => $pair->user_id,
                    'name' => $name,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('loans')
                ->where('user_id', $pair->user_id)
                ->where('debtor_name', $pair->debtor_name)
                ->update(['debtor_id' => $debtorId]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'debtor_id']);
            $table->dropConstrainedForeignId('debtor_id');
        });
    }
};
