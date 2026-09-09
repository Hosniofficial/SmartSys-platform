import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { matchesRoutePattern } from '@/config/menuConfig'

/**
 * Centralized route-matching logic: determines if a menu item's path
 * matches the current route. Exported as a shared composable so we have
 * ONE implementation of the matching logic (not copies in Layout, Sidebar,
 * SectionPanel, etc.).
 *
 * Handles:
 * - Exact path matches
 * - Dynamic route patterns (e.g. /sales/:id)
 * - Active parent items (if any child route is active, parent is active too)
 * - Query param stripping (base path only)
 */
export function useActiveRoute() {
  const route = useRoute()

  const currentPath = computed(() => {
    // Strip query params — we only care about base path
    return route.path.split('?')[0]
  })

  /**
   * Test if a leaf item (with a single path) matches the current route.
   * Handles both static paths and dynamic patterns.
   */
  const isLeafActive = (item) => {
    if (!item?.path) return false
    const itemPath = item.path.split('?')[0]

    if (itemPath === currentPath.value) return true
    if (itemPath.includes(':')) {
      return matchesRoutePattern(itemPath, currentPath.value)
    }

    return false
  }

  /**
   * Test if a parent item's section is active. A parent is active if:
   * 1. Its direct children include the current active leaf, OR
   * 2. Any of its child items (recursively) match the current route
   */
  const isActive = (parentItem) => {
    if (!parentItem.items) return isLeafActive(parentItem)

    // Check children recursively
    return parentItem.items.some(child => {
      if (isLeafActive(child)) return true
      if (child.items) return isActive(child)
      return false
    })
  }

  return {
    isLeafActive,
    isActive,
    currentPath
  }
}
