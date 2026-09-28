<?php

namespace App\Services;

use App\Models\StatementImport;
use App\Support\StatementStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class StatementRetentionService
{
    public const DEFAULT_RETENTION_DAYS = 90;

    /**
     * @return array{candidates: int, purged: int, missing: int, dry_run: bool, days: int}
     */
    public function purgeOlderThan(int $days = self::DEFAULT_RETENTION_DAYS, bool $dryRun = false): array
    {
        $days = max(1, $days);
        $cutoff = Carbon::now()->subDays($days);

        $imports = $this->candidates($cutoff);

        $purged = 0;
        $missing = 0;

        foreach ($imports as $import) {
            $path = (string) $import->stored_path;

            if ($dryRun) {
                Log::info('statements.purge.candidate', [
                    'import_id' => $import->id,
                    'stored_path' => $path,
                    'created_at' => optional($import->created_at)?->toDateTimeString(),
                ]);

                continue;
            }

            $disk = Storage::disk(StatementStorage::DISK);
            $deleted = false;

            if ($disk->exists($path)) {
                $deleted = $disk->delete($path);
                if ($deleted) {
                    $purged++;
                }
            } else {
                $missing++;
            }

            $import->forceFill([
                'purged_at' => Carbon::now(),
            ])->save();

            Log::info('statements.purge.done', [
                'import_id' => $import->id,
                'stored_path' => $path,
                'file_deleted' => $deleted,
                'file_was_missing' => ! $deleted && ! $disk->exists($path),
            ]);
        }

        return [
            'candidates' => $imports->count(),
            'purged' => $dryRun ? 0 : $purged,
            'missing' => $dryRun ? 0 : $missing,
            'dry_run' => $dryRun,
            'days' => $days,
        ];
    }

    /**
     * @return Collection<int, StatementImport>
     */
    public function candidates(Carbon $cutoff): Collection
    {
        return StatementImport::query()
            ->whereIn('status', ['completed', 'failed'])
            ->whereNull('purged_at')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('stored_path')
            ->where('stored_path', '!=', '')
            ->orderBy('id')
            ->get();
    }
}
