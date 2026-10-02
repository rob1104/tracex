<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">
            Resumen de Gestión de Cuentas
        </h3>
    </div>

    <!-- Cuentas de Correo -->
    <div class="mb-8">
        <h4 class="text-lg font-bold text-slate-700 mb-4">Cuentas de Correo Creadas por Mí</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-2xl border border-blue-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-blue-700 uppercase tracking-wide">Hoy</div>
                <div class="mt-2 text-5xl font-black text-blue-600">{{ $emailsToday }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-indigo-50 to-violet-50 p-6 rounded-2xl border border-indigo-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-indigo-700 uppercase tracking-wide">Esta Semana</div>
                <div class="mt-2 text-5xl font-black text-indigo-600">{{ $emailsWeek }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-violet-50 to-purple-50 p-6 rounded-2xl border border-violet-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-violet-700 uppercase tracking-wide">Este Mes</div>
                <div class="mt-2 text-5xl font-black text-violet-600">{{ $emailsMonth }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-slate-50 to-gray-100 p-6 rounded-2xl border border-slate-200 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-slate-700 uppercase tracking-wide">Total Histórico</div>
                <div class="mt-2 text-5xl font-black text-slate-600">{{ $emailsTotal }}</div>
            </div>
        </div>
    </div>

    <!-- Perfiles -->
    <div>
        <h4 class="text-lg font-bold text-slate-700 mb-4">Perfiles Sociales Creados por Mí</h4>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-2xl border border-emerald-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-emerald-700 uppercase tracking-wide">Hoy</div>
                <div class="mt-2 text-5xl font-black text-emerald-600">{{ $profilesToday }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-teal-50 to-cyan-50 p-6 rounded-2xl border border-teal-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-teal-700 uppercase tracking-wide">Esta Semana</div>
                <div class="mt-2 text-5xl font-black text-teal-600">{{ $profilesWeek }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-cyan-50 to-sky-50 p-6 rounded-2xl border border-cyan-100 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-cyan-700 uppercase tracking-wide">Este Mes</div>
                <div class="mt-2 text-5xl font-black text-cyan-600">{{ $profilesMonth }}</div>
            </div>
            
            <div class="bg-gradient-to-br from-slate-50 to-gray-100 p-6 rounded-2xl border border-slate-200 shadow-sm transition-transform hover:scale-[1.02]">
                <div class="text-sm font-bold text-slate-700 uppercase tracking-wide">Total Histórico</div>
                <div class="mt-2 text-5xl font-black text-slate-600">{{ $profilesTotal }}</div>
            </div>
        </div>
    </div>
</div>

