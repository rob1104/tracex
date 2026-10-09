<?php

namespace App\Livewire;

use App\Models\Perfil;
use App\Models\Reaccion;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PanelPerfil extends Component
{
    use WithPagination;

    public Perfil $perfil;

    #[Url] public string $seccion = 'comentarios';
    #[Url] public int $dias = 30;

    public function mount(Perfil $perfil): void
    {
        // Instalación local: cualquier usuario autenticado ve todos los perfiles registrados
        $this->perfil = $perfil;
    }

    public function updatedSeccion(): void { $this->resetPage(); }
    public function updatedDias(): void    { $this->resetPage(); }

    private function desde()
    {
        return now()->subDays($this->dias);
    }

    private function resumen(): array
    {
        $p = $this->perfil;
        $d = $this->desde();

        return [
            'comentarios' => $p->comentarios()->where('fecha', '>=', $d)->count(),
            'reacciones'  => $p->reacciones()->where('fecha', '>=', $d)->count(),
            'compartidos' => $p->compartidos()->where('fecha', '>=', $d)->count(),
        ];
    }

    private function actividadPorHora(): array
    {
        $filas = $this->perfil->comentarios()
            ->where('fecha', '>=', $this->desde())
            ->select('hora_local', DB::raw('COUNT(*) as total'))
            ->groupBy('hora_local')
            ->pluck('total', 'hora_local');

        return collect(range(0, 23))->map(fn ($h) => (int) ($filas[$h] ?? 0))->all();
    }

    private function reaccionesPorTipo(): array
    {
        return $this->perfil->reacciones()
            ->where('fecha', '>=', $this->desde())
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'etiqueta' => Reaccion::ETIQUETAS[$r->tipo] ?? $r->tipo,
                'total'    => $r->total,
            ])
            ->all();
    }

    private function listado()
    {
        $d = $this->desde();

        return match ($this->seccion) {
            'reacciones'  => $this->perfil->reacciones()->where('fecha', '>=', $d)->latest('fecha')->paginate(15),
            'compartidos' => $this->perfil->compartidos()->where('fecha', '>=', $d)->latest('fecha')->paginate(15),
            default       => $this->perfil->comentarios()->where('fecha', '>=', $d)->latest('fecha')->paginate(15),
        };
    }

    public function render()
    {
        return view('livewire.panel-perfil', [
            'resumen'    => $this->resumen(),
            'porHora'    => $this->actividadPorHora(),
            'porTipo'    => $this->reaccionesPorTipo(),
            'registros'  => $this->listado(),
            'etiquetas'  => Reaccion::ETIQUETAS,
        ]);
    }
}
