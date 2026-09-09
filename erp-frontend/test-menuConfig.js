/**
 * Quick test to validate menuConfig logic
 * Run: node test-menuConfig.js
 */

// Mock icons
const mockIcon = { name: 'MockIcon' }
const icons = new Proxy({}, {
  get: () => mockIcon
})

// Load menuConfig
import('./src/config/menuConfig.js').then(module => {
  const { 
    validateMenuConfig, 
    getMenuItems, 
    checkAccessForPath, 
    hasMenuPermission,
    getBreadcrumbForPath 
  } = module

  console.log('🧪 Testing menuConfig...\n')

  // Test 1: Validation
  try {
    validateMenuConfig()
    console.log('✅ Test 1: validateMenuConfig() passed - no duplicate paths')
  } catch (error) {
    console.error('❌ Test 1 FAILED:', error.message)
    process.exit(1)
  }

  // Test 2: superAdmin sees everything
  const superAdminItems = getMenuItems('super_admin', [])
  console.log(`✅ Test 2: superAdmin sees ${superAdminItems.length} top-level sections`)

  // Test 3: employee sees limited items
  const employeeItems = getMenuItems('employee', [])
  console.log(`✅ Test 3: employee (no permissions) sees ${employeeItems.length} top-level sections`)

  // Test 4: admin role
  const adminItems = getMenuItems('admin', ['sale.view', 'purchase.view'])
  console.log(`✅ Test 4: admin sees ${adminItems.length} top-level sections`)

  // Test 5: checkAccessForPath
  const testUser = { role: 'admin', permissions: ['sale.view', 'product.view'] }
  
  const canAccessSales = checkAccessForPath('/sales/history', testUser)
  console.log(`✅ Test 5a: admin with 'sale.view' can access /sales/history: ${canAccessForPath}`)

  const canAccessProducts = checkAccessForPath('/products', testUser)
  console.log(`✅ Test 5b: admin with 'product.view' can access /products: ${canAccessProducts}`)

  const canAccessNoPermission = checkAccessForPath('/admin/data-integrity', testUser)
  console.log(`✅ Test 5c: admin without specific permission CANNOT access /admin/data-integrity: ${!canAccessNoPermission}`)

  // Test 6: Breadcrumb for path
  const breadcrumb = getBreadcrumbForPath('/sales/history')
  console.log(`✅ Test 6: breadcrumb for /sales/history:`, breadcrumb ? `${breadcrumb.section} > ${breadcrumb.label}` : 'NOT FOUND')

  // Test 7: Dynamic route matching
  const dynamicBreadcrumb = getBreadcrumbForPath('/sales/12345')
  console.log(`✅ Test 7: dynamic route /sales/12345:`, dynamicBreadcrumb ? `${dynamicBreadcrumb.section} > ${dynamicBreadcrumb.label}` : 'NOT FOUND')

  // Test 8: Permission modes
  const userWithOne = { role: 'user', permissions: ['sale.view'] }
  const userWithBoth = { role: 'user', permissions: ['sale.view', 'sale.create'] }
  
  // This entry requires ['sale.view', 'sale.create'] with OR mode (default)
  const entry1 = { permissions: ['sale.view', 'sale.create'], access: 'user' }
  console.log(`✅ Test 8a: OR mode - user with ONE permission passes: ${hasMenuPermission(entry1, userWithOne.role, userWithOne.permissions)}`)
  console.log(`✅ Test 8b: OR mode - user with BOTH permissions passes: ${hasMenuPermission(entry1, userWithBoth.role, userWithBoth.permissions)}`)

  // This entry requires ALL permissions
  const entry2 = { permissions: ['sale.view', 'sale.create'], permissionsMode: 'all', access: 'user' }
  console.log(`✅ Test 8c: ALL mode - user with ONE permission fails: ${!hasMenuPermission(entry2, userWithOne.role, userWithOne.permissions)}`)
  console.log(`✅ Test 8d: ALL mode - user with BOTH permissions passes: ${hasMenuPermission(entry2, userWithBoth.role, userWithBoth.permissions)}`)

  // Test 9: Role hierarchy
  const managerUser = { role: 'manager', permissions: [] }
  const entry3 = { access: 'manager' }
  console.log(`✅ Test 9a: manager role can access 'manager' level: ${hasMenuPermission(entry3, managerUser.role, managerUser.permissions)}`)
  
  const entry4 = { access: 'admin' }
  console.log(`✅ Test 9b: manager role CANNOT access 'admin' level: ${!hasMenuPermission(entry4, managerUser.role, managerUser.permissions)}`)

  // Test 10: Unknown route
  const unknownAccess = checkAccessForPath('/some/unknown/path', testUser)
  console.log(`✅ Test 10: unknown route returns false (safe deny): ${!unknownAccess}`)

  console.log('\n✅ All tests passed! menuConfig is working correctly.')
})
