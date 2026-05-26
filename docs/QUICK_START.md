# 🚀 Quick Start Guide - SmartSys ERP

## System Requirements ✅
- **PHP**: 8.4.7 (XAMPP)
- **Node.js**: v24.4.0
- **npm**: 11.4.2
- **MySQL**: XAMPP built-in

## Architecture Overview

```
Frontend (Vue.js 3)          Backend (Slim 4 API)
Port 5173 (Vite)     <-->    Port 8000 (PHP)
http://localhost:5173         http://localhost:8000
    ↓ talks to ↓
API_BASE = 'http://localhost:8000'
```

## 🎯 Quick Start (5 minutes)

### Step 1: Install Frontend Dependencies
```bash
cd c:\xampp\htdocs\smartsys\erp-frontend
npm install
```
⏳ **Time**: 2-3 minutes (one time only)

---

### Step 2: Start Backend API (Terminal 1)
```bash
cd c:\xampp\htdocs\smartsys
php -S localhost:8000 -t public/
```
✅ **Success**: `Listening on http://localhost:8000`

---

### Step 3: Start Frontend Dev Server (Terminal 2)
```bash
cd c:\xampp\htdocs\smartsys\erp-frontend
npm run dev
```
✅ **Success**: `Network: http://localhost:5173/`

---

### Step 4: Open in Browser
```
🌐 http://localhost:5173/
```

---

## 📊 File Structure

### Backend Entry Points
```
public/index.php (NEW - ACTIVE)
├── config/bootstrap.php
├── config/database.php
├── config/container.php (DI Container)
├── config/routes.php (loads 38 route files)
├── config/middleware.php
└── Slim 4 App starts here

Old (Deprecated):
api/v1/index.php → Can be deleted after 1-2 weeks
```

### Frontend Structure  
```
erp-frontend/
├── src/
│   ├── config.js (API_BASE = 'http://localhost:8000')
│   ├── main.js (Vue entry point)
│   ├── components/ (Vue components)
│   ├── composables/ (Vue composables)
│   ├── router/ (Vue Router)
│   └── views/ (Vue pages)
├── package.json
├── vite.config.js
└── tailwind.config.js
```

---

## 🐛 Troubleshooting

### Frontend shows 404 / Can't find API
**Debug**:
```bash
# Check backend is running
curl http://localhost:8000/check

# Verify config
cat erp-frontend/src/config.js
```
**Fix**: Ensure API_BASE points to `http://localhost:8000`

### "Port 8000 already in use"
```bash
# Find what's using port 8000
netstat -ano | findstr :8000

# Use a different port
php -S localhost:8001 -t public/
```

### npm install fails
```bash
npm cache clean --force
npm install
```

### Database connection error
1. Check MySQL is running in XAMPP Control Panel
2. Verify `config/database.php` settings
3. Run: `php scripts/production-check.php`

### Routes not loading
1. Check `config/routes.php` has 37+ require statements
2. Verify all files in `app/routes/` are present
3. Check PHP error logs: `logs/app.log`

---

## ✅ Verification Checklist

- [ ] Backend running on http://localhost:8000
- [ ] Frontend running on http://localhost:5173
- [ ] Browser shows app without 500 errors
- [ ] Can see API responses in browser DevTools Network tab
- [ ] MySQL connection working
- [ ] All route files present in `app/routes/`
- [ ] No errors in `logs/error.log`

---

## 📚 Important Files Reference

| File | Purpose |
|------|---------|
| `app/routes/*.php` | 38 route files (modular) |
| `config/routes.php` | Loads all route files |
| `config/container.php` | Dependency injection setup |
| `erp-frontend/src/config.js` | Frontend API configuration |
| `MIGRATION_GUIDE.md` | Complete migration details |
| `CLEANUP_GUIDE.md` | Safe deletion procedures |

---

## 🚀 Production Deployment

### Apache Setup
```
Use .htaccess (already configured)
- Rewrite all requests through public/index.php
- Already in place at project root
```

### Nginx Setup
```
Use nginx.conf.example as reference configuration
- Located at project root
- Custom server configuration needed
```

### Environment Setup
1. Copy `.env.example` to `.env`
2. Update database credentials
3. Update frontend API_BASE to production URL
4. Build frontend: `npm run build`
5. Deploy `public/` directory

---

## 📞 Support

**Need help?** Check these files first:
1. `MIGRATION_GUIDE.md` - Complete technical reference
2. `CLEANUP_GUIDE.md` - Safe deletion procedures  
3. `logs/error.log` - Application errors
4. `logs/app.log` - Application logs

---

**Last Updated**: April 10, 2026  
**Status**: ✅ Production Ready
