<?php

namespace Tests\Feature\Aliases;

use App\Enums\AliasMatchType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Services\StatementUploadService;
use App\Support\StatementStorage;
use App\Support\TransactionHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §4.1 — aliases applied during statement persist.
 */
class AliasResolutionOnUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_applies_alias_display_name_and_category_keeps_hash_on_original(): void
    {
        Storage::fake(StatementStorage::DISK);

        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();

        TransactionAlias::factory()->for($user)->withCategory($category)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'IFOOD',
            'display_name' => 'iFood',
            'priority' => 1,
        ]);

        $path = base_path('tests/Fixtures/statements/nubank/sample_account_realistic_anon.csv');
        $file = new UploadedFile($path, 'nubank.csv', 'text/csv', null, true);

        $summary = app(StatementUploadService::class)->handle($user, $file, 'nubank');

        $this->assertGreaterThan(0, $summary->rowsImported);

        $aliased = Transaction::query()
            ->forUser($user)
            ->where('description', 'iFood')
            ->first();

        $this->assertNotNull($aliased, 'Expected at least one row rewritten by alias');
        $this->assertSame($category->id, $aliased->category_id);
        $this->assertSame('iFood', $aliased->description);
        $this->assertArrayHasKey('original_description', $aliased->raw_payload);
        $this->assertStringContainsStringIgnoringCase(
            'IFOOD',
            (string) $aliased->raw_payload['original_description']
        );
        $this->assertSame(
            (int) (is_array($aliased->raw_payload) ? ($aliased->raw_payload['alias_id'] ?? 0) : 0),
            TransactionAlias::query()->forUser($user)->value('id')
        );

        $expectedHash = TransactionHasher::make(
            $aliased->occurred_on->format('Y-m-d'),
            $aliased->amount,
            $aliased->type,
            (string) $aliased->raw_payload['original_description'],
            $aliased->external_id,
            'nubank',
        );
        $this->assertSame($expectedHash, $aliased->unique_hash);
    }
}
