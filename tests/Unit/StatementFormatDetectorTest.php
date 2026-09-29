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

    public function test_allowed_sources_include_credit_card_and_other(): void
    {
        $this->assertSame(
            ['nubank', 'nubank_credit', 'other'],
            StatementFormatDetector::ALLOWED_SOURCES
        );
        $this->assertTrue(StatementFormatDetector::isAllowedSource('nubank_credit'));
        $this->assertTrue(StatementFormatDetector::isAllowedSource('other'));
        $this->assertFalse(StatementFormatDetector::isAllowedSource('inter'));
    }

    public function test_statement_import_persists_csv_credit_card_format(): void
    {
        $import = StatementImport::factory()->creditCard()->create();

        $this->assertSame('csv_credit_card', $import->format);
        $this->assertSame('nubank_credit', $import->source);
        $this->assertContains($import->format, StatementImport::FORMATS);
        $this->assertContains($import->source, StatementImport::SOURCES);
    }

    public function test_sniffs_credit_card_headers_vs_checking(): void
    {
        $cc = base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv');
        $checking = base_path('tests/Fixtures/statements/nubank/sample_account.csv');

        $this->assertSame('csv_credit_card', StatementFormatDetector::detect($cc));
        $this->assertSame('csv', StatementFormatDetector::detect($checking));
    }

    public function test_source_nubank_credit_forces_csv_credit_card(): void
    {
        $checking = base_path('tests/Fixtures/statements/nubank/sample_account.csv');

        $this->assertSame(
            'csv_credit_card',
            StatementFormatDetector::detect($checking, 'nubank_credit')
        );

        $meta = StatementFormatDetector::detectForImport($checking, 'nubank_credit');
        $this->assertSame(['format' => 'csv_credit_card', 'source' => 'nubank_credit'], $meta);
    }

    public function test_statement_kind_override_for_csv_only(): void
    {
        $cc = base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv');
        $checking = base_path('tests/Fixtures/statements/nubank/sample_account.csv');
        $ofx = UploadedFile::fake()->create('nubank.ofx', 10, 'text/plain');

        $this->assertSame(
            'csv_credit_card',
            StatementFormatDetector::detect($checking, 'nubank', 'credit_card')
        );
        $this->assertSame(
            'csv',
            StatementFormatDetector::detect($cc, 'nubank', 'checking')
        );
        // OFX is never overridden by statement_kind.
        $this->assertSame('ofx', StatementFormatDetector::detect($ofx, 'nubank', 'credit_card'));

        $meta = StatementFormatDetector::detectForImport($cc, 'nubank', 'credit_card');
        $this->assertSame('csv_credit_card', $meta['format']);
        $this->assertSame('nubank_credit', $meta['source']);
    }

    public function test_allowed_kinds(): void
    {
        $this->assertSame(['checking', 'credit_card'], StatementFormatDetector::ALLOWED_KINDS);
        $this->assertTrue(StatementFormatDetector::isAllowedKind('credit_card'));
        $this->assertFalse(StatementFormatDetector::isAllowedKind('savings'));
        $this->assertNull(StatementFormatDetector::normalizeKind(''));
        $this->assertSame('credit_card', StatementFormatDetector::normalizeKind('Credit_Card'));
    }
}
