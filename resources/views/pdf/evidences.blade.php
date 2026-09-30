<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Evidencias - TraceX</title>
    <style>
        @page {
            margin: 120px 40px 60px 40px; /* Top, Right, Bottom, Left */
        }
        
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }

        /* HEADER AND FOOTER ABSOLUTE POSITIONING */
        header {
            position: fixed;
            top: -90px;
            left: 0;
            right: 0;
            height: 70px;
            border-bottom: 2px solid #4f46e5;
        }

        footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 9px;
            text-align: center;
        }
        
        /* DOMPDF Page Number Injection */
        .page-number:before {
            content: "Página " counter(page) " de " counter(pages);
        }

        /* HEADER CONTENT */
        .header-logo {
            float: left;
            width: 30%;
        }
        .header-logo h1 {
            color: #4f46e5;
            font-size: 24px;
            margin: 0;
            font-weight: 900;
            letter-spacing: -0.5px;
        }
        .header-logo span {
            color: #6b7280;
            font-size: 10px;
            font-weight: normal;
            display: block;
            margin-top: -2px;
        }

        .header-title {
            float: right;
            width: 70%;
            text-align: right;
        }
        .header-title h2 {
            font-size: 16px;
            color: #111827;
            margin: 0;
            text-transform: uppercase;
        }
        .header-title p {
            margin: 4px 0 0 0;
            font-size: 10px;
            color: #4b5563;
        }

        /* FILTERS SECTION */
        .filters-section {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
        }
        .filters-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 6px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .filters-list {
            display: table;
            width: 100%;
        }
        .filter-item {
            display: table-cell;
            width: 33%;
            font-size: 9px;
            color: #475569;
        }
        .filter-item strong {
            color: #0f172a;
        }

        /* TABLE */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px 6px;
            vertical-align: top;
        }
        th {
            background-color: #f3f4f6;
            color: #111827;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            text-align: left;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .text-center { text-align: center; }
        
        .col-id { width: 4%; text-align: center;}
        .col-user { width: 18%; }
        .col-net { width: 10%; }
        .col-profile { width: 14%; }
        .col-type { width: 10%; }
        .col-date { width: 8%; }
        .col-comment { width: 36%; }

        .user-name { font-weight: bold; color: #111827; }
        .user-email { color: #6b7280; font-size: 9px; }
        .comment-text { font-size: 9px; color: #4b5563; }
        
        .badge-suspect {
            color: #b91c1c;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
        }

        .clear { clear: both; }
    </style>
</head>
<body>

    <!-- HEADER (Repeated on every page) -->
    <header>
        <div class="header-logo">
            <h1>TraceX</h1>
            <span>Auditoría de Evidencias</span>
        </div>
        <div class="header-title">
            <h2>Reporte General de Evidencias</h2>
            <p><strong>Generado por:</strong> {{ $generator->name }} ({{ $generator->email }})</p>
            <p><strong>Fecha y Hora:</strong> {{ now()->translatedFormat('d M Y, h:i A') }}</p>
        </div>
        <div class="clear"></div>
    </header>

    <!-- FOOTER (Repeated on every page) -->
    <footer>
        <div style="float: left;">TraceX - Sistema Interno de Marketing Digital</div>
        <div style="float: right;" class="page-number"></div>
        <div class="clear"></div>
    </footer>

    <!-- MAIN CONTENT -->
    <main>
        <!-- APPLIED FILTERS INFO -->
        <div class="filters-section">
            <div class="filters-title">Criterios del Reporte (Filtros Aplicados)</div>
            <div class="filters-list">
                <div class="filter-item">
                    <strong>Búsqueda (Usuario):</strong> {{ $filters['searchUser'] ?: 'Todos' }}<br>
                    <strong>Tipo de Evidencia:</strong> {{ $filters['filterType'] ?: 'Todos' }}
                </div>
                <div class="filter-item">
                    <strong>Rango Desde:</strong> {{ $filters['filterDateFrom'] ? \Carbon\Carbon::parse($filters['filterDateFrom'])->format('d/m/Y') : 'Sin límite' }}<br>
                    <strong>Rango Hasta:</strong> {{ $filters['filterDateTo'] ? \Carbon\Carbon::parse($filters['filterDateTo'])->format('d/m/Y') : 'Sin límite' }}
                </div>
                <div class="filter-item">
                    <strong>Sospechosas:</strong> {{ $filters['filterSuspect'] ? 'Sí (Solo duplicados)' : 'Todas' }}<br>
                    <strong>Total Registros:</strong> {{ $evidences->count() }}
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <!-- DATA TABLE -->
        <table>
            <thead>
                <tr>
                    <th class="col-id">#</th>
                    <th class="col-user">Colaborador</th>
                    <th class="col-net">Red Social</th>
                    <th class="col-profile">Perfil</th>
                    <th class="col-type">Tipo</th>
                    <th class="col-date">Fecha Reg.</th>
                    <th class="col-comment">Comentario / Detalles</th>
                </tr>
            </thead>
            <tbody>
                @forelse($evidences as $evidence)
                    @php
                        $isSuspect = $evidence->images->contains('is_suspect', true);
                    @endphp
                    <tr>
                        <td class="col-id">{{ $evidence->id }}</td>
                        <td class="col-user">
                            <div class="user-name">{{ $evidence->user->name }}</div>
                            <div class="user-email">{{ $evidence->user->email }}</div>
                        </td>
                        <td class="col-net">{{ $evidence->social_network }}</td>
                        <td class="col-profile">{{ $evidence->profile ? $evidence->profile->name : 'N/A' }}</td>
                        <td class="col-type">
                            {{ optional($evidence->evidenceType)->name }}
                            @if($isSuspect)
                                <br><span class="badge-suspect">⚠️ Sospechosa</span>
                            @endif
                        </td>
                        <td class="col-date">{{ $evidence->created_at->format('d/m/Y') }}</td>
                        <td class="col-comment">
                            <div class="comment-text">{{ $evidence->comment ?: 'Sin comentarios.' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px; color: #6b7280;">No hay evidencias que coincidan con los filtros aplicados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>
