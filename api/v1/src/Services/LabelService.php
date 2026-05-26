<?php

declare(strict_types=1);

namespace App\Services;

/**
 * LabelService
 *
 * Centralises all human-readable label translations used across handlers.
 * Replaces the duplicated refLabel() / statusLabel() private methods that
 * existed independently in SalesHandler, PurchasesHandler, ReturnsHandler,
 * PaymentsHandler, and AccountStatementHandler.
 */
class LabelService
{
    // -------------------------------------------------------------------------
    // Reference-type labels
    // -------------------------------------------------------------------------

    /**
     * Maps a transaction reference type to a localised display label.
     *
     * Supported types:
     *   sale, purchase, sales_return, purchase_return,
     *   receipt, payment, return_payment
     *
     * @param string|null $type   e.g. 'sale', 'purchase_return'
     * @param string      $locale 'ar' (default) or 'en'
     */
    public static function refLabel(?string $type, string $locale = 'ar'): string
    {
        if (!$type) {
            return '';
        }

        $t = strtolower($type);

        $ar = [
            'sale'            => 'فاتورة بيع',
            'sales'           => 'فاتورة بيع',
            'purchase'        => 'فاتورة شراء',
            'purchases'       => 'فاتورة شراء',
            'sales_return'    => 'مرتجع بيع',
            'return_sale'     => 'مرتجع بيع',
            'return'          => 'مرتجع',
            'purchase_return' => 'مرتجع شراء',
            'return_purchase' => 'مرتجع شراء',
            'receipt'         => 'سند قبض',
            'payment'         => 'سند دفع',
            'return_payment'  => 'سداد مرتجع',
            'cash_voucher'    => 'سند نقدي',
            'journal'         => 'قيد يومية',
        ];

        $en = [
            'sale'            => 'Sales Invoice',
            'sales'           => 'Sales Invoice',
            'purchase'        => 'Purchase Invoice',
            'purchases'       => 'Purchase Invoice',
            'sales_return'    => 'Sales Return',
            'return_sale'     => 'Sales Return',
            'return'          => 'Return',
            'purchase_return' => 'Purchase Return',
            'return_purchase' => 'Purchase Return',
            'receipt'         => 'Receipt',
            'payment'         => 'Payment',
            'return_payment'  => 'Return Payment',
            'cash_voucher'    => 'Cash Voucher',
            'journal'         => 'Journal Entry',
        ];

        $map = $locale === 'ar' ? $ar : $en;

        return $map[$t] ?? ($locale === 'ar' ? 'مرجع' : 'Reference');
    }

    // -------------------------------------------------------------------------
    // Status labels
    // -------------------------------------------------------------------------

    /**
     * Maps a transaction/invoice status code to a localised display label.
     *
     * Supported codes:
     *   paid, partially_paid, partial, unpaid, pending, pending_payment,
     *   posted, draft, canceled, cancelled, rejected, completed,
     *   due, returned
     *
     * @param string|null $code   e.g. 'paid', 'partially_paid'
     * @param string      $locale 'ar' (default) or 'en'
     */
    public static function statusLabel(?string $code, string $locale = 'ar'): string
    {
        if (!$code) {
            return '';
        }

        $c = strtolower($code);

        $ar = [
            'paid'            => 'مدفوعة',
            'partially_paid'  => 'مدفوعة جزئياً',
            'partial'         => 'مدفوعة جزئياً',
            'unpaid'          => 'غير مدفوعة',
            'pending'         => 'قيد الانتظار',
            'pending_payment' => 'غير مدفوعة',
            'posted'          => 'مرحلة',
            'draft'           => 'مسودة',
            'canceled'        => 'ملغاة',
            'cancelled'       => 'ملغاة',
            'rejected'        => 'مرفوضة',
            'completed'       => 'مكتملة',
            'due'             => 'مستحقة',
            'returned'        => 'مرتجعة',
        ];

        $en = [
            'paid'            => 'Paid',
            'partially_paid'  => 'Partially Paid',
            'partial'         => 'Partially Paid',
            'unpaid'          => 'Unpaid',
            'pending'         => 'Pending',
            'pending_payment' => 'Pending Payment',
            'posted'          => 'Posted',
            'draft'           => 'Draft',
            'canceled'        => 'Canceled',
            'cancelled'       => 'Canceled',
            'rejected'        => 'Rejected',
            'completed'       => 'Completed',
            'due'             => 'Due',
            'returned'        => 'Returned',
        ];

        $map = $locale === 'ar' ? $ar : $en;

        return $map[$c] ?? ($locale === 'ar' ? 'غير معروف' : 'Unknown');
    }
}
