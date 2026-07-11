# 📚 دليل توحيد الأنماط البرمجية — Backend (PHP/Slim)

**الإصدار:** 1.0
**التاريخ:** 12 يوليو 2026
**الحالة:** معياري إجباري لجميع Pull Requests

---

## 🎯 الهدف

توحيد أنماط الكود عبر جميع ملفات المشروع لتحسين:
- **سهولة القراءة** للمطورين الجدد
- **سهولة الصيانة** والتعديلات المستقبلية
- **اكتشاف الأخطاء** بسهولة
- **الامتثال لـ best practices** في PHP/Slim

---

## ✅ قائمة التدقيق — يجب استيفاء كل بند قبل الـ Commit

```
قبل ما ترفع أي تعديل، تأكد من:

[ ] declare(strict_types=1) أول سطر في الملف
[ ] كل دالة HTTP handler عندها : Response وparameters معلّمة بالنوع
[ ] بيانات الطلب بتتقرأ بـ getParsedBody() مش json_decode
[ ] الـ catch بيمسك \Throwable (إلا لو فيه سبب موثّق للتضييق)
[ ] أي log بيستخدم $this->logger، مفيش error_log()
[ ] أي عملية متعددة الخطوات ملفوفة بمعاملة كاملة
[ ] رسائل الخطأ بتوصف السبب الحقيقي والرسائل المتكررة موحّدة
[ ] فحوصات الصلاحية بتتبع شكل requireAdminAccess() القياسي
[ ] الـ imports صحيحة (ServerRequestInterface as Request)
[ ] مفيش تعليقات "✅ CRITICAL FIX" أو تاريخ تعديلات جوه الكود
[ ] الملف اتعمله format بـ phpcs/php-cs-fixer قبل الـ commit
```

---

## 1️⃣ declare(strict_types=1) — لازم تكون أول سطر

### ❌ **خطأ: غايب أو في مكان غلط**
```php
<?php
namespace App\Handlers;

// ❌ declare غايب تماماً
// أو في مكان خاطئ
class MaintenanceHandler { ... }
```

### ✅ **صحيح: أول سطر بعد PHP tag**
```php
<?php

declare(strict_types=1);

namespace App\Handlers;

use PDO;
use Exception;

class MaintenanceHandler extends BaseHandler { ... }
```

### 📝 **التبرير:**
بدون `strict_types=1`:
- `$id == '5'` يعتبر `true` (loose comparison)
- passing string لـ parameter متوقع int بتعدي بصمت
- الأخطاء النوعية صعبة التتبع وتسبب باگات صعبة الاكتشاف

### 📍 **الملفات المطلوبة تصحيح:**
- `MaintenanceHandler.php`
- `ReturnsHandler.php`
- `SettingsHandler.php`
- `SetupHandler.php`
- `SubscriptionHandler.php`
- `ValidationHandler.php`

---

## 2️⃣ Return Type Hints على كل دوال HTTP Handlers

### ❌ **خطأ: بدون type hints**
```php
// ❌ CashVouchersHandler.php
public function get($request, $response, $id) {
    // بدون return type
}

public function create($request, $response) {
    // بدون parameter types
}
```

### ✅ **صحيح: كل الأنواع معلّمة**
```php
// ✅ النمط الصحيح
public function get(Request $request, Response $response, array $args): Response {
    $id = (int) ($args['id'] ?? 0);
    // ...
    return $response->withJson($data);
}

public function create(Request $request, Response $response): Response {
    $data = $request->getParsedBody() ?? [];
    // ...
    return $response->withStatus(201);
}
```

### 📝 **التبرير:**
- IDE يقدر يساعد بـ autocomplete
- Static analysis tools بتكتشف أخطاء نوعية قبل التشغيل
- الكود أوضح للقراءة

### 📍 **نفس المنطق ينطبق على Class Properties:**

#### ❌ **خطأ: بدون نوع**
```php
class CustomersHandler {
    private $costCenterService;  // ❌
    private $settingsRepo;       // ❌
}
```

#### ✅ **صحيح: مع النوع الصريح**
```php
class CustomersHandler {
    private CostCenterService $costCenterService;  // ✅
    private SettingsRepository $settingsRepo;      // ✅
}
```

---

## 3️⃣ قراءة بيانات الطلب: دايماً getParsedBody()

### ❌ **خطأ: استخدام json_decode مباشرة**
```php
// ❌ CustomersHandler.php
public function createCustomer(Request $request, Response $response): Response {
    $data = json_decode($request->getBody()->getContents(), true);
    // ❌ بتتخطى أي middleware لتحليل الـ body
}

// ❌ JournalEntriesHandler.php
public function create(Request $request, Response $response): Response {
    $body = $request->getBody()->getContents();
    $data = json_decode($body, true);
    // ❌ نفس المشكلة
}
```

### ✅ **صحيح: استخدام getParsedBody()**
```php
// ✅ النمط الصحيح
public function createCustomer(Request $request, Response $response): Response {
    $data = $request->getParsedBody() ?? [];
    
    // معالجة الـ data
    // ...
    
    return $response->withStatus(201)->withJson($data);
}
```

### 📝 **التبرير:**
- `getParsedBody()` تمر عبر جميع middleware الفريموورك
- إذا ضفنا validation middleware مركزي بعدين، يشتغل تلقائياً على كل المكالمات
- `json_decode()` مباشرة بتتخطى كل حاجة وتسبب سلوك غير متوقع

### ⚠️ **الاستثناء الوحيد:**
```php
// لو كان فيه سبب موثّق (مثل تلقي raw JSON من مصدر خارجي)
$data = json_decode($request->getBody()->getContents(), true);
// ضيّف comment يشرح الحالة الخاصة
```

---

## 4️⃣ Exception Handling: catch (\Throwable $e) كقاعدة عامة

### ❌ **خطأ: مسك نوع واحد فقط**
```php
// ❌ BranchHandler.php
public function updateBranch(Request $request, Response $response, array $args): Response {
    try {
        // ...
    } catch (PDOException $e) {
        // ❌ أي استثناء من service تاني هيهرب بدون معالجة
        return $this->errorResponse($response, 'فشل التحديث', 500);
    }
}
```

### ✅ **صحيح: مسك Throwable ثم تضييق لو لزم**
```php
// ✅ النمط الصحيح
public function updateBranch(Request $request, Response $response, array $args): Response {
    try {
        // ...
    } catch (PDOException $e) {
        // لو فيه سبب موثّق للتفرقة في معالجة Database errors
        $this->logger->error('Database error in branch update', ['error' => $e->getMessage()]);
        return $this->errorResponse($response, 'خطأ في قاعدة البيانات', 500);
    } catch (\Throwable $e) {
        // catch الاستثناءات الأخرى
        $this->logger->error('Unexpected error in branch update', ['error' => $e->getMessage()]);
        return $this->errorResponse($response, 'حدث خطأ غير متوقع', 500);
    }
}
```

### 📝 **التبرير:**
- أي exception من validation service أو خدمة تانية مش بتهرب بدون معالجة
- المستخدم يحصل على رسالة خطأ واضحة بدل `500 Internal Server Error` غامق
- الـ logging يسجل كل حاجة صحيح

### ⚠️ **الاستثناء:**
```php
// لو كان فيه منطق معالجة مختلف فعلاً (مثل ValidationException)
} catch (ValidationException $e) {
    // معالجة مخصصة لأخطاء التحقق
    return $this->errorResponse($response, $e->getMessage(), 400);
} catch (\Throwable $e) {
    // الحالات الأخرى
}
```

---

## 5️⃣ الـ Logging: MonologHandler فقط، ممنوع error_log()

### ❌ **خطأ: استخدام error_log() مباشرة**
```php
// ❌ Mailer.php
error_log('Email sent to: ' . $email);

// ❌ TwoFactorEncryptionService.php
error_log('Encryption failed: ' . $e->getMessage());
```

### ✅ **صحيح: استخدام MonologHandler**
```php
// ✅ البداية
private $logger;

public function __construct(PDO $db) {
    $this->logger = MonologHandler::getInstance('channel_name');
}

// ✅ الاستخدام
$this->logger->info('Email sent successfully', [
    'recipient' => $email,
    'timestamp' => date('Y-m-d H:i:s')
]);

$this->logger->error('Encryption failed', [
    'error' => $e->getMessage(),
    'code' => $e->getCode()
]);
```

### 📝 **التبرير:**
- MonologHandler بيضيف context تلقائياً (tenant_id, user_id, ip)
- قابل للتحكم بـ log level حسب البيئة (production vs development)
- متكامل مع النظام المركزي للـ logging
- سهل البحث والتصفية في الـ logs

---

## 6️⃣ الـ Transactions: استخدم TransactionManager أو نمط try/commit/rollback كامل

### ❌ **خطأ: عمليات متعددة بدون transaction**
```php
// ❌ بدون transaction — لو فشل السطر الثاني، الأول اتنفّذ وحده
$stmt1->execute();  // حذف قيد
$stmt2->execute();  // عكس مخزون
$stmt3->execute();  // حذف صفوف
```

### ✅ **الخيار 1: استخدام TransactionManager (مفضّل)**
```php
// ✅ استخدام TransactionManager (إذا كان متاح)
$txMgr = new TransactionManager($this->db);
$txMgr->execute(function() use ($stmts) {
    foreach ($stmts as $stmt) {
        $stmt->execute();
    }
});
```

### ✅ **الخيار 2: نمط try/commit/rollback كامل**
```php
// ✅ النمط اليدوي الصحيح (PurchaseService::deletePurchase)
try {
    $this->db->beginTransaction();
    
    // عملية 1
    $stmt1->execute();
    
    // عملية 2
    $stmt2->execute();
    
    // عملية 3
    $stmt3->execute();
    
    $this->db->commit();
    
} catch (\Throwable $e) {
    if ($this->db->inTransaction()) {
        $this->db->rollBack();
    }
    
    $this->logger->error('Transaction failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

### 📝 **التبرير:**
- جميع العمليات تنجح أو تفشل معاً
- بدون transaction، البيانات بتكون في حالة inconsistent لو فشل أحدهم
- يضمن integrity قاعدة البيانات

---

## 7️⃣ رسائل الخطأ: واضحة وموحّدة

### ❌ **خطأ 1: رسائل عامة مضللة**
```php
// ❌ CategoriesHandler.php — نفس الرسالة لحالات مختلفة
if (!$categoryData) {
    return $this->errorResponse($response, 'غير موجود', 404);  // قد يكون مكرر الاسم أو بيانات ناقصة
}

// ❌ رسالة غير دقيقة
return $this->errorResponse($response, 'خطأ في الاسترجاع', 500);  // دي مش عملية استرجاع!
```

### ❌ **خطأ 2: نفس الرسالة بصيغ مختلفة**
```php
// ❌ عبر المشروع بتلاقي:
'مطلوب معرف المستأجر (Tenant ID).'
'Tenant ID مطلوب'
'معرف المستأجر غير صحيح'
// كل واحد بيستخدم نص مختلف!
```

### ✅ **صحيح: رسائل واضحة وموحّدة**

#### أولاً: عرّف الثوابت في BaseHandler:
```php
// BaseHandler.php
class BaseHandler {
    const ERROR_TENANT_REQUIRED = 'مطلوب معرف المستأجر (Tenant ID).';
    const ERROR_NOT_FOUND = 'المورد المطلوب غير موجود.';
    const ERROR_DUPLICATE = 'هذا السجل موجود بالفعل.';
    const ERROR_VALIDATION = 'البيانات المدخلة غير صحيحة.';
    const ERROR_UNAUTHORIZED = 'ليس لديك صلاحية للوصول.';
    const ERROR_DATABASE = 'حدث خطأ في قاعدة البيانات.';
}
```

#### ثانياً: استخدم الثوابت في كل مكان:
```php
// ✅ بدل تكرار النص
if (!$tenantId) {
    return $this->errorResponse($response, self::ERROR_TENANT_REQUIRED, 403);
}

if (!$category) {
    return $this->errorResponse($response, self::ERROR_NOT_FOUND, 404);
}

if ($existingCategory) {
    return $this->errorResponse($response, self::ERROR_DUPLICATE, 400);
}
```

### ✅ **رسائل مخصصة للحالات الفريدة:**
```php
// لو كانت الحالة فريدة فعلاً وتحتاج رسالة خاصة:
return $this->errorResponse(
    $response,
    'لا يمكن حذف الفئة لأنها تحتوي على منتجات.',
    422
);
```

### 📝 **الفائدة:**
- رسائل موحّدة للمستخدم
- سهل تعديل الرسائل من مكان واحد
- ترجمة لـ أكثر من لغة بسهولة

---

## 8️⃣ فحص الصلاحيات: نمط RBACHandler::requireAdminAccess()

### ❌ **خطأ: أنماط مختلفة في handlers مختلفة**
```php
// ❌ Handler 1
$admin = $this->requireAdmin($request);
if (!$admin) return error;

// ❌ Handler 2
try {
    $this->checkAdminAccess($request);
} catch (Exception $e) {
    return error;
}

// ❌ Handler 3
if ($userData['role'] !== 'admin') return error;
```

### ✅ **صحيح: نفس النمط في كل مكان**
```php
// ✅ RBACHandler pattern — استخدم هذا كمرجع
public function someAdminFunction(Request $request, Response $response): Response {
    // خطوة 1: فحص الصلاحية
    [, , $err] = $this->requireAdminAccess($request, $response);
    if ($err) {
        return $err;
    }
    
    // خطوة 2: فحص الـ Tenant
    $tenantId = $this->extractTenantId($request);
    if (!$tenantId) {
        return $this->errorResponse($response, self::ERROR_TENANT_REQUIRED, 403);
    }
    
    // خطوة 3: منطق الدالة
    try {
        // ... العمل الفعلي
        return $this->jsonResponse($response, $data);
    } catch (\Throwable $e) {
        $this->logger->error('Operation failed', ['error' => $e->getMessage()]);
        return $this->errorResponse($response, self::ERROR_DATABASE, 500);
    }
}
```

### ✅ **helper methods توقع tuple قياسي:**
```php
// توقيع موحّد: [data, ..., $error]
// الفائدة: معروف دايماً أين الـ error

[, , $adminErr] = $this->requireAdminAccess(...);
if ($adminErr) return $adminErr;

[$userId, $tenantId, $validateErr] = $this->validateUserTenant(...);
if ($validateErr) return $validateErr;
```

---

## 9️⃣ الـ Imports: ServerRequestInterface as Request دايماً

### ❌ **خطأ: استخدام RequestInterface**
```php
// ❌ SubscriptionCronHandler.php
use Psr\Http\Message\RequestInterface as Request;

// ❌ RequestInterface مفيهاش getParsedBody()/getAttribute()
public function handleCron(Request $request) {
    // $request->getParsedBody();  // ❌ لا توجد هذه الميثود!
}
```

### ✅ **صحيح: استخدام ServerRequestInterface**
```php
// ✅ النمط الصحيح
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

public function handleCron(Request $request, Response $response): Response {
    $data = $request->getParsedBody() ?? [];  // ✅ موجودة
    $attr = $request->getAttribute('user');   // ✅ موجودة
    // ...
}
```

### 📝 **الفرق:**
- `RequestInterface` — قراءة فقط (immutable)
- `ServerRequestInterface` — قراءة + الوصول للـ server params، attributes، parsed body

---

## 🔟 التعليقات: لا تضع تاريخ أو "✅ CRITICAL FIX"

### ❌ **خطأ: تعليقات تاريخ وتغييرات**
```php
// ❌ AccountStatementHandler.php
/**
 * ✅ CRITICAL FIX (2026-06-15)
 * قبل كان بيرجع أرقام غلط
 * تم الإصلاح بإضافة filter على return_type
 */
public function getBalance() { ... }

// ❌ ReturnService.php (10 أسطر!)
/**
 * ✅ REMOVED: Duplicate status update block (2026-06-15)
 * السابق: كان في حالتين متطابقة بتحدّث الـ status
 * الآن: واحدة فقط
 */
public function processReturn() { ... }
```

### ✅ **صحيح: تعليقات توضح المنطق الحالي فقط**
```php
// ✅ توضح السلوك الحالي
/**
 * Get account balance from journal entries
 * 
 * @param int $accountId - Account ID
 * @return float - Balance amount
 */
public function getBalance(int $accountId): float {
    // Use return_type filter to distinguish between sales and purchase returns
    // This ensures accurate balance calculation
    $stmt = $this->db->prepare("SELECT ... WHERE return_type = ?");
    // ...
}

// ✅ تعليق قصير للمنطق المعقد
public function calculateInterest(): float {
    // Interest calculated daily on outstanding balance
    // Reset on payment or full settlement
    $daily = $balance * ($rate / 365);
    return $daily;
}
```

### 📝 **المكان الصحيح للتاريخ:**
```
✅ Commit message:
commit a8b83e8
Author: Developer
Date: 2026-07-12

Fix email verification by passing real Request object

Before: Tried to instantiate PSR interfaces (Class not found)
After: Pass ServerRequestInterface from createSecureTrial()
```

---

## 1️⃣1️⃣ التنسيق (Indentation): استخدم Formatter

### 📋 **قبل الـ Commit:**

1. **تثبيت php-cs-fixer أو phpcs:**
```bash
composer require --dev friendsofphp/php-cs-fixer
# أو
composer require --dev squizlabs/php_codesniffer
```

2. **تشغيل الـ Formatter:**
```bash
# استخدام PHP CS Fixer
vendor/bin/php-cs-fixer fix api/v1/handlers/YourFile.php --rules=@PSR12

# أو استخدام phpcs
vendor/bin/phpcs --standard=PSR12 api/v1/handlers/YourFile.php
```

3. **قبل الـ Commit:**
```bash
# تشغيل على كل الملفات المعدّلة
vendor/bin/php-cs-fixer fix api/v1/handlers/ --rules=@PSR12
git add .
git commit -m "..."
```

### 📝 **التبرير:**
- توحيد indentation عبر المشروع
- منع مشاكل merge غير ضرورية
- الكود أسهل قراءة

---

## 📋 نموذج Pull Request Checklist

استخدم هذا في وصف كل PR:

```markdown
## PR Checklist

### Code Quality
- [ ] `declare(strict_types=1)` في أول سطر
- [ ] كل functions عندها return type hints
- [ ] كل parameters معلّمة بالنوع
- [ ] استخدام `getParsedBody()` مش `json_decode`
- [ ] Exception handling مع `\Throwable`

### Best Practices
- [ ] استخدام `MonologHandler` للـ logging
- [ ] أي multi-step operation ملفوف بـ transaction
- [ ] رسائل الخطأ واضحة وموحّدة
- [ ] فحوصات الصلاحية تتبع pattern `requireAdminAccess()`
- [ ] imports صحيحة (`ServerRequestInterface as Request`)

### Code Style
- [ ] مفيش تعليقات "✅ CRITICAL FIX"
- [ ] مفيش تاريخ تعديلات في الكود
- [ ] الملف تم تنسيقه بـ php-cs-fixer/phpcs
- [ ] PSR-12 standard

### Documentation
- [ ] أي سلوك غير واضح موثّق
- [ ] function comments موجودة للدوال الحرجة

## Description
...

## Changes
- [ ] ...
```

---

## 🛠️ CI/CD Integration (اختياري)

إذا كان لديك GitHub Actions أو GitLab CI، أضف هذا:

### `.github/workflows/code-quality.yml`
```yaml
name: Code Quality

on: [pull_request]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Install dependencies
        run: composer install --no-interaction
      
      - name: Check strict types
        run: |
          for file in api/v1/handlers/*.php; do
            if ! head -3 "$file" | grep -q "declare(strict_types=1)"; then
              echo "❌ Missing strict_types in $file"
              exit 1
            fi
          done
      
      - name: Run PHP CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
      
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse api/v1
```

---

## 📚 المراجع والموارد

- [PHP PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12/)
- [Slim Framework Documentation](https://www.slimframework.com/)
- [PHP Type Declarations](https://www.php.net/manual/en/language.types.declarations.php)
- [PDO Transactions](https://www.php.net/manual/en/pdo.transactions.php)

---

## 🤝 الدعم والأسئلة

لو عندك أي استفسار عن الأنماط:
1. راجع هذا الملف أولاً
2. اسأل في قناة الـ development
3. فتح discussion في الـ GitHub

---

**آخر تحديث:** 12 يوليو 2026
**معياري:** إجباري لجميع Pull Requests
**التحقق من الالتزام:** من خلال Code Review

---

## ✅ الخلاصة

اتبع الأنماط المحددة هنا لضمان:
- ✅ كود موحّد وسهل الفهم
- ✅ سهولة الصيانة والتطوير
- ✅ جودة عالية وأمان أفضل
- ✅ معايير industry standard

**شكراً لالتزامك بالمعايير! 🎉**
