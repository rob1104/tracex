<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Usuarios</title>
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
            width: 50%;
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
        .col-name { width: 25%; }
        .col-email { width: 25%; }
        .col-role { width: 15%; text-align: center; }
        .col-date { width: 20%; text-align: center; }
        .col-status { width: 10%; text-align: center; }

        .name-text { font-weight: bold; color: #111827; }
        
        .badge-active {
            color: #15803d;
            font-weight: bold;
        }
        .badge-inactive {
            color: #b91c1c;
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
            <span>Administración</span>
        </div>
        <div class="header-title">
            <h2>Catálogo de Usuarios del Sistema</h2>
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
                    <strong>Búsqueda:</strong> {{ $filters['search'] }}
                </div>
                <div class="filter-item">
                    <strong>Total Registros:</strong> {{ $users->count() }}
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
                    <th class="col-email">Correo Electrónico</th>
                    <th class="col-role">Rol en el Sistema</th>
                    <th class="col-date">Fecha de Registro</th>
                    <th class="col-status">Estatus</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="col-id">{{ $user->id }}</td>
                        <td class="col-name">
                            <div class="name-text">{{ $user->name }}</div>
                        </td>
                        <td class="col-email">{{ $user->email }}</td>
                        <td class="col-role">{{ ucfirst($user->role) }}</td>
                        <td class="col-date">{{ $user->created_at->translatedFormat('d M Y, h:i A') }}</td>
                        <td class="col-status">
                            @if($user->is_active)
                                <span class="badge-active">Activo</span>
                            @else
                                <span class="badge-inactive">Inactivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 20px; color: #6b7280;">No hay usuarios que coincidan con la búsqueda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </main>

</body>
</html>
