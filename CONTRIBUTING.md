# 🤝 المساهمة في SmartSys ERP | Contributing to SmartSys ERP

شكراً لاهتمامك بالمساهمة في SmartSys! نحن نرحب بالمساهمات من الجميع.

Thank you for your interest in contributing to SmartSys! We welcome contributions from everyone.

---

## 📋 جدول المحتويات | Table of Contents

- [كيف تساهم | How to Contribute](#how-to-contribute)
- [معايير الكود | Code Standards](#code-standards)
- [عملية Pull Request](#pull-request-process)
- [البلاغات والمشاكل | Reporting Issues](#reporting-issues)

---

## 🚀 كيف تساهم | How to Contribute

### 1. Fork المشروع
```bash
# انقر على زر Fork في GitHub
# Click Fork button on GitHub
```

### 2. استنسخ النسخة الخاصة بك
```bash
git clone git@github.com:YOUR-USERNAME/SmartSys-platform.git
cd SmartSys-platform
```

### 3. أنشئ فرع جديد
```bash
git checkout -b feature/your-feature-name
# أو للإصلاحات | or for fixes
git checkout -b fix/your-fix-name
```

### 4. قم بالتعديلات واختبرها
- اكتب كود نظيف ومفهوم
- اتبع معايير الكود الموضحة أدناه
- اختبر تعديلاتك جيداً

### 5. Commit التغييرات
```bash
git add .
git commit -m "feat: وصف واضح للتغيير بالعربية أو الإنجليزية"
```

#### صيغة Commit Messages (Conventional Commits):
- `feat:` ميزة جديدة
- `fix:` إصلاح خطأ
- `docs:` تحديث التوثيق
- `style:` تنسيق الكود (لا يؤثر على المنطق)
- `refactor:` إعادة هيكلة الكود
- `perf:` تحسين الأداء
- `test:` إضافة أو تعديل الاختبارات
- `chore:` مهام صيانة

### 6. ادفع إلى GitHub
```bash
git push origin feature/your-feature-name
```

### 7. افتح Pull Request
- اذهب إلى صفحة المشروع على GitHub
- انقر على "New Pull Request"
- اختر الفرع الذي قمت بإنشائه
- اكتب وصفاً واضحاً للتغييرات

---

## 🎯 معايير الكود | Code Standards

### Backend (PHP)
```php
<?php
// استخدم PSR-12 coding standards
// Use PSR-12 coding standards

namespace App\Handlers;

class ExampleHandler extends BaseHandler
{
    // Type hints مطلوبة
    public function create(Request $request): Response
    {
        // استخدم dependency injection
        // منطق واضح ومفهوم
        // Clear and understandable logic
    }
}
```

**معايير PHP:**
- استخدام PSR-12 coding standard
- Type hints لجميع المعاملات والمرجعات
- DocBlocks للوظائف المعقدة
- تسمية واضحة للمتغيرات والدوال
- تجنب التكرار (DRY principle)

### Frontend (Vue.js)
```javascript
// استخدم Composition API
// Use Composition API
<script setup>
import { ref, onMounted } from 'vue';

const data = ref([]);

// أسماء واضحة ومعبرة
// Clear and descriptive names
const fetchData = async () => {
  // منطق واضح
  // Clear logic
};

onMounted(() => {
  fetchData();
});
</script>
```

**معايير Vue:**
- استخدم Composition API مع `<script setup>`
- استخدم Pinia للـ state management
- Components صغيرة ومركزة
- استخدم TypeScript حيثما أمكن
- تجنب `any` types

### Database
- استخدم prepared statements دائماً
- لا تستخدم `SELECT *` - حدد الأعمدة المطلوبة
- أضف indexes للأعمدة المستخدمة في WHERE/JOIN
- استخدم transactions للعمليات المتعددة

---

## 📝 عملية Pull Request | Pull Request Process

### قبل إرسال PR
- ✅ تأكد من أن الكود يعمل بدون أخطاء
- ✅ اختبر جميع الحالات (Success, Error, Edge Cases)
- ✅ تأكد من عدم وجود console.log أو var_dump
- ✅ حدّث التوثيق إذا لزم الأمر
- ✅ تأكد من اتباع معايير الكود

### وصف PR
يجب أن يحتوي PR على:

```markdown
## الوصف | Description
وصف واضح للتغييرات

## نوع التغيير | Type of Change
- [ ] ميزة جديدة | New feature
- [ ] إصلاح خطأ | Bug fix
- [ ] تحسين أداء | Performance improvement
- [ ] إعادة هيكلة | Refactoring
- [ ] توثيق | Documentation

## الاختبار | Testing
كيف تم اختبار هذه التغييرات؟

## Screenshots (إن وجدت)
أضف لقطات شاشة للتغييرات البصرية

## ملاحظات إضافية | Additional Notes
أي ملاحظات مهمة للمراجعين
```

### مراجعة الكود
- سيتم مراجعة جميع PRs من قبل maintainers
- قد يُطلب منك إجراء تعديلات
- التزم بالتعليقات البنّاءة
- كن صبوراً - المراجعة تستغرق وقتاً

---

## 🐛 البلاغات والمشاكل | Reporting Issues

### قبل فتح Issue
1. ابحث في Issues الموجودة للتأكد من عدم وجود تقرير مشابه
2. استخدم أحدث نسخة من المشروع
3. اجمع معلومات كافية عن المشكلة

### عند فتح Issue جديد
```markdown
## وصف المشكلة | Issue Description
وصف واضح ومفصل للمشكلة

## خطوات إعادة الإنتاج | Steps to Reproduce
1. افتح الصفحة X
2. اضغط على زر Y
3. لاحظ الخطأ Z

## السلوك المتوقع | Expected Behavior
ما الذي كان يجب أن يحدث؟

## السلوك الفعلي | Actual Behavior
ما الذي حدث فعلياً؟

## البيئة | Environment
- نظام التشغيل: Windows/Linux/Mac
- المتصفح: Chrome/Firefox/Safari
- نسخة PHP:
- نسخة Node:

## Screenshots/Logs
أضف لقطات شاشة أو logs إن أمكن
```

---

## 🔐 الأمان | Security

إذا اكتشفت ثغرة أمنية:
- **لا تفتح issue عام**
- راسل المشرفين مباشرة
- قدم تفاصيل كافية لإعادة الإنتاج
- انتظر حتى يتم حل المشكلة قبل الإفصاح العام

If you discover a security vulnerability:
- **Do not open a public issue**
- Contact maintainers directly
- Provide sufficient details to reproduce
- Wait for fix before public disclosure

---

## 📞 التواصل | Contact

- GitHub Issues: للمشاكل التقنية
- GitHub Discussions: للأسئلة والنقاشات
- Pull Requests: للمساهمات في الكود

---

## ⚖️ الرخصة | License

بالمساهمة في هذا المشروع، فإنك توافق على أن تكون مساهمتك مرخصة تحت رخصة MIT.

By contributing to this project, you agree that your contributions will be licensed under the MIT License.

---

**شكراً لمساهمتك! 🙏**
**Thank you for your contribution! 🙏**
