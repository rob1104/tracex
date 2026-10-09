<?php

use App\Livewire\Admin\Evidences\Index;
use App\Livewire\User\EvidenceCreate;
use App\Livewire\User\EvidenceCreateMultiple;
use App\Livewire\User\EvidenceList;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'role:user,admin'])->group(function () {
    Route::get('/evidencias/registrar', EvidenceCreate::class)->name('evidences.create');
    Route::get('/evidencias/registrar-masivo', EvidenceCreateMultiple::class)->name('evidences.create.multiple');
    Route::get('/evidencias/historial', EvidenceList::class)->name('evidences.list');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/evidencias', Index::class)->name('evidences.index');
    Route::get('/usuarios', App\Livewire\Admin\Users\Index::class)->name('users.index');
    Route::get('/tipos-evidencia', App\Livewire\Admin\EvidenceTypes\Index::class)->name('evidence_types.index');
});

Route::middleware(['auth', 'verified', 'role:admin,cuentas'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/cuentas-correo', App\Livewire\Admin\Emails\Index::class)->name('emails.index');
    Route::get('/perfiles-sociales', App\Livewire\Admin\Profiles\Index::class)->name('profiles.index');
});

require __DIR__.'/auth.php';

// TraceX: Monitoreo de usuarios (módulo integrado).
Route::middleware(['auth', 'verified'])->prefix('monitoreo')->name('monitor.')->group(function () {
    Route::get('/usuarios', App\Livewire\Perfiles::class)->name('usuarios.index');
    Route::get('/usuarios/{perfil}', App\Livewire\PanelPerfil::class)->name('usuarios.show');
});
