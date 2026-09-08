<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Historial de Evidencias
        </h2>
        <a href="{{ route('evidences.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" wire:navigate>
            Registrar Nueva
        </a>
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Filters -->
        <div class="bg-white p-4 shadow-sm sm:rounded-lg mb-6 flex flex-col sm:flex-row gap-4 items-end">
            <div class="w-full sm:w-1/3">
                <x-input-label value="Tipo de Evidencia" />
                <select wire:model.live="filterType" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                    <option value="">Todos los tipos</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-1/3">
                <x-input-label value="Desde" />
                <x-text-input wire:model.live="filterDateFrom" type="date" class="block w-full mt-1" />
            </div>

            <div class="w-full sm:w-1/3">
                <x-input-label value="Hasta" />
                <x-text-input wire:model.live="filterDateTo" type="date" class="block w-full mt-1" />
            </div>

            @if($filterType || $filterDateFrom || $filterDateTo)
                <div class="w-full sm:w-auto flex items-center mb-1">
                    <button wire:click="clearFilters" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 focus:bg-gray-200 active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Limpiar
                    </button>
                </div>
            @endif
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 overflow-x-auto">
                @if($evidences->count() > 0)
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-indigo-50 border-b border-indigo-100">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Fecha</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Red Social</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Tipo</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Capturas</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Comentario</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach($evidences as $evidence)
                                @php
                                    $net = strtolower($evidence->social_network);
                                    if(str_contains($net, 'facebook')) $netColor = 'bg-blue-100 text-blue-700 border-blue-200';
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
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">
                                        <div class="flex flex-col">
                                            <span>{{ $evidence->created_at->translatedFormat('d M Y') }}</span>
                                            <span class="text-xs text-gray-400">{{ $evidence->created_at->format('h:i A') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full border {{ $netColor }}">
                                            {{ $evidence->social_network }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full border {{ $typeColor }}">
                                            {{ optional($evidence->evidenceType)->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="flex -space-x-2 overflow-hidden">
                                            @foreach($evidence->images as $image)
                                                <button wire:click="showImage('{{ url('storage/'.$image->screenshot_path) }}')" class="relative group focus:outline-none z-0 hover:z-10 transition-transform hover:scale-110">
                                                    <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white object-cover cursor-pointer" src="{{ url('storage/'.$image->screenshot_path) }}" alt=""/>
                                                </button>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ Str::limit($evidence->comment, 50) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $evidences->links() }}
                    </div>
                @else
                    <div class="text-center py-10">
                        @if($filterType || $filterDateFrom || $filterDateTo)
                            <p class="text-slate-500 mb-4 text-lg">No hay evidencias que coincidan con los filtros aplicados.</p>
                            <button wire:click="clearFilters" class="text-indigo-600 hover:text-indigo-900 font-medium">Limpiar filtros</button>
                        @else
                            <p class="text-slate-500 mb-4 text-lg">No has registrado evidencias todavía.</p>
                            <a href="{{ route('evidences.create') }}" class="text-indigo-600 hover:text-indigo-900 font-medium" wire:navigate>Registrar tu primera evidencia</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    @if($previewImage)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity" aria-hidden="true" wire:click="closeImage"></div>
                <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 max-w-4xl w-full">
                    <div class="absolute top-0 right-0 pt-4 pr-4 z-10">
                        <button type="button" wire:click="closeImage" class="bg-white rounded-full p-2 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-md">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="bg-gray-100 flex justify-center items-center min-h-[50vh] p-4">
                        <img src="{{ $previewImage }}" alt="Vista previa" class="max-h-[85vh] object-contain rounded shadow-lg" />
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
