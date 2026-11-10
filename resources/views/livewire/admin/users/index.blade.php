<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Gestión de Usuarios
            </h2>
        </div>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-white p-4 shadow-sm sm:rounded-lg mb-6 flex flex-col sm:flex-row justify-between gap-4 items-end">
                <div class="w-full sm:w-1/3">
                    <x-input-label value="Buscar Usuario" />
                    <x-text-input wire:model.live.debounce.500ms="search" type="text" class="block w-full mt-1" placeholder="Nombre o correo..." />
                </div>
                <div>
                    <button wire:click="exportCsv" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">Excel</button> <button wire:click="exportPdf" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">PDF</button> <x-primary-button wire:click="create">Nuevo Usuario</x-primary-button>
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
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Nombre</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Correo</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Rol</th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-indigo-700 uppercase tracking-wider">Estado</th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-indigo-700 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($users as $user)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $user->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($user->role === 'admin')
                                        <span class="px-2 py-1 text-xs font-bold rounded bg-purple-100 text-purple-700">Administrador</span>
                                    @elseif($user->role === 'supervisor')
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="px-2 py-1 text-xs font-bold rounded bg-indigo-100 text-indigo-700">Supervisor</span>
                                            <span class="text-xs {{ ($user->monitored_collaborators_count ?? 0) > 0 ? 'text-indigo-600 font-medium' : 'text-amber-600' }}">
                                                {{ ($user->monitored_collaborators_count ?? 0).' colaboradores asignados' }}
                                            </span>
                                        </div>
                                    @elseif($user->role === 'cuentas')
                                        <span class="px-2 py-1 text-xs font-bold rounded bg-blue-100 text-blue-700">Cuentas</span>
                                    @else
                                        <div class="flex flex-col items-start gap-1">
                                            <span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-600">Usuario Regular</span>
                                            <span class="text-xs {{ $user->supervisor ? 'text-indigo-700 font-medium' : 'text-gray-400' }}">
                                                {{ $user->supervisor ? 'Supervisor: '.$user->supervisor->name : 'Sin supervisor asignado' }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($user->is_active)
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-green-50 text-green-700 border-green-200">Activo</span>
                                    @else
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-red-50 text-red-700 border-red-200">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        @if($user->role === 'supervisor')
                                            <button wire:click="openCollaboratorsModal({{ $user->id }})" title="Asignar Colaboradores" class="p-2 text-purple-600 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                                </svg>
                                            </button>
                                        @endif
                                        @if($user->role === 'cuentas')
                                            <button wire:click="viewAccounts({{ $user->id }})" title="Ver Cuentas Asignadas" class="p-2 text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                                </svg>
                                            </button>
                                        @endif
                                        <button wire:click="edit({{ $user->id }})" title="Editar" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </button>
                                        
                                        @if($user->id !== auth()->id())
                                            <button wire:click="toggleActive({{ $user->id }})" title="{{ $user->is_active ? 'Desactivar Usuario' : 'Activar Usuario' }}" class="p-2 {{ $user->is_active ? 'text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100' : 'text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100' }} rounded-lg transition-colors">
                                                @if($user->is_active)
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    </table>
                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div x-data x-init="setTimeout(() => $refs.firstInput?.focus(), 50)" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                
                <div class="fixed inset-0 z-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('showModal', false)"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="relative z-10 inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ $userId ? 'Editar Usuario' : 'Nuevo Usuario' }}
                            </h3>
                            
                            <div>
                                <x-input-label for="name" value="Nombre" />
                                <x-text-input x-ref="firstInput" wire:model="name" id="name" class="block mt-1 w-full" type="text" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" value="Correo Electrónico" />
                                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="role" value="Rol" />
                                <select wire:model.live="role" id="role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                    <option value="user">Usuario Regular</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="cuentas">Gestor de Cuentas</option>
                                    <option value="admin">Administrador</option>
                                </select>
                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            @if($role === 'user')
                                <div>
                                    <x-input-label for="supervisorId" value="Supervisor asignado" />
                                    <select wire:model="supervisorId" id="supervisorId" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                        <option value="">Sin supervisor asignado</option>
                                        @foreach($supervisors as $supervisorOption)
                                            <option value="{{ $supervisorOption->id }}">{{ $supervisorOption->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('supervisorId')" class="mt-2" />
                                </div>
                            @endif

                            <div>
                                <x-input-label for="password" value="Contraseña {{ $userId ? '(Dejar en blanco para no cambiar)' : '' }}" />
                                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
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

    <!-- Accounts View Modal -->
    @if($showAccountsModal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showAccountsModal', false)"></div>
                <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-3xl w-full">
                    <div class="bg-emerald-600 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-xl leading-6 font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                            Cuentas asignadas a {{ $viewingUserName }}
                        </h3>
                        <button type="button" wire:click="$set('showAccountsModal', false)" class="text-emerald-100 hover:text-white focus:outline-none">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <p class="text-sm text-gray-500">Listado de cuentas de correo administradas por este gestor.</p>
                            <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full">{{ count($viewingUserAccounts) }} Cuentas</span>
                        </div>

                        @if(count($viewingUserAccounts) > 0)
                            <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correo</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alias</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfiles</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatus</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($viewingUserAccounts as $account)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $account->email }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $account->alias ?: '-' }}</td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $account->profiles_count }} Perfiles
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    @if($account->status === 'active')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Activa</span>
                                                    @else
                                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Suspendida</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay cuentas asignadas</h3>
                                <p class="mt-1 text-sm text-gray-500">Este gestor todavía no tiene cuentas de correo a su cargo.</p>
                            </div>
                        @endif
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end border-t border-gray-200">
                        <button type="button" wire:click="$set('showAccountsModal', false)" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Collaborators Assignment Modal -->
    @if($showCollaboratorsModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="collaborators-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showCollaboratorsModal', false)"></div>
                <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-2xl w-full">
                    <form wire:submit="saveAdminCollaborators">
                        <div class="bg-purple-600 px-6 py-4 flex justify-between items-center">
                            <h3 class="text-lg leading-6 font-bold text-white" id="collaborators-modal-title">
                                Asignar colaboradores a {{ $assigningAdminName }}
                            </h3>
                            <button type="button" wire:click="$set('showCollaboratorsModal', false)" class="text-purple-100 hover:text-white focus:outline-none">
                                <span class="sr-only">Cerrar</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <p class="text-xs text-gray-500">
                                    Selecciona los colaboradores que este supervisor deberá monitorear.
                                </p>
                                <span class="shrink-0 bg-purple-100 text-purple-800 text-xs font-bold px-3 py-1 rounded-full">
                                    {{ count($assignedCollaboratorIds) }} seleccionados
                                </span>
                            </div>

                            <div>
                                <x-text-input wire:model.live.debounce.300ms="collaboratorSearch" type="search" class="block w-full text-sm" placeholder="Buscar colaborador por nombre o correo..." />
                            </div>

                            <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100">
                                @forelse($collaboratorCandidates as $collaborator)
                                    <label wire:key="assign-collab-{{ $collaborator->id }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-purple-50/40 cursor-pointer">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <input
                                                type="checkbox"
                                                value="{{ $collaborator->id }}"
                                                wire:model.live="assignedCollaboratorIds"
                                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                            >
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $collaborator->name }}</p>
                                                <p class="text-xs text-gray-500 truncate">{{ $collaborator->email }}</p>
                                            </div>
                                        </div>
                                        <div class="shrink-0">
                                            @if($collaborator->supervisor_id === $assigningAdminId)
                                                <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-purple-100 text-purple-800">Asignado aquí</span>
                                            @elseif($collaborator->supervisor)
                                                <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-amber-100 text-amber-800">En: {{ $collaborator->supervisor->name }}</span>
                                            @else
                                                <span class="px-2 py-0.5 text-[11px] font-medium rounded-full bg-gray-100 text-gray-600">Sin supervisor</span>
                                            @endif
                                        </div>
                                    </label>
                                @empty
                                    <div class="py-8 text-center text-sm text-gray-500">
                                        No se encontraron colaboradores con ese criterio.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end gap-2 border-t border-gray-200">
                            <button type="button" wire:click="$set('showCollaboratorsModal', false)" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <x-primary-button type="submit">
                                Guardar Asignación
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
