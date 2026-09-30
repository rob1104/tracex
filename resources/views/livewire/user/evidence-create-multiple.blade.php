<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Captura Masiva de Evidencias
        </h2>
        <a href="{{ route('evidences.create') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
            Volver a Captura Individual
        </a>
    </div>
</x-slot>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-indigo-50 border-l-4 border-indigo-400 p-4 mb-6 sm:rounded-lg shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-indigo-700">
                        <strong>Modo Masivo:</strong> Selecciona múltiples perfiles. Se creará un registro de evidencia individual por cada perfil seleccionado, y a todos se les adjuntará la misma captura de pantalla.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <form wire:submit="save" class="space-y-6 max-w-3xl mx-auto">

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

                    <!-- Multiple Profiles Selection -->
                    <div class="mb-6">
                        <x-input-label value="Perfiles de Red Social (Selecciona todos los que apliquen)" />
                        
                        <div x-data="{ search: '' }" class="mt-2 border border-gray-300 rounded-md bg-gray-50">
                            <div class="p-2 border-b border-gray-200 bg-white rounded-t-md">
                                <input type="text" x-model="search" placeholder="Buscar perfil..." class="w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm sm:text-sm">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-2 max-h-60 overflow-y-auto">
                                @foreach($profiles as $profile)
                                    <label x-show="'{{ strtolower(addslashes($profile->name)) }}'.includes(search.toLowerCase())" class="flex items-start space-x-3 bg-white p-3 border border-gray-200 rounded shadow-sm hover:bg-indigo-50 cursor-pointer transition-colors">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <input type="checkbox" wire:model="profile_ids" value="{{ $profile->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 w-4 h-4">
                                        </div>
                                        <div class="flex flex-col flex-1 min-w-0">
                                            <span class="text-sm font-medium text-gray-900 truncate">{{ $profile->name }}</span>
                                            <span class="text-xs text-gray-500">{{ $profile->social_network }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('profile_ids')" class="mt-2" />
                    </div>

                    <!-- Images -->
                    <div x-data="{
                        isUploading: false,
                        handlePaste(e) {
                            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                            const files = [];
                            for (let i = 0; i < items.length; i++) {
                                if (items[i].type.indexOf('image') !== -1) {
                                    files.push(items[i].getAsFile());
                                }
                            }
                            
                            if (files.length > 0) {
                                e.preventDefault();
                                this.isUploading = true;
                                $wire.uploadMultiple('images', files, 
                                    () => { this.isUploading = false; },
                                    () => { this.isUploading = false; alert('Error al procesar la imagen del portapapeles. Intenta subirla manualmente.'); },
                                    (event) => {}
                                );
                            }
                        }
                    }" @paste.window="handlePaste($event)">
                        <x-input-label value="Evidencia (Captura de pantalla)" />
                        
                        <!-- Drag, Drop & Paste Area -->
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md relative hover:bg-gray-50 transition-colors cursor-pointer"
                             onclick="document.getElementById('file-upload-multiple').click()">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 justify-center">
                                    <label for="file-upload-multiple" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                        <span>Sube un archivo</span>
                                        <input id="file-upload-multiple" name="file-upload-multiple" type="file" class="sr-only" wire:model="images" accept="image/*" multiple>
                                    </label>
                                    <p class="pl-1">o arrastra y suelta</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    PNG, JPG, GIF hasta 5MB. <strong>¡Puedes presionar Ctrl+V para pegar!</strong>
                                </p>
                            </div>
                        </div>

                        <!-- Loading Indicator -->
                        <div x-show="isUploading" class="mt-2 text-sm text-indigo-600 flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Procesando imagen pegada...
                        </div>

                        <x-input-error :messages="$errors->get('images')" class="mt-2" />
                        <x-input-error :messages="$errors->get('images.*')" class="mt-2" />

                        <!-- Image Preview -->
                        @if ($images)
                            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                                @foreach($images as $image)
                                    <div class="relative group rounded-lg overflow-hidden border border-gray-200 shadow-sm">
                                        <img src="{{ $image->temporaryUrl() }}" class="object-cover w-full h-24">
                                        <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <button type="button" wire:click="$set('images', [])" class="text-white bg-red-600 hover:bg-red-700 rounded-full p-1 focus:outline-none">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Comment -->
                    <div>
                        <x-input-label for="comment" value="Comentario / Notas" />
                        <textarea wire:model="comment" id="comment" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" placeholder="Detalles adicionales sobre la evidencia..."></textarea>
                        <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end">
                        <x-primary-button>
                            Guardar Evidencias Múltiples
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
