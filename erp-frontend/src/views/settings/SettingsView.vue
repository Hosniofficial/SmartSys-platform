<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 text-slate-700 animate-fadeIn">
    
    <!-- Global Loading Overlay -->
    <Transition name="fade">
      <div v-if="isLoading" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center z-[100]">
        <div class="bg-white p-10 rounded-[2.5rem] shadow-2xl flex flex-col items-center">
          <BaseSpinner :size="48" color="#2563eb" :margin="0" />
          <p class="text-slate-500 mt-5 font-black uppercase tracking-widest text-xs animate-pulse">جاري مزامنة الإعدادات...</p>
        </div>
      </div>
    </Transition>

    <!-- Sticky Floating Save Bar (Dirty Tracking) -->
    <Transition name="slide-up">
      <div v-if="isDirty" class="fixed bottom-8 inset-x-0 flex justify-center z-[80] px-4">
        <div class="bg-slate-900 border border-slate-800 shadow-2xl rounded-[1.5rem] px-8 py-4 flex items-center gap-8 max-w-2xl w-full">
          <div class="flex items-center gap-3">
            <div class="w-2.5 h-2.5 bg-amber-400 rounded-full animate-pulse shadow-[0_0_10px_rgba(251,191,36,0.5)]"></div>
            <span class="text-sm font-black text-white uppercase tracking-tight">لديك تغييرات لم تحفظ بعد</span>
          </div>
          <div class="flex gap-3 mr-auto">
            <button @click="discardActiveTab" class="px-6 py-2 rounded-xl text-xs font-black text-slate-400 hover:text-white transition-all">تجاهل</button>
            <button @click="saveActiveTab" :disabled="isSaving" class="px-8 py-2.5 bg-blue-600 text-white rounded-xl text-xs font-black shadow-lg shadow-blue-500/20 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
              <BaseSpinner v-if="isSaving" :size="14" color="#ffffff" :margin="0" />
              <i v-else class="fas fa-save"></i>
              حفظ الآن
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Success/Error Alerts -->
    <div class="fixed top-24 left-8 z-[90] space-y-3 max-w-sm">
      <Transition name="slide-left">
        <div v-if="errorMessage" class="bg-rose-50 border border-rose-100 text-rose-700 px-5 py-4 rounded-2xl shadow-xl flex items-center gap-3">
          <i class="fas fa-exclamation-circle text-lg"></i>
          <span class="text-xs font-black flex-1">{{ errorMessage }}</span>
          <button @click="errorMessage = ''" class="ml-auto"><i class="fas fa-times"></i></button>
        </div>
      </Transition>
    </div>

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-slate-900 rounded-2xl flex items-center justify-center shadow-xl text-white">
          <i class="fas fa-sliders-h text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">إعدادات النظام</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحكم كامل في هوية النشاط، الضرائب، الصلاحيات والطابعات</p>
        </div>
      </div>
    </div>

    <!-- Access Denied -->
    <div v-if="!hasSettingsAccess" class="py-32 text-center animate-fadeIn">
      <div class="w-24 h-24 bg-rose-50 text-rose-500 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
        <i class="fas fa-lock text-4xl"></i>
      </div>
      <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tight">غير مصرح بالوصول</h2>
      <p class="text-slate-400 mt-2 font-bold">تحتاج إلى صلاحيات المدير العام للوصول لهذه الإعدادات</p>
      <router-link to="/dashboard" class="mt-8 inline-flex px-8 py-3 bg-slate-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-slate-200">العودة للرئيسية</router-link>
    </div>

    <!-- Main Layout -->
    <div v-else class="flex flex-col lg:flex-row gap-8 items-start">
      
      <!-- Sticky Navigation Sidebar -->
      <nav class="w-full lg:w-80 space-y-6 sticky top-8">
        <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6">
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block px-1">بحث سريع</label>
          <div class="relative group">
            <input v-model="searchTerm" type="text" class="form-input-modern pr-10 h-11 text-xs" placeholder="ابحث في الإعدادات..." />
            <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-blue-500 transition-colors"></i>
          </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden py-4">
          <button
            v-for="tab in filteredTabs"
            :key="tab.id"
            @click="setActiveTab(tab.id)"
            :class="[activeTab === tab.id ? 'bg-blue-50 text-blue-600' : 'text-slate-400 hover:bg-slate-50 hover:text-slate-600']"
            class="w-full px-6 py-4 flex items-center gap-4 transition-all duration-300 relative group"
          >
            <div v-if="activeTab === tab.id" class="absolute right-0 top-0 bottom-0 w-1.5 bg-blue-600 rounded-l-full"></div>
            <div :class="[activeTab === tab.id ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-slate-50 text-slate-400 group-hover:bg-white']" class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300">
              <i :class="tab.icon"></i>
            </div>
            <div class="flex-grow text-right">
              <span class="block text-xs font-black uppercase tracking-tight">{{ tab.label }}</span>
              <span v-if="isSectionDirty(sectionKeyFromTab(tab.id)) && sectionKeyFromTab(tab.id)" class="text-[9px] font-bold text-amber-500 animate-pulse flex items-center gap-1 mt-1">
                <i class="fas fa-circle text-[6px]"></i> تعديلات غير محفوظة
              </span>
            </div>
            <!-- Dirty dot indicator -->
            <div v-if="isSectionDirty(sectionKeyFromTab(tab.id))" class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></div>
          </button>
        </div>
      </nav>

      <!-- Settings Content Area -->
      <main class="flex-1 w-full space-y-8">

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- General Settings Section                                    -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'general-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">الإعدادات العامة</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">هوية النشاط، الإعدادات الإقليمية والشعار</p>
          </div>

          <div class="p-8 space-y-12">
            <!-- Business Info + Logo -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Business Info -->
              <div class="space-y-6">
                <h3 class="subsection-title"><i class="fas fa-building text-blue-500"></i> بيانات النشاط</h3>
                <div class="space-y-4">
                  <div class="space-y-1.5">
                    <label class="modal-label">اسم المتجر / العلامة التجارية <span class="text-rose-500">*</span></label>
                    <input v-model="general.businessName" type="text" class="form-input-modern font-black" :class="{ 'border-rose-400': errors.businessName }" placeholder="أدخل الاسم الرسمي" />
                    <p v-if="errors.businessName" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.businessName }}</p>
                    <p class="text-[9px] text-slate-400 font-bold px-1">سيظهر هذا الاسم في الفواتير والتقارير</p>
                  </div>
                  <div class="space-y-1.5">
                    <label class="modal-label">البريد الإلكتروني الرسمي</label>
                    <input v-model="general.email" type="email" class="form-input-modern font-bold" placeholder="mail@business.com" />
                  </div>
                  <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                      <label class="modal-label">رقم الهاتف</label>
                      <input v-model="general.phone" type="tel" class="form-input-modern font-mono" placeholder="050..." />
                    </div>
                    <div class="space-y-1.5">
                      <label class="modal-label">العنوان</label>
                      <input v-model="general.address" type="text" class="form-input-modern font-bold" placeholder="المدينة، الشارع" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Logo Upload Box -->
              <div class="space-y-6">
                <h3 class="subsection-title"><i class="fas fa-image text-purple-500"></i> شعار النظام</h3>
                <div class="flex flex-col items-center">
                  <div class="w-48 h-48 rounded-[2.5rem] bg-slate-50 border-4 border-white shadow-xl relative overflow-hidden group">
                    <template v-if="logoPreview">
                      <img :src="logoPreview" class="w-full h-full object-contain p-4" alt="معاينة الشعار" />
                      <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                        <button @click="removeLogo" class="w-10 h-10 bg-rose-500 text-white rounded-xl hover:bg-rose-600 transition-all active:scale-90"><i class="fas fa-trash-alt"></i></button>
                      </div>
                    </template>
                    <div v-else class="flex flex-col items-center justify-center h-full text-slate-300 opacity-40">
                      <i class="fas fa-images text-5xl mb-2"></i>
                      <p class="text-[10px] font-black uppercase">لا يوجد شعار</p>
                    </div>
                  </div>
                  <input type="file" ref="logoInput" @change="handleLogoUpload" class="hidden" accept="image/*" />
                  <button @click="$refs.logoInput.click()" type="button" class="mt-6 px-6 py-2.5 rounded-xl bg-blue-50 text-blue-600 text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all">اختيار شعار جديد</button>
                  <p class="mt-3 text-[9px] text-slate-400 font-bold leading-relaxed text-center px-6">
                    الصيغ المدعومة: JPG, PNG, GIF • الحجم الأقصى: 2 ميجابايت • الأبعاد المفضلة: 512×512
                  </p>
                </div>
              </div>
            </div>

            <!-- Regional Settings -->
            <div class="pt-10 border-t border-slate-50">
              <h3 class="subsection-title mb-6"><i class="fas fa-globe text-emerald-500"></i> الإعدادات الإقليمية</h3>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="space-y-1.5">
                  <label class="modal-label">العملة الافتراضية</label>
                  <select v-model="general.currency" @change="updateCurrencySymbol" class="form-select-modern font-black">
                    <option v-for="curr in getAvailableCurrencies('ar')" :key="curr.code" :value="curr.code">{{ curr.name }} ({{ curr.symbol }})</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="modal-label">المنطقة الزمنية</label>
                  <select v-model="general.timezone" class="form-select-modern font-bold">
                    <option value="Asia/Riyadh">(GMT+03:00) الرياض، الكويت</option>
                    <option value="Asia/Dubai">(GMT+04:00) دبي، أبو ظبي</option>
                    <option value="Africa/Cairo">(GMT+02:00) القاهرة</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="modal-label">تنسيق التاريخ</label>
                  <select v-model="general.dateFormat" class="form-select-modern font-bold">
                    <option value="DD/MM/YYYY">DD/MM/YYYY (31/12/2023)</option>
                    <option value="MM/DD/YYYY">MM/DD/YYYY (12/31/2023)</option>
                    <option value="YYYY-MM-DD">YYYY-MM-DD (2023-12-31)</option>
                    <option value="DD MMM, YYYY">DD MMM, YYYY (31 Dec, 2023)</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="modal-label">توقيت الساعة</label>
                  <select v-model="general.timeFormat" class="form-select-modern font-bold">
                    <option value="12">12 ساعة (01:30 م)</option>
                    <option value="24">24 ساعة (13:30)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Section Actions -->
            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
              <button type="button" @click="resetForm" class="btn-outline-modern">إعادة تعيين</button>
              <button type="button" @click="saveGeneralSettings" :disabled="isSaving" class="btn-primary-modern">
                <BaseSpinner v-if="isSaving" :size="16" color="#ffffff" :margin="0" />
                <i v-else class="fas fa-save"></i>
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
              </button>
            </div>
          </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- POS Settings Section                                        -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'pos-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">إعدادات الكاشير (POS)</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">التحكم في الجلسات، الورديات، والموافقات المالية</p>
          </div>

          <div class="p-8 space-y-10">
            <!-- Terminals Quick Link -->
            <div class="bg-blue-600 rounded-[2rem] p-8 text-white flex flex-col md:flex-row items-center justify-between gap-6 shadow-xl shadow-blue-100 relative overflow-hidden group">
              <div class="absolute top-0 left-0 w-32 h-32 bg-white/5 rounded-full -translate-x-12 -translate-y-12 transition-transform group-hover:scale-125"></div>
              <div class="relative z-10 flex items-center gap-6">
                <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl shadow-inner"><i class="fas fa-desktop"></i></div>
                <div>
                  <h3 class="text-lg font-black leading-none">إدارة أجهزة نقاط البيع</h3>
                  <p class="text-xs text-white/60 font-bold mt-2 leading-relaxed">قم بتعريف الأجهزة المرتبطة بكل مخزن لتسهيل اختيارها عند فتح الورديات.</p>
                </div>
              </div>
              <router-link :to="{ name: 'TerminalsManagement' }" class="relative z-10 px-8 py-3 bg-white text-blue-600 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-50 transition-all active:scale-95 shadow-sm shrink-0">إدارة الأجهزة</router-link>
            </div>

            <!-- Role Enforcement -->
            <div class="space-y-6">
              <div class="flex items-center justify-between px-1">
                <h3 class="subsection-title"><i class="fas fa-user-lock text-rose-500"></i> تقييد الجلسات بالأدوار</h3>
                <div class="flex items-center gap-3">
                  <button type="button" @click="posSession.enforceForRoles = filteredPosRoleOptions.map(r => Number(r.id))" class="text-[9px] font-black text-blue-600 uppercase tracking-widest hover:underline">تحديد الكل</button>
                  <button type="button" @click="posSession.enforceForRoles = []" class="text-[9px] font-black text-slate-400 uppercase tracking-widest hover:underline">إلغاء الكل</button>
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <label v-for="role in filteredPosRoleOptions" :key="role.id" class="relative flex items-center p-4 rounded-2xl bg-slate-50 border-2 border-transparent hover:border-blue-100 transition-all cursor-pointer has-[:checked]:bg-white has-[:checked]:border-blue-600 has-[:checked]:shadow-lg has-[:checked]:shadow-blue-50">
                  <input type="checkbox" :value="Number(role.id)" v-model="posSession.enforceForRoles" class="w-5 h-5 rounded-lg border-slate-300 text-blue-600 focus:ring-blue-100 transition-all" />
                  <span class="mr-3 text-xs font-black text-slate-700 uppercase tracking-tight">{{ translateRole(role.name) }}</span>
                </label>
              </div>
              <div v-if="rolesLoading" class="text-xs text-slate-500 bg-blue-50 p-3 rounded-xl border border-blue-100 flex items-center gap-2">
                <BaseSpinner :size="14" color="#3b82f6" margin="0" /> جاري تحميل الأدوار المتاحة...
              </div>
              <p class="bg-amber-50 text-amber-700 p-4 rounded-2xl border border-amber-100 text-[11px] font-bold leading-relaxed italic">
                <i class="fas fa-info-circle ml-1"></i> الأدوار المحددة لن تتمكن من إجراء عمليات بيع دون فتح جلسة مرتبطة بمستودع وجهاز محدد.
              </p>
            </div>

            <!-- Approval Toggle -->
            <div class="pt-10 border-t border-slate-50">
              <label class="flex items-center justify-between p-6 rounded-[2rem] bg-slate-900 text-white shadow-xl shadow-slate-200 border border-slate-800 transition-all hover:scale-[1.01] cursor-pointer">
                <div class="max-w-md">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-amber-500/20 text-amber-400 rounded-lg flex items-center justify-center text-sm"><i class="fas fa-check-double"></i></div>
                    <h4 class="text-sm font-black uppercase tracking-widest">تفعيل نظام الموافقة المسبقة</h4>
                  </div>
                  <p class="text-[10px] text-white/50 font-bold mt-2 leading-relaxed">عند التفعيل، سيتم إرسال فواتير نقطة البيع للمراجعة الإدارية قبل الاعتماد المالي وتحديث المخزون.</p>
                </div>
                <div class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="posSession.requireApproval" class="sr-only peer" />
                  <div class="w-14 h-7 bg-white/10 rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-500"></div>
                </div>
              </label>
            </div>


            <!-- Session Limits & Type -->
            <div class="pt-6 border-t border-slate-50 space-y-6">
              <h3 class="subsection-title"><i class="fas fa-history text-indigo-500"></i> سعة الورديات ونوع الجلسة</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="modal-label">عدد الجلسات المسموح يومياً لكل مخزن</label>
                  <select v-model="posSession.mode" class="form-select-modern font-black text-sm">
                    <option value="">غير محدد (مفتوح)</option>
                    <option value="one_per_day">جلسة واحدة فقط</option>
                    <option value="two_per_day">جلستان كحد أقصى</option>
                    <option value="three_per_day">ثلاث جلسات</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label class="modal-label">نوع الجلسة التلقائي</label>
                  <select v-model="posSession.sessionTypeMode" class="form-select-modern font-black text-sm">
                    <option value="">يدوي (المستخدم يختار)</option>
                    <option value="daily">يومي (جلسة يوم كامل)</option>
                    <option value="morning">صباحية / مسائية (تلقائي حسب الوقت)</option>
                  </select>
                  <p class="text-[10px] text-slate-400 font-bold italic"><i class="fas fa-info-circle ml-1"></i> مكافئ لـ Shift Type في SAP Retail</p>
                </div>
              </div>
              <!-- Period Cutoff — shown only when morning/evening mode -->
              <div v-if="posSession.sessionTypeMode === 'morning'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="modal-label">وقت الفصل بين الوردية الصباحية والمسائية</label>
                  <input type="time" v-model="posSession.periodCutoff" class="form-input-modern font-black font-mono" />
                  <p class="text-[10px] text-slate-400 font-bold italic"><i class="fas fa-clock ml-1"></i> قبل هذا الوقت = صباحية، بعده = مسائية</p>
                </div>
              </div>
              <!-- Manager Override -->
              <label class="flex items-center justify-between p-5 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer hover:bg-slate-100 transition-all">
                <div>
                  <div class="text-xs font-black text-slate-800"><i class="fas fa-user-shield ml-2 text-indigo-500"></i>السماح للمدير بتجاوز حد الورديات</div>
                  <p class="text-[10px] text-slate-400 font-bold mt-1">المسؤولون والمدراء يمكنهم فتح جلسات إضافية عند الضرورة — مكافئ لـ Manager Override في Oracle POS</p>
                </div>
                <input type="checkbox" v-model="posSession.allowManagerOverride" class="w-5 h-5 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-100" />
              </label>
            </div>

            <!-- Per-Branch Approval Overrides -->
            <div class="pt-6 border-t border-slate-50 space-y-4">
              <div class="flex items-center gap-3">
                <h3 class="subsection-title"><i class="fas fa-code-branch text-emerald-500"></i> تجاوزات الموافقة لكل فرع</h3>
                <span class="text-[9px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-black uppercase">مكافئ SAP Plant-Level</span>
              </div>
              <p class="text-[11px] text-slate-400 font-bold italic leading-relaxed">
                <i class="fas fa-info-circle ml-1"></i> يمكنك تحديد فروع تعمل بشكل مختلف عن الإعداد العام أعلاه. الفروع غير المحددة هنا تتبع الإعداد العام.
              </p>
              <div v-if="branchOptions.length" class="space-y-3">
                <label v-for="branch in branchOptions" :key="branch.id"
                  class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer hover:bg-slate-100 transition-all">
                  <span class="text-xs font-black text-slate-700"><i class="fas fa-store ml-2 text-slate-400"></i>{{ branch.name }}</span>
                  <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold text-slate-400">
                      {{ posSession.branchApprovals[branch.id] === true ? 'موافقة مفعّلة' : posSession.branchApprovals[branch.id] === false ? 'موافقة معطّلة' : 'يتبع الإعداد العام' }}
                    </span>
                    <select
                      :value="posSession.branchApprovals[branch.id] === true ? 'on' : posSession.branchApprovals[branch.id] === false ? 'off' : ''"
                      @change="e => { const v = e.target.value; if (v === '') { const b = {...posSession.branchApprovals}; delete b[branch.id]; posSession.branchApprovals = b } else { posSession.branchApprovals = {...posSession.branchApprovals, [branch.id]: v === 'on'} } }"
                      class="h-9 bg-white border border-slate-200 rounded-xl px-3 text-xs font-black outline-none focus:ring-2 focus:ring-blue-50">
                      <option value="">إعداد عام</option>
                      <option value="on">موافقة مفعّلة</option>
                      <option value="off">موافقة معطّلة</option>
                    </select>
                  </div>
                </label>
              </div>
              <div v-else class="text-[11px] text-slate-400 bg-slate-50 border border-slate-100 rounded-2xl p-4 font-bold">
                <i class="fas fa-info-circle ml-1"></i> لا توجد فروع معرّفة. أضف فروعاً من إعدادات المخازن.
              </div>
            </div>

            <!-- Section Actions -->
            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
              <button type="button" @click="resetSectionToSnapshot('pos')" class="btn-outline-modern">إلغاء التغييرات</button>
              <button type="button" @click="savePosSettings" :disabled="isSaving" class="btn-primary-modern">
                <BaseSpinner v-if="isSaving" :size="16" color="#ffffff" :margin="0" />
                <i v-else class="fas fa-save"></i>
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ إعدادات POS' }}
              </button>
            </div>
          </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- Invoice & Tax Settings Section                              -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'invoice-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">الفواتير والضرائب</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">تخصيص الفواتير، الأرقام الضريبية، والبادئات</p>
          </div>

          <div class="p-8 space-y-12">
            <!-- Tax Configuration -->
            <div>
              <h3 class="subsection-title mb-8"><i class="fas fa-percent text-rose-500"></i> تهيئة الضرائب</h3>
              <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                <div class="md:col-span-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-1.5">
                    <label class="modal-label">رقم السجل الضريبي <span class="text-rose-500">*</span></label>
                    <input v-model="invoice.taxNumber" type="text" class="form-input-modern font-black font-mono tracking-widest" :class="{ 'border-rose-400': errors.taxNumber }" placeholder="123456789012345" />
                    <p v-if="errors.taxNumber" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.taxNumber }}</p>
                  </div>
                  <div class="space-y-1.5">
                    <label class="modal-label">اسم الضريبة <span class="text-rose-500">*</span></label>
                    <input v-model="invoice.taxName" type="text" class="form-input-modern font-black" :class="{ 'border-rose-400': errors.taxName }" placeholder="ضريبة القيمة المضافة" />
                    <p v-if="errors.taxName" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.taxName }}</p>
                  </div>
                  <div class="space-y-1.5">
                    <label class="modal-label">نسبة الضريبة (%) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                      <input v-model.number="invoice.taxRate" type="number" min="0" max="100" step="0.01" class="form-input-modern font-black text-lg pl-10 pr-11" :class="{ 'border-rose-400': errors.taxRate }" placeholder="15" />
                      <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-300">%</span>
                    </div>
                    <p v-if="errors.taxRate" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.taxRate }}</p>
                  </div>
                </div>

                <div class="md:col-span-4 space-y-4">
                  <label class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer transition-all hover:bg-white">
                    <div class="flex-grow">
                      <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-widest leading-none">تفعيل الضرائب</h4>
                      <p class="text-[9px] text-slate-400 font-bold mt-1">سيتم احتساب الضرائب تلقائياً</p>
                    </div>
                    <div class="relative inline-flex items-center">
                      <input type="checkbox" v-model="invoice.taxEnabled" class="sr-only peer" />
                      <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500"></div>
                    </div>
                  </label>
                  <label class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer transition-all hover:bg-white">
                    <div class="flex-grow">
                      <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-widest leading-none">إظهار الضريبة في السعر</h4>
                      <p class="text-[9px] text-slate-400 font-bold mt-1">تضمين الضريبة في سعر العرض</p>
                    </div>
                    <div class="relative inline-flex items-center">
                      <input type="checkbox" v-model="invoice.showTaxInPrice" class="sr-only peer" />
                      <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:after:-translate-x-full rtl:peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Prefix & Sequence -->
            <div class="pt-8 border-t border-slate-50">
              <h3 class="subsection-title mb-8"><i class="fas fa-hashtag text-blue-500"></i> أرقام وتسلسل الفواتير</h3>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="space-y-1.5">
                  <label class="modal-label">بادئة رقم الفاتورة <span class="text-rose-500">*</span></label>
                  <input v-model="invoice.invoicePrefix" type="text" class="form-input-modern font-black font-mono" :class="{ 'border-rose-400': errors.invoicePrefix }" placeholder="INV-" />
                  <p v-if="errors.invoicePrefix" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.invoicePrefix }}</p>
                  <p class="text-[9px] font-bold text-slate-400 italic">مثال: INV-1001</p>
                </div>
                <div class="space-y-1.5">
                  <label class="modal-label">رقم الفاتورة التالي <span class="text-rose-500">*</span></label>
                  <input v-model.number="invoice.nextInvoiceNumber" type="number" min="1" class="form-input-modern font-black text-lg" :class="{ 'border-rose-400': errors.nextInvoiceNumber }" placeholder="1001" />
                  <p v-if="errors.nextInvoiceNumber" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ errors.nextInvoiceNumber }}</p>
                </div>
                <div class="space-y-1.5">
                  <label class="modal-label">نص تذييل الفاتورة</label>
                  <textarea v-model="invoice.footerText" rows="2" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white outline-none focus:border-blue-400 resize-none transition-all" placeholder="شكراً لتعاملكم معنا..."></textarea>
                  <p class="text-[9px] text-slate-400 font-bold">سيظهر في أسفل كل فاتورة</p>
                </div>
              </div>
            </div>

            <!-- Section Actions -->
            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
              <button type="button" @click="resetForm" class="btn-outline-modern">إعادة تعيين</button>
              <button type="button" @click="saveInvoiceSettings" :disabled="isSaving" class="btn-primary-modern">
                <BaseSpinner v-if="isSaving" :size="16" color="#ffffff" :margin="0" />
                <i v-else class="fas fa-save"></i>
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
              </button>
            </div>
          </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- Accounting Settings Section (RESTORED FROM OLD FILE)        -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'accounting-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">الحسابات المحاسبية</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">تعيين حسابات COGS والمخزون وتسويات المخزون</p>
          </div>

          <div class="p-8 space-y-8">
            <!-- Costing Policy Info -->
            <div class="rounded-[2rem] border border-blue-100 bg-blue-50 p-6">
              <div class="flex items-start gap-4">
                <div class="mt-0.5 text-blue-600 text-lg"><i class="fas fa-balance-scale"></i></div>
                <div class="flex-1">
                  <div class="flex items-center gap-3 flex-wrap">
                    <div class="font-black text-blue-800 text-sm uppercase tracking-widest">سياسة تكلفة المخزون الحالية</div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[10px] font-black text-white bg-blue-600 rounded-xl uppercase tracking-widest">
                      <i class="fas fa-chart-area"></i> المتوسط المرجّح (WAC)
                    </span>
                  </div>
                  <p class="text-sm text-blue-700 mt-2 font-bold">
                    يتم تقييم تكلفة المخزون وتكلفة البضاعة المباعة باستخدام متوسط التكلفة المرجّح تلقائيًا عبر النظام.
                  </p>
                </div>
              </div>
            </div>

            <!-- Warning: missing accounts -->
            <div v-if="accountingMissingKeys.length" class="rounded-[2rem] bg-amber-50 border border-amber-200 p-6 text-amber-900">
              <div class="font-black text-sm uppercase tracking-widest mb-3 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-500"></i>
                إعدادات الحسابات الأساسية غير مكتملة
              </div>
              <ul class="list-disc pr-5 text-xs font-bold space-y-1 text-amber-800">
                <li v-for="key in accountingMissingKeys" :key="key">لم يتم ضبط: {{ accountingKeyLabels[key] }}</li>
              </ul>
              <p class="text-[10px] text-amber-700 mt-3 font-bold italic">
                قم بتعيين هذه الحسابات ثم اضغط حفظ لضمان إنشاء القيود المحاسبية بشكل صحيح.
              </p>
            </div>

            <!-- Account Selectors -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- COGS Account -->
              <div class="space-y-2">
                <label class="modal-label">حساب COGS (تكلفة البضاعة المباعة) <span class="text-rose-500">*</span></label>
                <select v-model="accounting.cogsAccountId" class="form-select-modern">
                  <option :value="null">— اختر حساباً —</option>
                  <optgroup v-if="accounts.tenant.length" label="حسابات المستأجر">
                    <option v-for="acc in accounts.tenant" :key="'cogs-t-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                  <optgroup v-if="accounts.global.length" label="حسابات عامة (افتراضية)">
                    <option v-for="acc in accounts.global" :key="'cogs-g-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                </select>
                <p class="text-[9px] font-bold text-slate-400 italic">يستخدم عند بيع المنتجات</p>
              </div>

              <!-- Inventory Account -->
              <div class="space-y-2">
                <label class="modal-label">حساب المخزون <span class="text-rose-500">*</span></label>
                <select v-model="accounting.inventoryAccountId" class="form-select-modern">
                  <option :value="null">— اختر حساباً —</option>
                  <optgroup v-if="accounts.tenant.length" label="حسابات المستأجر">
                    <option v-for="acc in accounts.tenant" :key="'inv-t-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                  <optgroup v-if="accounts.global.length" label="حسابات عامة (افتراضية)">
                    <option v-for="acc in accounts.global" :key="'inv-g-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                </select>
                <p class="text-[9px] font-bold text-slate-400 italic">يستخدم لتسجيل قيمة المخزون</p>
              </div>

              <!-- Adjustment Account -->
              <div class="space-y-2">
                <label class="modal-label">حساب تسويات المخزون <span class="text-rose-500">*</span></label>
                <select v-model="accounting.inventoryAdjustmentAccountId" class="form-select-modern">
                  <option :value="null">— اختر حساباً —</option>
                  <optgroup v-if="accounts.tenant.length" label="حسابات المستأجر">
                    <option v-for="acc in accounts.tenant" :key="'adj-t-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                  <optgroup v-if="accounts.global.length" label="حسابات عامة (افتراضية)">
                    <option v-for="acc in accounts.global" :key="'adj-g-'+acc.id" :value="acc.id">
                      {{ acc.code ? `${acc.code} — ${acc.name}` : acc.name }}
                    </option>
                  </optgroup>
                </select>
                <p class="text-[9px] font-bold text-slate-400 italic">يستخدم عند تسوية فروق المخزون</p>
              </div>
            </div>

            <!-- Section Actions -->
            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
              <button type="button" @click="resetForm" class="btn-outline-modern">إعادة تعيين</button>
              <button type="button" @click="saveAccountingSettings" :disabled="isSaving" class="btn-primary-modern">
                <BaseSpinner v-if="isSaving" :size="16" color="#ffffff" :margin="0" />
                <i v-else class="fas fa-save"></i>
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ إعدادات المحاسبة' }}
              </button>
            </div>
          </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- Users Settings Section                                      -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'users-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">إدارة مستخدمي النظام</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">التحكم في الحسابات، الأدوار، وتخصيص الفروع</p>
          </div>

          <div class="p-8">
            <!-- Stats + Add Button -->
            <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-10">
              <div class="flex items-center gap-6 bg-slate-900 text-white p-6 rounded-[2rem] shadow-xl shadow-slate-200 flex-1 w-full relative overflow-hidden">
                <div class="absolute right-0 bottom-0 w-24 h-24 bg-white/5 rounded-full translate-x-8 translate-y-8"></div>
                <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-xl shadow-inner relative z-10"><i class="fas fa-users"></i></div>
                <div class="relative z-10">
                  <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest leading-none mb-2">إجمالي المسجلين</p>
                  <p class="text-3xl font-black tracking-tighter leading-none">{{ usersTotal }} <span class="text-[11px] text-white/30 font-bold uppercase tracking-[0.2em] ml-2">مستخدم</span></p>
                </div>
              </div>

              <button @click="addUser" class="h-14 px-10 bg-blue-600 text-white rounded-2xl font-black text-sm shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-3 shrink-0">
                <i class="fas fa-plus"></i> إضافة مستخدم جديد
              </button>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
              <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                  <thead>
                    <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                      <th class="px-8 py-5">الموظف / البريد</th>
                      <th class="px-4 py-5">اسم المستخدم</th>
                      <th class="px-4 py-5">الأدوار والصلاحيات</th>
                      <th class="px-8 py-5 text-center">إجراءات</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-50">
                    <tr v-if="usersLoading">
                      <td colspan="4" class="py-20 text-center">
                        <div class="flex flex-col items-center gap-3">
                          <BaseSpinner :size="24" color="#2563eb" :margin="0" />
                          <span class="text-slate-400 font-black text-xs uppercase">جاري تحميل المستخدمين...</span>
                        </div>
                      </td>
                    </tr>
                    <tr v-else-if="!users.length">
                      <td colspan="4" class="py-20 text-center">
                        <div class="flex flex-col items-center gap-3 opacity-30">
                          <i class="fas fa-users text-5xl text-slate-400"></i>
                          <p class="font-black text-xs uppercase text-slate-400">لا يوجد مستخدمون. ابدأ بإضافة أول موظف</p>
                        </div>
                      </td>
                    </tr>
                    <tr v-for="user in users" :key="user.id || user.username" class="hover:bg-blue-50/30 transition-all group font-bold">
                      <td class="px-8 py-4">
                        <div class="flex items-center gap-4">
                          <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-blue-600 group-hover:text-white transition-all font-black">{{ (user.name || 'U').charAt(0).toUpperCase() }}</div>
                          <div class="flex flex-col">
                            <span class="font-black text-slate-800 leading-none truncate max-w-[150px]">{{ user.name }}</span>
                            <span class="text-[10px] text-slate-400 mt-1.5 font-bold">{{ user.email }}</span>
                          </div>
                        </div>
                      </td>
                      <td class="px-4 py-4 text-xs font-black text-slate-500 font-mono">@{{ user.username }}</td>
                      <td class="px-4 py-4">
                        <div class="flex flex-wrap gap-1.5">
                          <template v-if="Array.isArray(user.roles) && user.roles.length > 0">
                            <span v-for="role in user.roles.slice(0, 2)" :key="role" class="px-2.5 py-1 bg-slate-50 border border-slate-100 text-slate-600 text-[9px] font-black uppercase rounded-lg group-hover:border-blue-200 transition-all">{{ translateRole(role) }}</span>
                            <span v-if="user.roles.length > 2" class="text-[9px] font-black text-slate-300">+{{ user.roles.length - 2 }}</span>
                          </template>
                          <span v-else class="text-[9px] font-black text-slate-300">لا توجد أدوار</span>
                        </div>
                      </td>
                      <td class="px-8 py-4 text-center">
                        <button @click="editUser(user)" class="px-5 py-2 bg-slate-50 text-slate-500 rounded-xl text-[10px] font-black uppercase hover:bg-amber-100 hover:text-amber-700 transition-all active:scale-95 inline-flex items-center gap-2 mx-auto">
                          <i class="fas fa-pen text-[9px]"></i> تعديل الحساب
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination -->
              <div v-if="usersTotal > usersLimit" class="px-8 py-5 bg-slate-50/50 border-t border-slate-50 flex items-center justify-between">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                  صفحة {{ usersPage }} من {{ Math.max(1, Math.ceil(usersTotal / usersLimit)) }}
                  ({{ usersTotal }} مستخدم)
                </span>
                <div class="flex items-center gap-1">
                  <button @click="prevUsersPage" :disabled="usersPage <= 1 || usersLoading" class="pagination-btn"><i class="fas fa-angle-right"></i></button>
                  <button @click="nextUsersPage" :disabled="usersPage >= Math.ceil(usersTotal / usersLimit) || usersLoading" class="pagination-btn"><i class="fas fa-angle-left"></i></button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════ -->
        <!-- Printers Settings Section                                   -->
        <!-- ════════════════════════════════════════════════════════════ -->
        <section v-if="activeTab === 'printers-settings'" class="settings-section animate-fadeIn">
          <div class="section-header">
            <h2 class="text-xl font-black text-slate-900 leading-none">إعدادات الطباعة</h2>
            <p class="text-slate-400 text-xs mt-2 font-bold uppercase tracking-widest italic">تعريف الطابعات، قوالب الفواتير والمعاينة المباشرة</p>
          </div>

          <div class="p-8 space-y-12">
            <!-- Print Mode -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Mode Selector -->
              <div class="space-y-6">
                <h3 class="subsection-title"><i class="fas fa-print text-blue-500"></i> وضع الطباعة</h3>
                <div class="space-y-4">
                  <div class="space-y-1.5">
                    <label class="modal-label">آلية إرسال الفاتورة للطابعة</label>
                    <select v-model="printMode" class="form-select-modern font-black text-sm">
                      <option value="browser">متصفح (browser print)</option>
                      <option value="qztray">QZ Tray — طباعة مباشرة للطابعة</option>
                    </select>
                    <p class="text-[9px] font-bold text-slate-400 italic">في وضع المتصفح، تختار الطابعة من نافذة الطباعة التي يفتحها المتصفح</p>
                  </div>
                  <div v-if="printMode === 'qztray'" class="space-y-3">
                    <div class="space-y-1.5">
                      <div class="flex items-center justify-between">
                        <label class="modal-label">اختر الطابعة</label>
                        <button type="button" @click="loadQzPrinters" :disabled="qzPrintersLoading" class="text-[10px] font-black text-blue-600 hover:text-blue-800 flex items-center gap-1 disabled:opacity-50">
                          <i :class="qzPrintersLoading ? 'fas fa-spinner fa-spin' : 'fas fa-sync'"></i>
                          {{ qzPrintersLoading ? 'جاري البحث...' : 'تحديث الطابعات' }}
                        </button>
                      </div>
                      <select v-model="qzPrinterName" class="form-select-modern font-black text-sm" :disabled="qzPrintersLoading">
                        <option value="">— طابعة النظام الافتراضية —</option>
                        <option v-for="p in qzPrinters" :key="p" :value="p">{{ p }}</option>
                      </select>
                      <p v-if="qzConnectError" class="text-[10px] text-rose-500 font-bold"><i class="fas fa-exclamation-circle ml-1"></i>{{ qzConnectError }}</p>
                      <p v-else class="text-[9px] text-slate-400 font-bold italic">اتركه فارغاً لاستخدام الطابعة الافتراضية في النظام</p>
                    </div>
                    <div class="p-3 rounded-xl bg-blue-50 border border-blue-100 text-blue-700 text-[10px] font-bold">
                      <i class="fas fa-info-circle ml-1"></i>
                      يتطلب تشغيل تطبيق <strong>QZ Tray</strong> على الجهاز. <a href="https://qz.io" target="_blank" class="underline">تحميل QZ Tray</a>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Print Options Toggles -->
              <div class="space-y-6">
                <h3 class="subsection-title"><i class="fas fa-check-double text-emerald-500"></i> خيارات الطباعة</h3>
                <div class="grid grid-cols-1 gap-4">
                  <label class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer hover:bg-white transition-all">
                    <div>
                      <span class="text-[11px] font-black text-slate-700 uppercase">إظهار ترويسة المتجر</span>
                      <p class="text-[9px] text-slate-400 font-bold mt-1">إظهار اسم المتجر وبياناته في أعلى الفاتورة</p>
                    </div>
                    <input type="checkbox" v-model="printers.printHeader" class="w-5 h-5 rounded-lg text-blue-600 border-slate-300 focus:ring-blue-100" />
                  </label>
                  <label class="flex items-center justify-between p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer hover:bg-white transition-all">
                    <div>
                      <span class="text-[11px] font-black text-slate-700 uppercase">إظهار تذييل الفاتورة</span>
                      <p class="text-[9px] text-slate-400 font-bold mt-1">إظهار نص التذييل في أسفل الفاتورة</p>
                    </div>
                    <input type="checkbox" v-model="printers.printFooter" class="w-5 h-5 rounded-lg text-blue-600 border-slate-300 focus:ring-blue-100" />
                  </label>
                </div>
              </div>
            </div>

            <!-- Print Template & Preview -->
            <div class="pt-10 border-t border-slate-50">
              <div class="flex flex-col lg:flex-row gap-10">
                <!-- Template Settings -->
                <div class="lg:w-1/2 space-y-6">
                  <h3 class="subsection-title"><i class="fas fa-file-invoice text-indigo-500"></i> قالب وتصميم الفاتورة</h3>
                  <div class="space-y-4">
                    <div class="space-y-1.5">
                      <label class="modal-label">القالب المعتمد للطباعة</label>
                      <select v-model="printTemplate" class="form-select-modern font-black">
                        <option value="thermal-compact">حراري - مختصر (80mm)</option>
                        <option value="thermal-detailed">حراري - تفصيلي</option>
                        <option value="a4-simple">A4 - بسيط</option>
                        <option value="a4-professional">A4 - احترافي (شعار، QR)</option>
                      </select>
                      <p class="text-[9px] font-bold text-slate-400 italic">يستخدم عند الضغط على "حفظ وطباعة" في نقطة البيع</p>
                    </div>
                    <div class="space-y-1.5">
                      <label class="modal-label">نص ترحيبي (ترويسة)</label>
                      <input v-model="printTexts.headerText" type="text" class="form-input-modern text-xs" placeholder="مثال: مرحباً بكم في متجرنا" />
                      <p class="text-[9px] text-slate-400 font-bold">يظهر أسفل اسم المتجر (اتركه فارغاً لإخفائه)</p>
                    </div>
                    <div class="space-y-1.5">
                      <label class="modal-label">نص التذييل</label>
                      <textarea v-model="printTexts.footerText" rows="2" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white outline-none focus:border-blue-400 resize-none transition-all" placeholder="شكراً لتعاملكم معنا"></textarea>
                    </div>
                    <div class="space-y-1.5">
                      <label class="modal-label">الشروط والأحكام</label>
                      <textarea v-model="printTexts.termsText" rows="3" class="w-full rounded-2xl border border-slate-200 p-4 text-xs font-bold bg-slate-50 focus:bg-white outline-none focus:border-blue-400 resize-none transition-all" placeholder="سطر لكل شرط. اتركه فارغاً لإخفاء القسم"></textarea>
                    </div>
                  </div>
                </div>

                <!-- Live Preview -->
                <div class="lg:w-1/2 bg-slate-900 rounded-[3rem] p-8 md:p-12 shadow-2xl relative overflow-hidden">
                  <div class="absolute top-4 right-8 text-[10px] font-black text-white/20 uppercase tracking-[0.3em]">معاينة حية</div>

                  <!-- Preview Type Tabs -->
                  <div class="flex gap-2 mb-4">
                    <button type="button" @click="previewType = 'sale'"
                      :class="previewType === 'sale' ? 'bg-white text-slate-800 shadow' : 'bg-white/10 text-white/70 hover:bg-white/20'"
                      class="flex-1 py-2 rounded-xl text-[10px] font-black transition-all flex items-center justify-center gap-1.5">
                      <i class="fas fa-file-invoice"></i> فاتورة بيع
                    </button>
                    <button type="button" @click="previewType = 'return'"
                      :class="previewType === 'return' ? 'bg-white text-slate-800 shadow' : 'bg-white/10 text-white/70 hover:bg-white/20'"
                      class="flex-1 py-2 rounded-xl text-[10px] font-black transition-all flex items-center justify-center gap-1.5">
                      <i class="fas fa-undo-alt"></i> مرتجع
                    </button>
                    <button type="button" @click="previewType = 'purchase'"
                      :class="previewType === 'purchase' ? 'bg-white text-slate-800 shadow' : 'bg-white/10 text-white/70 hover:bg-white/20'"
                      class="flex-1 py-2 rounded-xl text-[10px] font-black transition-all flex items-center justify-center gap-1.5">
                      <i class="fas fa-shopping-cart"></i> مشتريات
                    </button>
                  </div>

                  <!-- Preview iframe -->
                  <iframe :srcdoc="previewHtml" sandbox="allow-same-origin"
                    class="w-full rounded-xl bg-white shadow-inner"
                    style="height:480px; border:none; display:block;"></iframe>

                  <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <button type="button" @click="testPrint" class="px-6 py-2.5 rounded-xl bg-white/10 text-white text-[10px] font-black uppercase hover:bg-white/20 transition-all flex items-center gap-2">
                      <i class="fas fa-print"></i> طباعة تجريبية
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section Actions -->
            <div class="pt-8 border-t border-slate-50 flex justify-end gap-4">
              <button type="button" @click="resetForm" class="btn-outline-modern">إعادة تعيين</button>
              <button type="button" @click="savePrinterSettings" :disabled="isSaving" class="btn-primary-modern">
                <BaseSpinner v-if="isSaving" :size="16" color="#ffffff" :margin="0" />
                <i v-else class="fas fa-save"></i>
                {{ isSaving ? 'جاري الحفظ...' : 'حفظ إعدادات الطابعات' }}
              </button>
            </div>
          </div>
        </section>

      </main>
    </div>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- User Modal                                                      -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <Transition name="modal">
      <div v-if="showUserModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-content-modern animate-modalIn max-w-2xl border border-white" @click.stop>
          <div class="px-8 py-7 border-b border-slate-50 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center text-white shadow-lg"><i class="fas fa-user-plus"></i></div>
              <h3 class="text-xl font-black text-slate-800">{{ isEditingUser ? 'تعديل بيانات الموظف' : 'تسجيل موظف جديد' }}</h3>
            </div>
            <button @click="closeUserModal" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fas fa-times text-xl"></i></button>
          </div>

          <div class="p-8 space-y-8 max-h-[70vh] overflow-y-auto custom-scroll text-right" dir="rtl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-1.5">
                <label class="modal-label">الاسم بالكامل <span class="text-rose-500">*</span></label>
                <input v-model="userForm.name" type="text" class="form-input-modern font-bold" :class="{ 'border-rose-400': formErrors.name }" placeholder="أدخل اسم الموظف" />
                <p v-if="formErrors.name" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ formErrors.name }}</p>
              </div>
              <div class="space-y-1.5">
                <label class="modal-label">اسم المستخدم (للدخول) <span class="text-rose-500">*</span></label>
                <input v-model="userForm.username" type="text" class="form-input-modern font-black font-mono" :class="{ 'border-rose-400': formErrors.username }" placeholder="username" />
                <p v-if="formErrors.username" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ formErrors.username }}</p>
              </div>
              <div class="space-y-1.5">
                <label class="modal-label">البريد الإلكتروني <span class="text-rose-500">*</span></label>
                <input v-model="userForm.email" type="email" class="form-input-modern font-bold" :class="{ 'border-rose-400': formErrors.email }" placeholder="email@example.com" />
                <p v-if="formErrors.email" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ formErrors.email }}</p>
              </div>
              <div v-if="!isEditingUser" class="space-y-1.5">
                <label class="modal-label">كلمة المرور <span class="text-rose-500">*</span></label>
                <input v-model="userForm.password" type="password" class="form-input-modern font-black" :class="{ 'border-rose-400': formErrors.password }" placeholder="••••••••" />
                <p v-if="formErrors.password" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ formErrors.password }}</p>
              </div>
            </div>

            <!-- Roles Selection -->
            <div class="space-y-4">
              <label class="modal-label">تعيين الأدوار والصلاحيات <span class="text-rose-500">*</span></label>
              <div class="grid grid-cols-2 gap-3">
                <div v-for="role in filteredRoleOptions" :key="role.id" class="flex items-center gap-3 p-4 rounded-2xl border-2 border-slate-50 hover:border-blue-100 transition-all cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/30">
                  <input type="checkbox" :value="role.id" v-model="userForm.roles" class="w-5 h-5 rounded-lg text-blue-600 border-slate-300 focus:ring-blue-100" />
                  <span class="text-xs font-black text-slate-700">{{ translateRole(role.name) }}</span>
                </div>
              </div>
              <p v-if="formErrors.roles" class="text-[10px] text-rose-500 font-bold px-1"><i class="fas fa-exclamation-circle ml-1"></i>{{ formErrors.roles }}</p>
              <div v-if="rolesLoading" class="text-[10px] text-slate-500 bg-blue-50 p-3 rounded-xl border border-blue-100 flex items-center gap-2">
                <BaseSpinner :size="14" color="#3b82f6" margin="0" /> جاري تحميل الأدوار...
              </div>
              <div v-else-if="!rolesLoading && roleOptions.length === 0" class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3">
                <div class="flex items-center justify-between gap-2">
                  <span><i class="fas fa-exclamation-triangle ml-1"></i> لا توجد أدوار متاحة. أنشئ دوراً من إعدادات الصلاحيات أولاً.</span>
                  <button type="button" @click="fetchRoleOptions" class="text-blue-600 hover:underline font-black text-[9px]"><i class="fas fa-sync ml-1"></i>إعادة تحميل</button>
                </div>
              </div>
            </div>

            <!-- Branch Assignment -->
            <div class="space-y-2">
              <label class="modal-label">مستودع العمل (الفرع الأساسي)</label>
              <select v-model="userForm.branch_id" class="form-select-modern font-black">
                <option :value="null">إمكانية الوصول لكامل الفروع</option>
                <option v-for="wh in branchOptions" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
              <div v-if="branchesLoading" class="text-[10px] text-slate-500 bg-blue-50 p-3 rounded-xl border border-blue-100 flex items-center gap-2">
                <BaseSpinner :size="14" color="#3b82f6" margin="0" /> جاري تحميل الفروع...
              </div>
              <div v-else-if="!branchesLoading && branchOptions.length === 0" class="text-[10px] text-slate-400 bg-slate-50 border border-slate-200 rounded-xl p-3 flex items-center justify-between">
                <span>لا توجد فروع متاحة حالياً.</span>
                <button type="button" @click="fetchBranchOptions" class="text-blue-600 hover:underline font-black text-[9px]"><i class="fas fa-sync ml-1"></i>إعادة تحميل</button>
              </div>
            </div>
          </div>

          <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button @click="closeUserModal" class="px-8 py-3 rounded-xl text-xs font-black text-slate-500 hover:bg-white transition-all border border-slate-200">إلغاء</button>
            <button @click="saveUser" :disabled="isSaving" class="px-10 py-3 bg-blue-600 text-white rounded-xl font-black text-xs shadow-xl shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2 disabled:opacity-70">
              <BaseSpinner v-if="isSaving" :size="14" color="#fff" margin="0" />
              <i v-else class="fas fa-save"></i>
              <span>{{ isEditingUser ? 'تحديث الحساب' : 'إضافة الموظف' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useAuthStore } from '@/stores/auth';
import { useBranchStore } from '@/stores/branch';
import { useUserStore } from '@/stores/user/userStore';
import { useRoleStore } from '@/stores/role/roleStore';
import { useAccountStore } from '@/stores/account/accountStore';
import { useSettingsStore } from '@/stores/settings/settingsStore'
import { getAvailablePrinters, getQzPrinterName, setQzPrinterName, printDocument } from '@/utils/PrintService';
import { getBuilderByTemplate, buildReturnHtml, buildPurchaseHtml } from '@/utils/printTemplates';
import { clearPrintConfigCache } from '@/utils/printTemplates/printConfig';
import { getImageUrl } from '@/utils/imageHelpers';
import { useCompanyCurrency } from '@/composables/useCompanyCurrency'
import { useToast } from '@/composables/useToast'
import { useDirtyTracking } from '@/composables/useSettings'
import { getAvailableCurrencies, getCurrencySymbol, SUPPORTED_CURRENCIES } from '@/config/currencies'
import BaseSpinner from '../../components/ui/BaseSpinner.vue'
import AlertService from '@/services/AlertService'
import { storeToRefs } from 'pinia'

// ─── Core Setup ───────────────────────────────────────────────────────────────
const route = useRoute();
const router = useRouter();
const { showToast } = useToast()
const authStore = useAuthStore();
const branchStore = useBranchStore();
const userStore = useUserStore();
const roleStore = useRoleStore();
const accountStore = useAccountStore();
const settingsStore = useSettingsStore();
const isLoading = ref(false)
const isSaving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const logoPreview = ref(null)
const logoFile = ref(null)
const activeTab = ref(route.query.tab || 'general-settings')
const errors = ref({})
const searchTerm = ref('')

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const tabs = [
  { id: 'general-settings',  label: 'الإعدادات العامة',     icon: 'fas fa-cog' },
  { id: 'invoice-settings',  label: 'الفواتير والضرائب',     icon: 'fas fa-file-invoice' },
  { id: 'pos-settings',      label: 'نقطة البيع (POS)',      icon: 'fas fa-cash-register' },
  { id: 'accounting-settings', label: 'الحسابات المحاسبية', icon: 'fas fa-balance-scale' },
  { id: 'users-settings',    label: 'إدارة المستخدمين',      icon: 'fas fa-users-cog' },
  { id: 'printers-settings', label: 'إعدادات الطابعات',      icon: 'fas fa-print' },
]

const filteredTabs = computed(() => {
  const q = (searchTerm.value || '').trim()
  if (!q) return tabs
  return tabs.filter(t => t.label.includes(q) || t.id.includes(q))
})

// ─── Print Mode ───────────────────────────────────────────────────────────────
const printMode       = ref('browser')
const qzPrinterName   = ref('')
const qzPrinters      = ref([])
const qzPrintersLoading = ref(false)
const qzConnectError  = ref('')

const loadQzPrinters = async () => {
  qzPrintersLoading.value = true
  qzConnectError.value = ''
  try {
    qzPrinters.value = await getAvailablePrinters()
    if (!qzPrinters.value.length) qzConnectError.value = 'لم يتم العثور على طابعات — تأكد من تشغيل QZ Tray'
  } catch {
    qzConnectError.value = 'فشل الاتصال بـ QZ Tray — تأكد من تثبيته وتشغيله'
  } finally {
    qzPrintersLoading.value = false
  }
}

// ─── Settings State ───────────────────────────────────────────────────────────
const general = ref({
  businessName: '', email: '', phone: '', address: '',
  currency: 'EGP', timezone: 'Asia/Riyadh',
  dateFormat: 'DD/MM/YYYY', timeFormat: '12', logo: null,
})

const invoice = ref({
  taxNumber: '', taxRate: 15, taxName: 'ضريبة القيمة المضافة',
  taxEnabled: true, footerText: 'شكراً لتعاملكم معنا',
  invoicePrefix: 'INV-', nextInvoiceNumber: 1001, showTaxInPrice: true,
})

const posSession = ref({
  enforceForRoles: [],
  mode: '',
  requireApproval: false,
  sessionTypeMode: '',
  periodCutoff: '15:00',
  allowManagerOverride: false,
  branchApprovals: {},
})

const printers = ref({
  printHeader: true, printFooter: true,
})

const printTemplate = ref(localStorage.getItem('pos_print_template') || 'thermal-compact')
const previewType   = ref('sale')

const printTexts = ref({
  headerText: localStorage.getItem('pos_print_header_text') || '',
  footerText: localStorage.getItem('pos_print_footer_text') || 'شكراً لتعاملكم معنا',
  termsText:  localStorage.getItem('pos_print_terms_text')  || '',
})

// ─── Accounting ───────────────────────────────────────────────────────────────
const accounting = ref({
  cogsAccountId: null,
  inventoryAccountId: null,
  inventoryAdjustmentAccountId: null,
})

const { accounts } = storeToRefs(accountStore);

// Reactive role options from store
const roleOptions = computed(() => roleStore.roles);

// Computed: missing required accounting keys
const accountingMissingKeys = computed(() => {
  const missing = []
  if (!accounting.value.cogsAccountId)                 missing.push('cogs_account_id')
  if (!accounting.value.inventoryAccountId)             missing.push('inventory_account_id')
  if (!accounting.value.inventoryAdjustmentAccountId)       missing.push('inventory_adjustment_account_id')
  return missing
})

const accountingKeyLabels = {
  cogs_account_id: 'حساب تكلفة البضاعة المباعة (COGS)',
  inventory_account_id: 'حساب المخزون',
  inventory_adjustment_account_id: 'حساب تسويات/فروق المخزون',
}

// ─── Users State ──────────────────────────────────────────────────────────────
const users = ref([]);
const usersLoading = ref(false);
const usersPage = ref(1);
const usersLimit = ref(10);
const usersTotal = ref(0);
const showUserModal = ref(false);
const isEditingUser = ref(false);
const rolesLoading = ref(false);
const branchOptions = computed(() => branchStore.branches.map(w => ({ id: w.id, name: w.name })))
const branchesLoading = ref(false)
const userForm = ref({ id: null, name: '', username: '', email: '', password: '', roles: [], branch_id: null })
const formErrors = ref({})

// ─── Dirty Tracking ───────────────────────────────────────────────────────────
const { isDirty, isSectionDirty, setSnapshotsFromCurrent, resetSectionToSnapshot } = useDirtyTracking({
  general, invoice, pos: posSession, printers, accounting, users,
})

// ─── Role Filters ─────────────────────────────────────────────────────────────
const excludedPosRoleNames = ['super_admin', 'مدير النظام']

const filteredPosRoleOptions = computed(() => {
  try {
    return (roleOptions.value || []).filter(r => {
      const n = String(r.name || '').trim().toLowerCase()
      return !excludedPosRoleNames.some(x => n === String(x).toLowerCase())
    })
  } catch (_) { return roleOptions.value || [] }
})

const filteredRoleOptions = computed(() => {
  try {
    const userData = authStore.user?.user || authStore.user
    const currentUserRole = (userData?.role || '').toLowerCase()
    const isSuperAdmin = ['super_admin', 'manager'].includes(currentUserRole)
    return (roleOptions.value || []).filter(r => {
      const roleName = String(r.name || '').trim().toLowerCase()
      if (!isSuperAdmin && ['super_admin', 'admin_system'].includes(roleName)) return false
      return true
    })
  } catch (_) { return roleOptions.value || [] }
})

// Guard: strip excluded roles from posSession.enforceForRoles if they somehow slip in
watch(() => posSession.value.enforceForRoles, (vals) => {
  try {
    const excludedIds = new Set(
      (roleOptions.value || [])
        .filter(r => excludedPosRoleNames.map(x => x.toLowerCase()).includes(String(r.name || '').trim().toLowerCase()))
        .map(r => Number(r.id))
    )
    const cleaned = (vals || []).filter(v => !excludedIds.has(Number(v)))
    if (cleaned.length !== (vals || []).length) posSession.value.enforceForRoles = cleaned
  } catch (_) { /* ignore */ }
}, { deep: true })

// ─── Role Translations ────────────────────────────────────────────────────────
const roleTranslations = {
  super_admin: 'مدير النظام', admin: 'مدير', manager: 'مدير الفرع',
  user: 'مستخدم', cashier: 'كاشير', finance_officer: 'مسؤول مالي',
  inventory_clerk: 'مسؤول المخزون', accountant: 'محاسب',
  inventory: 'مخزون', client: 'عميل',
}
const translateRole = (role) => {
  if (!role) return role
  return roleTranslations[String(role).toLowerCase().trim()] || role
}

// ─── Tab Navigation ───────────────────────────────────────────────────────────
function sectionKeyFromTab(tabId) {
  return {
    'general-settings':    'general',
    'invoice-settings':    'invoice',
    'pos-settings':        'pos',
    'users-settings':      'users',
    'printers-settings':   'printers',
    'accounting-settings': 'accounting',
  }[tabId] || null
}

function setActiveTab(tabId) {
  const currentKey = sectionKeyFromTab(activeTab.value)
  if (currentKey && isSectionDirty(currentKey)) {
    const ok = AlertService.confirm(
      'هناك تغييرات غير محفوظة في هذا القسم. هل تريد المتابعة بدون حفظ؟',
      'تغييرات غير محفوظة'
    )
    if (!ok) return
  }
  activeTab.value = tabId
}

// Sync URL query param with activeTab
watch(() => route.query.tab, (newTab) => {
  if (newTab && newTab !== activeTab.value) activeTab.value = String(newTab)
})

watch(activeTab, (tab) => {
  if (tab === 'users-settings') {
    if (!roleOptions.value.length) fetchRoleOptions();
    if (!branchOptions.value.length) fetchBranchOptions();
    if (!users.value.length) fetchUsersList();
  }
  if (tab === 'pos-settings' && !roleOptions.value.length) fetchRoleOptions();
});

// ─── Save Bar Handlers ────────────────────────────────────────────────────────
async function saveActiveTab() {
  const key = sectionKeyFromTab(activeTab.value)
  if (!key) { toast.info('لا يوجد ما يتم حفظه في هذا التبويب'); return }
  if (!isSectionDirty(key)) { toast.info('لا توجد تغييرات لحفظها'); return }

  if (key === 'general')     await saveGeneralSettings()
  else if (key === 'invoice')     await saveInvoiceSettings()
  else if (key === 'pos')         await savePosSettings()
  else if (key === 'printers')    await savePrinterSettings()
  else if (key === 'accounting')  await saveAccountingSettings()
  else if (key === 'users') {
    toast.info('سيتم تفعيل حفظ إعدادات المستخدمين لاحقاً.')
    setSnapshotsFromCurrent()
  }
}

function discardActiveTab() {
  const key = sectionKeyFromTab(activeTab.value)
  if (!key || !isSectionDirty(key)) return
  const ok = AlertService.confirm(
    'سيتم تجاهل التغييرات غير المحفوظة في هذا القسم. هل أنت متأكد؟',
    'تجاهل التغييرات'
  )
  if (!ok) return
  resetSectionToSnapshot(key)
}

// ─── Toast Helpers ────────────────────────────────────────────────────────────
const showError = (message) => {
  errorMessage.value = message
  showToast(message, 'error', 5000)
  setTimeout(() => { errorMessage.value = '' }, 5000)
}

const showSuccess = (message) => {
  showToast(message, 'success', 3000)
}

// ─── Logo Handling ────────────────────────────────────────────────────────────
const handleLogoUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const validTypes = ['image/jpeg', 'image/png', 'image/gif']
  if (!validTypes.includes(file.type)) {
    showError('نوع الملف غير مدعوم. يرجى اختيار صورة بصيغة JPG أو PNG أو GIF')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    showError('حجم الملف كبير جداً. الحد الأقصى المسموح به هو 2 ميجابايت')
    return
  }
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

const removeLogo = () => {
  logoFile.value = null
  logoPreview.value = null
  general.value.logo = null
  const fileInput = document.querySelector('input[type="file"]')
  if (fileInput) fileInput.value = ''
  // Clear from localStorage immediately
  try {
    localStorage.removeItem('pos_company_logo')
  } catch (e) { /* ignore */ }
}

// ─── Reset Form ───────────────────────────────────────────────────────────────
const resetForm = () => {
  general.value = {
    businessName: general.value.businessName,
    email: '', phone: '', address: '',
    currency: 'EGP', timezone: 'Asia/Riyadh',
    dateFormat: 'DD/MM/YYYY', timeFormat: '12', logo: null,
  }
  logoFile.value = null
  logoPreview.value = null
  errors.value = {}
}

// ─── Computed: Access Control ─────────────────────────────────────────────────
const hasSettingsAccess = computed(() => {
  if (!authStore.user) return false

  // Explicit permission check (most reliable)
  if (typeof authStore.hasPermission === 'function' &&
      (authStore.hasPermission('settings.view') || authStore.hasPermission('settings.manage'))) {
    return true
  }

  // Store-level admin flag
  if (typeof authStore.isAdmin !== 'undefined' && authStore.isAdmin) return true

  // Role-based fallback
  const userData = authStore.user.user || authStore.user
  const role = (userData?.role || '').toLowerCase()
  const roleId = typeof userData?.role_id === 'string'
    ? parseInt(userData.role_id, 10)
    : userData?.role_id

  return roleId === 1 ||
    ['super_admin', 'admin', 'administrator', 'manager', 'مدير', 'مدير_النظام'].includes(role)
})

// ─── Data Fetching: Roles / Branches / Users ────────────────────────────────
async function fetchRoleOptions() {
  if (!hasSettingsAccess.value) return
  try {
    rolesLoading.value = true
    await roleStore.fetchRoles();
    // Role options are now cached in roleStore
  } catch (err) {
    console.error('Error fetching roles:', err)
    showError(err.response?.data?.message || 'فشل تحميل الأدوار.')
  } finally {
    rolesLoading.value = false
  }
}

async function fetchBranchOptions() {
  if (!hasSettingsAccess.value) return
  try {
    branchesLoading.value = true
    await branchStore.fetchBranches();
  } catch (err) {
    console.error('Error fetching branches:', err)
    showError(err.response?.data?.message || 'فشل تحميل الفروع.')
  } finally {
    branchesLoading.value = false
  }
}

async function fetchUsersList() {
  if (!hasSettingsAccess.value) return
  try {
    usersLoading.value = true
    const usersResponse = await userStore.fetchUsers({ page: usersPage.value, limit: usersLimit.value });
    
    if (usersResponse.status === 'success') {
      const userData = usersResponse.data;
      usersTotal.value = userData?.total || 0;
      usersPage.value = userData?.page || usersPage.value;
      usersLimit.value = userData?.limit || usersLimit.value;
      users.value = (userData?.items || []).map(u => ({
      ...u,
      roles: Array.isArray(u.roles)
        ? u.roles
        : (typeof u.roles === 'string' && u.roles.length ? u.roles.split(',').map(r => r.trim()) : []),
    }));
    setSnapshotsFromCurrent();
  }
  } catch (error) {
    console.error('Error fetching users:', error)
    showError(error.response?.data?.message || 'فشل تحميل قائمة المستخدمين.')
  } finally {
    usersLoading.value = false
  }
}

function nextUsersPage() {
  const maxPage = Math.max(1, Math.ceil(usersTotal.value / usersLimit.value))
  if (usersPage.value < maxPage) { usersPage.value += 1; fetchUsersList() }
}

function prevUsersPage() {
  if (usersPage.value > 1) { usersPage.value -= 1; fetchUsersList() }
}

// ─── User Modal Actions ───────────────────────────────────────────────────────
async function addUser() {
  formErrors.value = {}
  isEditingUser.value = false
  userForm.value = { id: null, name: '', username: '', email: '', password: '', roles: [], branch_id: null }
  if (!roleOptions.value.length) await fetchRoleOptions()
  if (!branchOptions.value.length) await fetchBranchOptions()
  showUserModal.value = true
}

async function editUser(user) {
  formErrors.value = {}
  isEditingUser.value = true
  if (!roleOptions.value.length) await fetchRoleOptions()
  if (!branchOptions.value.length) await fetchBranchOptions()

  // Convert role names → IDs if needed
  let roleIds = []
  if (Array.isArray(user.roles)) {
    if (user.roles.length && typeof user.roles[0] === 'string') {
      const nameToId = new Map(roleOptions.value.map(r => [String(r.name).trim().toLowerCase(), r.id]))
      roleIds = user.roles.map(n => nameToId.get(String(n).trim().toLowerCase())).filter(v => !!v)
    } else {
      roleIds = user.roles
    }
  }
  userForm.value = { id: user.id, name: user.name || '', username: user.username || '', email: user.email || '', password: '', roles: roleIds, branch_id: user.branch_id ?? null }
  showUserModal.value = true
}

function closeUserModal() {
  showUserModal.value = false
}

function validateUserForm() {
  const e = {}
  if (!userForm.value.name?.trim())     e.name     = 'يرجى إدخال الاسم'
  if (!userForm.value.username?.trim()) e.username  = 'يرجى إدخال اسم المستخدم'
  if (!userForm.value.email?.trim())    e.email     = 'يرجى إدخال البريد الإلكتروني'
  if (!isEditingUser.value && !userForm.value.password?.trim()) e.password = 'يرجى إدخال كلمة المرور'
  if (!Array.isArray(userForm.value.roles) || userForm.value.roles.length === 0)
    e.roles = 'يرجى اختيار دور واحد على الأقل'
  formErrors.value = e
  return Object.keys(e).length === 0
}

async function saveUser() {
  if (!validateUserForm()) return
  isSaving.value = true
  try {
    const payload = {
      name: userForm.value.name,
      username: userForm.value.username,
      email: userForm.value.email,
      roles: userForm.value.roles,
      branch_id: userForm.value.branch_id || null,
    }
    if (!isEditingUser.value) payload.password = userForm.value.password

    const resp = isEditingUser.value
      ? await userStore.updateUser(userForm.value.id, payload)
      : await userStore.createUser(payload)

    if (resp?.status === 'success') {
      showSuccess(isEditingUser.value ? 'تم تحديث المستخدم بنجاح' : 'تم إضافة المستخدم بنجاح')
      closeUserModal()
      await fetchUsersList()
      setSnapshotsFromCurrent()
    } else {
      showError(resp?.message || 'حدث خطأ غير متوقع')
    }
  } catch (error) {
    console.error('Error saving user:', error)
    showError(error?.message || 'فشل حفظ بيانات المستخدم.')
  } finally {
    isSaving.value = false
  }
}

// ─── Settings Fetch ───────────────────────────────────────────────────────────
async function fetchSettings() {
  if (!hasSettingsAccess.value) return
  isLoading.value = true
  errorMessage.value = ''
  try {
    const response = await settingsStore.fetchSettings();
    if (response.status === 'success' && response.data && response.data.settings) {
      const s = response.data;

      general.value = {
        businessName: s['company.name'] || s['company_name'] || '',
        email:        s['company.email'] || '',
        phone:        s['company.phone'] || '',
        address:      s['company.address'] || '',
        currency:     s['company.currency'] || 'EGP',
        timezone:     s['app.timezone'] || 'Asia/Riyadh',
        dateFormat:   s['app.date_format'] || 'DD/MM/YYYY',
        timeFormat:   s['app.time_format'] || '12',
        logo:         s['company.logo'] || null,
      }
      logoPreview.value = getImageUrl(general.value.logo)

      invoice.value = {
        taxNumber:         s['tax.tax_number'] || '',
        taxRate:           parseFloat(s['tax.tax_rate']) || 15,
        taxName:           s['tax.tax_name'] || 'ضريبة القيمة المضافة',
        taxEnabled:        s['tax.tax_enabled'] === '1',
        footerText:        s['invoice.footer_text'] || 'شكراً لتعاملكم معنا',
        invoicePrefix:     s['invoice.prefix'] || 'INV-',
        nextInvoiceNumber: Number(s['invoice.next_number']) || 1001,
        showTaxInPrice:    s['invoice.show_tax_in_price'] !== '0',
      }

      printMode.value    = s['print.mode'] || 'browser'
      qzPrinterName.value = s['print.qztray_printer'] || getQzPrinterName()
      printers.value = {
        printHeader: s['printer.print_header'] !== '0',
        printFooter: s['printer.print_footer'] !== '0',
      }

      // POS sessions enforcement — handles JSON array or comma-separated string
      ;(function parseEnforceRoles() {
        const raw = s['pos.sessions.enforce_for_roles']
        let parsed = []
        if (Array.isArray(raw)) {
          parsed = raw
        } else if (typeof raw === 'string') {
          const trimmed = raw.trim()
          try {
            if (trimmed.startsWith('[')) parsed = JSON.parse(trimmed)
            else if (trimmed.length) parsed = trimmed.split(',').map(x => Number(x.trim())).filter(n => !isNaN(n))
          } catch (_) { /* ignore */ }
        }
        posSession.value.enforceForRoles = parsed.map(n => Number(n)).filter(n => !isNaN(n))
      })()

      posSession.value.mode                 = (s['pos.sessions.mode'] || '').toString()
      posSession.value.requireApproval      = s['pos.require_approval'] === '1'
      posSession.value.sessionTypeMode      = (s['pos.sessions.session_type_mode'] || '').toString()
      posSession.value.periodCutoff         = (s['pos.sessions.period_cutoff'] || '15:00').toString()
      posSession.value.allowManagerOverride = s['pos.sessions.allow_manager_override'] === '1'
      // Per-branch approval overrides: stored as JSON object {branch_id: bool}
      try {
        const raw = s['pos.branch_approvals']
        posSession.value.branchApprovals = raw ? JSON.parse(raw) : {}
      } catch (_) { posSession.value.branchApprovals = {} }

      accounting.value = {
        cogsAccountId:                   s['cogs_account_id'] || null,
        inventoryAccountId:              s['inventory_account_id'] || null,
        inventoryAdjustmentAccountId:    s['inventory_adjustment_account_id'] || null,
      }

      printTexts.value = {
        headerText: s['print.header_text'] || printTexts.value.headerText,
        footerText: s['print.footer_text'] || printTexts.value.footerText,
        termsText:  s['print.terms_text']  || printTexts.value.termsText,
      }

      // Sync key values to localStorage for POS print headers
      try {
        if (general.value.businessName) localStorage.setItem('pos_company_name', general.value.businessName)
        if (general.value.phone)        localStorage.setItem('pos_company_phone', general.value.phone)
        if (invoice.value.taxNumber)    localStorage.setItem('pos_company_tax_number', invoice.value.taxNumber)
        if (general.value.logo) {
          localStorage.setItem('pos_company_logo', getImageUrl(general.value.logo))
        }
      } catch (e) { /* ignore quota errors */ }

      setSnapshotsFromCurrent()
    }
  } catch (error) {
    console.error('Error fetching settings:', error)
    showError(error.response?.data?.message || 'فشل تحميل الإعدادات. يرجى المحاولة مرة أخرى.')
  } finally {
    isLoading.value = false
  }
}

// ─── Save Methods ─────────────────────────────────────────────────────────────
async function saveGeneralSettings() {
  errors.value = {}
  if (!general.value.businessName?.trim()) {
    errors.value.businessName = 'يرجى إدخال اسم النشاط التجاري'
    return
  }
  isSaving.value = true
  try {
    const formData = new FormData()
    const settings = {
      'company.name':            general.value.businessName,
      'company.email':           general.value.email,
      'company.phone':           general.value.phone,
      'company.address':         general.value.address,
      'company.currency':        general.value.currency,
      'company.currency_code':   general.value.currency,
      'company.currency_symbol': SUPPORTED_CURRENCIES[general.value.currency]?.symbol || '€',
      'app.timezone':            general.value.timezone,
      'app.date_format':         general.value.dateFormat,
      'app.time_format':         general.value.timeFormat,
    }
    if (logoFile.value) {
      formData.append('logo', logoFile.value)
    } else if (general.value.logo === null) {
      settings['company.logo'] = ''
    }
    formData.append('settings', JSON.stringify(settings))

    const response = await settingsStore.uploadLogo(formData)
    if (response.status === 'success') {
      showSuccess('تم حفظ الإعدادات بنجاح')
      try {
        localStorage.setItem('pos_company_name',    general.value.businessName || '')
        localStorage.setItem('pos_company_phone',   general.value.phone || '')
        localStorage.setItem('pos_company_email',   general.value.email || '')
        localStorage.setItem('pos_company_address', general.value.address || '')
      } catch (e) { /* ignore */ }
      const { refreshSettings } = useCompanyCurrency()
      await refreshSettings()
      await settingsStore.fetchSettings({ force: true })
      // Update logoPreview immediately with the new logo from store
      const newLogo = settingsStore.settings?.['company.logo']
      if (newLogo) {
        general.value.logo = newLogo
        logoPreview.value = getImageUrl(newLogo)
      }
      setSnapshotsFromCurrent()
    } else {
      showError(response?.message || 'حدث خطأ أثناء حفظ الإعدادات.')
    }
  } catch (error) {
    console.error('Error saving general settings:', error)
    showError(error.response?.data?.message || 'حدث خطأ أثناء حفظ الإعدادات.')
  } finally {
    isSaving.value = false
  }
}

async function saveInvoiceSettings() {
  isSaving.value = true
  try {
    const settings = {
      'company.tax_number':          invoice.value.taxNumber,
      'company.tax_rate':            invoice.value.taxRate,
      'company.tax_name':            invoice.value.taxName,
      'company.tax_enabled':         invoice.value.taxEnabled ? '1' : '0',
      'invoice.footer_text':         invoice.value.footerText,
      'invoice.prefix':              invoice.value.invoicePrefix,
      'invoice.next_number':         invoice.value.nextInvoiceNumber,
      'invoice.show_tax_in_price':   invoice.value.showTaxInPrice ? '1' : '0',
    }
    const response = await settingsStore.updateSettings(settings)
    if (response.status === 'success') {
      showSuccess('تم حفظ إعدادات الفواتير بنجاح')
      const { refreshSettings } = useCompanyCurrency()
      await refreshSettings()
      await fetchSettings()
      setSnapshotsFromCurrent()
    } else {
  showError(response?.message || 'حدث خطأ أثناء حفظ إعدادات الفواتير.')
   }
  } catch (error) {
    console.error('Error saving invoice settings:', error)
    showError(error.response?.data?.message || 'حدث خطأ أثناء حفظ إعدادات الفواتير.')
  } finally {
    isSaving.value = false
  }
}

async function savePosSettings() {
  isSaving.value = true
  try {
    const enforce = (posSession.value.enforceForRoles || [])
      .filter(n => !isNaN(Number(n))).map(n => Number(n))

    const settings = {
      'pos.sessions.enforce_for_roles':       JSON.stringify(enforce),
      'pos.sessions.mode':                    posSession.value.mode || '',
      'pos.require_approval':                 posSession.value.requireApproval ? '1' : '0',
      'pos.sessions.session_type_mode':       posSession.value.sessionTypeMode || '',
      'pos.sessions.period_cutoff':           posSession.value.periodCutoff || '15:00',
      'pos.sessions.allow_manager_override':  posSession.value.allowManagerOverride ? '1' : '0',
      'pos.branch_approvals':                 JSON.stringify(posSession.value.branchApprovals || {}),
    }
    const response = await settingsStore.updateSettings(settings)
    if (response.status === 'success') {
      showSuccess('تم حفظ إعدادات نقطة البيع بنجاح')
      const { refreshSettings } = useCompanyCurrency()
      await refreshSettings()
      await fetchSettings()
      setSnapshotsFromCurrent()
    } else {
  showError(response?.message || 'حدث خطأ أثناء حفظ إعدادات POS.')
   }
  } catch (error) {
    console.error('Error saving POS settings:', error)
    showError(error.response?.data?.message || 'حدث خطأ أثناء حفظ إعدادات POS.')
  } finally {
    isSaving.value = false
  }
}

async function savePrinterSettings() {
  isSaving.value = true
  try {
    const settings = {
      'print.mode':              printMode.value,
      'print.qztray_printer':   qzPrinterName.value,
      'printer.print_header':   printers.value.printHeader ? '1' : '0',
      'printer.print_footer': printers.value.printFooter ? '1' : '0',
      'print.header_text':    printTexts.value.headerText,
      'print.footer_text':    printTexts.value.footerText,
      'print.terms_text':     printTexts.value.termsText,
    }
    const response = await settingsStore.updateSettings(settings)
    if (response.status === 'success') {
      localStorage.setItem('print.mode',                 printMode.value)
      setQzPrinterName(qzPrinterName.value)
      localStorage.setItem('pos_print_template',         printTemplate.value)
      localStorage.setItem('pos_print_header_enabled',   printers.value.printHeader ? '1' : '0')
      localStorage.setItem('pos_print_footer_enabled',   printers.value.printFooter ? '1' : '0')
      localStorage.setItem('pos_print_header_text',      printTexts.value.headerText || '')
      localStorage.setItem('pos_print_footer_text',      printTexts.value.footerText || '')
      localStorage.setItem('pos_print_terms_text',       printTexts.value.termsText  || '')
      showSuccess('تم حفظ إعدادات الطابعات بنجاح')
      const { refreshSettings } = useCompanyCurrency()
      await refreshSettings()
      await fetchSettings()
      setSnapshotsFromCurrent()
    } else {
  showError(response?.message || 'حدث خطأ أثناء حفظ إعدادات الطابعة.')
  }
  } catch (error) {
    console.error('Error saving printer settings:', error)
    showError(error.response?.data?.message || 'حدث خطأ أثناء حفظ إعدادات الطابعات.')
  } finally {
    isSaving.value = false
  }
}

async function saveAccountingSettings() {
  isSaving.value = true
  try {
    const settings = {
      cogs_account_id:                    accounting.value.cogsAccountId,
      inventory_account_id:               accounting.value.inventoryAccountId,
      inventory_adjustment_account_id:    accounting.value.inventoryAdjustmentAccountId,
    }
    const response = await settingsStore.updateSettings(settings)
    if (response.status === 'success') {
      showSuccess('تم حفظ إعدادات الحسابات المحاسبية بنجاح')
      const { refreshSettings } = useCompanyCurrency()
      await refreshSettings()
      await fetchSettings()
      setSnapshotsFromCurrent()
    }
  } catch (error) {
    console.error('Error saving accounting settings:', error)
    showError(error.response?.data?.message || 'حدث خطأ أثناء حفظ إعدادات الحسابات المحاسبية.')
  } finally {
    isSaving.value = false
  }
}

// ─── Accounting Accounts Loader ───────────────────────────────────────────────
async function loadAccountingAccounts() {
  try {
    await accountStore.fetchGroupedAccounts();
    // Accounting accounts are now cached in accountStore
  } catch (error) {
    console.error('Error loading accounting accounts:', error)
    showError('فشل تحميل الحسابات المحاسبية')
  }
}

// ─── Temporarily sync form values → localStorage → call fn → restore ─────────
function withFormLS(fn) {
  const KEYS = [
    'pos_print_header_enabled', 'pos_print_footer_enabled',
    'pos_print_header_text',    'pos_print_footer_text',
    'pos_print_terms_text',
    'pos_company_name',         'pos_company_phone',
    'pos_company_tax_number',   'pos_company_logo',
    'pos_company_address',
  ]
  const prev = Object.fromEntries(KEYS.map(k => [k, localStorage.getItem(k)]))
  const logo = getImageUrl(general.value.logo || prev['pos_company_logo']) || ''
  localStorage.setItem('pos_print_header_enabled', printers.value.printHeader ? '1' : '0')
  localStorage.setItem('pos_print_footer_enabled', printers.value.printFooter ? '1' : '0')
  localStorage.setItem('pos_print_header_text',    printTexts.value.headerText || '')
  localStorage.setItem('pos_print_footer_text',    printTexts.value.footerText || '')
  localStorage.setItem('pos_print_terms_text',     printTexts.value.termsText  || '')
  localStorage.setItem('pos_company_name',         general.value.businessName  || prev['pos_company_name'] || 'اسم المتجر')
  localStorage.setItem('pos_company_phone',        general.value.phone         || prev['pos_company_phone'] || '')
  localStorage.setItem('pos_company_tax_number',   invoice.value.taxNumber     || prev['pos_company_tax_number'] || '')
  localStorage.setItem('pos_company_address',      general.value.address       || prev['pos_company_address'] || '')
  localStorage.setItem('pos_company_logo',         logo)
  clearPrintConfigCache()
  const result = fn()
  KEYS.forEach(k => { if (prev[k] === null) localStorage.removeItem(k); else localStorage.setItem(k, prev[k]) })
  clearPrintConfigCache()
  return result
}

// ─── Print Test ───────────────────────────────────────────────────────────────
async function testPrint() {
  const taxR = Number(invoice.value.taxRate || 15)
  const sub  = 50
  const tax  = +(sub * taxR / 100).toFixed(2)
  const html = withFormLS(() => {
    const sampleSale = {
      invoice_number: `${invoice.value.invoicePrefix || 'INV-'}DEMO`,
      sale_date:      new Date().toISOString(),
      customer_name:  'عميل تجريبي',
      payment_method: 'نقداً',
      discount_type:  'fixed',
      discount_value: 5,
      tax_rate:       taxR,
      tax_amount:     tax,
      total_amount:   sub,
      net_total_amount: +(sub - 5 + tax).toFixed(2),
      paid_amount:    +(sub - 5 + tax).toFixed(2),
      change_amount:  0,
      items: [
        { product_name: 'منتج تجريبي أ', quantity: 1, sale_price: 25, net_price: 25, net_total: 25 },
        { product_name: 'منتج تجريبي ب', quantity: 2, sale_price: 12.5, net_price: 12.5, net_total: 25 },
      ],
    }
    return getBuilderByTemplate(printTemplate.value || 'thermal-compact')(sampleSale)
  })
  await printDocument(html)
}

// ─── Currency Symbol Update ───────────────────────────────────────────────────
const updateCurrencySymbol = () => {
  // triggers re-render with new selection; actual save happens on form submit
}

// ─── Live Print Preview ───────────────────────────────────────────────────────
const previewHtml = computed(() => {
  const type = previewType.value
  const taxR = Number(invoice.value.taxRate || 15)
  const sub  = 50
  const tax  = +(sub * taxR / 100).toFixed(2)

  return withFormLS(() => {
    let html
    if (type === 'return') {
      html = buildReturnHtml({
        return_number:  'RET-001',
        return_date:    '2025-08-20T15:30:00',
        customer_name:  'أحمد محمد',
        return_reason:  'منتج تالف',
        grand_total:    80,
        total_amount:   80,
        items: [
          { product_name: 'منتج مرتجع أ', quantity: 1, unit_price: 50, net_total: 50 },
          { product_name: 'منتج مرتجع ب', quantity: 2, unit_price: 15, net_total: 30 },
        ],
      })
    } else if (type === 'purchase') {
      html = buildPurchaseHtml({
        invoice_number: 'PUR-001',
        invoice_date:   '2025-08-20T15:30:00',
        supplier_name:  'المورد الرئيسي',
        status:         'partial',
        total_amount:   350,
        paid_amount:    200,
        items: [
          { product_name: 'مادة خام أ', quantity: 10, price: 20, unit_price: 20 },
          { product_name: 'مادة خام ب', quantity: 5,  price: 30, unit_price: 30 },
        ],
      })
    } else {
      html = getBuilderByTemplate(printTemplate.value || 'thermal-compact')({
        invoice_number:    `${invoice.value.invoicePrefix || 'INV-'}12345`,
        sale_date:         '2025-08-20T15:30:00',
        customer_name:     'أحمد محمد',
        payment_method:    'نقداً',
        discount_type:     'fixed',
        discount_value:    5,
        tax_rate:          taxR,
        tax_amount:        tax,
        total_amount:      sub,
        net_total_amount:  +(sub - 5 + tax).toFixed(2),
        paid_amount:       +(sub - 5 + tax).toFixed(2),
        change_amount:     0,
        items: [
          { product_name: 'منتج أ', quantity: 1, sale_price: 25,   net_price: 25,   net_total: 25 },
          { product_name: 'منتج ب', quantity: 2, sale_price: 12.5, net_price: 12.5, net_total: 25 },
        ],
      })
    }
    return html.replace(/<script[\s\S]*?<\/script>/gi, '')
  })
})

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onBeforeRouteLeave((to, from, next) => {
  if (isDirty.value) {
    const ok = confirm('لديك تغييرات غير محفوظة، هل تريد المغادرة؟');
    if (!ok) return next(false);
  }
  next();
});

onMounted(async () => {
  if (hasSettingsAccess.value) {
    await Promise.all([fetchSettings(), loadAccountingAccounts(), fetchRoleOptions()])
  }
});
</script>

<style scoped>



/* Section Layout */
.settings-section { @apply bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden; }
.section-header   { @apply px-8 py-6 border-b border-slate-50 bg-slate-50/50; }
.subsection-title { @apply text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-3; }

/* Form Components */
.form-input-modern,
.form-select-modern {
  @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300
         focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm;
}
.modal-label {
  @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1;
}

/* Action Buttons */
.btn-primary-modern {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 text-white rounded-2xl
         text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all
         active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed;
}
.btn-outline-modern {
  @apply inline-flex items-center gap-2 px-6 py-2.5 bg-white text-slate-500 rounded-2xl
         text-xs font-black border border-slate-200 hover:bg-slate-50 transition-all;
}

/* Pagination */
.pagination-btn {
  @apply w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center
         hover:bg-slate-50 disabled:opacity-30 transition-all text-slate-500;
}

/* Modal */
.modal-overlay       { @apply fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4; }
.modal-content-modern { @apply bg-white w-full rounded-[2.5rem] shadow-2xl overflow-hidden; }

/* Scrollbar */
.custom-scroll::-webkit-scrollbar       { width: 4px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

/* Animations */
.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-modalIn { animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95) translateY(20px); }
  to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Transitions */
.fade-enter-active, .fade-leave-active   { transition: opacity 0.3s ease; }
.fade-enter-from,  .fade-leave-to        { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-enter-from,   .slide-up-leave-to     { transform: translateY(100px); opacity: 0; }

.slide-left-enter-active, .slide-left-leave-active { transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-left-enter-from,   .slide-left-leave-to     { transform: translateX(-30px); opacity: 0; }

.modal-enter-active, .modal-leave-active { transition: all 0.3s ease; }
.modal-enter-from,   .modal-leave-to     { opacity: 0; transform: scale(0.95); }
</style>