<template>
  <div class="flex flex-col h-screen bg-[#f8fafc] overflow-hidden text-slate-700" dir="rtl">
    
    <!-- Top Navigation Bar -->
    <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-100 shadow-sm sticky top-0 z-[60] shrink-0 px-4 lg:px-8">
      <div class="h-full max-w-[1600px] mx-auto flex justify-between items-center">
        
        <!-- Right side: Logo & Desktop Nav -->
        <div class="flex items-center gap-8">
          <!-- Mobile Menu Toggle -->
          <button @click="mobileMenuOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-slate-50 rounded-xl transition-all">
            <Menu :size="24" />
          </button>

          <!-- Branding -->
          <div class="flex items-center gap-3 group cursor-pointer" @click="router.push('/cashier-dashboard')">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-200 text-white transform group-hover:rotate-6 transition-transform">
              <Building :size="22" />
            </div>
            <div class="hidden sm:block">
              <h2 class="text-sm font-black text-slate-900 leading-none tracking-tight uppercase">نظام الإدارة</h2>
              <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-[0.2em]">SMARTSYS ERP</p>
            </div>
          </div>

          <!-- Desktop Navigation -->
          <nav class="hidden lg:flex items-center gap-1" @mouseleave="activeDropdown = null">
            <template v-for="item in menuItems" :key="item.key || item.path">

              <!-- Direct link (no children) -->
              <router-link
                v-if="!item.children"
                :to="item.path"
                class="nav-link"
                :class="{'nav-link-active': isParentActive(item)}"
              >
                <component :is="item.icon" :size="18" class="shrink-0" />
                <span>{{ item.name }}</span>
              </router-link>

              <!-- Mega Dropdown parent -->
              <div
                v-else
                class="relative"
                @mouseenter="activeDropdown = item.key"
              >
                <button
                  class="nav-link"
                  :class="{'nav-link-active': isParentActive(item)}"
                  @click="activeDropdown = activeDropdown === item.key ? null : item.key"
                >
                  <component :is="item.icon" :size="18" class="shrink-0" />
                  <span>{{ item.name }}</span>
                  <ChevronDown :size="13" class="transition-transform duration-200 opacity-60" :class="{'rotate-180': activeDropdown === item.key}" />
                </button>

                <!-- Dropdown Panel -->
                <Transition name="dropdown-fade">
                  <div
                    v-if="activeDropdown === item.key"
                    class="mega-dropdown"
                    @mouseenter="activeDropdown = item.key"
                  >
                    <div
                      class="grid gap-0.5 p-2"
                      :class="item.children.length >= 5 ? 'grid-cols-2' : 'grid-cols-1'"
                    >
                      <router-link
                        v-for="child in item.children"
                        :key="child.path"
                        :to="child.path"
                        class="dropdown-item"
                        :class="{'dropdown-item-active': isChildActive(child)}"
                        @click="activeDropdown = null"
                      >
                        <div class="dropdown-item-icon">
                          <component :is="child.icon" :size="15" />
                        </div>
                        <span class="flex-1">{{ child.name }}</span>
                        <span v-if="child.badge && badgeCounts[child.badge]" class="bg-rose-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-lg leading-none">
                          {{ badgeCounts[child.badge] }}
                        </span>
                      </router-link>
                    </div>
                  </div>
                </Transition>
              </div>

            </template>
          </nav>
        </div>

        <!-- Left side: Notifications & User -->
        <div class="flex items-center gap-3">
          
          <!-- Notifications Dropdown -->
          <div class="relative">
            <button @click="toggleNotifications" class="w-11 h-11 flex items-center justify-center text-slate-500 hover:bg-slate-50 rounded-2xl transition-all relative group" :class="{'text-red-600': unreadCount > 0}">
              <Bell :size="20" class="shrink-0" :class="{'animate-bounce': unreadCount > 0}" />
              <!-- Badge: عدد الإشعارات -->
              <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 w-6 h-6 flex items-center justify-center text-[11px] font-black text-white bg-gradient-to-br from-red-500 to-rose-600 rounded-full border-2 border-white shadow-lg shadow-red-300/50 animate-pulse-badge">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
              
              <!-- Indicator dot: بدون عدد -->
              <div v-else-if="notifications.length > 0" class="absolute top-2 right-2 w-2 h-2 bg-slate-300 rounded-full"></div>
            </button>

            <Transition name="modal-fade">
              <div v-if="showNotifications" class="absolute left-0 mt-3 w-96 bg-white rounded-[1.5rem] shadow-2xl z-[70] border border-slate-100 overflow-hidden animate-modalIn">
                <div class="px-5 py-3.5 border-b border-slate-100 flex justify-between items-center bg-white">
                  <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">الإشعارات</h3>
                  <button @click="markAllAsRead" class="text-[10px] font-bold text-blue-600 hover:text-blue-700 uppercase transition-all">
                    تمييز الكل
                  </button>
                </div>

                <div class="max-h-96 overflow-y-auto custom-scroll">
                  <div v-if="loadingNotifications" class="p-8 text-center"><BaseSpinner :size="20" color="#2563eb" /></div>
                  <div v-else-if="notifications.length === 0" class="p-8 text-center text-slate-400">
                     <Bell class="text-3xl mb-2 opacity-20 inline-block"></Bell>
                     <p class="text-[11px] font-bold mt-2">لا توجد إشعارات</p>
                  </div>
                  <div v-else class="space-y-0">
                    <div
                      v-for="note in notifications"
                      :key="note.id"
                      class="w-full text-right p-3 border-b border-slate-50 hover:bg-slate-50/50 transition-all group flex items-start justify-between gap-3 cursor-pointer last:border-b-0"
                    >
                      <!-- Left: Icon + Content -->
                      <div class="flex items-start gap-3 flex-1 min-w-0" @click="markNotificationAsRead(note)">
                        <!-- New notification indicator -->
                        <div v-if="!note.is_read" class="flex-shrink-0 mt-2">
                          <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                        </div>
                        
                        <!-- Icon + Title + Message -->
                        <div class="space-y-1 flex-1 min-w-0">
                          <div class="flex items-center gap-2">
                            <span v-if="note.icon" class="text-base flex-shrink-0">{{ note.icon }}</span>
                            <p class="text-xs font-bold text-slate-900 truncate">{{ note.title }}</p>
                          </div>
                          <p class="text-[10px] text-slate-600 leading-relaxed line-clamp-2">{{ note.message }}</p>
                          
                          <!-- Action Button -->
                          <div v-if="note.action" class="mt-2 pt-2 border-t border-slate-100">
                            <router-link
                              v-if="note.action.path"
                              :to="note.action.path"
                              class="text-[10px] font-bold text-blue-600 hover:text-blue-700 transition-all inline-flex items-center gap-1"
                            >
                              {{ note.action.label }}
                              <span class="text-[8px]">→</span>
                            </router-link>
                          </div>
                        </div>
                      </div>

                      <!-- Right: Time + Close -->
                      <div class="flex items-start gap-2 flex-shrink-0 pt-1">
                        <span class="text-[9px] text-slate-400 whitespace-nowrap font-mono">{{ formatRelativeTime(note.created_at) }}</span>
                        <!-- ✅ UX: زر Close منفصل - واضح أنه يغلق وليس يعلّم "مقروء" -->
                        <button
                          v-if="note.dismissible !== false"
                          @click.stop="markNotificationAsRead(note)"
                          class="text-slate-300 hover:text-slate-500 hover:bg-slate-100 p-1 rounded transition-all opacity-0 group-hover:opacity-100"
                          title="إغلاق الإشعار"
                        >
                          <X :size="14" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

          <div class="h-8 w-px bg-slate-100 mx-1 hidden md:block"></div>

          <!-- User Profile Dropdown -->
          <div class="relative group">
            <button class="flex items-center gap-3 p-1.5 pr-4 bg-slate-50 border border-slate-100 rounded-2xl hover:bg-white hover:border-blue-200 transition-all">
              <div class="hidden md:block text-right">
                <p class="text-xs font-black text-slate-900 leading-none">{{ authStore.user?.name || 'مستخدم' }}</p>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-widest">{{ userRoleDisplay }}</p>
              </div>
              <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-blue-600 shadow-sm border border-slate-100 group-hover:bg-blue-600 group-hover:text-white transition-all">
                <User :size="20" />
              </div>
            </button>
            
            <!-- User Mini Menu -->
            <div class="absolute left-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-slate-100 p-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all transform translate-y-2 group-hover:translate-y-0">
               <router-link to="/profile" class="w-full px-4 py-3 text-xs font-black text-slate-700 hover:bg-slate-50 rounded-xl transition-all flex items-center gap-3">
                 <User :size="16" />
                 <span>الملف الشخصي</span>
               </router-link>
               <button @click="logout" class="w-full px-4 py-3 text-xs font-black text-rose-500 hover:bg-rose-50 rounded-xl transition-all flex items-center gap-3">
                 <LogOut :size="16" />
                 <span>تسجيل خروج آمن</span>
               </button>
            </div>
          </div>
        </div>
      </div>
    </header>


    <!-- Mobile Navigation Drawer -->
    <Transition name="fade">
       <div v-if="mobileMenuOpen" class="fixed inset-0 z-[100] lg:hidden">
          <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="mobileMenuOpen = false"></div>
          <aside class="absolute top-0 right-0 w-80 h-full bg-white shadow-2xl flex flex-col animate-modalIn">
             <div class="p-8 border-b border-slate-50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                   <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white"><Building :size="20"/></div>
                   <span class="font-black text-slate-800">القائمة الرئيسية</span>
                </div>
                <button @click="mobileMenuOpen = false" class="text-slate-300"><X :size="24"/></button>
             </div>
             <div class="flex-grow overflow-y-auto p-4 space-y-1 custom-scroll">
                <template v-for="item in menuItems" :key="item.key || item.path">

                  <!-- Direct link (no children) -->
                  <router-link
                    v-if="!item.children"
                    :to="item.path"
                    @click="mobileMenuOpen = false"
                    class="flex items-center gap-4 px-4 py-4 rounded-2xl text-sm font-bold transition-all"
                    :class="[isParentActive(item) ? 'bg-blue-50 text-blue-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50']"
                  >
                    <component :is="item.icon" :size="20" class="shrink-0" />
                    {{ item.name }}
                  </router-link>

                  <!-- Accordion parent (has children) -->
                  <div v-else>
                    <button
                      @click="toggleMobileItem(item.key)"
                      class="w-full flex items-center gap-4 px-4 py-4 rounded-2xl text-sm font-bold transition-all"
                      :class="[isParentActive(item) ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50']"
                    >
                      <component :is="item.icon" :size="20" class="shrink-0" />
                      <span class="flex-1 text-right">{{ item.name }}</span>
                      <ChevronDown :size="16" class="transition-transform duration-200 shrink-0 opacity-60" :class="{'rotate-180': expandedMobileItems.has(item.key)}" />
                    </button>

                    <!-- Accordion children -->
                    <Transition name="accordion">
                      <div v-if="expandedMobileItems.has(item.key)" class="mr-6 mt-1 mb-1 space-y-0.5 border-r-2 border-slate-100 pr-2">
                        <router-link
                          v-for="child in item.children"
                          :key="child.path"
                          :to="child.path"
                          @click="mobileMenuOpen = false"
                          class="flex items-center gap-3 px-4 py-3 rounded-xl text-xs font-bold transition-all"
                          :class="[isChildActive(child) ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50']"
                        >
                          <component :is="child.icon" :size="16" class="shrink-0" />
                          <span class="flex-1">{{ child.name }}</span>
                          <span v-if="child.badge && badgeCounts[child.badge]" class="bg-rose-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded-lg leading-none">
                            {{ badgeCounts[child.badge] }}
                          </span>
                        </router-link>
                      </div>
                    </Transition>
                  </div>

                </template>
             </div>
          </aside>
       </div>
    </Transition>

    <!-- Main Dynamic Content -->
    <div class="flex-1 flex flex-col overflow-hidden relative">
      <main class="flex-1 overflow-x-hidden overflow-y-auto custom-scroll relative z-10">
        <slot></slot>
      </main>
      
      <!-- Decorative Background Elements -->
      <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-blue-50/50 rounded-full blur-[100px] -z-10 translate-x-1/2 translate-y-1/2"></div>
      <div class="absolute top-0 left-0 w-[500px] h-[500px] bg-indigo-50/50 rounded-full blur-[100px] -z-10 -translate-x-1/2 -translate-y-1/2"></div>
    </div>

  </div>
</template>

<script setup>
import { computed, ref, reactive, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToast } from '@/composables/useToast'
import SubscriptionsService from '@/services/subscriptions'
import apiClient from '@/config/axios'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { 
  LayoutDashboard, ShoppingCart, Undo2, ShoppingBag, Building,
  Users, Wallet, Shield, Settings, UserCog, CreditCard, Layers,
  Bell, User, LogOut, Clock, Menu, X, ChevronDown,
  FileText, ClipboardCheck, Receipt, History,
  Package2, Warehouse, Building2, Share2, Link, ArrowLeftRight,
  Banknote, TrendingUp, TrendingDown, BarChart2,
  ShieldCheck, ClipboardList, Truck, Printer, PieChart, BookOpen, CalendarCheck
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { showToast } = useToast()
const mobileMenuOpen = ref(false)
const activeDropdown = ref(null)
const expandedMobileItems = ref(new Set())
const badgeCounts = reactive({})

const isAdmin = computed(() => authStore.isAdmin)
const isSuperAdmin = computed(() => authStore.isSuperAdmin)

const notifications = ref([])
const unreadCount = ref(0)
const showNotifications = ref(false)
const loadingNotifications = ref(false)
const previousUnreadCount = ref(0)

// ✅ Helper: فحص ما إذا كانت الإشعارات غير مقروءة
const isUnread = (notification) => Number(notification.is_read) === 0

// ✅ حفظ واسترجاع الإشعارات من localStorage (مع TTL)
function saveNotificationsToStorage() {
  if (notifications.value.length > 0) {
    localStorage.setItem('cached_notifications', JSON.stringify(notifications.value))
    localStorage.setItem('cached_unread_count', String(unreadCount.value))
    localStorage.setItem('cached_notifications_time', String(Date.now())) // TTL tracking
  }
}

function loadNotificationsFromStorage() {
  try {
    const cached = localStorage.getItem('cached_notifications')
    const cachedCount = localStorage.getItem('cached_unread_count')
    
    if (cached) {
      // ✅ تحقق من البيانات قبل parsing
      const parsed = JSON.parse(cached)
      if (Array.isArray(parsed)) {
        notifications.value = parsed
        unreadCount.value = cachedCount ? parseInt(cachedCount) : 0
        previousUnreadCount.value = unreadCount.value
      } else {
        // تالفة - امسح الـ cache
        localStorage.removeItem('cached_notifications')
        localStorage.removeItem('cached_unread_count')
        localStorage.removeItem('cached_notifications_time')
      }
    }
  } catch (e) {
    console.error('Failed to load notifications from cache:', e)
    // تالفة - امسح الـ cache
    localStorage.removeItem('cached_notifications')
    localStorage.removeItem('cached_unread_count')
    localStorage.removeItem('cached_notifications_time')
  }
}

// ✅  خريطة أسماء الأدوار
const userRoleDisplay = computed(() => {
  const role = authStore.user?.role
  const roleNames = {
    'super_admin': 'مدير النظام',
    'admin': 'مدير',
    'manager': 'مشرف',
    'cashier': 'كاشير',
    'employee': 'موظف'
  }
  return roleNames[role] || role
})

function isChildActive(child) {
  // ✅ استخدام fullPath للمقارنة الدقيقة (including query params)
  return route.fullPath === child.path
}

function isParentActive(item) {
  if (!item.children) return route.path === item.path
  return item.children.some(child => isChildActive(child))
}

function toggleMobileItem(key) {
  const newSet = new Set(expandedMobileItems.value)
  if (newSet.has(key)) {
    newSet.delete(key)
  } else {
    newSet.add(key)
  }
  expandedMobileItems.value = newSet
}

const menuItems = computed(() => {
  if (!isAdmin.value) {
    return [
      { name: 'المبيعات', key: 'sales', icon: ShoppingCart, children: [
        { name: 'نقطة البيع', path: '/sales/point', icon: ShoppingCart },
        { name: 'سجل المبيعات', path: '/sales/history', icon: FileText },
        { name: 'موافقات المبيعات', path: '/sales/approvals', icon: ClipboardCheck, badge: 'approvals' },
        { name: 'لوحة تحكم الكاشير', path: '/cashier-dashboard', icon: LayoutDashboard },
      ]},
      { name: 'العملاء والموردين', key: 'contacts', icon: Users, children: [
        { name: 'العملاء', path: '/customers', icon: Users },
        { name: 'الموردين', path: '/suppliers', icon: Truck },
      ]},
      { name: 'المالية', key: 'finance', icon: Wallet, children: [
        { name: 'المدفوعات', path: '/payments', icon: Banknote },
        { name: 'سندات القبض والصرف', path: '/vouchers', icon: Receipt },
      ]},
      { name: 'الضمان', key: 'warranty', icon: Shield, children: [
        { name: 'طلبات الضمان', path: '/warranty', icon: Shield },
      ]},
    ]
  }

  const items = [
    { name: 'لوحة التحكم', path: '/admin-dashboard', icon: LayoutDashboard },
    { name: 'المبيعات', key: 'sales', icon: ShoppingCart, children: [
      { name: 'نقطة البيع', path: '/sales/point', icon: ShoppingCart },
      { name: 'سجل المبيعات', path: '/sales/history', icon: FileText },
      { name: 'موافقات المبيعات', path: '/sales/approvals', icon: ClipboardCheck, badge: 'approvals' },
      { name: 'لوحة تحكم الكاشير', path: '/cashier-dashboard', icon: LayoutDashboard },
      { name: 'جلسات الكاشير', path: '/sessions', icon: Clock },
      { name: 'تحليلات المبيعات', path: '/reports/sales-analytics', icon: PieChart },
      { name: 'ملخص المبيعات', path: '/reports/sales-summary', icon: Receipt },
    ]},
    { name: 'المرتجعات', key: 'returns', icon: Undo2, children: [
      { name: 'إدارة المرتجعات', path: '/sales/returns', icon: Undo2 },
      { name: 'سجل المرتجعات', path: '/returns/history', icon: History },
      { name: 'سندات القبض والصرف', path: '/vouchers', icon: Receipt },
    ]},
    { name: 'المشتريات', key: 'purchases', icon: ShoppingBag, children: [
      { name: 'إدارة المشتريات', path: '/purchases', icon: ShoppingBag },
      { name: 'سجل المشتريات', path: '/purchases/history', icon: History },
    ]},
    { name: 'المخزون', key: 'inventory', icon: Package2, children: [
      { name: 'إدارة المنتجات', path: '/products', icon: Package2 },
      { name: 'إدارة المخزون', path: '/inventory', icon: Warehouse },
      { name: 'إدارة الفروع', path: '/branches', icon: Building2 },
      { name: 'توزيع جماعي', path: '/branches/bulk-distribution', icon: Share2 },
      { name: 'الرصيد الافتتاحي', path: '/setup/opening-balance', icon: CreditCard },
      { name: 'ربط حسابات المخازن', path: '/settings/branches-accounting', icon: Link },
      { name: 'قيمة المخزون', path: '/reports/inventory-value', icon: TrendingUp },
      { name: 'حركة المخزون', path: '/reports/inventory-movements', icon: ArrowLeftRight },
    ]},
    { name: 'العملاء والموردين', key: 'contacts', icon: Users, children: [
      { name: 'العملاء', path: '/customers', icon: Users },
      { name: 'الموردين', path: '/suppliers', icon: Truck },
    ]},
    { name: 'المالية', key: 'finance', icon: Wallet, children: [
      { name: 'المدفوعات', path: '/payments', icon: Banknote },
      { name: 'طرق الدفع', path: '/admin/payment-methods', icon: CreditCard },
      { name: 'سندات القبض والصرف', path: '/vouchers', icon: Receipt },
      { name: 'تقرير الأرباح والخسائر', path: '/reports/profit-loss', icon: TrendingUp },
      { name: 'تقرير الصرف والقبض', path: '/reports/cash-vouchers', icon: BarChart2 },
      { name: 'الدورات المحاسبية', path: '/accounting/periods', icon: CalendarCheck },
    ]},
    { name: 'الضمان', key: 'warranty', icon: Shield, children: [
      { name: 'طلبات الضمان', path: '/warranty', icon: Shield },
    ]},
    { name: 'التقارير', key: 'reports', icon: BarChart2, children: [
      { name: 'تحليلات المبيعات', path: '/reports/sales-analytics', icon: PieChart },
      { name: 'ملخص المبيعات', path: '/reports/sales-summary', icon: Receipt },
      { name: 'تقرير الأرباح والخسائر', path: '/reports/profit-loss', icon: TrendingUp },
      { name: 'تقرير الصرف والقبض', path: '/reports/cash-vouchers', icon: Banknote },
      { name: 'حركة المخزون', path: '/reports/inventory-movements', icon: ArrowLeftRight },
      { name: 'قيمة المخزون', path: '/reports/inventory-value', icon: TrendingUp },
      { name: 'قيمة المخزون حسب الفرع', path: '/reports/inventory-value/by-branch', icon: Building2 },
      { name: 'ملخص الجلسات', path: '/reports/sessions-summary', icon: Clock },
      { name: 'التقارير المحاسبية', path: '/reports/accounting', icon: BookOpen },
      { name: 'سجل تدقيق النظام', path: '/reports/audit-logs', icon: ClipboardList },
    ]},
    { name: 'الإعدادات', key: 'settings', icon: Settings, children: [
      { name: 'الإعدادات العامة', path: '/settings/details?tab=general-settings', icon: Settings },
      { name: 'الفواتير والضرائب', path: '/settings/details?tab=invoice-settings', icon: FileText },
      { name: 'نقطة البيع (POS)', path: '/settings/details?tab=pos-settings', icon: ShoppingCart },
      { name: 'الحسابات المحاسبية', path: '/settings/details?tab=accounting-settings', icon: BarChart2 },
      { name: 'الطباعة', path: '/settings/details?tab=print-settings', icon: Printer },
      { name: 'الإشعارات', path: '/settings/details?tab=notifications', icon: Bell },
      { name: 'ربط حسابات المخازن', path: '/settings/branches-accounting', icon: Link },
    ]},
    { name: 'النظام والتدقيق', key: 'system', icon: UserCog, children: [
      { name: 'إدارة الموظفين والصلاحيات', path: '/admin/users', icon: UserCog },
      { name: 'سلامة البيانات', path: '/admin/data-integrity', icon: ShieldCheck },
      { name: 'سجل تدقيق النظام', path: '/reports/audit-logs', icon: ClipboardList },
    ]},
  ]

  if (isSuperAdmin.value) {
    items.push(
      { name: 'الاشتراكات', key: 'subscriptions', icon: CreditCard, children: [
        { name: 'الاشتراكات', path: '/admin/subscriptions', icon: CreditCard },
        { name: 'الخطط', path: '/admin/plans', icon: Layers },
      ]}
    )
  }
  return items
})

const logout = async () => {
  await authStore.logout()
  router.push('/')
}

// ✅ منطق إشعارات الاشتراك الذكية
async function loadSubscription() {
  try {
    const { useSubscriptionStore } = await import('@/stores/subscriptions/subscriptionStore')
    const subscriptionStore = useSubscriptionStore()
    await subscriptionStore.fetchSubscription()
    
    // Generate smart alerts
    const alerts = subscriptionStore.generateSubscriptionAlerts()
    
    // Add alerts to notifications if any exist
    if (alerts && alerts.length > 0) {
      alerts.forEach(alert => {
        // Check if alert doesn't already exist
        const exists = notifications.value.some(n => n.type === alert.type)
        if (!exists) {
          const newAlert = {
            id: `alert_${alert.type}_${Date.now()}`,
            title: alert.title,
            message: alert.message,
            type: alert.type,
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
    // ✅ تحسين: معالجة أفضل للأخطاء - فقط سجل في dev
    if (import.meta.env.DEV) {
      console.error('Failed to load subscription alerts:', e.message || e)
    }
    // هذا لا يوقف التطبيق - الإشعارات اختيارية
  }
}

// ✅ منطق الإشعارات - دمج مع إشعارات الاشتراك
async function loadNotifications() {
  loadingNotifications.value = true
  try {
    const response = await apiClient.get('/notifications?page=1&per_page=10')
    if (response.data.status === 'success') {
      const responseData = response.data.data || {}
      const apiNotifications = Array.isArray(responseData.notifications) ? responseData.notifications : []
      
      // Keep subscription alerts, merge with API notifications
      const subscriptionAlerts = notifications.value.filter(n => n.type?.startsWith('subscription_'))
      notifications.value = [...subscriptionAlerts, ...apiNotifications]
      
      // Calculate unread
      const currentUnread = notifications.value.filter(isUnread).length
      
      // Show toast for new notifications
      if (currentUnread > previousUnreadCount.value && currentUnread > 0) {
        const newCount = currentUnread - previousUnreadCount.value
        const message = `لديك ${newCount} إشعار${newCount > 1 ? 'ات' : ''} جديد${newCount > 1 ? 'ة' : ''}`
        showToast(message, 'info', 3000)
      }
      
      previousUnreadCount.value = currentUnread
      unreadCount.value = currentUnread
      
      // Save to localStorage for persistence
      saveNotificationsToStorage()
    }
  } catch (error) {
    // ✅ تحسين: معالجة أفضل للأخطاء
    // لو كان في خطأ في الاتصال بالـ API، لا نعرض رسالة خطأ للمستخدم
    // فقط في dev نسجل الخطأ
    if (import.meta.env.DEV) {
      console.error('Error loading notifications', error.message || error)
    }
    
    // If API call fails, that's OK - we keep the cached notifications
    // This prevents the app from breaking when notifications API is unavailable
  } finally {
    loadingNotifications.value = false
  }
}

async function markNotificationAsRead(notification) {
  try {
    if (notification.is_read) return
    
    // ✅ Don't send API request for synthetic subscription alerts
    if (notification.type?.startsWith('subscription_')) {
      notification.is_read = 1
      unreadCount.value = notifications.value.filter(isUnread).length
      previousUnreadCount.value = unreadCount.value
      saveNotificationsToStorage()
      return
    }
    
    // Only send API request for real notifications from database
    try {
      const result = await apiClient.put(`/notifications/${notification.id}/read`)
      if (result.data?.status === 'success' && result.data.data?.notification) {
        Object.assign(notification, result.data.data.notification)
      } else {
        notification.is_read = 1
      }
    } catch (apiError) {
      // If API call fails, mark as read locally anyway
      notification.is_read = 1
    }

    unreadCount.value = notifications.value.filter(isUnread).length
    previousUnreadCount.value = unreadCount.value
    
    // Update cache
    saveNotificationsToStorage()
  } catch (error) {
    // Silently fail - don't block the UI
    if (import.meta.env.DEV) {
      console.error('Error marking notification read', error.message || error)
    }
  }
}

// ✅ تحسين: حمّل الإشعارات أول مرة فقط (وليس بناءً على length)
const hasLoadedNotifications = ref(false)

function toggleNotifications() {
  showNotifications.value = !showNotifications.value
  if (showNotifications.value && !hasLoadedNotifications.value) {
    loadNotifications()
    hasLoadedNotifications.value = true
  }
}

// Watch for unread count changes
watch(unreadCount, (newCount) => {
  if (newCount === 0 && previousUnreadCount.value > 0) {
    // All notifications have been read
    previousUnreadCount.value = 0
  }
})

// ✅ إصلاح مشكلة async: استخدم for بدل forEach
async function markAllAsRead() {
  for (const note of notifications.value) {
    if (!note.is_read) {
      await markNotificationAsRead(note)
    }
  }
}

// Helper: وقت نسبي لعرض الإشعارات
const formatRelativeTime = (date) => {
  if (!date) return 'للتو'
  
  try {
    const now = new Date()
    const notifDate = new Date(date)
    
    // Check if date is valid
    if (isNaN(notifDate.getTime())) return 'للتو'
    
    const diff = now - notifDate
    const mins = Math.floor(diff / 60000)
    
    if (mins < 1) return 'للتو'
    if (mins < 60) return `منذ ${mins} د`
    
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `منذ ${hours} س`
    
    const days = Math.floor(hours / 24)
    if (days < 7) return `منذ ${days} ي`
    
    return notifDate.toLocaleDateString('ar-EG', { 
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
  // Load cached notifications first for immediate display
  loadNotificationsFromStorage()
  
  // Skip all API calls on /upgrade page (subscription expired)
  // The /upgrade page has allowExpired: true and doesn't need live updates
  if (route.path === '/upgrade') {
    return
  }
  
  // Skip loading subscription on /setup — it's not needed during onboarding
  if (route.path !== '/setup') {
    loadSubscription()
    // Load fresh notifications from API
    loadNotifications()
  }
  
  // Request notification permission on app startup (safely)
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {})
  }
  
  // ✅ إصلاح: امسح الـ interval القديم قبل إنشاء واحد جديد (منع التكرار)
  if (notificationIntervalId) clearInterval(notificationIntervalId)
  
  // ✅ تحسين: بدّل الـ polling إلى أقل تكراراً (60 ثانية بدل 30) لتقليل الحمل على الخادم
  // Poll both subscription and notifications every 60 seconds
  // (إذا كان الـ API مش موجود، هذا لا يؤثر على الـ UX لأن المحلية مخزنة)
  notificationIntervalId = setInterval(async () => {
    await loadSubscription().catch(() => {})
    await loadNotifications().catch(() => {})
  }, 60000)
})

// Cleanup on unmount
onUnmounted(() => {
  if (notificationIntervalId) {
    clearInterval(notificationIntervalId)
  }
})
</script>

<style scoped>

/* Navigation Links Styling */
.nav-link { @apply px-3 py-2.5 rounded-xl text-xs font-black text-slate-500 flex items-center gap-2 transition-all duration-200 hover:bg-slate-50 hover:text-slate-900; }
.nav-link-active { @apply bg-blue-600 text-white shadow-lg shadow-blue-100 hover:bg-blue-700 hover:text-white; }

/* Mega Dropdown Panel */
.mega-dropdown {
  @apply absolute top-full mt-2 bg-white rounded-2xl shadow-2xl border border-slate-100 z-[80] overflow-hidden;
  min-width: 220px;
  right: 0;
}

/* Dropdown Items */
.dropdown-item {
  @apply flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900 cursor-pointer;
}
.dropdown-item-active {
  @apply bg-blue-50 text-blue-600;
}
.dropdown-item-icon {
  @apply w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 shrink-0;
}
.dropdown-item:hover .dropdown-item-icon { @apply bg-blue-100 text-blue-600; }
.dropdown-item-active .dropdown-item-icon { @apply bg-blue-100 text-blue-600; }

/* Custom Scrollbar */
.custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* Modal / Drawer Animation */
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }

/* Dropdown Transition */
.dropdown-fade-enter-active { transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1); }
.dropdown-fade-leave-active { transition: all 0.12s ease; }
.dropdown-fade-enter-from { opacity: 0; transform: translateY(8px) scale(0.97); }
.dropdown-fade-leave-to { opacity: 0; transform: translateY(4px); }

/* Accordion Transition */
.accordion-enter-active, .accordion-leave-active { transition: all 0.25s ease; overflow: hidden; }
.accordion-enter-from, .accordion-leave-to { opacity: 0; max-height: 0; }
.accordion-enter-to, .accordion-leave-from { opacity: 1; max-height: 600px; }

/* Modal Fade */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* Slide Up Banner */
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100px); opacity: 0; }

/* Notification Badge Animations */
@keyframes badge-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7), 0 4px 12px rgba(220, 38, 38, 0.3);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(239, 68, 68, 0), 0 4px 12px rgba(220, 38, 38, 0.3);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0), 0 4px 12px rgba(220, 38, 38, 0.3);
  }
}

@keyframes badge-bounce-strong {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.15);
  }
}

.animate-pulse-badge {
  animation: badge-pulse 2s infinite;
}

.animate-bounce-badge {
  animation: badge-bounce-strong 1s;
}
</style>