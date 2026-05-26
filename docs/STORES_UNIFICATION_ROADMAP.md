# خطة التوحيد - Implementation Roadmap

## 🎯 الهدف
توحيد جميع الـ Views لاستخدام Pinia Stores بدلاً من استدعاء Services مباشرة.

---

## 📋 الخطوات المفصلة

### Phase 1: إنشاء الـ Stores المفقودة (الأسبوع الأول)

#### 1. Warranty Store
```javascript
// src/stores/warranty/warrantyStore.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/config/axios';

export const useWarrantyStore = defineStore('warranty', () => {
  const items = ref([]);
  const details = ref(null);
  const isLoading = ref(false);
  const error = ref(null);

  const fetchWarranties = async (params = {}) => {
    isLoading.value = true;
    try {
      const response = await apiClient.get('/warranty', { params });
      items.value = response?.data?.data || [];
      return { status: 'success', data: items.value };
    } catch (err) {
      error.value = err.message;
      return { status: 'error', message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  const fetchWarrantyDetails = async (id) => {
    isLoading.value = true;
    try {
      const response = await apiClient.get(`/warranty/${id}`);
      details.value = response?.data?.data;
      return { status: 'success', data: details.value };
    } catch (err) {
      error.value = err.message;
      return { status: 'error', message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  const createWarranty = async (payload) => {
    try {
      const response = await apiClient.post('/warranty', payload);
      return { status: 'success', data: response?.data?.data };
    } catch (err) {
      return { status: 'error', message: err.message };
    }
  };

  const updateWarrantyStatus = async (id, status, note = '') => {
    try {
      const response = await apiClient.put(`/warranty/${id}/status`, { status, note });
      details.value = response?.data?.data;
      return { status: 'success', data: details.value };
    } catch (err) {
      return { status: 'error', message: err.message };
    }
  };

  const addNote = async (id, content, isInternal = false) => {
    try {
      const response = await apiClient.post(`/warranty/${id}/notes`, { content, is_internal: isInternal });
      return { status: 'success', data: response?.data?.data };
    } catch (err) {
      return { status: 'error', message: err.message };
    }
  };

  return {
    items,
    details,
    isLoading,
    error,
    fetchWarranties,
    fetchWarrantyDetails,
    createWarranty,
    updateWarrantyStatus,
    addNote,
  };
});
```

#### 2. Session Store
```javascript
// src/stores/session/sessionStore.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import apiClient from '@/config/axios';

export const useSessionStore = defineStore('session', () => {
  const currentSession = ref(null);
  const sessions = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  const fetchSessions = async (params = {}) => {
    isLoading.value = true;
    try {
      const response = await apiClient.get('/sessions', { params });
      sessions.value = response?.data?.data || [];
      return { status: 'success', data: sessions.value };
    } catch (err) {
      error.value = err.message;
      return { status: 'error', message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  const getCurrentSession = async (id) => {
    try {
      const response = await apiClient.get(`/sessions/${id}`);
      currentSession.value = response?.data?.data;
      return { status: 'success', data: currentSession.value };
    } catch (err) {
      return { status: 'error', message: err.message };
    }
  };

  const getSessionSummary = async (id) => {
    try {
      const response = await apiClient.get(`/sessions/${id}/summary`);
      return { status: 'success', data: response?.data?.data };
    } catch (err) {
      return { status: 'error', message: err.message };
    }
  };

  return {
    currentSession,
    sessions,
    isLoading,
    error,
    fetchSessions,
    getCurrentSession,
    getSessionSummary,
  };
});
```

#### 3. Shift Store
```javascript
// src/stores/shift/shiftStore.js
(نفس النمط - omitted للاختصار)
```

#### 4. Terminal Store
```javascript
// src/stores/terminal/terminalStore.js
(نفس النمط - omitted للاختصار)
```

#### 5. Payment Store
```javascript
// src/stores/payment/paymentStore.js
(نفس النمط - omitted للاختصار)
```

---

### Phase 2: تحديث الـ Views (الأسبوع الأول والثاني)

#### 1. تحديث WarrantyManagement.vue

**قبل:**
```javascript
import { WarrantyService } from '@/services/warranty';

// في الـ component
async function fetchList() {
  isLoading.value = true;
  try {
    const res = await WarrantyService.list(params);
    items.value = res?.data || [];
  } catch (e) {
    error.value = 'فشل تحميل طلبات الضمان';
  } finally {
    isLoading.value = false;
  }
}
```

**بعد:**
```javascript
import { useWarrantyStore } from '@/stores/warranty/warrantyStore';

const warrantyStore = useWarrantyStore();
const items = computed(() => warrantyStore.items);
const isLoading = computed(() => warrantyStore.isLoading);
const error = computed(() => warrantyStore.error);

// في الـ component
async function fetchList() {
  const result = await warrantyStore.fetchWarranties({
    search: search.value,
    status: statusFilter.value,
    priority: priorityFilter.value,
  });
  if (result.status !== 'success') {
    // handle error
  }
}
```

#### 2. تحديث CashierDashboard.vue

**قبل:**
```javascript
import SessionsService from '@/services/sessions';
import shiftsService from '@/services/shifts';
import terminalsService from '@/services/terminals';

const summary = await SessionsService.getSummary(activeSessionId.value);
```

**بعد:**
```javascript
import { useSessionStore } from '@/stores/session/sessionStore';
import { useShiftStore } from '@/stores/shift/shiftStore';
import { useTerminalStore } from '@/stores/terminal/terminalStore';

const sessionStore = useSessionStore();
const shiftStore = useShiftStore();
const terminalStore = useTerminalStore();

const summary = await sessionStore.getSessionSummary(activeSessionId.value);
```

#### 3. تحديث BulkDistribution.vue

```javascript
// قبل
import { BranchesService } from '@/services/branches';
const list = await BranchesService.getAll();

// بعد
import { useBranchStore } from '@/stores/branch';
const branchStore = useBranchStore();
const list = await branchStore.fetchBranches();
```

---

### Phase 3: التحسينات الإضافية (الأسبوع الثالث)

#### 1. إضافة Error Handling Centralized
```javascript
// stores/ui/uiStore.js - إضافة toast notifications
const showToast = (message, type = 'info') => {
  // implementation
};
```

#### 2. إضافة Loading States
```javascript
// يجب أن يكون في كل store
const isLoading = ref(false);
const error = ref(null);
```

#### 3. إضافة Caching
```javascript
// طبق Caching Strategy:
const TTL = {
  warranties: 5 * 60 * 1000,  // 5 دقائق
  sessions: 1 * 60 * 1000,    // دقيقة واحدة
  branches: 10 * 60 * 1000,   // 10 دقائق
};
```

---

## 📊 جدول المتابعة

| المهمة | الملف | الحالة | الأسبوع |
|------|------|--------|---------|
| إنشاء warrantyStore.js | src/stores/warranty/ | ⏳ | 1 |
| إنشاء sessionStore.js | src/stores/session/ | ⏳ | 1 |
| إنشاء shiftStore.js | src/stores/shift/ | ⏳ | 1 |
| إنشاء terminalStore.js | src/stores/terminal/ | ⏳ | 1 |
| تحديث WarrantyManagement.vue | views/warranty/ | ⏳ | 2 |
| تحديث CashierDashboard.vue | views/ | ⏳ | 2 |
| تحديث BulkDistribution.vue | views/branches/ | ⏳ | 2 |
| توحيد CustomerStore | views/contacts/ | ⏳ | 2 |
| توحيد SupplierStore | views/contacts/ | ⏳ | 3 |
| Unit Tests | tests/stores/ | ⏳ | 3 |

---

## ✅ معايير الاختبار

بعد كل تحديث، يجب التأكد من:

1. **Functional Tests:**
   - ✅ تحميل البيانات بشكل صحيح
   - ✅ معالجة الأخطاء بشكل صحيح
   - ✅ الـ Caching يعمل (عدم تكرار API calls)
   - ✅ الـ Loading states صحيحة

2. **Code Quality:**
   - ✅ لا وجود استدعاءات Service مباشرة في Views
   - ✅ استخدام Computed Properties من Store
   - ✅ تعليقات واضحة

3. **Performance:**
   - ✅ لا API calls غير ضرورية
   - ✅ Caching يقلل من الـ requests
   - ✅ Memory usage معقول

---

## 🔍 Validation Checklist

```
قبل الاستقرار على أي تحديث:
- [ ] جميع الاستدعاءات عبر Store
- [ ] لا يوجد استدعاء مباشر للـ API Service
- [ ] الـ Error handling كامل
- [ ] الـ Loading states موجودة
- [ ] Caching يعمل
- [ ] المكونات تستخدم Computed Properties
- [ ] Tests passed
- [ ] Browser DevTools تظهر الـ Store الصحيح
```

---

## 📚 Resources

- [Pinia Documentation](https://pinia.vuejs.org/)
- [Vue 3 Composition API](https://vuejs.org/guide/extras/composition-api-faq.html)
- [State Management Best Practices](https://vuejs.org/guide/scaling-up/state-management.html)

---

**ملاحظة:** هذه الخطة قابلة للتعديل حسب احتياجات الفريق والأولويات.

تاريخ الإنشاء: 2026-04-24
