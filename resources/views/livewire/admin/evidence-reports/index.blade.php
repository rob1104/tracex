<x-slot name="header">
    <div class="flex flex-col gap-1">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reporte de evidencias por colaborador</h2>
        <p class="text-sm text-gray-500">Consulta el volumen de evidencias registradas por el equipo.</p>
    </div>
</x-slot>

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-sm font-medium text-slate-500">Evidencias en el resultado</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalEvidence) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <p class="text-sm font-medium text-slate-500">Colaboradores</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalCollaborators) }}</p>
            </div>
        </div>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-2 xl:col-span-2">
                    <x-input-label for="evidence-report-search" value="Buscar colaborador" />
                    <x-text-input id="evidence-report-search" wire:model.live.debounce.400ms="search" type="search" class="block w-full mt-1" placeholder="Nombre o correo" />
                </div>

                <div class="md:col-span-2 xl:col-span-2" x-data="{ open: false }" @click.outside="open = false">
                    <x-input-label for="evidence-report-collaborators" value="Colaboradores" />
                    <div class="relative mt-1">
                        <button id="evidence-report-collaborators" type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" class="inline-flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-700 shadow-sm hover:border-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            <span class="truncate">{{ count($collaboratorIds) ? count($collaboratorIds).' colaboradores seleccionados' : 'Todos los colaboradores' }}</span>
                            <svg class="ml-2 h-4 w-4 shrink-0 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-cloak x-show="open" x-transition class="absolute z-40 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                            <div role="listbox" aria-label="Filtrar por colaboradores" aria-multiselectable="true" class="max-h-56 overflow-y-auto p-2">
                                @foreach($collaboratorOptions as $option)
                                    <label wire:key="evidence-report-option-{{ $option->id }}" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" wire:model.live="collaboratorIds" value="{{ $option->id }}" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="truncate">{{ $option->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-100 px-3 py-2">
                                <span class="text-xs text-slate-500">Puedes elegir varios</span>
                                <button type="button" wire:click="clearCollaboratorSelection" @click="open = false" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">Mostrar todos</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-2">
                    <x-input-label for="evidence-report-from" value="Desde" />
                    <x-text-input id="evidence-report-from" wire:model.live="dateFrom" type="date" class="block w-full mt-1" />
                </div>

                <div class="xl:col-span-2">
                    <x-input-label for="evidence-report-to" value="Hasta" />
                    <x-text-input id="evidence-report-to" wire:model.live="dateTo" type="date" class="block w-full mt-1" />
                </div>

                <div class="md:col-span-2 xl:col-span-4 flex flex-wrap items-center justify-end gap-2">
                    <button type="button" wire:click="clearFilters" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Limpiar
                    </button>
                    <button type="button" wire:click="exportCsv" class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-700 text-sm font-semibold text-white hover:bg-emerald-800">
                        Exportar CSV
                    </button>
                    <button type="button" wire:click="exportPdf" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-700 text-sm font-semibold text-white hover:bg-indigo-800">
                        Exportar PDF
                    </button>
                </div>
            </div>

            @error('dateFrom')
                <p class="mt-2 text-sm text-red-600">Selecciona una fecha inicial válida.</p>
            @enderror
            @error('dateTo')
                <p class="mt-2 text-sm text-red-600">La fecha final debe ser válida y no anterior a la inicial.</p>
            @enderror
            @error('collaboratorIds.*')
                <p class="mt-2 text-sm text-red-600">La selección de colaboradores no es válida.</p>
            @enderror
        </section>

        <section class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 hover:text-slate-900">Colaborador @if($sortColumn === 'name')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Correo</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <button type="button" wire:click="sortBy('evidence_count')" class="inline-flex items-center gap-1 hover:text-slate-900">Total de evidencias @if($sortColumn === 'evidence_count')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <button type="button" wire:click="sortBy('first_evidence_at')" class="inline-flex items-center gap-1 hover:text-slate-900">Primera captura @if($sortColumn === 'first_evidence_at')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <button type="button" wire:click="sortBy('last_evidence_at')" class="inline-flex items-center gap-1 hover:text-slate-900">Última captura @if($sortColumn === 'last_evidence_at')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($collaborators as $collaborator)
                            <tr wire:key="evidence-report-collaborator-{{ $collaborator->id }}" class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $collaborator->name }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $collaborator->email }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-slate-900">{{ number_format($collaborator->evidence_count) }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $collaborator->first_evidence_at ? \Illuminate\Support\Carbon::parse($collaborator->first_evidence_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $collaborator->last_evidence_at ? \Illuminate\Support\Carbon::parse($collaborator->last_evidence_at)->format('d/m/Y H:i') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No hay colaboradores que coincidan con los filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($collaborators->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">{{ $collaborators->links() }}</div>
            @endif
        </section>
    </div>
</div>
