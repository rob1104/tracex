<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50 relative overflow-hidden">
        <!-- Background vibrant gradient with abstract blobs -->
        <div class="absolute inset-0 bg-gradient-to-br from-violet-600 via-indigo-600 to-teal-500 z-0 pointer-events-none">
            <div class="absolute top-0 left-0 w-full h-full opacity-40">
                <div class="absolute -top-20 -left-20 w-96 h-96 bg-fuchsia-400 rounded-full mix-blend-multiply filter blur-3xl opacity-70"></div>
                <div class="absolute top-40 -right-10 w-96 h-96 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-70"></div>
                <div class="absolute -bottom-20 left-1/3 w-96 h-96 bg-violet-400 rounded-full mix-blend-multiply filter blur-3xl opacity-70"></div>
            </div>
        </div>

        <div class="min-h-screen flex flex-col justify-center items-center p-6 relative z-10">
            <div class="w-full max-w-md">
                <!-- Form Container -->
                <div class="bg-white/95 backdrop-blur-md shadow-2xl rounded-3xl p-8 sm:p-10 border border-white/40">
                    <div class="mb-8 flex flex-col items-center text-center">
                        <a href="/" wire:navigate class="flex items-center justify-center">
                            <x-application-logo class="w-20 h-20 shadow-md ring-4 ring-white/50 rounded-2xl" />
                        </a>
                        <h2 class="mt-6 text-3xl font-black bg-clip-text text-transparent bg-gradient-to-r from-violet-600 to-indigo-600">TraceX</h2>
                        <p class="mt-2 text-sm text-gray-500 font-medium">Ingresa tus credenciales para acceder</p>
                    </div>

                    {{ $slot }}
                </div>
                
                <div class="mt-8 text-center text-sm text-indigo-100 font-medium tracking-wide drop-shadow-md">
                    &copy; {{ date('Y') }} TraceX. Todos los derechos reservados.
                </div>
            </div>
        </div>
    </body>
</html>
