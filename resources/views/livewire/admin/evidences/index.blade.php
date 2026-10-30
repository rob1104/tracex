<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Reporte General de Evidencias
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-6 rounded-lg bg-white p-4 shadow-sm sm:rounded-lg">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="filterCollaborator" value="Colaboradores" />
                    <select id="filterCollaborator" wire:model.live="filterCollaborator" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los colaboradores</option>
                        @foreach($collaborators as $collaborator)
                            <option value="{{ $collaborator->id }}">{{ $collaborator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filterType" value="Tipo de Evidencia" />
                    <select id="filterType" wire:model.live="filterType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filterDateFrom" value="Desde" />
                    <x-text-input id="filterDateFrom" wire:model.live="filterDateFrom" type="date" class="mt-1 block w-full" />
                </div>
                <div>
                    <x-input-label for="filterDateTo" value="Hasta" />
                    <x-text-input id="filterDateTo" wire:model.live="filterDateTo" type="date" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-4 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex items-center">
                    <input type="checkbox" wire:model.live="filterSuspect" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="ml-2 text-sm font-semibold text-red-600">Solo sospechosas</span>
                </label>
                <div class="flex flex-wrap justify-end gap-3">
                    <button wire:click="exportCsv" wire:loading.attr="disabled" class="inline-flex items-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50">
                    Exportar a Excel
                    </button>
                    <button wire:click="exportPdf" wire:loading.attr="disabled" class="inline-flex items-center rounded-md border border-transparent bg-rose-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-rose-700 disabled:opacity-50">
                    Exportar a PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Colaborador</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Detalles</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Capturas</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-indigo-700">Fecha</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-indigo-700">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($evidences as $evidence)
                        @php
                            $isSuspectRow = $evidence->images->contains('is_suspect', true);
                            $net = strtolower((string) $evidence->social_network);
                            if (str_contains($net, 'facebook')) $netColor = 'bg-blue-600 text-white border-blue-700';
                            elseif (str_contains($net, 'instagram')) $netColor = 'bg-pink-100 text-pink-700 border-pink-200';
                            elseif (str_contains($net, 'tiktok')) $netColor = 'bg-slate-800 text-white border-slate-700';
                            elseif (str_contains($net, 'twitter') || $net === 'x') $netColor = 'bg-sky-100 text-sky-700 border-sky-200';
                            elseif (str_contains($net, 'youtube')) $netColor = 'bg-red-100 text-red-700 border-red-200';
                            elseif (str_contains($net, 'linkedin')) $netColor = 'bg-blue-800 text-white border-blue-900';
                            else $netColor = 'bg-gray-100 text-gray-700 border-gray-200';
                            $typeColors = ['bg-purple-100 text-purple-700 border-purple-200', 'bg-emerald-100 text-emerald-700 border-emerald-200', 'bg-amber-100 text-amber-700 border-amber-200', 'bg-orange-100 text-orange-700 border-orange-200', 'bg-cyan-100 text-cyan-700 border-cyan-200', 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200'];
                            $typeColor = $typeColors[($evidence->evidence_type_id ?? 0) % 6];
                            $activityAt = $evidence->external_published_at ?? $evidence->created_at;
                        @endphp
                        <tr wire:key="evidence-row-{{ $evidence->id }}" class="{{ $isSuspectRow ? 'border-l-4 border-red-500 bg-red-50' : 'border-l-4 border-transparent hover:bg-slate-50' }}">
                            <td class="whitespace-normal px-6 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $evidence->user?->name ?? 'Sin colaborador' }}</div>
                                <div class="text-xs text-slate-500">{{ $evidence->user?->email ?? '' }}</div>
                            </td>
                            <td class="max-w-xl px-6 py-4">
                                @if($evidence->profile)
                                    <div class="mb-1 text-sm font-medium text-slate-800">{{ $evidence->profile->name }}</div>
                                @endif
                                <div class="flex flex-wrap items-center gap-1">
                                    <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $netColor }}">{{ $evidence->social_network }}</span>
                                    <span class="rounded-full border px-2 py-1 text-xs font-semibold {{ $typeColor }}">{{ optional($evidence->evidenceType)->name }}</span>
                                    @if($isSuspectRow)
                                        <button wire:click="viewSuspectDetails({{ $evidence->id }})" class="rounded-full border border-red-200 bg-red-100 px-2 py-1 text-xs font-bold text-red-700 hover:bg-red-200">Sospechosa</button>
                                    @endif
                                </div>
                                <div class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $evidence->comment }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">
                                <div class="flex -space-x-2 overflow-hidden">
                                    @foreach($evidence->images as $image)
                                        <button wire:click="showImage('{{ url('storage/'.$image->screenshot_path) }}')" class="relative z-0 transition-transform hover:z-10 hover:scale-110">
                                            <img class="h-10 w-10 rounded-full object-cover ring-2 {{ $image->is_suspect ? 'ring-red-500' : 'ring-white' }}" src="{{ url('storage/'.$image->screenshot_path) }}" alt="Captura de evidencia" />
                                            @if($image->is_suspect)<span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white" title="Posible duplicado">!</span>@endif
                                        </button>
                                    @endforeach
                                    @if($evidence->images->isEmpty())<span class="text-xs text-slate-400">Sin capturas</span>@endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-700">
                                <div>{{ $activityAt?->translatedFormat('d M Y') ?? '-' }}</div>
                                <div class="text-xs font-normal text-slate-400">{{ $activityAt?->format('h:i A') ?? '' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <button wire:click="delete({{ $evidence->id }})" wire:confirm="&iquest;Est&aacute;s seguro de que deseas eliminar esta evidencia permanentemente?" title="Eliminar evidencia" class="inline-flex rounded-lg bg-red-50 p-2 text-red-600 hover:bg-red-100 hover:text-red-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No se encontraron evidencias para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($evidences->hasPages())
            <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">{{ $evidences->links() }}</div>
        @endif
        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

    </div>
    <!-- Image Preview Modal -->
    @if($previewImage)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="closeImage"></div>

                <!-- Modal panel -->
                <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 max-w-4xl w-full">
                    <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                        <button type="button" wire:click="closeImage" class="bg-white rounded-full p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-md">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="bg-gray-100 flex justify-center items-center min-h-[50vh] p-4">
                        <img src="{{ $previewImage }}" alt="Vista previa de evidencia" class="max-h-[85vh] object-contain rounded shadow-lg" />
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Suspect Details Modal -->
    @if($suspectModalData)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity" wire:click="closeSuspectModal"></div>
                <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 max-w-6xl w-full">
                    <div class="bg-red-600 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-xl leading-6 font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Análisis de Evidencia Sospechosa (Posible Duplicado)
                        </h3>
                        <button type="button" wire:click="closeSuspectModal" class="text-red-100 hover:text-white focus:outline-none">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <div class="bg-gray-50 px-6 py-6">
                        <p class="text-gray-600 mb-6 text-sm text-center">
                            El sistema ha detectado que la captura de pantalla de esta evidencia es una copia idéntica (mismo archivo o recorte exacto) de una evidencia registrada previamente en la plataforma.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Current Evidence (The duplicate) -->
                            <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden flex flex-col relative">
                                <div class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                                    Copia (Sospechosa)
                                </div>
                                <div class="p-5 border-b border-gray-100">
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">Evidencia Seleccionada</h4>
                                    <div class="text-sm text-gray-500 mb-4">
                                        Registrada el: <span class="font-medium text-gray-900">{{ $suspectModalData['current']->created_at->format('d M, Y h:i A') }}</span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Colaborador:</span>
                                            <span class="font-medium text-gray-900">{{ $suspectModalData['current']->user->name }}</span>
                                        </div>
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Perfil Asociado:</span>
                                            <span class="font-medium text-gray-900">{{ optional($suspectModalData['current']->profile)->name ?? 'Manual' }} ({{ $suspectModalData['current']->social_network }})</span>
                                        </div>
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Tipo:</span>
                                            <span class="font-medium text-gray-900">{{ optional($suspectModalData['current']->evidenceType)->name }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 block mb-1">Comentario:</span>
                                            <p class="text-gray-800 bg-gray-50 p-2 rounded italic">{{ $suspectModalData['current']->comment ?: 'Sin comentarios.' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-5 bg-gray-50 flex-grow flex items-center justify-center">
                                    <img src="{{ url('storage/'.$suspectModalData['currentImage']->screenshot_path) }}" class="max-h-64 object-contain rounded border border-gray-300 shadow-sm" alt="Imagen Sospechosa" />
                                </div>
                            </div>

                            <!-- Original Evidence -->
                            <div class="bg-white rounded-xl border border-green-200 shadow-sm overflow-hidden flex flex-col relative">
                                <div class="absolute top-0 right-0 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                                    Original
                                </div>
                                <div class="p-5 border-b border-gray-100">
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">Evidencia Original</h4>
                                    <div class="text-sm text-gray-500 mb-4">
                                        Registrada el: <span class="font-medium text-gray-900">{{ $suspectModalData['original']->created_at->format('d M, Y h:i A') }}</span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Colaborador:</span>
                                            <span class="font-medium text-gray-900">{{ $suspectModalData['original']->user->name }}</span>
                                        </div>
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Perfil Asociado:</span>
                                            <span class="font-medium text-gray-900">{{ optional($suspectModalData['original']->profile)->name ?? 'Manual' }} ({{ $suspectModalData['original']->social_network }})</span>
                                        </div>
                                        <div class="flex justify-between border-b pb-1">
                                            <span class="text-gray-500">Tipo:</span>
                                            <span class="font-medium text-gray-900">{{ optional($suspectModalData['original']->evidenceType)->name }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500 block mb-1">Comentario:</span>
                                            <p class="text-gray-800 bg-gray-50 p-2 rounded italic">{{ $suspectModalData['original']->comment ?: 'Sin comentarios.' }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-5 bg-gray-50 flex-grow flex items-center justify-center">
                                    <img src="{{ url('storage/'.$suspectModalData['originalImage']->screenshot_path) }}" class="max-h-64 object-contain rounded border border-gray-300 shadow-sm" alt="Imagen Original" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-100 px-6 py-4 flex justify-end gap-3">
                        <button type="button" wire:click="markAsNotSuspect({{ $suspectModalData['currentImage']->id }})" class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm transition-colors flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Marcar como Válida (Quitar sospecha)
                        </button>
                        <button type="button" wire:click="closeSuspectModal" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm transition-colors">
                            Cerrar Panel de Análisis
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
