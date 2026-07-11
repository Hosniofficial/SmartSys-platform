<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Top Breadcrumb & Back -->
    <div class="mb-6">
        <button @click="router.back()" class="flex items-center gap-2 text-xs font-black text-slate-400 hover:text-blue-600 transition-all group uppercase tracking-widest">
            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            <span>العودة لقائمة الفروع</span>
        </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="space-y-8">
        <div class="bg-white rounded-[2.5rem] shadow-sm p-8 h-40 animate-pulse border border-slate-50"></div>
        <div class="grid grid-cols-1 gap-6">
            <div class="w-full h-12 bg-slate-100 rounded-2xl animate-pulse"></div>
            <div v-for="i in 3" :key="i" class="w-full h-20 bg-white rounded-[2rem] animate-pulse border border-slate-50"></div>
        </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="py-20 text-center px-6 bg-white rounded-[3rem] shadow-sm border border-slate-100 max-w-2xl mx-auto animate-fadeIn">
        <div class="w-20 h-20 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
          <i class="fas fa-exclamation-triangle text-3xl"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800">حدث خطأ في النظام</h3>
        <p class="text-slate-400 text-sm mt-2 font-bold">{{ error }}</p>
        <button @click="fetchBranch" class="mt-6 px-8 py-3 bg-blue-600 text-white rounded-2xl font-black text-xs shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all">إعادة المحاولة</button>
    </div>

    <!-- Data Loaded State -->
    <div v-else-if="branch" class="space-y-8">
        
        <!-- Branch Header Profile -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-8 md:p-10 relative overflow-hidden group">
            <div class="absolute top-0 left-0 w-40 h-40 bg-blue-50/50 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 bg-blue-600 rounded-[2rem] flex items-center justify-center text-3xl shadow-xl shadow-blue-100 text-white shrink-0">
                       <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 leading-none tracking-tight">{{ branch.name }}</h1>
                        <div class="flex flex-wrap items-center gap-4 mt-3 text-xs font-bold text-slate-400 uppercase tracking-widest">
                            <span class="flex items-center gap-2"><i class="fas fa-map-marker-alt text-blue-500"></i> {{ branch.location || 'موقع غير محدد' }}</span>
                            <span class="hidden md:inline text-slate-200">|</span>
                            <span class="flex items-center gap-2"><i class="fas fa-fingerprint text-blue-500 text-[10px]"></i> ID: {{ branch.id }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 bg-slate-50 p-2 rounded-2xl border border-slate-100">
                  <span :class="[(branch.active === 1 || branch.active === true) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500']" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-[0.1em]">
                      {{ (branch.active === 1 || branch.active === true) ? 'نشط الآن' : 'غير نشط' }}
                  </span>
                  <router-link
                    :to="{ path: '/branches/bulk-distribution' }"
                    class="h-10 px-5 rounded-xl bg-white border border-slate-200 text-[10px] font-black text-slate-600 flex items-center gap-2 hover:bg-slate-50 transition-all shadow-sm active:scale-95"
                  >
                    <i class="fas fa-share-alt text-blue-500"></i>
                    توزيع جماعي
                  </router-link>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 p-1.5 bg-white rounded-2xl border border-slate-100 shadow-sm w-fit mx-auto sticky top-4 z-40 backdrop-blur-md bg-white/90">
            <button
                v-for="tab in [
                    { id: 'inventory', name: 'المخزون', icon: 'boxes' },
                    { id: 'transfers', name: 'عمليات النقل', icon: 'exchange-alt' },
                    { id: 'stock_transfers', name: 'سجل المراجع', icon: 'history' },
                    { id: 'settings', name: 'الإعدادات', icon: 'cog' }
                ]"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[activeTab === tab.id ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-500 hover:bg-slate-50']"
                class="tab-pill"
            >
                <i :class="`fas fa-${tab.icon} text-[10px]`"></i>
                <span>{{ tab.name }}</span>
            </button>
        </div>

        <!-- Tab Content Area -->
        <div class="min-h-[500px]">
            <!-- Content: Inventory -->
            <transition name="fade">
                <div v-if="activeTab === 'inventory'" class="space-y-8 animate-fadeIn">
                    <!-- BranchInventory widget — request-transfer حُذف لأن النقل انتقل لـ InventoryManagement -->
                    <BranchInventory 
                        :branch-id="branchId"
                        @inventory-updated="handleInventoryUpdated"
                    />

                    <!-- GL Products Section (محفوظ كاملاً — عرض GL من منظور الفرع) -->
                    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
                            <div class="flex items-center gap-3">
                                <span class="w-1.5 h-6 bg-emerald-600 rounded-full"></span>
                                <h3 class="font-black text-slate-800 uppercase tracking-tight">حالات المنتجات (GL Status)</h3>
                            </div>
                            <button @click="loadBranchProductGLStatuses" :disabled="isGLLoading" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-emerald-600 transition-all active:scale-90">
                                <i class="fas fa-sync-alt" :class="{ 'animate-spin': isGLLoading }"></i>
                            </button>
                        </div>

                        <div v-if="isGLLoading" class="p-6 space-y-4">
                            <div v-for="i in 4" :key="i" class="flex items-center gap-4 py-3 border-b border-slate-50">
                                <BaseSkeleton type="circle" size="sm" animation="shimmer" />
                                <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
                                <BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" />
                                <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
                            </div>
                        </div>
                        <div v-else-if="glError" class="py-20 text-center text-rose-500 font-bold">
                            <i class="fas fa-exclamation-circle mb-2 block text-2xl"></i>
                            {{ glError }}
                        </div>
                        <div v-else>
                            <!-- Status Tabs -->
                            <div class="flex items-center gap-2 p-3 bg-slate-50/50 border-b border-slate-100 overflow-x-auto">
                                <button
                                    v-for="status in ['DRAFT', 'ACTIVE_IN_BRANCH', 'RECONCILED']"
                                    :key="status"
                                    @click="glStatusFilter = status"
                                    :class="[
                                        glStatusFilter === status ? 'bg-white border-slate-300 text-slate-900 shadow-sm' : 'bg-slate-50 text-slate-500 border-transparent',
                                        'px-4 py-2 rounded-xl border text-xs font-black uppercase transition-all'
                                    ]"
                                >
                                    <span v-if="status === 'DRAFT'" class="flex items-center gap-2">
                                        <i class="fas fa-file-alt"></i> مسودة
                                    </span>
                                    <span v-else-if="status === 'ACTIVE_IN_BRANCH'" class="flex items-center gap-2">
                                        <i class="fas fa-checkbox"></i> مفعّل
                                    </span>
                                    <span v-else class="flex items-center gap-2">
                                        <i class="fas fa-check-circle"></i> مرصود
                                    </span>
                                </button>
                            </div>

                            <!-- Products List by Status -->
                            <div class="divide-y divide-slate-50">
                                <div v-for="product in filteredGLProducts" :key="product.product_id" class="p-6 hover:bg-slate-50/50 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-black text-slate-800">{{ product.product_name }}</h4>
                                        <div class="flex items-center gap-4 mt-2 text-xs text-slate-400 font-bold">
                                            <span v-if="product.barcode" class="font-mono">{{ product.barcode }}</span>
                                            <span class="flex items-center gap-2">
                                                <i class="fas fa-warehouse"></i> الكمية: {{ product.quantity || 0 }}
                                            </span>
                                            <span class="flex items-center gap-2">
                                                <i class="fas fa-coins"></i> التكلفة: {{ (product.average_cost || 0).toFixed(2) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row items-end sm:items-center gap-3">
                                        <span 
                                            :class="[
                                                product.activation_status === 'DRAFT' ? 'bg-slate-100 text-slate-600' :
                                                product.activation_status === 'ACTIVE_IN_BRANCH' ? 'bg-blue-100 text-blue-600' :
                                                'bg-emerald-100 text-emerald-600'
                                            ]"
                                            class="px-4 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap"
                                        >
                                            {{ 
                                                product.activation_status === 'DRAFT' ? 'مسودة' :
                                                product.activation_status === 'ACTIVE_IN_BRANCH' ? 'مفعّل' :
                                                'مرصود ✓'
                                            }}
                                        </span>

                                        <button 
                                            v-if="product.activation_status === 'DRAFT'"
                                            @click="openBranchActivateModal(product)"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-black hover:bg-blue-700 transition-all active:scale-95 shadow-md shadow-blue-100"
                                        >
                                            <i class="fas fa-arrow-up ml-1"></i> تفعيل
                                        </button>

                                        <button 
                                            v-else-if="product.activation_status === 'ACTIVE_IN_BRANCH'"
                                            @click="openBranchOpeningBalanceModal(product)"
                                            class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-black hover:bg-emerald-700 transition-all active:scale-95 shadow-md shadow-emerald-100"
                                        >
                                            <i class="fas fa-plus-circle ml-1"></i> ترصيد
                                        </button>

                                        <span v-else class="px-4 py-2 bg-emerald-50 text-emerald-600 rounded-xl text-xs font-black">
                                            <i class="fas fa-check-circle ml-1"></i> مرصود
                                        </span>
                                    </div>
                                </div>

                                <div v-if="filteredGLProducts.length === 0" class="py-20 text-center opacity-20 text-slate-400">
                                    <i class="fas fa-inbox text-6xl mb-4"></i>
                                    <p class="font-black text-sm uppercase tracking-widest">لا توجد منتجات في هذه الحالة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content: Transfers Log -->
                <div v-else-if="activeTab === 'transfers'" class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fadeIn">
                    <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
                        <div class="flex items-center gap-3">
                          <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
                          <h3 class="font-black text-slate-800 uppercase tracking-tight">سجل عمليات نقل المنتجات</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- نقل جديد: يفتح صفحة إدارة المخزون مع branch_id وaction=transfer -->
                            <router-link
                                :to="{ path: '/inventory', query: { branch_id: branchId, action: 'transfer' } }"
                                class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2"
                            >
                                <i class="fas fa-plus"></i> نقل جديد
                            </router-link>
                            <button @click="fetchTransfers" :disabled="isTransfersLoading" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all active:scale-90">
                                <i class="fas fa-sync-alt" :class="{ 'animate-spin': isTransfersLoading }"></i>
                            </button>
                        </div>
                    </div>

                    <div v-if="isTransfersLoading" class="p-6 space-y-4">
                        <div v-for="i in 4" :key="i" class="flex items-center gap-4 py-3 border-b border-slate-50">
                            <BaseSkeleton type="circle" size="sm" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="5rem" animation="shimmer" />
                        </div>
                    </div>
                    <div v-else-if="transfersError" class="py-20 text-center text-rose-500 font-bold">
                        <i class="fas fa-exclamation-circle mb-2 block text-2xl"></i>
                        {{ transfersError }}
                    </div>
                    <div v-else>
                        <div v-if="transfers.length > 0" class="overflow-x-auto">
                            <table class="w-full text-right text-sm">
                                <thead>
                                    <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                                        <th class="px-6 py-5">التاريخ والوقت</th>
                                        <th class="px-4 py-5">المنتج المنقول</th>
                                        <th class="px-4 py-5">المسار (من → إلى)</th>
                                        <th class="px-4 py-5 text-center">الكمية</th>
                                        <th class="px-4 py-5">المسؤول</th>
                                        <th class="px-6 py-5">ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 font-bold">
                                    <tr v-for="t in transfers" :key="t.id" class="hover:bg-slate-50/50 transition-all">
                                        <td class="px-6 py-4 text-xs text-slate-400 font-mono tracking-tighter">{{ new Date(t.created_at).toLocaleString('ar-EG') }}</td>
                                        <td class="px-4 py-4">
                                            <div class="font-black text-slate-800 leading-none">{{ t.product_name }}</div>
                                            <span class="text-[10px] text-slate-300 font-mono mt-1 block uppercase" v-if="t.barcode">BARCODE: {{ t.barcode }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-2 text-xs font-black text-slate-500">
                                                <span class="truncate max-w-[100px]">{{ t.from_branch_name }}</span>
                                                <i class="fas fa-long-arrow-alt-left text-blue-500"></i>
                                                <span class="truncate max-w-[100px] text-blue-600">{{ t.to_branch_name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="px-3 py-1 bg-slate-100 rounded-lg text-slate-900 font-mono font-black text-xs">{{ t.quantity }}</span>
                                        </td>
                                        <td class="px-4 py-4 text-xs font-black text-slate-600">{{ t.created_by_name || '—' }}</td>
                                        <td class="px-6 py-4 text-xs text-slate-400 italic max-w-xs truncate" :title="t.notes">{{ t.notes || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-24 text-center opacity-20 text-slate-400">
                            <i class="fas fa-exchange-alt text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">لا توجد عمليات نقل مسجلة</p>
                        </div>
                    </div>
                </div>

                <!-- Content: Stock Transfers (Ref Table) -->
                <div v-else-if="activeTab === 'stock_transfers'" class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden animate-fadeIn">
                    <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                        <div class="flex items-center gap-3">
                          <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
                          <h3 class="font-black text-slate-800 uppercase tracking-tight">السجل المرجعي للنقل (Stock Transfers)</h3>
                        </div>
                        <button @click="fetchStockTransfers" :disabled="isStockTransfersLoading" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-indigo-600 transition-all">
                            <i class="fas fa-sync-alt" :class="{ 'animate-spin': isStockTransfersLoading }"></i>
                        </button>
                    </div>

                    <div v-if="isStockTransfersLoading" class="p-6 space-y-4">
                        <div v-for="i in 4" :key="i" class="flex items-center gap-4 py-3 border-b border-slate-50">
                            <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="8rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="10rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="4rem" animation="shimmer" />
                            <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
                        </div>
                    </div>
                    <div v-else-if="stockTransfersError" class="py-20 text-center text-rose-500 font-bold">{{ stockTransfersError }}</div>
                    <div v-else>
                        <div v-if="stockTransfers.length > 0" class="overflow-x-auto">
                            <table class="w-full text-right text-sm">
                                <thead>
                                    <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                                        <th class="px-6 py-5">التاريخ</th>
                                        <th class="px-4 py-5">المنتج</th>
                                        <th class="px-4 py-5">المسار</th>
                                        <th class="px-4 py-5 text-center">الكمية</th>
                                        <th class="px-4 py-5">بواسطة</th>
                                        <th class="px-6 py-5 text-center">الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 font-bold">
                                    <tr v-for="t in stockTransfers" :key="t.id" class="hover:bg-slate-50/50 transition-all">
                                        <td class="px-6 py-4 text-xs text-slate-400 font-mono tracking-tighter">{{ new Date(t.created_at).toLocaleString('ar-EG') }}</td>
                                        <td class="px-4 py-4 text-slate-800 font-black text-xs">{{ t.product_name }}</td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-2 text-[10px] uppercase font-black text-slate-400">
                                                <span>{{ t.from_branch_name }}</span>
                                                <i class="fas fa-chevron-left text-indigo-400"></i>
                                                <span>{{ t.to_branch_name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-center font-black text-indigo-600">{{ t.quantity }}</td>
                                        <td class="px-4 py-4 text-xs font-black text-slate-500">{{ t.created_by_name || '—' }}</td>
                                        <td class="px-6 py-4 text-center">
                                            <button @click="openStockTransferDetails(t.id)" class="px-4 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                                <i class="fas fa-eye ml-1"></i> التفاصيل
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-24 text-center opacity-20 text-slate-400">
                            <i class="fas fa-history text-6xl mb-4"></i>
                            <p class="font-black text-sm uppercase tracking-widest">لا توجد سجلات مرجعية</p>
                        </div>
                    </div>
                </div>

                <!-- Content: Settings -->
                <div v-else-if="activeTab === 'settings'" class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-10 animate-fadeIn max-w-3xl mx-auto">
                    <div class="flex items-center gap-4 mb-10 border-b border-slate-50 pb-6">
                        <div class="w-12 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center text-xl"><i class="fas fa-sliders-h"></i></div>
                        <div>
                            <h3 class="text-xl font-black text-slate-900 leading-none">إعدادات الفرع التفضيلية</h3>
                            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest">تخصيص سلوك وتنبيهات الفرع</p>
                        </div>
                    </div>
                    
                    <div class="space-y-10">
                        <div class="flex items-center justify-between p-6 bg-slate-50 rounded-[1.5rem] border border-slate-100 transition-all hover:border-blue-200">
                            <div class="max-w-md">
                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">حالة الفرع الحالية</h4>
                                <p class="text-xs text-slate-500 mt-2 font-bold leading-relaxed italic">عند إلغاء التفعيل، لن يظهر هذا الفرع في قوائم البيع أو المشتريات.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="branch.is_active" class="sr-only peer">
                                <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-6 bg-slate-50 rounded-[1.5rem] border border-slate-100 transition-all hover:border-blue-200">
                            <div class="max-w-md">
                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">نظام تنبيهات المخزون</h4>
                                <p class="text-xs text-slate-500 mt-2 font-bold leading-relaxed italic">تفعيل الإشعارات التلقائية عند وصول المنتجات للحد الأدنى المسموح به.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-12 pt-8 border-t border-slate-50 flex justify-end">
                        <button type="button" class="px-10 py-3.5 bg-slate-900 text-white rounded-2xl font-black text-sm shadow-xl shadow-slate-200 hover:bg-black transition-all active:scale-95 flex items-center gap-3">
                            <i class="fas fa-save"></i> حفظ كافة التغييرات
                        </button>
                    </div>
                </div>
            </transition>
        </div>
    </div>

    <!-- Stock Transfer Details Modal (محفوظ — عرض سجل النقل المرجعي) -->
    <transition name="modal">
      <div v-if="showStockTransferDetails" class="modal-overlay">
        <div class="modal-content-modern max-w-4xl animate-modalIn">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-3 text-indigo-600">
               <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center shadow-sm"><i class="fas fa-file-invoice"></i></div>
               <h3 class="text-xl font-black text-slate-800 leading-none">تفاصيل عملية النقل المحاسبية</h3>
            </div>
            <button @click="showStockTransferDetails = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>
          
          <div class="p-8 overflow-y-auto custom-scroll max-h-[80vh]">
            <div v-if="isStockTransferDetailsLoading" class="space-y-6">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="i in 4" :key="i" class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                  <BaseSkeleton type="text" size="xs" width="4rem" animation="shimmer" />
                  <BaseSkeleton type="text" size="sm" width="6rem" animation="shimmer" />
                </div>
              </div>
            </div>

            <template v-else-if="stockTransferDetails">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">المرجع (ID)</p>
                  <p class="font-black text-slate-800 font-mono tracking-widest text-base">#{{ stockTransferDetails.id }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">تاريخ النقل</p>
                  <p class="font-black text-slate-800 text-xs">{{ new Date(stockTransferDetails.created_at).toLocaleString('en-US') }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-100 flex flex-col justify-center">
                  <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-1">الكمية الإجمالية</p>
                  <p class="font-black text-indigo-600 text-xl font-mono leading-none">{{ stockTransferDetails.quantity }}</p>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col justify-center">
                  <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">تمت بواسطة</p>
                  <p class="font-black text-slate-800 text-xs">{{ stockTransferDetails.created_by_name || '-' }}</p>
                </div>
              </div>

              <div class="bg-slate-900 p-6 rounded-[2rem] text-white shadow-xl mb-10 border border-slate-800 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-white/5 rounded-full translate-x-8 -translate-y-8"></div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-1">المنتج المنقول</p>
                        <h4 class="text-xl font-black">{{ stockTransferDetails.product_name }}</h4>
                        <p class="text-xs text-white/40 font-mono mt-1" v-if="stockTransferDetails.barcode">Barcode: {{ stockTransferDetails.barcode }}</p>
                    </div>
                    <div class="flex items-center gap-4 bg-white/5 p-4 rounded-2xl border border-white/5">
                        <div class="text-center px-4">
                            <p class="text-[8px] font-black text-white/30 uppercase mb-1">من مستودع</p>
                            <p class="text-xs font-black">{{ stockTransferDetails.from_branch_name }}</p>
                        </div>
                        <div class="text-blue-400"><i class="fas fa-exchange-alt"></i></div>
                        <div class="text-center px-4">
                            <p class="text-[8px] font-black text-white/30 uppercase mb-1">إلى مستودع</p>
                            <p class="text-xs font-black">{{ stockTransferDetails.to_branch_name }}</p>
                        </div>
                    </div>
                </div>
              </div>

              <h4 class="text-xs font-black text-slate-900 uppercase tracking-[0.2em] mb-4 px-2 flex items-center gap-2">
                <i class="fas fa-stream text-indigo-500"></i> الحركات المخزنية المرتبطة (Traceability)
              </h4>
              <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-right text-xs">
                  <thead>
                    <tr class="bg-slate-50/50 text-slate-500 font-black uppercase tracking-tighter border-b border-slate-50">
                      <th class="px-6 py-4">وقت الحركة</th>
                      <th class="px-4 py-4">النوع التقني</th>
                      <th class="px-4 py-4">من → إلى</th>
                      <th class="px-4 py-4 text-center">الكمية</th>
                      <th class="px-6 py-4">الملاحظات البرمجية</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50 font-bold">
                    <tr v-for="it in stockTransferDetails.inventory_transactions" :key="it.id" class="hover:bg-slate-50/50 transition-all">
                      <td class="px-6 py-4 text-slate-400 font-mono tracking-tighter">{{ new Date(it.created_at || it.movement_date).toLocaleString('en-US') }}</td>
                      <td class="px-4 py-4 uppercase text-[10px] text-slate-600 tracking-widest">{{ it.movement_type }}</td>
                      <td class="px-4 py-4 text-slate-400">
                          <span class="truncate max-w-[80px] inline-block">{{ it.branch_from || '-' }}</span> 
                          <i class="fas fa-caret-left mx-1 text-slate-300"></i>
                          <span class="truncate max-w-[80px] inline-block">{{ it.branch_to || '-' }}</span>
                      </td>
                      <td class="px-4 py-4 text-center font-black">{{ it.quantity }}</td>
                      <td class="px-6 py-4 text-slate-400 italic">{{ it.notes || '—' }}</td>
                    </tr>
                    <tr v-if="!(stockTransferDetails.inventory_transactions || []).length">
                       <td colspan="5" class="py-10 text-center text-slate-300 uppercase tracking-widest font-black opacity-30">لا توجد حركات تقنية مسجلة</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
          
          <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex justify-end">
            <button @click="showStockTransferDetails = false" class="px-8 py-3 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-all active:scale-95 shadow-sm">إغلاق النافذة</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- GL Activate Product Modal (محفوظ) -->
    <transition name="modal">
      <div v-if="showBranchActivateModal" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-blue-50/50">
            <div class="flex items-center gap-3 text-blue-600">
              <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-arrow-up"></i></div>
              <h3 class="text-lg font-black text-slate-800">تفعيل المنتج</h3>
            </div>
            <button @click="showBranchActivateModal = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>
          <div class="p-8">
            <p class="text-slate-600 font-bold mb-6">هل تريد تفعيل هذا المنتج في الفرع؟</p>
            <div class="bg-slate-50 p-4 rounded-2xl mb-6 border border-slate-100">
              <p class="text-sm font-black text-slate-700">{{ selectedBranchProduct?.product_name }}</p>
              <p class="text-xs text-slate-500 mt-1 font-bold">{{ selectedBranchProduct?.barcode }}</p>
            </div>
            <div class="flex gap-3 justify-end">
              <button @click="showBranchActivateModal = false" class="px-6 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-600 hover:bg-slate-50 transition-all">إلغاء</button>
              <button @click="activateBranchProduct" :disabled="isActivatingBranch" class="px-6 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-black hover:bg-blue-700 transition-all active:scale-95 disabled:opacity-50">
                <i v-if="!isActivatingBranch" class="fas fa-arrow-up ml-1"></i>
                <i v-else class="fas fa-spinner animate-spin ml-1"></i>
                {{ isActivatingBranch ? 'جاري التفعيل...' : 'تفعيل' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- GL Opening Balance Modal (محفوظ) -->
    <transition name="modal">
      <div v-if="showBranchOpeningBalanceModal" class="modal-overlay">
        <div class="modal-content-modern max-w-md animate-modalIn">
          <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between bg-emerald-50/50">
            <div class="flex items-center gap-3 text-emerald-600">
              <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center"><i class="fas fa-plus-circle"></i></div>
              <h3 class="text-lg font-black text-slate-800">ترصيد الرصيد الافتتاحي</h3>
            </div>
            <button @click="showBranchOpeningBalanceModal = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>
          <div class="p-8">
            <div class="bg-slate-50 p-4 rounded-2xl mb-6 border border-slate-100">
              <p class="text-sm font-black text-slate-700">{{ selectedBranchProduct?.product_name }}</p>
              <p class="text-xs text-slate-500 mt-1 font-bold">{{ selectedBranchProduct?.barcode }}</p>
            </div>
            <div class="space-y-4 mb-6">
              <div>
                <label class="block text-xs font-black text-slate-600 uppercase mb-2">الكمية</label>
                <input v-model.number="branchObQuantity" type="number" step="0.01" min="0" placeholder="أدخل الكمية" class="w-full h-11 bg-white border border-slate-200 rounded-xl px-4 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-50 text-sm font-bold">
              </div>
              <div>
                <label class="block text-xs font-black text-slate-600 uppercase mb-2">سعر الوحدة</label>
                <input v-model.number="branchObUnitCost" type="number" step="0.01" min="0" placeholder="أدخل السعر" class="w-full h-11 bg-white border border-slate-200 rounded-xl px-4 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-50 text-sm font-bold">
              </div>
              <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <p class="text-[10px] font-black text-emerald-600 uppercase mb-1">الإجمالي</p>
                <p class="text-lg font-black text-emerald-700">{{ (branchObQuantity * branchObUnitCost).toFixed(2) }}</p>
              </div>
            </div>
            <div class="flex gap-3 justify-end">
              <button @click="showBranchOpeningBalanceModal = false" class="px-6 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-black text-slate-600 hover:bg-slate-50 transition-all">إلغاء</button>
              <button @click="handleBranchOpeningBalanceSubmit" :disabled="isPostingBranch || !branchObQuantity || !branchObUnitCost" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-black hover:bg-emerald-700 transition-all active:scale-95 disabled:opacity-50">
                <i v-if="!isPostingBranch" class="fas fa-check-circle ml-1"></i>
                <i v-else class="fas fa-spinner animate-spin ml-1"></i>
                {{ isPostingBranch ? 'جاري الترصيد...' : 'ترصيد' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, defineEmits, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import getLocalDateISO from '@/utils/date';
import { useToast } from '@/composables/useToast';
// StockTransferModal حُذف — النقل انتقل لصفحة إدارة المخزون (/inventory)
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

/**
 * GL PRODUCTS WORKFLOW DOCUMENTATION:
 * ════════════════════════════════════════════════════════════════════
 * 
 * A product in a branch goes through the following GL status transitions:
 * 
 * 1. DRAFT (Initial State)
 *    - Product exists in system but not yet configured for this branch
 *    - Product has no GL account mapping
 *    - User cannot sell this product in this branch
 *    - ACTION: Admin must "Activate" product for this branch
 * 
 * 2. ACTIVE_IN_BRANCH (Post Activation)
 *    - Product is now configured for this branch
 *    - GL account(s) mapped and ready for posting
 *    - User CAN sell this product in this branch
 *    - Transactions will generate GL entries automatically
 *    - ACTION: Admin can "Reconcile" to mark period as complete
 * 
 * 3. RECONCILED (Post Reconciliation)
 *    - Product transactions have been audited and verified
 *    - Fiscal period is closed for this product in this branch
 *    - Cannot modify transactions for this product in this branch
 *    - ACTION: Can only view historical data (read-only)
 * 
 * Permission Model:
 * - DRAFT → ACTIVE_IN_BRANCH: Only Super Admin or Branch Admin
 * - ACTIVE_IN_BRANCH → RECONCILED: Only Super Admin or Finance Manager
 * - Any status: Normal users can only view/download reports
 */

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

// Transfers Data (محفوظ — جدول العرض فقط، لا modal للنقل)
const transfers = ref([]);
const isTransfersLoading = ref(false);
const transfersError = ref(null);

// Stock Transfers Reference
const stockTransfers = ref([]);
const isStockTransfersLoading = ref(false);
const stockTransfersError = ref(null);

// Transfer Details Modal (محفوظ — لعرض تفاصيل سجل النقل)
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

// handleRequestTransfer + handleTransferSuccess + selectedProductForTransfer حُذفوا
// BranchInventory لم تعد تُطلق request-transfer — النقل في /inventory مباشرةً

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

  // Fetch branches once, then fan-out + run GL in parallel
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
.tab-pill { @apply px-8 py-3 rounded-xl text-xs font-black transition-all flex items-center gap-3 active:scale-95; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm appearance-none; }
.modal-overlay { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden border border-white; }
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
.custom-scroll::-webkit-scrollbar { width: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>