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

                    <!-- Social Network -->
                    <div>
                        <x-input-label for="social_network" value="Red Social" />
                        <select wire:model="social_network" id="social_network" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full" required>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="X">X (Twitter)</option>
                            <option value="TikTok">TikTok</option>
                            <option value="LinkedIn">LinkedIn</option>
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
