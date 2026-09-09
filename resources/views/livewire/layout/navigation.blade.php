<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-slate-900 border-b border-slate-800 shadow-lg relative z-50">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                        <x-application-logo class="block h-10 w-10 ring-2 ring-white/20 rounded-xl" />
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-300 to-teal-300">TraceX</span>
                            <span class="px-2 py-0.5 rounded-md bg-slate-800 border border-slate-700 text-slate-400 text-[10px] font-bold tracking-wider">
                                {{ config('app.version', 'v1.0.2') }}
                            </span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        Panel Principal
                    </x-nav-link>

                    @if(auth()->user()->isUser())
                        <x-nav-link :href="route('evidences.create')" :active="request()->routeIs('evidences.create')" wire:navigate>
                            Registrar Evidencia
                        </x-nav-link>
                        <x-nav-link :href="route('evidences.list')" :active="request()->routeIs('evidences.list')" wire:navigate>
                            Historial
                        </x-nav-link>
                    @endif

                    @if(auth()->user()->isAdmin())
                        <x-nav-link :href="route('admin.evidences.index')" :active="request()->routeIs('admin.evidences.index')" wire:navigate>
                            Reporte de Evidencias
                        </x-nav-link>
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')" wire:navigate>
                            Usuarios
                        </x-nav-link>
                        <x-nav-link :href="route('admin.evidence_types.index')" :active="request()->routeIs('admin.evidence_types.index')" wire:navigate>
                            Catálogo
                        </x-nav-link>
                    @endif

                    @if(auth()->user()->isAdmin() || auth()->user()->isCuentas())
                        <x-nav-link :href="route('admin.emails.index')" :active="request()->routeIs('admin.emails.index')" wire:navigate>
                            Cuentas de Correo
                        </x-nav-link>
                        <x-nav-link :href="route('admin.profiles.index')" :active="request()->routeIs('admin.profiles.index')" wire:navigate>
                            Perfiles
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-300 bg-slate-800 hover:text-white hover:bg-slate-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            @if(auth()->user()->isAdmin())
                                <span class="ms-2 px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 text-[10px] font-bold uppercase tracking-wider border border-emerald-500/30">
                                    Admin
                                </span>
                            @elseif(auth()->user()->isCuentas())
                                <span class="ms-2 px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 text-[10px] font-bold uppercase tracking-wider border border-amber-500/30">
                                    Cuentas
                                </span>
                            @else
                                <span class="ms-2 px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-wider border border-indigo-500/30">
                                    Colaborador
                                </span>
                            @endif

                            <div class="ms-2">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            Mi Perfil
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                Cerrar Sesión
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none focus:bg-slate-800 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden bg-slate-900 border-t border-slate-800">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                Panel Principal
            </x-responsive-nav-link>

            @if(auth()->user()->isUser())
                <x-responsive-nav-link :href="route('evidences.create')" :active="request()->routeIs('evidences.create')" wire:navigate>
                    Registrar Evidencia
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('evidences.list')" :active="request()->routeIs('evidences.list')" wire:navigate>
                    Historial
                </x-responsive-nav-link>
            @endif

            @if(auth()->user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.evidences.index')" :active="request()->routeIs('admin.evidences.index')" wire:navigate>
                    Reporte de Evidencias
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')" wire:navigate>
                    Usuarios
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.evidence_types.index')" :active="request()->routeIs('admin.evidence_types.index')" wire:navigate>
                    Catálogo
                </x-responsive-nav-link>
            @endif

            @if(auth()->user()->isAdmin() || auth()->user()->isCuentas())
                <x-responsive-nav-link :href="route('admin.emails.index')" :active="request()->routeIs('admin.emails.index')" wire:navigate>
                    Cuentas de Correo
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.profiles.index')" :active="request()->routeIs('admin.profiles.index')" wire:navigate>
                    Perfiles
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-slate-700">
            <div class="px-4">
                <div class="flex items-center gap-2">
                    <div class="font-medium text-base text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    @if(auth()->user()->isAdmin())
                        <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 text-[10px] font-bold uppercase tracking-wider border border-emerald-500/30">
                            Admin
                        </span>
                    @elseif(auth()->user()->isCuentas())
                        <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 text-[10px] font-bold uppercase tracking-wider border border-amber-500/30">
                            Cuentas
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 text-[10px] font-bold uppercase tracking-wider border border-indigo-500/30">
                            Colaborador
                        </span>
                    @endif
                </div>
                <div class="font-medium text-sm text-slate-400">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    Mi Perfil
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        Cerrar Sesión
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
