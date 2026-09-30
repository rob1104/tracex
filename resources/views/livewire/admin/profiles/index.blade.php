<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Perfiles Sociales
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 space-y-4 sm:space-y-0">
                        <div class="flex-1 w-full sm:w-1/3 mr-4">
                            <x-text-input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar perfil..." class="w-full" />
                        </div>
                        <div class="flex gap-3 flex-wrap justify-end">
                            <button wire:click="openColabProfilesModal" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Ver por Colaborador
                            </button>
                            @if(auth()->user()->isAdmin())
                                <button wire:click="openMassAssignModal" class="inline-flex items-center px-4 py-2 bg-slate-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-700 focus:bg-slate-700 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Asignación Masiva
                                </button>
                            @endif
                            <x-primary-button wire:click="create">
                                Nuevo Perfil
                            </x-primary-button>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="flex flex-col sm:flex-row gap-4 mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        @if(auth()->user()->isAdmin())
                            <div class="w-full sm:w-1/4">
                                <x-input-label for="filter_gestor" value="Gestor" class="text-xs text-gray-500 mb-1" />
                                <select wire:model.live="filter_gestor" id="filter_gestor" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm">
                                    <option value="">Todos los gestores</option>
                                    @foreach($cuentasUsers as $gestor)
                                        <option value="{{ $gestor->id }}">{{ $gestor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="w-full sm:w-1/4">
                            <x-input-label for="filter_email_account_id" value="Cuenta de Correo" class="text-xs text-gray-500 mb-1" />
                            <select wire:model.live="filter_email_account_id" id="filter_email_account_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm">
                                <option value="">Todas las cuentas</option>
                                @foreach($emails as $email)
                                    <option value="{{ $email->id }}">{{ $email->email }} {{ $email->alias ? '('.$email->alias.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-1/4">
                            <x-input-label for="filter_network" value="Red Social" class="text-xs text-gray-500 mb-1" />
                            <select wire:model.live="filter_network" id="filter_network" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm">
                                <option value="">Todas las redes</option>
                                @foreach($networks as $network)
                                    <option value="{{ $network }}">{{ $network }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-1/4">
                            <x-input-label for="filter_status" value="Estatus" class="text-xs text-gray-500 mb-1" />
                            <select wire:model.live="filter_status" id="filter_status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm">
                                <option value="">Todos los estatus</option>
                                <option value="active">Activa</option>
                                <option value="restricted">Restringida</option>
                                <option value="suspended">Suspendida</option>
                            </select>
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
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfil</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta Asociada</th>
                                    @if(auth()->user()->isAdmin())
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gestor</th>
                                    @endif
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contraseña</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colaboradores</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatus</th>
                                    <th scope="col" class="relative px-4 py-2"><span class="sr-only">Acciones</span></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($profiles as $profile)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-normal break-words">
                                            <div class="flex items-center">
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ $profile->name }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        @php
                                                            $netColors = [
                                                                'Facebook' => 'bg-blue-600 text-white',
                                                                'X' => 'bg-slate-100 text-slate-800',
                                                                'Instagram' => 'bg-fuchsia-100 text-fuchsia-800',
                                                                'TikTok' => 'bg-black text-white',
                                                                'LinkedIn' => 'bg-sky-100 text-sky-800',
                                                                'YouTube' => 'bg-red-100 text-red-800',
                                                            ];
                                                            $color = $netColors[$profile->social_network] ?? 'bg-gray-100 text-gray-800';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                                                            {{ $profile->social_network }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-normal break-words">
                                            <div class="text-sm text-gray-900">{{ $profile->emailAccount->email ?? 'Sin cuenta' }}</div>
                                            <div class="text-xs text-gray-500">{{ $profile->emailAccount->alias ?? '' }}</div>
                                        </td>
                                        @if(auth()->user()->isAdmin())
                                            <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">{{ $profile->creator->name ?? 'N/A' }}</td>
                                        @endif
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">
                                            @if($profile->password)
                                                <button wire:click="revealPassword({{ $profile->id }})" class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1 text-xs font-semibold bg-indigo-50 px-2 py-1 rounded transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    Ver Contraseña
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-400">Sin contraseña</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">
                                            <button wire:click="openAssignModal({{ $profile->id }})" class="flex items-center gap-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-colors font-semibold text-xs border border-indigo-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                {{ $profile->users->count() }} Asignados
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 whitespace-normal break-words text-sm text-gray-500">
                                            @if($profile->status === 'active')
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-green-50 text-green-700 border-green-200">Activo</span>
                                            @elseif($profile->status === 'restricted')
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-orange-50 text-orange-700 border-orange-200">Restringido</span>
                                            @else
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-full border bg-red-50 text-red-700 border-red-200">Suspendido</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-normal break-words text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button wire:click="edit({{ $profile->id }})" title="Editar" class="p-2 text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                                </button>

                                                <button wire:click="toggleStatus({{ $profile->id }})" title="{{ $profile->status === 'active' ? 'Suspender' : 'Activar' }}" class="p-2 {{ $profile->status === 'active' ? 'text-amber-600 hover:text-amber-900 bg-amber-50 hover:bg-amber-100' : 'text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100' }} rounded-lg transition-colors">
                                                    @if($profile->status === 'active')
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
                                        <td colspan="6" class="px-4 py-3 text-center text-sm text-gray-500">
                                            No hay perfiles sociales registrados.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $profiles->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form CRUD -->
    @if($showModal)
        <div x-data x-init="setTimeout(() => $refs.firstInput.focus(), 50)" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 space-y-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                {{ $profileId ? 'Editar Perfil' : 'Nuevo Perfil' }}
                            </h3>
                            <div>
                                <x-input-label for="name" value="Nombre del Perfil" />
                                <x-text-input x-ref="firstInput" wire:model="name" id="name" class="block mt-1 w-full" type="text" required placeholder="Ej. Perfil Juan Pérez" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <!-- Alpine Searchable Dropdown for Email Account -->
                            <div x-data="{
                                    search: '',
                                    open: false,
                                    selectedText: 'Seleccione una cuenta...',

                                    init() {
                                        this.updateSelectedText();
                                        $watch('$wire.email_account_id', () => {
                                            this.updateSelectedText();
                                        });
                                    },

                                    updateSelectedText() {
                                        if (!$wire.email_account_id) {
                                            this.selectedText = 'Seleccione una cuenta...';
                                            return;
                                        }
                                        let selectedOpt = this.$refs.accountsList.querySelector(`li[data-id='${$wire.email_account_id}']`);
                                        if (selectedOpt) {
                                            this.selectedText = selectedOpt.getAttribute('data-text');
                                        }
                                    },

                                    selectAccount(id, text) {
                                        $wire.set('email_account_id', id);
                                        this.selectedText = text;
                                        this.open = false;
                                    }
                                }"
                                class="relative"
                                @click.outside="open = false">

                                <x-input-label value="Cuenta de Correo Asociada" />

                                <button type="button" @click="open = !open" class="mt-1 relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <span class="block truncate" x-text="selectedText">Seleccione una cuenta...</span>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>

                                <div x-show="open" class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm" style="display: none;">
                                    <div class="px-2 pb-2 sticky top-0 bg-white pt-2">
                                        <input type="text" x-model="search" placeholder="Buscar por correo o alias..." class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm sm:text-sm">
                                    </div>
                                    <ul x-ref="accountsList" class="max-h-48 overflow-y-auto">
                                        <li @click="$wire.set('email_account_id', ''); selectedText = 'Seleccione una cuenta...'; open = false;" class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white">
                                            <span class="block font-normal truncate">-- Seleccione --</span>
                                        </li>
                                        @foreach($emails as $email)
                                            @php
                                                $displayText = $email->email . ($email->alias ? ' ('.$email->alias.')' : '');
                                            @endphp
                                            <li data-id="{{ $email->id }}" data-text="{{ addslashes($displayText) }}"
                                                x-show="'{{ strtolower(addslashes($displayText)) }}'.includes(search.toLowerCase())"
                                                @click="selectAccount('{{ $email->id }}', '{{ addslashes($displayText) }}')"
                                                class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white transition-colors">
                                                <div class="flex items-center">
                                                    <span class="font-normal block truncate">{{ $email->email }}</span>
                                                    @if($email->alias)
                                                        <span class="ml-2 text-xs text-white bg-indigo-600 rounded px-1">{{ $email->alias }}</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <x-input-error :messages="$errors->get('email_account_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="social_network" value="Red Social" />
                                <select wire:model="social_network" id="social_network" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Facebook">Facebook</option>
                                    <option value="X">X</option>
                                    <option value="Instagram">Instagram</option>
                                    <option value="TikTok">TikTok</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="YouTube">YouTube</option>
                                </select>
                                <x-input-error :messages="$errors->get('social_network')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="password" value="Contraseña" />
                                <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="text" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="status" value="Estatus" />
                                <select wire:model="status" id="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                    <option value="active">Activo</option>
                                    <option value="restricted">Restringido</option>
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
                        <div class="mt-2 p-3 bg-gray-100 rounded text-center text-xl font-mono tracking-wider text-gray-800 break-all select-all">
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

    <!-- Assign Modal -->
    @if($showAssignModal && $assignProfile)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showAssignModal', false)"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="saveAssignments">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                                Asignar Colaboradores a "{{ $assignProfile->name }}"
                            </h3>
                            <p class="text-sm text-gray-500 mb-4">Selecciona qué colaboradores podrán usar este perfil para registrar sus evidencias.</p>

                            <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md bg-gray-50 p-2 space-y-1">
                                @foreach($usersList as $user)
                                    <label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-gray-200 hover:shadow-sm">
                                        <input type="checkbox" wire:model="assignedUsers" value="{{ $user->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-700">{{ $user->name }}</span>
                                            <span class="block text-xs text-gray-500">{{ $user->email }}</span>
                                        </div>
                                    </label>
                                @endforeach
                                @if($usersList->isEmpty())
                                    <div class="text-center p-4 text-sm text-gray-500">No hay colaboradores activos en el sistema.</div>
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <x-primary-button type="submit" class="w-full sm:w-auto sm:ml-3">Guardar Asignaciones</x-primary-button>
                            <button type="button" wire:click="$set('showAssignModal', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancelar</button>
                        </div>
                    </form>
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
                                Reasignación Masiva de Perfiles
                            </h3>
                            <button type="button" wire:click="$set('showMassAssignModal', false)" class="text-indigo-100 hover:text-white focus:outline-none">
                                <span class="sr-only">Cerrar</span>
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <p class="text-sm text-gray-500 mb-6">Transfiere de forma masiva los perfiles asignados a un colaborador para que sean administrados por otro.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <x-input-label for="sourceColabId" value="Colaborador Origen" />
                                    <select wire:model.live="sourceColabId" id="sourceColabId" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                        <option value="">Seleccione origen...</option>
                                        @foreach($usersList as $colab)
                                            <option value="{{ $colab->id }}">{{ $colab->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('sourceColabId')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="destinationColabId" value="Colaborador Destino" />
                                    <select wire:model.live="destinationColabId" id="destinationColabId" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                                        <option value="">Seleccione destino...</option>
                                        @foreach($usersList as $colab)
                                            @if($colab->id != $sourceColabId)
                                                <option value="{{ $colab->id }}">{{ $colab->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('destinationColabId')" class="mt-2" />
                                </div>
                            </div>

                            @if($sourceColabId)
                                <div class="mt-4 border-t border-gray-200 pt-4">
                                    <h4 class="text-md font-semibold text-gray-800 mb-3 flex items-center justify-between">
                                        Perfiles a Transferir
                                        <span class="bg-indigo-100 text-indigo-800 text-xs py-1 px-2 rounded-full">{{ count($sourceProfiles) }} perfiles encontrados</span>
                                    </h4>
                                    
                                    @if(count($sourceProfiles) > 0)
                                        <div class="mb-2 flex justify-end">
                                            <button type="button" wire:click="toggleSelectAll" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium focus:outline-none">
                                                {{ count($selectedProfiles) === count($sourceProfiles) ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                                            </button>
                                        </div>
                                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md bg-gray-50 p-2">
                                            <div class="space-y-2">
                                                @foreach($sourceProfiles as $profile)
                                                    <label class="flex items-center p-2 bg-white rounded border border-gray-100 shadow-sm cursor-pointer hover:bg-indigo-50 transition-colors">
                                                        <input type="checkbox" wire:model="selectedProfiles" value="{{ $profile->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-3 w-4 h-4">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-gray-900 text-sm">{{ $profile->name }}</div>
                                                            <div class="text-xs text-gray-500">{{ $profile->social_network }} | {{ $profile->emailAccount ? $profile->emailAccount->email : 'Sin cuenta' }}</div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 italic p-4 bg-gray-50 rounded-md text-center">Este colaborador no tiene perfiles asignados actualmente.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('selectedProfiles')" class="mt-2" />
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200">
                            <button type="submit" onclick="return confirm('¿Estás seguro de reasignar los perfiles seleccionados?')" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50" @if(count($sourceProfiles) === 0 || !$destinationColabId) disabled @endif>
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

    <!-- Colab Profiles View Modal -->
    @if($showColabProfilesModal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="$set('showColabProfilesModal', false)"></div>
                <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-3xl w-full">
                    <div class="bg-emerald-600 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-xl leading-6 font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            Ver Perfiles por Colaborador
                        </h3>
                        <button type="button" wire:click="$set('showColabProfilesModal', false)" class="text-emerald-100 hover:text-white focus:outline-none">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 min-h-[400px]">
                        <div class="mb-6">
                            <x-input-label value="Seleccione o busque un colaborador" />
                            
                            <!-- Alpine Searchable Dropdown for Colaborador -->
                            <div x-data="{
                                    search: '',
                                    open: false,
                                    selectedText: 'Seleccione un colaborador...',

                                    init() {
                                        this.updateSelectedText();
                                        $watch('$wire.selectedColabViewId', () => {
                                            this.updateSelectedText();
                                        });
                                    },

                                    updateSelectedText() {
                                        if (!$wire.selectedColabViewId) {
                                            this.selectedText = 'Seleccione un colaborador...';
                                            return;
                                        }
                                        let item = this.$refs.colabsList.querySelector(`li[data-id='${$wire.selectedColabViewId}']`);
                                        if (item) {
                                            this.selectedText = item.getAttribute('data-text');
                                        }
                                    },

                                    selectColab(id, text) {
                                        $wire.set('selectedColabViewId', id);
                                        this.selectedText = text;
                                        this.open = false;
                                        this.search = '';
                                    }
                                }"
                                class="relative mt-1"
                                @click.outside="open = false">
                                
                                <button type="button" @click="open = !open" class="relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm">
                                    <span class="block truncate" x-text="selectedText"></span>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>

                                <div x-show="open" class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm" style="display: none;">
                                    <div class="px-2 pb-2 sticky top-0 bg-white pt-2">
                                        <input type="text" x-model="search" placeholder="Buscar colaborador..." class="w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm sm:text-sm">
                                    </div>
                                    <ul x-ref="colabsList" class="max-h-48 overflow-y-auto">
                                        <li @click="$wire.set('selectedColabViewId', ''); selectedText = 'Seleccione un colaborador...'; open = false;" class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-emerald-600 hover:text-white">
                                            <span class="block font-normal truncate">-- Seleccione --</span>
                                        </li>
                                        @foreach($usersList as $colab)
                                            <li data-id="{{ $colab->id }}" data-text="{{ addslashes($colab->name) }}"
                                                x-show="'{{ strtolower(addslashes($colab->name)) }}'.includes(search.toLowerCase())"
                                                @click="selectColab('{{ $colab->id }}', '{{ addslashes($colab->name) }}')"
                                                class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-emerald-600 hover:text-white transition-colors">
                                                <div class="flex items-center">
                                                    <span class="font-normal block truncate">{{ $colab->name }}</span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>

                        @if($selectedColabViewId)
                            <div class="flex justify-between items-center mb-4">
                                <p class="text-sm text-gray-500">Perfiles asignados a este colaborador.</p>
                                <span class="bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full">{{ count($viewingColabProfiles) }} Perfiles</span>
                            </div>

                            @if(count($viewingColabProfiles) > 0)
                                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre del Perfil</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Red Social</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cuenta Asociada</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatus</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($viewingColabProfiles as $profile)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $profile->name }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $profile->social_network }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $profile->emailAccount ? $profile->emailAccount->email : '-' }}</td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        @if($profile->status === 'active')
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Activo</span>
                                                        @elseif($profile->status === 'restricted')
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-50 text-orange-700 border border-orange-200">Restringido</span>
                                                        @else
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Suspendido</span>
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
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay perfiles</h3>
                                    <p class="mt-1 text-sm text-gray-500">Este colaborador no tiene perfiles sociales asignados.</p>
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end border-t border-gray-200">
                        <button type="button" wire:click="$set('showColabProfilesModal', false)" class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
