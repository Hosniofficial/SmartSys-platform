<template>
  <div class="flex flex-col h-screen bg-[#fafafa] overflow-hidden text-slate-900 antialiased" dir="rtl">

    <AppSidebar
      :menu-items="menuItems"
      :collapsed="sidebarCollapsed"
      :active-section="activeSection"
      @section="toggleSection"
      @toggle="toggleSidebar"
    />

    <!--
      Top bar: branding + utilities only (notifications, profile). The old
      "desktop top navigation" (<nav> with menu links / dropdowns) has been
      REMOVED, not hidden. Sidebar + SectionPanel is the single navigation
      system now. See design decision log at the bottom of this file's
      companion doc for why (ERP with 11 top-level sections + RTL).
    -->
    <header
      class="h-14 md:h-16 bg-white border-b border-slate-200 sticky top-0 z-40 shrink-0 px-2 sm:px-4 lg:px-6 transition-[margin] duration-200"
      :style="{ marginRight: contentOffset }"
    >
      <div class="h-full max-w-[1800px] mx-auto flex justify-between items-center overflow-x-hidden">

        <!-- Right side: Branding + mobile toggle -->
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
          <!-- Mobile Menu Toggle only — desktop toggle is in AppSidebar bottom button -->
          <button
            @click="mobileMenuOpen = true"
            class="md:hidden w-8 h-8 flex items-center justify-center text-slate-500 hover:bg-slate-50 rounded-md transition-colors shrink-0"
            aria-label="فتح القائمة"
          >
            <Menu :size="20" class="shrink-0" />
          </button>

          <!-- Branding -->
          <div
            class="flex items-center gap-2 sm:gap-3 group cursor-pointer shrink-0"
            @click="router.push('/cashier-dashboard')"
          >
            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-slate-900 rounded-md flex items-center justify-center text-white transition-transform group-hover:scale-105 shrink-0">
              <Building :size="16" class="sm:hidden shrink-0" />
              <Building :size="18" class="hidden sm:block shrink-0" />
            </div>
            <div class="hidden sm:block">
              <h2 class="text-[10px] sm:text-xs font-bold text-slate-900 leading-none tracking-tight">نظام الإدارة</h2>
              <p class="text-[8px] sm:text-[9px] font-bold text-blue-600 mt-0.5 sm:mt-1 uppercase tracking-widest">SMARTSYS ERP</p>
            </div>
          </div>
        </div>

        <!-- Left side: Notification & Profile -->
        <div class="flex items-center gap-1 sm:gap-2 md:gap-3 shrink-0">

          <!-- Notifications -->
          <div class="relative" ref="notificationsRef">
            <button
              @click="toggleNotifications"
              class="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-50 rounded-md transition-all relative shrink-0"
              :class="{ '!text-red-600': unreadCount > 0 }"
              :aria-expanded="showNotifications"
              aria-haspopup="true"
              aria-label="الإشعارات"
            >
              <Bell :size="16" class="sm:hidden" :class="{ 'animate-bounce': unreadCount > 0 }" />
              <Bell :size="18" class="hidden sm:block" :class="{ 'animate-bounce': unreadCount > 0 }" />
              <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 sm:-top-1 sm:-right-1 min-w-[14px] sm:min-w-[16px] h-3.5 sm:h-4 px-0.5 flex items-center justify-center text-[8px] sm:text-[9px] font-black text-white bg-gradient-to-br from-red-500 to-rose-600 rounded-full border border-white shadow-sm"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </button>

            <!-- Notifications Panel -->
            <Transition name="dropdown-v2">
              <div
                v-if="showNotifications"
                class="fixed sm:absolute left-2 right-2 sm:left-0 md:left-auto md:right-0 top-full mt-1 sm:w-[min(90vw,320px)] md:w-80 max-h-[calc(100vh-80px)] sm:max-h-[70vh] md:max-h-80 bg-white border border-slate-200 rounded-lg shadow-2xl z-[200] overflow-hidden"
              >
                <div class="px-3 sm:px-4 py-2 sm:py-3 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 sticky top-0 z-10">
                  <span class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-widest">الإشعارات</span>
                  <button @click="markAllAsRead" class="text-[9px] sm:text-[10px] font-bold text-blue-600 hover:underline">تمييز الكل</button>
                </div>

                <div class="max-h-[calc(100vh-140px)] sm:max-h-80 overflow-y-auto custom-scroll">
                  <div v-if="loadingNotifications" class="p-6 text-center"><BaseSpinner :size="16" /></div>
                  <div v-else-if="notifications.length === 0" class="p-6 sm:p-8 text-center text-slate-400">
                    <p class="text-[10px] sm:text-[11px] font-medium">لا توجد إشعارات حالياً</p>
                  </div>
                  <div v-else class="divide-y divide-slate-50">
                    <div
                      v-for="note in notifications"
                      :key="note.id"
                      class="p-3 sm:p-4 hover:bg-slate-50 transition-colors group flex items-start justify-between gap-2 sm:gap-3"
                    >
                      <div class="flex items-start gap-2 sm:gap-3 flex-1 min-w-0 cursor-pointer" @click="markNotificationAsRead(note)">
                        <div v-if="!note.is_read" class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full bg-blue-600 mt-1.5 shrink-0"></div>
                        <div class="flex-1 min-w-0">
                          <div class="flex items-center gap-1.5 sm:gap-2 mb-0.5">
                            <span v-if="note.icon" class="text-xs sm:text-sm">{{ note.icon }}</span>
                            <p class="text-[10px] sm:text-xs font-bold text-slate-900 truncate">{{ note.title }}</p>
                          </div>
                          <p class="text-[9px] sm:text-[10px] text-slate-500 leading-normal line-clamp-2">{{ note.message }}</p>

                          <div v-if="note.action" class="mt-1.5 sm:mt-2 pt-1 sm:pt-1.5 border-t border-slate-100">
                            <router-link
                              v-if="note.action.path"
                              :to="note.action.path"
                              class="text-[9px] sm:text-[10px] font-bold text-blue-600 hover:text-blue-700 transition-all inline-flex items-center gap-1"
                              @click.stop
                            >
                              {{ note.action.label }}
                              <span class="text-[7px] sm:text-[8px]">→</span>
                            </router-link>
                          </div>

                          <p class="text-[8px] sm:text-[9px] text-slate-400 mt-1 sm:mt-1.5 font-mono">{{ formatRelativeTime(note.created_at) }}</p>
                        </div>
                      </div>
                      <button
                        v-if="note.dismissible !== false"
                        @click.stop="markNotificationAsRead(note)"
                        class="text-slate-300 hover:text-slate-500 hover:bg-slate-100 p-0.5 sm:p-1 rounded transition-all opacity-0 group-hover:opacity-100 shrink-0"
                        title="إغلاق الإشعار"
                      >
                        <X :size="10" class="sm:hidden" />
                        <X :size="12" class="hidden sm:block" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <div class="h-6 w-px bg-slate-200 mx-0.5 sm:mx-1 hidden md:block"></div>

          <!-- Profile: click-toggle (touch-friendly), closes on outside click -->
          <div class="relative" ref="profileRef">
            <button
              @click="showProfileMenu = !showProfileMenu"
              class="flex items-center gap-1.5 sm:gap-2 md:gap-3 p-0.5 sm:p-1 rounded-md hover:bg-slate-50 transition-all"
              :aria-expanded="showProfileMenu"
              aria-haspopup="true"
              aria-label="قائمة الحساب"
            >
              <div class="w-7 h-7 sm:w-8 sm:h-8 rounded bg-slate-100 flex items-center justify-center text-slate-600 border border-slate-200 shrink-0">
                <User :size="14" class="sm:hidden" />
                <User :size="16" class="hidden sm:block" />
              </div>
              <div class="hidden md:block text-right min-w-0">
                <p class="text-[10px] md:text-[11px] font-bold text-slate-900 leading-none truncate max-w-[120px]">{{ authStore.user?.name }}</p>
                <p class="text-[8px] md:text-[9px] font-medium text-slate-400 mt-0.5 md:mt-1">{{ userRoleDisplay }}</p>
              </div>
              <ChevronDown
                :size="10"
                class="text-slate-300 hidden sm:block transition-transform shrink-0"
                :class="{ 'rotate-180': showProfileMenu }"
              />
            </button>

            <Transition name="dropdown-v2">
              <div
                v-if="showProfileMenu"
                class="absolute left-0 top-full mt-1 w-52 bg-white border border-slate-200 rounded-lg shadow-xl py-1.5 z-[200]"
              >
                <!-- معلومات المستخدم -->
                <div class="px-4 py-2.5 border-b border-slate-100 mb-1">
                  <p class="text-[11px] font-bold text-slate-900 truncate">{{ authStore.user?.name }}</p>
                  <p class="text-[9px] text-slate-400 mt-0.5">{{ userRoleDisplay }}</p>
                </div>
                <router-link to="/profile" class="flex items-center gap-2 px-4 py-2 text-[11px] font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900" @click="showProfileMenu = false">
                  <User :size="14" /> <span>الملف الشخصي</span>
                </router-link>
                <div class="h-px bg-slate-100 my-1"></div>
                <button @click="logout" class="w-full flex items-center gap-2 px-4 py-2 text-[11px] font-bold text-rose-600 hover:bg-rose-50">
                  <LogOut :size="14" /> <span>تسجيل خروج</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </div>
    </header>

    <Transition name="dropdown-v2">
      <SectionPanel
        v-if="activeSection"
        :section="activeSection"
        :badge-counts="badgeCounts"
        :sidebar-collapsed="sidebarCollapsed"
        @close="activeSection = null"
        @navigate="activeSection = null"
      />
    </Transition>

    <!-- Mobile Navigation Drawer -->
    <Transition name="fade">
       <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 md:hidden" @click.self="mobileMenuOpen = false">
          <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
          <aside class="absolute top-0 right-0 w-[min(280px,80vw)] h-full bg-white flex flex-col shadow-2xl overflow-hidden animate-slide-in">
             <!-- Header -->
             <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-gradient-to-r from-slate-50 to-white">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 bg-slate-900 rounded flex items-center justify-center">
                    <Building :size="14" class="text-white" />
                  </div>
                  <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest text-slate-700">القائمة</span>
                </div>
                <button @click="mobileMenuOpen = false" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-900 hover:bg-slate-100 rounded-md transition-colors" aria-label="إغلاق القائمة">
                  <X :size="18" />
                </button>
             </div>

             <!-- Menu Items -->
             <div class="flex-1 overflow-y-auto p-2 sm:p-3 space-y-0.5 custom-scroll overscroll-contain">
                <template v-for="item in menuItems" :key="item.key || item.path">
                  <!-- Direct Link -->
                  <router-link
                    v-if="!item.items"
                    :to="item.path"
                    @click="mobileMenuOpen = false"
                    class="flex items-center gap-2.5 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg text-[11px] sm:text-xs font-bold transition-all"
                    :class="[isActive(item) ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-700 hover:bg-slate-50 active:bg-slate-100']"
                  >
                    <component :is="item.icon" :size="16" class="sm:w-[18px] sm:h-[18px] shrink-0" />
                    <span class="flex-1">{{ item.name }}</span>
                  </router-link>

                  <!-- Parent with Children -->
                  <div v-else class="space-y-0.5">
                    <button
                      @click="toggleMobileItem(item.key)"
                      class="w-full flex items-center gap-2.5 sm:gap-3 px-3 sm:px-4 py-2.5 sm:py-3 rounded-lg text-[11px] sm:text-xs font-bold text-slate-700 hover:bg-slate-50 active:bg-slate-100 transition-all"
                      :class="[isActive(item) ? 'bg-slate-100' : '']"
                      :aria-expanded="expandedMobileItems.has(item.key)"
                    >
                      <component :is="item.icon" :size="16" class="sm:w-[18px] sm:h-[18px] shrink-0" />
                      <span class="flex-1 text-right">{{ item.name }}</span>
                      <ChevronDown :size="12" class="sm:w-[14px] sm:h-[14px] transition-transform duration-200 shrink-0" :class="{ 'rotate-180': expandedMobileItems.has(item.key) }" />
                    </button>

                    <!-- Children -->
                    <Transition name="expand">
                      <div v-if="expandedMobileItems.has(item.key)" class="mr-3 sm:mr-4 mt-0.5 border-r-2 border-slate-100 space-y-0.5">
                        <router-link
                          v-for="child in item.items"
                          :key="child.path"
                          :to="child.path"
                          @click="mobileMenuOpen = false"
                          class="flex items-center gap-2 sm:gap-2.5 px-4 sm:px-5 py-2 sm:py-2.5 rounded-lg text-[10px] sm:text-[11px] font-bold transition-all"
                          :class="[isLeafActive(child) ? 'text-blue-600 bg-blue-50 border-r-2 border-blue-600' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50']"
                        >
                          <component :is="child.icon" :size="13" class="sm:w-[14px] sm:h-[14px] shrink-0 opacity-70" />
                          <span class="flex-1">{{ child.name }}</span>
                        </router-link>
                      </div>
                    </Transition>
                  </div>
                </template>
             </div>

             <!-- Footer -->
             <div class="p-3 sm:p-4 border-t border-slate-100 bg-slate-50/50 shrink-0">
               <!-- معلومات المستخدم -->
               <div class="flex items-center gap-2 sm:gap-3 px-2 sm:px-3 py-2 mb-2">
                 <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 shrink-0">
                   <User :size="14" class="sm:w-4 sm:h-4" />
                 </div>
                 <div class="flex-1 min-w-0">
                   <p class="text-[10px] sm:text-[11px] font-bold text-slate-900 truncate">{{ authStore.user?.name }}</p>
                   <p class="text-[8px] sm:text-[9px] text-slate-500 mt-0.5">{{ userRoleDisplay }}</p>
                 </div>
               </div>
               <!-- أزرار الإجراءات -->
               <div class="flex gap-2">
                 <router-link
                   to="/profile"
                   @click="mobileMenuOpen = false"
                   class="flex-1 flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-600 hover:bg-slate-100 transition-colors"
                 >
                   <User :size="13" />
                   <span>الملف الشخصي</span>
                 </router-link>
                 <button
                   @click="logout"
                   class="flex-1 flex items-center justify-center gap-1.5 h-9 rounded-lg bg-rose-50 border border-rose-100 text-[11px] font-bold text-rose-600 hover:bg-rose-100 transition-colors"
                 >
                   <LogOut :size="13" />
                   <span>تسجيل خروج</span>
                 </button>
               </div>
             </div>
          </aside>
       </div>
    </Transition>

    <!--
      Main content. `contentOffset` now accounts for BOTH the sidebar width
      AND an open SectionPanel, so the panel never silently overlaps content
      (fixes UX issue #3 from code review) — the content area visibly makes
      room instead of being covered.
    -->
    <main
      class="flex-1 overflow-x-hidden overflow-y-auto custom-scroll relative z-10 transition-[margin] duration-200"
      :style="{ marginRight: contentOffset }"
    >
      <slot></slot>
    </main>

  </div>
</template>

<script setup>
import { computed, ref, reactive, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToast } from '@/composables/useToast'
import { useActiveRoute } from '@/composables/useActiveRoute'
import { getMenuItems } from '@/config/menuConfig' // ← Single Source of Truth
import apiClient from '@/config/axios'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import SectionPanel from '@/components/layout/SectionPanel.vue'
import {
  Bell, User, LogOut, Menu, X, ChevronDown, Building
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { showToast } = useToast()

// Single shared active-route implementation — the old desktop top nav
// (and its activeDropdown/toggleDropdown/handleNavMouseEnter/handleNavMouseLeave
// functions) has been REMOVED entirely, not just hidden. Sidebar +
// SectionPanel is the one and only navigation system.
const { isActive, isLeafActive } = useActiveRoute()

const mobileMenuOpen = ref(false)
const sidebarCollapsed = ref(true)
const activeSection = ref(null)
const expandedMobileItems = ref(new Set())
const badgeCounts = reactive({})
const notifications = ref([])
const unreadCount = ref(0)
const showNotifications = ref(false)
const showProfileMenu = ref(false)
const loadingNotifications = ref(false)
const previousUnreadCount = ref(0)

const notificationsRef = ref(null)
const profileRef = ref(null)

const sidebarWidth = computed(() => (sidebarCollapsed.value ? 72 : 256))

// SectionPanel width mirrors its own template: min(320px, 100vw - 72px).
// We only need the desktop numeric contribution here since the panel is
// itself hidden below md breakpoint contexts in practice (mobile uses the
// drawer instead), so this offset is safe to add unconditionally on the
// content that already only applies margin on md+ via the panel's own
// v-if="section" gating.
const sectionPanelWidth = 320

const contentOffset = computed(() => {
  const base = sidebarWidth.value
  const panel = activeSection.value ? sectionPanelWidth : 0
  return `${base + panel}px`
})

const isUnread = (notification) => Number(notification.is_read) === 0

function saveNotificationsToStorage() {
  if (notifications.value.length > 0) {
    localStorage.setItem('cached_notifications', JSON.stringify(notifications.value))
    localStorage.setItem('cached_unread_count', String(unreadCount.value))
    localStorage.setItem('cached_notifications_time', String(Date.now()))
  }
}

function loadNotificationsFromStorage() {
  try {
    const cached = localStorage.getItem('cached_notifications')
    const cachedCount = localStorage.getItem('cached_unread_count')
    const cachedTime = Number(localStorage.getItem('cached_notifications_time'))
    const cacheIsFresh = cachedTime > 0 && Date.now() - cachedTime < 5 * 60 * 1000
    if (cached && cacheIsFresh) {
      const parsed = JSON.parse(cached)
      if (Array.isArray(parsed)) {
        notifications.value = parsed
        unreadCount.value = cachedCount ? parseInt(cachedCount) : 0
        previousUnreadCount.value = unreadCount.value
      } else {
        localStorage.removeItem('cached_notifications')
        localStorage.removeItem('cached_unread_count')
        localStorage.removeItem('cached_notifications_time')
      }
    } else if (cached || cachedTime) {
      localStorage.removeItem('cached_notifications')
      localStorage.removeItem('cached_unread_count')
      localStorage.removeItem('cached_notifications_time')
    }
  } catch (e) {
    console.error('Failed to load notifications from cache:', e)
    localStorage.removeItem('cached_notifications')
    localStorage.removeItem('cached_unread_count')
    localStorage.removeItem('cached_notifications_time')
  }
}

const userRoleDisplay = computed(() => {
  const role = authStore.user?.role
  const roleNames = {
    'super_admin': 'مالك النظام',
    'admin': 'مدير',
    'manager': 'مشرف',
    'cashier': 'كاشير',
    'inventory_clerk': 'مسؤول المخزون',
    'finance_officer': 'مسؤول مالي',
    'branch_manager': 'مدير الفرع',
    'employee': 'موظف'
  }
  return roleNames[role] || role
})

function toggleMobileItem(key) {
  const newSet = new Set(expandedMobileItems.value)
  if (newSet.has(key)) newSet.delete(key)
  else newSet.add(key)
  expandedMobileItems.value = newSet
}

function toggleSection(item) {
  activeSection.value = activeSection.value?.key === item.key ? null : item
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('smartsys_sidebar_collapsed', String(sidebarCollapsed.value))
}

// ── Click-outside handling for click-toggled menus ──
function handleDocumentClick(e) {
  if (activeSection.value && !e.target.closest('.section-panel') && !e.target.closest('aside')) {
    activeSection.value = null
  }
  if (showNotifications.value && notificationsRef.value && !notificationsRef.value.contains(e.target)) {
    showNotifications.value = false
  }
  if (showProfileMenu.value && profileRef.value && !profileRef.value.contains(e.target)) {
    showProfileMenu.value = false
  }
}

// Handle ESC key to close menus
function handleEscKey(e) {
  if (e.key === 'Escape') {
    mobileMenuOpen.value = false
    activeSection.value = null
    showNotifications.value = false
    showProfileMenu.value = false
  }
}

// ✅ Single Source of Truth: menuConfig.js
const menuItems = computed(() => {
  return getMenuItems(authStore.user?.role || 'employee', authStore.user?.permissions || [])
})

const logout = async () => { await authStore.logout(); router.push('/') }

async function loadSubscription() {
  try {
    const { useSubscriptionStore } = await import('@/stores/subscriptions/subscriptionStore')
    const subscriptionStore = useSubscriptionStore()
    await subscriptionStore.fetchSubscription()

    const alerts = subscriptionStore.generateSubscriptionAlerts()

    if (alerts && alerts.length > 0) {
      alerts.forEach(alert => {
        const subscriptionKey = `${alert.type}_${alert.id || alert.date || alert.expires_at || 'current'}`
        const exists = notifications.value.some(n => n.subscription_key === subscriptionKey)
        if (!exists) {
          const newAlert = {
            id: `alert_${alert.type}_${Date.now()}`,
            title: alert.title,
            message: alert.message,
            type: alert.type,
            subscription_key: subscriptionKey,
            priority: alert.priority,
            color: alert.color,
            icon: alert.icon,
            action: alert.action,
            is_read: 0,
            created_at: new Date().toISOString(),
            dismissible: alert.dismissible
          }
          notifications.value.unshift(newAlert)
          unreadCount.value = notifications.value.filter(isUnread).length
          saveNotificationsToStorage()
        }
      })
    }
  } catch (e) {
    if (import.meta.env.DEV) {
      console.error('Failed to load subscription alerts:', e.message || e)
    }
  }
}

async function loadNotifications() {
  loadingNotifications.value = true
  try {
    const response = await apiClient.get('/notifications?page=1&per_page=10')
    if (response.data.status === 'success') {
      const responseData = response.data.data || {}
      const apiNotifications = Array.isArray(responseData.notifications) ? responseData.notifications : []

      const subscriptionAlerts = notifications.value.filter(n => n.type?.startsWith('subscription_'))
      notifications.value = [...subscriptionAlerts, ...apiNotifications]

      const currentUnread = notifications.value.filter(isUnread).length

      if (currentUnread > previousUnreadCount.value && currentUnread > 0) {
        const newCount = currentUnread - previousUnreadCount.value
        const message = `لديك ${newCount} إشعار${newCount > 1 ? 'ات' : ''} جديد${newCount > 1 ? 'ة' : ''}`
        showToast(message, 'info', 3000)
      }

      previousUnreadCount.value = currentUnread
      unreadCount.value = currentUnread

      saveNotificationsToStorage()
    }
  } catch (error) {
    if (import.meta.env.DEV) {
      console.error('Error loading notifications', error.message || error)
    }
  } finally {
    loadingNotifications.value = false
  }
}

async function markNotificationAsRead(notification) {
  try {
    if (notification.is_read) return

    if (notification.type?.startsWith('subscription_')) {
      notification.is_read = 1
      unreadCount.value = notifications.value.filter(isUnread).length
      previousUnreadCount.value = unreadCount.value
      saveNotificationsToStorage()
      return
    }

    try {
      const result = await apiClient.put(`/notifications/${notification.id}/read`)
      if (result.data?.status === 'success' && result.data.data?.notification) {
        Object.assign(notification, result.data.data.notification)
      } else {
        notification.is_read = 1
      }
    } catch (apiError) {
      notification.is_read = 1
    }

    unreadCount.value = notifications.value.filter(isUnread).length
    previousUnreadCount.value = unreadCount.value

    saveNotificationsToStorage()
  } catch (error) {
    if (import.meta.env.DEV) {
      console.error('Error marking notification read', error.message || error)
    }
  }
}

const hasLoadedNotifications = ref(false)
function toggleNotifications() {
  showNotifications.value = !showNotifications.value
  showProfileMenu.value = false
  if (showNotifications.value && !hasLoadedNotifications.value) {
    loadNotifications()
    hasLoadedNotifications.value = true
  }
}

watch(unreadCount, (newCount) => {
  if (newCount === 0 && previousUnreadCount.value > 0) {
    previousUnreadCount.value = 0
  }
})

watch(showProfileMenu, (val) => {
  if (val) { showNotifications.value = false }
})

async function markAllAsRead() {
  for (const note of notifications.value) {
    if (!note.is_read) {
      await markNotificationAsRead(note)
    }
  }
}

const formatRelativeTime = (date) => {
  if (!date) return 'للتو'

  try {
    const now = new Date()
    const notifDate = new Date(date)

    if (isNaN(notifDate.getTime())) return 'للتو'

    const diff = now - notifDate
    const mins = Math.floor(diff / 60000)

    if (mins < 1) return 'للتو'
    if (mins < 60) return `منذ ${mins} د`

    const hours = Math.floor(mins / 60)
    if (hours < 24) return `منذ ${hours} س`

    const days = Math.floor(hours / 24)
    if (days < 7) return `منذ ${days} ي`

    return notifDate.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  } catch (e) {
    return 'للتو'
  }
}

let notificationIntervalId = null

onMounted(() => {
  const savedSidebarState = localStorage.getItem('smartsys_sidebar_collapsed')
  if (savedSidebarState !== null) sidebarCollapsed.value = savedSidebarState !== 'false'

  loadNotificationsFromStorage()

  document.addEventListener('click', handleDocumentClick)
  document.addEventListener('keydown', handleEscKey)

  if (route.path === '/upgrade') {
    return
  }

  if (route.path !== '/setup') {
    loadSubscription()
    loadNotifications()
  }

  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {})
  }

  if (notificationIntervalId) clearInterval(notificationIntervalId)

  notificationIntervalId = setInterval(async () => {
    await loadSubscription().catch(() => {})
    await loadNotifications().catch(() => {})
  }, 60000)
})

onUnmounted(() => {
  if (notificationIntervalId) {
    clearInterval(notificationIntervalId)
  }
  document.removeEventListener('click', handleDocumentClick)
  document.removeEventListener('keydown', handleEscKey)
})
</script>

<style scoped>
/* Dropdown Items */
.dropdown-item-v2 {
  @apply flex items-center gap-3 px-3 py-2 text-[11px] font-bold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors;
}
.dropdown-item-v2.active {
  @apply text-blue-600 bg-blue-50/50;
}

/* Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* Transitions */
.dropdown-v2-enter-active { transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.dropdown-v2-leave-active { transition: all 0.1s ease; }
.dropdown-v2-enter-from { opacity: 0; transform: translateY(4px) scale(0.98); }
.dropdown-v2-leave-to { opacity: 0; transform: translateY(4px) scale(0.98); }

.expand-enter-active, .expand-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  transform-origin: top;
}
.expand-enter-from, .expand-leave-to {
  opacity: 0;
  transform: scaleY(0.95);
  max-height: 0;
}
.expand-enter-to, .expand-leave-from {
  opacity: 1;
  transform: scaleY(1);
  max-height: 500px;
}

.animate-slide-in { animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Respect reduced-motion preference */
@media (prefers-reduced-motion: reduce) {
  .dropdown-v2-enter-active,
  .dropdown-v2-leave-active,
  .expand-enter-active,
  .expand-leave-active,
  .animate-slide-in,
  .fade-enter-active,
  .fade-leave-active {
    transition: none !important;
    animation: none !important;
  }
}
</style>
