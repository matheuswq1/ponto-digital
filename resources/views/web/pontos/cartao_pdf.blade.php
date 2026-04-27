<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #111; }

/* ── Página ── */
.page { width: 100%; padding: 6mm 8mm; page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* ── Cabeçalho ── */
.header { border-bottom: 2px solid #1e293b; padding-bottom: 4px; margin-bottom: 5px; }
.header table { width: 100%; border-collapse: collapse; }
.header td { vertical-align: middle; }
.header-logo { font-size: 10px; font-weight: 900; color: #1e293b; border: 2px solid #1e293b; padding: 3px 5px; text-align: center; white-space: nowrap; }
.header-title { text-align: center; }
.header-title h1 { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
.header-title h2 { font-size: 9px; font-weight: 700; color: #374151; }
.header-period { font-size: 7px; text-align: right; white-space: nowrap; }

/* ── Empresa + Gabarito ── */
.info-top table.split { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
.info-top table.split td { vertical-align: top; padding: 0 3px; width: 50%; }
.box { border: 1px solid #9ca3af; padding: 4px 5px; background: #f8fafc; }
.box h3 { font-size: 7px; text-transform: uppercase; color: #64748b; font-weight: 800; margin-bottom: 3px; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
.emp-line { font-size: 7.5px; margin: 1px 0; }
.emp-line strong { color: #334155; }
.gabarito-title { font-size: 8px; font-weight: 700; margin-bottom: 2px; }
.gabarito-sub { font-size: 7px; color: #64748b; margin-bottom: 3px; }
.gabarito-table { width: 100%; border-collapse: collapse; font-size: 7px; }
.gabarito-table th, .gabarito-table td { border: 1px solid #d1d5db; padding: 2px 3px; text-align: center; }
.gabarito-table th { background: #1e293b; color: #fff; }

/* ── Tabela de batidas ── */
.ponto-table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
.ponto-table th { background: #1e293b; color: #fff; padding: 3px 2px; text-align: center; border: 1px solid #374151; font-size: 7px; }
.ponto-table th.sub { background: #374151; font-size: 6.5px; }
.ponto-table td { border: 1px solid #d1d5db; text-align: center; padding: 2px 1px; height: 12px; }
.ponto-table tr.even td { background: #f9fafb; }
.ponto-table tr.folga td { background: #f1f5f9; color: #64748b; font-style: italic; }
.ponto-table tr.sem-ponto td { background: #fff7ed; }
.ponto-table tr.feriado td { background: #faf5ff; color: #6b21a8; }
.ponto-table tfoot td { background: #1e293b; color: #fff; font-weight: 700; font-size: 7.5px; padding: 3px 2px; border: 1px solid #374151; }
.td-date { font-weight: 600; text-align: left; padding-left: 3px; }
.td-extra { color: #059669; font-weight: 700; }
.td-falta { color: #dc2626; font-weight: 700; }
.td-100 { color: #7c3aed; font-weight: 700; }
.td-noc { color: #0369a1; font-weight: 700; }
.banco-ok { color: #16a34a; font-weight: 700; }

/* ── Rodapé ── */
.footer { margin-top: 8px; border-top: 1px solid #9ca3af; padding-top: 4px; }
.footer-text { font-size: 7px; color: #374151; margin-bottom: 8px; line-height: 1.6; }
.assinaturas table { width: 100%; border-collapse: collapse; }
.assinaturas td { text-align: center; padding-top: 16px; border-top: 1px solid #374151; font-size: 7.5px; }
</style>
</head>
<body>

@php
if (!function_exists('pdf_fmt_min')) {
    function pdf_fmt_min(int $m): string {
        if ($m === 0) return '—';
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
}
$diasSemana   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$diasGabarito = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',0=>'Dom'];
@endphp

@foreach($cards as $card)
@php
    $emp       = $card['employee'];
    $ws        = $emp->workSchedule;
    $dept      = $emp->dept;
    $company   = $emp->company;
    $dfCarbon  = \Carbon\Carbon::parse($card['date_from']);
    $dtCarbon  = \Carbon\Carbon::parse($card['date_to']);

    if ($dept && $dept->entry_time && $dept->exit_time) {
        $gabKind  = 'dept';
        $gabLabel = 'Departamento: '.$dept->name;
        $gabRef   = $dept;
        $gWorkDays = $dept->workDaysList();
    } elseif ($ws && $ws->entry_time && $ws->exit_time) {
        $gabKind  = 'ws';
        $gabLabel = 'Escala individual';
        $gabRef   = $ws;
        $gWorkDays = $ws->workDaysList();
    } else {
        $gabKind  = null;
        $gabLabel = null;
        $gabRef   = null;
        $gWorkDays = [];
    }
@endphp

<div class="page">

    {{-- Cabeçalho --}}
    <div class="header">
        <table>
            <tr>
                <td style="width:55px;"><div class="header-logo">PONTO<br>DIGITAL</div></td>
                <td class="header-title">
                    <h1>Espelho de Ponto</h1>
                    <h2>{{ $company?->name ?? 'Empresa' }}</h2>
                </td>
                <td class="header-period">
                    <strong>Período:</strong><br>
                    {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- Empresa (esq.) + Gabarito (dir.) --}}
    <div class="info-top">
        <table class="split">
            <tr>
                <td>
                    <div class="box">
                        <h3>Empresa e colaborador</h3>
                        <p class="emp-line"><strong>Razão social:</strong> {{ $company?->name ?? '—' }}</p>
                        @if($company?->cnpj)<p class="emp-line"><strong>CNPJ:</strong> {{ $company->cnpj }}</p>@endif
                        @if($company?->address)<p class="emp-line"><strong>End.:</strong> {{ $company->address }}{{ $company->city ? ', '.$company->city.'/'.$company->state : '' }}</p>@endif
                        <p class="emp-line" style="margin-top:3px;"><strong>Colaborador:</strong> {{ $emp->user?->name ?? '—' }}</p>
                        <p class="emp-line"><strong>Matrícula:</strong> {{ $emp->registration_number ?? '—' }} &nbsp;|&nbsp; <strong>PIS:</strong> {{ $emp->pis ?? '—' }}</p>
                        <p class="emp-line"><strong>CPF:</strong> {{ $emp->cpf ?? '—' }} &nbsp;|&nbsp; <strong>Cargo:</strong> {{ $emp->cargo ?? '—' }}</p>
                        <p class="emp-line"><strong>Departamento:</strong> {{ $dept?->name ?? $emp->department ?? '—' }}</p>
                        <p class="emp-line"><strong>Admissão:</strong> {{ $emp->admission_date?->format('d/m/Y') ?? '—' }} &nbsp;|&nbsp; <strong>Horas/sem.:</strong> {{ $emp->weekly_hours }}h</p>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <h3>Gabarito — escala de referência</h3>
                        @if($gabKind)
                            <p class="gabarito-title">{{ $gabLabel }}</p>
                            <p class="gabarito-sub">
                                Tolerância: ±{{ $gabRef->tolerance_minutes ?? 10 }} min
                                &middot;
                                @if($gabKind === 'dept' && $dept->hasVariableLunchByDay())
                                    Intervalo: <strong>varia por dia</strong>
                                @else
                                    Intervalo: {{ (int)($gabRef->lunch_minutes ?? 0) }} min
                                @endif
                            </p>
                            <table class="gabarito-table">
                                <thead>
                                    <tr>
                                        <th>Dia</th><th>ENT1</th><th>SAI1</th><th>ENT2</th><th>SAI2</th>
                                        @if($gabKind === 'dept' && $dept->hasVariableLunchByDay())<th>Int.</th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach([1,2,3,4,5,6,0] as $dow)
                                @php
                                    $rt = $gabKind === 'dept' ? $dept->getGabaritoTimesForDay($dow) : $ws->getGabaritoTimes();
                                @endphp
                                <tr>
                                    <td><strong>{{ $diasGabarito[$dow] }}</strong></td>
                                    @if(in_array($dow, array_map('intval',(array)$gWorkDays), true) && $rt)
                                        <td>{{ $rt['e1'] }}</td><td>{{ $rt['s1'] }}</td><td>{{ $rt['e2'] }}</td><td>{{ $rt['s2'] }}</td>
                                        @if($gabKind === 'dept' && $dept->hasVariableLunchByDay())
                                            <td>{{ $dept->getLunchMinutesForDay($dow) }}'</td>
                                        @endif
                                    @else
                                        <td colspan="{{ ($gabKind==='dept'&&$dept->hasVariableLunchByDay())?5:4 }}" style="color:#94a3b8;font-style:italic;">Folga</td>
                                    @endif
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <p style="font-size:7px;color:#94a3b8;">Nenhuma escala definida para este colaborador.</p>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Tabela de batidas --}}
    <table class="ponto-table">
        <thead>
            <tr>
                <th rowspan="2" style="width:14mm;">Data</th>
                <th rowspan="2" style="width:6mm;">Dia</th>
                <th colspan="2">1ª Batida</th>
                <th colspan="2">2ª Batida</th>
                <th colspan="2">3ª Batida</th>
                <th rowspan="2" style="width:10mm;">Trabalhado</th>
                <th rowspan="2" style="width:8mm;">Faltas</th>
                <th rowspan="2" style="width:8mm;">EX 50%</th>
                <th rowspan="2" style="width:8mm;">EX 100%</th>
                <th rowspan="2" style="width:8mm;">EXF01</th>
                <th rowspan="2" style="width:8mm;">Extras</th>
            </tr>
            <tr>
                <th class="sub" style="width:9mm;">ENT1</th>
                <th class="sub" style="width:9mm;">SAI1</th>
                <th class="sub" style="width:9mm;">ENT2</th>
                <th class="sub" style="width:9mm;">SAI2</th>
                <th class="sub" style="width:9mm;">ENT3</th>
                <th class="sub" style="width:9mm;">SAI3</th>
            </tr>
        </thead>
        <tbody>
        @foreach($card['days'] as $i => $day)
        @php
            $dw       = (int) $day['date']->format('w');
            $rowClass = $day['is_holiday'] ? 'feriado'
                      : ($day['folga'] ? 'folga'
                      : ($day['sem_ponto'] ? 'sem-ponto'
                      : ($i % 2 === 0 ? '' : 'even')));
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="td-date">
                {{ $day['date']->format('d/m/Y') }}
                @if($day['banco_ok']) <span class="banco-ok">&#10003;</span>@endif
            </td>
            <td style="color:#6b7280;font-size:7px;">{{ $diasSemana[$dw] }}</td>

            @if($day['folga'])
                <td colspan="6" style="font-style:italic;color:#64748b;">Folga</td>
                <td colspan="6"></td>
            @elseif($day['is_holiday'] && $day['worked_min'] === 0)
                <td colspan="6" style="font-style:italic;">Feriado</td>
                <td colspan="6"></td>
            @else
                @foreach($day['batidas'] as $bat)
                    <td>{{ $bat['ent'] }}</td><td>{{ $bat['sai'] }}</td>
                @endforeach
                <td style="font-weight:600;">{{ pdf_fmt_min($day['worked_min']) }}</td>
                <td class="td-falta">{{ $day['falta_min'] > 0 ? pdf_fmt_min($day['falta_min']) : '' }}</td>
                <td class="td-extra">{{ $day['extra_50_min'] > 0 ? pdf_fmt_min($day['extra_50_min']) : '' }}</td>
                <td class="{{ $day['is_holiday'] ? 'td-100' : 'td-extra' }}">{{ $day['extra_100_min'] > 0 ? pdf_fmt_min($day['extra_100_min']) : '' }}</td>
                <td class="td-noc">{{ $day['extra_noc_min'] > 0 ? pdf_fmt_min($day['extra_noc_min']) : '' }}</td>
                <td class="td-extra">{{ $day['extra_min'] > 0 ? pdf_fmt_min($day['extra_min']) : '' }}</td>
            @endif
        </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align:right;padding-right:5px;">TOTAIS</td>
                <td>{{ pdf_fmt_min($card['total_worked']) }}</td>
                <td style="color:#fca5a5;">{{ $card['total_falta'] > 0 ? pdf_fmt_min($card['total_falta']) : '—' }}</td>
                <td style="color:#86efac;">{{ $card['total_extra_50'] > 0 ? pdf_fmt_min($card['total_extra_50']) : '—' }}</td>
                <td style="color:#c4b5fd;">{{ $card['total_extra_100'] > 0 ? pdf_fmt_min($card['total_extra_100']) : '—' }}</td>
                <td style="color:#93c5fd;">{{ $card['total_extra_noc'] > 0 ? pdf_fmt_min($card['total_extra_noc']) : '—' }}</td>
                <td style="color:#86efac;">{{ $card['total_extra'] > 0 ? pdf_fmt_min($card['total_extra']) : '—' }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Rodapé com assinaturas --}}
    <div class="footer">
        <p class="footer-text">
            Reconheço a exatidão das horas constantes de acordo com minha frequência neste intervalo
            de {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}.
        </p>
        <div class="assinaturas">
            <table>
                <tr>
                    <td style="width:45%;">{{ $emp->user?->name ?? '' }}<br><span style="font-size:7px;color:#64748b;">Colaborador</span></td>
                    <td style="width:10%;"></td>
                    <td style="width:45%;">Responsável / Diretor</td>
                </tr>
            </table>
        </div>
    </div>

</div>
@endforeach

</body>
</html>
