<?php

namespace Tests\Unit\Enums;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §2.1 — LoanKind / LoanStatus.
 */
class LoanEnumsTest extends TestCase
{
    public function test_loan_kind_values(): void
    {
        $this->assertSame(['cash', 'card_limit'], LoanKind::values());
        $this->assertSame('cash', LoanKind::Cash->value);
        $this->assertSame('card_limit', LoanKind::CardLimit->value);
    }

    public function test_loan_status_values(): void
    {
        $this->assertSame(
            ['open', 'partial', 'paid', 'cancelled'],
            LoanStatus::values()
        );
    }

    #[DataProvider('kindLabels')]
    public function test_loan_kind_labels(LoanKind $kind, string $label): void
    {
        $this->assertSame($label, $kind->label());
    }

    /**
     * @return list<array{0: LoanKind, 1: string}>
     */
    public static function kindLabels(): array
    {
        return [
            [LoanKind::Cash, 'Dinheiro'],
            [LoanKind::CardLimit, 'Limite do cartão'],
        ];
    }

    #[DataProvider('statusLabels')]
    public function test_loan_status_labels(LoanStatus $status, string $label): void
    {
        $this->assertSame($label, $status->label());
    }

    /**
     * @return list<array{0: LoanStatus, 1: string}>
     */
    public static function statusLabels(): array
    {
        return [
            [LoanStatus::Open, 'Em aberto'],
            [LoanStatus::Partial, 'Parcial'],
            [LoanStatus::Paid, 'Pago'],
            [LoanStatus::Cancelled, 'Cancelado'],
        ];
    }
}
