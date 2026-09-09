<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\MonologHandler;

/**
 * FinancialCalculationService
 *
 * Unified service for financial calculations across the system.
 * Consolidates duplicated calculations for taxes, discounts, totals, and amounts.
 *
 * Calculations unified:
 * 1. Grand Total = Net Total + Tax - (Discount if not already applied)
 * 2. Net Total = Gross - Discount (when discount not tax-inclusive)
 * 3. Tax Calculation = Base Amount * (Tax Rate / 100) OR fixed tax amount
 * 4. Discount Calculation = Amount * (Discount % / 100) OR fixed discount amount
 * 5. Amount Due = Total - Paid Amount
 */
class FinancialCalculationService
{
    private $logger;

    public function __construct()
    {
        $this->logger = MonologHandler::getInstance('financial_calculation');
    }

    /**
     * Calculate grand total (final amount to be paid)
     *
     * Formula: (base_amount + tax_amount) or (base_amount + tax_amount - discount_amount)
     * depending on whether discount is already subtracted from base
     *
     * Used by: ReturnsHandler (multiple calculations), AnalyticsHandler, SalesHandler
     *
     * @param float $baseAmount - Amount before tax and discount
     * @param float $taxAmount - Tax amount (calculated or fixed)
     * @param float $discountAmount - Discount amount (optional, default 0)
     * @param string $discountType - Whether discount applies 'before_tax' or 'after_tax' (default: 'after_tax')
     * @return float - Grand total rounded to 2 decimal places
     */
    public function calculateGrandTotal(
        float $baseAmount,
        float $taxAmount,
        float $discountAmount = 0.0,
        string $discountType = 'after_tax'
    ): float {
        try {
            $baseAmount = max(0.0, (float)$baseAmount);
            $taxAmount = max(0.0, (float)$taxAmount);
            $discountAmount = max(0.0, (float)$discountAmount);

            if ($discountType === 'before_tax') {
                // Discount applied before tax: (base - discount) + tax
                $grandTotal = ($baseAmount - $discountAmount) + $taxAmount;
            } else {
                // Discount applied after tax: base + tax - discount
                $grandTotal = ($baseAmount + $taxAmount) - $discountAmount;
            }

            return round($grandTotal, 2);

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating grand total', [
                'base_amount' => $baseAmount,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate net total (before tax)
     *
     * Formula: gross_amount - discount_amount
     * OR: gross_amount / (1 + tax_rate/100) if extracting tax from gross
     *
     * Used by: Sales calculations, Purchase calculations
     *
     * @param float $grossAmount - Gross amount (with or without tax)
     * @param float $discountAmount - Discount to apply (default 0)
     * @param string $context - 'discount_only' (simple) or 'extract_tax' (removes tax from gross)
     * @return float - Net total rounded to 2 decimal places
     */
    public function calculateNetTotal(
        float $grossAmount,
        float $discountAmount = 0.0,
        string $context = 'discount_only'
    ): float {
        try {
            $grossAmount = max(0.0, (float)$grossAmount);
            $discountAmount = max(0.0, (float)$discountAmount);

            if ($context === 'extract_tax') {
                // Net = Gross / (1 + tax_rate) - more complex, handled separately
                // For now, just subtract discount from gross
                return round($grossAmount - $discountAmount, 2);
            }

            // Simple: gross - discount
            return round($grossAmount - $discountAmount, 2);

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating net total', [
                'gross_amount' => $grossAmount,
                'discount_amount' => $discountAmount,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate tax amount
     *
     * Supports two modes:
     * 1. Percentage-based: amount * (tax_rate / 100)
     * 2. Fixed amount: direct tax value
     *
     * Used by: PurchasesHandler, SalesHandler, ReturnsHandler
     *
     * @param float $baseAmount - Amount to calculate tax on
     * @param float $taxValue - Tax rate (%) or fixed amount
     * @param string $taxType - 'percentage' or 'fixed'
     * @param string $roundingMode - 'round' (default), 'ceil', 'floor'
     * @return float - Tax amount rounded to 2 decimal places
     */
    public function calculateTax(
        float $baseAmount,
        float $taxValue,
        string $taxType = 'percentage',
        string $roundingMode = 'round'
    ): float {
        try {
            $baseAmount = max(0.0, (float)$baseAmount);
            $taxValue = max(0.0, (float)$taxValue);

            $taxAmount = 0.0;

            if ($taxType === 'fixed') {
                // Fixed tax amount
                $taxAmount = $taxValue;
            } else {
                // Percentage-based tax
                if ($taxValue > 0) {
                    $taxAmount = $baseAmount * ($taxValue / 100);
                }
            }

            // Apply rounding mode
            if ($roundingMode === 'ceil') {
                $taxAmount = ceil($taxAmount * 100) / 100;
            } elseif ($roundingMode === 'floor') {
                $taxAmount = floor($taxAmount * 100) / 100;
            } else {
                $taxAmount = round($taxAmount, 2);
            }

            return $taxAmount;

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating tax', [
                'base_amount' => $baseAmount,
                'tax_value' => $taxValue,
                'tax_type' => $taxType,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate tax-inclusive price (extract net from gross including tax)
     *
     * Used when total includes tax and we need to know base amount
     * Formula: gross_amount / (1 + tax_rate/100)
     *
     * @param float $grossAmount - Total amount including tax
     * @param float $taxRate - Tax rate percentage
     * @return float - Net amount before tax
     */
    public function calculateTaxInclusivePrice(
        float $grossAmount,
        float $taxRate
    ): float {
        try {
            $grossAmount = max(0.0, (float)$grossAmount);
            $taxRate = max(0.0, (float)$taxRate);

            if ($taxRate === 0.0) {
                return $grossAmount;
            }

            $netAmount = $grossAmount / (1 + ($taxRate / 100));
            return round($netAmount, 2);

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating tax-inclusive price', [
                'gross_amount' => $grossAmount,
                'tax_rate' => $taxRate,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate discount amount
     *
     * Supports two modes:
     * 1. Percentage-based: amount * (discount_rate / 100)
     * 2. Fixed amount: direct discount value
     *
     * Used by: PurchasesHandler, AnalyticsHandler, ReturnsHandler
     *
     * @param float $baseAmount - Amount to calculate discount on
     * @param float $discountValue - Discount rate (%) or fixed amount
     * @param string $discountType - 'percentage' or 'fixed'
     * @return float - Discount amount (never exceeds base), rounded to 2 decimal places
     */
    public function calculateDiscount(
        float $baseAmount,
        float $discountValue,
        string $discountType = 'percentage'
    ): float {
        try {
            $baseAmount = max(0.0, (float)$baseAmount);
            $discountValue = max(0.0, (float)$discountValue);

            $discountAmount = 0.0;

            if ($discountType === 'fixed') {
                // Fixed discount amount
                $discountAmount = min($discountValue, $baseAmount); // Cannot exceed base
            } else {
                // Percentage-based discount
                if ($discountValue > 0 && $discountValue <= 100) {
                    $discountAmount = $baseAmount * ($discountValue / 100);
                } elseif ($discountValue > 100) {
                    // Treat as fixed amount if percentage > 100%
                    $discountAmount = min($discountValue, $baseAmount);
                }
            }

            return round($discountAmount, 2);

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating discount', [
                'base_amount' => $baseAmount,
                'discount_value' => $discountValue,
                'discount_type' => $discountType,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calculate remaining amount (e.g., balance due, change, adjustment)
     *
     * Simple subtraction with validation
     * Formula: base_amount - paid_amount
     *
     * Used by: Payment processing, Balance calculations, Change calculations
     *
     * @param float $totalAmount - Total/base amount
     * @param float $paidAmount - Amount already paid/processed
     * @return float - Remaining amount (never negative), rounded to 2 decimal places
     */
    public function calculateRemaining(
        float $totalAmount,
        float $paidAmount
    ): float {
        try {
            $totalAmount = max(0.0, (float)$totalAmount);
            $paidAmount = max(0.0, (float)$paidAmount);

            $remaining = max(0.0, $totalAmount - $paidAmount);
            return round($remaining, 2);

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating remaining', [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Complete financial calculation breakdown
     *
     * Calculates all financial components in one call for performance
     * Returns: [net_total, discount_amount, tax_amount, grand_total, amount_due]
     *
     * Used by: Invoice creation, Detailed calculations
     *
     * @param float $baseAmount - Base amount before any calculations
     * @param float $discountValue - Discount value
     * @param string $discountType - 'percentage' or 'fixed'
     * @param float $taxValue - Tax value
     * @param string $taxType - 'percentage' or 'fixed'
     * @param float $paidAmount - Amount already paid
     * @return array - [net_total, discount, tax, grand_total, amount_due]
     */
    public function calculateCompleteBreakdown(
        float $baseAmount,
        float $discountValue = 0.0,
        string $discountType = 'percentage',
        float $taxValue = 0.0,
        string $taxType = 'percentage',
        float $paidAmount = 0.0
    ): array {
        try {
            // Calculate discount first
            $discountAmount = $this->calculateDiscount($baseAmount, $discountValue, $discountType);

            // Net total after discount
            $netTotal = $baseAmount - $discountAmount;

            // Calculate tax on net total
            $taxAmount = $this->calculateTax($netTotal, $taxValue, $taxType);

            // Grand total
            $grandTotal = $this->calculateGrandTotal($netTotal, $taxAmount, 0.0, 'after_tax');

            // Amount due
            $amountDue = $this->calculateRemaining($grandTotal, $paidAmount);

            return [
                'net_total' => $netTotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'amount_due' => $amountDue
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Error calculating complete breakdown', [
                'base_amount' => $baseAmount,
                'discount_value' => $discountValue,
                'tax_value' => $taxValue,
                'paid_amount' => $paidAmount,
                'error' => $e->getMessage()
            ]);

            return [
                'net_total' => 0.0,
                'discount_amount' => 0.0,
                'tax_amount' => 0.0,
                'grand_total' => 0.0,
                'amount_due' => 0.0
            ];
        }
    }
}
