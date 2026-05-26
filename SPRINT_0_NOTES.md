# Sprint 0 — Completed Tasks (Security Hotfixes)

تاريخ التنفيذ: 2026-05-12

## ✅ ما تم إنجازه تلقائياً

### 1. Rotated JWT / CRON secrets
- تم توليد `JWT_SECRET`, `JWT_REFRESH_SECRET`, `CRON_SECRET` جديدة (64 hex chars, 256-bit entropy).
- تم تحديث `.env` بالقيم الجديدة.
- الأثر: كل access/refresh tokens المُصدرة قبل هذه اللحظة صارت **غير صالحة**. كل المستخدمين النشطين سيُطلب منهم إعادة تسجيل الدخول.

### 2. Cleaned `.env.example`
- الأسرار المسرّبة (`1ac09fad74fa8ff6f6dd88a2a953af63` + refresh + cron) استُبدلت بـ placeholders.
- Placeholders صارت صريحة: `CHANGE_ME_generate_with_openssl_rand_hex_32`.
- أضيف comment يوجّه الأمر لتوليدها بـ PHP: `php -r "echo bin2hex(random_bytes(32));"`.

### 3. أضيف سكربت دائم
- `scripts/generate_secrets.php`: يولّد 3 أسرار بنفس المعيار الآمن. آمن للتشغيل متى احتجنا rotation آخر.

### 4. CSP hardening
- أُزيلت `'unsafe-eval'` من `script-src` في `config/security.php`.
- `'unsafe-inline'` بقيت (تحتاج migration منفصل لـ nonces). علّقتُ هذا في الكود.

### 5. Fixed `lastSubscriptionCheck` cross-user leak
- أُزيل المتغير `lastSubscriptionCheck` و `CACHE_TTL` من `erp-frontend/src/router/index.js`.
- قرار الـ TTL صار مسؤولية `subscriptionStore` وحده عبر `isCacheValid` + `lastFetched`.
- لأن `subscriptionStore.reset()` يُستدعى داخل `auth.login` و `auth.clearAuthData`، الـ cache الآن يُبطل بشكل صحيح عبر تبديل المستخدمين.

---

## ⚠️ مطلوب منك (لا يمكن أتمتته)

### 🔴 HIGH: إبطال SMTP App Password المسرّب

الملف `.env` السابق (المدموج في repo) احتوى على:
```
SMTP_USER=hosniofficial99@gmail.com
SMTP_PASS=yixesvkyyrdosnyg  ← Gmail App Password مكشوف
```

**الخطوات**:
1. افتح <https://myaccount.google.com/apppasswords>.
2. احذف الـ App Password المسمّى لهذا المشروع.
3. أنشئ App Password جديد.
4. حدّث `.env` بالقيمة الجديدة.
5. أعد تشغيل الخادم.

**لماذا**: أي شخص رأى الـ repo بإمكانه إرسال بريد من حسابك.

### 🔴 HIGH: Force logout لكل المستخدمين الحاليين

بما أن JWT secrets تغيّرت، كل الـ refresh_tokens المخزنة عند المستخدمين صارت لاغية — أي طلب refresh سيفشل وسيُعاد توجيههم للـ login. هذا هو السلوك المرغوب.

لكن تأكد من:
- إرسال إشعار للمستخدمين قبل/بعد rotate: "لأسباب أمنية، الرجاء إعادة تسجيل الدخول".
- مسح جدول `refresh_tokens_blacklist` (إن كان يتضخم) — optional.

### 🟡 MEDIUM: فحص لوجات الأمان

لا توجد دلائل قاطعة على استغلال حتى الآن، لكن احترازياً:
```sql
SELECT * FROM security_events
WHERE event_type IN ('login.failed','security.brute_force','security.unauthorized_access')
  AND created_at > NOW() - INTERVAL 30 DAY
ORDER BY created_at DESC LIMIT 200;
```
لو شفت نشاط غير طبيعي من IPs غريبة، راجعها بعناية.

---

## 🧪 اختبار ما بعد الـ rotation

قبل إغلاق Sprint 0، تأكد من:

- [ ] تسجيل الدخول بمستخدم جديد يعمل → يُصدر access+refresh tokens جديدة.
- [ ] محاولة refresh بـ refresh_token قديم (من قبل rotation) → يفشل بـ 401.
- [ ] فتح شاشة POS بعد login → لا أخطاء CSP في console.
- [ ] تسجيل خروج ثم دخول بمستخدم آخر → الـ subscription يُفحص من جديد (ليس cached من المستخدم السابق).

---

## 📁 Files Changed

```
modified:   .env                              (secrets rotated)
modified:   .env.example                      (placeholders only)
modified:   config/security.php               (no unsafe-eval)
modified:   erp-frontend/src/router/index.js  (no module-level cache)
new file:   scripts/generate_secrets.php     (permanent rotation helper)
new file:   SPRINT_0_NOTES.md                (this file)
```

---

## التالي

أنتهيت من Sprint 0. الـ Week 1 يبدأ بـ:
- Permissions catalog class (`Permissions.php`)
- تحسين `PermissionMiddleware`
- DI bindings
- Logout with token revocation proper
