# Deployment Guide — SmartSys ERP

## Pre-deployment Checklist

Run this checklist before every production deployment.

### 1. Environment Variables

```bash
# Verify all required vars are set (non-empty)
grep -E "^(JWT_SECRET|JWT_REFRESH_SECRET|DB_PASSWORD|CRON_SECRET)=" .env.production
```

Required values:
- `APP_ENV=production`
- `APP_DEBUG=false`
- `HTTPS_ENFORCEMENT_ENABLED=true`
- `JWT_SECRET` — 64 hex chars (generate: `php scripts/generate_secrets.php`)
- `JWT_REFRESH_SECRET` — 64 hex chars, different from JWT_SECRET
- `CRON_SECRET` — 64 hex chars
- `DB_PASSWORD` — strong password, not empty
- `CORS_ALLOWED_ORIGINS` — exact production domain(s), no wildcards

### 2. Database Migrations

Run all pending migrations in order:

```bash
php scripts/run_migration.php database/migrations/W1_seed_permissions.sql
php scripts/run_migration.php database/migrations/W2_integrity_fixes.sql
php scripts/run_migration.php database/migrations/W4_add_indexes.sql
```

Verify indexes:
```bash
php scripts/analyze_indexes.php   # must exit 0
```

Verify permissions:
```bash
php scripts/check_permission_coverage.php   # must exit 0
```

### 3. Frontend Build

```bash
cd erp-frontend
npm ci                  # use lockfile — never npm install in production
npm run build
```

Expected output (approximate, before gzip):
- `vendor-core.js`   ~  80 KB
- `vendor-ui.js`     ~ 200 KB
- `vendor-charts.js` ~ 200 KB
- `vendor-excel.js`  ~ 400 KB  (lazy — only loaded on export pages)
- `vendor-print.js`  ~  50 KB  (lazy — only loaded on POS)
- `index.js`         ~  50 KB  (app bootstrap)

### 4. Smoke Tests (post-deploy)

```bash
# Liveness
curl -s https://yourdomain.com/check | jq .status

# Full health
curl -s https://yourdomain.com/health | jq .

# Login
curl -s -X POST https://yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"..."}' | jq .status

# Verify refresh_token is in cookie (not body)
curl -sv -X POST https://yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"..."}' 2>&1 | grep -i "set-cookie"
# Expected: Set-Cookie: refresh_token=...; HttpOnly; Secure; SameSite=Strict
```

### 5. Cron Jobs

Add to server crontab (`crontab -e`):

```cron
# Daily cleanup (JWT blacklist, expired tokens, old logs)
0 3 * * * php /var/www/smartsys/api/v1/crons/cleanup_cron.php >> /var/log/smartsys-cleanup.log 2>&1

# Subscription expiry alerts (existing)
0 8 * * * php /var/www/smartsys/api/v1/crons/daily_expiry_alerts.php >> /var/log/smartsys-cron.log 2>&1

# Subscription alignment (existing)
0 2 * * * php /var/www/smartsys/api/v1/crons/align_subscriptions_cron.php >> /var/log/smartsys-cron.log 2>&1
```

### 6. Web Server Configuration

#### Nginx (recommended)

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    root /var/www/smartsys/erp-frontend/dist;
    index index.html;

    # Frontend SPA — serve index.html for all non-asset routes
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API — proxy to PHP
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Static assets — long cache
    location ~* \.(js|css|woff2|png|jpg|svg|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security headers (supplement SecurityHeadersMiddleware)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

#### Apache (.htaccess — already present)

The existing `.htaccess` handles SPA routing. Ensure `mod_rewrite` is enabled.

---

## Rollback Plan

1. Keep the previous `dist/` build in `dist.backup/`
2. Database migrations are additive (indexes, new tables) — safe to leave in place
3. If a migration must be reversed, a rollback script is in `database/migrations/rollbacks/`

---

## Monitoring

- **Health endpoint**: `GET /health` — returns 200/503
- **Liveness**: `GET /check` — returns 200 always (process alive)
- **Logs**: `logs/` directory — one file per channel (auth, sales, accounting, security)
- **Cleanup cron**: runs at 03:00 — check `/var/log/smartsys-cleanup.log`

Recommended monitoring tools:
- UptimeRobot (free) — ping `/health` every 5 minutes
- Sentry (configured via `SENTRY_DSN` in `.env`) — error tracking
