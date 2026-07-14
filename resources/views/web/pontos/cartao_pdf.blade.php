<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html { width: 100%; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 7px;
    color: #111;
    margin: 0;
    padding: 0;
}

/**
 * A4 retrato: largura útil ~202 mm (210 − margens). DomPDF corta à direita se o bloco
 * "imaginar" mais largo que a página — por isso .pdf-root em mm e grelha ~92%.
 */
@page { margin: 4mm 5mm; size: A4 portrait; }

.pdf-root {
    width: 198mm;
    max-width: 100%;
    margin: 0 auto;
}

/* ── Página ── */
.page { width: 100%; padding: 2mm 1mm; page-break-after: always; }
.page:last-child { page-break-after: auto; }

/* ── Cabeçalho ── */
.header { border-bottom: 2px solid #1e293b; padding-bottom: 4px; margin-bottom: 5px; }
.header table { width: 100%; border-collapse: collapse; }
.header td { vertical-align: middle; }
.header-logo { font-size: 8px; font-weight: 900; color: #1e293b; border: 1px solid #1e293b; padding: 2px 4px; text-align: center; white-space: nowrap; line-height: 1.1; }
.header-title { text-align: center; }
.header-title h1 { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; }
.header-title h2 { font-size: 8px; font-weight: 700; color: #374151; }
.header-period { font-size: 7px; text-align: right; white-space: nowrap; }

/* ── Empresa + Gabarito ── */
.info-top table.split { width: 100%; max-width: 100%; border-collapse: collapse; margin-bottom: 5px; table-layout: fixed; }
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

/* ── Tabela de batidas: retrato, colunas estreitas, uma linha de cabeçalho ── */
.ponto-table {
    width: 98%;
    max-width: 98%;
    margin: 0 auto;
    border-collapse: collapse;
    font-size: 5.5px;
    table-layout: fixed;
}
.ponto-table th {
    background: #1e293b;
    color: #fff;
    padding: 1px 0;
    text-align: center;
    border: 1px solid #374151;
    font-size: 5px;
    font-weight: 700;
    line-height: 1.05;
}
.ponto-table td {
    border: 1px solid #d1d5db;
    text-align: center;
    padding: 0;
    height: auto;
    min-height: 8px;
    font-size: 5.5px;
    word-wrap: break-word;
    overflow-wrap: anywhere;
    vertical-align: middle;
}
.ponto-table tr.even td { background: #f9fafb; }
.ponto-table tr.folga td { background: #f1f5f9; color: #64748b; font-style: italic; }
.ponto-table tr.sem-ponto td { background: #fff7ed; }
.ponto-table tr.feriado td { background: #faf5ff; color: #6b21a8; }
.ponto-table tfoot td { background: #1e293b; color: #fff; font-weight: 700; font-size: 5.5px; padding: 1px 0; border: 1px solid #374151; }
.td-date {
    font-weight: 600;
    text-align: left;
    padding-left: 1px;
    font-size: 5px;
    line-height: 1.1;
    vertical-align: top;
    hyphens: none;
}
.td-extra { color: #059669; font-weight: 700; }
.td-falta { color: #dc2626; font-weight: 700; }
.td-100 { color: #7c3aed; font-weight: 700; }
.td-noc { color: #0369a1; font-weight: 700; }
.banco-ok { color: #16a34a; font-weight: 700; }

.pdf-col-legend { font-size: 4.5px; color: #64748b; margin: 2px 0 10mm; padding: 0 1mm; line-height: 1.2; }

/* ── Rodapé — afastado do histórico + espaço para assinar -- */
.footer {
    margin-top: 12mm;
    padding-top: 5mm;
    border-top: 1px solid #9ca3af;
}
.footer-text {
    font-size: 7px;
    color: #374151;
    margin-bottom: 12mm;
    line-height: 1.6;
}
.assinaturas { margin-top: 4mm; }
.assinaturas table { width: 100%; border-collapse: separate; border-spacing: 5mm 0; }
.assinaturas .assinatura-gap { width: 6%; border: none !important; padding: 0 !important; }
.assinatura-cell {
    width: 47%;
    vertical-align: top;
    text-align: center;
    padding: 0;
    border: none !important;
}
.assinatura-caption {
    font-size: 6px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 1.5mm;
    text-align: center;
}
/* Área livre para caneta / carimbo */
.assinatura-blank {
    min-height: 28mm;
    height: 28mm;
    border: 1px solid #64748b;
    background: #fff;
    margin: 0 auto 3mm;
    box-sizing: border-box;
}
.assinatura-ident {
    font-size: 7px;
    font-weight: 600;
    color: #1e293b;
    margin-top: 1mm;
    line-height: 1.35;
}
.assinatura-role {
    font-size: 6px;
    color: #64748b;
    margin-top: 1mm;
}
.assinatura-data-linha {
    font-size: 6px;
    color: #475569;
    margin-top: 2mm;
    letter-spacing: 0.5px;
}
</style>
</head>
<body>

<div class="pdf-root">

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

    if ($dept && $dept->hasGabarito()) {
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
                <td style="width:42px;"><div class="header-logo">PONTO<br>DIGITAL</div></td>
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

    {{-- Tabela de batidas — cabeçalho numa linha (menos altura); larguras % dentro da .pdf-root --}}
    <table class="ponto-table">
        <colgroup>
            <col style="width:10%">
            <col style="width:2.5%">
            <col style="width:6.2%"><col style="width:6.2%"><col style="width:6.2%"><col style="width:6.2%"><col style="width:6.2%"><col style="width:6.2%">
            <col style="width:8%">
            <col style="width:6.5%"><col style="width:6.5%"><col style="width:6.5%"><col style="width:6.5%"><col style="width:7%">
        </colgroup>
        <thead>
            <tr>
                <th>Data</th>
                <th>D</th>
                <th>E1</th><th>S1</th><th>E2</th><th>S2</th><th>E3</th><th>S3</th>
                <th>Tr</th><th>Fa</th><th>50</th><th>100</th><th>Nc</th><th>Ex</th>
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
            <td class="td-date" @if(!empty($day['work_day']?->tolerance_snapshot)) title="{{ e($day['work_day']->toleranceCartaoHintPt()) }}" @endif>
                {{ $day['date']->format('d/m/Y') }}
                @if($day['banco_ok']) <span class="banco-ok">&#10003;</span>@endif
                @php $pdfTolBadge = $day['work_day']?->toleranceUxBadgePt(); @endphp
                @if($pdfTolBadge)
                    <div style="font-size:5px;font-weight:700;margin-top:1px;padding:0 2px;line-height:1.1;border-radius:2px;background:{{ $pdfTolBadge['bg'] }};color:{{ $pdfTolBadge['color'] }};">{{ $pdfTolBadge['label'] }}</div>
                @endif
                @php $pdfTolWarn = $day['work_day']?->tolerancePostCloseMismatchPt(); @endphp
                @if($pdfTolWarn)
                    <div style="font-size:5px;color:#9a3412;margin-top:1px;line-height:1.1;">{{ $pdfTolWarn }}</div>
                @endif
            </td>
            <td style="color:#6b7280;font-size:5px;">{{ $diasSemana[$dw] }}</td>

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
                <td colspan="8" style="text-align:right;padding-right:2px;">Totais</td>
                <td>{{ pdf_fmt_min($card['total_worked']) }}</td>
                <td style="color:#fca5a5;">{{ $card['total_falta'] > 0 ? pdf_fmt_min($card['total_falta']) : '—' }}</td>
                <td style="color:#86efac;">{{ $card['total_extra_50'] > 0 ? pdf_fmt_min($card['total_extra_50']) : '—' }}</td>
                <td style="color:#c4b5fd;">{{ $card['total_extra_100'] > 0 ? pdf_fmt_min($card['total_extra_100']) : '—' }}</td>
                <td style="color:#93c5fd;">{{ $card['total_extra_noc'] > 0 ? pdf_fmt_min($card['total_extra_noc']) : '—' }}</td>
                <td style="color:#86efac;">{{ $card['total_extra'] > 0 ? pdf_fmt_min($card['total_extra']) : '—' }}</td>
            </tr>
        </tfoot>
    </table>
    <p class="pdf-col-legend">Tr trabalhado · Fa faltas · 50/100 extras · Nc noturno · Ex saldo extra</p>

    {{-- Rodapé com assinaturas --}}
    <div class="footer">
        <p class="footer-text">
            Reconheço a exatidão das horas constantes de acordo com minha frequência neste intervalo
            de {{ $dfCarbon->format('d/m/Y') }} a {{ $dtCarbon->format('d/m/Y') }}.
        </p>
        <div class="assinaturas">
            <table>
                <tr>
                    <td class="assinatura-cell">
                        <div class="assinatura-caption">Colaborador — assinatura ou carimbo</div>
                        <div class="assinatura-blank"></div>
                        <div class="assinatura-ident">{{ $emp->user?->name ?? '_________________________' }}</div>
                        <div class="assinatura-role">Nome legível do colaborador</div>
                        <div class="assinatura-data-linha">Data: _____ / _____ / ________</div>
                    </td>
                    <td class="assinatura-gap"></td>
                    <td class="assinatura-cell">
                        <div class="assinatura-caption">Empresa — assinatura ou carimbo</div>
                        <div class="assinatura-blank"></div>
                        <div class="assinatura-ident">Responsável pela empresa</div>
                        <div class="assinatura-role">Cargo / Direção</div>
                        <div class="assinatura-data-linha">Data: _____ / _____ / ________</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</div>
@endforeach

</div>
</body>
</html>
