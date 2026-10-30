<div class="min-h-[calc(100vh-4rem)] bg-slate-100" wire:poll.60s>
    <div class="mx-auto max-w-[1720px] px-0 sm:px-4 lg:px-6">
        <div class="min-h-[calc(100vh-4rem)] overflow-hidden bg-white shadow-sm lg:rounded-xl">

            <main class="min-w-0 bg-slate-50/70 p-4 sm:p-6 xl:p-8">
                <header class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Módulo de monitoreo</p>
                        <h1 class="mt-1 flex items-center gap-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ auth()->user()->isAdmin() ? 'Monitoreo de colaboradores' : 'Monitoreo de usuarios' }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->isAdmin() ? 'Consulta las exportaciones y actividad de Facebook asociadas a cada colaborador.' : 'Consulta la actividad de Facebook recibida mediante exportaciones oficiales y Google Drive.' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        @if(auth()->user()->isAdmin())
                            <div x-data="{
                                    open: false,
                                    selectedId: @js((string) $collaboratorId),
                                    collaborators: @js($collaborators->map(fn ($collaborator) => ['id' => (string) $collaborator->id, 'name' => $collaborator->name])->values()),
                                    get selectedText() {
                                        return this.collaborators.find((collaborator) => collaborator.id === this.selectedId)?.name || 'Todos los colaboradores';
                                    },
                                    selectCollaborator(id) {
                                        this.selectedId = id;
                                        this.open = false;
                                        $wire.set('collaboratorId', id);
                                    }
                                }"
                                class="relative w-full sm:w-64"
                                @click.outside="open = false">
                                <label class="sr-only" for="collaboratorFilter">Buscar o filtrar por colaborador</label>
                                <button id="collaboratorFilter" type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 text-left text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <span class="truncate" x-text="selectedText"></span>
                                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-cloak x-show="open" x-transition class="absolute right-0 z-50 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                    <ul role="listbox" class="max-h-60 overflow-y-auto p-1">
                                        <li><button type="button" role="option" :aria-selected="selectedId === ''" @click="selectCollaborator('')" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50">Todos los colaboradores</button></li>
                                        <template x-for="collaborator in collaborators" :key="collaborator.id">
                                            <li><button type="button" role="option" :aria-selected="selectedId === collaborator.id" @click="selectCollaborator(collaborator.id)" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50" x-text="collaborator.name"></button></li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        @endif
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                            <span class="font-semibold text-slate-700">Google Drive:</span>
                            <span class="{{ $driveConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $driveConfigured ? 'Configurado' : 'Pendiente de configurar' }}</span>
                        </div>

                    </div>
                </header>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
                @endif

                <section class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Resumen del sistema">
                    <article class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-lg text-blue-600">♟</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Perfiles registrados</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalProfiles }}</p><p class="mt-1 text-xs text-slate-400">Facebook vinculado</p></div>
                    </article>
                    <article class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-lg text-blue-600">↓</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Importaciones</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalImports }}</p><p class="mt-1 text-xs text-slate-400">Exportaciones recibidas</p></div>
                    </article>
                    <article class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 text-lg text-emerald-600">✓</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Procesamiento</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $processingCount > 0 ? 'En proceso' : 'Al día' }}</p><p class="mt-1 text-xs text-slate-400">{{ $processingCount }} cargas pendientes</p></div>
                    </article>

                </section>

                <div class="mb-5">
                    <section id="monitored-profiles" class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900">{{ auth()->user()->isAdmin() ? 'Perfiles de Facebook por colaborador' : 'Usuarios de Facebook monitoreados' }}</h2>
                                <p class="text-sm text-slate-500">{{ auth()->user()->isAdmin() ? 'Actividad acumulada y exportaciones vinculadas a los colaboradores.' : 'Actividad acumulada y exportaciones asociadas a cada usuario.' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">⌕</span>
                                    <input wire:model.live.debounce.300ms="search" type="search" aria-label="Buscar usuario" placeholder="Buscar usuario..." class="w-full rounded-lg border-slate-200 py-2 pl-9 text-sm sm:w-52">
                                </div>
                                <span class="whitespace-nowrap rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">{{ $profiles->total() }} perfiles</span>
                            </div>
                        </div>
                        <div class="grid gap-2 p-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-6">
                            @forelse($profiles as $profile)
                                @php
                                    $identifier = (string) $profile->facebook_identifier;
                                    $facebookUrl = str_starts_with($identifier, 'uri:') ? substr($identifier, 4) : (str_starts_with($identifier, 'http') ? $identifier : null);
                                @endphp
                                <article wire:key="family-profile-{{ $profile->id }}" class="flex min-w-0 flex-col rounded-lg border border-slate-200 p-3 transition hover:border-blue-200 hover:shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-100 to-indigo-100 text-lg font-bold text-blue-700">{{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $profile->name }}</h3>
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $profile->emailAccount?->email ?? 'Sin cuenta asociada' }}</p>
                                            @if(auth()->user()->isAdmin())
                                                <p class="mt-1 truncate text-xs text-indigo-700">Colaborador: {{ $profile->users->first()?->name ?? 'Sin asignar' }}</p>
                                            @endif
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
                                    <div class="my-2 grid grid-cols-3 gap-1.5 border-y border-slate-100 py-2 text-[11px]">
                                        <div><p class="text-slate-500">Comentarios</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_comments_count ?? 0) }}</p></div>
                                        <div><p class="text-slate-500">Reacciones</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_reactions_count ?? 0) }}</p></div>
                                        <div><p class="text-slate-500">Compartidos</p><p class="mt-1 font-bold text-slate-800">{{ number_format($profile->facebook_shares_count ?? 0) }}</p></div>
                                    </div>
                                    <div class="mb-2 flex items-center justify-between text-[11px] text-slate-500">
                                        <span>{{ $profile->facebook_exports_count ?? 0 }} exportaciones</span>
                                        <span>{{ $profile->latest_facebook_export_at ? \Illuminate\Support\Carbon::parse($profile->latest_facebook_export_at)->format('d/m/Y') : 'Sin exportaciones' }}</span>
                                    </div>
                                    <a href="#recent-activity" wire:click="showProfileActivity({{ $profile->id }})" class="mt-auto flex w-full items-center justify-center gap-2 rounded-md border border-blue-100 bg-blue-50/70 px-2 py-1.5 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100">
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
                            <div class="max-h-96 overflow-auto overscroll-contain">
                                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                                    <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                                        <tr><th class="px-4 py-3 font-semibold">Fecha</th><th class="px-4 py-3 font-semibold">Perfil</th><th class="px-4 py-3 font-semibold">Tipo</th><th class="px-4 py-3 font-semibold">Descripción</th><th class="px-4 py-3 font-semibold">Estado</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($activity as $evidence)
                                            @php $typeName = $evidence->evidenceType?->name ?? 'Actividad'; @endphp
                                            <tr class="align-top hover:bg-slate-50/70">
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ ($evidence->external_published_at ?? $evidence->created_at)?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                                <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">{{ $evidence->profile?->name ?? 'Perfil no asignado' }}</td>
                                                <td class="whitespace-nowrap px-4 py-3"><span class="inline-flex items-center gap-1.5 text-slate-700"><span class="h-2 w-2 rounded-full {{ str_contains(mb_strtolower($typeName), 'coment') ? 'bg-blue-500' : (str_contains(mb_strtolower($typeName), 'compart') ? 'bg-emerald-500' : 'bg-rose-500') }}"></span>{{ $typeName }}</span></td>
                                                <td class="max-w-sm break-words px-4 py-3 text-slate-500"><div class="whitespace-pre-line">{{ $evidence->comment }}</div></td>
                                                <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold text-emerald-700">Procesado</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Aún no hay comentarios, reacciones o compartidos asignados.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($activity->hasPages())
                                <div class="border-t border-slate-100 px-4 py-3 sm:px-5">{{ $activity->links() }}</div>
                            @endif
                        @else
                            <div class="max-h-96 overflow-auto overscroll-contain">
                                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                                    <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Carpeta / archivo</th><th class="px-4 py-3 font-semibold">Perfil de Facebook detectado</th><th class="px-4 py-3 font-semibold">Estado</th><th class="px-4 py-3 font-semibold">Recibida</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($imports as $importLog)
                                            @php
                                                $facebookUser = $importLog->facebookUsers->first();
                                                $metadata = is_array($facebookUser?->metadata) ? $facebookUser->metadata : [];
                                                $folderFbId = preg_match('/facebook-(\d{5,})(?:[-_\/\\\\]|$)/i', (string) $importLog->file_name, $fbMatch) ? 'ID: '.$fbMatch[1] : null;
                                                $detectedProfileLabel = $importLog->profile?->name
                                                    ?? $metadata['name']
                                                    ?? (! empty($metadata['export_identifier']) ? 'ID: '.str_replace('id:', '', (string) $metadata['export_identifier']) : null)
                                                    ?? $folderFbId
                                                    ?? 'Pendiente de coincidencia';
                                                $importMetadata = is_array($importLog->metadata) ? $importLog->metadata : [];
                                                $commentCount = (int) ($importLog->total_comments_extracted ?? 0);
                                                $reactionCount = (int) ($importLog->total_reactions_extracted ?? 0);
                                                $shareCount = (int) ($importMetadata['facebook_shares_count'] ?? (is_array($importMetadata['facebook_shares'] ?? null) ? count($importMetadata['facebook_shares']) : 0));
                                                $hasExtractedActivity = $commentCount + $reactionCount + $shareCount > 0;
                                                $statusLabel = match($importLog->status) {
                                                    'completed' => 'Procesado', 'processing' => 'Procesando', 'downloaded' => 'Descargado', 'pending_assignment' => 'Sin asignar', 'failed' => 'Error', default => ucfirst($importLog->status),
                                                };
                                                $emptyActivityLabel = match($importLog->status) {
                                                    'processing' => 'Extrayendo actividad',
                                                    'downloaded' => 'Pendiente de extracción',
                                                    'failed' => 'No se pudo extraer',
                                                    default => 'Sin actividad detectada',
                                                };
                                            @endphp
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="max-w-xs px-4 py-3"><p class="truncate font-semibold text-slate-700">{{ $importLog->file_name }}</p><p class="mt-1 truncate text-slate-400">ID: {{ $importLog->drive_file_id }}</p></td>
                                                <td class="px-4 py-3 text-slate-600">{{ $detectedProfileLabel }}</td>
                                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $importLog->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : ($importLog->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800') }}">{{ $statusLabel }}</span></td>
                                                <td class="min-w-[280px] px-4 py-3">
                                                    @if($hasExtractedActivity)
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-medium text-blue-700">Comentarios: {{ $commentCount }}</span>
                                                            <span class="rounded-full bg-rose-50 px-2 py-1 text-[10px] font-medium text-rose-700">Reacciones: {{ $reactionCount }}</span>
                                                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-700">Compartidos: {{ $shareCount }}</span>
                                                        </div>
                                                        @if($importLog->status === 'failed')
                                                            <p class="mt-1 text-[10px] text-rose-600">Extracción incompleta</p>
                                                        @endif
                                                    @else
                                                        <span class="text-slate-500">{{ $emptyActivityLabel }}</span>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $importLog->downloaded_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">No hay importaciones en esta sección.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($imports->hasPages())
                                <div class="border-t border-slate-100 px-4 py-3 sm:px-5">{{ $imports->links() }}</div>
                            @endif
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

                @if(auth()->user()->isAdmin() || auth()->user()->isCuentas())
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
                            <div x-data="{
                                    search: '',
                                    open: false,
                                    selectedId: $wire.entangle('monitorEmailAccountId'),
                                    emails: @js($emails->map(fn ($email) => ['id' => (string) $email->id, 'email' => $email->email, 'alias' => $email->alias])->values()),
                                    get selectedText() {
                                        const selected = this.emails.find((email) => email.id === String(this.selectedId));
                                        return selected ? `${selected.email}${selected.alias ? ' · ' + selected.alias : ''}` : 'Selecciona una cuenta de correo';
                                    },
                                    selectEmail(id) {
                                        this.selectedId = id;
                                        this.search = '';
                                        this.open = false;
                                    }
                                }"
                                class="relative"
                                @click.outside="open = false">
                                <button id="monitorEmailAccountId" type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-left text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <span class="truncate" x-text="selectedText"></span>
                                    <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </button>
                                <div x-cloak x-show="open" x-transition class="absolute bottom-full z-50 mb-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                    <div class="border-b border-slate-100 p-2">
                                        <input type="search" x-model="search" x-ref="emailSearch" @keydown.escape="open = false" placeholder="Buscar por correo o alias..." class="w-full rounded-md border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500" aria-label="Buscar cuenta por correo o alias">
                                    </div>
                                    <ul role="listbox" class="max-h-60 overflow-y-auto p-1">
                                        <li><button type="button" role="option" :aria-selected="!selectedId" @click="selectEmail('')" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50">Selecciona una cuenta de correo</button></li>
                                        <template x-for="email in emails.filter((email) => `${email.email} ${email.alias || ''}`.toLowerCase().includes(search.toLowerCase()))" :key="email.id">
                                            <li><button type="button" role="option" :aria-selected="String(selectedId) === email.id" @click="selectEmail(email.id)" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-blue-50"><span x-text="email.email"></span><span x-show="email.alias" class="text-slate-500" x-text="email.alias ? ' · ' + email.alias : ''"></span></button></li>
                                        </template>
                                        <li x-show="!emails.some((email) => `${email.email} ${email.alias || ''}`.toLowerCase().includes(search.toLowerCase()))" class="px-3 py-2 text-sm text-slate-500">No se encontraron correos.</li>
                                    </ul>
                                </div>
                            </div>
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
                @endif

                <footer class="flex flex-col gap-1 py-4 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <span>TraceX · Panel de monitoreo de Facebook</span><span>Exportación de Meta → Drive → base de datos local</span>
                </footer>
            </main>
        </div>
    </div>
</div>
