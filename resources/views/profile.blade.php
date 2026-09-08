<x-app-layout>
    <x-slot name="header">
        Mi Perfil
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-50 rounded-bl-full -z-10 opacity-50"></div>
                    <div class="w-full">
                        <livewire:profile.update-profile-information-form />
                    </div>
                </div>

                <div class="p-8 bg-white shadow-sm sm:rounded-2xl border border-gray-100 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-teal-50 rounded-bl-full -z-10 opacity-50"></div>
                    <div class="w-full">
                        <livewire:profile.update-password-form />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
