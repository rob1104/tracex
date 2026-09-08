<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->fill($validated);
        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-xl font-bold text-slate-900">
            Información Personal
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Actualiza el nombre de tu cuenta. Tu correo electrónico es fijo y no puede modificarse por seguridad.
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6 relative z-10">
        <div>
            <x-input-label for="name" value="Nombre Completo" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full border-gray-300 shadow-sm" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo Electrónico (No modificable)" />
            <div class="mt-1 flex rounded-md shadow-sm">
                <input type="email" value="{{ auth()->user()->email }}" disabled class="block w-full rounded-md border-gray-300 bg-gray-100 text-gray-500 cursor-not-allowed sm:text-sm" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar Cambios</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                Guardado exitosamente.
            </x-action-message>
        </div>
    </form>
</section>
