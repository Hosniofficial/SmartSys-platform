# SmartSys ERP Platform

<div align="center">
  
![SmartSys Logo](https://img.shields.io/badge/SmartSys-ERP%20Platform-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.0-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)

**نظام إدارة موارد المؤسسات الشامل (ERP)**

[التوثيق](#documentation) •
[التثبيت](#installation) •
[المزايا](#features) •
[التقنيات](#technologies) •
[المساهمة](#contributing)

</div>

---

## 📋 نظرة عامة

SmartSys هو نظام ERP متكامل مصمم لإدارة جميع جوانب العمليات التجارية للشركات الصغيرة والمتوسطة. يوفر النظام إدارة شاملة للمخزون، المبيعات، المشتريات، المحاسبة، وإدارة العلاقات مع العملاء.

### ✨ المزايا الرئيسية

- 🏪 **إدارة نقاط البيع (POS)** - نظام متطور لنقاط البيع مع دعم الجلسات والورديات
- 📦 **إدارة المخزون** - تتبع شامل للمنتجات، الباركود، والتصنيفات
- 💰 **المحاسبة المالية** - نظام محاسبي متكامل مع دعم القيود اليومية
- 📊 **التقارير والتحليلات** - تقارير مفصلة ولوحات تحكم تفاعلية
- 👥 **إدارة العملاء والموردين** - CRM متكامل
- 🔐 **نظام صلاحيات متقدم** - تحكم دقيق في صلاحيات المستخدمين
- 🌐 **Multi-Tenant** - دعم متعدد المستأجرين (SaaS)
- ⚡ **Bootstrap API** - تحسين أداء بنسبة 50-75%

---

## 🏗️ البنية المعمارية

```
SmartSys ERP
│
├── Backend (PHP/Slim Framework)
│   ├── REST API
│   ├── JWT Authentication
│   ├── Multi-tenant Architecture
│   └── MySQL Database
│
└── Frontend (Vue.js 3)
    ├── Pinia State Management
    ├── Vue Router
    ├── Tailwind CSS
    └── Responsive Design
```

---

## 🚀 البدء السريع

### المتطلبات الأساسية

- PHP >= 8.0
- MySQL >= 8.0
- Composer >= 2.0
- Node.js >= 16.0
- npm >= 8.0

### التثبيت

#### 1. استنساخ المشروع

```bash
git clone https://github.com/Hosniofficial/SmartSys-platform.git
cd SmartSys-platform
```

#### 2. إعداد Backend

```bash
# تثبيت Dependencies
composer install

# نسخ ملف البيئة
cp .env.example .env

# تعديل إعدادات قاعدة البيانات في .env
# DB_HOST=localhost
# DB_NAME=smartsys
# DB_USER=root
# DB_PASS=

# إنشاء قاعدة البيانات
mysql -u root -p < database/schema.sql

# تشغيل الـ Backend
php -S localhost:8000 -t public
```

#### 3. إعداد Frontend

```bash
cd erp-frontend

# تثبيت Dependencies
npm install

# نسخ ملف البيئة
cp .env.example .env

# تعديل VITE_API_URL في .env
# VITE_API_URL=http://localhost:8000/api/v1

# تشغيل الـ Frontend
npm run dev
```

#### 4. الوصول للنظام

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api/v1
- **مستخدم افتراضي**: admin / admin123

---

## 📁 هيكل المشروع

```
SmartSys-platform/
│
├── api/                          # Backend API
│   └── v1/
│       ├── handlers/            # Request Handlers
│       ├── middleware/          # Middleware (Auth, CORS, etc.)
│       ├── src/                 # Core Business Logic
│       │   ├── Services/       # Business Services
│       │   ├── Repositories/   # Data Access Layer
│       │   └── Exceptions/     # Custom Exceptions
│       └── logs/               # Application Logs
│
├── app/                         # Application Core
│   └── routes/                 # API Routes
│
├── config/                      # Configuration Files
│   ├── container.php           # DI Container
│   ├── database.php            # Database Config
│   └── routes.php              # Routes Registration
│
├── database/                    # Database Files
│   ├── migrations/             # Database Migrations
│   └── seeds/                  # Seed Data
│
├── erp-frontend/               # Frontend Application
│   ├── src/
│   │   ├── assets/            # Static Assets
│   │   ├── components/        # Vue Components
│   │   ├── composables/       # Composition API
│   │   ├── stores/            # Pinia Stores
│   │   ├── views/             # Page Views
│   │   ├── router/            # Vue Router
│   │   └── config/            # Frontend Config
│   │
│   ├── public/                # Public Assets
│   ├── index.html            # Entry HTML
│   ├── package.json          # NPM Dependencies
│   └── vite.config.js        # Vite Configuration
│
├── public/                     # Web Root (Backend)
│   └── index.php              # Entry Point
│
├── scripts/                    # Utility Scripts
├── vendor/                     # Composer Dependencies
│
├── .env.example               # Environment Template
├── .gitignore                # Git Ignore Rules
├── composer.json             # PHP Dependencies
├── README.md                 # This File
└── LICENSE                   # License File
```

---

## 🛠️ التقنيات المستخدمة

### Backend
- **Framework**: Slim Framework 4
- **Database**: MySQL 8.0
- **Authentication**: JWT (JSON Web Tokens)
- **API**: RESTful API
- **Architecture**: Multi-tier, Multi-tenant
- **Caching**: APCu (Optional)

### Frontend
- **Framework**: Vue.js 3 (Composition API)
- **State Management**: Pinia
- **Routing**: Vue Router 4
- **Styling**: Tailwind CSS 3
- **Build Tool**: Vite
- **HTTP Client**: Axios
- **Icons**: Font Awesome

### DevOps
- **Web Server**: Apache/Nginx
- **Version Control**: Git
- **Package Managers**: Composer, npm

---

## 📊 Bootstrap API Optimization

تم تطبيق **Bootstrap Aggregation Pattern** لتحسين أداء تحميل الصفحات:

### النتائج:
- ⚡ **تحسين 50-75%** في زمن التحميل
- 🔄 **تقليل 80%** في عدد HTTP Requests
- 💾 **Caching ذكي** (5 دقائق TTL)
- 🛡️ **Fallback تلقائي** عند الفشل

### الصفحات المحسّنة:
1. ✅ نقطة البيع (POS)
2. ✅ إدارة المشتريات
3. ✅ سجل المدفوعات
4. ✅ إدارة الجلسات
5. ✅ الصفحات التاريخية

**للمزيد من التفاصيل**: راجع `BOOTSTRAP_FINAL_DELIVERY.md`

---

## 📖 التوثيق

### وثائق التطوير
- [Bootstrap API Documentation](BOOTSTRAP_FINAL_DELIVERY.md) - توثيق تقني شامل
- [Bootstrap Quick Guide](BOOTSTRAP_README.md) - دليل سريع
- [Delivery Summary](DELIVERY_SUMMARY.md) - ملخص التسليم
- [Final Checklist](FINAL_CHECKLIST.md) - Checklist كامل

### API Endpoints
قاعدة URL: `http://localhost:8000/api/v1`

#### Bootstrap Endpoints
- `GET /bootstrap/pos` - بيانات نقطة البيع
- `GET /bootstrap/payments` - بيانات المدفوعات
- `GET /bootstrap/sessions` - بيانات الجلسات
- `GET /bootstrap/management/{type}` - بيانات الإدارة

#### Core Endpoints
- `POST /auth/login` - تسجيل الدخول
- `POST /auth/refresh` - تحديث الـ Token
- `GET /sales` - المبيعات
- `GET /purchases` - المشتريات
- `GET /products` - المنتجات
- `GET /customers` - العملاء
- `GET /suppliers` - الموردين

**توثيق API كامل**: قريباً

---

## 🔒 الأمان

- ✅ JWT Authentication
- ✅ Password Hashing (bcrypt)
- ✅ SQL Injection Prevention (Prepared Statements)
- ✅ CORS Protection
- ✅ Rate Limiting
- ✅ Input Validation
- ✅ HTTPS Support
- ✅ Tenant Isolation

---

## 🧪 الاختبار

```bash
# Backend Tests
composer test

# Frontend Tests
cd erp-frontend
npm run test

# E2E Tests
npm run test:e2e
```

---

## 🚢 النشر

### Production Checklist
- [ ] تعديل `.env` للإنتاج
- [ ] تفعيل HTTPS
- [ ] ضبط permissions الملفات
- [ ] تفعيل Caching
- [ ] ضبط Error Logging
- [ ] Backup استراتيجية
- [ ] تفعيل Rate Limiting

### Docker (قريباً)
```bash
docker-compose up -d
```

---

## 👥 الفريق

- **المطور الرئيسي**: [Hosni Official](https://github.com/Hosniofficial)
- **AI Assistant**: Kiro AI

---

## 📝 الترخيص

هذا المشروع محمي بحقوق الملكية. جميع الحقوق محفوظة © 2026 SmartSys ERP.

---

## 🤝 المساهمة

نرحب بالمساهمات! يرجى:
1. Fork المشروع
2. إنشاء Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit التغييرات (`git commit -m 'Add some AmazingFeature'`)
4. Push إلى البranch (`git push origin feature/AmazingFeature`)
5. فتح Pull Request

---

## 📞 الدعم

للدعم الفني أو الاستفسارات:
- **Issues**: [GitHub Issues](https://github.com/Hosniofficial/SmartSys-platform/issues)
- **Email**: support@smartsys.com (قريباً)

---

## 🗺️ Roadmap

### الإصدار القادم (v1.1.0)
- [ ] Reference Data Store مركزي
- [ ] Server-side Caching (Redis)
- [ ] Real-time Notifications
- [ ] Mobile App (React Native)
- [ ] Advanced Analytics Dashboard
- [ ] Multi-language Support
- [ ] Dark Mode

### المستقبل (v2.0.0)
- [ ] Microservices Architecture
- [ ] GraphQL API
- [ ] AI-powered Insights
- [ ] Blockchain Integration
- [ ] IoT Support

---

## ⭐ Star History

إذا أعجبك المشروع، لا تنسى إضافة ⭐️!

---

<div align="center">

**صُنع بـ ❤️ في مصر**

[⬆ العودة للأعلى](#smartsys-erp-platform)

</div>
