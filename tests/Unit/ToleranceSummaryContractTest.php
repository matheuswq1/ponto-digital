<?php

namespace Tests\Unit;

use App\Contracts\ToleranceSummaryContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToleranceSummaryContractTest extends TestCase
{
    #[Test]
    public function ordered_reconciliation_follows_contract_keys(): void
    {
        $values = [];
        foreach (ToleranceSummaryContract::RECONCILIATION_KEYS as $key) {
            $values[$key] = match ($key) {
                'identity_holds' => true,
                default => 0,
            };
        }

        $ordered = ToleranceSummaryContract::orderedReconciliation($values);

        $this->assertSame(ToleranceSummaryContract::RECONCILIATION_KEYS, array_keys($ordered));
        $this->assertTrue($ordered['identity_holds']);
    }

    #[Test]
    public function ordered_reconciliation_throws_when_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('total_rows_in_period');

        ToleranceSummaryContract::orderedReconciliation([]);
    }
}
