<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Perfiles Sociales</title>
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
        .col-name { width: 15%; }
        .col-net { width: 10%; }
        .col-email { width: 15%; }
        .col-gestor { width: 12%; }
        .col-users { width: 18%; }
        .col-date { width: 15%; text-align: center; }
        .col-status { width: 10%; text-align: center; }

        .name-text { font-weight: bold; color: #111827; }
        
        .badge-active {
            color: #15803d;
            font-weight: bold;
        }
        .badge-suspended {
            color: #b91c1c;
            font-weight: bold;
        }
        .badge-restricted {
            color: #ca8a04;
            font-weight: bold;
        }

        .clear { clear: both; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header>
        <div class="header-logo">
            <h1>TraceX</h1>
            <span>Auditoría de Perfiles</span>
        </div>
        <div class="header-title">
            <h2>Catálogo de Perfiles Sociales</h2>
            <p><strong>Generado por:</strong> {{ $generator->name }} ({{ $generator->email }})</p>
            <p><strong>Fecha y Hora:</strong> {{ now()->translatedFormat('d M Y, h:i A') }}</p>
        </div>
        <div class="clear"></div>
    </header>

    <!-- FOOTER -->
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
                    <strong>Cuenta de Correo:</strong> {{ $filters['email'] ?? 'Todas' }}<br>
                    <strong>Red Social:</strong> {{ $filters['network'] }}
                </div>
                <div class="filter-item">
                    <strong>Estatus:</strong> {{ $filters['status'] }}<br>
                    <strong>Total Registros:</strong> {{ $profiles->count() }}
                </div>
            </div>
            <div class="clear"></div>
        </div>

        <!-- DATA TABLE -->
        <table>
            <thead>
                <tr>
                    <th class="col-id">#</th>
                    <th class="col-name">Nombre</th>
                    <th class="col-net">Red Social</th>
                    <th class="col-email">Cuenta Origen</th>
                    <th class="col-gestor">Gestor</th>
                    <th class="col-users">Colaboradores</th>
                    <th class="col-date">Fecha Reg.</th>
                    <th class="col-status">Estatus</th>
                </tr>
            </thead>
            <tbody>
                @forelse($profiles as $profile)
                    <tr>
                        <td class="col-id">{{ $profile->id }}</td>
                        <td class="col-name">
                            <div class="name-text">{{ $profile->name }}</div>
                        </td>
                        <td class="col-net">{{ $profile->social_network }}</td>
                        <td class="col-email">{{ $profile->emailAccount->email ?? 'N/A' }}</td>
                        <td class="col-gestor">{{ $profile->creator->name ?? 'N/A' }}</td>
                        <td class="col-users">{{ $profile->users->pluck('name')->implode(', ') }}</td>
                        <td class="col-date">{{ $profile->created_at->translatedFormat('d M Y, h:i A') }}</td>
                        <td class="col-status">
                            @if($profile->status === 'active')
                                <span class="badge-active">Activo</span>
                            @elseif($profile->status === 'restricted')
                                <span class="badge-restricted">Restringido</span>
                            @else
                                <span class="badge-suspended">Suspendido</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 20px; color: #6b7280;">No hay perfiles que coincidan con los filtros aplicados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>
