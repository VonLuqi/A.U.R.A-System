<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Path / checksum helpers for statement uploads (Etapa C §2.2).
 *
 * Relative path on disk `statements`:
 *   {Y}/{m}/{userId}/{uuid}_{sanitizedOriginalName}.{ext}
 *
 * Persist only the relative path in statement_imports.stored_path — never an absolute server path.
 */
final class StatementStorage
{
    public const DISK = 'statements';

    /**
     * Basename only; strip path traversal and unsafe characters.
     */
    public static function sanitizeOriginalFilename(string $filename): string
    {
        $normalized = str_replace(["\0", '\\'], ['', '/'], $filename);
        $basename = basename($normalized);

        // Collapse anything outside a conservative safe set (keep letters, digits, dot, dash, underscore).
        $safe = preg_replace('/[^\p{L}\p{N}._-]+/u', '-', $basename) ?? '';
        $safe = trim($safe, '.-_');
        $safe = preg_replace('/-+/', '-', $safe) ?? $safe;

        if ($safe === '' || $safe === '.' || $safe === '..') {
            return 'statement.bin';
        }

        // Guard against extremely long client names (DB column is 255).
        return Str::limit($safe, 200, '');
    }

    /**
     * Build relative path: {Y}/{m}/{userId}/{uuid}_{sanitizedOriginalName}.{ext}
     */
    public static function buildRelativePath(
        int $userId,
        string $originalFilename,
        ?\DateTimeInterface $at = null,
        ?string $uuid = null,
    ): string {
        $at ??= now();
        $uuid ??= (string) Str::uuid();
        $safeName = self::sanitizeOriginalFilename($originalFilename);

        return sprintf(
            '%s/%s/%d/%s_%s',
            $at->format('Y'),
            $at->format('m'),
            $userId,
            $uuid,
            $safeName,
        );
    }

    /**
     * SHA-256 hex of file contents (64 chars).
     */
    public static function checksum(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new RuntimeException('Unable to compute statement file checksum.');
        }

        return $hash;
    }

    /**
     * Store upload on the private statements disk and return persistence fields.
     *
     * @return array{stored_path: string, original_filename: string, checksum: string}
     */
    public static function storeUploadedFile(UploadedFile $file, int $userId): array
    {
        $originalFilename = self::sanitizeOriginalFilename($file->getClientOriginalName());
        $storedPath = self::buildRelativePath($userId, $originalFilename);

        $directory = dirname($storedPath);
        $basename = basename($storedPath);

        Storage::disk(self::DISK)->putFileAs($directory, $file, $basename);

        $absolutePath = Storage::disk(self::DISK)->path($storedPath);

        return [
            'stored_path' => $storedPath,
            'original_filename' => $originalFilename,
            'checksum' => self::checksum($absolutePath),
        ];
    }
}
