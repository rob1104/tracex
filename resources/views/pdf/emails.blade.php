<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cuentas de Correo</title>
    <style>
        /* BASE STYLES */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            font-size: 10px;
            margin-top: 100px;
            margin-bottom: 50px;
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
        
        .col-id { width: 5%; text-align: center;}
        .col-alias { width: 15%; }
        .col-email { width: 25%; }
        .col-profiles { width: 10%; text-align: center; }
        .col-gestor { width: 15%; }
        .col-status { width: 10%; text-align: center; }
        .col-date { width: 20%; text-align: center; }

        .email-text { font-weight: bold; color: #111827; }
        .alias-text { color: #6b7280; font-size: 9px; }
        
        .badge-active {
            color: #15803d;
            font-weight: bold;
        }
        .badge-suspended {
            color: #b91c1c;
            font-weight: bold;
        }

        .clear { clear: both; }
    </style>
</head>
<body>

    <!-- HEADER (Repeated on every page) -->
    <header>
        <div class="header-logo">
            <h1>TraceX</h1>
            <span>Auditoría de Cuentas</span>
        </div>
        <div class="header-title">
            <h2>Catálogo de Cuentas de Correo</h2>
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
                    <strong>Búsqueda:</strong> {{ $filters['search'] ?: 'Todas' }}<br>
                    <strong>Gestor Asignado:</strong> {{ $filters['gestor'] ?? 'Todos' }}
                </div>
                <div class="filter-item">
                    <strong>Estatus:</strong> {{ $filters['status'] }}<br>
                    <strong>Vinculación:</strong> {{ $filters['profiles'] }}
                </div>
                <div class="filter-item">
                    <strong>Total Registros:</strong> {{ $accounts->count() }}
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <!-- DATA TABLE -->
        <table>
            <thead>
                <tr>
                    <th class="col-id">#</th>
                    <th class="col-alias">Alias</th>
                    <th class="col-email">Correo Electrónico</th>
                    <th class="col-profiles">Perfiles</th>
                    <th class="col-gestor">Gestor Asignado</th>
                    <th class="col-date">Fecha de Creación</th>
                    <th class="col-status">Estatus</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td class="col-id">{{ $account->id }}</td>
                        <td class="col-alias">
                            <div class="alias-text">{{ $account->alias ?: 'Sin Alias' }}</div>
                        </td>
                        <td class="col-email">
                            <div class="email-text">{{ $account->email }}</div>
                        </td>
                        <td class="col-profiles text-center">{{ $account->profiles->count() }}</td>
                        <td class="col-gestor">{{ $account->creator->name ?? 'N/A' }}</td>
                        <td class="col-date">{{ $account->created_at->translatedFormat('d M Y, h:i A') }}</td>
                        <td class="col-status">
                            @if($account->status === 'active')
                                <span class="badge-active">Activa</span>
                            @else
                                <span class="badge-suspended">Suspendida</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px; color: #6b7280;">No hay cuentas que coincidan con los filtros aplicados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>
