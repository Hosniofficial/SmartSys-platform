<template>
  <div class="min-h-screen bg-[#fafafa] text-slate-900 font-sans antialiased selection:bg-blue-100" dir="rtl">
    
    <!-- Top Progress Bar -->
    <div 
      v-if="isLoading || isGLLoading || isTransfersLoading || isStockTransfersLoading || isActivatingBranch || isPostingBranch" 
      class="fixed top-0 left-0 right-0 h-0.5 bg-blue-600/10 z-[110]"
    >
      <div class="h-full bg-blue-600 animate-[loading_2s_ease-in-out_infinite] w-1/3"></div>
    </div>

    <div class="max-w-[1600px] mx-auto p-6 lg:p-10 space-y-8 animate-fadeIn">
      
      <!-- Top Navigation & Back Button -->
      <div class="flex items-center justify-between">
        <button 
          @click="router.back()" 
          class="h-9 px-3 rounded-md border border-slate-200 bg-white text-slate-700 text-xs font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm active:scale-95 group"
        >
          <i class="fas fa-arrow-right text-[10px] group-hover:-translate-x-1 transition-transform"></i>
          <span>العودة لقائمة الفروع</span>
        </button>
      </div>

      <!-- Loading Skeleton State -->
      <div v-if="isLoading" class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl p-8 h-36 animate-pulse"></div>
        <div class="grid grid-cols-1 gap-4">
          <div class="h-10 bg-slate-100 rounded-lg w-72 mx-auto animate-pulse"></div>
          <div v-for="i in 3" :key="i" class="h-20 bg-white border border-slate-200 rounded-xl animate-pulse"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="py-16 text-center px-6 bg-white border border-slate-200 rounded-xl shadow-sm max-w-md mx-auto animate-fadeIn">
        <div class="w-12 h-12 bg-rose-50 border border-rose-100 text-rose-600 rounded-xl flex items-center justify-center mx-auto mb-4 shadow-sm">
          <i class="fas fa-triangle-exclamation text-base"></i>
        </div>
        <h3 class="text-base font-bold text-slate-900">حدث خطأ في جلب بيانات الفرع</h3>
        <p class="text-xs text-slate-500 mt-1 font-medium leading-relaxed">{{ error }}</p>
        <button 
          @click="fetchBranch" 
          class="mt-5 h-9 px-6 bg-slate-900 text-white rounded-md text-xs font-bold shadow-sm hover:bg-black active:scale-95 transition-all"
        >
          إعادة المحاولة
        </button>
      </div>

      <!-- Main Branch Data View -->
      <div v-else-if="branch" class="space-y-6">
        
        <!-- Branch Profile Header Banner -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-slate-900 text-white rounded-xl flex items-center justify-center text-xl shadow-sm shrink-0">
              <i class="fas fa-building"></i>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ branch.name }}</h1>
                <span 
                  :class="[(branch.active === 1 || branch.active === true) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200']" 
                  class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[9px] font-bold border"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                  {{ (branch.active === 1 || branch.active === true) ? 'نشط الآن' : 'معطل' }}
                </span>
              </div>
              <div class="flex flex-wrap items-center gap-3 text-xs font-medium text-slate-500">
                <span class="flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-slate-400 text-[11px]"></i> {{ branch.location || 'موقع غير محدد' }}</span>
                <span class="text-slate-200">|</span>
                <span class="font-mono text-slate-400">REF_ID: #{{ branch.id }}</span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <router-link
              :to="{ path: '/branches/bulk-distribution' }"
              class="h-9 px-4 rounded-md border border-slate-200 bg-white text-slate-700 text-xs font-bold transition-all flex items-center gap-2 shadow-sm hover:bg-slate-50 active:scale-95"
            >
              <i class="fas fa-share-nodes text-blue-600 text-xs"></i>
              <span>التوزيع الجماعي</span>
            </router-link>
          </div>
        </div>

        <!-- Navigation Tabs Bar -->
        <div class="flex items-center gap-1 p-1 bg-slate-100/80 border border-slate-200 rounded-xl w-fit mx-auto sticky top-4 z-40 backdrop-blur-md">
          <button
            v-for="tab in [
              { id: 'inventory', name: 'المخزون', icon: 'boxes' },
              { id: 'transfers', name: 'عمليات النقل', icon: 'exchange-alt' },
              { id: 'stock_transfers', name: 'السجل المرجعي', icon: 'history' },
              { id: 'settings', name: 'الإعدادات', icon: 'cog' }
            ]"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[activeTab === tab.id ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900']"
            class="h-8 px-4 rounded-lg text-xs font-bold transition-all flex items-center gap-2 active:scale-95"
          >
            <i :class="`fas fa-${tab.icon} text-[10px]`"></i>
            <span>{{ tab.name }}</span>
          </button>
        </div>

        <!-- Tab Content Area -->
        <div class="min-h-[500px]">
          
          <!-- TAB 1: INVENTORY & GL STATUS -->
          <transition name="fade">
            <div v-if="activeTab === 'inventory'" class="space-y-6 animate-fadeIn">
              
              <!-- BranchInventory Component -->
              <BranchInventory 
                :branch-id="branchId"
                @inventory-updated="handleInventoryUpdated"
              />

              <!-- GL Products Section -->
              <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <div class="p-4 px-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                  <div class="flex items-center gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">حالات المنتجات (GL Status)</h3>
                  </div>
                  <button 
                    @click="loadBranchProductGLStatuses" 
                    :disabled="isGLLoading" 
                    class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-emerald-600 hover:border-emerald-200 transition-all flex items-center justify-center active:scale-90"
                    title="تحديث حالات GL"
                  >
                    <i class="fas fa-sync-alt text-[10px]" :class="{ 'animate-spin': isGLLoading }"></i>
                  </button>
                </div>

                <div v-if="isGLLoading" class="p-6 space-y-3">
                  <div v-for="i in 4" :key="i" class="flex items-center gap-4 py-2 border-b border-slate-50 animate-pulse">
                    <div class="w-8 h-8 bg-slate-100 rounded-lg"></div>
                    <div class="h-3 bg-slate-100 rounded w-1/4"></div>
                    <div class="h-3 bg-slate-100 rounded w-1/4"></div>
                  </div>
                </div>

                <div v-else-if="glError" class="py-16 text-center text-rose-600 font-bold text-xs">
                  <i class="fas fa-circle-exclamation mb-1 block text-lg"></i>
                  {{ glError }}
                </div>

                <div v-else>
                  <!-- Status Tabs Filter Bar -->
                  <div class="flex items-center gap-1.5 p-3 bg-slate-50/70 border-b border-slate-100">
                    <button
                      v-for="status in ['DRAFT', 'ACTIVE_IN_BRANCH', 'RECONCILED']"
                      :key="status"
                      @click="glStatusFilter = status"
                      :class="[
                        glStatusFilter === status ? 'bg-white border-slate-200 text-slate-900 shadow-sm' : 'bg-transparent text-slate-500 hover:bg-white hover:text-slate-700 border-transparent',
                        'px-3 py-1.5 rounded-md border text-[11px] font-bold transition-all'
                      ]"
                    >
                      <span v-if="status === 'DRAFT'" class="flex items-center gap-1.5">
                        <i class="fas fa-file-alt text-[10px]"></i> مسودة
                      </span>
                      <span v-else-if="status === 'ACTIVE_IN_BRANCH'" class="flex items-center gap-1.5">
                        <i class="fas fa-check-double text-[10px]"></i> مفعّل بالفرع
                      </span>
                      <span v-else class="flex items-center gap-1.5">
                        <i class="fas fa-circle-check text-[10px]"></i> مرصود ومحاسب
                      </span>
                    </button>
                  </div>

                  <!-- GL Products List -->
                  <div class="divide-y divide-slate-100">
                    <div 
                      v-for="product in filteredGLProducts" 
                      :key="product.product_id" 
                      class="p-4 px-6 hover:bg-slate-50/50 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
                    >
                      <div class="space-y-1">
                        <h4 class="text-xs font-bold text-slate-900">{{ product.product_name }}</h4>
                        <div class="flex items-center gap-3 text-[11px] text-slate-400 font-medium">
                          <span v-if="product.barcode" class="font-mono text-slate-500">BARCODE: {{ product.barcode }}</span>
                          <span class="text-slate-200">|</span>
                          <span class="font-mono">الكمية: <strong class="text-slate-700">{{ product.quantity || 0 }}</strong></span>
                          <span class="text-slate-200">|</span>
                          <span class="font-mono">التكلفة: <strong class="text-slate-700">{{ (product.average_cost || 0).toFixed(2) }}</strong></span>
                        </div>
                      </div>

                      <div class="flex items-center gap-2">
                        <span 
                          :class="[
                            product.activation_status === 'DRAFT' ? 'bg-slate-100 text-slate-600 border-slate-200' :
                            product.activation_status === 'ACTIVE_IN_BRANCH' ? 'bg-blue-50 text-blue-600 border-blue-100' :
                            'bg-emerald-50 text-emerald-600 border-emerald-100'
                          ]"
                          class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono border"
                        >
                          {{ 
                            product.activation_status === 'DRAFT' ? 'DRAFT' :
                            product.activation_status === 'ACTIVE_IN_BRANCH' ? 'ACTIVE' :
                            'RECONCILED'
                          }}
                        </span>

                        <button 
                          v-if="product.activation_status === 'DRAFT'"
                          @click="openBranchActivateModal(product)"
                          class="h-8 px-3 bg-blue-600 text-white rounded-md text-xs font-bold hover:bg-blue-700 transition-all active:scale-95 shadow-sm"
                        >
                          <i class="fas fa-arrow-up text-[10px] ml-1"></i> تفعيل
                        </button>

                        <button 
                          v-else-if="product.activation_status === 'ACTIVE_IN_BRANCH'"
                          @click="openBranchOpeningBalanceModal(product)"
                          class="h-8 px-3 bg-emerald-600 text-white rounded-md text-xs font-bold hover:bg-emerald-700 transition-all active:scale-95 shadow-sm"
                        >
                          <i class="fas fa-plus text-[10px] ml-1"></i> ترصيد
                        </button>

                        <span v-else class="text-[11px] font-bold text-emerald-600 flex items-center gap-1">
                          <i class="fas fa-check text-xs"></i> مرصود
                        </span>
                      </div>
                    </div>

                    <div v-if="filteredGLProducts.length === 0" class="py-16 text-center text-slate-300">
                      <i class="fas fa-inbox text-3xl mb-2 opacity-20 block"></i>
                      <p class="text-xs font-bold uppercase tracking-widest">لا توجد منتجات مسجلة في هذه الحالة</p>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </transition>

          <!-- TAB 2: TRANSFERS LOG -->
          <transition name="fade">
            <div v-if="activeTab === 'transfers'" class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm animate-fadeIn">
              <div class="p-4 px-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                  <i class="fas fa-exchange-alt text-blue-600 text-xs"></i>
                  <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">سجل عمليات نقل المنتجات</h3>
                </div>
                <div class="flex items-center gap-2">
                  <router-link
                    :to="{ path: '/inventory', query: { branch_id: branchId, action: 'transfer' } }"
                    class="h-8 px-3 bg-blue-600 text-white rounded-md text-xs font-bold shadow-sm hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-1.5"
                  >
                    <i class="fas fa-plus text-[10px]"></i>
                    <span>نقل جديد</span>
                  </router-link>
                  <button 
                    @click="fetchTransfers" 
                    :disabled="isTransfersLoading" 
                    class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-blue-600 transition-all active:scale-90 flex items-center justify-center"
                    title="تحديث السجل"
                  >
                    <i class="fas fa-sync-alt text-[10px]" :class="{ 'animate-spin': isTransfersLoading }"></i>
                  </button>
                </div>
              </div>

              <div v-if="isTransfersLoading" class="p-6 space-y-3">
                <div v-for="i in 4" :key="i" class="h-4 bg-slate-100 rounded animate-pulse"></div>
              </div>

              <div v-else-if="transfersError" class="py-16 text-center text-rose-600 font-bold text-xs">
                <i class="fas fa-triangle-exclamation mb-1 block text-lg"></i>
                {{ transfersError }}
              </div>

              <div v-else>
                <div v-if="transfers.length > 0" class="overflow-x-auto">
                  <table class="w-full text-right border-collapse">
                    <thead>
                      <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">التاريخ والوقت</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">المنتج المنقول</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">المسار (من → إلى)</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">الكمية</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">المسؤول</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">ملاحظات</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-xs">
                      <tr v-for="t in transfers" :key="t.id" class="hover:bg-blue-50/20 transition-all">
                        <td class="px-6 py-3.5 font-mono text-slate-400 text-[11px]">{{ new Date(t.created_at).toLocaleString('ar-EG') }}</td>
                        <td class="px-4 py-3.5">
                          <div class="font-bold text-slate-900">{{ t.product_name }}</div>
                          <span class="text-[9px] text-slate-400 font-mono block mt-0.5" v-if="t.barcode">{{ t.barcode }}</span>
                        </td>
                        <td class="px-4 py-3.5">
                          <div class="flex items-center gap-2 text-xs font-bold">
                            <span class="text-slate-600 truncate max-w-[120px]">{{ t.from_branch_name }}</span>
                            <i class="fas fa-arrow-left text-slate-300 text-[10px]"></i>
                            <span class="text-blue-600 truncate max-w-[120px]">{{ t.to_branch_name }}</span>
                          </div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                          <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-mono font-bold text-slate-900 text-[11px]">
                            {{ t.quantity }}
                          </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600">{{ t.created_by_name || '—' }}</td>
                        <td class="px-6 py-3.5 text-slate-400 italic max-w-xs truncate" :title="t.notes">{{ t.notes || '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-else class="py-20 text-center text-slate-300">
                  <i class="fas fa-exchange-alt text-3xl mb-2 opacity-20 block"></i>
                  <p class="text-xs font-bold uppercase tracking-widest">لا توجد عمليات نقل مسجلة</p>
                </div>
              </div>
            </div>
          </transition>

          <!-- TAB 3: STOCK TRANSFERS REF TABLE -->
          <transition name="fade">
            <div v-if="activeTab === 'stock_transfers'" class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm animate-fadeIn">
              <div class="p-4 px-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                  <i class="fas fa-history text-indigo-600 text-xs"></i>
                  <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">السجل المرجعي للنقل (Stock Transfers)</h3>
                </div>
                <button 
                  @click="fetchStockTransfers" 
                  :disabled="isStockTransfersLoading" 
                  class="w-8 h-8 rounded-lg border border-slate-200 text-slate-400 hover:text-indigo-600 transition-all flex items-center justify-center"
                  title="تحديث السجلات"
                >
                  <i class="fas fa-sync-alt text-[10px]" :class="{ 'animate-spin': isStockTransfersLoading }"></i>
                </button>
              </div>

              <div v-if="isStockTransfersLoading" class="p-6 space-y-3">
                <div v-for="i in 4" :key="i" class="h-4 bg-slate-100 rounded animate-pulse"></div>
              </div>

              <div v-else-if="stockTransfersError" class="py-16 text-center text-rose-600 font-bold text-xs">
                {{ stockTransfersError }}
              </div>

              <div v-else>
                <div v-if="stockTransfers.length > 0" class="overflow-x-auto">
                  <table class="w-full text-right border-collapse">
                    <thead>
                      <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">التاريخ</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">المنتج</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">المسار</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">الكمية</th>
                        <th class="px-4 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">بواسطة</th>
                        <th class="px-6 py-3.5 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center w-20">الإجراء</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-xs">
                      <tr v-for="t in stockTransfers" :key="t.id" class="hover:bg-blue-50/20 transition-all">
                        <td class="px-6 py-3.5 font-mono text-slate-400 text-[11px]">{{ new Date(t.created_at).toLocaleString('ar-EG') }}</td>
                        <td class="px-4 py-3.5 font-bold text-slate-900">{{ t.product_name }}</td>
                        <td class="px-4 py-3.5">
                          <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-600">
                            <span>{{ t.from_branch_name }}</span>
                            <i class="fas fa-chevron-left text-slate-300 text-[9px]"></i>
                            <span class="text-indigo-600">{{ t.to_branch_name }}</span>
                          </div>
                        </td>
                        <td class="px-4 py-3.5 text-center font-mono font-bold text-indigo-600">{{ t.quantity }}</td>
                        <td class="px-4 py-3.5 text-slate-600">{{ t.created_by_name || '—' }}</td>
                        <td class="px-6 py-3.5 text-center">
                          <button 
                            @click="openStockTransferDetails(t.id)" 
                            class="w-7 h-7 rounded-md border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all flex items-center justify-center mx-auto"
                            title="عرض التفاصيل"
                          >
                            <i class="fas fa-eye text-[10px]"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-else class="py-20 text-center text-slate-300">
                  <i class="fas fa-history text-3xl mb-2 opacity-20 block"></i>
                  <p class="text-xs font-bold uppercase tracking-widest">لا توجد سجلات مرجعية</p>
                </div>
              </div>
            </div>
          </transition>

          <!-- TAB 4: SETTINGS -->
          <transition name="fade">
            <div v-if="activeTab === 'settings'" class="bg-white border border-slate-200 rounded-xl p-6 sm:p-8 max-w-2xl mx-auto shadow-sm space-y-6 animate-fadeIn">
              <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center text-xs">
                  <i class="fas fa-sliders-h"></i>
                </div>
                <div>
                  <h3 class="text-sm font-bold text-slate-900 uppercase">إعدادات الفرع التفضيلية</h3>
                  <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">تخصيص سلوك وتنبيهات الفرع</p>
                </div>
              </div>
              
              <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                  <div class="space-y-0.5">
                    <h4 class="text-xs font-bold text-slate-900">حالة نشاط الفرع</h4>
                    <p class="text-[11px] text-slate-500">عند إلغاء التفعيل، لن يظهر هذا الفرع في شاشات المبيعات أو المشتريات.</p>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="branch.is_active" class="sr-only peer">
                    <div 
                      @click="branch.is_active = !branch.is_active"
                      :class="branch.is_active ? 'bg-blue-600' : 'bg-slate-300'"
                      class="w-10 h-5 rounded-full cursor-pointer transition-colors relative"
                    >
                      <span
                        :class="branch.is_active ? 'translate-x-[-1.25rem]' : 'translate-x-0'"
                        class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                      ></span>
                    </div>
                  </label>
                </div>

                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                  <div class="space-y-0.5">
                    <h4 class="text-xs font-bold text-slate-900">نظام تنبيهات المخزون الحرج</h4>
                    <p class="text-[11px] text-slate-500">إطلاق إشعارات آلية عند وصول أرصدة الأصناف إلى الحد الأدنى المحدد.</p>
                  </div>
                  <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked class="sr-only peer" ref="alertToggle" />
                    <div 
                      @click="alertToggle.checked = !alertToggle.checked"
                      :class="alertToggle?.checked ? 'bg-indigo-600' : 'bg-slate-300'"
                      class="w-10 h-5 rounded-full cursor-pointer transition-colors relative"
                    >
                      <span
                        :class="alertToggle?.checked ? 'translate-x-[-1.25rem]' : 'translate-x-0'"
                        class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                      ></span>
                    </div>
                  </label>
                </div>
              </div>

              <div class="pt-4 border-t border-slate-100 flex justify-end">
                <button 
                  type="button" 
                  class="h-9 px-6 bg-slate-900 text-white rounded-md font-bold text-xs shadow-sm hover:bg-black active:scale-95 transition-all flex items-center gap-2"
                >
                  <i class="fas fa-save text-[10px]"></i>
                  <span>حفظ التعديلات</span>
                </button>
              </div>
            </div>
          </transition>

        </div>
      </div>

    </div>

    <!-- ===== SYSTEM MODALS ===== -->

    <!-- Stock Transfer Details Modal -->
    <BaseModal :show="showStockTransferDetails" @close="showStockTransferDetails = false" maxWidth="3xl">
      <template #header>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center text-white text-xs">
            <i class="fas fa-file-invoice"></i>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 uppercase">تفاصيل عملية النقل المحاسبية</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">سجل التتبع والقيود المخزنية المرتبطة</p>
          </div>
        </div>
      </template>

      <div class="space-y-6">
        <div v-if="isStockTransferDetailsLoading" class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-pulse">
          <div v-for="i in 4" :key="i" class="h-16 bg-slate-100 rounded-lg"></div>
        </div>

        <template v-else-if="stockTransferDetails">
          <!-- Quick Meta Stats -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">رقم المرجع</p>
              <p class="font-bold text-slate-900 font-mono text-sm mt-0.5">#{{ stockTransferDetails.id }}</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">تاريخ النقل</p>
              <p class="font-bold text-slate-800 text-xs mt-0.5 font-mono">{{ new Date(stockTransferDetails.created_at).toLocaleDateString('ar-EG') }}</p>
            </div>
            <div class="p-3 rounded-lg bg-indigo-50 border border-indigo-100">
              <p class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest">الكمية المنقولة</p>
              <p class="font-bold text-indigo-700 text-base font-mono mt-0.5">{{ stockTransferDetails.quantity }}</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">المسؤول</p>
              <p class="font-bold text-slate-800 text-xs mt-0.5 truncate">{{ stockTransferDetails.created_by_name || '-' }}</p>
            </div>
          </div>

          <!-- Product & Route Card -->
          <div class="bg-slate-900 p-5 rounded-xl text-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <p class="text-[9px] font-bold text-blue-400 uppercase tracking-widest">بيانات الصنف</p>
              <h4 class="text-sm font-bold mt-0.5">{{ stockTransferDetails.product_name }}</h4>
              <p class="text-[10px] text-slate-400 font-mono mt-0.5" v-if="stockTransferDetails.barcode">BARCODE: {{ stockTransferDetails.barcode }}</p>
            </div>
            <div class="flex items-center gap-3 bg-white/5 p-2.5 px-4 rounded-lg border border-white/10 text-xs">
              <span class="font-bold text-slate-300">{{ stockTransferDetails.from_branch_name }}</span>
              <i class="fas fa-arrow-left text-blue-400 text-[10px]"></i>
              <span class="font-bold text-white">{{ stockTransferDetails.to_branch_name }}</span>
            </div>
          </div>

          <!-- Audit Log Table -->
          <div class="space-y-2">
            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">الحركات المخزنية المرتبطة (Audit Traceability)</h4>
            <div class="border border-slate-200 rounded-xl overflow-hidden">
              <table class="w-full text-right text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    <th class="px-4 py-2.5">الوقت</th>
                    <th class="px-3 py-2.5">النوع التقني</th>
                    <th class="px-3 py-2.5">المسار</th>
                    <th class="px-3 py-2.5 text-center">الكمية</th>
                    <th class="px-4 py-2.5">ملاحظات</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                  <tr v-for="it in stockTransferDetails.inventory_transactions" :key="it.id">
                    <td class="px-4 py-2.5 text-slate-400 font-mono text-[11px]">{{ new Date(it.created_at || it.movement_date).toLocaleTimeString('ar-EG') }}</td>
                    <td class="px-3 py-2.5 font-mono text-[10px] text-slate-600 uppercase">{{ it.movement_type }}</td>
                    <td class="px-3 py-2.5 text-slate-600">{{ it.branch_from || '-' }} ← {{ it.branch_to || '-' }}</td>
                    <td class="px-3 py-2.5 text-center font-mono font-bold text-indigo-600">{{ it.quantity }}</td>
                    <td class="px-4 py-2.5 text-slate-400 italic text-[11px]">{{ it.notes || '—' }}</td>
                  </tr>
                  <tr v-if="!(stockTransferDetails.inventory_transactions || []).length">
                    <td colspan="5" class="py-6 text-center text-slate-300 uppercase tracking-widest font-bold text-xs">لا توجد حركات تقنية مسجلة</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </template>
      </div>

      <template #footer>
        <button 
          @click="showStockTransferDetails = false" 
          class="px-6 h-9 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors"
        >
          إغلاق
        </button>
      </template>
    </BaseModal>

    <!-- GL Activate Product Modal -->
    <BaseModal :show="showBranchActivateModal" @close="showBranchActivateModal = false" maxWidth="md">
      <template #header>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white text-xs">
            <i class="fas fa-arrow-up"></i>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 uppercase">تفعيل المنتج في الفرع</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">إتاحة البيع وربط شجرة الحسابات</p>
          </div>
        </div>
      </template>

      <div class="space-y-4">
        <p class="text-xs text-slate-600 font-medium">هل تريد اعتماد وتفعيل هذا المنتج للعمل داخل هذا الفرع؟</p>
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-0.5">
          <p class="text-xs font-bold text-slate-900">{{ selectedBranchProduct?.product_name }}</p>
          <p class="text-[10px] text-slate-400 font-mono">{{ selectedBranchProduct?.barcode }}</p>
        </div>
      </div>

      <template #footer>
        <button @click="showBranchActivateModal = false" class="px-6 h-9 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors">
          إلغاء
        </button>
        <button 
          @click="activateBranchProduct" 
          :disabled="isActivatingBranch" 
          class="px-6 h-9 rounded-md bg-blue-600 text-white text-xs font-bold shadow-sm hover:bg-blue-700 transition-all active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50"
        >
          <BaseSpinner v-if="isActivatingBranch" size="14" color="#fff" />
          <span>تأكيد التفعيل</span>
        </button>
      </template>
    </BaseModal>

    <!-- GL Opening Balance Modal -->
    <BaseModal :show="showBranchOpeningBalanceModal" @close="showBranchOpeningBalanceModal = false" maxWidth="md">
      <template #header>
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white text-xs">
            <i class="fas fa-plus"></i>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 uppercase">ترصيد الرصيد الافتتاحي</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">تثبيت الكمية والتكلفة الأولية في الدفاتر</p>
          </div>
        </div>
      </template>

      <div class="space-y-3.5">
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-0.5">
          <p class="text-xs font-bold text-slate-900">{{ selectedBranchProduct?.product_name }}</p>
          <p class="text-[10px] text-slate-400 font-mono">{{ selectedBranchProduct?.barcode }}</p>
        </div>

        <div class="space-y-1.5">
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">الكمية الافتتاحية</label>
          <input v-model.number="branchObQuantity" type="number" step="0.01" min="0" placeholder="0.00" class="filter-input font-mono">
        </div>

        <div class="space-y-1.5">
          <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">سعر الوحدة (التكلفة)</label>
          <input v-model.number="branchObUnitCost" type="number" step="0.01" min="0" placeholder="0.00" class="filter-input font-mono">
        </div>

        <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-lg flex items-center justify-between text-xs font-mono">
          <span class="text-[10px] font-bold text-emerald-800 uppercase">الإجمالي المالي:</span>
          <span class="text-sm font-bold text-emerald-700">{{ (branchObQuantity * branchObUnitCost).toFixed(2) }}</span>
        </div>
      </div>

      <template #footer>
        <button @click="showBranchOpeningBalanceModal = false" class="px-6 h-9 text-xs font-bold text-slate-500 hover:text-slate-900 transition-colors">
          إلغاء
        </button>
        <button 
          @click="handleBranchOpeningBalanceSubmit" 
          :disabled="isPostingBranch || !branchObQuantity || !branchObUnitCost" 
          class="px-6 h-9 rounded-md bg-emerald-600 text-white text-xs font-bold shadow-sm hover:bg-emerald-700 transition-all active:scale-95 flex items-center justify-center gap-2 disabled:opacity-50"
        >
          <BaseSpinner v-if="isPostingBranch" size="14" color="#fff" />
          <span>تأكيد الترصيد</span>
        </button>
      </template>
    </BaseModal>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, defineEmits, watch } from 'vue';
import BaseModal from '@/components/BaseModal.vue';
import { useRoute, useRouter } from 'vue-router';
import getLocalDateISO from '@/utils/date';
import { useToast } from '@/composables/useToast';
import BranchInventory from '@/components/branch/BranchInventory.vue';
import BaseSpinner from '@/components/ui/BaseSpinner.vue';
import BaseSkeleton from '@/components/ui/BaseSkeleton.vue';
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useProductStore } from '@/stores/product/productStore';

// --- Services & Router ---
const route = useRoute();
const router = useRouter();
const { showToast } = useToast();
const emit = defineEmits(['inventory-updated']);
const authStore = useAuthStore();
const branchStore = useBranchStore();
const productStore = useProductStore();

// --- State (ALL ORIGINAL REFS PRESERVED) ---
const branch = ref(null);
const isLoading = ref(true);
const error = ref(null);
const activeTab = ref('inventory');
const branchId = parseInt(route.params.id);

// GL Products Data & Modals (محفوظ كاملاً)
const glProducts = ref([]);
const isGLLoading = ref(false);
const glError = ref(null);
const glStatusFilter = ref('DRAFT');
const showBranchActivateModal = ref(false);
const showBranchOpeningBalanceModal = ref(false);
const selectedBranchProduct = ref(null);
const branchObQuantity = ref(0);
const branchObUnitCost = ref(0);
const isActivatingBranch = ref(false);
const isPostingBranch = ref(false);

// Transfers Data
const transfers = ref([]);
const isTransfersLoading = ref(false);
const transfersError = ref(null);

// Stock Transfers Reference
const stockTransfers = ref([]);
const isStockTransfersLoading = ref(false);
const stockTransfersError = ref(null);

// Transfer Details Modal
const showStockTransferDetails = ref(false);
const isStockTransferDetailsLoading = ref(false);
const stockTransferDetails = ref(null);

// --- Computed Properties ---
const filteredGLProducts = computed(() => {
  return glProducts.value.filter(p => p.activation_status === glStatusFilter.value);
});

// --- GL Integration Methods (محفوظة كاملاً) ---
const loadBranchProductGLStatuses = async () => {
  if (!branchId) return;
  isGLLoading.value = true;
  glError.value = null;
  try {
    const prods = await productStore.fetchGLStatus({ branchId });
    glProducts.value = prods || [];
  } catch (err) {
    glError.value = 'فشل تحميل حالات المنتجات';
    console.warn('GL status load failed:', err);
  } finally {
    isGLLoading.value = false;
  }
};

const openBranchActivateModal = (product) => {
  selectedBranchProduct.value = product;
  showBranchActivateModal.value = true;
};

const openBranchOpeningBalanceModal = (product) => {
  selectedBranchProduct.value = product;
  branchObQuantity.value = 0;
  branchObUnitCost.value = 0;
  showBranchOpeningBalanceModal.value = true;
};

const activateBranchProduct = async () => {
  if (!selectedBranchProduct.value) return;
  isActivatingBranch.value = true;
  try {
    const res = await productStore.activateProductInBranch(selectedBranchProduct.value.product_id, branchId);
    if (res.status === 'success') {
      showToast('تم التفعيل بنجاح', 'success');
      showBranchActivateModal.value = false;
      await loadBranchProductGLStatuses();
    } else {
      showToast(res.message || 'فشل التفعيل', 'error');
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'فشل التفعيل', 'error');
  } finally {
    isActivatingBranch.value = false;
  }
};

const handleBranchOpeningBalanceSubmit = async () => {
  if (!selectedBranchProduct.value || !branchObQuantity.value || !branchObUnitCost.value) {
    showToast('يرجى ملء جميع الحقول', 'error');
    return;
  }
  isPostingBranch.value = true;
  try {
    const res = await productStore.postOpeningBalance({
      mapping_id: selectedBranchProduct.value.mapping_id || selectedBranchProduct.value.product_id,
      branch_id: Number(branchId),
      quantity: branchObQuantity.value,
      unit_cost: branchObUnitCost.value,
      entry_date: getLocalDateISO()
    });
    if (res.status === 'success') {
      showToast('تم الترصيد بنجاح', 'success');
      showBranchOpeningBalanceModal.value = false;
      await loadBranchProductGLStatuses();
    } else {
      showToast(res.message || 'فشل الترصيد', 'error');
    }
  } catch (err) {
    showToast(err.response?.data?.message || 'فشل الترصيد', 'error');
  } finally {
    isPostingBranch.value = false;
  }
};

// --- API Methods (STRICTLY PRESERVED) ---
const fetchBranch = async (branchesData) => {
  isLoading.value = true;
  error.value = null;
  try {
    const list = branchesData ?? await branchStore.fetchBranches();
    const found = list?.find(b => b.id === branchId);
    if (found) {
      branch.value = found;
    } else {
      throw new Error('فشل في تحميل تفاصيل الفرع');
    }
  } catch (err) {
    error.value = 'حدث خطأ أثناء جلب بيانات الفرع';
    showToast(error.value, 'error');
  } finally {
    isLoading.value = false;
  }
};

const fetchTransfers = async (branchesData) => {
  if (!branchId) return;
  isTransfersLoading.value = true;
  transfersError.value = null;
  try {
    const list = branchesData ?? await branchStore.fetchBranches();
    const branchData = list?.find(b => b.id === branchId);
    transfers.value = branchData?.transfers || [];
  } catch (err) {
    transfersError.value = err.message || 'حدث خطأ أثناء جلب سجل عمليات النقل';
  } finally {
    isTransfersLoading.value = false;
  }
};

const fetchStockTransfers = async (branchesData) => {
  if (!branchId) return;
  isStockTransfersLoading.value = true;
  stockTransfersError.value = null;
  try {
    const list = branchesData ?? await branchStore.fetchBranches();
    const branchData = list?.find(b => b.id === branchId);
    stockTransfers.value = branchData?.stock_transfers || [];
  } catch (err) {
    stockTransfersError.value = err.message || 'حدث خطأ أثناء جلب عمليات النقل المرجعية';
  } finally {
    isStockTransfersLoading.value = false;
  }
};

const openStockTransferDetails = async (id) => {
  if (!id) return;
  isStockTransferDetailsLoading.value = true;
  stockTransferDetails.value = null;
  showStockTransferDetails.value = true;
  try {
    const branches = await branchStore.fetchBranches();
    const branchData = branches?.find(b => b.id === branchId);
    stockTransferDetails.value = branchData?.stock_transfers?.find(t => t.id === id) || null;
  } catch (err) {
    showToast(err.message || 'حدث خطأ أثناء جلب التفاصيل', 'error');
  } finally {
    isStockTransferDetailsLoading.value = false;
  }
};

// --- Handlers (PRESERVED) ---
const handleInventoryUpdated = () => {
  showToast('تم تحديث المخزون بنجاح', 'success');
};

// --- Watchers & Lifecycle ---
onMounted(async () => {
  await branchStore.initialize();

  const branchIdFromUrl = parseInt(branchId);
  if (!branchStore.hasAccessToBranch(branchIdFromUrl)) {
    error.value = 'ليس لديك صلاحية للوصول إلى هذا الفرع';
    isLoading.value = false;
    return;
  }

  branchStore.setSelectedBranch(branchIdFromUrl);

  const branchesData = await branchStore.fetchBranches();
  await Promise.all([
    fetchBranch(branchesData),
    fetchTransfers(branchesData),
    fetchStockTransfers(branchesData),
    loadBranchProductGLStatuses(),
  ]);
});

watch(activeTab, (tab) => {
  if (tab === 'transfers') fetchTransfers();
  if (tab === 'stock_transfers') fetchStockTransfers();
});
</script>

<style scoped>
@keyframes loading {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}

.filter-input {
  @apply h-9 w-full bg-white border border-slate-200 rounded-md px-3 text-[11px] font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/10 focus:border-blue-500 outline-none transition-all;
}

.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>