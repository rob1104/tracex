<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Reporte General de Evidencias
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white p-4 shadow-sm sm:rounded-lg mb-6">
            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-4 items-end mb-4">
                <div class="w-full sm:w-1/4">
                    <x-input-label value="Buscar Usuario" />
                    <x-text-input wire:model.live.debounce.500ms="searchUser" type="text" class="block w-full mt-1" placeholder="Nombre o correo..." />
                </div>

                <div class="w-full sm:w-1/4">
                    <x-input-label value="Tipo de Evidencia" />
                    <select wire:model.live="filterType" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                        <option value="">Todos</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-1/5">
                    <x-input-label value="Desde" />
                    <x-text-input wire:model.live="filterDateFrom" type="date" class="block w-full mt-1" />
                </div>

                <div class="w-full sm:w-1/5">
                    <x-input-label value="Hasta" />
                    <x-text-input wire:model.live="filterDateTo" type="date" class="block w-full mt-1" />
                </div>

                <div class="w-full sm:w-auto flex items-center mb-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" wire:model.live="filterSuspect" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-red-600 font-semibold">Solo Sospechosas</span>
                    </label>
                </div>
            </div>

            <!-- Export Actions -->
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button wire:click="exportCsv" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Exportar a Excel
                </button>
                <button wire:click="exportPdf" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-rose-700 focus:bg-rose-700 active:bg-rose-900 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    Exportar a PDF
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-indigo-50 border-b border-indigo-100">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Creada Por</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Detalles</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Capturas</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Fecha</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-indigo-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($evidences as $evidence)
                            @php
                                $isSuspectRow = $evidence->images->contains('is_suspect', true);

                                $net = strtolower($evidence->social_network);
                                if(str_contains($net, 'facebook')) $netColor = 'bg-blue-600 text-white border-blue-700';
                                elseif(str_contains($net, 'instagram')) $netColor = 'bg-pink-100 text-pink-700 border-pink-200';
                                elseif(str_contains($net, 'tiktok')) $netColor = 'bg-slate-800 text-white border-slate-700';
                                elseif(str_contains($net, 'x ') || str_contains($net, 'twitter') || $net === 'x') $netColor = 'bg-sky-100 text-sky-700 border-sky-200';
                                elseif(str_contains($net, 'youtube')) $netColor = 'bg-red-100 text-red-700 border-red-200';
                                elseif(str_contains($net, 'linkedin')) $netColor = 'bg-blue-800 text-white border-blue-900';
                                else $netColor = 'bg-gray-100 text-gray-700 border-gray-200';

                                $typeId = $evidence->evidence_type_id ?? 0;
                                $typeColors = [
                                    'bg-purple-100 text-purple-700 border-purple-200',
                                    'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'bg-amber-100 text-amber-700 border-amber-200',
                                    'bg-orange-100 text-orange-700 border-orange-200',
                                    'bg-cyan-100 text-cyan-700 border-cyan-200',
                                    'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-200'
                                ];
                                $typeColor = $typeColors[$typeId % 6];
                            @endphp
                            <tr class="transition-colors {{ $isSuspectRow ? 'bg-red-50 border-l-4 border-red-500 hover:bg-red-100' : 'hover:bg-slate-50 border-l-4 border-transparent' }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $evidence->user->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $evidence->user->email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="mb-1 flex flex-col items-start gap-1">
                                        @if($evidence->profile)
                                            <span class="font-medium text-gray-700 text-sm">{{ $evidence->profile->name }}</span>
                                        @endif
                                        <div class="flex flex-wrap gap-1">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full border {{ $netColor }}">
                                                {{ $evidence->social_network }}
                                            </span>
                                            <span class="ml-1 px-2 py-1 text-xs font-semibold rounded-full border {{ $typeColor }}">
                                                {{ optional($evidence->evidenceType)->name }}
                                            </span>
                                            @if($isSuspectRow)
                                                <span class="ml-1 px-2 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-200">
                                                    Sospechosa
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-2">{{ Str::limit($evidence->comment, 50) }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div class="flex -space-x-2 overflow-hidden">
                                        @foreach($evidence->images as $image)
                                            <button wire:click="showImage('{{ url('storage/'.$image->screenshot_path) }}')" class="relative group focus:outline-none z-0 hover:z-10 transition-transform hover:scale-110">
                                                <img class="inline-block h-10 w-10 rounded-full ring-2 {{ $image->is_suspect ? 'ring-red-500' : 'ring-white' }} object-cover cursor-pointer" src="{{ url('storage/'.$image->screenshot_path) }}" alt=""/>
                                                @if($image->is_suspect)
                                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full h-4 w-4 flex items-center justify-center text-[10px] shadow-sm" title="Posible duplicado">!</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">
                                    <div class="flex flex-col">
                                        <span>{{ $evidence->created_at->translatedFormat('d M Y') }}</span>
                                        <span class="text-xs text-gray-400">{{ $evidence->created_at->format('h:i A') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="delete({{ $evidence->id }})" wire:confirm="¿Estás seguro de que deseas eliminar esta evidencia permanentemente?" title="Eliminar" class="p-2 text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 rounded-lg transition-colors inline-flex">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No se encontraron evidencias.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $evidences->links() }}
                </div>
            </div>
        </div>
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
</div>
