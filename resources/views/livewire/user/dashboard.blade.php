<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-violet-600 to-indigo-600">
            ¡Hola, {{ auth()->user()->name }}!
        </h3>
        
        <a href="/evidencias/registrar" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-violet-600 to-indigo-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest hover:from-violet-500 hover:to-indigo-500 shadow-md hover:shadow-lg transition-all duration-200" wire:navigate>
            Registrar Evidencia
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Hoy -->
        <div class="bg-gradient-to-br from-indigo-50 to-violet-50 p-6 rounded-2xl border border-indigo-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-indigo-700 uppercase tracking-wide">Registradas hoy</div>
            <div class="mt-2 text-5xl font-black text-indigo-600">{{ $todayCount }}</div>
        </div>
        
        <!-- Semana -->
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-2xl border border-emerald-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-emerald-700 uppercase tracking-wide">Esta semana</div>
            <div class="mt-2 text-5xl font-black text-emerald-600">{{ $weekCount }}</div>
        </div>
        
        <!-- Mes -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 p-6 rounded-2xl border border-amber-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-amber-700 uppercase tracking-wide">Este mes</div>
            <div class="mt-2 text-5xl font-black text-amber-600">{{ $monthCount }}</div>
        </div>
    </div>
</div>
