<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Cuentas de Correo
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex-1 w-full sm:w-1/3 mr-4">
                            <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar por correo o alias..." class="w-full" />
                        </div>
                        <div class="flex gap-3">
                            @if(auth()->user()->isAdmin())
                                <button wire:click="openMassAssignModal" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-700 focus:bg-slate-700 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Asignación Masiva
                                </button>
                            @endif
                            <x-primary-button wire:click="create">
                                Nueva Cuenta
                            </x-primary-button>
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="mb-4 font-medium text-sm text-green-600 bg-green-50 p-4 rounded-lg">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200 shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alias</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correo</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfiles</th>
                                    @if(auth()->user()->isAdmin())
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gestor Asignado</th>
                                    @endif
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contraseña</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatus</th>
                                    <th scope="col" class="relative px-4 py-2"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($accounts as $account)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm font-medium text-gray-900">{{ $account->alias ?: '-' }}</td>
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">{{ $account->email }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <button wire:click="viewProfiles({{ $account->id }})" class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full hover:bg-indigo-700 transition-colors">
                                                {{ $account->profiles->count() }} Perfiles
                                            </button>
                                        </td>
                                        @if(auth()->user()->isAdmin())
                                            <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">{{ $account->creator->name ?? 'N/A' }}</td>
                                        @endif
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">
                                            <button wire:click="revealPassword({{ $account->id }})" class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1 text-xs font-semibold bg-indigo-50 px-2 py-1 rounded transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                Ver Contraseña
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">
                                            @if($account->status === 'active')
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-green-50 text-green-700 border-green-200">Activo</span>
                                            @else
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-red-50 text-red-700 border-red-200">Suspendido</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button wire:click="edit({{ $account->id }})" title="Editar" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                                </button>
                                                
                                                <button wire:click="toggleStatus({{ $account->id }})" title="{{ $account->status === 'active' ? 'Suspender' : 'Activar' }}" class="p-2 {{ $account->status === 'active' ? 'text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100' : 'text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100' }} rounded-lg transition-colors">
                                                    @if($account->status === 'active')
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                    @endif
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-center text-sm text-gray-500">
                                            No hay cuentas de correo registradas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $accounts->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    @if($showModal)
        <div x-data x-init="setTimeout(() => $refs.firstInput.focus(), 50)" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ $emailId ? 'Editar Cuenta' : 'Nueva Cuenta' }}
                            </h3>
                            <div>
                                <x-input-label for="alias" value="Alias (Opcional)" />
                                <x-text-input x-ref="firstInput" wire:model="alias" id="alias" class="block mt-1 w-full" type="text" />
                                <x-input-error :messages="$errors->get('alias')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="email" value="Correo Electrónico" />
                                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="password" value="Contraseña" />
                                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="text" required />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="status" value="Estatus" />
                                <select wire:model="status" id="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                    <option value="active">Activo</option>
                                    <option value="suspended">Suspendido</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                            @if(auth()->user()->isAdmin())
                            <div>
                                <x-input-label for="created_by" value="Gestor Asignado" />
                                <select wire:model="created_by" id="created_by" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                    <option value="">Seleccione un usuario...</option>
                                    @foreach($cuentasUsers as $cUser)
                                        <option value="{{ $cUser->id }}">{{ $cUser->name }} ({{ $cUser->email }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('created_by')" class="mt-2" />
                            </div>
                            @endif
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <x-primary-button type="submit" class="w-full sm:w-auto sm:ml-3">Guardar</x-primary-button>
                            <button type="button" wire:click="$set('showModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Password Modal -->
    @if($showPasswordModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showPasswordModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 mb-4">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">Contraseña Desencriptada</h3>
                        <div class="mt-2 p-3 bg-gray-100 rounded text-center text-xl font-mono tracking-wider text-gray-800 break-words select-all">
                            {{ $revealedPassword }}
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-center">
                        <x-primary-button type="button" wire:click="$set('showPasswordModal', false)" class="w-full justify-center">Cerrar</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Profiles List Modal -->
    @if($showProfilesModal && $selectedAccountForProfiles)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showProfilesModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center border-b pb-3 mb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                Perfiles de: <span class="text-indigo-600">{{ $selectedAccountForProfiles->email }}</span>
                            </h3>
                            <button wire:click="$set('showProfilesModal', false)" class="text-gray-400 hover:text-gray-500">
                                <span class="sr-only">Cerrar</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            @if($selectedAccountForProfiles->profiles->count() > 0)
                                <ul class="divide-y divide-gray-200">
                                    @foreach($selectedAccountForProfiles->profiles as $p)
                                        <li class="py-4 flex justify-between items-center">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ $p->name }}</p>
                                                <p class="text-sm text-gray-500">{{ $p->social_network }}</p>
                                            </div>
                                            <div>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $p->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $p->status === 'active' ? 'Activo' : 'Suspendido' }}
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-gray-500 text-center py-4">No hay perfiles asociados a esta cuenta.</p>
                            @endif
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end">
                        <x-primary-button type="button" wire:click="$set('showProfilesModal', false)">Cerrar</x-primary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Mass Assignment Modal -->
    @if($showMassAssignModal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showMassAssignModal', false)"></div>
                <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl w-full">
                    <form wire:submit.prevent="executeMassAssignment">
                        <div class="bg-indigo-600 px-6 py-4 flex justify-between items-center">
                            <h3 class="text-xl leading-6 font-bold text-white flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                                Asignación Masiva de Cuentas
                            </h3>
                            <button type="button" wire:click="$set('showMassAssignModal', false)" class="text-indigo-100 hover:text-white focus:outline-none">
                                <span class="sr-only">Cerrar</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <p class="text-sm text-gray-500 mb-6">Transfiere de forma masiva todas o algunas de las cuentas (y sus perfiles) de un Gestor a otro.</p>

                            @php
                                $gestores = \App\Models\User::whereIn('role', ['admin', 'cuentas'])->where('is_active', true)->get();
                            @endphp

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <x-input-label for="sourceGestorId" value="Gestor Origen" />
                                    <select wire:model.live="sourceGestorId" id="sourceGestorId" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                        <option value="">Seleccione origen...</option>
                                        @foreach($gestores as $gestor)
                                            <option value="{{ $gestor->id }}">{{ $gestor->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('sourceGestorId')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="destinationGestorId" value="Gestor Destino" />
                                    <select wire:model="destinationGestorId" id="destinationGestorId" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                        <option value="">Seleccione destino...</option>
                                        @foreach($gestores as $gestor)
                                            @if($gestor->id != $sourceGestorId)
                                                <option value="{{ $gestor->id }}">{{ $gestor->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('destinationGestorId')" class="mt-2" />
                                </div>
                            </div>

                            @if($sourceGestorId)
                                <div class="mt-4 border-t border-gray-200 pt-4">
                                    <h4 class="text-md font-semibold text-gray-800 mb-3 flex items-center justify-between">
                                        Cuentas a Transferir
                                        <span class="bg-indigo-100 text-indigo-800 text-xs py-1 px-2 rounded-full">{{ count($sourceAccounts) }} cuentas encontradas</span>
                                    </h4>
                                    
                                    @if(count($sourceAccounts) > 0)
                                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md bg-gray-50 p-2">
                                            <div class="space-y-2">
                                                @foreach($sourceAccounts as $account)
                                                    <label class="flex items-center p-2 bg-white rounded border border-gray-100 shadow-sm cursor-pointer hover:bg-indigo-50 transition-colors">
                                                        <input type="checkbox" wire:model="selectedAccounts" value="{{ $account->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-3 w-4 h-4">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-gray-900 text-sm">{{ $account->email }}</div>
                                                            @if($account->alias)
                                                                <div class="text-xs text-gray-500">{{ $account->alias }}</div>
                                                            @endif
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 italic p-4 bg-gray-50 rounded-md text-center">Este gestor no tiene cuentas asignadas.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('selectedAccounts')" class="mt-2" />
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                            <button type="submit" onclick="return confirm('¿Estás seguro de reasignar las cuentas seleccionadas? Los perfiles asociados también se reasignarán al nuevo gestor.')" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50" @if(count($sourceAccounts) === 0 || !$destinationGestorId) disabled @endif>
                                <svg wire:loading wire:target="executeMassAssignment" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Ejecutar Transferencia
                            </button>
                            <button type="button" wire:click="$set('showMassAssignModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
