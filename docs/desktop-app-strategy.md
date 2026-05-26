# 🖥️ استراتيجية تطبيق سطح المكتب - نظام ERP

## 🎯 الهدف
تحويل نظام ERP الحالي إلى تطبيق سطح مكتب سريع مع دعم التخزين المحلي والعمل بدون اتصال.

## 🏗️ الهيكل المقترح

### 1. **التقنية المختارة: Electron + Vue.js**

```
erp-desktop/
├── main.js                 # Electron main process
├── preload.js             # Bridge بين renderer و main
├── renderer/              # Vue.js app (الكود الحالي)
│   ├── src/
│   ├── public/
│   └── dist/
├── database/              # SQLite local database
│   ├── schema.sql
│   ├── migrations/
│   └── seeds/
├── sync/                  # مزامنة البيانات
│   ├── sync-manager.js
│   ├── conflict-resolver.js
│   └── queue-manager.js
└── build/                 # تطبيقات مبنية
```

### 2. **قاعدة البيانات المحلية**

#### **SQLite Schema:**
```sql
-- نسخة محلية من الجداول الرئيسية
CREATE TABLE products_local (
    id INTEGER PRIMARY KEY,
    server_id INTEGER,
    name TEXT NOT NULL,
    barcode TEXT,
    price DECIMAL(10,2),
    stock_quantity INTEGER,
    last_sync DATETIME,
    sync_status TEXT DEFAULT 'pending' -- pending, synced, conflict
);

CREATE TABLE sales_local (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    server_id INTEGER,
    invoice_number TEXT,
    customer_id INTEGER,
    total_amount DECIMAL(10,2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sync_status TEXT DEFAULT 'pending'
);

-- جدول المزامنة
CREATE TABLE sync_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name TEXT NOT NULL,
    record_id INTEGER NOT NULL,
    action TEXT NOT NULL, -- insert, update, delete
    data TEXT, -- JSON data
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sync_status TEXT DEFAULT 'pending'
);
```

### 3. **نظام المزامنة**

#### **Sync Manager:**
```javascript
class SyncManager {
    constructor() {
        this.db = new SQLiteDB();
        this.api = new APIClient();
        this.conflictResolver = new ConflictResolver();
    }

    // مزامنة البيانات من الخادم
    async syncFromServer() {
        try {
            // 1. جلب التحديثات من الخادم
            const updates = await this.api.getUpdates(this.getLastSyncTime());
            
            // 2. تطبيق التحديثات محلياً
            for (const update of updates) {
                await this.applyUpdate(update);
            }
            
            // 3. تحديث وقت آخر مزامنة
            await this.updateLastSyncTime();
            
        } catch (error) {
            console.error('Sync from server failed:', error);
        }
    }

    // مزامنة البيانات إلى الخادم
    async syncToServer() {
        try {
            // 1. جلب البيانات المعلقة
            const pendingChanges = await this.db.getPendingChanges();
            
            // 2. إرسال التغييرات للخادم
            for (const change of pendingChanges) {
                try {
                    await this.api.sendChange(change);
                    await this.db.markAsSynced(change.id);
                } catch (error) {
                    await this.handleSyncError(change, error);
                }
            }
            
        } catch (error) {
            console.error('Sync to server failed:', error);
        }
    }

    // مزامنة تلقائية كل فترة
    startAutoSync() {
        setInterval(async () => {
            if (navigator.onLine) {
                await this.syncFromServer();
                await this.syncToServer();
            }
        }, 30000); // كل 30 ثانية
    }
}
```

### 4. **تحسين الأداء**

#### **Data Caching:**
```javascript
class DataCache {
    constructor() {
        this.cache = new Map();
        this.maxSize = 1000;
    }

    // تخزين مؤقت للبيانات المستخدمة بكثرة
    async getProducts() {
        if (this.cache.has('products')) {
            return this.cache.get('products');
        }
        
        const products = await this.db.getProducts();
        this.cache.set('products', products);
        return products;
    }

    // تحديث الكاش عند تغيير البيانات
    invalidateCache(key) {
        this.cache.delete(key);
    }
}
```

#### **Lazy Loading:**
```javascript
// تحميل البيانات عند الحاجة فقط
const ProductList = {
    data() {
        return {
            products: [],
            loading: false,
            page: 1,
            hasMore: true
        }
    },
    
    async mounted() {
        await this.loadProducts();
        this.setupInfiniteScroll();
    },
    
    methods: {
        async loadProducts() {
            this.loading = true;
            try {
                const newProducts = await this.$db.getProducts({
                    page: this.page,
                    limit: 50
                });
                
                this.products.push(...newProducts);
                this.hasMore = newProducts.length === 50;
                this.page++;
            } finally {
                this.loading = false;
            }
        }
    }
}
```

### 5. **العمل بدون اتصال (Offline Mode)**

#### **Service Worker للـ PWA:**
```javascript
// sw.js
const CACHE_NAME = 'erp-v1';
const urlsToCache = [
    '/',
    '/css/app.css',
    '/js/app.js',
    '/api/products',
    '/api/categories'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // إرجاع من الكاش إذا موجود
                if (response) {
                    return response;
                }
                
                // محاولة جلب من الشبكة
                return fetch(event.request)
                    .catch(() => {
                        // إرجاع صفحة offline
                        return caches.match('/offline.html');
                    });
            })
    );
});
```

### 6. **تحسينات الأداء المتقدمة**

#### **Virtual Scrolling:**
```vue
<template>
  <div class="virtual-list" :style="{ height: containerHeight + 'px' }">
    <div :style="{ height: totalHeight + 'px' }">
      <div
        v-for="item in visibleItems"
        :key="item.id"
        :style="{ 
          position: 'absolute',
          top: item.top + 'px',
          height: itemHeight + 'px'
        }"
      >
        {{ item.data }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      items: [], // جميع العناصر
      visibleItems: [], // العناصر المرئية فقط
      itemHeight: 50,
      containerHeight: 400,
      scrollTop: 0
    }
  },
  
  computed: {
    totalHeight() {
      return this.items.length * this.itemHeight;
    }
  },
  
  methods: {
    updateVisibleItems() {
      const startIndex = Math.floor(this.scrollTop / this.itemHeight);
      const endIndex = Math.min(
        startIndex + Math.ceil(this.containerHeight / this.itemHeight) + 1,
        this.items.length
      );
      
      this.visibleItems = this.items
        .slice(startIndex, endIndex)
        .map((item, index) => ({
          ...item,
          top: (startIndex + index) * this.itemHeight
        }));
    }
  }
}
</script>
```

### 7. **إعداد Electron**

#### **main.js:**
```javascript
const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const Database = require('better-sqlite3');

let mainWindow;
let db;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js')
        }
    });

    // تحميل التطبيق
    if (process.env.NODE_ENV === 'development') {
        mainWindow.loadURL('http://localhost:8080');
    } else {
        mainWindow.loadFile('dist/index.html');
    }
}

// إعداد قاعدة البيانات
function setupDatabase() {
    db = new Database('erp-local.db');
    
    // إنشاء الجداول
    const schema = require('./database/schema.sql');
    db.exec(schema);
}

// معالجة طلبات قاعدة البيانات
ipcMain.handle('db-query', async (event, query, params) => {
    try {
        const stmt = db.prepare(query);
        return stmt.all(params);
    } catch (error) {
        throw error;
    }
});

app.whenReady().then(() => {
    setupDatabase();
    createWindow();
});
```

#### **preload.js:**
```javascript
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    // قاعدة البيانات
    dbQuery: (query, params) => ipcRenderer.invoke('db-query', query, params),
    
    // ملفات النظام
    readFile: (path) => ipcRenderer.invoke('read-file', path),
    writeFile: (path, data) => ipcRenderer.invoke('write-file', path, data),
    
    // إشعارات النظام
    showNotification: (title, body) => ipcRenderer.invoke('show-notification', title, body)
});
```

### 8. **تحسين Vue.js للأداء**

#### **Composition API مع Caching:**
```javascript
// composables/useProducts.js
import { ref, computed, onMounted } from 'vue';

export function useProducts() {
    const products = ref([]);
    const loading = ref(false);
    const cache = new Map();

    const loadProducts = async (useCache = true) => {
        if (useCache && cache.has('products')) {
            products.value = cache.get('products');
            return;
        }

        loading.value = true;
        try {
            const data = await window.electronAPI.dbQuery(
                'SELECT * FROM products_local WHERE sync_status != "deleted"'
            );
            products.value = data;
            cache.set('products', data);
        } finally {
            loading.value = false;
        }
    };

    const addProduct = async (product) => {
        // إضافة محلياً
        const result = await window.electronAPI.dbQuery(
            'INSERT INTO products_local (name, price, stock_quantity) VALUES (?, ?, ?)',
            [product.name, product.price, product.stock_quantity]
        );

        // إضافة لقائمة المزامنة
        await window.electronAPI.dbQuery(
            'INSERT INTO sync_queue (table_name, record_id, action, data) VALUES (?, ?, ?, ?)',
            ['products', result.lastInsertRowid, 'insert', JSON.stringify(product)]
        );

        // تحديث الكاش
        cache.delete('products');
        await loadProducts();
    };

    return {
        products,
        loading,
        loadProducts,
        addProduct
    };
}
```

## 🚀 خطة التنفيذ

### المرحلة 1: الإعداد الأساسي (أسبوع 1)
- [ ] إعداد Electron
- [ ] إنشاء قاعدة البيانات المحلية
- [ ] نقل الواجهات الأساسية

### المرحلة 2: التخزين المحلي (أسبوع 2)
- [ ] تطبيق نظام الكاش
- [ ] إعداد SQLite
- [ ] تطوير Data Layer

### المرحلة 3: المزامنة (أسبوع 3)
- [ ] نظام المزامنة الأساسي
- [ ] معالجة التعارضات
- [ ] Queue Management

### المرحلة 4: التحسينات (أسبوع 4)
- [ ] Virtual Scrolling
- [ ] Lazy Loading
- [ ] Performance Optimization

### المرحلة 5: الاختبار والنشر (أسبوع 5)
- [ ] اختبار شامل
- [ ] بناء التطبيق
- [ ] إعداد التحديثات التلقائية

## 📊 المزايا المتوقعة

- **سرعة التحميل:** 80% أسرع
- **العمل بدون اتصال:** 100%
- **استجابة الواجهة:** فورية
- **استهلاك البيانات:** 60% أقل
- **تجربة المستخدم:** محسنة بشكل كبير
