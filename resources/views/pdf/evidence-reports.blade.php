<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de evidencias por colaborador - TraceX</title>
    <style>
        @page { margin: 34px 38px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 9px; }
        header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 18px; }
        h1 { color: #0f766e; font-size: 20px; margin: 0 0 4px; }
        h2 { color: #334155; font-size: 13px; margin: 0; }
        .meta { color: #64748b; font-size: 8px; line-height: 1.6; }
        .summary { width: 100%; margin: 12px 0 16px; border-collapse: collapse; }
        .summary td { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px; width: 50%; }
        .filters { margin: 10px 0 14px; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; line-height: 1.6; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th, .data td { border: 1px solid #cbd5e1; padding: 7px 6px; text-align: left; }
        .data th { background: #e2e8f0; color: #0f172a; text-transform: uppercase; font-size: 8px; }
        .data td.count { text-align: right; }
        .data tr:nth-child(even) { background: #f8fafc; }
        tfoot td { background: #ecfdf5; font-weight: bold; }
        footer { position: fixed; bottom: -24px; left: 0; right: 0; color: #64748b; font-size: 8px; border-top: 1px solid #cbd5e1; padding-top: 5px; }
        .page:after { content: "Página " counter(page) " de " counter(pages); float: right; }
    </style>
</head>
<body>
    <header>
        <h1>TraceX</h1>
        <h2>Reporte de evidencias por colaborador</h2>
        <div class="meta">Generado por {{ $generator->name }} ({{ $generator->email }}) · {{ now()->format('d/m/Y H:i') }}</div>
    </header>

    <table class="summary">
        <tr>
            <td><strong>Total de evidencias:</strong> {{ number_format($totalEvidence) }}</td>
            <td><strong>Colaboradores incluidos:</strong> {{ number_format($collaborators->count()) }}</td>
        </tr>
    </table>

    <div class="filters">
        <strong>Filtros aplicados</strong><br>
        <strong>Desde:</strong> {{ $filters['dateFrom'] }} &nbsp;&nbsp;
        <strong>Hasta:</strong> {{ $filters['dateTo'] }} &nbsp;&nbsp;
        <strong>Colaboradores:</strong> {{ $filters['collaborators'] }} &nbsp;&nbsp;
        <strong>Búsqueda:</strong> {{ $filters['search'] }}
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Correo</th>
                <th>Total evidencias</th>
                <th>Primera captura</th>
                <th>Última captura</th>
            </tr>
        </thead>
        <tbody>
            @forelse($collaborators as $collaborator)
                <tr>
                    <td>{{ $collaborator->name }}</td>
                    <td>{{ $collaborator->email }}</td>
                    <td class="count">{{ number_format($collaborator->evidence_count) }}</td>
                    <td>{{ $collaborator->first_evidence_at ? \Illuminate\Support\Carbon::parse($collaborator->first_evidence_at)->format('d/m/Y H:i') : '—' }}</td>
                    <td>{{ $collaborator->last_evidence_at ? \Illuminate\Support\Carbon::parse($collaborator->last_evidence_at)->format('d/m/Y H:i') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center">No hay colaboradores que coincidan con los filtros.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="2">Total de evidencias en el reporte</td><td class="count">{{ number_format($totalEvidence) }}</td><td colspan="2"></td></tr>
        </tfoot>
    </table>

    <footer><span>TraceX · Reporte interno de evidencias</span><span class="page"></span></footer>
</body>
</html>
