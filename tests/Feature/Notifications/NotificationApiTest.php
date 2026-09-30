<?php

namespace Tests\Feature\Notifications;

use App\Models\CreditCard;
use App\Models\User;
use App\Notifications\CreditCardDueNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §5 — /api/notifications.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Sao_Paulo']);
        config(['aura.features.notifications' => true]);
        config(['mail.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_feature_off_returns_feature_disabled(): void
    {
        config(['aura.features.notifications' => false]);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'notifications');
    }

    public function test_list_returns_recent_notifications_default_limit_shape(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'due_day' => 1,
        ]);
        $user->notify(new CreditCardDueNotification($card));

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'credit_card_due')
            ->assertJsonPath('data.0.data.credit_card_id', $card->id)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'type', 'data', 'read_at', 'created_at'],
                ],
            ]);

        $this->assertNull($response->json('data.0.read_at'));
    }

    public function test_unread_filter_and_unread_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $cardA = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);
        $cardB = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 2]);

        $user->notify(new CreditCardDueNotification($cardA));
        $user->notify(new CreditCardDueNotification($cardB));
        $user->notifications()->first()?->markAsRead();

        $this->actingAs($user)
            ->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_mark_read_and_read_all(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $cardA = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);
        $cardB = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 2]);
        $user->notify(new CreditCardDueNotification($cardA));
        $user->notify(new CreditCardDueNotification($cardB));

        $firstId = (string) $user->notifications()->latest()->first()?->id;

        $this->actingAs($user)
            ->postJson("/api/notifications/{$firstId}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $firstId);

        $this->assertNotNull($user->notifications()->whereKey($firstId)->first()?->read_at);

        $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 1);

        $this->actingAs($user)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $owner->id, 'due_day' => 1]);
        $owner->notify(new CreditCardDueNotification($card));

        $id = (string) $owner->notifications()->first()?->id;

        $this->actingAs($other)
            ->postJson("/api/notifications/{$id}/read")
            ->assertForbidden();

        $this->assertNull($owner->notifications()->first()?->read_at);
    }

    public function test_list_does_not_leak_other_users_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $owner->id, 'due_day' => 1]);
        $owner->notify(new CreditCardDueNotification($card));

        $this->actingAs($other)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($other)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }
}
