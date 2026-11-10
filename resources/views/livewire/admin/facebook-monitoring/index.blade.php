<div class="min-h-[calc(100vh-4rem)] bg-slate-100" wire:poll.60s>
    <div class="mx-auto max-w-[1720px] px-0 sm:px-4 lg:px-6">
        <div class="min-h-[calc(100vh-4rem)] overflow-hidden bg-white shadow-sm lg:rounded-xl">

            <main class="min-w-0 bg-slate-50/70 p-4 sm:p-6 xl:p-8">
                <header class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-600">Módulo de monitoreo</p>
                        <h1 class="mt-1 flex items-center gap-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ auth()->user()->canMonitorCollaborators() ? 'Monitoreo de colaboradores' : 'Monitoreo de usuarios' }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">{{ auth()->user()->canMonitorCollaborators() ? 'Consulta las exportaciones y actividad de Facebook asociadas a cada colaborador.' : 'Consulta la actividad de Facebook recibida por Google Drive o mediante cargas locales.' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
                            <span class="font-semibold text-slate-700">Google Drive:</span>
                            <span class="{{ $driveConfigured ? 'text-emerald-700' : 'text-amber-700' }}">{{ $driveConfigured ? 'Configurado' : 'Pendiente de configurar' }}</span>
                        </div>

                    </div>
                </header>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</div>
                @endif

                <section class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumen del sistema">
                    <article class="flex h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-lg text-blue-600">♟</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Perfiles asignados</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalProfiles }}</p><p class="mt-1 text-xs text-slate-400">{{ $profilesMissingIdCount > 0 ? $profilesMissingIdCount.' sin ID vinculado' : 'Todos con ID vinculado' }}</p></div>
                    </article>
                    <article class="flex h-24 items-center gap-3 rounded-xl border border-emerald-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 text-lg font-bold text-emerald-600">&#10003;</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Con actividad</p><p class="mt-0.5 text-xl font-bold text-emerald-700">{{ $profilesWithActivityCount }}</p><p class="mt-1 text-xs text-slate-400">{{ $activityCoveragePercent }}% de cobertura</p></div>
                    </article>
                    <article class="flex h-24 items-center gap-3 rounded-xl border border-amber-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-amber-50 text-lg font-bold text-amber-600">!</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Sin actividad</p><p class="mt-0.5 text-xl font-bold text-amber-700">{{ $profilesWithoutActivityCount }}</p><p class="mt-1 text-xs text-slate-400">{{ $totalProfiles > 0 ? (100 - $activityCoveragePercent).'% pendiente' : 'Sin perfiles' }}</p></div>
                    </article>
                    <article class="flex h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-blue-50 text-lg text-blue-600">↓</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Importaciones</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $totalImports }}</p><p class="mt-1 text-xs text-slate-400">Exportaciones y textos recibidos</p></div>
                    </article>
                    <article class="flex h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-indigo-50 text-lg text-indigo-600">⟳</span>
                        <div class="min-w-0"><p class="text-xs text-slate-500">Procesamiento</p><p class="mt-0.5 text-xl font-bold text-slate-900">{{ $processingCount > 0 ? 'En proceso' : 'Al día' }}</p><p class="mt-1 text-xs text-slate-400">{{ $processingCount }} cargas pendientes</p></div>
                    </article>
                </section>

                <div class="mb-5">
                    <section id="monitored-profiles" class="rounded-xl border border-slate-200 bg-white shadow-sm" aria-label="Gestión de Perfiles de Facebook">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-slate-900">Gestión de Perfiles de Facebook</h2>
                                    <p class="text-sm text-slate-500">{{ auth()->user()->canMonitorCollaborators() ? 'Cobertura de actividad, perfiles asignados y exportaciones vinculadas a los colaboradores.' : 'Cobertura de actividad acumulada y exportaciones asociadas a cada perfil asignado.' }}</p>
                                </div>
                                <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:flex-nowrap sm:items-center">
                                    @if(auth()->user()->canMonitorCollaborators())
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
                                            class="relative w-full sm:w-56"
                                            @click.outside="open = false">
                                            <label class="sr-only" for="collaboratorFilter">Buscar o filtrar por colaborador</label>
                                            <button id="collaboratorFilter" type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
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
                                    <div class="relative w-full sm:w-64">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">⌕</span>
                                        <input wire:model.live.debounce.300ms="search" type="search" aria-label="Buscar usuario" placeholder="Buscar usuario..." class="w-full rounded-lg border-slate-200 py-2 pl-9 text-sm">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="mb-1.5 flex items-center justify-between text-xs">
                                        <span class="font-semibold text-slate-700">{{ $profilesWithActivityCount }} de {{ $totalProfiles }} perfiles han recibido actividad</span>
                                        <span class="font-bold {{ $activityCoveragePercent >= 70 ? 'text-emerald-700' : ($activityCoveragePercent >= 35 ? 'text-amber-700' : 'text-rose-700') }}">{{ $activityCoveragePercent }}%</span>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $activityCoveragePercent }}%"></div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 lg:shrink-0">
                                    @foreach([
                                        'all' => 'Todos ('.$totalProfiles.')',
                                        'with_activity' => 'Con actividad ('.$profilesWithActivityCount.')',
                                        'without_activity' => 'Sin actividad ('.$profilesWithoutActivityCount.')',
                                        'missing_id' => 'Sin ID ('.$profilesMissingIdCount.')',
                                    ] as $filterKey => $filterLabel)
                                        <button type="button" wire:click="setProfileActivityFilter('{{ $filterKey }}')" class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition {{ $profileActivityFilter === $filterKey ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                                            {{ $filterLabel }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            @forelse($profiles as $profile)
                                @php
                                    $identifier = (string) $profile->facebook_identifier;
                                    $facebookUrl = str_starts_with($identifier, 'uri:') ? substr($identifier, 4) : (str_starts_with($identifier, 'http') ? $identifier : null);
                                    $cardActivityCount = (int) ($profile->facebook_comments_count ?? 0)
                                        + (int) ($profile->facebook_txt_comments_count ?? 0)
                                        + (int) ($profile->facebook_reactions_count ?? 0)
                                        + (int) ($profile->facebook_txt_reactions_count ?? 0)
                                        + (int) ($profile->facebook_shares_count ?? 0);
                                @endphp
                                <article wire:key="family-profile-{{ $profile->id }}" class="flex min-w-0 flex-col rounded-lg border border-slate-200 p-3 transition hover:border-blue-200 hover:shadow-sm">
                                    <div class="flex items-start gap-3">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-blue-100 to-indigo-100 text-lg font-bold text-blue-700">{{ mb_strtoupper(mb_substr($profile->name, 0, 1)) }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-1.5">
                                                <h3 class="truncate text-sm font-bold text-slate-900">{{ $profile->name }}</h3>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $cardActivityCount > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ $cardActivityCount > 0 ? 'Con actividad' : 'Sin actividad' }}
                                                </span>
                                            </div>
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $profile->emailAccount?->email ?? 'Sin cuenta asociada' }}</p>
                                            @if(auth()->user()->canMonitorCollaborators())
                                                <p class="mt-1 break-words text-xs leading-4 text-indigo-700">Colaborador: {{ $profile->users->first()?->name ?? 'Sin asignar' }}</p>
                                            @endif
                                            <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium {{ $identifier !== '' ? 'text-emerald-700' : 'text-amber-700' }}"><span class="h-2 w-2 rounded-full {{ $identifier !== '' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span> {{ $identifier !== '' ? 'Facebook vinculado' : 'ID por asociar' }}</span>
                                        </div>
                                    </div>
                                    @if($facebookUrl)
                                        <a href="{{ $facebookUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 truncate text-xs text-blue-600 hover:underline">{{ $facebookUrl }}</a>
                                    @elseif($identifier !== '')
                                        <p class="mt-2 truncate text-xs text-slate-500">ID de Facebook: {{ str_starts_with($identifier, 'id:') ? substr($identifier, 3) : $identifier }}</p>
                                    @else
                                        <div class="mt-2 rounded-md border border-amber-200 bg-amber-50/70 p-2">
                                            <form wire:submit="assignFacebookIdentifier({{ $profile->id }})" class="space-y-1.5">
                                                <label for="fb-id-input-{{ $profile->id }}" class="block text-[11px] font-semibold text-amber-900">Asignar ID o enlace de Facebook</label>
                                                <div class="flex gap-1.5">
                                                    <input
                                                        id="fb-id-input-{{ $profile->id }}"
                                                        type="text"
                                                        wire:model="profileFacebookIdentifiers.{{ $profile->id }}"
                                                        placeholder="ID numérico o facebook.com/..."
                                                        class="min-w-0 flex-1 rounded-md border-amber-300 bg-white px-2 py-1 text-xs text-slate-800 focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                    <button
                                                        type="submit"
                                                        wire:loading.attr="disabled"
                                                        wire:target="assignFacebookIdentifier({{ $profile->id }})"
                                                        class="shrink-0 rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                                    >
                                                        Guardar ID
                                                    </button>
                                                </div>
                                                @error('profileFacebookIdentifiers.'.$profile->id)
                                                    <p class="text-[11px] text-rose-600">{{ $message }}</p>
                                                @enderror
                                                @if($assigningIdentifierProfileId === $profile->id)
                                                    @error('assigningFacebookIdentifier')
                                                        <p class="text-[11px] text-rose-600">{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </form>
                                        </div>
                                    @endif
                                    <div class="my-2 grid grid-cols-3 gap-1.5 border-y border-slate-100 py-2 text-[11px]">
                                        <div><p class="text-slate-500">Comentarios</p><p class="mt-1 font-bold text-slate-800">{{ number_format(($profile->facebook_comments_count ?? 0) + ($profile->facebook_txt_comments_count ?? 0)) }}</p></div>
                                        <div><p class="text-slate-500">Reacciones</p><p class="mt-1 font-bold text-slate-800">{{ number_format(($profile->facebook_reactions_count ?? 0) + ($profile->facebook_txt_reactions_count ?? 0)) }}</p></div>
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
                                    <p class="mt-1 text-xs text-slate-500">Comentarios, reacciones y publicaciones compartidas de exportaciones y textos importados.</p>
                                @endif
                            </div>
                            <a href="{{ auth()->user()->canMonitorCollaborators() ? route('admin.evidences.index') : route('admin.profiles.index') }}" wire:navigate class="text-xs font-semibold text-blue-600 hover:underline">Ver reporte</a>
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
                                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $evidence->origin === 'facebook_txt' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $evidence->origin === 'facebook_txt' ? 'Importado por texto' : 'Procesado' }}</span></td>
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
                        @elseif($activityTab === 'imports')
                            <div class="max-h-96 overflow-auto overscroll-contain">
                                <table class="w-full table-fixed divide-y divide-slate-100 text-left text-xs">
                                    <colgroup>
                                        <col class="w-[10%]">
                                        <col class="w-[17%]">
                                        <col class="w-[12%]">
                                        <col class="w-[12%]">
                                        <col class="w-[15%]">
                                        <col class="w-[19%]">
                                        <col class="w-[15%]">
                                    </colgroup>
                                    <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                                        <tr><th class="px-2 py-3 font-semibold">Método</th><th class="px-2 py-3 font-semibold">Archivo / texto</th><th class="px-2 py-3 font-semibold">Perfil de Facebook</th><th class="px-2 py-3 font-semibold">Colaborador</th><th class="px-2 py-3 font-semibold">Estado</th><th class="px-2 py-3 font-semibold">Resultado</th><th class="px-2 py-3 font-semibold">Recibida</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($combinedImports as $importRow)
                                            @php
                                                $isTextImport = $importRow->source_type === 'text';
                                                $importRecord = $isTextImport
                                                    ? $textImportRecords->get($importRow->source_id)
                                                    : $exportImportRecords->get($importRow->source_id);
                                                $importMetadata = ! $isTextImport && is_array($importRecord?->metadata) ? $importRecord->metadata : [];
                                                $methodLabel = $isTextImport
                                                    ? 'TXT'
                                                    : (($importMetadata['source'] ?? null) === 'local_upload' ? 'Carga local' : 'Google Drive');
                                                $profileLabel = $isTextImport
                                                    ? ($importRecord?->profile?->name ?? 'Perfil no disponible')
                                                    : ($detectedImportProfileNames[$importRow->source_id] ?? $importRecord?->profile?->name ?? 'Nombre no incluido en la exportación');
                                                $collaboratorLabel = $importRecord?->user?->name ?? 'Usuario no disponible';
                                                $statusLabel = $isTextImport
                                                    ? match($importRow->status) {
                                                        'queued_preview' => 'En cola para analizar', 'analyzing' => 'Analizando', 'awaiting_confirmation' => 'Vista previa lista', 'queued' => 'En cola para importar', 'processing' => 'Importando', 'completed' => 'Completada', 'failed' => 'Error', 'cancelled' => 'Descartada', default => ucfirst($importRow->status),
                                                    }
                                                    : match($importRow->status) {
                                                        'completed' => 'Procesado', 'processing' => 'Procesando', 'downloaded' => 'Descargado', 'pending_assignment' => 'Sin asignar', 'failed' => 'Error', default => ucfirst($importRow->status),
                                                    };
                                                $statusClass = match($importRow->status) {
                                                    'completed' => 'bg-emerald-50 text-emerald-700',
                                                    'failed' => 'bg-rose-50 text-rose-700',
                                                    'awaiting_confirmation' => 'bg-blue-50 text-blue-700',
                                                    'cancelled' => 'bg-slate-100 text-slate-600',
                                                    default => 'bg-amber-50 text-amber-800',
                                                };
                                                $importFiles = is_array($importMetadata['files'] ?? null) ? $importMetadata['files'] : [];
                                                $importDiagnostics = is_array($importMetadata['diagnostics'] ?? null) ? $importMetadata['diagnostics'] : [];
                                                $diagnosticDetail = $importDiagnostics['empty_reason_label'] ?? null;
                                                $errorText = (string) ($importRecord?->error_message ?? '');
                                                $basenames = array_map(fn ($path) => strtolower(basename((string) $path)), $importFiles);
                                                $textCounts = $isTextImport
                                                    ? ($textImportActivityCounts[$importRow->source_id] ?? ['comments' => 0, 'reactions' => 0, 'shares' => 0])
                                                    : ['comments' => 0, 'reactions' => 0, 'shares' => 0];
                                                $exportCommentCount = $isTextImport
                                                    ? (int) ($textCounts['comments'] ?? 0)
                                                    : (int) ($importRecord?->total_comments_extracted ?? 0);
                                                $exportReactionCount = $isTextImport
                                                    ? (int) ($textCounts['reactions'] ?? 0)
                                                    : (int) ($importRecord?->total_reactions_extracted ?? 0);
                                                $exportShareCount = $isTextImport
                                                    ? (int) ($textCounts['shares'] ?? 0)
                                                    : max(
                                                        (int) ($importRecord?->total_shares_extracted ?? 0),
                                                        (int) ($importMetadata['facebook_shares_count'] ?? (is_array($importMetadata['facebook_shares'] ?? null) ? count($importMetadata['facebook_shares']) : 0))
                                                    );
                                                $hasExportActivity = $exportCommentCount + $exportReactionCount + $exportShareCount > 0;
                                                if (! $isTextImport && $diagnosticDetail === null && ! $hasExportActivity) {
                                                    if (str_contains($errorText, 'formato HTML')) {
                                                        $diagnosticDetail = 'Exportación en formato HTML (se requiere JSON)';
                                                    } elseif (in_array('no_data.json', $basenames, true)) {
                                                        $diagnosticDetail = 'Meta sin actividad en el periodo (no_data.json)';
                                                    } elseif ($basenames !== [] && ! collect($basenames)->contains(fn ($basename) => (bool) preg_match('/^(comments|group_comments|your_comments_in_groups|likes_and_reactions|reactions|your_posts|posts|group_posts|your_posts_in_groups|shared_memories).*\.json$/i', $basename))) {
                                                        $diagnosticDetail = 'Solo archivos de perfil/dispositivos (sin archivos de actividad)';
                                                    }
                                                }
                                                $textImportIsProcessing = in_array($importRow->status, ['queued_preview', 'analyzing', 'queued', 'processing'], true);
                                                $textImportName = $importRecord?->original_file_name ?? $importRow->display_name;
                                                $exportFileName = $importRecord?->file_name ?? $importRow->display_name;
                                                $emptyExportActivityLabel = $isTextImport
                                                    ? match($importRow->status) {
                                                        'queued_preview', 'analyzing' => 'Analizando actividad',
                                                        'awaiting_confirmation' => 'Pendiente de confirmación',
                                                        'queued', 'processing' => 'Importando actividad',
                                                        'failed' => 'No se pudo extraer',
                                                        'cancelled' => 'Importación descartada',
                                                        default => 'Sin actividad detectada',
                                                    }
                                                    : match($importRow->status) {
                                                        'processing' => 'Extrayendo actividad',
                                                        'downloaded' => 'Pendiente de extracción',
                                                        'failed' => 'No se pudo extraer',
                                                        default => 'Sin actividad detectada',
                                                    };
                                            @endphp
                                            <tr class="align-top hover:bg-slate-50/70">
                                                <td class="whitespace-nowrap px-2 py-3"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold {{ $isTextImport ? 'bg-indigo-50 text-indigo-700' : (($importMetadata['source'] ?? null) === 'local_upload' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600') }}">{{ $methodLabel }}</span></td>
                                                <td class="px-2 py-3"><p title="{{ $isTextImport ? $textImportName : $exportFileName }}" class="truncate font-semibold text-slate-700">{{ $isTextImport ? $textImportName : $exportFileName }}</p></td>
                                                <td class="px-2 py-3 text-slate-600">{{ $profileLabel }}</td>
                                                <td class="px-2 py-3 text-slate-600">{{ $collaboratorLabel }}</td>
                                                <td class="whitespace-nowrap px-2 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                                <td class="break-words px-2 py-3 text-slate-600">
                                                    @if($hasExportActivity)
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-medium text-blue-700">Comentarios: {{ $exportCommentCount }}</span>
                                                            <span class="rounded-full bg-rose-50 px-2 py-1 text-[10px] font-medium text-rose-700">Reacciones: {{ $exportReactionCount }}</span>
                                                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-medium text-emerald-700">Compartidos: {{ $exportShareCount }}</span>
                                                        </div>
                                                        @if($isTextImport && $textImportIsProcessing)
                                                            <p class="mt-1 text-[10px] text-slate-400">Procesadas {{ number_format($importRecord?->processed_count ?? 0) }} de {{ number_format($importRecord?->total_entries ?? 0) }}</p>
                                                        @elseif($isTextImport && $importRow->status === 'awaiting_confirmation')
                                                            <p class="mt-1 text-[10px] text-blue-600">Pendiente de confirmación desde el módulo de importación.</p>
                                                        @elseif($importRow->status === 'failed')
                                                            <p class="mt-1 text-[10px] text-rose-600">{{ $isTextImport && $errorText !== '' ? $errorText : 'Extracción incompleta' }}</p>
                                                        @endif
                                                    @else
                                                        <span class="text-slate-500">{{ $emptyExportActivityLabel }}</span>
                                                        @if($isTextImport && $textImportIsProcessing)
                                                            <p class="mt-0.5 text-[10px] text-slate-400">Procesadas {{ number_format($importRecord?->processed_count ?? 0) }} de {{ number_format($importRecord?->total_entries ?? 0) }}</p>
                                                        @elseif($isTextImport && $errorText !== '')
                                                            <p class="mt-0.5 break-words text-[10px] text-rose-600">{{ $errorText }}</p>
                                                        @elseif($diagnosticDetail)
                                                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $diagnosticDetail }}</p>
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-2 py-3 text-slate-500">{{ $importRow->received_at ? \Illuminate\Support\Carbon::parse($importRow->received_at)->format('d/m/Y H:i') : 'Sin fecha' }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No hay importaciones para estos filtros.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            @if($combinedImports->hasPages())
                                <div class="border-t border-slate-100 px-4 py-3 sm:px-5">{{ $combinedImports->links() }}</div>
                            @endif
                        @else
                            <div class="max-h-96 overflow-auto overscroll-contain">
                                <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                                    <thead class="sticky top-0 z-10 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Carpeta / archivo</th><th class="px-4 py-3 font-semibold">Perfil de Facebook detectado</th><th class="px-4 py-3 font-semibold">Estado</th><th class="px-4 py-3 font-semibold">Recibida</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse($imports as $importLog)
                                            @php
                                                $importMetadata = is_array($importLog->metadata) ? $importLog->metadata : [];
                                                $detectedProfileLabel = $detectedImportProfileNames[$importLog->id] ?? 'Nombre no incluido en la exportación';
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
                                                $importFiles = is_array($importMetadata['files'] ?? null) ? $importMetadata['files'] : [];
                                                $importDiagnostics = is_array($importMetadata['diagnostics'] ?? null) ? $importMetadata['diagnostics'] : [];
                                                $diagnosticDetail = $importDiagnostics['empty_reason_label'] ?? null;
                                                if ($diagnosticDetail === null && ! $hasExtractedActivity) {
                                                    $errorText = (string) ($importLog->error_message ?? '');
                                                    $basenames = array_map(fn ($p) => strtolower(basename((string) $p)), $importFiles);
                                                    if (str_contains($errorText, 'formato HTML')) {
                                                        $diagnosticDetail = 'Exportación en formato HTML (se requiere JSON)';
                                                    } elseif (in_array('no_data.json', $basenames, true)) {
                                                        $diagnosticDetail = 'Meta sin actividad en el periodo (no_data.json)';
                                                    } elseif ($basenames !== [] && ! collect($basenames)->contains(fn ($b) => (bool) preg_match('/^(comments|group_comments|your_comments_in_groups|likes_and_reactions|reactions|your_posts|posts|group_posts|your_posts_in_groups|shared_memories).*\.json$/i', $b))) {
                                                        $diagnosticDetail = 'Solo archivos de perfil/dispositivos (sin archivos de actividad)';
                                                    }
                                                }
                                            @endphp
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="max-w-xs px-4 py-3"><p class="truncate font-semibold text-slate-700">{{ $importLog->file_name }}</p><span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ ($importMetadata['source'] ?? null) === 'local_upload' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-500' }}">{{ ($importMetadata['source'] ?? null) === 'local_upload' ? 'Carga local' : 'Google Drive' }}</span></td>
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
                                                        @if($diagnosticDetail)
                                                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $diagnosticDetail }}</p>
                                                        @endif
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
                                    <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">Cuando se procese una exportación o confirmes una importación por texto, sus actividades aparecerán aquí.</p>
                                @else
                                    <p class="mt-1 max-w-xs text-xs leading-5 text-slate-500">No hay evidencias asignadas en este periodo. Revisa la pestaña “Sin asignar” si una exportación aún no tiene perfil asociado.</p>
                                @endif
                            </div>
                        @endif
                    </section>
                </div>

                @if(auth()->user()->canMonitorCollaborators() || auth()->user()->isCuentas())
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
                    <span>TraceX · Panel de monitoreo de Facebook</span><span>Exportación de Meta → Google Drive o carga local → base de datos</span>
                </footer>
            </main>
        </div>
    </div>
</div>
