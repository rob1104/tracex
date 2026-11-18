<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de colaboradores</title>
    <style>
        @page { margin: 76px 30px 42px; }

        body {
            color: #334155;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9px;
            margin: 0;
        }

        header {
            border-bottom: 2px solid #2563eb;
            height: 54px;
            left: 0;
            position: fixed;
            right: 0;
            top: -58px;
        }

        footer {
            border-top: 1px solid #e2e8f0;
            bottom: -30px;
            color: #64748b;
            font-size: 8px;
            height: 20px;
            left: 0;
            position: fixed;
            right: 0;
        }

        .brand { color: #1d4ed8; float: left; font-size: 19px; font-weight: bold; margin-top: 5px; }
        .brand small { color: #64748b; display: block; font-size: 8px; font-weight: normal; margin-top: 1px; }
        .report-meta { color: #475569; float: right; line-height: 1.5; text-align: right; }
        .report-meta h1 { color: #0f172a; font-size: 13px; margin: 0 0 3px; }
        .clear { clear: both; }

        .filters {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 10px;
            padding: 7px 9px;
        }

        .filters strong { color: #0f172a; }
        .section-heading { margin: 10px 0 6px; }
        .section-title { color: #0f172a; font-size: 11px; font-weight: bold; margin: 0 0 3px; }
        .section-subtitle { color: #64748b; font-size: 8px; }

        .coverage {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 5px;
            margin-bottom: 9px;
            padding: 7px 9px;
        }

        .coverage-heading { color: #1e3a8a; font-size: 9px; font-weight: bold; }
        .coverage-percent { color: #1d4ed8; float: right; font-size: 13px; font-weight: bold; }
        .coverage-track { background: #dbeafe; border-radius: 5px; height: 7px; margin: 6px 0 4px; overflow: hidden; }
        .coverage-fill { background: #2563eb; height: 7px; }
        .coverage-detail { color: #64748b; font-size: 8px; }

        .summary { border-spacing: 5px 0; margin: 0 -5px 10px; width: 100%; }
        .summary td { background: #eff6ff; border: 1px solid #dbeafe; border-radius: 5px; padding: 7px 8px; width: 20%; }
        .summary-label { color: #1d4ed8; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .summary-value { color: #0f172a; font-size: 13px; font-weight: bold; margin-top: 3px; }

        table.data { border-collapse: collapse; width: 100%; }
        .data th, .data td { border: 1px solid #e2e8f0; padding: 6px 5px; vertical-align: top; }
        .data th { background: #f1f5f9; color: #334155; font-size: 7px; text-align: left; text-transform: uppercase; }
        .data tbody tr:nth-child(even) { background: #f8fafc; }
        .number { text-align: right; white-space: nowrap; }
        .name { color: #0f172a; font-weight: bold; }
        .muted { color: #64748b; }
        .total-row td { background: #eff6ff; color: #0f172a; font-weight: bold; }

        .collaborator-page { page-break-before: always; }
        .collaborator-chart { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px 12px; page-break-inside: avoid; }
        .collaborator-heading { border-bottom: 1px solid #e2e8f0; margin-bottom: 10px; padding-bottom: 9px; }
        .collaborator-title { color: #0f172a; font-size: 15px; font-weight: bold; margin: 0 0 4px; }
        .collaborator-subtitle { color: #64748b; font-size: 9px; }
        .collaborator-stats { border-spacing: 5px 0; margin: 0 -5px 11px; width: 100%; }
        .collaborator-stats td { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px; width: 25%; }
        .collaborator-stats-label { color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .collaborator-stats-value { color: #0f172a; font-size: 13px; font-weight: bold; margin-top: 2px; }

        table.activity-chart { border-collapse: collapse; margin-top: 8px; table-layout: fixed; width: 100%; }
        .activity-chart-cell { border-bottom: 1px solid #cbd5e1; height: 112px; padding: 0 2px; vertical-align: bottom; }
        table.activity-bars { border-collapse: collapse; height: 104px; table-layout: fixed; width: 100%; }
        .activity-bars tr, .activity-bars td { height: 104px; }
        .activity-bars td { padding: 0; text-align: center; vertical-align: bottom; }
        .bar { border-radius: 3px 3px 0 0; margin: 0 auto; width: 5px; }
        .bar-comments { background: #3b82f6; }
        .bar-reactions { background: #f43f5e; }
        .bar-shares { background: #10b981; }
        .activity-label { color: #64748b; font-size: 6px; padding: 4px 0 0; text-align: center; }
        .collaborator-empty { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 4px; color: #64748b; margin-top: 10px; padding: 24px 12px; text-align: center; }
        .legend { color: #64748b; font-size: 8px; margin-top: 8px; }
        .legend span { margin-right: 12px; }
        .legend i { display: inline-block; height: 7px; margin-right: 3px; width: 7px; }
        .page-number:before { content: "Pagina " counter(page) " de " counter(pages); }
    </style>
</head>
<body>
    <header>
        <div class="brand">TraceX<small>Monitoreo de Facebook</small></div>
        <div class="report-meta">
            <h1>Actividad de colaboradores</h1>
            <div>Generado por {{ $generator->name }} · {{ now()->translatedFormat('d M Y, h:i A') }}</div>
        </div>
        <div class="clear"></div>
    </header>

    <footer>
        <span>TraceX · Reporte de actividad de colaboradores</span>
        <span style="float: right" class="page-number"></span>
    </footer>

    <main>
        <div class="section-heading">
            <div class="section-title">Colaboradores</div>
            <div class="section-subtitle">{{ $collaboratorName }} · {{ $chartRangeLabel }} · {{ $chartGroupingLabel }} · Cuentas con actividad: {{ $activeAccountsCount }}/{{ $totalAccountsCount }} ({{ $activityCoveragePercent }}%)</div>
        </div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 18%">Colaborador</th>
                    <th style="width: 27%">Cuenta asociada</th>
                    <th class="number" style="width: 13%">Perfiles trabajados<div class="muted">con actividad / asignados</div></th>
                    <th class="number" style="width: 10%">Comentarios</th>
                    <th class="number" style="width: 10%">Reacciones</th>
                    <th class="number" style="width: 10%">Compartidos</th>
                    <th class="number" style="width: 12%">Actividad total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($collaboratorCharts as $chart)
                    @php
                        $visibleAccountEmails = array_slice($chart['accountEmails'], 0, 2);
                        $remainingAccountCount = count($chart['accountEmails']) - count($visibleAccountEmails);
                    @endphp
                    <tr>
                        <td><div class="name">{{ $chart['collaborator']->name }}</div></td>
                        <td>
                            {{ $visibleAccountEmails !== [] ? implode(', ', $visibleAccountEmails) : 'Sin cuenta asociada' }}
                            @if($remainingAccountCount > 0)
                                <div class="muted">+{{ $remainingAccountCount }} cuentas más</div>
                            @endif
                        </td>
                        <td class="number">{{ number_format($chart['activeProfileCount']) }}/{{ number_format($chart['profileCount']) }}</td>
                        <td class="number">{{ number_format($chart['totals']['comments']) }}</td>
                        <td class="number">{{ number_format($chart['totals']['reactions']) }}</td>
                        <td class="number">{{ number_format($chart['totals']['shares']) }}</td>
                        <td class="number">{{ number_format($chart['totals']['activity']) }}</td>
                    </tr>
                @empty
                    <tr><td class="muted" colspan="7" style="padding: 18px; text-align: center">No hay colaboradores que coincidan con los filtros aplicados.</td></tr>
                @endforelse
                @if($collaboratorCharts->isNotEmpty())
                    <tr class="total-row">
                        <td colspan="2">Totales del periodo</td>
                        <td class="number">{{ number_format($activeAccountsCount) }}/{{ number_format($totalAccountsCount) }}</td>
                        <td class="number">{{ number_format($totals['comments']) }}</td>
                        <td class="number">{{ number_format($totals['reactions']) }}</td>
                        <td class="number">{{ number_format($totals['shares']) }}</td>
                        <td class="number">{{ number_format($totals['activity']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
        @forelse($collaboratorCharts as $chart)
            <section class="collaborator-page">
                <div class="collaborator-chart">
                    <div class="collaborator-heading">
                        <h2 class="collaborator-title">{{ $chart['collaborator']->name }}</h2>
                        <div class="collaborator-subtitle">{{ $chart['profileCount'] }} perfiles · {{ $chartRangeLabel }} · {{ $chartGroupingLabel }}</div>
                    </div>
                    <table class="collaborator-stats">
                        <tr>
                            <td><div class="collaborator-stats-label">Actividad total</div><div class="collaborator-stats-value">{{ number_format($chart['totals']['activity']) }}</div></td>
                            <td><div class="collaborator-stats-label">Comentarios</div><div class="collaborator-stats-value">{{ number_format($chart['totals']['comments']) }}</div></td>
                            <td><div class="collaborator-stats-label">Reacciones</div><div class="collaborator-stats-value">{{ number_format($chart['totals']['reactions']) }}</div></td>
                            <td><div class="collaborator-stats-label">Compartidos</div><div class="collaborator-stats-value">{{ number_format($chart['totals']['shares']) }}</div></td>
                        </tr>
                    </table>

                    @if($chart['totals']['activity'] > 0)
                        <table class="activity-chart">
                            <tbody>
                                <tr>
                                    @foreach($chart['buckets'] as $bucket)
                                        @php
                                            $commentsHeight = $bucket['comments'] > 0 ? max(2, (int) round(($bucket['comments'] / $chart['max']) * 96)) : 0;
                                            $reactionsHeight = $bucket['reactions'] > 0 ? max(2, (int) round(($bucket['reactions'] / $chart['max']) * 96)) : 0;
                                            $sharesHeight = $bucket['shares'] > 0 ? max(2, (int) round(($bucket['shares'] / $chart['max']) * 96)) : 0;
                                        @endphp
                                        <td class="activity-chart-cell">
                                            <table class="activity-bars"><tr height="104" style="height: 104px">
                                                <td height="104" valign="bottom" style="height: 104px; vertical-align: bottom"><div class="bar bar-comments" style="height: {{ $commentsHeight }}px"></div></td>
                                                <td height="104" valign="bottom" style="height: 104px; vertical-align: bottom"><div class="bar bar-reactions" style="height: {{ $reactionsHeight }}px"></div></td>
                                                <td height="104" valign="bottom" style="height: 104px; vertical-align: bottom"><div class="bar bar-shares" style="height: {{ $sharesHeight }}px"></div></td>
                                            </tr></table>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach($chart['buckets'] as $bucket)
                                        <td class="activity-label">{{ $bucket['label'] }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                        <div class="legend">
                            <span><i style="background: #3b82f6"></i>Comentarios</span>
                            <span><i style="background: #f43f5e"></i>Reacciones</span>
                            <span><i style="background: #10b981"></i>Compartidos</span>
                        </div>
                    @else
                        <div class="collaborator-empty">Este colaborador no registró actividad durante el periodo seleccionado.</div>
                    @endif
                </div>
            </section>
        @empty
            <section class="collaborator-page">
                <div class="collaborator-empty">No hay colaboradores asociados a perfiles que coincidan con los filtros.</div>
            </section>
        @endforelse
    </main>
</body>
</html>
