# Database Migrations

## نسخ الهجرات (Database Migrations)

### كيفية تطبيق الهجرات

#### الطريقة 1: من خلال phpMyAdmin
1. افتح phpMyAdmin
2. اختر قاعدة البيانات `smartsys`
3. اضغط على تبويب **SQL**
4. انسخ محتوى الملف `.sql`
5. اضغط **GO**

#### الطريقة 2: من خلال سطر الأوامر (CLI)
```bash
# للـ Windows
mysql -u root -p smartsys < database/migrations/add_promo_3months_plan.sql

# للـ Linux/Mac
mysql -u root -p smartsys < database/migrations/add_promo_3months_plan.sql
```

#### الطريقة 3: من خلال PHP CLI
```bash
php database/migrations/apply_migrations.php
```

---

## قائمة الهجرات

### 1. add_promo_3months_plan.sql
- **الوصف:** إضافة خطة ترويجية جديدة (3 أشهر مجانية)
- **الحالة:** جديد ✨
- **التاريخ:** 2026-09-12
- **الكود:** `promo_3months`
- **المدة:** 90 يوم
- **السعر:** 0.00 EGP

---

## ملاحظات مهمة

⚠️ **قبل تطبيق أي هجرة:**
1. تأكد من عمل نسخة احتياطية من قاعدة البيانات
2. اختبر الهجرة على بيئة الاختبار أولاً
3. تحقق من سجل الأخطاء (Logs)

✅ **بعد تطبيق الهجرة:**
1. تحقق من نجاح التطبيق من phpMyAdmin
2. اختبر الوظيفة من الواجهة الأمامية
3. تحقق من جدول `plans` للتأكد من إضافة الخطة الجديدة

---

## استكشاف الأخطاء

**المشكلة:** "Duplicate entry for key 'code'"
**الحل:** الخطة موجودة بالفعل في قاعدة البيانات (لا توجد مشكلة)

**المشكلة:** "Table 'smartsys.plans' doesn't exist"
**الحل:** تأكد من أن قاعدة البيانات مُثبتة بشكل صحيح

**المشكلة:** خطأ في الوصول (Permission denied)
**الحل:** تأكد من أن المستخدم له صلاحيات التعديل على قاعدة البيانات
