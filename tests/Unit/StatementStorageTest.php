<?php

namespace Tests\Unit;

use App\Support\StatementStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatementStorageTest extends TestCase
{
    public function test_sanitize_original_filename_strips_path_traversal(): void
    {
        $this->assertSame(
            'nubank-setembro.csv',
            StatementStorage::sanitizeOriginalFilename('../../etc/passwd/nubank-setembro.csv')
        );

        $this->assertSame(
            'extrato.csv',
            StatementStorage::sanitizeOriginalFilename('C:\\Users\\x\\extrato.csv')
        );

        $this->assertSame(
            'statement.bin',
            StatementStorage::sanitizeOriginalFilename('../..')
        );
    }

    public function test_build_relative_path_follows_convention(): void
    {
        $at = CarbonImmutable::create(2026, 9, 27, 12, 0, 0, 'America/Sao_Paulo');
        $uuid = '9f3c2a1b-0000-4000-8000-000000000001';

        $path = StatementStorage::buildRelativePath(
            userId: 1,
            originalFilename: 'nubank setembro.csv',
            at: $at,
            uuid: $uuid,
        );

        $this->assertSame(
            '2026/09/1/9f3c2a1b-0000-4000-8000-000000000001_nubank-setembro.csv',
            $path
        );

        $this->assertStringNotContainsString('..', $path);
        $this->assertDoesNotMatchRegularExpression('/^[A-Za-z]:\\\\/', $path);
    }

    public function test_store_uploaded_file_returns_relative_path_and_checksum(): void
    {
        Storage::fake(StatementStorage::DISK);

        $file = UploadedFile::fake()->createWithContent(
            'NU_123.csv',
            "Data,Valor\n01/01/2026,-10,00\n"
        );

        $result = StatementStorage::storeUploadedFile($file, userId: 7);

        $this->assertSame('NU_123.csv', $result['original_filename']);
        $this->assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/7/[0-9a-f-]{36}_NU_123\.csv$#',
            $result['stored_path']
        );
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['checksum']);
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($result['stored_path']));

        // Absolute path must not be what we persist.
        $this->assertStringNotContainsString(storage_path(), $result['stored_path']);
    }

    public function test_checksum_matches_hash_file(): void
    {
        Storage::fake(StatementStorage::DISK);

        $contents = "hello-aura\n";
        Storage::disk(StatementStorage::DISK)->put('tmp/check.bin', $contents);
        $absolute = Storage::disk(StatementStorage::DISK)->path('tmp/check.bin');

        $this->assertSame(
            hash('sha256', $contents),
            StatementStorage::checksum($absolute)
        );
    }
}
