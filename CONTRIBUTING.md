# 🤝 دليل المساهمة في SmartSys

**شكراً لرغبتك بالمساهمة في المشروع! 🎉**

هذا الدليل يوضح كيفية المساهمة بشكل فعّال والالتزام بمعايير المشروع.

---

## 📋 خطوات البدء السريعة

### 1. عمل Fork وClone
```bash
git clone https://github.com/YOUR_USERNAME/SmartSys-platform.git
cd SmartSys-platform
```

### 2. عمل Branch جديد
```bash
git checkout -b feature/your-feature-name
# أو
git checkout -b fix/your-bug-fix
```

### 3. التطوير والـ Testing
```bash
# تطوير الميزة
# ...

# تشغيل الاختبارات
vendor/bin/phpunit

# تشغيل الـ Linter
vendor/bin/php-cs-fixer fix api/v1/handlers/YourFile.php --rules=@PSR12
```

### 4. الـ Commit والـ Push
```bash
git add .
git commit -m "✨ Brief description of changes"
git push origin feature/your-feature-name
```

### 5. فتح Pull Request

---

## ✅ قائمة تدقيق قبل PR

**تأكد من جميع البنود قبل فتح PR:**

### Code Standards
- [ ] `declare(strict_types=1);` في أول سطر
- [ ] كل دوال HTTP handlers عندها `: Response`
- [ ] كل parameters معلّمة بالنوع الصحيح
- [ ] Class properties معلّمة بالنوع

### Best Practices
- [ ] استخدام `$request->getParsedBody()` مش `json_decode`
- [ ] `catch (\Throwable $e)` (إلا لو فيه سبب موثّق)
- [ ] استخدام `$this->logger->` مش `error_log()`
- [ ] عمليات متعددة الخطوات ملفوفة بـ transaction
- [ ] رسائل الخطأ واضحة وموحّدة

### Code Quality
- [ ] الملف تم تنسيقه بـ php-cs-fixer
- [ ] مفيش تعليقات "✅ CRITICAL FIX" أو تاريخ
- [ ] imports صحيحة (`ServerRequestInterface as Request`)
- [ ] مفيش dead code

### Documentation
- [ ] التغييرات موثّقة (Comments، Docblocks)
- [ ] رسالة commit واضحة

---

## 📝 معايير رسالة الـ Commit

### صيغة الـ Commit Message
```
<type>: <subject>

<body>

<footer>
```

### الأنواع (Types)
- `feat:` ميزة جديدة
- `fix:` إصلاح باگ
- `docs:` تحديثات التوثيق
- `refactor:` إعادة هيكلة بدون تغيير السلوك
- `perf:` تحسينات الأداء
- `test:` إضافة أو تحديث اختبارات
- `chore:` تحديثات البناء والتبعيات

### أمثلة
```bash
# ✅ صحيح
git commit -m "fix: handle null userId in getUserPermissions"
git commit -m "feat: add retry logic to generateInvoiceNumber"
git commit -m "docs: update CODING_STANDARDS with strict_types requirement"

# ❌ خطأ
git commit -m "fixed stuff"
git commit -m "✅ CRITICAL FIX (2026-07-12)"
git commit -m "update"
```

---

## 🔍 عملية Code Review

### ماذا يتوقع المراجع؟

1. **Functionality** - هل الميزة/الإصلاح يعمل كما هو متوقع؟
2. **Code Quality** - هل يتبع معايير المشروع؟
3. **Documentation** - هل موثّق بشكل كافي؟
4. **Tests** - هل هناك اختبارات؟ هل تمر؟
5. **Performance** - هل لا يسبب مشاكل أداء؟
6. **Security** - هل هناك ثغرات أمنية؟

### الرد على التعليقات

- الاستجابة السريعة أفضل
- اشرح الخيارات البديلة إذا كنت لا تتفق
- اقبل الملاحظات البناءة بروح رياضية
- اعمل التعديلات المطلوبة ثم أخبر المراجع

---

## 📂 هيكل المشروع

```
api/v1/
├── handlers/          # HTTP request handlers
├── middleware/        # Request/response middleware
├── src/
│   ├── Services/      # Business logic
│   ├── Repositories/  # Database queries
│   ├── Exceptions/    # Custom exceptions
│   ├── Listeners/     # Event listeners
│   ├── Security/      # Security helpers
│   └── Resources/     # Data transformation
└── logs/              # Application logs

config/               # Configuration files
.kiro/
├── specs/            # Feature specifications
└── steering/         # Development guidelines
```

---

## 🛠️ التطوير المحلي

### متطلبات الإعداد
```bash
# PHP 8.1+
php --version

# Composer
composer install

# MySQL/MariaDB (يشغل على XAMPP)
# يجب أن يكون MySQL مشغّل

# التحقق من الاتصال
php -r "new PDO('mysql:host=localhost', 'root', '');" && echo "✅ DB Connection OK"
```

### تشغيل الـ Development Server
```bash
# Slim development server
php -S localhost:8000 -t . api/index.php

# أو عبر PHP CLI
vendor/bin/slim start
```

### تشغيل الاختبارات
```bash
# جميع الاختبارات
vendor/bin/phpunit

# اختبار ملف معين
vendor/bin/phpunit tests/Unit/UserTest.php

# اختبار مع التغطية
vendor/bin/phpunit --coverage-html coverage/
```

---

## 🐛 الإبلاغ عن الأخطاء

### عند اكتشاف باگ

1. **تأكد من أنه باگ فعلاً** (ليس سلوك متوقع)
2. **ابحث عن issues موجودة** قد تغطي نفس المشكلة
3. **أنشئ issue جديد** مع:
   - عنوان واضح
   - وصف المشكلة بالتفصيل
   - خطوات إعادة الإنتاج
   - السلوك المتوقع vs الفعلي
   - معلومات عن البيئة (PHP version، OS، إلخ)

### مثال على Issue جيد
```markdown
**العنوان:** getUserPermissions يسمح بـ cross-tenant access

**الوصف:**
الدالة `RBACHandler::getUserPermissions()` تسمح لأي admin بمشاهدة صلاحيات أي مستخدم
من أي tenant (IDOR vulnerability).

**خطوات الإعادة:**
1. عمل login بـ admin من tenant A
2. استدعاء GET /api/v1/users/{user_from_tenant_b}/permissions
3. النتيجة: الصلاحيات بتظهر رغم أن المستخدم من tenant مختلف

**السلوك المتوقع:**
يجب أن ترفض الطلب مع 404 Not Found

**البيئة:**
- PHP 8.1
- MySQL 8.0
- SmartSys commit a8b83e8
```

---

## 📚 الموارد المفيدة

- [CODING_STANDARDS.md](CODING_STANDARDS.md) - معايير الكود
- [PRODUCTION_READINESS_REPORT.md](PRODUCTION_READINESS_REPORT.md) - حالة الإنتاج الحالية
- [ISSUES_RESOLUTION_REPORT.md](ISSUES_RESOLUTION_REPORT.md) - سجل الإصلاحات
- [Slim Framework Docs](https://www.slimframework.com/)
- [PHP Best Practices](https://www.php.net/manual/)

---

## 🚀 نصائح للمساهمين الجدد

### البدايات الجيدة
- ابدأ بـ issues معلّمة بـ `good first issue`
- اختر feature صغيرة لتعلم سير العمل
- اسأل الأسئلة لو احتجت توضيح

### تجنب هذه الأخطاء
- ❌ تعديل multiple features في PR واحد
- ❌ الالتزام بـ master مباشرة
- ❌ تجاهل معايير المشروع
- ❌ عدم كتابة اختبارات
- ❌ رسائل commit غامضة

### نصائح للنجاح
- ✅ PR صغيرة وركزة (حول موضوع واحد)
- ✅ رسائل commit واضحة
- ✅ اختبارات شاملة
- ✅ توثيق جيد
- ✅ تفاعل إيجابي مع التعليقات

---

## 💬 التواصل

### قنوات الاتصال
- **Issues:** لـ bugs والميزات الجديدة
- **Discussions:** للأسئلة والنقاش
- **Slack/Discord:** للتواصل المباشر (لو متوفر)
- **Email:** (لو موثّق في المشروع)

### الآداب
- كن محترماً وإيجابياً
- تقبل النقد البناء
- ساعد الآخرين
- لا تتخذ الملاحظات بشكل شخصي

---

## 📈 مسارات التطوير الوظيفي

### يمكنك الترقي من:
1. **Contributor** → إصلاحات وميزات صغيرة
2. **Reviewer** → مراجعة PR من مساهمين آخرين
3. **Maintainer** → إدارة المشروع والإصدارات

---

## ✨ الخلاصة

شكراً لمساهمتك! 🎉

اتبع المعايير واسأل عند الالتباس — الفريق هنا لدعمك!

**Happy Coding! 🚀**

---

**آخر تحديث:** 12 يوليو 2026
