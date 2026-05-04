<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1e293b; background: #fff; }

    .header { background: #1e3a8a; color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
    .header-title { font-size: 15px; font-weight: 700; letter-spacing: 0.3px; }
    .header-sub { font-size: 9px; opacity: 0.75; margin-top: 2px; }
    .header-right { text-align: right; font-size: 9px; opacity: 0.85; }

    .summary { display: flex; gap: 8px; margin: 0 18px 12px; flex-wrap: wrap; }
    .card { background: #f1f5f9; border-radius: 6px; padding: 7px 10px; flex: 1; min-width: 80px; text-align: center; }
    .card-label { font-size: 7.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
    .card-value { font-size: 13px; font-weight: 700; color: #1e3a8a; }

    .section { margin: 0 18px 18px; }
    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #1e3a8a; color: #fff; }
    thead th { padding: 5px 6px; text-align: left; font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:nth-child(odd) { background: #ffffff; }
    tbody td { padding: 4.5px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    .name { font-weight: 600; color: #0f172a; }
    .sub { color: #94a3b8; font-size: 8px; }
    .num { font-weight: 600; color: #1e3a8a; text-align: center; }
    .warn { color: #dc2626; font-weight: 600; text-align: center; }
    .amber { color: #d97706; font-weight: 600; text-align: center; }
    .violet { color: #7c3aed; font-weight: 600; text-align: center; }
    .sky { color: #0284c7; font-weight: 600; text-align: center; }
    tfoot tr { background: #1e293b; color: #fff; }
    tfoot td { padding: 5px 6px; font-weight: 700; font-size: 8.5px; }

    .footer { position: fixed; bottom: 0; left: 0; right: 0; background: #f1f5f9;
              border-top: 1px solid #e2e8f0; padding: 5px 18px;
              font-size: 7.5px; color: #94a3b8; display: flex; justify-content: space-between; }
    .page { color: #94a3b8; font-size: 7.5px; }
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="header-title">RM Colaboradores — Folha de Pagamento</div>
        <div class="header-sub">Período: {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
    </div>
    <div class="header-right">
        Gerado em: {{ now()->format('d/m/Y \à\s H:i') }}<br>
        Total de colaboradores: {{ count($rows) }}
    </div>
</div>

@php
function pdf_fmt(int $m): string {
    if ($m === 0) return '00:00';
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}
$totTrab    = array_sum(array_column($rows, 'trabalhado_min'));
$totExtra   = array_sum(array_column($rows, 'extra_min'));
$tot50      = array_sum(array_column($rows, 'extra_50_min'));
$tot100     = array_sum(array_column($rows, 'extra_100_min'));
$totNoc     = array_sum(array_column($rows, 'extra_noc_min'));
$totFalta   = array_sum(array_column($rows, 'falta_min'));
$totDiasT   = array_sum(array_column($rows, 'dias_trabalhados'));
$totDiasF   = array_sum(array_column($rows, 'dias_falta'));
@endphp

<div class="summary">
    <div class="card"><div class="card-label">Colaboradores</div><div class="card-value">{{ count($rows) }}</div></div>
    <div class="card"><div class="card-label">Dias Trabalhados</div><div class="card-value">{{ $totDiasT }}</div></div>
    <div class="card"><div class="card-label">Dias de Falta</div><div class="card-value" style="color:#dc2626">{{ $totDiasF }}</div></div>
    <div class="card"><div class="card-label">Horas Trabalhadas</div><div class="card-value">{{ pdf_fmt($totTrab) }}</div></div>
    <div class="card"><div class="card-label">HE Total</div><div class="card-value" style="color:#d97706">{{ pdf_fmt($totExtra) }}</div></div>
    <div class="card"><div class="card-label">HE 50%</div><div class="card-value" style="color:#d97706">{{ pdf_fmt($tot50) }}</div></div>
    <div class="card"><div class="card-label">HE 100%</div><div class="card-value" style="color:#7c3aed">{{ pdf_fmt($tot100) }}</div></div>
    <div class="card"><div class="card-label">Ad. Noturno</div><div class="card-value" style="color:#0284c7">{{ pdf_fmt($totNoc) }}</div></div>
</div>

<div class="section">
<table>
    <thead>
        <tr>
            <th>Colaborador</th>
            <th>Matrícula</th>
            <th>CPF</th>
            <th>Cargo</th>
            <th>Departamento</th>
            <th style="text-align:center">Dias Trab.</th>
            <th style="text-align:center">Faltas</th>
            <th style="text-align:center">H. Trabalhadas</th>
            <th style="text-align:center">HE 50%</th>
            <th style="text-align:center">HE 100%</th>
            <th style="text-align:center">Ad. Not.</th>
            <th style="text-align:center">H. Falta</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td>
                <div class="name">{{ $row['nome'] }}</div>
                <div class="sub">{{ $row['empresa'] }}</div>
            </td>
            <td>{{ $row['matricula'] }}</td>
            <td>{{ $row['cpf'] }}</td>
            <td>{{ $row['cargo'] }}</td>
            <td>{{ $row['departamento'] }}</td>
            <td class="num">{{ $row['dias_trabalhados'] }}</td>
            <td class="warn">{{ $row['dias_falta'] > 0 ? $row['dias_falta'] : '—' }}</td>
            <td class="num">{{ pdf_fmt($row['trabalhado_min']) }}</td>
            <td class="amber">{{ $row['extra_50_min'] > 0 ? pdf_fmt($row['extra_50_min']) : '—' }}</td>
            <td class="violet">{{ $row['extra_100_min'] > 0 ? pdf_fmt($row['extra_100_min']) : '—' }}</td>
            <td class="sky">{{ $row['extra_noc_min'] > 0 ? pdf_fmt($row['extra_noc_min']) : '—' }}</td>
            <td class="warn">{{ $row['falta_min'] > 0 ? pdf_fmt($row['falta_min']) : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAIS</td>
            <td style="text-align:center">{{ $totDiasT }}</td>
            <td style="text-align:center">{{ $totDiasF }}</td>
            <td style="text-align:center">{{ pdf_fmt($totTrab) }}</td>
            <td style="text-align:center">{{ pdf_fmt($tot50) }}</td>
            <td style="text-align:center">{{ pdf_fmt($tot100) }}</td>
            <td style="text-align:center">{{ pdf_fmt($totNoc) }}</td>
            <td style="text-align:center">{{ pdf_fmt($totFalta) }}</td>
        </tr>
    </tfoot>
</table>
</div>

<div class="footer">
    <span>RM Colaboradores — Documento gerado automaticamente. Não substitui documentos legais.</span>
    <span class="page">Página <span class="pagenum"></span></span>
</div>
</body>
</html>
