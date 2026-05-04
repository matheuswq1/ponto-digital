<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #1e293b; background:#fff; }

    .header { background: #1e3a8a; color: #fff; padding: 12px 16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .header-title { font-size:14px; font-weight:700; }
    .header-sub { font-size:8px; opacity:0.75; margin-top:2px; }
    .header-right { text-align:right; font-size:8px; opacity:0.85; }

    .legend { display:flex; gap:12px; padding:0 16px 10px; flex-wrap:wrap; }
    .leg-item { display:flex; align-items:center; gap:4px; font-size:7.5px; color:#475569; }
    .leg-dot { width:9px; height:9px; border-radius:3px; }

    .section { margin: 0 16px 16px; }
    table { width:100%; border-collapse:collapse; }
    thead tr { background:#1e3a8a; color:#fff; }
    thead th { padding:4px 4px; font-size:7px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; text-align:center; white-space:nowrap; }
    thead th.name-col { text-align:left; padding-left:6px; min-width:90px; }
    tbody tr:nth-child(even) { background:#f8fafc; }
    tbody td { padding:3px 2px; border-bottom:1px solid #f1f5f9; text-align:center; font-size:7.5px; }
    tbody td.name-col { text-align:left; padding-left:6px; font-weight:600; color:#0f172a; }
    .cell-P  { background:#d1fae5; color:#065f46; font-weight:700; border-radius:2px; }
    .cell-F  { background:#fee2e2; color:#991b1b; font-weight:700; border-radius:2px; }
    .cell-H  { background:#ede9fe; color:#5b21b6; font-weight:700; border-radius:2px; }
    .cell-Fo { color:#94a3b8; }
    .cell-dash{ color:#cbd5e1; }
    .num-p   { color:#047857; font-weight:700; }
    .num-f   { color:#dc2626; font-weight:700; }
    tfoot tr { background:#1e293b; color:#fff; }
    tfoot td { padding:4px; font-weight:700; font-size:8px; text-align:center; }

    .footer { position:fixed; bottom:0; left:0; right:0; background:#f1f5f9;
              border-top:1px solid #e2e8f0; padding:4px 16px;
              font-size:7px; color:#94a3b8; display:flex; justify-content:space-between; }
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="header-title">RM Colaboradores — Mapa de Presença</div>
        <div class="header-sub">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
    </div>
    <div class="header-right">
        Gerado em: {{ now()->format('d/m/Y \à\s H:i') }}<br>
        {{ count($rows) }} colaborador(es) · {{ count($dates) }} dias
    </div>
</div>

<div class="legend">
    <div class="leg-item"><div class="leg-dot" style="background:#d1fae5;border:1px solid #6ee7b7"></div> P = Presente</div>
    <div class="leg-item"><div class="leg-dot" style="background:#fee2e2;border:1px solid #fca5a5"></div> F = Falta</div>
    <div class="leg-item"><div class="leg-dot" style="background:#ede9fe;border:1px solid #c4b5fd"></div> H = Feriado</div>
    <div class="leg-item"><div class="leg-dot" style="background:#f1f5f9;border:1px solid #cbd5e1"></div> Fo = Folga/Fim-sem.</div>
</div>

<div class="section">
<table>
    <thead>
        <tr>
            <th class="name-col">Colaborador</th>
            @foreach($dates as $d)
                <th>{{ \Carbon\Carbon::parse($d)->format('d') }}<br><span style="opacity:0.7">{{ ['D','S','T','Q','Q','S','S'][\Carbon\Carbon::parse($d)->dayOfWeek] }}</span></th>
            @endforeach
            <th>P</th><th>F</th><th>H</th><th>Fo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td class="name-col">{{ $row['nome'] }}<br><span style="color:#94a3b8;font-size:6.5px">{{ $row['empresa'] }}</span></td>
            @foreach($dates as $d)
                @php $s = $row['dias'][$d] ?? ''; @endphp
                @if($s === 'P')
                    <td><span class="cell-P">P</span></td>
                @elseif($s === 'F')
                    <td><span class="cell-F">F</span></td>
                @elseif($s === 'H')
                    <td><span class="cell-H">H</span></td>
                @elseif($s === 'Fo')
                    <td class="cell-Fo">Fo</td>
                @else
                    <td class="cell-dash">—</td>
                @endif
            @endforeach
            <td class="num-p">{{ $row['total_p'] }}</td>
            <td class="num-f">{{ $row['total_f'] > 0 ? $row['total_f'] : '—' }}</td>
            <td style="color:#5b21b6;font-weight:600">{{ $row['total_h'] > 0 ? $row['total_h'] : '—' }}</td>
            <td style="color:#94a3b8">{{ $row['total_fo'] }}</td>
        </tr>
        @endforeach
    </tbody>
    @php
        $tP  = array_sum(array_column($rows, 'total_p'));
        $tF  = array_sum(array_column($rows, 'total_f'));
        $tH  = array_sum(array_column($rows, 'total_h'));
        $tFo = array_sum(array_column($rows, 'total_fo'));
    @endphp
    <tfoot>
        <tr>
            <td style="text-align:left;padding-left:6px" colspan="{{ count($dates) + 1 }}">TOTAIS</td>
            <td>{{ $tP }}</td><td>{{ $tF }}</td><td>{{ $tH }}</td><td>{{ $tFo }}</td>
        </tr>
    </tfoot>
</table>
</div>

<div class="footer">
    <span>RM Colaboradores — Documento gerado automaticamente.</span>
    <span>Página <span class="pagenum"></span></span>
</div>
</body>
</html>
