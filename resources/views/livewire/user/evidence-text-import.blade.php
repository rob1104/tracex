<x-slot name="header">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importar evidencias desde texto</h2>
        <a href="{{ route('evidences.create') }}" wire:navigate class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700">
            Registrar con captura
        </a>
    </div>
</x-slot>

<div class="py-10" @if($hasPendingImports) wire:poll.5s @endif>
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h3 class="text-lg font-semibold text-slate-900">Pegar texto de Facebook</h3>
                <p class="mt-1 text-sm text-slate-600">Copia el contenido de la exportación y pégalo aquí. Analizaremos las actividades en segundo plano para que revises el resultado antes de guardarlo.</p>
            </div>

            <form wire:submit="startAnalysis" class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="profileId" value="Perfil de Facebook" />
                    <div
                        x-data="{
                            open: false,
                            search: '',
                            selectedId: $wire.entangle('profileId'),
                            profiles: @js($profiles->map(fn ($profile) => ['id' => (string) $profile->id, 'name' => $profile->name])->values()),
                            init() {
                                this.syncSearchFromSelection();
                                this.$watch('selectedId', () => this.syncSearchFromSelection());
                            },
                            syncSearchFromSelection() {
                                if (!this.selectedId) {
                                    this.search = '';
                                    return;
                                }
                                const selected = this.profiles.find((profile) => profile.id === String(this.selectedId));
                                this.search = selected ? selected.name : '';
                            },
                            get filteredProfiles() {
                                const q = this.search.trim().toLowerCase();
                                if (!q) return this.profiles;
                                const selected = this.profiles.find((profile) => profile.id === String(this.selectedId));
                                if (selected && selected.name.toLowerCase() === q) {
                                    return this.profiles;
                                }
                                return this.profiles.filter((profile) => profile.name.toLowerCase().includes(q));
                            },
                            onInput() {
                                this.open = true;
                                const match = this.profiles.find((profile) => profile.name.toLowerCase() === this.search.trim().toLowerCase());
                                this.selectedId = match ? match.id : '';
                            },
                            selectProfile(id) {
                                this.selectedId = id;
                                this.syncSearchFromSelection();
                                this.open = false;
                            }
                        }"
                        @click.outside="open = false; syncSearchFromSelection()"
                        @keydown.escape="open = false; syncSearchFromSelection()"
                        class="relative mt-1"
                    >
                        <div class="relative">
                            <input
                                id="profileId"
                                x-ref="profileSearchInput"
                                type="text"
                                x-model="search"
                                @focus="open = true"
                                @click="open = true"
                                @input="onInput()"
                                placeholder="Selecciona o escribe el nombre del perfil asignado..."
                                autocomplete="off"
                                role="combobox"
                                :aria-expanded="open"
                                aria-haspopup="listbox"
                                @disabled($profiles->isEmpty())
                                class="block w-full rounded-md border-gray-300 bg-white py-2 pl-3 pr-9 text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                            >
                            <button
                                type="button"
                                @click="if (selectedId) { selectProfile(''); $nextTick(() => { open = true; $refs.profileSearchInput.focus(); }); } else { open = !open; if (open) $nextTick(() => $refs.profileSearchInput.focus()); }"
                                @disabled($profiles->isEmpty())
                                class="absolute inset-y-0 right-0 flex items-center px-2.5 text-gray-400 hover:text-gray-600 disabled:cursor-not-allowed"
                                :title="selectedId ? 'Cambiar perfil' : 'Mostrar perfiles'"
                            >
                                <template x-if="selectedId">
                                    <span class="text-sm font-bold leading-none">&times;</span>
                                </template>
                                <template x-if="!selectedId">
                                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
                                </template>
                            </button>
                        </div>
                        <div x-cloak x-show="open" x-transition class="absolute z-30 mt-1 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                            <ul role="listbox" class="max-h-56 overflow-y-auto py-1">
                                <template x-for="profile in filteredProfiles" :key="profile.id">
                                    <li>
                                        <button
                                            type="button"
                                            role="option"
                                            :aria-selected="String(selectedId) === profile.id"
                                            @click="selectProfile(profile.id)"
                                            class="flex w-full items-center justify-between truncate px-3 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50"
                                            :class="String(selectedId) === profile.id ? 'bg-indigo-50 font-semibold text-indigo-700' : ''"
                                        >
                                            <span class="truncate" x-text="profile.name"></span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="filteredProfiles.length === 0" class="px-3 py-2 text-sm text-gray-500">No se encontraron perfiles con ese nombre.</li>
                            </ul>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('profileId')" class="mt-2" />
                    @if($profiles->isEmpty())
                        <p class="mt-2 text-sm text-amber-700">No tienes perfiles activos de Facebook asignados.</p>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="pastedText" value="Texto de la exportación" />
                    <textarea id="pastedText" wire:model="pastedText" rows="14" spellcheck="false" placeholder="Copia y pega aquí el contenido completo de la exportación de Facebook…" required class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <p class="mt-1 text-xs text-slate-500">Pega el texto directamente; no necesitas guardar ni seleccionar el archivo. Máximo 5 MB. Se conservan acentos, emojis y saltos de línea.</p>
                    <x-input-error :messages="$errors->get('pastedText')" class="mt-2" />
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="startAnalysis" @disabled($profiles->isEmpty()) class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="startAnalysis">Analizar y preparar vista previa</span>
                        <span wire:loading wire:target="startAnalysis">Enviando texto a análisis…</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Importaciones recientes</h3>
                <p class="text-sm text-slate-600">Revisa el avance, las actividades interpretadas y las entradas pendientes de revisión.</p>
            </div>

            @forelse($imports as $import)
                @php
                    $statusLabels = [
                        'queued_preview' => 'En espera de análisis',
                        'analyzing' => 'Analizando texto',
                        'awaiting_confirmation' => 'Vista previa lista',
                        'queued' => 'En espera de importación',
                        'processing' => 'Importando evidencias',
                        'completed' => 'Importación finalizada',
                        'failed' => 'Error',
                        'cancelled' => 'Descartada',
                    ];
                    $statusColors = [
                        'failed' => 'bg-red-100 text-red-800',
                        'completed' => 'bg-emerald-100 text-emerald-800',
                        'awaiting_confirmation' => 'bg-indigo-100 text-indigo-800',
                        'cancelled' => 'bg-slate-100 text-slate-600',
                    ];
                    $progress = $import->total_entries > 0
                        ? min(100, (int) floor(($import->processed_count / $import->total_entries) * 100))
                        : 0;
                @endphp

                <article wire:key="text-import-{{ $import->id }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h4 class="break-all font-semibold text-slate-900">{{ $import->original_file_name }}</h4>
                            <p class="mt-1 text-sm text-slate-600">Perfil: {{ $import->profile?->name ?? 'Perfil eliminado' }} · {{ $import->created_at->format('d/m/Y h:i A') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusColors[$import->status] ?? 'bg-amber-100 text-amber-800' }}">
                            {{ $statusLabels[$import->status] ?? $import->status }}
                        </span>
                    </div>

                    @if(in_array($import->status, ['queued_preview', 'analyzing'], true))
                        <div class="space-y-2 px-5 py-4">
                            <p class="text-sm text-slate-600">El texto se está procesando fuera de la solicitud web. Entradas analizadas: {{ number_format($import->processed_count) }}.</p>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full animate-pulse rounded-full bg-indigo-500" style="width: 45%"></div></div>
                        </div>
                    @elseif($import->status === 'awaiting_confirmation')
                        <div class="space-y-5 p-5">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg bg-emerald-50 p-3"><div class="text-2xl font-bold text-emerald-800">{{ number_format($import->recognized_count) }}</div><div class="text-xs font-medium text-emerald-700">Actividades detectadas</div></div>
                                <div class="rounded-lg bg-amber-50 p-3"><div class="text-2xl font-bold text-amber-800">{{ number_format($import->unrecognized_count) }}</div><div class="text-xs font-medium text-amber-700">Entradas para revisión</div></div>
                                <div class="rounded-lg bg-slate-50 p-3"><div class="text-2xl font-bold text-slate-800">{{ number_format($import->total_entries) }}</div><div class="text-xs font-medium text-slate-600">Entradas encontradas</div></div>
                            </div>

                            @if($import->recognized_count === 0)
                                <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">No se detectaron actividades que puedan importarse. Revisa las entradas sin interpretar o confirma que el texto corresponda a una exportación de Facebook.</p>
                            @endif

                            @if($import->preview_records)
                                <div>
                                    <h5 class="mb-2 font-semibold text-slate-800">Vista previa de actividades (máximo 30)</h5>
                                    <div class="overflow-x-auto rounded-lg border border-slate-200">
                                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                                <tr><th class="px-3 py-2">Tipo</th><th class="px-3 py-2">Fecha y hora</th><th class="px-3 py-2">Texto</th></tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach($import->preview_records as $record)
                                                    <tr class="align-top">
                                                        <td class="whitespace-nowrap px-3 py-2 font-medium text-indigo-700">{{ $record['type_label'] }}</td>
                                                        <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ \Carbon\Carbon::parse($record['occurred_at'])->format('d/m/Y h:i A') }}</td>
                                                        <td class="max-w-2xl whitespace-pre-line px-3 py-2 text-slate-700">{{ $record['text'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            @if($import->preview_unparsed)
                                <details class="rounded-lg border border-amber-200 bg-amber-50">
                                    <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-amber-900">Entradas que requieren revisión (se muestran hasta 50)</summary>
                                    <div class="max-h-96 space-y-3 overflow-y-auto px-4 pb-4">
                                        @foreach($import->preview_unparsed as $entry)
                                            <div class="rounded-md border border-amber-200 bg-white p-3">
                                                <div class="text-xs font-semibold text-amber-800">Línea {{ $entry['source_line'] }} · {{ $entry['reason'] }}</div>
                                                <pre class="mt-2 whitespace-pre-wrap break-words font-sans text-sm text-slate-700">{{ $entry['text'] }}</pre>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @elseif($import->unrecognized_count > 0)
                                <p class="text-sm text-amber-800">Hay {{ number_format($import->unrecognized_count) }} entradas sin interpretar. La vista muestra las primeras 50.</p>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                <p class="text-xs text-slate-500">La importación evita duplicados por perfil, tipo, texto y fecha/hora.</p>
                                <div class="flex gap-2">
                                    <button wire:click="discardImport({{ $import->id }})" wire:confirm="¿Descartar esta vista previa y borrar el texto temporal?" class="rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50">Descartar</button>
                                    <button wire:click="confirmImport({{ $import->id }})" @disabled($import->recognized_count < 1) class="rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Confirmar importación</button>
                                </div>
                            </div>
                        </div>
                    @elseif(in_array($import->status, ['queued', 'processing'], true))
                        <div class="space-y-3 px-5 py-4">
                            <div class="flex justify-between text-sm text-slate-600"><span>Avance: {{ number_format($import->processed_count) }} de {{ number_format($import->total_entries) }}</span><span>{{ $progress }}%</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ $progress }}%"></div></div>
                            <p class="text-xs text-slate-500">Guardadas: {{ number_format($import->imported_count) }} · Duplicadas omitidas: {{ number_format($import->duplicate_count) }} · Sin interpretar: {{ number_format($import->unrecognized_count) }}</p>
                        </div>
                    @elseif($import->status === 'completed')
                        <div class="px-5 py-4 text-sm text-slate-700">
                            Importadas: <strong>{{ number_format($import->imported_count) }}</strong> · Duplicadas omitidas: <strong>{{ number_format($import->duplicate_count) }}</strong> · Entradas sin interpretar: <strong>{{ number_format($import->unrecognized_count) }}</strong>.
                        </div>
                    @elseif($import->status === 'failed')
                        <div class="space-y-3 px-5 py-4">
                            <p class="text-sm text-red-700">{{ $import->error_message ?: 'No se pudo procesar el texto.' }}</p>
                            <button wire:click="discardImport({{ $import->id }})" class="rounded-md border border-red-200 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-700 hover:bg-red-50">Cerrar y borrar temporales</button>
                        </div>
                    @elseif($import->status === 'cancelled')
                        <p class="px-5 py-4 text-sm text-slate-500">Se descartó esta importación y se eliminaron sus archivos temporales.</p>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Todavía no hay textos de Facebook para analizar.</div>
            @endforelse
        </section>
    </div>
</div>
