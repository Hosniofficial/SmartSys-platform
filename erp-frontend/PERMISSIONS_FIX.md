# إصلاح مشكلة الصلاحيات - Admin لا يرى جميع القوائم

## 🐛 المشكلة
- المستخدم مسجل كـ **admin** (tenant_id: 47)
- يظهر فقط **4 قوائم** بدلاً من جميع القوائم
- المبيعات، المشتريات، المخزون، وغيرها **لا تظهر**

## 🔍 السبب الجذري
عند تسجيل الدخول عبر `/auth/login` أو تحديث الجلسة عبر `/auth/refresh`، الـ API كان يُرجع:

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "role": "admin",
    "tenant_id": 47,
    // ❌ permissions مفقودة!
  }
}
```

بدون `permissions` array، الكود في `menuConfig.js`:
```javascript
const menuItems = computed(() => {
  return getMenuItems(
    authStore.user?.role || 'employee', 
    authStore.user?.permissions || []  // ← [] empty array!
  )
})
```

كان يُمرّر `permissions: []` (array فارغة)، مما يجعل `hasMenuPermission()` ترفض أي item يحتاج صلاحية محددة.

## ✅ الحل

### 1. Backend: إضافة permissions في AuthHandler.php

#### في `/auth/login`:
```php
// Get user permissions
$permissions = $this->getPermissionsForRole((int) $user['role_id']);

return $this->setRefreshTokenCookie(
    $this->successResponse($response, [
        'access_token' => $accessToken,
        'user' => [
            'id'              => (int) $user['id'],
            'name'            => $user['name'],
            'username'        => $user['username'],
            'role_id'         => (int) $user['role_id'],
            'role'            => $this->mapRoleName((int) $user['role_id']),
            'isAdmin'         => $this->isAdmin((int) $user['role_id']),
            'tenant_id'       => (int) $user['tenant_id'],
            'branch_id'       => isset($user['branch_id']) ? (int) $user['branch_id'] : null,
            'is_owner'        => (int) ($user['is_owner'] ?? 0),
            'is_setup_complete' => (int) ($user['is_setup_complete'] ?? 0),
            'permissions'     => $permissions,  // ✅ Added
        ],
    ], 200),
    $refreshToken
);
```

#### في `/auth/refresh`:
```php
// Get user permissions
$permissions = $this->getPermissionsForRole((int) $user['role_id']);

return $this->setRefreshTokenCookie(
    $this->successResponse($response, [
        'access_token' => $newAccessToken,
        'user' => [
            'id'              => (int) $user['id'],
            'name'            => $user['name'],
            'username'        => $user['username'],
            'role_id'         => (int) $user['role_id'],
            'role'            => $this->mapRoleName((int) $user['role_id']),
            'isAdmin'         => $this->isAdmin((int) $user['role_id']),
            'tenant_id'       => (int) $user['tenant_id'],
            'branch_id'       => isset($user['branch_id']) ? (int) $user['branch_id'] : null,
            'is_owner'        => (int) ($user['is_owner'] ?? 0),
            'permissions'     => $permissions,  // ✅ Added
        ],
    ], 200),
    $newRefreshToken
);
```

الدالة `getPermissionsForRole()` موجودة بالفعل:
```php
private function getPermissionsForRole(int $roleId): array
{
    try {
        $stmt = $this->db->prepare("
            SELECT p.name
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$roleId]);

        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $permissions ?: [];
    } catch (\Throwable $e) {
        $this->logger->error('Failed to fetch permissions for role', [
            'role_id' => $roleId,
            'message' => $e->getMessage()
        ]);
        return [];
    }
}
```

### 2. Frontend: تحديث user عند refresh

في `erp-frontend/src/stores/auth.js`:

```javascript
const _doSilentRefresh = async () => {
  try {
    const response = await apiClient.post('/auth/refresh', {}, {
      meta: { skipLoader: true },
      _isRefreshRequest: true,
    });

    if (response.data?.status === 'success' && response.data?.data?.access_token) {
      const newAccessToken = response.data.data.access_token;
      const userData = response.data.data.user; // ✅ Get updated user data
      
      token.value = newAccessToken;
      apiClient.defaults.headers.common['Authorization'] = `Bearer ${newAccessToken}`;
      
      // ✅ Update user data if provided (includes permissions)
      if (userData) {
        user.value = userData;
        localStorage.setItem('user', JSON.stringify(userData));
      }
      
      return newAccessToken;
    }
    return null;
  } catch (err) {
    // ... error handling
  }
};
```

## 📊 كيف يعمل الآن

### Flow الكامل:

1. **Login** (`/auth/login`):
   ```
   User credentials → Backend
   ↓
   Validate user
   ↓
   Get permissions from role_permissions table
   ↓
   Return: { access_token, user: { ..., permissions: [...] } }
   ```

2. **Frontend استقبال البيانات**:
   ```javascript
   authStore.setAuthData(userData, accessToken)
   ↓
   user.value = { ..., permissions: ['sale.view', 'product.view', ...] }
   ↓
   localStorage.setItem('user', JSON.stringify(userData))
   ```

3. **Menu Rendering**:
   ```javascript
   const menuItems = computed(() => {
     return getMenuItems(
       authStore.user?.role || 'employee',        // 'admin'
       authStore.user?.permissions || []          // ['sale.view', 'product.view', ...]
     )
   })
   ```

4. **hasMenuPermission() يتحقق**:
   ```javascript
   // للـ item: { name: 'المبيعات', permission: 'sale.view' }
   hasMenuPermission(item, 'admin', ['sale.view', 'product.view', ...])
   ↓
   requiredPermissions = ['sale.view']
   permissionNames.includes('sale.view') // ✅ true
   ↓
   Item يظهر في القائمة!
   ```

## 🎯 النتيجة المتوقعة

بعد الإصلاح، المستخدم الـ admin سيرى:

### إذا كان admin مع صلاحيات كاملة:
- ✅ لوحة التحكم
- ✅ المبيعات (مع جميع sub-items حسب الصلاحيات)
- ✅ المرتجعات
- ✅ المشتريات
- ✅ المخزون
- ✅ العملاء والموردين
- ✅ المالية
- ✅ الضمان
- ✅ التقارير (مع sub-items حسب الصلاحيات)
- ✅ الإعدادات
- ✅ النظام والتدقيق
- ❌ الاشتراكات (superAdmin only)

### إذا كان admin بصلاحيات محدودة:
- سيرى فقط الأقسام والعناصر التي يملك صلاحياتها
- مثال: إذا كان يملك `['sale.view', 'product.view']`
  - ✅ المبيعات > سجل المبيعات
  - ✅ المخزون > إدارة المنتجات
  - ❌ المشتريات (لا يملك purchase.view)

## 🔧 الملفات المعدلة

1. ✅ `api/v1/src/Handlers/AuthHandler.php`
   - إضافة `permissions` في `/auth/login` response
   - إضافة `permissions` في `/auth/refresh` response

2. ✅ `erp-frontend/src/stores/auth.js`
   - تحديث `_doSilentRefresh()` لحفظ user data من refresh

## ✅ اختبار الإصلاح

### خطوات الاختبار:
1. سجّل خروج من الحساب الحالي
2. امسح localStorage و cookies:
   ```javascript
   localStorage.clear()
   document.cookie.split(";").forEach(c => {
     document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
   });
   ```
3. سجّل دخول مرة أخرى
4. افتح Console واكتب:
   ```javascript
   JSON.parse(localStorage.getItem('user'))
   ```
5. يجب أن ترى:
   ```json
   {
     "id": 1,
     "name": "Admin",
     "role": "admin",
     "permissions": [
       "sale.view",
       "sale.create",
       "product.view",
       "product.create",
       ...
     ]
   }
   ```

### التحقق من القوائم:
- افتح الصفحة الرئيسية
- يجب أن تظهر جميع القوائم التي لديك صلاحيات لها
- افتح Console وجرّب:
  ```javascript
  import { useAuthStore } from '@/stores/auth'
  const authStore = useAuthStore()
  console.log('User permissions:', authStore.user?.permissions)
  ```

## 📝 ملاحظات إضافية

### لماذا لم تظهر المشكلة قبل؟
- الكود القديم كان يعتمد فقط على `role` (admin/manager/user)
- الكود الجديد (menuConfig) يتحقق من `permissions` المحددة
- بدون `permissions` array، كل الـ items التي تتطلب صلاحيات مُحددة كانت تُرفض

### superAdmin Exception:
```javascript
if (normalizedRole === 'superAdmin') return true
```
- superAdmin يتجاوز جميع فحوصات الصلاحيات
- لا يحتاج إلى permissions array

### Role Hierarchy:
```javascript
const FULL_ADMIN_ROLES = ['superAdmin', 'admin']
const MANAGER_ROLES = ['manager']
```
- `access: 'user'` → الكل يمكنه الوصول (مع الصلاحيات المطلوبة)
- `access: 'manager'` → manager + admin + superAdmin
- `access: 'admin'` → admin + superAdmin فقط
- `access: 'superAdmin'` → superAdmin فقط

## ✅ الخلاصة

**المشكلة**: API لا يُرجع `permissions` في user object  
**الحل**: إضافة `permissions` في `/auth/login` و `/auth/refresh`  
**النتيجة**: القوائم تظهر بناءً على الصلاحيات الفعلية للمستخدم  

**الإصلاح مكتمل!** 🎉
