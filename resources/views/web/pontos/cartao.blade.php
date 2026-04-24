<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartão Ponto</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            background: #e5e7eb;
            color: #111;
        }

        /* ── Barra de controles (não imprime) ── */
        .controls {
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .controls label { font-size: 12px; display: flex; align-items: center; gap: 6px; }
        .controls input, .controls select {
            padding: 5px 8px; border-radius: 6px; border: none; font-size: 12px;
            background: #334155; color: #fff;
        }
        .controls button {
            padding: 7px 18px; border-radius: 6px; border: none; cursor: pointer;
            font-size: 12px; font-weight: 600;
        }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-print:hover { background: #4338ca; }
        .btn-back  { background: #475569; color: #fff; }

        /* ── Página A4 ── */
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            padding: 8mm 10mm;
            display: flex;
            flex-direction: column;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }

        /* ── Cabeçalho ── */
        .header {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        .header-logo {
            font-size: 11px;
            font-weight: 900;
            color: #1e293b;
            border: 2px solid #1e293b;
            padding: 4px 6px;
            line-height: 1.2;
            min-width: 60px;
            text-align: center;
        }
        .header-title {
            flex: 1;
            text-align: center;
        }
        .header-title h1 {
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header-title h2 {
            font-size: 10px;
            font-weight: 700;
            color: #374151;
        }
        .header-period {
            font-size: 8px;
            text-align: right;
            min-width: 90px;
        }

        /* ── Dados do colaborador ── */
        .employee-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2px 8px;
            border: 1px solid #9ca3af;
            padding: 4px 6px;
            margin-bottom: 4px;
            background: #f8fafc;
        }
        .info-item { font-size: 8px; }
        .info-item strong { display: block; font-size: 7px; color: #6b7280; text-transform: uppercase; }

        /* Linha de horário de trabalho */
        .horario-box {
            border: 1px solid #9ca3af;
            padding: 3px 6px;
            margin-bottom: 5px;
            background: #f8fafc;
        }
        .horario-box table { width: 100%; border-collapse: collapse; }
        .horario-box td { font-size: 8px; padding: 1px 4px; border: 1px solid #d1d5db; text-align: center; }
        .horario-box th { font-size: 7px; padding: 1px 4px; border: 1px solid #9ca3af; background: #1e293b; color: #fff; text-align: center; }

        /* ── Tabela de batidas ── */
        .ponto-table {
            width: 100%;
            border-collapse: collapse;
            flex: 1;
            font-size: 8px;
        }
        .ponto-table thead tr th {
            background: #1e293b;
            color: #fff;
            text-align: center;
            padding: 3px 2px;
            font-size: 7.5px;
            border: 1px solid #374151;
        }
        .ponto-table thead tr.sub-header th {
            background: #374151;
            font-size: 7px;
            padding: 2px;
        }
        .ponto-table tbody tr td {
            border: 1px solid #d1d5db;
            text-align: center;
            padding: 2px 1px;
            font-size: 8px;
            height: 14px;
        }
        .ponto-table tbody tr:nth-child(even) td { background: #f9fafb; }
        .ponto-table tbody tr.folga td {
            background: #f1f5f9;
            color: #64748b;
            font-style: italic;
        }
        .ponto-table tbody tr.sem-ponto td { background: #fff7ed; }
        .ponto-table tbody tr.sem-ponto td.falta-col { color: #dc2626; font-weight: 700; }
        .ponto-table .td-date { font-weight: 600; text-align: left; padding-left: 4px; min-width: 18mm; }
        .ponto-table .td-dia  { font-size: 7px; color: #6b7280; }
        .ponto-table .td-horas { font-weight: 600; }
        .ponto-table .td-extra { color: #059669; font-weight: 700; }
        .ponto-table .td-falta { color: #dc2626; font-weight: 700; }
        .ponto-table tfoot tr td {
            background: #1e293b;
            color: #fff;
            font-weight: 700;
            font-size: 8px;
            padding: 3px 2px;
            border: 1px solid #374151;
        }
        .folga-label {
            font-size: 7.5px;
            color: #64748b;
            font-style: italic;
        }

        /* ── Rodapé ── */
        .footer {
            margin-top: 6px;
            border-top: 1px solid #9ca3af;
            padding-top: 4px;
        }
        .footer-text {
            font-size: 7.5px;
            color: #374151;
            margin-bottom: 6px;
            line-height: 1.5;
        }
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            gap: 20px;
        }
        .assinatura {
            flex: 1;
            text-align: center;
            border-top: 1px solid #374151;
            padding-top: 3px;
            font-size: 8px;
        }

        /* ── Impressão ── */
        @media print {
            body { background: #fff; }
            .controls { display: none !important; }
            .page {
                margin: 0;
                padding: 8mm 10mm;
                width: 210mm;
                min-height: 297mm;
                box-shadow: none;
            }
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>

{{-- ── Barra de controles ── --}}
<div class="controls">
    <a href="{{ route('painel.pontos.index') }}" class="btn-back" style="padding:7px 14px;border-radius:6px;color:#fff;text-decoration:none;font-size:12px;background:#475569;">← Voltar</a>

    <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label>
            Colaborador:
            <select name="employee_id">
                <option value="">Todos os colaboradores</option>
                @foreach($allEmployees as $emp)
                    <option value="{{ $emp->id }}" @selected($employeeId == $emp->id)>{{ $emp->user->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            De: <input type="date" name="date_from" value="{{ $dateFrom }}">
        </label>
        <label>
            Até: <input type="date" name="date_to" value="{{ $dateTo }}">
        </label>
        <button type="submit" class="btn-print">Gerar</button>
    </form>

    <button onclick="window.print()" class="btn-print" style="background:#16a34a;">🖨️ Imprimir</button>
</div>

@forelse($cards as $card)
@php
    $emp      = $card['employee'];
    $ws       = $emp->workSchedule;
    $company  = $emp->company;
    $dateFrom = $card['date_from'];
    $dateTo   = $card['date_to'];
    $dfCarbon = \Carbon\Carbon::parse($dateFrom);
    $dtCarbon = \Carbon\Carbon::parse($dateTo);

    $diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    $meses      = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

    function fmtMin(int $min): string {
        if ($min === 0) return '';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
@endphp

<div class="page">

    {{-- Cabeçalho --}}
    <div class="header">
        <div class="header-logo">
            PONTO<br>DIGITAL
        </div>
        <div class="header-title">
            <h1>Cartão Ponto</h1>
            <h2>{{ $company->name ?? 'Empresa' }}</h2>
        </div>
        <div class="header-period">
            <strong>Período:</strong><br>
            {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}
        </div>
    </div>

    {{-- Dados do colaborador --}}
    <div class="employee-info">
        <div class="info-item" style="grid-column: span 2;">
            <strong>Nome</strong>
            {{ $emp->user->name ?? '—' }}
        </div>
        <div class="info-item">
            <strong>Matrícula</strong>
            {{ $emp->registration_number ?? '—' }}
        </div>
        <div class="info-item">
            <strong>PIS / NIT</strong>
            {{ $emp->pis ?? '—' }}
        </div>
        <div class="info-item">
            <strong>CPF</strong>
            {{ $emp->cpf ?? '—' }}
        </div>
        <div class="info-item">
            <strong>Cargo / Função</strong>
            {{ $emp->cargo ?? '—' }}
        </div>
        <div class="info-item">
            <strong>Departamento</strong>
            {{ $emp->department ?? '—' }}
        </div>
        <div class="info-item">
            <strong>Admissão</strong>
            {{ $emp->admission_date?->format('d/m/Y') ?? '—' }}
        </div>
    </div>

    {{-- Horário de trabalho --}}
    @if($ws)
    <div class="horario-box">
        <table>
            <tr>
                <th>Horário de Trabalho</th>
                <th>Entrada</th>
                <th>Saída</th>
                <th>Intervalo</th>
                <th>Horas/Semana</th>
                <th>Tolerância</th>
            </tr>
            <tr>
                <td>Escala Padrão</td>
                <td>{{ $ws->entry_time }}</td>
                <td>{{ $ws->exit_time }}</td>
                <td>{{ $ws->lunch_minutes ? $ws->lunch_minutes . ' min' : 'Flexível' }}</td>
                <td>{{ $emp->weekly_hours }}h</td>
                <td>± {{ $ws->tolerance_minutes ?? 5 }} min</td>
            </tr>
        </table>
    </div>
    @endif

    {{-- Tabela de batidas --}}
    <table class="ponto-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:18mm;">Data</th>
                <th rowspan="2" style="width:8mm;">Dia</th>
                <th colspan="2">1ª Batida</th>
                <th colspan="2">2ª Batida</th>
                <th colspan="2">3ª Batida</th>
                <th rowspan="2" style="width:12mm;">Trabalhado</th>
                <th rowspan="2" style="width:10mm;">Extra</th>
                <th rowspan="2" style="width:10mm;">Falta</th>
                <th rowspan="2" style="width:14mm;">Obs</th>
            </tr>
            <tr class="sub-header">
                <th style="width:11mm;">ENT 1</th>
                <th style="width:11mm;">SAI 1</th>
                <th style="width:11mm;">ENT 2</th>
                <th style="width:11mm;">SAI 2</th>
                <th style="width:11mm;">ENT 3</th>
                <th style="width:11mm;">SAI 3</th>
            </tr>
        </thead>
        <tbody>
            @foreach($card['days'] as $day)
            @php
                $dw       = (int) $day['date']->format('w');
                $isWeekend= in_array($dw, [0,6]);
                $rowClass = $day['folga'] ? 'folga' : ($day['sem_ponto'] ? 'sem-ponto' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="td-date">{{ $day['date']->format('d/m/Y') }}</td>
                <td class="td-dia">{{ $diasSemana[$dw] }}</td>
                @if($day['folga'])
                    <td colspan="6" class="folga-label">
                        {{ $isWeekend ? 'Folga' : 'Folga / Feriado' }}
                    </td>
                    <td colspan="3"></td>
                    <td></td>
                @else
                    @foreach($day['batidas'] as $bat)
                        <td>{{ $bat['ent'] }}</td>
                        <td>{{ $bat['sai'] }}</td>
                    @endforeach
                    <td class="td-horas">{{ fmtMin($day['worked_min']) }}</td>
                    <td class="td-extra">{{ fmtMin($day['extra_min']) }}</td>
                    <td class="td-falta {{ $day['sem_ponto'] ? 'falta-col' : '' }}">{{ fmtMin($day['falta_min']) }}</td>
                    <td></td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align:right;padding-right:6px;">TOTAIS</td>
                <td>{{ fmtMin($card['total_worked']) }}</td>
                <td style="color:#86efac;">{{ fmtMin($card['total_extra']) }}</td>
                <td style="color:#fca5a5;">{{ fmtMin($card['total_falta']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Rodapé --}}
    <div class="footer">
        <div class="footer-text">
            Reconheço a exatidão das horas constantes de acordo com minha frequência neste intervalo
            de {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}.
        </div>
        <div class="assinaturas">
            <div class="assinatura">
                {{ $emp->user->name ?? '' }}<br>Colaborador
            </div>
            <div class="assinatura">
                Responsável / Diretor
            </div>
        </div>
    </div>

</div>
@empty
<div style="text-align:center;padding:40px;color:#6b7280;">
    Nenhum colaborador encontrado para o período selecionado.
</div>
@endforelse

</body>
</html>
