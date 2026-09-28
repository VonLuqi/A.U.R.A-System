<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedStatementFormatException;
use App\Models\StatementImport;
use App\Models\User;
use App\Support\StatementFormatDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StatementFormatDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_csv_ofx_qfx_from_extension(): void
    {
        $this->assertSame('csv', StatementFormatDetector::detect(
            UploadedFile::fake()->create('nubank.csv', 10, 'text/csv')
        ));
        $this->assertSame('ofx', StatementFormatDetector::detect(
            UploadedFile::fake()->create('nubank.ofx', 10, 'text/plain')
        ));
        $this->assertSame('ofx', StatementFormatDetector::detect(
            UploadedFile::fake()->create('nubank.qfx', 10, 'text/plain')
        ));
    }

    public function test_extension_wins_over_content(): void
    {
        $path = storage_path('framework/testing/fake_csv_with_ofx_text.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "OFXHEADER:100\nData,Valor,Descrição\n01/09/2026,-10,00,X\n");

        try {
            $this->assertSame('csv', StatementFormatDetector::detect($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_sniffs_ofx_when_extension_unknown(): void
    {
        $path = storage_path('framework/testing/statement_noext');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "OFXHEADER:100\nDATA:OFXSGML\n\n<OFX>\n</OFX>\n");

        try {
            $this->assertSame('ofx', StatementFormatDetector::detect($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_sniffs_ofx_tag_without_header(): void
    {
        $path = storage_path('framework/testing/statement_tag_only.bin');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, "<?xml version=\"1.0\"?>\n<OFX><SIGNONMSGSRSV1></SIGNONMSGSRSV1></OFX>\n");

        try {
            $this->assertSame('ofx', StatementFormatDetector::detect($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_unknown_format_throws(): void
    {
        $path = storage_path('framework/testing/statement.pdf');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, '%PDF-1.4 fake');

        try {
            $this->expectException(UnsupportedStatementFormatException::class);
            StatementFormatDetector::detect($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_default_source_is_nubank_and_persists_on_statement_import(): void
    {
        $meta = StatementFormatDetector::detectForImport(
            UploadedFile::fake()->create('extrato.qfx', 10, 'text/plain'),
            null,
        );

        $this->assertSame(['format' => 'ofx', 'source' => 'nubank'], $meta);

        $user = User::factory()->create();
        $import = StatementImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => 'extrato.qfx',
            'stored_path' => '2026/09/1/uuid_extrato.qfx',
            'format' => $meta['format'],
            'source' => $meta['source'],
            'status' => 'processing',
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_skipped' => 0,
            'checksum' => hash('sha256', 'x'),
            'error_message' => null,
        ]);

        $import->refresh();
        $this->assertSame('ofx', $import->format);
        $this->assertSame('nubank', $import->source);
    }
}
