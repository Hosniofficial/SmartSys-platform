# SmartSys Fix Verification Test Suite

Dynamic (executed, not static) tests proving the audit fixes work correctly
against a real MySQL/MariaDB instance running the actual, unmodified fixed
source files from `/home/claude/smartsys/api/v1`.

## How to run

1. Have a MySQL/MariaDB server available.
2. Create a database and load the schema + seed data:
   ```
   mysql -u root -e "CREATE DATABASE smarttest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -e "CREATE USER IF NOT EXISTS 'testuser'@'localhost' IDENTIFIED BY 'testpass'; GRANT ALL ON smarttest.* TO 'testuser'@'localhost'; FLUSH PRIVILEGES;"
   mysql -u root smarttest < schema.sql
   mysql -u root smarttest < seed.sql
   ```
3. Edit `autoload.php` to point `SMARTSYS_SRC` / `SMARTSYS_HANDLERS` at your actual
   checkout of the SmartSys repo (currently hardcoded to this sandbox's path).
4. Run each test file: `php test_status_and_payments.php`, `php test_stock_transfer.php`,
   `php test_cogs_reversal.php`. Each prints PASS/FAIL per assertion and exits
   non-zero on any failure (CI-friendly).

## What's covered

- **Group A–C** (`test_status_and_payments.php`): the full sales-status vocabulary
  (`settled_by_credit`, `closed_by_return`, `returned`, `settled_mixed`, refund
  netting), the duplicate-payment retry guard, and the distinct-Idempotency-Key
  protection.
- **Group D** (`test_stock_transfer.php`): reproduces the original MySQL
  execution-order bug in stock transfers numerically (250 vs the correct 200),
  then proves the fix, then proves tenant-wide inventory value is conserved.
- **Group E** (`test_cogs_reversal.php`): reproduces the exact audit RETURN-001
  example — proves the old WAC-at-return-date logic leaked $13.33, and the
  fixed WAC-at-sale-date logic nets to exactly zero.

## What's NOT covered here (still needs testing)

Fix #2/#3 (HTTP-layer Idempotency-Key — needs PSR-7 request mocking), Fix #5
(dead report filter), Fix #6 (return race-condition — needs true concurrent
connections, not just sequential calls), Fix #8 (stock-adjustment rollback
behavior), and the report-vocabulary rollout in AdvancedReportsHandler /
PosAnalyticsHandler.

## MonologHandler stub

`stubs/Services/MonologHandler.php` replaces the real MonologHandler (which
requires monolog/monolog, unreachable via composer in a network-restricted
sandbox) with a dependency-free stub sharing the same public interface. It is
the ONLY substitution — every other class under test is the real, unmodified
production file.
