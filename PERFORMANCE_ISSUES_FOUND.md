# 🔴 Performance & Data Issues Found - July 12, 2026

## 🔴 CRITICAL Issues

### 1. Duplicate `/analytics/sales` calls with different date formats in `CashierDashboard.vue`
**Impact:** 3 API calls instead of 1 (200-300ms wasted)
**Root Cause:** Mixed date handling - naive local strings vs UTC conversion
**Location:** 
- `fetchDashboardData()` line 620: `start_date: today + ' 00:00:00'` (local)
- `analyticsStore.fetchDashboardSummary()` line 631-637: converts to UTC if no sessionId
- `analyticsStore.fetchSalesAnalytics()` line 63-64: UTC conversion logic

**Example:**
```
GET /analytics/sales {start_date: '2026-07-12 00:00:00', end_date: '2026-07-12 23:59:59'} ← Call 1 & 2
GET /analytics/sales {start_date: '2026-07-11T21:00:00.000Z', end_date: '2026-07-12T20:59:59.999Z'} ← Call 3 (UTC)
```

**Fix:** Standardize to ONE date format across all analytics calls

---

### 2. Debug logs leaking API responses in production
**Impact:** Performance degradation, security risk (exposes data structure)
**Location:** 
- `CashierDashboard.vue:667-669` - full API response logged
- Similar pattern in `StatementView.vue` (previously identified)

**Example:**
```javascript
console.log('=== CashierDashboard DEBUG ===');
console.log('Query params:', queryParams);
console.log('API response:', data); // ← Full response!
```

**Fix:** Remove all debug console.logs before production build

---

### 3. `/sessions/current` called with `branch_id: null`
**Impact:** Possible incorrect session lookup
**Location:** Session store or CashierDashboard initialization
**Example:**
```
GET /sessions/current {branch_id: null, user_id: 61, device_id: '...'}
```
While `/shifts/current` correctly sends `branch_id: 48`

**Fix:** Ensure branch context is passed correctly to session API

---

## 🟡 OPTIMIZATION Opportunities

### 4. `/branches` fetched ~15 times without caching
**Impact:** ~1.5 seconds cumulative wasted on redundant requests
**Current:** No "Using cached branches data" logs
**Comparison:** Sessions, Payments, Purchase Management have visible caching

**Fix:** Implement TTL cache for branches like other stores

---

### 5. Duplicate `/analytics/pos-performance` calls in `admin-dashboard`
**Impact:** 2 API calls instead of 1
**Example:**
```
GET /analytics/pos-performance {start_date: '2026-07-06', end_date: '2026-07-13', branch_id: {...}}
GET /analytics/pos-performance {start_date: '2026-07-05T21:00:00.000Z', end_date: '2026-07-13T20:59:59.999Z'}
```

**Fix:** Same as #1 - standardize date handling

---

### 6. Missing `branch_id` in `/purchases`, `/sales`, `/returns` requests
**Impact:** Unclear if intentional ("all branches") or bug
**Affected endpoints:**
- `GET /purchases {page: '1', per_page: '20'}` (PurchaseHistory)
- `GET /purchases {page: '1', per_page: '50'}` (PurchaseManagement)
- `GET /sales {page: '1', per_page: '20'}` (SalesHistory)
- `GET /returns/sale {page: '1', per_page: '20'}`
- `GET /returns/purchase {page: '1', per_page: '20'}`

**Note:** Other endpoints on same pages correctly send `branch_id: '48'`

**Action Required:** Verify if this is:
- Intentional (showing all branches)
- Repeat of previous spread operator bug that silently drops filters

---

### 7. `/sales` sorted by `created_at ASC` (oldest first)
**Impact:** Poor UX - users expect newest sales first
**Location:** `SalesHistory.vue` default sort
**Example:** `GET /sales?sort=created_at&order=asc`

**Fix:** Change default to `order=desc`

---

## 🟢 MINOR Issues

### 8. `InventoryMovements` re-fetched on navigation without cache
**Impact:** Minor UX delay
**Fix:** Add TTL caching like other modules

---

### 9. Negative inventory balance in report
**Data:** `final_balance: -1` (opening: 0, in: 0, out: 1)
**Impact:** Business logic concern - selling unavailable stock
**Action:** Add validation/warning at business logic level

---

## Summary Stats

- **Critical Issues:** 3
- **Optimization Opportunities:** 6  
- **Minor Issues:** 2

**Estimated Performance Gain if all fixed:** 30-40% faster page loads

---

## Next Steps

1. ✅ Fix debug logs (5 min)
2. ✅ Standardize date handling in CashierDashboard (15 min)
3. ✅ Fix sessions/current branch_id (10 min)
4. Add branches caching (20 min)
5. Review branch_id filtering across all list endpoints (30 min)
6. Add inventory negative balance validation (business decision needed)
