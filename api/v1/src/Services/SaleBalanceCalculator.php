<?php

declare(strict_types=1);

namespace App\Services;

final class SaleBalanceCalculator
{
    public static function remaining(
        float $grandTotal,
        float $netPaidAmount,
        float $cashRefundAmount,
        float $returnCredits
    ): float {
        return max(0.0, round($grandTotal - $netPaidAmount - $cashRefundAmount - $returnCredits, 2));
    }

    public static function status(
        float $grandTotal,
        float $netPaidAmount,
        float $cashRefundAmount,
        float $returnAmount,
        float $returnCredits,
        bool $hasDirectReturn
    ): string {
        if ($grandTotal <= 0) {
            return 'paid';
        }
        if ($returnAmount >= $grandTotal) {
            return 'returned';
        }

        $paidAmount = $netPaidAmount + $cashRefundAmount;
        $outstanding = self::remaining($grandTotal, $paidAmount, 0.0, $returnCredits);
        if ($outstanding < 0.01) {
            if ($paidAmount >= $grandTotal - 0.01) {
                return 'paid';
            }
            if ($paidAmount < 0.01) {
                if ($returnCredits > 0.01 && !$hasDirectReturn) {
                    return 'settled_by_credit';
                }
                return 'closed_by_return';
            }
            if ($returnCredits > 0.01 && $paidAmount > 0.01) {
                return 'settled_mixed';
            }
            return 'closed_by_return';
        }

        if ($paidAmount <= 0) {
            return 'pending_payment';
        }

        return 'partial';
    }
}
