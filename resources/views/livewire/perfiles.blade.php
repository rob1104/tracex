<div class="min-h-screen bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">Módulo de monitoreo</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-900">Usuarios monitoreados</h1>
                <p class="mt-1 text-sm text-slate-500">Administra perfiles y consulta actividad importada mediante exportaciones oficiales.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                <span class="font-semibold text-slate-700">Google Drive:</span>
                <span class="text-slate-500">{{ $correoDestino ?: 'No configurado' }}</span>
            </div>
        </div>

        @if (session('ok'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('ok') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['Usuarios', $perfiles->count(), 'indigo'], ['Importaciones', $historial->count(), 'blue'], ['Pendientes', $pendientes->count(), 'amber'], ['Drive', $correoDestino ? 'Conectado' : 'Pendiente', 'emerald']] as [$label, $value, $tone])
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ $value }}</p>
                    <div class="mt-3 h-1.5 rounded-full {{ ['indigo'=>'bg-indigo-100','blue'=>'bg-blue-100','amber'=>'bg-amber-100','emerald'=>'bg-emerald-100'][$tone] }}"><div class="h-1.5 w-2/3 rounded-full {{ ['indigo'=>'bg-indigo-500','blue'=>'bg-blue-500','amber'=>'bg-amber-500','emerald'=>'bg-emerald-500'][$tone] }}"></div></div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div><h2 class="font-bold text-slate-900">Usuarios registrados</h2><p class="text-sm text-slate-500">Perfiles asociados a la instalación.</p></div>
                    <span class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{{ $perfiles->count() }} registrados</span>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($perfiles as $perfil)
                        <a href="{{ route('monitor.usuarios.show', $perfil) }}" wire:navigate class="group rounded-xl border border-slate-200 p-4 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-50 text-sm font-black text-indigo-700">{{ strtoupper(substr($perfil->alias, 0, 1)) }}</div>
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">{{ $perfil->hash_fb ? 'Vinculado' : 'Pendiente' }}</span>
                            </div>
                            <h3 class="mt-4 font-bold text-slate-900">{{ $perfil->alias }}</h3>
                            <div class="mt-3 space-y-2 text-xs text-slate-500">
                                <div class="flex justify-between"><span>Comentarios</span><strong class="text-slate-800">{{ $perfil->comentarios_count }}</strong></div>
                                <div class="flex justify-between"><span>Reacciones</span><strong class="text-slate-800">{{ $perfil->reacciones_count }}</strong></div>
                                <div class="flex justify-between"><span>Compartidos</span><strong class="text-slate-800">{{ $perfil->compartidos_count }}</strong></div>
                            </div>
                            <div class="mt-4 text-xs font-bold text-indigo-600 group-hover:text-indigo-700">Ver actividad →</div>
                        </a>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3 rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">No hay usuarios monitoreados registrados.</div>
                    @endforelse
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-bold text-slate-900">Registrar usuario</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Agrega el nombre de referencia y el enlace de su perfil de Facebook.</p>
                    <form wire:submit="agregarPerfil" class="mt-5 space-y-4">
                        <div><label class="text-xs font-bold text-slate-600">Nombre / alias</label><input wire:model="nuevoAlias" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Ej. Usuario 01">@error('nuevoAlias')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="text-xs font-bold text-slate-600">Enlace de Facebook</label><input wire:model="nuevoEnlace" type="text" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="https://facebook.com/...">@error('nuevoEnlace')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">Registrar usuario</button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between"><h2 class="font-bold text-slate-900">Google Drive</h2><span class="h-2.5 w-2.5 rounded-full {{ $correoDestino ? 'bg-emerald-500' : 'bg-amber-500' }}"></span></div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Las exportaciones oficiales se reciben temporalmente y se procesan en la instalación local.</p>
                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">{{ $correoDestino ?: 'Ejecuta php artisan google:token para autorizar.' }}</div>
                </section>
            </aside>
        </div>

        @if($pendientes->count())
            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 shadow-sm">
                <div class="border-b border-amber-100 px-6 py-5"><h2 class="font-bold text-slate-900">Importaciones pendientes</h2><p class="text-sm text-slate-500">Revisa las cargas que no pudieron asociarse automáticamente.</p></div>
                <div class="divide-y divide-amber-100">
                    @foreach($pendientes as $imp)
                        <div class="grid gap-4 px-6 py-4 md:grid-cols-[1fr_220px_auto] md:items-center">
                            <div><p class="text-sm font-bold text-slate-800">{{ $imp->nombre_carpeta }}</p><p class="text-xs text-slate-500">{{ optional($imp->recibido_en)->format('d/m/Y H:i') }} · {{ $imp->estado }}</p></div>
                            <select wire:model="seleccion.{{ $imp->id }}" class="rounded-lg border-slate-300 text-sm"><option value="">Seleccionar usuario</option>@foreach($perfiles as $p)<option value="{{ $p->id }}">{{ $p->alias }}</option>@endforeach</select>
                            <div class="flex gap-2"><button wire:click="asignar('{{ $imp->id }}')" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white">Asignar</button><button wire:click="descartar('{{ $imp->id }}')" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600">Descartar</button></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-5"><h2 class="font-bold text-slate-900">Últimas importaciones</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3">Carpeta</th><th class="px-6 py-3">Usuario</th><th class="px-6 py-3">Estado</th><th class="px-6 py-3">Actualizado</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($historial as $imp)<tr><td class="px-6 py-4 font-medium text-slate-800">{{ $imp->nombre_carpeta }}</td><td class="px-6 py-4 text-slate-600">{{ $imp->perfil?->alias ?? '—' }}</td><td class="px-6 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ ucfirst($imp->estado) }}</span></td><td class="px-6 py-4 text-slate-500">{{ optional($imp->updated_at)->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Todavía no hay importaciones procesadas.</td></tr>@endforelse</tbody></table></div>
        </section>
    </div>
</div>
