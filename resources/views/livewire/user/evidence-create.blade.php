<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Registrar Evidencia
    </h2>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <form wire:submit="save" class="space-y-6 max-w-2xl mx-auto">

                    <!-- Evidence Type -->
                    <div>
                        <x-input-label for="evidence_type_id" value="Tipo de Evidencia" />
                        <select wire:model="evidence_type_id" id="evidence_type_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                            <option value="">Selecciona una opción...</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('evidence_type_id')" class="mt-2" />
                    </div>

                    <!-- Profile Selector with Alpine Searchable Dropdown -->
                    <div x-data="{
                            search: '',
                            open: false,
                            selectedName: 'Seleccionar perfil...',
                            selectProfile(id, name, network) {
                                $wire.set('profile_id', id);
                                this.selectedName = name + ' (' + network + ')';
                                this.open = false;
                            }
                        }"
                        class="relative"
                        @click.outside="open = false">

                        <x-input-label value="Perfil de Red Social" />

                        <button type="button" @click="open = !open" class="mt-1 relative w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <span class="block truncate" x-text="selectedName">Seleccionar perfil...</span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open" class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm" style="display: none;">
                            <div class="px-2 pb-2">
                                <input type="text" x-model="search" placeholder="Escribe para buscar..." class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm sm:text-sm">
                            </div>
                            <ul class="max-h-48 overflow-y-auto">
                                <li @click="$wire.set('profile_id', ''); selectedName = 'Sin perfil asignado (Manual)'; open = false;" class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white">
                                    <span class="block font-normal truncate">Sin perfil asignado (Registro Manual)</span>
                                </li>
                                @foreach($profiles as $profile)
                                    <li x-show="'{{ strtolower($profile->name) }}'.includes(search.toLowerCase())"
                                        @click="selectProfile('{{ $profile->id }}', '{{ addslashes($profile->name) }}', '{{ addslashes($profile->social_network) }}')"
                                        class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white transition-colors">
                                        <div class="flex items-center">
                                            <span class="font-normal block truncate">{{ $profile->name }}</span>
                                            <span class="ml-2 text-xs text-white bg-blue-600 rounded px-1">{{ $profile->social_network }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- Social Network (Manual - Hidden if profile selected) -->
                    <div x-data x-show="!$wire.profile_id">
                        <x-input-label for="social_network" value="Red Social (Manual)" />
                        <select wire:model="social_network" id="social_network" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" :required="!$wire.profile_id">
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="X">X</option>
                            <option value="TikTok">TikTok</option>
                            <option value="LinkedIn">LinkedIn</option>
                            <option value="YouTube">YouTube</option>
                            <option value="Otro">Otro</option>
                        </select>
                        <x-input-error :messages="$errors->get('social_network')" class="mt-2" />
                    </div>

                    <!-- Images -->
                    <div>
                        <x-input-label for="images" value="Captura(s) de Pantalla" />
                        <input type="file" wire:model="images" id="images" class="block mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" multiple accept="image/*" required>
                        <x-input-error :messages="$errors->get('images')" class="mt-2" />

                        <div wire:loading wire:target="images" class="text-sm text-indigo-600 mt-2">Cargando imágenes...</div>

                        @if ($images)
                            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                                @foreach($images as $image)
                                    <img src="{{ $image->temporaryUrl() }}" class="rounded-lg shadow-sm w-full h-32 object-cover">
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Comment -->
                    <div>
                        <x-input-label for="comment" value="Comentario / Notas (Opcional)" />
                        <textarea wire:model="comment" id="comment" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"></textarea>
                        <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end">
                        <a href="{{ url('/dashboard') }}" class="text-sm text-gray-600 hover:text-gray-900 underline mr-4" wire:navigate>
                            Cancelar
                        </a>
                        <x-primary-button>
                            <span wire:loading.remove wire:target="save">Guardar Evidencia</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
