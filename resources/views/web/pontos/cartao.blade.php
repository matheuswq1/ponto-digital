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

        .info-top {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            align-items: start;
            margin-bottom: 6px;
        }
        @media (max-width: 700px) {
            .info-top { grid-template-columns: 1fr; }
        }
        .box-left, .box-right {
            border: 1px solid #9ca3af;
            padding: 5px 7px;
            background: #f8fafc;
        }
        .box-left h3, .box-right h3 {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 800;
            margin-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
        }
        .company-block .emp-line { font-size: 8px; margin: 1px 0; }
        .gabarito-title { font-size: 8px; font-weight: 700; color: #0f172a; margin-bottom: 3px; }
        .gabarito-sub { font-size: 7px; color: #64748b; margin-bottom: 3px; }
        .gabarito-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
        .gabarito-table th, .gabarito-table td {
            border: 1px solid #d1d5db;
            padding: 2px 3px;
            text-align: center;
        }
        .gabarito-table th { background: #1e293b; color: #fff; }

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

@php
if (!function_exists('ponto_cartao_fmt_min')) {
    function ponto_cartao_fmt_min(int $min): string {
        if ($min === 0) {
            return '';
        }
        $h = intdiv($min, 60);
        $m = $min % 60;

        return sprintf('%02d:%02d', $h, $m);
    }
}
@endphp

{{-- ── Barra de controles ── --}}
<div class="controls">
    <a href="{{ route('painel.pontos.index') }}" class="btn-back" style="padding:7px 14px;border-radius:6px;color:#fff;text-decoration:none;font-size:12px;background:#475569;">← Voltar</a>

    <form method="get" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        @if(request()->filled('q'))
            <input type="hidden" name="q" value="{{ request('q') }}">
        @endif
        <label>
            Colaborador:
            <select name="employee_id">
                <option value="">Todos os colaboradores</option>
                @foreach($allEmployees as $emp)
                    <option value="{{ $emp->id }}" @selected($employeeId == $emp->id)>{{ $emp->user?->name ?? '—' }}</option>
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
    $deptModel = $emp->dept;
    $company  = $emp->company;
    $dateFrom = $card['date_from'];
    $dateTo   = $card['date_to'];
    $dfCarbon = \Carbon\Carbon::parse($dateFrom);
    $dtCarbon = \Carbon\Carbon::parse($dateTo);

    $diasSemana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    $diasGabarito = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

    if ($deptModel && $deptModel->entry_time && $deptModel->exit_time) {
        $gabaritoLabel = 'Departamento: '.$deptModel->name;
        $gabaritoRef = $deptModel;
        $gWorkDays = $deptModel->workDaysList();
        $gabaritoKind = 'dept';
    } elseif ($ws && $ws->entry_time && $ws->exit_time) {
        $gabaritoLabel = 'Escala individual (colaborador)';
        $gabaritoRef = $ws;
        $gWorkDays = $ws->workDaysList();
        $gabaritoKind = 'ws';
    } else {
        $gabaritoLabel = null;
        $gabaritoRef = null;
        $gWorkDays = [];
        $gabaritoKind = null;
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
            <h2>{{ $company?->name ?? 'Empresa' }}</h2>
        </div>
        <div class="header-period">
            <strong>Período:</strong><br>
            {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}
        </div>
    </div>

    {{-- Empresa (esq.) + Gabarito / escala (dir.) --}}
    <div class="info-top">
        <div class="box-left">
            <h3>Empresa e colaborador</h3>
            <div class="company-block" style="margin-bottom:5px;padding-bottom:4px;border-bottom:1px dashed #cbd5e1;">
                <p class="emp-line"><strong style="color:#334155">Razão social:</strong> {{ $company?->name ?? '—' }}</p>
                @if($company?->cnpj)
                <p class="emp-line"><strong style="color:#334155">CNPJ:</strong> {{ $company->cnpj }}</p>
                @endif
                @if($company?->address)
                <p class="emp-line"><strong style="color:#334155">End.:</strong> {{ $company->address }}{{ $company->city ? ', '.$company->city.'/'.$company->state : '' }}</p>
                @endif
            </div>
            <div class="emp-block">
                <p class="emp-line"><strong style="color:#334155">Colaborador:</strong> {{ $emp->user?->name ?? '—' }}</p>
                <p class="emp-line"><strong style="color:#334155">Matrícula:</strong> {{ $emp->registration_number ?? '—' }} &nbsp;|&nbsp; <strong>PIS:</strong> {{ $emp->pis ?? '—' }}</p>
                <p class="emp-line"><strong style="color:#334155">CPF:</strong> {{ $emp->cpf ?? '—' }} &nbsp;|&nbsp; <strong>Cargo:</strong> {{ $emp->cargo ?? '—' }}</p>
                <p class="emp-line"><strong style="color:#334155">Departamento:</strong> {{ $deptModel?->name ?? $emp->department ?? '—' }}</p>
                <p class="emp-line"><strong style="color:#334155">Admissão:</strong> {{ $emp->admission_date?->format('d/m/Y') ?? '—' }} &nbsp;|&nbsp; <strong>Horas/sem.:</strong> {{ $emp->weekly_hours }}h</p>
            </div>
        </div>
        <div class="box-right">
            <h3>Gabarito &mdash; escala de referência</h3>
            @if($gabaritoKind)
                <p class="gabarito-title">{{ $gabaritoLabel }}</p>
                <p class="gabarito-sub">
                    Tolerância: ±{{ $gabaritoRef->tolerance_minutes ?? 10 }} min
                    &middot;
                    @if($gabaritoKind === 'dept' && $deptModel->hasVariableLunchByDay())
                        Intervalo: <strong>varia por dia</strong> (ver tabela abaixo)
                    @else
                        Intervalo: {{ (int)($gabaritoRef->lunch_minutes ?? 0) }} min
                    @endif
                </p>
                <table class="gabarito-table">
                    <thead>
                        <tr>
                            <th>Dia</th>
                            <th>ENT 1</th>
                            <th>SAI 1</th>
                            <th>ENT 2</th>
                            <th>SAI 2</th>
                            @if($gabaritoKind === 'dept' && $deptModel->hasVariableLunchByDay())
                                <th>Int.</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([1,2,3,4,5,6,0] as $dow)
                        @php
                            if ($gabaritoKind === 'dept') {
                                $rowTimes = $deptModel->getGabaritoTimesForDay($dow);
                            } else {
                                $rowTimes = $ws->getGabaritoTimes();
                            }
                        @endphp
                        <tr>
                            <td><strong>{{ $diasGabarito[$dow] }}</strong></td>
                            @if(in_array($dow, array_map('intval', (array) $gWorkDays), true))
                                @if($rowTimes)
                                    <td>{{ $rowTimes['e1'] }}</td>
                                    <td>{{ $rowTimes['s1'] }}</td>
                                    <td>{{ $rowTimes['e2'] }}</td>
                                    <td>{{ $rowTimes['s2'] }}</td>
                                    @if($gabaritoKind === 'dept' && $deptModel->hasVariableLunchByDay())
                                        <td>{{ $deptModel->getLunchMinutesForDay($dow) }}′</td>
                                    @endif
                                @else
                                    <td colspan="{{ ($gabaritoKind === 'dept' && $deptModel->hasVariableLunchByDay()) ? 5 : 4 }}" style="color:#94a3b8;">—</td>
                                @endif
                            @else
                                <td colspan="{{ ($gabaritoKind === 'dept' && $deptModel->hasVariableLunchByDay()) ? 5 : 4 }}" style="font-style:italic;color:#64748b;">Folga</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="font-size:8px;color:#94a3b8;">Defina o departamento do colaborador (menu Departamentos) ou a escala individual na ficha do colaborador para exibir o gabarito.</p>
            @endif
        </div>
    </div>

    {{-- Tabela de batidas --}}
    <table class="ponto-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:16mm;">Data</th>
                <th rowspan="2" style="width:7mm;">Dia</th>
                <th colspan="2">1ª Batida</th>
                <th colspan="2">2ª Batida</th>
                <th colspan="2">3ª Batida</th>
                <th rowspan="2" style="width:11mm;">Trabalhado</th>
                <th rowspan="2" style="width:9mm;">Faltas</th>
                <th rowspan="2" style="width:9mm;">EX 50%</th>
                <th rowspan="2" style="width:9mm;">EX 100%</th>
                <th rowspan="2" style="width:9mm; color:#94a3b8;">EXF01</th>
                <th rowspan="2" style="width:9mm;">Extras</th>
            </tr>
            <tr class="sub-header">
                <th style="width:10mm;">ENT 1</th>
                <th style="width:10mm;">SAI 1</th>
                <th style="width:10mm;">ENT 2</th>
                <th style="width:10mm;">SAI 2</th>
                <th style="width:10mm;">ENT 3</th>
                <th style="width:10mm;">SAI 3</th>
            </tr>
        </thead>
        <tbody>
            @foreach($card['days'] as $day)
            @php
                $dw        = (int) $day['date']->format('w');
                $isWeekend = in_array($dw, [0,6]);
                $rowClass  = $day['folga'] ? 'folga' : ($day['sem_ponto'] ? 'sem-ponto' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="td-date">{{ $day['date']->format('d/m/Y') }}</td>
                <td class="td-dia">{{ $diasSemana[$dw] }}</td>
                @if($day['folga'])
                    <td colspan="6" class="folga-label">
                        {{ $isWeekend ? 'Folga' : 'Folga / Feriado' }}
                    </td>
                    <td colspan="6"></td>
                @else
                    @foreach($day['batidas'] as $bat)
                        <td>{{ $bat['ent'] }}</td>
                        <td>{{ $bat['sai'] }}</td>
                    @endforeach
                    <td class="td-horas">{{ ponto_cartao_fmt_min($day['worked_min']) }}</td>
                    <td class="td-falta {{ $day['sem_ponto'] ? 'falta-col' : '' }}">{{ ponto_cartao_fmt_min($day['falta_min']) }}</td>
                    <td class="td-extra">{{ ponto_cartao_fmt_min($day['extra_50_min']) }}</td>
                    <td class="td-extra">{{ ponto_cartao_fmt_min($day['extra_100_min']) }}</td>
                    <td style="color:#cbd5e1;">—</td>
                    <td class="td-extra">{{ ponto_cartao_fmt_min($day['extra_min']) }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align:right;padding-right:6px;">TOTAIS</td>
                <td>{{ ponto_cartao_fmt_min($card['total_worked']) }}</td>
                <td style="color:#fca5a5;">{{ ponto_cartao_fmt_min($card['total_falta']) }}</td>
                <td style="color:#86efac;">{{ ponto_cartao_fmt_min($card['total_extra_50']) }}</td>
                <td style="color:#86efac;">{{ ponto_cartao_fmt_min($card['total_extra_100']) }}</td>
                <td style="color:#64748b;">—</td>
                <td style="color:#86efac;">{{ ponto_cartao_fmt_min($card['total_extra']) }}</td>
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
                {{ $emp->user?->name ?? '' }}<br>Colaborador
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
