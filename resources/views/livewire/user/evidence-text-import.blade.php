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
                    <select id="profileId" wire:model="profileId" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecciona un perfil asignado</option>
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                        @endforeach
                    </select>
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
                    $duplicateDecisions = $import->duplicate_decisions ?? [];
                    $duplicatePreviews = $import->duplicate_preview ?? [];
                    $decisionErrorKey = 'duplicateDecisions.'.$import->id;
                    $hasUnreviewedDuplicates = collect($duplicatePreviews)->contains(function (array $duplicate) use ($duplicateDecisions): bool {
                        $sourceLine = (string) $duplicate['source_line'];

                        return ! in_array($duplicateDecisions[$sourceLine] ?? null, ['skip', 'import', 'replace'], true);
                    });
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

                            @if($duplicatePreviews !== [])
                                <section class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                    <div>
                                        <h5 class="font-semibold text-amber-950">Coincidencias posibles para revisar ({{ number_format(count($duplicatePreviews)) }})</h5>
                                        <p class="mt-1 text-sm text-amber-900">Compara el texto completo con el registro existente. Elige una opción para cada actividad antes de confirmar.</p>
                                    </div>

                                    @foreach($duplicatePreviews as $duplicate)
                                        @php
                                            $sourceLine = (int) $duplicate['source_line'];
                                            $decision = $duplicateDecisions[(string) $sourceLine] ?? null;
                                            $databaseMatch = $duplicate['matches']['database'] ?? null;
                                            $sameImportMatch = $duplicate['matches']['same_import'] ?? null;
                                        @endphp

                                        <article wire:key="duplicate-review-{{ $import->id }}-{{ $sourceLine }}" class="space-y-3 rounded-lg border border-amber-200 bg-white p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="text-sm font-semibold text-slate-900">
                                                    {{ $duplicate['type_label'] }} · {{ \Carbon\Carbon::parse($duplicate['occurred_at'])->format('d/m/Y h:i A') }} · línea {{ $sourceLine }}
                                                </div>
                                                @if($decision)
                                                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                                                        {{ ['skip' => 'Se omitirá', 'import' => 'Se subirá de todos modos', 'replace' => 'Se reemplazará'][$decision] }}
                                                    </span>
                                                @else
                                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Pendiente de decisión</span>
                                                @endif
                                            </div>

                                            <div class="grid gap-3 lg:grid-cols-2">
                                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                                    <h6 class="text-xs font-bold uppercase tracking-wide text-slate-500">Actividad del TXT</h6>
                                                    <pre class="mt-2 whitespace-pre-wrap break-words font-sans text-sm text-slate-800">{{ $duplicate['text'] }}</pre>
                                                </div>

                                                @if($databaseMatch)
                                                    <div class="rounded-md border border-indigo-200 bg-indigo-50 p-3">
                                                        <h6 class="text-xs font-bold uppercase tracking-wide text-indigo-800">Ya guardada · evidencia #{{ $databaseMatch['evidence_id'] }}</h6>
                                                        <p class="mt-1 text-xs text-indigo-700">Colaborador: {{ $databaseMatch['user_name'] }} · Tipo: {{ $databaseMatch['type_name'] }} · Origen: {{ $databaseMatch['origin'] ?: 'manual' }}@if($databaseMatch['occurred_at']) · {{ \Carbon\Carbon::parse($databaseMatch['occurred_at'])->format('d/m/Y h:i A') }}@endif</p>
                                                        <pre class="mt-2 whitespace-pre-wrap break-words font-sans text-sm text-slate-800">{{ $databaseMatch['text'] }}</pre>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($sameImportMatch)
                                                <p class="rounded-md bg-slate-100 px-3 py-2 text-xs text-slate-700">También coincide con otra actividad de este mismo texto, en la línea {{ $sameImportMatch['source_line'] }}.</p>
                                            @endif

                                            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                                <button type="button" wire:click="setDuplicateDecision({{ $import->id }}, {{ $sourceLine }}, 'skip')" aria-pressed="{{ $decision === 'skip' ? 'true' : 'false' }}" class="rounded-md border px-3 py-2 text-xs font-semibold {{ $decision === 'skip' ? 'border-indigo-500 bg-indigo-50 text-indigo-800 ring-1 ring-indigo-500' : 'border-slate-300 text-slate-700 hover:bg-slate-50' }}">Omitir</button>
                                                <button type="button" wire:click="setDuplicateDecision({{ $import->id }}, {{ $sourceLine }}, 'import')" aria-pressed="{{ $decision === 'import' ? 'true' : 'false' }}" class="rounded-md border px-3 py-2 text-xs font-semibold {{ $decision === 'import' ? 'border-indigo-500 bg-indigo-50 text-indigo-800 ring-1 ring-indigo-500' : 'border-slate-300 text-slate-700 hover:bg-slate-50' }}">Subir de todos modos</button>
                                                @if($databaseMatch)
                                                    <button type="button" wire:click="setDuplicateDecision({{ $import->id }}, {{ $sourceLine }}, 'replace')" wire:confirm="¿Reemplazar la evidencia guardada #{{ $databaseMatch['evidence_id'] }} con la actividad del TXT? Se conservarán sus capturas." aria-pressed="{{ $decision === 'replace' ? 'true' : 'false' }}" class="rounded-md border px-3 py-2 text-xs font-semibold {{ $decision === 'replace' ? 'border-indigo-500 bg-indigo-50 text-indigo-800 ring-1 ring-indigo-500' : 'border-rose-300 text-rose-700 hover:bg-rose-50' }}">Reemplazar registro</button>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach

                                    @error($decisionErrorKey)
                                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                                    @enderror

                                    @if($hasUnreviewedDuplicates)
                                        <p class="text-xs font-medium text-amber-900">Selecciona una opción para cada coincidencia antes de continuar.</p>
                                    @endif
                                </section>
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
                                <p class="text-xs text-slate-500">Revisa cada coincidencia y elige si omitirla, subirla o reemplazar la evidencia guardada.</p>
                                <div class="flex gap-2">
                                    <button wire:click="discardImport({{ $import->id }})" wire:confirm="¿Descartar esta vista previa y borrar el texto temporal?" class="rounded-md border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50">Descartar</button>
                                    <button wire:click="confirmImport({{ $import->id }})" @disabled($import->recognized_count < 1 || $hasUnreviewedDuplicates) class="rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Confirmar importación</button>
                                </div>
                            </div>
                        </div>
                    @elseif(in_array($import->status, ['queued', 'processing'], true))
                        <div class="space-y-3 px-5 py-4">
                            <div class="flex justify-between text-sm text-slate-600"><span>Avance: {{ number_format($import->processed_count) }} de {{ number_format($import->total_entries) }}</span><span>{{ $progress }}%</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ $progress }}%"></div></div>
                            <p class="text-xs text-slate-500">Guardadas: {{ number_format($import->imported_count) }} · Duplicadas omitidas: {{ number_format($import->duplicate_count) }} · Sin interpretar: {{ number_format($import->unrecognized_count) }}</p>
                            <p class="text-xs text-slate-500">Reemplazadas: {{ number_format($import->replaced_count) }}</p>
                        </div>
                    @elseif($import->status === 'completed')
                        <div class="px-5 py-4 text-sm text-slate-700">
                            Importadas: <strong>{{ number_format($import->imported_count) }}</strong> · Reemplazadas: <strong>{{ number_format($import->replaced_count) }}</strong> · Duplicadas omitidas: <strong>{{ number_format($import->duplicate_count) }}</strong> · Entradas sin interpretar: <strong>{{ number_format($import->unrecognized_count) }}</strong>.
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
