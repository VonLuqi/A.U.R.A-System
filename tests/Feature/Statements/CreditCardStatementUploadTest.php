<?php

namespace Tests\Feature\Statements;

use App\Models\CreditCard;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Parsers\NubankCreditCardCsvParser;
use App\Parsers\NubankCsvParser;
use App\Parsers\StatementParserResolver;
use App\Support\StatementFormatDetector;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §5.2 — credit-card CSV upload feature coverage.
 */
class CreditCardStatementUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(StatementStorage::DISK);
    }

    private function uploadedCreditCardFixture(string $clientName = 'fatura.csv'): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv'),
            $clientName,
            'text/plain',
            null,
            true,
        );
    }

    private function uploadedCheckingFixture(string $clientName = 'conta.csv'): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/statements/nubank/sample_account.csv'),
            $clientName,
            'text/plain',
            null,
            true,
        );
    }

    public function test_authenticated_upload_imports_credit_card_rows(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture('NU_CC.csv'),
            'source' => 'nubank_credit',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.format', 'csv_credit_card')
            ->assertJsonPath('data.source', 'nubank_credit')
            ->assertJsonPath('data.original_filename', 'NU_CC.csv')
            ->assertJsonPath('data.rows_total', 9)
            ->assertJsonPath('data.rows_imported', 7)
            ->assertJsonPath('data.rows_skipped', 2);

        $this->assertGreaterThan(0, $response->json('data.rows_imported'));
        $this->assertSame(7, Transaction::query()->where('user_id', $user->id)->count());

        $refund = Transaction::query()
            ->where('user_id', $user->id)
            ->where('description', 'Estorno Uber *Trip')
            ->firstOrFail();
        $this->assertSame('credit', $refund->type);
        $this->assertSame('18.90', $refund->amount);

        $purchase = Transaction::query()
            ->where('user_id', $user->id)
            ->where('description', 'Mercado Extra')
            ->firstOrFail();
        $this->assertSame('debit', $purchase->type);
        $this->assertSame('89.90', $purchase->amount);
        $this->assertSame('2026-09-01', $purchase->occurred_on->format('Y-m-d'));
        $this->assertSame('nubank_credit', $purchase->raw_payload['card_source'] ?? null);

        $import = StatementImport::query()->findOrFail($response->json('data.import_id'));
        $this->assertSame('csv_credit_card', $import->format);
        $this->assertSame('nubank_credit', $import->source);
        $this->assertTrue(Storage::disk(StatementStorage::DISK)->exists($import->stored_path));
    }

    public function test_credit_card_import_links_default_card(): void
    {
        $user = User::factory()->create();
        $default = CreditCard::factory()->default()->create(['user_id' => $user->id]);
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'source' => 'nubank_credit',
        ])->assertCreated();

        $this->assertSame(
            7,
            Transaction::query()
                ->where('user_id', $user->id)
                ->where('credit_card_id', $default->id)
                ->count(),
        );
    }

    public function test_credit_card_import_links_override_card(): void
    {
        $user = User::factory()->create();
        CreditCard::factory()->default()->create(['user_id' => $user->id]);
        $override = CreditCard::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'source' => 'nubank_credit',
            'credit_card_id' => $override->id,
        ])->assertCreated();

        $this->assertSame(
            7,
            Transaction::query()
                ->where('user_id', $user->id)
                ->where('credit_card_id', $override->id)
                ->count(),
        );
    }

    public function test_credit_card_import_rejects_foreign_card_id(): void
    {
        $user = User::factory()->create();
        $foreign = CreditCard::factory()->create();

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'source' => 'nubank_credit',
            'credit_card_id' => $foreign->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id']);
    }

    public function test_checking_import_does_not_set_credit_card_id(): void
    {
        $user = User::factory()->create();
        CreditCard::factory()->default()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCheckingFixture(),
            'statement_kind' => 'checking',
        ])->assertCreated();

        $this->assertSame(
            0,
            Transaction::query()
                ->where('user_id', $user->id)
                ->whereNotNull('credit_card_id')
                ->count(),
        );
    }

    public function test_header_sniff_routes_credit_card_without_explicit_source(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.format', 'csv_credit_card')
            ->assertJsonPath('data.source', 'nubank_credit')
            ->assertJsonPath('data.rows_imported', 7);
    }

    public function test_statement_kind_override_and_checking_csv_still_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'statement_kind' => 'credit_card',
        ])
            ->assertCreated()
            ->assertJsonPath('data.format', 'csv_credit_card')
            ->assertJsonPath('data.rows_imported', 7);

        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCheckingFixture(),
            'source' => 'nubank',
        ])
            ->assertCreated()
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.source', 'nubank')
            ->assertJsonPath('data.rows_imported', 4);
    }

    public function test_detector_chooses_credit_card_parser_not_checking_parser(): void
    {
        $ccPath = base_path('tests/Fixtures/statements/nubank_credit_card_sample.csv');
        $checkingPath = base_path('tests/Fixtures/statements/nubank/sample_account.csv');

        $this->assertSame('csv_credit_card', StatementFormatDetector::detect($ccPath));
        $this->assertSame('csv', StatementFormatDetector::detect($checkingPath));

        $resolver = $this->app->make(StatementParserResolver::class);
        $this->assertInstanceOf(
            NubankCreditCardCsvParser::class,
            $resolver->resolve('csv_credit_card', 'nubank_credit')
        );
        $this->assertInstanceOf(
            NubankCsvParser::class,
            $resolver->resolve('csv', 'nubank')
        );
        $this->assertFalse((new NubankCsvParser)->supports('csv_credit_card', 'nubank_credit'));
    }

    public function test_reupload_credit_card_csv_links_existing_rows(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->default()->create(['user_id' => $user->id]);

        // First import without an owned default would still resolve; wipe card link after.
        $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'source' => 'nubank_credit',
            'credit_card_id' => $card->id,
        ])->assertCreated();

        Transaction::query()->where('user_id', $user->id)->update(['credit_card_id' => null]);
        $this->assertSame(
            0,
            Transaction::query()->where('user_id', $user->id)->whereNotNull('credit_card_id')->count(),
        );

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'source' => 'nubank_credit',
            'credit_card_id' => $card->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.rows_imported', 0)
            ->assertJsonPath('data.rows_updated', 7);

        $this->assertSame(
            7,
            Transaction::query()
                ->where('user_id', $user->id)
                ->where('credit_card_id', $card->id)
                ->count(),
        );
        $this->assertSame(7, Transaction::query()->where('user_id', $user->id)->count());
    }

    public function test_forcing_checking_kind_on_credit_card_csv_returns_clear_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/statements/upload', [
            'file' => $this->uploadedCreditCardFixture(),
            'statement_kind' => 'checking',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'invalid_statement')
            ->assertJsonStructure(['message', 'error_code', 'import_id']);

        $this->assertStringContainsString(
            'Cabeçalho CSV inválido',
            (string) $response->json('message')
        );
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(
            'failed',
            StatementImport::query()->findOrFail($response->json('import_id'))->status
        );
    }
}
