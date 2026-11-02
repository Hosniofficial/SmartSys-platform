<?php

declare(strict_types=1);

use App\Services\SaleBalanceCalculator;
use PHPUnit\Framework\TestCase;

final class SaleBalanceCalculatorTest extends TestCase
{
    public function testCashRefundDoesNotTurnTheRemainingPaidSaleIntoDebt(): void
    {
        self::assertSame(
            0.0,
            SaleBalanceCalculator::remaining(3000.0, 1500.0, 1500.0, 0.0)
        );
        self::assertSame(
            'paid',
            SaleBalanceCalculator::status(3000.0, 1500.0, 1500.0, 1500.0, 0.0, true)
        );
    }

    public function testFullCashRefundKeepsTheSaleReturned(): void
    {
        self::assertSame(
            'returned',
            SaleBalanceCalculator::status(3000.0, 0.0, 3000.0, 3000.0, 0.0, true)
        );
    }

    public function testReturnCreditsContinueToReduceOutstandingBalance(): void
    {
        self::assertSame(
            1000.0,
            SaleBalanceCalculator::remaining(3000.0, 1000.0, 0.0, 1000.0)
        );
    }
}
