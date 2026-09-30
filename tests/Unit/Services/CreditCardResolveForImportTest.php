<?php

namespace Tests\Unit\Services;

use App\Models\CreditCard;
use App\Models\User;
use App\Services\CreditCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * resolveForImport — auto-link csv_credit_card → credit_card_id.
 */
class CreditCardResolveForImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_wins_when_owned(): void
    {
        $user = User::factory()->create();
        $a = CreditCard::factory()->default()->create(['user_id' => $user->id]);
        $b = CreditCard::factory()->create(['user_id' => $user->id]);

        $id = app(CreditCardService::class)->resolveForImport($user, (int) $b->id);

        $this->assertSame((int) $b->id, $id);
        $this->assertNotSame((int) $a->id, $id);
    }

    public function test_override_foreign_returns_null(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = CreditCard::factory()->create(['user_id' => $other->id]);

        $id = app(CreditCardService::class)->resolveForImport($owner, (int) $foreign->id);

        $this->assertNull($id);
    }

    public function test_prefers_active_default(): void
    {
        $user = User::factory()->create();
        CreditCard::factory()->create(['user_id' => $user->id, 'is_default' => false]);
        $default = CreditCard::factory()->default()->create(['user_id' => $user->id]);

        $id = app(CreditCardService::class)->resolveForImport($user);

        $this->assertSame((int) $default->id, $id);
    }

    public function test_sole_active_when_no_default(): void
    {
        $user = User::factory()->create();
        $only = CreditCard::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);
        CreditCard::factory()->inactive()->create(['user_id' => $user->id]);

        $id = app(CreditCardService::class)->resolveForImport($user);

        $this->assertSame((int) $only->id, $id);
    }

    public function test_multiple_active_without_default_returns_null(): void
    {
        $user = User::factory()->create();
        CreditCard::factory()->count(2)->create([
            'user_id' => $user->id,
            'is_default' => false,
        ]);

        $this->assertNull(app(CreditCardService::class)->resolveForImport($user));
    }
}
