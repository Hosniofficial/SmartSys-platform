<template>
  <div class="min-h-screen bg-[#f8fafc] p-4 lg:p-8 font-cairo text-slate-700 animate-fadeIn text-right" dir="rtl">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center shadow-xl shadow-blue-100 text-white shrink-0">
          <i class="fas fa-file-invoice-dollar text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-black text-slate-900 leading-none tracking-tight">التقارير المالية والمحاسبية</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium italic">تحليل موازين المراجعة، دفاتر الأستاذ، والقوائم الختامية</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-100 shadow-sm">
        <button @click="loadAll" class="px-8 py-2.5 rounded-xl bg-blue-600 text-white text-xs font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all active:scale-95 flex items-center gap-2">
          <i class="fas fa-sync-alt"></i> تحديث التقارير
        </button>
      </div>
    </div>

    <!-- Main Filters Panel -->
    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8 mb-10 overflow-visible">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-6 items-end">
        
        <div class="lg:col-span-2 space-y-2 group">
          <label class="filter-label">من تاريخ</label>
          <div class="relative">
            <input ref="startRef" type="date" v-model="filters.start" class="form-input-modern font-bold text-sm h-12" />
            <i class="fas fa-calendar-day absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="startRef.showPicker()"></i>
          </div>
        </div>

        <div class="lg:col-span-2 space-y-2 group">
          <label class="filter-label">إلى تاريخ</label>
          <div class="relative">
            <input ref="endRef" type="date" v-model="filters.end" class="form-input-modern font-bold text-sm h-12" />
            <i class="fas fa-calendar-check absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="endRef.showPicker()"></i>
          </div>
        </div>

        <div class="lg:col-span-2 space-y-2 group">
          <label class="filter-label">حتى تاريخ (الميزانية)</label>
          <div class="relative">
            <input ref="asOfRef" type="date" v-model="filters.asOf" class="form-input-modern font-bold text-sm h-12" />
            <i class="fas fa-history absolute right-4 inset-y-0 my-auto h-fit text-slate-400 cursor-pointer group-focus-within:text-blue-500 transition-colors" @click="asOfRef.showPicker()"></i>
          </div>
        </div>

        <div class="lg:col-span-4 space-y-2">
          <label class="filter-label">حساب دفتر الأستاذ (اختياري)</label>
          <select v-model.number="filters.ledgerAccountId" class="form-select-modern h-12 font-black text-xs">
            <option value="">-- اختر حساباً من القائمة --</option>
            <optgroup label="حسابات المنشأة" v-if="tenantAccounts.length">
              <option v-for="acc in tenantAccounts" :key="'lg-tenant-'+acc.id" :value="acc.id">{{ accOptionLabel(acc) }}</option>
            </optgroup>
            <optgroup label="الحسابات العامة (الافتراضية)" v-if="globalAccounts.length">
              <option v-for="acc in globalAccounts" :key="'lg-global-'+acc.id" :value="acc.id">{{ accOptionLabel(acc) }}</option>
            </optgroup>
          </select>
        </div>

        <div class="lg:col-span-2">
           <button @click="loadAll" class="h-12 w-full bg-slate-900 text-white rounded-2xl font-black shadow-xl shadow-slate-200 hover:bg-black transition-all flex items-center justify-center active:scale-95">
             <i class="fas fa-filter ml-2"></i> تصفية
           </button>
        </div>
      </div>
    </div>

    <!-- Reports Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
      
      <!-- 1. Trial Balance (6/12) -->
      <section class="lg:col-span-7 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col">
        <div class="p-8 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
           <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight flex items-center gap-3">
             <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
             ميزان المراجعة
           </h2>
           <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] bg-white px-3 py-1 rounded-lg border border-slate-100">تحليل الأرصدة المدينة والدائنة</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-right text-sm font-cairo">
            <thead>
              <tr class="bg-slate-50/50 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter">
                <th class="px-6 py-4">كود الحساب</th>
                <th class="px-4 py-4">اسم الحساب</th>
                <th class="px-4 py-4">النوع</th>
                <th class="px-4 py-4 text-center">مدين (+)</th>
                <th class="px-6 py-4 text-center">دائن (-)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
              <tr v-for="row in trialBalance" :key="row.account_id" class="hover:bg-blue-50/30 transition-all group font-bold">
                <td class="px-6 py-4 font-black text-blue-600 font-mono text-xs uppercase">{{ row.code }}</td>
                <td class="px-4 py-4 text-slate-800 leading-none">{{ row.name }}</td>
                <td class="px-4 py-4"><span class="text-[10px] font-black text-slate-400 uppercase">{{ row.type }}</span></td>
                <td class="px-4 py-4 text-center font-black font-mono text-base text-rose-600 tracking-tighter">{{ fmt(row.total_debit) }}</td>
                <td class="px-6 py-4 text-center font-black font-mono text-base text-emerald-600 tracking-tighter">{{ fmt(row.total_credit) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 2. Income Statement (5/12) -->
      <section class="lg:col-span-5 flex flex-col gap-8">
        <!-- Income Statement Card -->
        <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white shadow-2xl relative overflow-hidden group border border-slate-800">
          <div class="absolute top-0 left-0 w-40 h-40 bg-blue-500/10 rounded-full -translate-x-20 -translate-y-20 transition-transform group-hover:scale-110"></div>
          
          <h2 class="text-xl font-black text-blue-400 uppercase tracking-widest mb-10 flex items-center gap-3">
             <i class="fas fa-chart-line"></i> ملخص قائمة الدخل
          </h2>
          
          <div class="space-y-8">
            <div class="flex justify-between items-center group/item transition-all">
              <span class="text-slate-400 font-bold uppercase tracking-widest text-xs group-hover/item:text-white">إجمالي الإيرادات (+)</span>
              <span class="text-2xl font-black font-mono tracking-tighter text-emerald-400">{{ fmt(incomeStatement.revenues) }}</span>
            </div>
            <div class="flex justify-between items-center group/item transition-all">
              <span class="text-slate-400 font-bold uppercase tracking-widest text-xs group-hover/item:text-white">إجمالي المصروفات (COGS) (-)</span>
              <span class="text-2xl font-black font-mono tracking-tighter text-rose-400">{{ fmt(incomeStatement.expenses) }}</span>
            </div>
            <div class="pt-8 border-t border-white/5 flex justify-between items-center">
              <div class="flex flex-col">
                <span class="text-sm font-black uppercase tracking-[0.2em] text-blue-400">صافي الأرباح النهائية</span>
                <span class="text-[9px] font-bold text-white/30 uppercase mt-1">Net Income (Bottom Line)</span>
              </div>
              <span class="text-4xl font-black font-mono tracking-tighter" :class="incomeStatement.net_income >= 0 ? 'text-emerald-500' : 'text-rose-500'">{{ fmt(incomeStatement.net_income) }}</span>
            </div>
          </div>
        </div>

        <!-- System Integrity / Coverage Check -->
        <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-8">
          <div class="flex items-center justify-between mb-8">
            <div>
              <h3 class="font-black text-slate-800 leading-none">تغطية ربط الفروع</h3>
              <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-2">التدقيق المحاسبي للفروع</p>
            </div>
            <button @click="loadCoverage" class="w-10 h-10 bg-slate-50 text-slate-400 rounded-xl flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all active:scale-90 shadow-sm"><i class="fas fa-rotate text-xs"></i></button>
          </div>

          <div v-if="coverage" class="space-y-6">
            <div class="flex gap-4">
               <div class="flex-1 p-4 bg-slate-50 rounded-2xl text-center border border-slate-100">
                  <p class="text-[9px] font-black text-slate-400 uppercase mb-1">إجمالي الفروع</p>
                  <p class="text-xl font-black text-slate-800 leading-none">{{ coverage.total_branchs }}</p>
               </div>
               <div class="flex-1 p-4 bg-rose-50 rounded-2xl text-center border border-rose-100">
                  <p class="text-[9px] font-black text-rose-400 uppercase mb-1">فجوات الربط</p>
                  <p class="text-xl font-black text-rose-600 leading-none">{{ coverage.missing_count }}</p>
               </div>
            </div>

            <div v-if="(coverage.missing_branchs || []).length" class="overflow-hidden rounded-2xl border border-slate-50">
              <table class="w-full text-right text-xs">
                 <thead class="bg-slate-50 text-slate-400 font-black">
                   <tr><th class="px-4 py-3">الفرع / الموقع</th><th class="px-4 py-3 text-center">حالة الربط</th></tr>
                 </thead>
                 <tbody class="divide-y divide-slate-50">
                   <tr v-for="w in coverage.missing_branchs" :key="w.id" class="font-bold">
                     <td class="px-4 py-3 text-slate-800 leading-none">{{ w.name }} <p class="text-[8px] text-slate-300 font-bold mt-1 uppercase">{{ w.location || '-' }}</p></td>
                     <td class="px-4 py-3 text-center"><span class="status-badge bg-rose-50 text-rose-500 border border-rose-100 italic">غير مرتبط</span></td>
                   </tr>
                 </tbody>
              </table>
            </div>
            <div v-else class="bg-emerald-50 border border-emerald-100 p-6 rounded-[1.5rem] flex items-center gap-4 shadow-sm shadow-emerald-50">
               <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-emerald-500 shadow-sm shrink-0"><i class="fas fa-check-double"></i></div>
               <p class="text-xs font-black text-emerald-800 uppercase leading-relaxed tracking-tight">كافة الفروع مرتبطة حسابياً بشكل مثالي.</p>
            </div>
          </div>
        </div>
      </section>

      <!-- 3. Balance Sheet (Full Width) -->
      <section class="lg:col-span-12 bg-white rounded-[3rem] shadow-sm border border-slate-100 p-8 md:p-12 relative overflow-hidden">
        <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-3 mb-10">
          <span class="w-1.5 h-6 bg-indigo-600 rounded-full"></span>
          الميزانية العمومية (Balance Sheet)
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
          <!-- Assets -->
          <div class="space-y-6">
            <h3 class="subsection-title text-blue-600 uppercase tracking-[0.2em] border-b border-blue-50 pb-4"><i class="fas fa-vault ml-2"></i> الأصول (Assets)</h3>
            <ul class="space-y-3 pr-2">
              <li v-for="a in balanceSheet.asset" :key="a.id" class="flex justify-between items-baseline group">
                <span class="text-xs font-black text-slate-500 group-hover:text-blue-600 transition-colors uppercase tracking-tighter">{{ a.code }} - {{ a.name }}</span>
                <span class="font-black font-mono text-slate-900 tracking-tighter">{{ fmt(a.balance) }}</span>
              </li>
              <li v-if="!balanceSheet.asset.length" class="text-slate-300 text-xs italic">لا توجد أصول مسجلة</li>
            </ul>
          </div>
          <!-- Liabilities -->
          <div class="space-y-6 border-r border-slate-50 pr-8">
            <h3 class="subsection-title text-rose-600 uppercase tracking-[0.2em] border-b border-rose-50 pb-4"><i class="fas fa-hand-holding-dollar ml-2"></i> الخصوم (Liabilities)</h3>
            <ul class="space-y-3 pr-2">
              <li v-for="a in balanceSheet.liability" :key="a.id" class="flex justify-between items-baseline group">
                <span class="text-xs font-black text-slate-500 group-hover:text-rose-600 transition-colors uppercase tracking-tighter">{{ a.code }} - {{ a.name }}</span>
                <span class="font-black font-mono text-slate-900 tracking-tighter">{{ fmt(a.balance) }}</span>
              </li>
              <li v-if="!balanceSheet.liability.length" class="text-slate-300 text-xs italic">لا توجد خصوم مسجلة</li>
            </ul>
          </div>
          <!-- Equity -->
          <div class="space-y-6 border-r border-slate-50 pr-8">
            <h3 class="subsection-title text-emerald-600 uppercase tracking-[0.2em] border-b border-emerald-50 pb-4"><i class="fas fa-scale-balanced ml-2"></i> حقوق الملكية (Equity)</h3>
            <ul class="space-y-3 pr-2">
              <li v-for="a in balanceSheet.equity" :key="a.id" class="flex justify-between items-baseline group">
                <span class="text-xs font-black text-slate-500 group-hover:text-emerald-600 transition-colors uppercase tracking-tighter">{{ a.code }} - {{ a.name }}</span>
                <span class="font-black font-mono text-slate-900 tracking-tighter">{{ fmt(a.balance) }}</span>
              </li>
              <li v-if="!balanceSheet.equity.length" class="text-slate-300 text-xs italic">لا توجد حقوق ملكية</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- 5. NRV Report (IAS 2) -->
      <section class="lg:col-span-12 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex items-center justify-between bg-amber-50/40">
          <div class="flex items-center gap-3">
            <span class="w-1.5 h-6 bg-amber-500 rounded-full"></span>
            <div>
              <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-triangle-exclamation text-amber-500"></i>
                تقرير صافي القيمة البيعية (NRV — IAS 2)
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">المنتجات التي تكلفتها أعلى من سعر البيع — تستوجب تخفيض قيمة المخزون</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button @click="loadNrv" :disabled="loadingNrv" class="w-9 h-9 bg-white border border-slate-200 text-slate-400 rounded-xl flex items-center justify-center hover:bg-amber-500 hover:text-white transition-all shadow-sm disabled:opacity-40">
              <i class="fas fa-rotate text-xs" :class="{'fa-spin': loadingNrv}"></i>
            </button>
            <button
              v-if="nrvData && nrvData.count > 0"
              @click="postWriteDown"
              :disabled="postingWriteDown"
              class="h-9 px-4 bg-red-600 text-white text-xs font-black rounded-xl hover:bg-red-700 transition-all shadow-sm disabled:opacity-40 flex items-center gap-2"
            >
              <i class="fas fa-file-invoice text-xs" :class="{'fa-spin': postingWriteDown}"></i>
              {{ postingWriteDown ? 'جاري...' : 'تسجيل قيد التخفيض' }}
            </button>
          </div>
        </div>

        <!-- نتيجة تسجيل القيد -->
        <div v-if="writeDownResult" class="mx-6 mt-4 p-4 rounded-2xl border text-xs font-bold flex items-center gap-3"
          :class="writeDownResult.status === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'">
          <i :class="writeDownResult.status === 'success' ? 'fas fa-check-circle text-emerald-500' : 'fas fa-circle-xmark text-red-500'"></i>
          <span v-if="writeDownResult.status === 'success'">
            {{ writeDownResult.data?.message }}
            <span v-if="writeDownResult.data?.journal_entry_ids?.length">
              — أرقام القيود: {{ writeDownResult.data.journal_entry_ids.join('، ') }}
              (إجمالي: {{ fmt(writeDownResult.data.total_amount) }})
            </span>
          </span>
          <span v-else>{{ writeDownResult.message }}</span>
        </div>

        <div v-if="loadingNrv" class="py-10 flex items-center justify-center text-slate-400">
          <i class="fas fa-spinner fa-spin text-2xl"></i>
        </div>
        <div v-else-if="!nrvData" class="py-8 text-center text-slate-400 text-sm font-medium">
          اضغط على زر التحديث لتحميل التقرير
        </div>
        <div v-else-if="nrvData.count === 0" class="py-8 flex flex-col items-center gap-2 text-emerald-600">
          <i class="fas fa-check-circle text-3xl"></i>
          <p class="font-bold text-sm">لا توجد منتجات تكلفتها أعلى من سعر البيع ✅</p>
        </div>
        <div v-else>
          <div class="px-6 py-3 bg-amber-50 border-b border-amber-100 flex items-center justify-between">
            <span class="text-xs font-black text-amber-800">{{ nrvData.count }} منتج يحتاج مراجعة</span>
            <span class="text-xs font-black text-red-700">إجمالي فرق التخفيض: {{ fmt(nrvData.total_impairment) }}</span>
          </div>
          <div class="overflow-x-auto max-h-64 custom-scroll">
            <table class="w-full text-right text-xs">
              <thead class="bg-slate-50 sticky top-0">
                <tr class="text-slate-500 font-black border-b border-slate-100">
                  <th class="px-5 py-3">المنتج</th>
                  <th class="px-4 py-3 text-center">الفرع</th>
                  <th class="px-4 py-3 text-center">الكمية</th>
                  <th class="px-4 py-3 text-center">متوسط التكلفة</th>
                  <th class="px-4 py-3 text-center">سعر البيع</th>
                  <th class="px-4 py-3 text-center">فرق الوحدة</th>
                  <th class="px-4 py-3 text-center">إجمالي التخفيض</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="item in nrvData.items" :key="item.product_id + '-' + item.branch_id" class="hover:bg-amber-50/40 transition-colors font-bold">
                  <td class="px-5 py-3 text-slate-800">{{ item.product_name }}<br><span class="text-slate-400 font-mono text-[10px]">{{ item.barcode }}</span></td>
                  <td class="px-4 py-3 text-center text-slate-600">{{ item.branch_name }}</td>
                  <td class="px-4 py-3 text-center font-mono">{{ item.qty_on_hand }}</td>
                  <td class="px-4 py-3 text-center font-mono text-slate-700">{{ fmt(item.unit_cost) }}</td>
                  <td class="px-4 py-3 text-center font-mono text-emerald-600">{{ fmt(item.sale_price) }}</td>
                  <td class="px-4 py-3 text-center font-mono text-red-600">{{ fmt(item.margin_per_unit) }}</td>
                  <td class="px-4 py-3 text-center font-mono font-black text-red-700">{{ fmt(item.impairment_amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- 7. AR Aging + Bad Debt Provision (IFRS 9) -->
      <section class="lg:col-span-12 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex items-center justify-between bg-rose-50/40">
          <div class="flex items-center gap-3">
            <span class="w-1.5 h-6 bg-rose-500 rounded-full"></span>
            <div>
              <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-hourglass-half text-rose-500"></i>
                تقرير تقادم الذمم المدينة (IFRS 9)
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">مصفوفة المخصصات — ECL Provisioning Matrix — مخصص الديون المشكوك فيها</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <input type="date" v-model="arAgingAsOf" class="h-9 px-3 text-xs border border-slate-200 rounded-xl bg-white text-slate-600 focus:outline-none focus:ring-2 focus:ring-rose-200" />
            <button @click="loadArAging" :disabled="loadingArAging" class="w-9 h-9 bg-white border border-slate-200 text-slate-400 rounded-xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-sm disabled:opacity-40">
              <i class="fas fa-rotate text-xs" :class="{'fa-spin': loadingArAging}"></i>
            </button>
          </div>
        </div>

        <div v-if="loadingArAging" class="py-10 flex items-center justify-center text-slate-400">
          <i class="fas fa-spinner fa-spin text-2xl"></i>
        </div>
        <div v-else-if="!arAgingData" class="py-8 text-center text-slate-400 text-sm font-medium">
          اضغط على زر التحديث لتحميل التقرير
        </div>
        <div v-else class="p-6 space-y-4">

          <!-- ملخص إجمالي -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-2">
            <div class="bg-rose-50 rounded-2xl p-4 border border-rose-100">
              <p class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-1">إجمالي الذمم المستحقة</p>
              <p class="text-xl font-black font-mono text-rose-700">{{ fmt(arAgingData.total_outstanding) }}</p>
            </div>
            <div class="bg-orange-50 rounded-2xl p-4 border border-orange-100">
              <p class="text-[10px] font-black text-orange-400 uppercase tracking-widest mb-1">إجمالي مخصص ECL</p>
              <p class="text-xl font-black font-mono text-orange-700">{{ fmt(arAgingData.total_provision) }}</p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">نسبة المخصص</p>
              <p class="text-xl font-black font-mono text-slate-700">
                {{ arAgingData.total_outstanding > 0 ? ((arAgingData.total_provision / arAgingData.total_outstanding) * 100).toFixed(1) : '0.0' }}%
              </p>
            </div>
            <div class="bg-white rounded-2xl p-4 border border-slate-200 flex flex-col justify-between">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">تسجيل قيد المخصص</p>
              <button
                v-if="arAgingData.total_provision > 0"
                @click="postProvision"
                :disabled="postingProvision"
                class="h-8 px-3 bg-rose-600 text-white text-xs font-black rounded-xl hover:bg-rose-700 transition-all disabled:opacity-40 flex items-center justify-center gap-1.5"
              >
                <i class="fas fa-file-invoice text-xs"></i>
                {{ postingProvision ? 'جاري...' : 'تسجيل' }}
              </button>
            </div>
          </div>

          <!-- نتيجة القيد -->
          <div v-if="provisionResult" class="p-4 rounded-2xl border text-xs font-bold flex items-center gap-3"
            :class="provisionResult.status === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'">
            <i :class="provisionResult.status === 'success' ? 'fas fa-check-circle text-emerald-500' : 'fas fa-circle-xmark text-red-500'"></i>
            <span v-if="provisionResult.status === 'success'">
              {{ provisionResult.data?.message }} — رقم القيد: {{ provisionResult.data?.journal_entry_id }}
              ({{ fmt(provisionResult.data?.amount) }})
            </span>
            <span v-else>{{ provisionResult.message }}</span>
          </div>

          <!-- جدول الشرائح -->
          <div v-for="(bucket, key) in arAgingData.buckets" :key="key" v-show="bucket.total_outstanding > 0"
            class="rounded-2xl border overflow-hidden border-slate-100">
            <div class="px-5 py-3 flex items-center justify-between bg-slate-50">
              <div class="flex items-center gap-3">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest"
                  :class="key === 'current' ? 'bg-emerald-100 text-emerald-700' :
                           key === 'days_31_60' ? 'bg-yellow-100 text-yellow-700' :
                           key === 'days_61_90' ? 'bg-orange-100 text-orange-700' :
                           key === 'days_91_180' ? 'bg-red-100 text-red-700' : 'bg-red-900 text-white'">
                  {{ bucket.label }}
                </span>
                <span class="text-xs text-slate-400 font-bold">معدل ECL: {{ (bucket.rate * 100).toFixed(0) }}%</span>
              </div>
              <div class="flex items-center gap-6 text-xs font-black">
                <span class="text-slate-600">الذمم: <span class="font-mono text-slate-800">{{ fmt(bucket.total_outstanding) }}</span></span>
                <span class="text-rose-600">المخصص: <span class="font-mono">{{ fmt(bucket.ecl_provision) }}</span></span>
              </div>
            </div>
            <div class="overflow-x-auto max-h-48 custom-scroll">
              <table class="w-full text-right text-xs">
                <thead class="bg-white sticky top-0 border-b border-slate-100">
                  <tr class="text-slate-400 font-black">
                    <th class="px-5 py-2">رقم الفاتورة</th>
                    <th class="px-4 py-2">العميل</th>
                    <th class="px-4 py-2 text-center">تاريخ البيع</th>
                    <th class="px-4 py-2 text-center">الأيام</th>
                    <th class="px-4 py-2 text-center">الإجمالي</th>
                    <th class="px-4 py-2 text-center">المدفوع</th>
                    <th class="px-4 py-2 text-center">المتبقي</th>
                    <th class="px-4 py-2 text-center">المخصص</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="inv in bucket.invoices" :key="inv.sale_id" class="hover:bg-rose-50/30 font-bold">
                    <td class="px-5 py-2.5 font-mono text-slate-700 text-[10px]">{{ inv.invoice_number }}</td>
                    <td class="px-4 py-2.5 text-slate-700">{{ inv.customer_name }}</td>
                    <td class="px-4 py-2.5 text-center text-slate-500">{{ inv.sale_date }}</td>
                    <td class="px-4 py-2.5 text-center font-mono" :class="inv.days_outstanding > 90 ? 'text-red-600' : inv.days_outstanding > 30 ? 'text-orange-500' : 'text-slate-600'">{{ inv.days_outstanding }}</td>
                    <td class="px-4 py-2.5 text-center font-mono text-slate-700">{{ fmt(inv.net_total) }}</td>
                    <td class="px-4 py-2.5 text-center font-mono text-emerald-600">{{ fmt(inv.paid) }}</td>
                    <td class="px-4 py-2.5 text-center font-mono text-rose-600 font-black">{{ fmt(inv.outstanding) }}</td>
                    <td class="px-4 py-2.5 text-center font-mono text-orange-600">{{ fmt(inv.ecl_provision) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <p class="text-[10px] text-slate-300 text-center font-bold uppercase tracking-widest">{{ arAgingData.note }}</p>
        </div>
      </section>

      <!-- 6. Cash Flow Statement (IAS 7) -->
      <section class="lg:col-span-12 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex items-center justify-between bg-sky-50/40">
          <div class="flex items-center gap-3">
            <span class="w-1.5 h-6 bg-sky-500 rounded-full"></span>
            <div>
              <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-water text-sky-500"></i>
                قائمة التدفقات النقدية (IAS 7)
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">الطريقة المباشرة — حركات الحسابات النقدية والبنكية</p>
            </div>
          </div>
          <button @click="loadCashFlow" :disabled="loadingCashFlow" class="w-9 h-9 bg-white border border-slate-200 text-slate-400 rounded-xl flex items-center justify-center hover:bg-sky-500 hover:text-white transition-all shadow-sm disabled:opacity-40">
            <i class="fas fa-rotate text-xs" :class="{'fa-spin': loadingCashFlow}"></i>
          </button>
        </div>

        <div v-if="loadingCashFlow" class="py-10 flex items-center justify-center text-slate-400">
          <i class="fas fa-spinner fa-spin text-2xl"></i>
        </div>
        <div v-else-if="!cashFlowData" class="py-8 text-center text-slate-400 text-sm font-medium">
          اضغط على زر التحديث لتحميل القائمة
        </div>
        <div v-else class="p-6 space-y-6">

          <!-- قسم لكل نوع تدفق -->
          <div v-for="(section, key) in cashFlowData.sections" :key="key" class="rounded-2xl border overflow-hidden"
            :class="key === 'operating' ? 'border-sky-100' : key === 'investing' ? 'border-violet-100' : 'border-emerald-100'">
            <div class="px-5 py-3 flex items-center justify-between"
              :class="key === 'operating' ? 'bg-sky-50' : key === 'investing' ? 'bg-violet-50' : 'bg-emerald-50'">
              <span class="text-xs font-black uppercase tracking-widest"
                :class="key === 'operating' ? 'text-sky-700' : key === 'investing' ? 'text-violet-700' : 'text-emerald-700'">
                {{ section.label }}
              </span>
              <span class="text-sm font-black font-mono" :class="section.total >= 0 ? 'text-emerald-600' : 'text-red-600'">
                {{ section.total >= 0 ? '+' : '' }}{{ fmt(section.total) }}
              </span>
            </div>
            <div v-if="section.items.length" class="overflow-x-auto">
              <table class="w-full text-right text-xs">
                <thead class="bg-slate-50 border-b border-slate-100">
                  <tr class="text-slate-400 font-black">
                    <th class="px-5 py-2.5">البيان</th>
                    <th class="px-4 py-2.5">الحساب</th>
                    <th class="px-4 py-2.5 text-center">تدفق داخل (+)</th>
                    <th class="px-4 py-2.5 text-center">تدفق خارج (-)</th>
                    <th class="px-4 py-2.5 text-center">الصافي</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-for="item in section.items" :key="item.reference_type + item.account_code" class="hover:bg-slate-50/50 font-bold">
                    <td class="px-5 py-3 text-slate-700">{{ item.label }}</td>
                    <td class="px-4 py-3 text-slate-400 font-mono text-[10px]">{{ item.account_code }} — {{ item.account_name }}</td>
                    <td class="px-4 py-3 text-center font-mono text-emerald-600">{{ item.inflow > 0 ? fmt(item.inflow) : '—' }}</td>
                    <td class="px-4 py-3 text-center font-mono text-red-500">{{ item.outflow > 0 ? fmt(item.outflow) : '—' }}</td>
                    <td class="px-4 py-3 text-center font-mono font-black" :class="item.net >= 0 ? 'text-emerald-700' : 'text-red-700'">
                      {{ item.net >= 0 ? '+' : '' }}{{ fmt(item.net) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="py-4 text-center text-slate-300 text-xs italic">لا توجد حركات نقدية</div>
          </div>

          <!-- الصافي الإجمالي -->
          <div class="flex items-center justify-between p-5 rounded-2xl border-2"
            :class="cashFlowData.net_change >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
            <span class="text-sm font-black uppercase tracking-widest"
              :class="cashFlowData.net_change >= 0 ? 'text-emerald-800' : 'text-red-800'">
              صافي التغير في النقدية للفترة
            </span>
            <span class="text-2xl font-black font-mono tracking-tighter"
              :class="cashFlowData.net_change >= 0 ? 'text-emerald-700' : 'text-red-700'">
              {{ cashFlowData.net_change >= 0 ? '+' : '' }}{{ fmt(cashFlowData.net_change) }}
            </span>
          </div>

          <p class="text-[10px] text-slate-300 text-center font-bold uppercase tracking-widest">{{ cashFlowData.note }}</p>
        </div>
      </section>

      <!-- 4. General Ledger (Full Width) -->
      <section v-if="filters.ledgerAccountId" class="lg:col-span-12 bg-white rounded-[3rem] shadow-sm border border-slate-100 overflow-hidden animate-fadeIn">
        <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/30">
           <div class="flex items-center gap-3">
              <span class="w-1.5 h-6 bg-slate-900 rounded-full"></span>
              <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">تقرير دفتر الأستاذ</h2>
           </div>
           <div class="flex items-center gap-4">
              <div class="px-5 py-2 bg-white border border-slate-200 rounded-xl shadow-sm">
                 <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">الرصيد الافتتاحي</p>
                 <p class="text-base font-black font-mono tracking-tighter leading-none">{{ fmt(ledger.opening_balance) }}</p>
              </div>
           </div>
        </div>
        <div class="overflow-x-auto max-h-[50vh] custom-scroll">
          <table class="w-full text-right text-sm font-cairo">
            <thead class="sticky top-0 z-20">
              <tr class="bg-slate-50/80 text-slate-500 font-black border-b border-slate-50 uppercase tracking-tighter shadow-sm">
                <th class="px-8 py-5">تاريخ القيد</th>
                <th class="px-4 py-5">البيان / الوصف المحاسبي</th>
                <th class="px-4 py-5 text-center">مدين (+)</th>
                <th class="px-8 py-5 text-center">دائن (-)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 font-bold text-slate-700">
              <tr v-for="(line, idx) in ledger.lines" :key="idx" class="hover:bg-slate-50/50 transition-all group">
                <td class="px-8 py-4 text-xs font-bold text-slate-400 font-mono tracking-tighter">{{ line.entry_date }}</td>
                <td class="px-4 py-4 text-xs font-black text-slate-800 leading-relaxed max-w-md truncate" :title="line.description">{{ line.description }}</td>
                <td class="px-4 py-4 text-center font-black font-mono text-rose-600 tracking-tighter">{{ line.debit_amount > 0 ? fmt(line.debit_amount) : '—' }}</td>
                <td class="px-8 py-4 text-center font-black font-mono text-emerald-600 tracking-tighter">{{ line.credit_amount > 0 ? fmt(line.credit_amount) : '—' }}</td>
              </tr>
              <tr v-if="!ledger.lines.length">
                 <td colspan="4" class="py-12 text-center text-slate-300 font-black uppercase tracking-widest opacity-30">لا توجد حركات مسجلة لهذا الحساب خلال الفترة</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useReportsStore } from '@/stores/reports'
import { useAccountStore } from '@/stores/account/accountStore'
import BaseSpinner from '@/components/ui/BaseSpinner.vue';

const reportsStore = useReportsStore();
const accountStore = useAccountStore();

const filters = ref({ start: '', end: '', asOf: '', ledgerAccountId: '' })
const startRef = ref(null); const endRef = ref(null); const asOfRef = ref(null);
const trialBalance = ref([]); const ledger = ref({ opening_balance: 0, lines: [] });
const incomeStatement = ref({ revenues: 0, expenses: 0, net_income: 0 });
const balanceSheet = ref({ asset: [], liability: [], equity: [] });
const coverage = ref(null);
const nrvData = ref(null); const loadingNrv = ref(false); const postingWriteDown = ref(false); const writeDownResult = ref(null);
const cashFlowData = ref(null); const loadingCashFlow = ref(false);
const arAgingData = ref(null); const loadingArAging = ref(false);
const arAgingAsOf = ref(new Date().toISOString().split('T')[0]);
const provisionResult = ref(null); const postingProvision = ref(false);
const tenantAccounts = ref([]); const globalAccounts = ref([]);

const accOptionLabel = (acc) => acc?.code ? `${acc.code} — ${acc.name}` : acc?.name || ''

function fmt(n) {
  if (n === undefined || n === null) return '—'
  const num = Number(n)
  return isNaN(num) ? '—' : num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadAll() {
  try {
    const [tb, is, bs] = await Promise.allSettled([
      reportsStore.fetchTrialBalance(filters.value.start, filters.value.end),
      reportsStore.fetchIncomeStatement(filters.value.start, filters.value.end),
      reportsStore.fetchBalanceSheet(filters.value.asOf)
    ])

    trialBalance.value = tb.status === 'fulfilled' && tb.value.status === 'success'
      ? (tb.value.data?.items || []) : []

    incomeStatement.value = is.status === 'fulfilled' && is.value.status === 'success'
      ? (is.value.data || { revenues: 0, expenses: 0, net_income: 0 })
      : { revenues: 0, expenses: 0, net_income: 0 }

    balanceSheet.value = bs.status === 'fulfilled' && bs.value.status === 'success'
      ? (bs.value.data || { asset: [], liability: [], equity: [] })
      : { asset: [], liability: [], equity: [] }

    if (filters.value.ledgerAccountId) {
      const lgResult = await reportsStore.fetchLedger(filters.value.ledgerAccountId, filters.value.start, filters.value.end)
      ledger.value = lgResult.status === 'success' ? (lgResult.data || { opening_balance: 0, lines: [] }) : { opening_balance: 0, lines: [] }
    } else {
      ledger.value = { opening_balance: 0, lines: [] }
    }
  } catch (e) {
    console.error('Error loading accounting reports:', e);
  }
}

async function loadAccounts() {
  try {
    const result = await accountStore.fetchAccounts()
    const payload = result.status === 'success' ? result.data : {}
    tenantAccounts.value = Array.isArray(payload) ? payload : (payload.tenant_accounts || [])
    globalAccounts.value = Array.isArray(payload) ? [] : (payload.global_accounts || [])
  } catch (_) { tenantAccounts.value = []; globalAccounts.value = []; }
}

async function loadCoverage() {
  try {
    const result = await reportsStore.fetchBranchAccountCoverage()
    coverage.value = result.status === 'success' ? result.data : null
  } catch (e) { coverage.value = null; console.error(e); }
}

async function loadArAging() {
  loadingArAging.value = true
  provisionResult.value = null
  try {
    const result = await reportsStore.fetchArAging(arAgingAsOf.value || null)
    arAgingData.value = result.status === 'success' ? result.data : null
  } catch (e) { arAgingData.value = null; console.error(e); }
  finally { loadingArAging.value = false }
}

async function postProvision() {
  if (!arAgingData.value?.total_provision) return
  if (!confirm(`سيتم تسجيل قيد مخصص الديون المشكوك فيها بمبلغ ${fmt(arAgingData.value.total_provision)} وفق IFRS 9. هل أنت متأكد؟`)) return
  postingProvision.value = true
  provisionResult.value = null
  try {
    provisionResult.value = await reportsStore.postBadDebtProvision(
      arAgingData.value.total_provision,
      arAgingAsOf.value || null
    )
  } catch (e) { console.error(e); }
  finally { postingProvision.value = false }
}

async function loadCashFlow() {
  loadingCashFlow.value = true
  try {
    const result = await reportsStore.fetchCashFlow(filters.value.start, filters.value.end)
    cashFlowData.value = result.status === 'success' ? result.data : null
  } catch (e) { cashFlowData.value = null; console.error(e); }
  finally { loadingCashFlow.value = false }
}

async function loadNrv() {
  loadingNrv.value = true
  try {
    const result = await reportsStore.fetchNrvReport()
    nrvData.value = result.status === 'success' ? result.data : null
  } catch (e) { nrvData.value = null; console.error(e); }
  finally { loadingNrv.value = false }
}

async function postWriteDown() {
  if (!confirm('سيتم تسجيل قيود تخفيض قيمة المخزون وفق IAS 2. هل أنت متأكد؟')) return
  postingWriteDown.value = true
  writeDownResult.value = null
  try {
    const result = await reportsStore.postNrvWriteDown()
    writeDownResult.value = result
    if (result.status === 'success') {
      await loadNrv()
    }
  } catch (e) { console.error(e); }
  finally { postingWriteDown.value = false }
}

onMounted(async () => {
  await Promise.all([loadAccounts(), loadAll(), loadCoverage()])
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap');
.font-cairo { font-family: 'Cairo', sans-serif; }

/* Modern UI Components */
.filter-label { @apply block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 px-1; }
.form-input-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 pr-11 outline-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.form-select-modern { @apply w-full h-11 bg-white border border-slate-200 rounded-2xl px-4 outline-none appearance-none transition-all duration-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 shadow-sm font-bold text-sm; }
.quick-range-btn { @apply px-3 py-1.5 rounded-xl bg-white text-slate-500 text-[9px] font-black uppercase tracking-widest border border-slate-100 hover:bg-slate-50 transition-all active:scale-95; }

.subsection-title { @apply text-[11px] font-black uppercase tracking-[0.2em] flex items-center gap-2; }
.status-badge { @apply px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest; }

/* Custom Scrollbars */
.custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scroll::-webkit-scrollbar-thumb { @apply bg-slate-200 rounded-full; }

.animate-fadeIn { animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Financial Highlighting Classes */
.table-header { @apply px-6 py-5 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest; }
</style>