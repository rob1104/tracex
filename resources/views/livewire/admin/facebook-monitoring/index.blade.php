<div class="min-h-[calc(100vh-4rem)] bg-slate-100" wire:poll.60s>
    <div class="mx-auto max-w-[1720px] px-0 sm:px-4 lg:px-6">
        <div class="grid min-h-[calc(100vh-4rem)] overflow-hidden bg-white shadow-sm lg:grid-cols-[232px_minmax(0,1fr)] lg:rounded-xl">
            <aside class="flex flex-col bg-slate-900 px-3 py-5 text-slate-200 lg:min-h-full">
                <div class="hidden items-center gap-3 px-3 pb-7 lg:flex">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-xl font-bold text-white shadow-lg shadow-blue-950/30">T</span>
                    <div>
                        <p class="text-lg font-bold tracking-wide text-white">TraceX</p>
                        <p class="text-[11px] text-slate-400">Monitoreo de Facebook</p>
                    </div>
                </div>

                <nav class="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:block lg:space-y-1" aria-label="Módulos de TraceX">
                    <p class="hidden px-3 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 lg:block">Principal</p>
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">⌂</span> Inicio
                    </a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.evidences.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                            <span class="w-5 text-center text-base">▣</span> Evidencias
                        </a>
                    @else
                        <a href="{{ route('admin.profiles.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                            <span class="w-5 text-center text-base">▣</span> Evidencias
                        </a>
                    @endif
                    <a href="#recent-activity" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">◷</span> Actividad
                    </a>
                    <a href="#drive-import" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">☁</span> Google Drive
                    </a>

                    <p class="hidden px-3 pb-2 pt-6 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 lg:block">Módulos</p>
                    <a href="{{ route('admin.facebook_monitoring.index') }}" wire:navigate aria-current="page" class="flex items-center gap-3 rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-950/20">
                        <span class="w-5 text-center text-base">👥</span> Monitoreo de usuarios
                    </a>
                    <a href="#monitored-profiles" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">👥</span> Usuarios monitoreados
                    </a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.evidence_types.index') }}#facebook-exports" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                            <span class="w-5 text-center text-base">↓</span> Sin asignar
                            <span class="ml-auto rounded-full bg-amber-400/15 px-2 py-0.5 text-xs font-semibold text-amber-300">{{ $pendingAssignmentCount }}</span>
                        </a>
                    @else
                        <a href="{{ route('admin.profiles.index') }}#facebook-pending-imports" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                            <span class="w-5 text-center text-base">↓</span> Sin asignar
                            <span class="ml-auto rounded-full bg-amber-400/15 px-2 py-0.5 text-xs font-semibold text-amber-300">{{ $pendingAssignmentCount }}</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.profiles.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">◉</span> Perfiles sociales
                    </a>
                    <a href="{{ route('admin.emails.index') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white">
                        <span class="w-5 text-center text-base">✉</span> Cuentas de correo
                    </a>
                </nav>

                <div class="mt-auto hidden border-t border-slate-800 px-3 pt-5 text-xs lg:block">
                    <p class="text-slate-400">Exportaciones de Meta</p>
                    <p class="mt-2 text-slate-500">TraceX · Facebook</p>
                </div>
            </aside>

            <main class="min-w-0 bg-slate-50/70 p-4 sm:p-6 xl:p-8">
                <header class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Módulo de monitoreo</p>
                        <h1 class="mt-1 flex items-center gap-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            <span class="text-3xl" aria-hidden="true">👥</span> Monitoreo de usuarios
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">Consulta la actividad de Facebook recibida mediante exportaciones oficiales y Google Drive.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                            <span class="font-semibold text-slate-700">Google Drive:</span>
                            <span class="{{ $driveConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $driveConfigured ? 'Configurado' : 'Pendiente de configurar' }}</span>
                        </div>
                        <a href="#register-facebook-profile" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            <span class="text-lg leading-none" aria-hidden="true">＋</span> Agregar usuario
                        </a>
                    </div>
                </header>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
                @endif

                <section class="mb-5 grid gap-3 sm:grid-cols-2 2xl:grid-cols-4" aria-label="Resumen del sistema">
                    <article class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600">♟</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Perfiles registrados</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalProfiles }}</p><p class="mt-1 text-xs text-slate-400">Facebook vinculado</p></div>
                    </article>
                    <article class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600">↓</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Importaciones</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalImports }}</p><p class="mt-1 text-xs text-slate-400">Exportaciones recibidas</p></div>
                    </article>
                    <article class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-emerald-50 text-xl text-emerald-600">✓</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Procesamiento</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $processingCount > 0 ? 'En proceso' : 'Al día' }}</p><p class="mt-1 text-xs text-slate-400">{{ $processingCount }} cargas pendientes</p></div>
                    </article>
                    <article class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-blue-50 text-xl text-blue-600">▤</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Base de datos local</p><p class="mt-0.5 truncate text-lg font-bold text-slate-900">{{ $databaseName }}</p><p class="mt-1 text-xs text-slate-400">Conexión disponible</p></div>
                    </article>
                </section>

                <div class="mb-5 grid gap-4 2xl:grid-cols-[minmax(0,2fr)_minmax(290px,0.85fr)]">
                    <section id="monitored-profiles" class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">Usuarios de Facebook monitoreados</h2>
                                <p class="text-sm text-slate-500">Actividad acumulada y exportaciones asociadas a cada usuario.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">⌕</span>
                                    <input wire:model.live.debounce.300ms="search" type="search" aria-label="Buscar usuario" placeholder="Buscar usuario..." class="w-full rounded-lg border-slate-200 py-2 pl-9 text-sm sm:w-52">
                                </div>
                                <span class="whitespace-nowrap rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">{{ $profiles->total() }} perfiles</span>
                            </div>
                        </div>
                        <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                            @forelse($profiles as $profile)
                                @php
                                    $identifier = (string) $profile->facebook_identifier;
                                    $facebookUrl = str_starts_with($identifier, 'uri:') ? substr($identifier, 4) : (str_starts_with($identifier, 'http') ? $identifier : null);
                                @endphp
                                <article wire:key="family-profile-{{ $profile->id }}" class="flex min-w-0 flex-col rounded-lg border border-slate-200 p-4 transition hover:border-blue-200 hover:shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-100 to-indigo-100 text-lg font-bold text-blue-700">{{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $profile->name }}</h3>
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $profile->emailAccount?->email ?? 'Sin cuenta asociada' }}</p>
                                            <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $identifier !== '' ? 'text-emerald-700' : 'text-amber-700' }}"><span class="h-2 w-2 rounded-full {{ $identifier !== '' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span> {{ $identifier !== '' ? 'Facebook vinculado' : 'ID por asociar' }}</span>
                                        </div>
                                    </div>
                                    @if($facebookUrl)
                                        <a href="{{ $facebookUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 truncate text-xs text-blue-600 hover:underline">{{ $facebookUrl }}</a>
                                    @elseif($identifier !== '')
                                        <p class="mt-2 truncate text-xs text-slate-500">ID de Facebook: {{ str_starts_with($identifier, 'id:') ? substr($identifier, 3) : $identifier }}</p>
                                    @else
                                        <p class="mt-2 text-xs text-amber-700">Falta asociar un ID de Facebook</p>
                                    @endif
                                    <div class="my-3 grid grid-cols-3 gap-2 border-y border-slate-100 py-3 text-xs">
                                        <div><p class="text-slate-500">Comentarios</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_comments_count ?? 0) }}</p></div>
                                        <div><p class="text-slate-500">Reacciones</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_reactions_count ?? 0) }}</p></div>
                                        <div><p class="text-slate-500">Compartidos</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_shares_count ?? 0) }}</p></div>
                                    </div>
                                    <div class="mb-3 flex items-center justify-between text-xs text-slate-500">
                                        <span>{{ $profile->facebook_exports_count ?? 0 }} exportaciones</span>
                                        <span>{{ $profile->latest_facebook_export_at ? \Illuminate\Support\Carbon::parse($profile->latest_facebook_export_at)->format('d/m/Y') : 'Sin exportaciones' }}</span>
                                    </div>
                                    <a href="#recent-activity" wire:click="showProfileActivity({{ $profile->id }})" class="mt-auto flex w-full items-center justify-center gap-2 rounded-md border border-blue-100 bg-blue-50/70 px-3 py-2 text-xs font-semibold text-blue-700 transition hover:bg-blue-100">
                                        <span aria-hidden="true">◉</span> Ver actividad
                                    </a>
                                </article>
                            @empty
                                <div class="col-span-full rounded-lg border border-dashed border-slate-200 px-5 py-10 text-center">
                                    <p class="font-semibold text-slate-700">No hay usuarios monitoreados todavía.</p>
                                    <p class="mt-1 text-sm text-slate-500">Agrega el nombre y el enlace de Facebook para asociar sus próximas exportaciones.</p>
                                </div>
                            @endforelse
                        </div>
                        @if($profiles->hasPages())
                            <div class="border-t border-slate-100 px-5 py-3">{{ $profiles->links() }}</div>
                        @endif
                    </section>

                    <section id="drive-import" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-xl text-blue-600">☁</span>
                            <div><h2 class="font-bold text-slate-900">Importación desde Google Drive</h2><p class="text-xs text-slate-500">Exportaciones oficiales de Meta</p></div>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-500">TraceX detecta las carpetas de exportación, procesa sus datos y guarda la actividad en la base local.</p>
                        <ul class="mt-4 space-y-3 text-xs text-slate-600">
                            <li class="flex gap-2"><span class="font-bold {{ $driveConfigured ? 'text-emerald-600' : 'text-amber-500' }}">●</span> Conexión con Google Drive {{ $driveConfigured ? 'configurada' : 'pendiente' }}</li>
                            <li class="flex gap-2"><span class="font-bold text-emerald-600">●</span> Detección de carpetas de Meta</li>
                            <li class="flex gap-2"><span class="font-bold text-emerald-600">●</span> Descarga y verificación de archivos</li>
                            <li class="flex gap-2"><span class="font-bold text-emerald-600">●</span> Eliminación del archivo remoto tras descargarlo</li>
                            <li class="flex gap-2"><span class="font-bold text-emerald-600">●</span> Procesamiento en cola y almacenamiento local</li>
                        </ul>
                        <div class="mt-5 rounded-lg bg-slate-50 px-3 py-3 text-xs leading-5 text-slate-500">
                            @if($driveConfigured)
                                La conexión está configurada. El estado de las últimas cargas aparece en Actividad e importaciones.
                            @else
                                Para autorizar Drive, configura las credenciales del servidor y ejecuta <code class="font-semibold text-slate-700">php artisan google:token</code> en la terminal.
                            @endif
                        </div>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.evidence_types.index') }}#facebook-exports" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                                <span aria-hidden="true">↓</span> Ver importaciones sin asignar
                            </a>
                        @else
                            <a href="{{ route('admin.profiles.index') }}#facebook-pending-imports" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                                <span aria-hidden="true">↓</span> Ver importaciones
                            </a>
                        @endif
                    </section>
                </div>

                <div class="grid gap-4 2xl:grid-cols-[minmax(0,1.65fr)_minmax(320px,0.9fr)]">
                    <section id="recent-activity" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-2 border-b border-slate-100 px-4 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div>
                                <h2 class="font-bold text-slate-900">Actividad e importaciones</h2>
                                @if($activityProfileId !== '')
                                    <p class="mt-1 text-xs text-slate-500">Filtrando actividad del perfil seleccionado. <button wire:click="showAllActivity" class="font-semibold text-blue-600 hover:underline">Ver todos</button></p>
                                @else
                                    <p class="mt-1 text-xs text-slate-500">Comentarios, reacciones y publicaciones compartidas procesadas.</p>
                                @endif
                            </div>
                            <a href="{{ auth()->user()->isAdmin() ? route('admin.evidences.index') : route('admin.profiles.index') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Ver reporte</a>
                        </div>
                        <div class="flex gap-4 overflow-x-auto border-b border-slate-100 px-4 sm:px-5">
                            @foreach(['recent' => 'Actividad reciente', 'imports' => 'Importaciones', 'pending' => 'Cargas pendientes', 'unassigned' => 'Sin asignar'] as $tab => $label)
                                <button type="button" wire:click="setActivityTab('{{ $tab }}')" class="whitespace-nowrap border-b-2 px-1 py-3 text-xs font-semibold transition {{ $activityTab === $tab ? 'border-blue-600 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $label }}@if($tab === 'unassigned') <span class="ml-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800">{{ $pendingAssignmentCount }}</span>@endif</button>
                            @endforeach
                        </div>

                        @if($activityTab === 'recent')
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                                        <tr><th class="px-4 py-3 font-semibold">Fecha</th><th class="px-4 py-3 font-semibold">Perfil</th><th class="px-4 py-3 font-semibold">Tipo</th><th class="px-4 py-3 font-semibold">Descripción</th><th class="px-4 py-3 font-semibold">Estado</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($activity as $evidence)
                                            @php $typeName = $evidence->evidenceType?->name ?? 'Actividad'; @endphp
                                            <tr class="align-top hover:bg-slate-50/70">
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ ($evidence->external_published_at ?? $evidence->created_at)?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">{{ $evidence->profile?->name ?? 'Perfil no asignado' }}</td>
                                                <td class="whitespace-nowrap px-4 py-3"><span class="inline-flex items-center gap-1.5 text-slate-700"><span class="h-2 w-2 rounded-full {{ str_contains(mb_strtolower($typeName), 'coment') ? 'bg-blue-500' : (str_contains(mb_strtolower($typeName), 'compart') ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>{{ $typeName }}</span></td>
                                                <td class="max-w-sm px-4 py-3 text-slate-500"><div class="whitespace-pre-line">{{ \Illuminate\Support\Str::limit($evidence->comment, 150) }}</div></td>
                                                <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Procesado</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Aún no hay comentarios, reacciones o compartidos asignados.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Carpeta / archivo</th><th class="px-4 py-3 font-semibold">Perfil de Facebook detectado</th><th class="px-4 py-3 font-semibold">Estado</th><th class="px-4 py-3 font-semibold">Recibida</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($imports as $importLog)
                                            @php
                                                $facebookUser = $importLog->facebookUsers->first();
                                                $metadata = is_array($facebookUser?->metadata) ? $facebookUser->metadata : [];
                                                $statusLabel = match($importLog->status) {
                                                    'completed' => 'Procesado', 'processing' => 'Procesando', 'downloaded' => 'Descargado', 'pending_assignment' => 'Sin asignar', 'failed' => 'Error', default => ucfirst($importLog->status),
                                                };
                                            @endphp
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="max-w-xs px-4 py-3"><p class="truncate font-semibold text-slate-700">{{ $importLog->file_name }}</p><p class="mt-1 truncate text-slate-400">ID: {{ $importLog->drive_file_id }}</p></td>
                                                <td class="px-4 py-3 text-slate-600">{{ $importLog->profile?->name ?? ($metadata['name'] ?? 'Pendiente de coincidencia') }}</td>
                                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $importLog->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($importLog->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800') }}">{{ $statusLabel }}</span></td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $importLog->downloaded_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No hay importaciones en esta sección.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div><h2 class="font-bold text-slate-900">Resumen de actividad</h2><p class="mt-1 text-xs text-slate-500">{{ $chartRangeLabel }} · agrupado {{ $chartGroupingLabel }}</p></div>
                            <div class="flex gap-2">
                                <label class="sr-only" for="chartPeriod">Periodo</label>
                                <select id="chartPeriod" wire:model.live="chartPeriod" class="rounded-md border-slate-200 py-1.5 pl-2 pr-7 text-[11px] text-slate-600 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="7">7 días</option>
                                    <option value="14">14 días</option>
                                    <option value="30">30 días</option>
                                </select>
                                <label class="sr-only" for="chartGrouping">Agrupar</label>
                                <select id="chartGrouping" wire:model.live="chartGrouping" class="rounded-md border-slate-200 py-1.5 pl-2 pr-7 text-[11px] text-slate-600 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="day">Por día</option>
                                    <option value="week">Por semana</option>
                                </select>
                            </div>
                        </div>
                        @if($chartHasActivity)
                            <div class="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-[10px] text-slate-500">
                                <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-blue-500"></i>Comentarios</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-rose-500"></i>Reacciones</span>
                                <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-emerald-500"></i>Compartidos</span>
                            </div>
                            <div class="mt-4 flex h-44 items-end justify-between gap-0.5 border-b border-l border-slate-200 px-1 pb-0 pt-2 sm:gap-1 sm:px-2">
                                @foreach($chart as $bucket)
                                    <div wire:key="activity-chart-{{ $bucket['key'] }}" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                        <div class="flex h-full w-full items-end justify-center gap-px sm:gap-1">
                                            <span title="{{ $bucket['label'] }}: {{ $bucket['comments'] }} comentarios" class="w-1.5 rounded-t bg-blue-500 sm:w-3" style="height: {{ max(1, (int) round(($bucket['comments'] / $chartMax) * 100)) }}%"></span>
                                            <span title="{{ $bucket['label'] }}: {{ $bucket['reactions'] }} reacciones" class="w-1.5 rounded-t bg-rose-500 sm:w-3" style="height: {{ max(1, (int) round(($bucket['reactions'] / $chartMax) * 100)) }}%"></span>
                                            <span title="{{ $bucket['label'] }}: {{ $bucket['shares'] }} compartidos" class="w-1.5 rounded-t bg-emerald-500 sm:w-3" style="height: {{ max(1, (int) round(($bucket['shares'] / $chartMax) * 100)) }}%"></span>
                                        </div>
                                        <span class="mt-2 whitespace-nowrap text-[8px] text-slate-400 sm:text-[9px]">{{ $bucket['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-3 text-[10px] text-slate-400">El gráfico cuenta evidencias asignadas a perfiles. Las exportaciones pendientes aparecen en sus pestañas.</p>
                        @else
                            <div class="mt-5 flex min-h-44 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/70 px-5 py-6 text-center">
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-white text-lg text-slate-400 shadow-sm" aria-hidden="true">◷</span>
                                <p class="mt-3 text-sm font-semibold text-slate-700">No hay actividad en {{ mb_strtolower($chartRangeLabel) }}</p>
                                @if($totalImports === 0)
                                    <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">Cuando registres perfiles y se procese una exportación de Facebook, los comentarios, reacciones y compartidos aparecerán aquí.</p>
                                @else
                                    <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">No hay evidencias asignadas en este periodo. Revisa la pestaña “Sin asignar” si una exportación aún no tiene perfil asociado.</p>
                                @endif
                            </div>
                        @endif
                    </section>
                </div>

                <section id="register-facebook-profile" class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-4">
                        <h2 class="font-bold text-slate-900">Agregar usuario de Facebook</h2>
                        <p class="mt-1 text-sm text-slate-500">Registra el nombre, el enlace del perfil y la cuenta de correo que corresponde a sus exportaciones.</p>
                    </div>
                    <form wire:submit="registerMonitoredFacebookProfile" class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="monitorName" class="mb-1.5 block text-xs font-semibold text-slate-600">Nombre del usuario o alias</label>
                            <input id="monitorName" type="text" wire:model="monitorName" placeholder="Ej. Sofía García" class="w-full rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('monitorName')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="monitorFacebookUrl" class="mb-1.5 block text-xs font-semibold text-slate-600">Enlace del perfil de Facebook</label>
                            <input id="monitorFacebookUrl" type="url" wire:model="monitorFacebookUrl" placeholder="https://www.facebook.com/usuario" class="w-full rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('monitorFacebookUrl')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="monitorEmailAccountId" class="mb-1.5 block text-xs font-semibold text-slate-600">Cuenta de correo asociada</label>
                            <select id="monitorEmailAccountId" wire:model="monitorEmailAccountId" class="w-full rounded-lg border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Selecciona una cuenta de correo</option>
                                @foreach($emails as $email)
                                    <option value="{{ $email->id }}">{{ $email->email }}{{ $email->alias ? ' · '.$email->alias : '' }}</option>
                                @endforeach
                            </select>
                            @error('monitorEmailAccountId')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @if($emails->isEmpty())
                                <p class="mt-2 text-xs text-amber-700">Primero <a href="{{ route('admin.emails.index') }}" wire:navigate class="font-semibold underline">registra una cuenta de correo</a>.</p>
                            @endif
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" wire:loading.attr="disabled" wire:target="registerMonitoredFacebookProfile" @disabled($emails->isEmpty()) class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 md:w-auto">
                                <span aria-hidden="true">＋</span><span wire:loading.remove wire:target="registerMonitoredFacebookProfile">Registrar usuario</span><span wire:loading wire:target="registerMonitoredFacebookProfile">Guardando…</span>
                            </button>
                        </div>
                    </form>
                </section>

                <footer class="flex flex-col gap-1 py-4 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <span>TraceX · Panel de monitoreo de Facebook</span><span>Exportación de Meta → Drive → base de datos local</span>
                </footer>
            </main>
        </div>
    </div>
</div>
