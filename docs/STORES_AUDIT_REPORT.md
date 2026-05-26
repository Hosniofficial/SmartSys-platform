# تقرير مراجعة Stores - Smart Sys

## 📋 ملخص المراجعة
تم فحص شامل للمشروع للتحقق من استخدام Pinia stores بشكل موحد وتطبيقها الصحيح على جميع الصفحات.

**النتيجة: ❌ عدم اكتمال التوحيد - المشروع يستخدم نمطين مختلفين للوصول للبيانات**

---

## 📊 الحالة الحالية

### ✅ ما تم تطبيقه بشكل صحيح:

1. **الـ Store Framework** موجود وفعال:
   - ✅ Pinia مثبت ومُهيأ في `main.js`
   - ✅ وجود stores منظمة بشكل جيد:
     - `stores/auth.js`
     - `stores/product/productStore.js`
     - `stores/analytics.js`
     - `stores/branch.js`
     - `stores/catalog/catalogStore.js`
     - `stores/customer/customerStore.js`
     - `stores/sales/salesStore.js`
     - وغيرها

2. **الـ Views التي تستخدم Stores بشكل صحيح**:
   - ✅ `ProductManagement.vue` - يستخدم:
     - `useProductStore`
     - `useBranchStore`
     - `useCatalogStore`
   
   - ✅ `SalesPoint.vue` - يستخدم:
     - `useProductStore`
     - `useBranchStore`
     - `useAuthStore`

3. **معمارية الـ Stores جيدة**:
   - ✅ Caching مع TTL (Time To Live)
   - ✅ عدم تكرار الاستدعاءات (In-flight prevention)
   - ✅ معالجة الأخطاء

---

## ❌ المشاكل المكتشفة

### المشكلة 1: استخدام Services مباشرة بدلاً من Stores

**الملفات المتأثرة:**
```
❌ WarrantyManagement.vue
   - يستخدم: import { WarrantyService } from '@/services/warranty'
   - الاستدعاءات:
     • WarrantyService.list(params)
     • WarrantyService.create(payload)
     • WarrantyService.get(id)
     • WarrantyService.changeStatus()
     • WarrantyService.addNote()
     • WarrantyService.uploadAttachment()

❌ BulkDistribution.vue
   - يستخدم:
     • BranchesService.getAll()
     • searchProducts() من BranchInventoryService
     • bulkAdjustProduct() من BulkAdjustmentsService
     • AlertService.* operations

❌ SuppliersManagement.vue
   - يستخدم:
     • paymentService مباشرة
     • AlertService مباشرة

❌ CustomersManagement.vue
   - يستخدم:
     • paymentService مباشرة
     • AlertService مباشرة

❌ BranchManagement.vue
   - يستخدم:
     • BranchesService.getAll() مباشرة
     • AlertService مباشرة

❌ CashierDashboard.vue
   - يستخدم:
     • SessionsService.getSummary()
     • paymentService مباشرة
     • terminalsService مباشرة
     • shiftsService مباشرة
```

### المشكلة 2: عدم وجود Store لـ Warranty

**الملف:** `WarrantyManagement.vue`
```javascript
// الحالي (❌ غير موحد):
import { WarrantyService } from '@/services/warranty';
const res = await WarrantyService.list(params);

// المطلوب (✅ موحد):
import { useWarrantyStore } from '@/stores/warranty/warrantyStore';
const warrantyStore = useWarrantyStore();
const res = await warrantyStore.fetchWarranties(params);
```

### المشكلة 3: عدم وجود Stores لـ:
- ❌ Branches (الفروع) - يستخدمون `BranchesService` مباشرة
- ❌ Suppliers (الموردون) - يستخدمون `paymentService` مباشرة
- ❌ Customers (العملاء) - موجود store لكن بعض الملفات تستخدم service مباشرة
- ❌ Shifts (الوورديات) - يستخدمون `shiftsService` مباشرة
- ❌ Terminals (الصرافات) - يستخدمون `terminalsService` مباشرة
- ❌ Sessions (الجلسات) - يستخدمون `SessionsService` مباشرة
- ❌ Payments (المدفوعات) - يستخدمون `paymentService` مباشرة

---

## 📈 الوضع التفصيلي حسب الـ Module

| Module | Status | الملفات المتأثرة | الحل المطلوب |
|--------|--------|----------------|---------:|
| Products | ✅ موحد | productStore.js | - |
| Branches | ❌ غير موحد | BranchManagement, BulkDistribution | إنشاء branchStore.js |
| Warranty | ❌ غير موحد | WarrantyManagement.vue | إنشاء warrantyStore.js |
| Customers | ⚠️ جزئي | بعض الملفات تستخدم service | توحيد استخدام customerStore |
| Sessions | ❌ غير موحد | CashierDashboard.vue | إنشاء sessionStore.js |
| Shifts | ❌ غير موحد | CashierDashboard.vue | إنشاء shiftsStore.js |
| Terminals | ❌ غير موحد | CashierDashboard.vue | إنشاء terminalsStore.js |
| Payments | ❌ غير موحد | كل الملفات المتعلقة | إنشاء paymentStore.js |
| Suppliers | ❌ غير موحد | SuppliersManagement.vue | استخدام contactStore أو supplierStore |

---

## 🔧 الحل الموصى به

### المرحلة 1: إعادة هيكلة الـ Stores الموجودة
**الملفات المطلوب تعديلها:**
```javascript
// 1. إضافة store للـ Warranty
src/stores/warranty/warrantyStore.js

// 2. إضافة store للـ Sessions
src/stores/session/sessionStore.js

// 3. إضافة store للـ Shifts
src/stores/shift/shiftStore.js

// 4. إضافة store للـ Terminals
src/stores/terminal/terminalStore.js

// 5. تحسين payment store أو إنشاء واحد جديد
src/stores/payment/paymentStore.js

// 6. تحسين branch store أو إنشاء واحد جديد
src/stores/branch/branchStore.js (الموجود)

// 7. توحيد supplier store
src/stores/supplier/supplierStore.js
```

### المرحلة 2: تحديث جميع الـ Views
**الملفات المطلوب تعديلها:**

1. `WarrantyManagement.vue` - استبدال `WarrantyService` بـ `useWarrantyStore`
2. `BulkDistribution.vue` - استبدال Services بـ Stores
3. `CashierDashboard.vue` - استبدال جميع Services بـ Stores
4. `BranchManagement.vue` - توحيد استخدام `branchStore`
5. `SuppliersManagement.vue` - استبدال `paymentService` بـ Store
6. `CustomersManagement.vue` - توحيد استخدام `customerStore`

---

## ✨ الفوائد المتوقعة بعد التوحيد

1. **Centralized State Management**: جميع البيانات في مكان واحد
2. **Better Caching**: تقليل استدعاءات API المكررة
3. **Easier Testing**: أسهل لكتابة Unit Tests
4. **Consistency**: نمط واحد موحد في المشروع
5. **Maintainability**: أسهل للصيانة والتطوير المستقبلي
6. **Time Travel Debugging**: يمكن تتبع تغييرات الحالة
7. **Dev Tools**: استخدام Pinia DevTools للتصحيح

---

## 🚀 الخطوات التالية (الأولويات)

### Immediate (يومي):
1. ✅ إنشاء `warrantyStore.js` لـ WarrantyManagement.vue
2. ✅ إنشاء `sessionStore.js` لـ CashierDashboard.vue
3. ✅ توحيد استخدام `branchStore.js` في جميع الملفات

### Short-term (أسبوع واحد):
4. ✅ إنشاء `shiftStore.js` و `terminalStore.js` و `paymentStore.js`
5. ✅ تحديث `BulkDistribution.vue` و `CashierDashboard.vue`

### Medium-term (أسبوعين):
6. ✅ توحيد جميع الـ Views المتبقية
7. ✅ إضافة caching للـ Services الثقيلة
8. ✅ كتابة Unit Tests للـ Stores الجديدة

---

## 📝 نموذج Store الموصى به

```javascript
// stores/example/exampleStore.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import apiClient from '@/config/axios';

const nowMs = () => Date.now();

export const useExampleStore = defineStore('example', () => {
  // ─── Cache Configuration ───
  const TTL = {
    items: 5 * 60 * 1000, // 5 دقائق
  };

  // ─── State ───
  const items = ref({});
  const itemsFetchedAt = ref({});
  const itemsInFlight = ref({});

  // ─── Helpers ───
  const isFresh = (fetchedAt, ttl) =>
    !!fetchedAt && (nowMs() - fetchedAt) < ttl;

  // ─── Actions ───
  const fetchItems = async ({ force = false } = {}) => {
    try {
      const cacheKey = 'all';
      const cached = items.value[cacheKey];
      const cachedAt = itemsFetchedAt.value[cacheKey];

      if (!force && cached && isFresh(cachedAt, TTL.items)) {
        return { status: 'success', data: cached };
      }

      if (!force && itemsInFlight.value?.[cacheKey]) {
        return await itemsInFlight.value[cacheKey];
      }

      const promise = (async () => {
        const response = await apiClient.get('/endpoint');
        const data = response?.data?.data || [];
        items.value[cacheKey] = data;
        itemsFetchedAt.value[cacheKey] = nowMs();
        return { status: 'success', data };
      })();

      itemsInFlight.value[cacheKey] = promise;
      return await promise;
    } catch (error) {
      return {
        status: 'error',
        message: error.message
      };
    }
  };

  return {
    items: computed(() => items.value),
    fetchItems,
  };
});
```

---

## 📞 ملاحظات مهمة

1. **AlertService** قد يبقى كـ Utility بدلاً من Store (للرسائل الفورية)
2. **استخدام Composables** مثل `useToast` لا بأس به
3. **Services الثقيلة** يجب تحويلها إلى Stores
4. **Caching Strategy** مهم جداً للأداء

---

## 🎯 الخلاصة

المشروع لديه **بنية store جيدة** لكن **التطبيق غير موحد**. 

**التوصية النهائية:**
```
⚠️ يجب توحيد جميع الـ Views لاستخدام Stores بدلاً من Services مباشرة
```

التقدير المتوقع:
- **النسبة الحالية:** 40% موحد ✅ / 60% غير موحد ❌
- **النسبة المستهدفة:** 100% موحد ✅

---

**تاريخ المراجعة:** 2026-04-24
**المراجع:** Smart Sys ERP Frontend
