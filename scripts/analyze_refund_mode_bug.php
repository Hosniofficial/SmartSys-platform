<?php
/**
 * Analysis: auto vs credit_note refund mode bug
 * 
 * Problem: When creating a return for a cash invoice with refund_mode='auto',
 * the debt is NOT deducted from customer outstanding even if customer has other debts.
 * But with 'credit_note' it works correctly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "\n" . str_repeat("═", 100) . "\n";
echo "🔍 ANALYSIS: refund_mode='auto' vs 'credit_note' Bug\n";
echo str_repeat("═", 100) . "\n\n";

echo "SCENARIO:\n";
echo "─────────\n";
echo "1. Customer has cash invoice: sale#784, amount=2000, paid=2000, status='paid'\n";
echo "   → saleOutstanding = 0 (fully paid)\n\n";
echo "2. Customer has other debts: sale#785, amount=3000, paid=0\n";
echo "   → customerTotalOutstanding = 3000\n\n";
echo "3. Create return for sale#784 with amount=2000\n\n";

echo str_repeat("═", 100) . "\n";
echo "CASE 1: refund_mode='auto' (NOT WORKING - BUG)\n";
echo str_repeat("═", 100) . "\n\n";

echo "Code Logic in ReturnService.php:\n";
echo "─────────────────────────────────\n\n";

echo "Line 745-757: if (\$saleOutstanding > 0) {\n";
echo "  → NOT executed (saleOutstanding=0)\n\n";

echo "Line 758-762: } else {\n";
echo "    if (\$refundMode === 'credit_note' || \$refundMode === 'deduct_and_return') {\n";
echo "        \$data['paid_amount'] = 0;\n";
echo "    }\n";
echo "  → NOT executed ('auto' is neither 'credit_note' nor 'deduct_and_return')\n";
echo "  → paid_amount is NOT set here\n";
echo "  → deductFromCustomerBalance is NOT set here (stays 0)\n\n";

echo "Line 774-785: if (\$saleOutstanding === 0) {\n";
echo "    if (in_array(strtolower((string) \$refundMode), ['deduct_and_return', 'auto'], true)) {\n";
echo "        \$deductFromCustomerBalance = min(\$customerTotalOutstanding, \$grandTotal);\n";
echo "        \$data['paid_amount'] = round(max(0, \$grandTotal - \$deductFromCustomerBalance), 2);\n";
echo "    }\n";
echo "  }\n\n";
echo "SHOULD BE executed for 'auto':\n";
echo "  - customerTotalOutstanding = getCustomerTotalOutstanding() = 3000\n";
echo "  - deductFromCustomerBalance = min(3000, 2000) = 2000\n";
echo "  - paid_amount = max(0, 2000 - 2000) = 0\n\n";

echo "EXPECTED RESULT:\n";
echo "  ✓ deductFromCustomerBalance = 2000 (will be passed to allocateCustomerBalance)\n";
echo "  ✓ paid_amount = 0 (no cash refund)\n\n";

echo "ACTUAL ISSUE:\n";
echo "  ❌ allocateCustomerBalance is NOT called\n";
echo "  ❌ Debt is NOT deducted from customer\n\n";

echo str_repeat("═", 100) . "\n";
echo "CASE 2: refund_mode='credit_note' (WORKING - CORRECT)\n";
echo str_repeat("═", 100) . "\n\n";

echo "Code Logic in ReturnService.php:\n";
echo "─────────────────────────────────\n\n";

echo "Line 758-762: } else {\n";
echo "    if (\$refundMode === 'credit_note' || \$refundMode === 'deduct_and_return') {\n";
echo "        \$data['paid_amount'] = 0;  ← EXECUTED\n";
echo "    }\n";
echo "  }\n\n";

echo "Result:\n";
echo "  ✓ paid_amount = 0\n";
echo "  ✓ Line 774-785 skips because condition at 776 is false for 'credit_note'\n";
echo "  ✓ BUT deductFromCustomerBalance is still 0!\n\n";

echo "WAIT... this doesn't explain why credit_note works!\n";
echo "Let me re-read the code...\n\n";

echo str_repeat("═", 100) . "\n";
echo "DEEPER ANALYSIS: The Real Issue\n";
echo str_repeat("═", 100) . "\n\n";

echo "Looking at Line 774-785 more carefully:\n\n";

echo "if (\$saleOutstanding === 0) {\n";
echo "    // حساب outstanding عبر كل فواتير العميل\n";
echo "    \$customerTotalOutstanding = \$this->getCustomerTotalOutstanding((int) \$data['party_id'], \$tenantId);\n";
echo "    if (in_array(strtolower((string) \$refundMode), ['deduct_and_return', 'auto'], true)) {\n";
echo "        \$deductFromCustomerBalance = min(\$customerTotalOutstanding, \$grandTotal);\n";
echo "        \$data['paid_amount'] = round(max(0, \$grandTotal - \$deductFromCustomerBalance), 2);\n";
echo "    } elseif (\$refundMode === 'cash') {\n";
echo "        \$data['paid_amount'] = \$grandTotal;\n";
echo "    } elseif (\$refundMode === 'credit_note') {\n";
echo "        \$data['paid_amount'] = 0;  ← REDUNDANT! Already set at line 761\n";
echo "    }\n";
echo "}\n\n";

echo "THE BUG IS HERE!\n";
echo "─────────────────\n\n";

echo "For 'credit_note':\n";
echo "  1. Line 761: paid_amount = 0 ✓\n";
echo "  2. Line 782-783: paid_amount = 0 ✓ (redundant)\n";
echo "  3. BUT: deductFromCustomerBalance is NEVER set!\n";
echo "  4. So how does credit_note work?\n\n";

echo "Looking back at the condition in line 758-762:\n";
echo "  'credit_note' is handled there\n";
echo "  'auto' is NOT handled there\n\n";

echo "So maybe the issue is that for 'auto':\n";
echo "  Line 758-762 doesn't execute → deductFromCustomerBalance stays 0\n";
echo "  Line 774-785 should execute → but maybe something is wrong?\n\n";

echo "Or maybe the condition check is the issue?\n";
echo "Line 776: in_array(strtolower((string) \$refundMode), ['deduct_and_return', 'auto'], true)\n";
echo "  This checks: is refundMode in ['deduct_and_return', 'auto']?\n";
echo "  For 'auto': YES, should execute\n\n";

echo "HYPOTHESIS:\n";
echo "───────────\n";
echo "The issue might be that deductFromCustomerBalance is calculated correctly,\n";
echo "but it's not being passed correctly to allocateCustomerBalance,\n";
echo "OR allocateCustomerBalance is not being called at all.\n\n";

echo "Check line 1057-1065:\n";
echo "  if (\$data['return_type'] === 'sale' && !empty(\$data['party_id']) && \$deductFromCustomerBalance > 0) {\n";
echo "      \$this->allocateCustomerBalance(...)\n";
echo "  }\n\n";

echo "For 'credit_note' to work, deductFromCustomerBalance must be > 0\n";
echo "But it's NEVER set in the code for 'credit_note'!\n";
echo "So maybe credit_note is also NOT working correctly?\n\n";

echo "OR: Is deductFromCustomerBalance calculated BEFORE the return is saved?\n";
echo "If not, the value might not persist.\n\n";

echo str_repeat("═", 100) . "\n";
echo "CONCLUSION\n";
echo str_repeat("═", 100) . "\n\n";

echo "The bug seems to be in line 758-762:\n";
echo "  The 'auto' mode is NOT handled when saleOutstanding = 0\n";
echo "  This causes deductFromCustomerBalance to remain 0\n";
echo "  Later at line 776, it checks if refundMode is 'auto', which should be true\n\n";

echo "BUT: If customerTotalOutstanding is 0 (no other debts),\n";
echo "  then even if line 776 executes, deductFromCustomerBalance = 0\n\n";

echo "POTENTIAL SOLUTIONS:\n";
echo "───────────────────\n\n";

echo "Option 1: Add 'auto' handling in line 758-762:\n";
echo "  if (\$refundMode === 'credit_note' || \$refundMode === 'deduct_and_return' || \$refundMode === 'auto') {\n";
echo "      \$data['paid_amount'] = 0;\n";
echo "  }\n\n";

echo "Option 2: Ensure line 774-785 handles deductFromCustomerBalance for 'auto'\n";
echo "  Add: } else { \$deductFromCustomerBalance = 0; }\n\n";

echo "Option 3: Debug allocateCustomerBalance to see if it's being called\n";
echo "  Maybe the issue is in allocateCustomerBalance, not in the refund_mode logic\n\n";

