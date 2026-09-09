# 📋 إدارة اللوجات - SmartSys

## 🐛 المشكلة التي تم إصلاحها

### الأعراض:
- ملف `logs/error.log` يكبر بسرعة (200KB+)
- آلاف الرسائل المتكررة:
  ```
  PHP Deprecated: Using ${var} in strings is deprecated, use {$var} instead
  in vendor/phpgangsta/googleauthenticator/PHPGangsta/GoogleAuthenticator.php on line 112
  ```
- اللوجات تمتلئ كل دقيقة من token refresh أو cron jobs

### السبب:
- مكتبة `phpgangsta/googleauthenticator` قديمة
- تستخدم syntax قديم `${var}` بدلاً من `{$var}`
- PHP 8.1+ يُصدر deprecation warning
- الـ warning يتكرر مع كل request يستخدم 2FA

---

## ✅ الحلول المُطبقة

### 1. إصلاح المكتبة (مؤقت)
**الملف:** `vendor/phpgangsta/googleauthenticator/PHPGangsta/GoogleAuthenticator.php`

```php
// ❌ قبل (السطر 112):
return "https://api.qrserver.com/v1/create-qr-code/?data=$urlencoded&size=${width}x${height}&ecc=$level";

// ✅ بعد:
return "https://api.qrserver.com/v1/create-qr-code/?data=$urlencoded&size={$width}x{$height}&ecc=$level";
```

**ملاحظة:** هذا التعديل سيُمسح عند تشغيل `composer update`. استخدم الحل #2 للحل الدائم.

---

### 2. إخفاء Deprecation Warnings (دائم)
**الملف:** `public/index.php`

```php
// في بداية الملف:
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0'); // Don't display errors on screen
ini_set('log_errors', '1');     // Log errors to file
```

**ماذا يفعل:**
- يُخفي `E_DEPRECATED` و `E_STRICT` warnings
- يُبقي على `E_ERROR`, `E_WARNING`, `E_NOTICE` في اللوجات
- لا يعرض الأخطاء على الشاشة (أمان أفضل)

**متى تستخدمه:**
- ✅ Production (يجب استخدامه دائماً)
- ⚠️ Development (اختياري - يُخفي تحذيرات مفيدة)

---

### 3. تنظيف اللوجات الحالية
```bash
# تم تنظيف error.log من 200KB إلى ~200 bytes
```

**الملفات:**
- ✅ `logs/error.log` - نُظّف
- ✅ `logs/.gitignore` - أُضيف (يتجاهل *.log في Git)

---

### 4. Script تنظيف تلقائي
**الملف:** `scripts/cleanup-logs.php`

**الاستخدام:**
```bash
# حذف اللوجات الأقدم من 7 أيام (افتراضي)
php scripts/cleanup-logs.php

# حذف اللوجات الأقدم من 30 يوم
php scripts/cleanup-logs.php --days=30

# حذف اللوجات الأقدم من يوم واحد
php scripts/cleanup-logs.php --days=1
```

**الخرج:**
```
🧹 SmartSys Log Cleanup
━━━━━━━━━━━━━━━━━━━━━━━━━━━
Logs directory: /path/to/logs
Keep logs for:  7 days

✅ Keeping: error.log (0.1 days old, 1.2 KB)
🗑️  Deleted: error-2026-09-01.log (7.2 days old, 15.3 MB)

━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Summary:
  Files processed: 2
  Files deleted:   1
  Total size:      15.31 MB
  Cleaned size:    15.3 MB
  Space saved:     15.3 MB
━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Cleanup complete!
```

---

## 🔧 التكوين الموصى به

### Development (.env):
```env
# عرض جميع الأخطاء
APP_ENV=development
LOG_LEVEL=debug
```

```php
// public/index.php
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);  // Show everything including deprecations
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}
```

### Production (.env):
```env
APP_ENV=production
LOG_LEVEL=warning  # أو error
```

```php
// public/index.php (كما هو الآن)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
```

---

## 📊 مراقبة اللوجات

### فحص حجم اللوجات:
```bash
# Windows PowerShell
Get-ChildItem logs/*.log | Select-Object Name, Length, LastWriteTime

# Linux/Mac
ls -lh logs/*.log
du -sh logs/
```

### مراقبة اللوجات مباشرة:
```bash
# Windows PowerShell
Get-Content logs/error.log -Wait -Tail 50

# Linux/Mac
tail -f logs/error.log
```

### إحصائيات اللوجات:
```bash
# عدد الأخطاء
Select-String -Path logs/error.log -Pattern "PHP Fatal error" | Measure-Object

# أكثر الأخطاء تكراراً
Select-String -Path logs/error.log -Pattern "PHP" | Group-Object | Sort-Object Count -Descending
```

---

## 🔄 Cron Job للتنظيف التلقائي

### Linux/Mac (crontab):
```bash
# افتح crontab
crontab -e

# أضف سطر (تنظيف يومي الساعة 3 صباحاً)
0 3 * * * cd /path/to/SmartSys && php scripts/cleanup-logs.php --days=7
```

### Windows (Task Scheduler):
```powershell
# إنشاء Scheduled Task
$action = New-ScheduledTaskAction -Execute "php" -Argument "C:\xampp\htdocs\SmartSys\scripts\cleanup-logs.php --days=7"
$trigger = New-ScheduledTaskTrigger -Daily -At 3am
Register-ScheduledTask -TaskName "SmartSys-LogCleanup" -Action $action -Trigger $trigger
```

---

## 🚨 تنبيهات مهمة

### ⚠️ لا تُخفي الأخطاء الحرجة!
```php
// ❌ خطأ - يُخفي جميع الأخطاء
error_reporting(0);

// ✅ صحيح - يُخفي deprecations فقط
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
```

### ⚠️ لا تحذف اللوجات الحديثة!
- احتفظ باللوجات لمدة 7-30 يوم على الأقل
- قد تحتاجها للـ debugging أو التدقيق

### ⚠️ راقب مساحة القرص!
```bash
# فحص مساحة القرص
df -h  # Linux/Mac
Get-PSDrive C | Select-Object Used, Free  # Windows
```

---

## 📝 أفضل الممارسات

### 1. Log Rotation
- استخدم log rotation لتقسيم اللوجات حسب التاريخ
- مثال: `error-2026-09-08.log`, `error-2026-09-09.log`

### 2. Log Levels
```php
// استخدم مستويات مختلفة
$logger->debug('Debug info');    // Development only
$logger->info('User logged in'); // General info
$logger->warning('API slow');    // Warnings
$logger->error('DB connection failed'); // Errors
$logger->critical('System down'); // Critical
```

### 3. Structured Logging
```php
// ❌ غير منظم
error_log("User 123 did something");

// ✅ منظم (JSON)
error_log(json_encode([
    'level' => 'info',
    'message' => 'User action',
    'user_id' => 123,
    'action' => 'login',
    'timestamp' => date('c')
]));
```

### 4. External Log Management
للـ production الكبيرة، استخدم:
- **ELK Stack** (Elasticsearch + Logstash + Kibana)
- **Graylog**
- **Sentry** (للأخطاء فقط)
- **Loggly**

---

## ✅ الخلاصة

**المشكلة حُلّت بـ 3 طرق:**

1. ✅ **إصلاح المكتبة** - تغيير `${var}` إلى `{$var}` (مؤقت)
2. ✅ **إخفاء deprecations** - `error_reporting()` في `public/index.php` (دائم)
3. ✅ **تنظيف اللوجات** - `scripts/cleanup-logs.php` (صيانة)

**النتيجة:**
- ❌ قبل: 200KB+ من deprecation warnings كل دقيقة
- ✅ بعد: فقط أخطاء حقيقية في اللوجات

**الصيانة:**
- جرّب `php scripts/cleanup-logs.php` شهرياً
- أو أضفها في cron job لتنظيف تلقائي

🎉 **اللوجات نظيفة ومنظمة!**
