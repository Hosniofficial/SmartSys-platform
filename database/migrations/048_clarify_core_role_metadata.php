<?php

declare(strict_types=1);

/** Clarify built-in role names and responsibilities without changing permissions. */
final class ClarifyCoreRoleMetadata
{
    public function __construct(private PDO $pdo)
    {
    }

    public function up(): void
    {
        $roles = [
            'super_admin' => [
                'مدير المشروع',
                'مدير منصة SmartSys، مسؤول إدارة المستأجرين والاشتراكات وإعدادات النظام وصلاحياته على مستوى المنصة.',
            ],
            'admin' => [
                'مدير المنشأة',
                'يدير المحل أو الشركة (المنشأة)، ويشرف على موظفيها وأدوارهم وصلاحياتهم ونطاق الفروع المخصصة لهم.',
            ],
            'manager' => [
                'مشرف الفروع',
                'يشرف على فرع واحد أو عدة فروع يخصصها له مدير المنشأة، وينفذ العمليات الإدارية ضمن صلاحياته ونطاق فروعه.',
            ],
            'branch_manager' => [
                'مسؤول الفرع',
                'يتابع أعمال الفرع أو الفروع المسندة إليه، ويدير العمليات اليومية ضمن الصلاحيات الممنوحة له.',
            ],
            'cashier' => [
                'موظف المبيعات والمرتجعات',
                'ينفذ عمليات البيع وتسجيل المدفوعات والمرتجعات المصرح بها، ويتعامل مع جلسات نقطة البيع ضمن صلاحياته.',
            ],
            'inventory_clerk' => [
                'مسؤول المخزون',
                'يتابع أرصدة وحركات المخزون، وينفذ التسويات والتحويلات وإجراءات المنتجات المصرح بها.',
            ],
            'finance_officer' => [
                'المسؤول المالي',
                'يتابع العمليات والتقارير والاعتمادات المالية التي يمنحها مدير المنشأة، ضمن نطاق المنشأة والفروع المخصصة له.',
            ],
        ];

        $update = $this->pdo->prepare(
            "UPDATE roles
             SET display_name = ?, description = ?, updated_at = CURRENT_TIMESTAMP
             WHERE LOWER(name) = ?
               AND (
                   tenant_id IS NULL
                   OR role_code IS NULL
                   OR SUBSTRING(role_code, 1, 7) <> 'custom_'
               )"
        );

        foreach ($roles as $name => [$displayName, $description]) {
            $update->execute([$displayName, $description, $name]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migration 048 is forward-only to preserve reviewed role descriptions.'
        );
    }
}
