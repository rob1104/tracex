<?php

namespace App\Livewire;

use App\Models\Importacion;
use App\Models\Perfil;
use App\Services\AsignadorImportaciones;
use App\Support\IdentidadFacebook;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla principal de usuarios monitoreados:
 *  - registrar perfiles con el enlace de su perfil de Facebook
 *  - tarjetas de los perfiles (cada una abre el panel de actividad)
 *  - cargas que necesitan revisión manual
 *  - historial de cargas
 */
#[Layout('layouts.app')]
class Perfiles extends Component
{
    public string $nuevoAlias = '';
    public string $nuevoEnlace = '';

    /** perfil elegido por cada importación pendiente: [importacion_id => perfil_id] */
    public array $seleccion = [];

    public function agregarPerfil(AsignadorImportaciones $asignador): void
    {
        $this->validate([
            'nuevoAlias'  => 'required|string|max:60',
            'nuevoEnlace' => 'required|string|max:300',
        ], [], ['nuevoAlias' => 'nombre', 'nuevoEnlace' => 'enlace']);

        $normalizado = IdentidadFacebook::normalizarEnlace($this->nuevoEnlace);

        if (! $normalizado) {
            throw ValidationException::withMessages([
                'nuevoEnlace' => 'Pega el enlace del perfil, por ejemplo facebook.com/sofia.perez o facebook.com/profile.php?id=1000…',
            ]);
        }

        $hashUrl = IdentidadFacebook::huella($normalizado);

        if (Perfil::where('hash_url', $hashUrl)->exists()) {
            throw ValidationException::withMessages(['nuevoEnlace' => 'Ese perfil de Facebook ya está registrado.']);
        }

        $perfil = Perfil::create([
            'user_id'  => auth()->id(),
            'alias'    => $this->nuevoAlias,
            'url_fb'   => $this->nuevoEnlace,
            'hash_url' => $hashUrl,
        ]);

        // Si su exportación llegó antes de registrarlo, se le asigna ahora
        $reclamadas = $asignador->reclamarSinDueno($perfil);

        $this->reset('nuevoAlias', 'nuevoEnlace');
        session()->flash('ok', $reclamadas
            ? "{$perfil->alias} agregado. Se encontraron {$reclamadas} cargas suyas que ya habían llegado."
            : "{$perfil->alias} agregado. Sus exportaciones se asignarán solas al llegar al Drive.");
    }

    public function asignar(string $importacionId, AsignadorImportaciones $asignador): void
    {
        $imp = Importacion::whereIn('estado', ['sin_dueno', 'pendiente'])->findOrFail($importacionId);
        $perfil = Perfil::find($this->seleccion[$importacionId] ?? null);

        if (! $perfil) {
            throw ValidationException::withMessages(["seleccion.$importacionId" => 'Elige a qué perfil pertenece.']);
        }

        try {
            $resultado = $asignador->asignar($imp, $perfil);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(["seleccion.$importacionId" => $e->errors()['perfil'][0]]);
        }

        unset($this->seleccion[$importacionId]);
        session()->flash('ok', $resultado['nuevos'] === 0
            ? "Todo lo de esta carga ya estaba registrado en {$perfil->alias}; no se agregó nada."
            : "Carga asignada a {$perfil->alias}: {$resultado['nuevos']} registros nuevos, {$resultado['omitidos']} ya existían.");
    }

    public function descartar(string $importacionId): void
    {
        Importacion::whereIn('estado', ['sin_dueno', 'pendiente', 'rechazado', 'error'])
            ->findOrFail($importacionId)
            ->delete(); // borra también sus filas por cascada
    }

    public function render()
    {
        // Instalación local: todos los usuarios ven todos los perfiles
        return view('livewire.perfiles', [
            'perfiles' => Perfil::withCount(['comentarios', 'reacciones', 'compartidos'])
                ->orderBy('alias')
                ->get(),
            'pendientes' => Importacion::whereIn('estado', ['sin_dueno', 'pendiente', 'rechazado', 'error'])
                ->latest('recibido_en')
                ->get(),
            'historial' => Importacion::with('perfil')
                ->whereIn('estado', ['asignado', 'duplicado'])
                ->latest('updated_at')
                ->take(10)
                ->get(),
            'correoDestino' => config('services.google.correo_destino'),
        ]);
    }
}
