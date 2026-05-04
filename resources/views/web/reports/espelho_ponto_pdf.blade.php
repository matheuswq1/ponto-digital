<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size:9px; color:#1e293b; }

    .header { background:#1e3a8a; color:#fff; padding:14px 18px; margin-bottom:14px; }
    .header-title { font-size:14px; font-weight:700; }
    .header-meta { font-size:8px; opacity:0.75; margin-top:4px; display:flex; gap:20px; }
    .header-right { float:right; text-align:right; font-size:8px; opacity:0.85; }

    .emp-card { margin:0 18px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; display:flex; gap:24px; }
    .emp-field { font-size:8px; }
    .emp-label { color:#94a3b8; text-transform:uppercase; font-size:7px; letter-spacing:0.4px; margin-bottom:2px; }
    .emp-value { font-weight:600; color:#0f172a; }

    .section { margin:0 18px 18px; }
    table { width:100%; border-collapse:collapse; }
    thead tr { background:#334155; color:#fff; }
    thead th { padding:5px 6px; font-size:8px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; text-align:left; }
    thead th.center { text-align:center; }
    tbody tr.work:nth-child(even) { background:#f8fafc; }
    tbody tr.work:nth-child(odd)  { background:#ffffff; }
    tbody tr.holiday { background:#faf5ff; }
    tbody tr.weekend { background:#f8fafc; }
    tbody tr.absent  { background:#fff5f5; }
    tbody td { padding:4px 6px; border-bottom:1px solid #f1f5f9; font-size:8.5px; }
    tbody td.center { text-align:center; }
    .badge-P  { background:#d1fae5; color:#065f46; padding:1px 5px; border-radius:3px; font-weight:700; font-size:7.5px; }
    .badge-F  { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-weight:700; font-size:7.5px; }
    .badge-H  { background:#ede9fe; color:#5b21b6; padding:1px 5px; border-radius:3px; font-weight:700; font-size:7.5px; }
    .badge-Fo { background:#f1f5f9; color:#94a3b8; padding:1px 5px; border-radius:3px; font-weight:700; font-size:7.5px; }
    .plus  { color:#047857; font-weight:700; }
    .minus { color:#dc2626; font-weight:700; }

    tfoot tr { background:#1e293b; color:#fff; }
    tfoot td { padding:5px 6px; font-weight:700; font-size:8.5px; }

    .footer { position:fixed; bottom:0; left:0; right:0; background:#f8fafc;
              border-top:1px solid #e2e8f0; padding:5px 18px;
              font-size:7px; color:#94a3b8; display:flex; justify-content:space-between; }
</style>
</head>
<body>

<div class="header">
    <div class="header-right">
        Gerado em: {{ now()->format('d/m/Y \à\s H:i') }}
    </div>
    <div class="header-title">RM Colaboradores — Espelho de Ponto</div>
    <div class="header-meta">
        <span>Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</span>
        <span>Empresa: {{ $emp->company?->name ?? '—' }}</span>
    </div>
</div>

<div class="emp-card">
    <div class="emp-field"><div class="emp-label">Colaborador</div><div class="emp-value">{{ $emp->user?->name ?? '—' }}</div></div>
    <div class="emp-field"><div class="emp-label">Matrícula</div><div class="emp-value">{{ $emp->registration_number ?? '—' }}</div></div>
    <div class="emp-field"><div class="emp-label">CPF</div><div class="emp-value">{{ $emp->cpf ?? '—' }}</div></div>
    <div class="emp-field"><div class="emp-label">Cargo</div><div class="emp-value">{{ $emp->cargo ?? '—' }}</div></div>
    <div class="emp-field"><div class="emp-label">Departamento</div><div class="emp-value">{{ $emp->dept?->name ?? $emp->department ?? '—' }}</div></div>
    <div class="emp-field"><div class="emp-label">PIS</div><div class="emp-value">{{ $emp->pis ?? '—' }}</div></div>
</div>

<div class="section">
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th class="center">Entradas</th>
            <th class="center">Saídas</th>
            <th class="center">Trabalhado</th>
            <th class="center">Esperado</th>
            <th class="center">Diferença</th>
            <th class="center">Status</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totWorked = 0;
            $totDiff   = 0;
            $totPresent= 0;
            $totAbsent = 0;
        @endphp
        @foreach($rows as $row)
        @php
            $trClass = match($row['status']) {
                'Feriado'  => 'holiday',
                'Folga'    => 'weekend',
                'Falta'    => 'absent',
                default    => 'work',
            };
            if($row['status'] === 'Presente') { $totWorked += $row['worked_m']; $totPresent++; }
            if($row['status'] === 'Falta') $totAbsent++;
            $totDiff += $row['diff_m'];
        @endphp
        <tr class="{{ $trClass }}">
            <td>{{ $row['date_fmt'] }}</td>
            <td class="center">{{ $row['entries']->implode('  ') ?: '—' }}</td>
            <td class="center">{{ $row['exits']->implode('  ') ?: '—' }}</td>
            <td class="center">{{ $row['worked'] !== '00:00' ? $row['worked'] : '—' }}</td>
            <td class="center" style="color:#64748b">
                {{ in_array($row['status'], ['Folga','Feriado','Futuro']) ? '—' : $row['expected'] }}
            </td>
            <td class="center">
                @if($row['diff'] !== '—')
                    <span class="{{ str_starts_with($row['diff'],'+') ? 'plus' : 'minus' }}">{{ $row['diff'] }}</span>
                @else —
                @endif
            </td>
            <td class="center">
                @php
                    $bc = match($row['status']) { 'Presente'=>'badge-P','Falta'=>'badge-F','Feriado'=>'badge-H', default=>'badge-Fo' };
                @endphp
                <span class="{{ $bc }}">{{ $row['status'] }}</span>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">TOTAIS — {{ $totPresent }} dia(s) presente · {{ $totAbsent }} falta(s)</td>
            <td style="text-align:center">{{ $fmtMin($totWorked) }}</td>
            <td></td>
            <td style="text-align:center">
                <span style="color:{{ $totDiff >= 0 ? '#34d399' : '#f87171' }}">
                    {{ ($totDiff >= 0 ? '+' : '-').$fmtMin(abs($totDiff)) }}
                </span>
            </td>
            <td></td>
        </tr>
    </tfoot>
</table>
</div>

<div class="footer">
    <span>RM Colaboradores — Espelho de Ponto. Documento gerado automaticamente. Não substitui o espelho assinado.</span>
    <span>Página <span class="pagenum"></span></span>
</div>
</body>
</html>
