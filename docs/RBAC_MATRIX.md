# RBAC Permission Matrix — SmartSys ERP

> Generated: Week 1 refactor  
> Source of truth: `api/v1/src/Security/Permissions.php`  
> DB seed: `database/migrations/W1_seed_permissions.sql`

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Granted |
| ❌ | Denied (403) |
| 🔓 | Bypass — super_admin skips all checks at middleware level |
| — | Not applicable |

## Roles

| ID | Name | Description |
|----|------|-------------|
| 1 | super_admin | Platform admin — bypasses all RBAC checks |
| 2 | admin | Full tenant admin |
| 3 | manager | Branch/store manager |
| 4 | cashier | POS operator |
| 5 | inventory_clerk | Warehouse staff |
| 9 | finance_officer | Financial approvals & reporting |

---

## Sales

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| sale.view | 🔓 | ✅ | ✅ | ✅ | ❌ | ✅ |
| sale.create | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| sale.edit | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| sale.void | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| sale.discount | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| sale.payment.create | 🔓 | ✅ | ✅ | ✅ | ❌ | ✅ |
| sales.approval.view | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |
| sales.approval.approve | 🔓 | ✅ | ❌ | ❌ | ❌ | ✅ |
| sales.approval.reject | 🔓 | ✅ | ❌ | ❌ | ❌ | ✅ |

## Purchases

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| purchase.view | 🔓 | ✅ | ✅ | ❌ | ✅ | ✅ |
| purchase.create | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| purchase.edit | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| purchase.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| purchase.payment.create | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |

## Returns

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| return.view | 🔓 | ✅ | ✅ | ✅ | ❌ | ✅ |
| return.create | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| return.approve | 🔓 | ✅ | ❌ | ❌ | ❌ | ✅ |
| return.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## Products & Inventory

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| product.view | 🔓 | ✅ | ✅ | ✅ | ✅ | ❌ |
| product.create | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| product.edit | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| product.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| inventory.view | 🔓 | ✅ | ✅ | ✅ | ✅ | ❌ |
| inventory.adjust | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| inventory.transfer | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |

## Contacts (Customers / Suppliers)

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| customer.view | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| customer.create | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| customer.edit | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| customer.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| supplier.view | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| supplier.create | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| supplier.edit | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| supplier.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## Branches

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| branch.view | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| branch.create | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| branch.edit | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| branch.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## Cash Vouchers

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| voucher.view | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |
| voucher.create | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |
| voucher.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## Reports

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| report.sales | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |
| report.inventory | 🔓 | ✅ | ✅ | ❌ | ✅ | ❌ |
| report.purchases | 🔓 | ✅ | ✅ | ❌ | ✅ | ✅ |
| report.financial | 🔓 | ✅ | ❌ | ❌ | ❌ | ✅ |

## Accounting

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| accounting.view | 🔓 | ✅ | ✅ | ❌ | ❌ | ✅ |
| accounting.period.manage | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| accounting.je.create | 🔓 | ✅ | ❌ | ❌ | ❌ | ✅ |

## POS Sessions

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| pos.session.open | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| pos.session.close | 🔓 | ✅ | ✅ | ✅ | ❌ | ❌ |
| pos.session.manage | 🔓 | ✅ | ✅ | ❌ | ❌ | ❌ |
| pos.terminal.manage | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## Settings

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| settings.view | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| settings.manage | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

## User Management

| Permission | super_admin | admin | manager | cashier | inventory_clerk | finance_officer |
|-----------|:-----------:|:-----:|:-------:|:-------:|:---------------:|:---------------:|
| user.view | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| user.create | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| user.edit | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| user.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| role.view | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| role.create | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| role.edit | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| role.delete | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| permission.view | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |
| permission.assign | 🔓 | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## Customization

Roles and permissions are stored in the DB (`roles`, `permissions`, `role_permissions`).  
Tenant admins can create custom roles and assign any combination of permissions via the RBAC panel (`/rbac/roles`).

To add a new permission:
1. Add constant to `api/v1/src/Security/Permissions.php`
2. Add INSERT to a new migration file in `database/migrations/`
3. Run `php scripts/run_migration.php database/migrations/<file>.sql`
4. Run `php scripts/check_permission_coverage.php` → must return exit 0
5. Add `->add(PermissionMiddleware::require(Permissions::NEW_PERM, $db))` to the route
