<?php

namespace Tests\Feature\Loans;

use App\Enums\InstallmentItemStatus;
use App\Enums\InstallmentPlanStatus;
use App\Models\CreditCard;
use App\Models\Debtor;
use App\Models\InstallmentItem;
use App\Models\InstallmentPlan;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InstallmentPlanService;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config(['aura.features.loans' => true]);
    }

    public function test_upsert_from_transaction_creates_all_items_with_past_paid(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '33.41',
            'description' => 'Shopee *Shpstecnologia – Parcela 4/10',
            'credit_card_id' => $card->id,
        ]);

        $plan = app(InstallmentPlanService::class)->upsertFromTransaction($user, $tx);

        $this->assertNotNull($plan);
        $this->assertSame('Shopee *Shpstecnologia', $plan->title);
        $this->assertSame(10, (int) $plan->total_count);
        $this->assertSame(10, $plan->items()->count());
        $this->assertSame(InstallmentPlanStatus::Partial, $plan->status);

        $items = $plan->items()->orderBy('number')->get();
        $this->assertSame(InstallmentItemStatus::Paid, $items[0]->status);
        $this->assertSame(InstallmentItemStatus::Paid, $items[2]->status);
        $this->assertSame(InstallmentItemStatus::Open, $items[3]->status);
        $this->assertSame((int) $tx->id, (int) $items[3]->transaction_id);
        $this->assertSame(InstallmentItemStatus::Open, $items[9]->status);
        $this->assertNull($items[9]->transaction_id);
        $this->assertSame(3, $plan->paidCount());
    }

    public function test_second_installment_line_links_existing_plan(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $service = app(InstallmentPlanService::class);

        $tx4 = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '33.41',
            'description' => 'Shopee Foo Parcela 4/10',
            'credit_card_id' => $card->id,
        ]);
        $plan = $service->upsertFromTransaction($user, $tx4);

        $tx5 = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-10-06',
            'type' => 'debit',
            'amount' => '33.41',
            'description' => 'Shopee Foo Parcela 5/10',
            'credit_card_id' => $card->id,
        ]);
        $again = $service->upsertFromTransaction($user, $tx5);

        $this->assertSame((int) $plan->id, (int) $again->id);
        $this->assertSame(1, InstallmentPlan::query()->where('user_id', $user->id)->count());

        $item5 = InstallmentItem::query()
            ->where('installment_plan_id', $plan->id)
            ->where('number', 5)
            ->first();
        $this->assertNotNull($item5);
        $this->assertSame((int) $tx5->id, (int) $item5->transaction_id);
        $this->assertSame(InstallmentItemStatus::Open, $item5->status);
    }

    public function test_manual_create_mark_paid_and_debtor_syncs_loans(): void
    {
        $user = User::factory()->admin()->create();
        $debtor = Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Ana']);

        $response = $this->actingAs($user)->postJson('/api/installment-plans', [
            'title' => 'Notebook',
            'total_count' => 4,
            'installment_amount' => '100.00',
            'first_due_on' => '2026-01-15',
            'debtor_id' => $debtor->id,
            'paid_numbers' => [1],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Notebook')
            ->assertJsonPath('data.paid_count', 1)
            ->assertJsonPath('data.total_count', 4);

        $planId = (int) $response->json('data.id');
        $this->assertSame(3, Loan::query()->where('debtor_id', $debtor->id)->where('status', 'open')->count());

        $this->actingAs($user)
            ->postJson("/api/installment-plans/{$planId}/items/2/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.paid_count', 2);

        $this->assertSame(2, Loan::query()->where('debtor_id', $debtor->id)->where('status', 'open')->count());
    }

    public function test_link_debtor_attaches_whole_installment_plan(): void
    {
        $user = User::factory()->admin()->create();
        $debtor = Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Mateus']);
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-06',
            'type' => 'debit',
            'amount' => '55.87',
            'description' => 'Pneu Free Com – Parcela 9/12',
            'credit_card_id' => $card->id,
            'loan_id' => null,
        ]);

        app(InstallmentPlanService::class)->upsertFromTransaction($user, $tx);

        $this->actingAs($user)
            ->postJson('/api/debtors/'.$debtor->id.'/link-transactions', [
                'transaction_ids' => [$tx->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.linked', 1);

        $plan = InstallmentPlan::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame((int) $debtor->id, (int) $plan->debtor_id);

        // Open items (9..12) get loans; 1..8 were inferred paid.
        $openItems = $plan->items()->where('status', InstallmentItemStatus::Open)->count();
        $this->assertSame(4, $openItems);
        $this->assertSame(
            4,
            Loan::query()->where('debtor_id', $debtor->id)->whereIn('status', ['open', 'partial'])->count(),
        );

        $tx->refresh();
        $this->assertNotNull($tx->loan_id);
    }

    public function test_index_backfills_existing_installment_transactions(): void
    {
        $user = User::factory()->admin()->create();

        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-05',
            'type' => 'debit',
            'amount' => '127.42',
            'description' => 'Magazine Luiza 1/12',
        ]);

        $this->assertSame(0, InstallmentPlan::query()->count());

        $this->actingAs($user)
            ->getJson('/api/installment-plans')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Magazine Luiza')
            ->assertJsonPath('data.0.total_count', 12);
    }
}
