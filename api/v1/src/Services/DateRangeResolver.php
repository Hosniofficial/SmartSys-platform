<?php

declare(strict_types=1);

namespace App\Services;

/**
 * DateRangeResolver
 *
 * المرجع الوحيد (Single Source of Truth) لتطبيع فلاتر التاريخ عبر كل تقارير وواجهات
 * النظام. تم اكتشاف أثناء المراجعة المعمارية أن نفس المفهوم ("فترة من تاريخ إلى تاريخ")
 * كان يُطبَّق بأكثر من طريقة مختلفة في نفس النظام:
 *
 *   1) AccountStatementHandler::getTransactions      → entry_date >= start AND entry_date < end+1day
 *   2) AccountingReportsHandler::resolveDateRange     → نفس النمط (١) — هذا هو النمط الصحيح
 *   3) AdvancedReportsHandler::getProfitLossReport    → DATE(created_at) BETWEEN start AND end
 *   4) SalesAnalyticsHandler::dailySalesSummary       → created_at BETWEEN 'start 00:00:00' AND 'end 23:59:59'
 *   5) PosAnalyticsHandler::cashierDashboardSummary   → created_at BETWEEN start AND end (بدون تطبيع وقت
 *                                                        عند تمرير تاريخ فقط من العميل — خطأ حقيقي: يستبعد
 *                                                        تقريباً كل سجلات اليوم الأخير)
 *
 * هذه الخدمة توحّد الجميع على النمط رقم (2) — النطاق نصف المفتوح [start, end) — لأنه:
 *   - يعمل بشكل صحيح مع أعمدة DATETIME/TIMESTAMP بغض النظر عن دقة الثواني/الميلي ثانية.
 *   - لا يعتمد على DATE() على العمود (والذي يمنع استخدام أي index على العمود في MySQL).
 *   - آمن تماماً سواء مرَّر المستخدم 'YYYY-MM-DD' أو 'YYYY-MM-DD HH:MM:SS'.
 */
class DateRangeResolver
{
    /**
     * يطبّع start/end (بأي صيغة مدخلة) إلى نطاق نصف مفتوح جاهز للاستخدام مباشرة كـ:
     *   {$column} >= ? AND {$column} < ?
     *
     * @param string|null $start          تاريخ البداية كما وصل من الطلب (قد يكون فارغاً)
     * @param string|null $end            تاريخ النهاية كما وصل من الطلب (قد يكون فارغاً)
     * @param int         $defaultDaysBack عدد الأيام الافتراضي للخلف إذا لم يُمرَّر start/end
     *
     * @return array{0: string, 1: string} [fromDateTimeInclusive, toDateTimeExclusive]
     */
    public static function resolve(?string $start, ?string $end, int $defaultDaysBack = 30): array
    {
        $start = ($start !== null && $start !== '') ? $start : date('Y-m-d', strtotime("-{$defaultDaysBack} days"));
        $end   = ($end   !== null && $end   !== '') ? $end   : date('Y-m-d');

        // أخذ جزء التاريخ فقط (أول 10 حروف) بغض النظر عمّا إذا وصل معه وقت أم لا —
        // يضمن نفس النتيجة سواء أرسل العميل '2026-08-01' أو '2026-08-01 14:30:00'،
        // وهو الثغرة التي كانت تُسبِّب استبعاد سجلات اليوم الأخير عند تمرير تاريخ فقط
        // (راجع PosAnalyticsHandler::cashierDashboardSummary).
        $startDateOnly = substr($start, 0, 10);
        $endDateOnly   = substr($end, 0, 10);

        $fromInclusive = $startDateOnly . ' 00:00:00';
        // نهاية نصف مفتوحة = بداية اليوم التالي لتاريخ النهاية — يضمن تغطية كامل
        // اليوم الأخير (حتى 23:59:59.999) دون الحاجة لـ DATE() على العمود.
        $toExclusive = date('Y-m-d', strtotime($endDateOnly . ' +1 day')) . ' 00:00:00';

        return [$fromInclusive, $toExclusive];
    }

    /**
     * يبني شرط SQL جاهز + قيمه، لتفادي إعادة كتابة نفس النمط يدوياً في كل استعلام.
     *
     * مثال:
     *   [$sql, $vals] = DateRangeResolver::buildCondition('s.created_at', $start, $end);
     *   // $sql  = 's.created_at >= ? AND s.created_at < ?'
     *   // $vals = ['2026-07-01 00:00:00', '2026-08-01 00:00:00']
     *
     * @return array{0: string, 1: array{0:string,1:string}}
     */
    public static function buildCondition(string $column, ?string $start, ?string $end, int $defaultDaysBack = 30): array
    {
        [$from, $toExclusive] = self::resolve($start, $end, $defaultDaysBack);
        return ["{$column} >= ? AND {$column} < ?", [$from, $toExclusive]];
    }
}
