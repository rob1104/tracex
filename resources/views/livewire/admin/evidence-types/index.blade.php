<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Catálogo de Tipos de Evidencia
        </h2>
    </div>
</x-slot>

<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="mb-4 text-right">
                <x-primary-button wire:click="create">Nuevo Tipo</x-primary-button>
            </div>
            
            @if (session('status'))
                <div class="mb-4 font-medium text-sm text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            <section id="facebook-exports" class="mb-8 overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Exportaciones de Facebook sin asignar</h2>
                        <p class="mt-1 text-sm text-slate-500">Aquí quedan las exportaciones de Drive que no encontraron un perfil o colaborador automáticamente.</p>
                    </div>
                    <span class="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800">{{ $pendingImports->total() }} pendientes</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Carpeta de Drive</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Facebook detectado</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Actividad extraída</th>
                                <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Recibida</th>
                                <th class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($pendingImports as $importLog)
                                @php
                                    $facebookUser = $importLog->facebookUsers->first();
                                    $facebookMetadata = is_array($facebookUser?->metadata) ? $facebookUser->metadata : [];
                                @endphp
                                <tr wire:key="catalog-facebook-import-{{ $importLog->id }}" class="hover:bg-amber-50/30">
                                    <td class="px-5 py-4 text-sm text-slate-800">
                                        <div class="font-semibold">{{ $importLog->file_name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">ID: {{ $importLog->drive_file_id }}</div>
                                        @if($importLog->error_message)<p class="mt-1 max-w-md text-xs text-amber-700">{{ $importLog->error_message }}</p>@endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700">
                                        <div>{{ $importLog->profile?->name ?? ($facebookMetadata['name'] ?? 'Sin coincidencia') }}</div>
                                        @if(! empty($facebookMetadata['profile_uri']))
                                            <div class="mt-1 max-w-xs break-all text-xs text-slate-500">{{ $facebookMetadata['profile_uri'] }}</div>
                                        @endif
                                        @if(! $importLog->profile_id && ! empty($facebookMetadata['export_identifier']))
                                            <div class="mt-1 text-xs text-slate-500">ID detectado: {{ $facebookMetadata['export_identifier'] }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        {{ $importLog->comments_count }} comentarios<br>
                                        {{ $importLog->reactions_count }} reacciones<br>
                                        {{ data_get($importLog->metadata, 'facebook_shares_count', count((array) data_get($importLog->metadata, 'facebook_shares', []))) }} compartidos
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $importLog->downloaded_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <button wire:click="openImportAssignmentModal({{ $importLog->id }})" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-indigo-700">Asignar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">No hay exportaciones pendientes de asignación.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4 sm:px-6">{{ $pendingImports->links() }}</div>
            </section>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-indigo-50 border-b border-indigo-100">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Nombre</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Descripción</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Estado</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-indigo-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($types as $type)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $type->name }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $type->description ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($type->is_active)
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-green-50 text-green-700 border-green-200">Activo</span>
                                    @else
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-red-50 text-red-700 border-red-200">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button wire:click="edit({{ $type->id }})" title="Editar" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                        
                                        <button wire:click="toggleActive({{ $type->id }})" title="{{ $type->is_active ? 'Desactivar Tipo' : 'Activar Tipo' }}" class="p-2 {{ $type->is_active ? 'text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100' : 'text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100' }} rounded-lg transition-colors">
                                            @if($type->is_active)
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @endif
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div x-data x-init="setTimeout(() => $refs.firstInput.focus(), 50)" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ $typeId ? 'Editar Tipo' : 'Nuevo Tipo' }}
                            </h3>
                            
                            <div>
                                <x-input-label for="name" value="Nombre" />
                                <x-text-input x-ref="firstInput" wire:model="name" id="name" class="block mt-1 w-full" type="text" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="description" value="Descripción" />
                                <textarea wire:model="description" id="description" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" rows="3"></textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <x-primary-button type="submit" class="w-full sm:w-auto sm:ml-3">Guardar</x-primary-button>
                            <button type="button" wire:click="$set('showModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($showImportAssignmentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="facebook-import-assignment-title">
            <div class="flex min-h-screen items-center justify-center px-4 py-10">
                <button type="button" class="fixed inset-0 bg-slate-900/50" wire:click="$set('showImportAssignmentModal', false)" aria-label="Cerrar"></button>
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                    <h3 id="facebook-import-assignment-title" class="text-lg font-bold text-slate-900">Asignar exportación de Facebook</h3>
                    <p class="mt-1 text-sm text-slate-500">Elige el perfil y el colaborador. TraceX agregará la actividad importada a su historial.</p>
                    <form wire:submit="assignPendingImport" class="mt-5 space-y-4">
                        <div>
                            <x-input-label for="claimProfileId" value="Perfil de Facebook" />
                            <select id="claimProfileId" wire:model="claimProfileId" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Selecciona un perfil</option>
                                @foreach($facebookProfiles as $facebookProfile)
                                    <option value="{{ $facebookProfile->id }}">{{ $facebookProfile->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('claimProfileId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="claimUserId" value="Colaborador" />
                            <select id="claimUserId" wire:model="claimUserId" class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Selecciona un colaborador</option>
                                @foreach($collaborators as $collaborator)
                                    <option value="{{ $collaborator->id }}">{{ $collaborator->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('claimUserId')" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="$set('showImportAssignmentModal', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="assignPendingImport" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="assignPendingImport">Asignar exportación</span>
                                <span wire:loading wire:target="assignPendingImport">Procesando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
