<?php

namespace Tests\Unit\DTOs;

use App\DTOs\UploadSummary;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UploadSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_array_matches_api_contract_and_truncates_row_errors(): void
    {
        $errors = [];
        for ($i = 1; $i <= 25; $i++) {
            $errors[] = ['line' => $i, 'message' => "Erro {$i}"];
        }

        $summary = new UploadSummary(
            importId: 12,
            status: 'completed',
            format: 'csv',
            source: 'nubank',
            originalFilename: 'NU_123.csv',
            rowsTotal: 120,
            rowsImported: 100,
            rowsSkipped: 18,
            rowErrors: $errors,
            checksum: str_repeat('a', 64),
        );

        $payload = $summary->toArray();

        $this->assertSame(12, $payload['import_id']);
        $this->assertSame('NU_123.csv', $payload['original_filename']);
        $this->assertSame(25, $payload['row_errors_count']);
        $this->assertCount(UploadSummary::MAX_ROW_ERRORS, $payload['row_errors']);
        $this->assertSame(1, $payload['row_errors'][0]['line']);
        $this->assertSame(20, $payload['row_errors'][19]['line']);
    }

    public function test_from_import_maps_model_fields(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create([
            'status' => 'completed',
            'format' => 'csv',
            'source' => 'nubank',
            'original_filename' => 'nubank.csv',
            'rows_total' => 10,
            'rows_imported' => 8,
            'rows_skipped' => 2,
            'checksum' => str_repeat('b', 64),
        ]);

        $summary = UploadSummary::fromImport($import, [
            ['line' => 4, 'message' => 'Data inválida'],
        ]);

        $this->assertSame($import->id, $summary->importId);
        $this->assertSame(8, $summary->toArray()['rows_imported']);
        $this->assertSame(1, $summary->toArray()['row_errors_count']);
    }

    public function test_it_rejects_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UploadSummary(
            importId: 1,
            status: 'completed',
            format: 'pdf',
            source: 'nubank',
            originalFilename: 'x.pdf',
            rowsTotal: 0,
            rowsImported: 0,
            rowsSkipped: 0,
            rowErrors: [],
            checksum: '',
        );
    }
}
