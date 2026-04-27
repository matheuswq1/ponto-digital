<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 12px; text-align: center; margin-bottom: 4px; }
        .sub { font-size: 8px; text-align: center; color: #555; margin-bottom: 8px; }
        .emp { page-break-inside: avoid; margin-bottom: 10px; }
        .emp-h { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 8.5px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; font-size: 7.5px; }
        th { background: #1e293b; color: #fff; padding: 3px 4px; text-align: left; border: 1px solid #334155; }
        th.r, td.r { text-align: right; }
        td { border: 1px solid #d1d5db; padding: 2px 4px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .muted { color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
    <h1>Extrato — Banco de horas</h1>
    <p class="sub">{{ $monthLabel }}</p>

    @foreach($sections as $sec)
    @php $e = $sec['employee']; @endphp
    <div class="emp">
        <div class="emp-h">
            {{ $e->user?->name ?? '—' }} &mdash; {{ $e->company?->name ?? '—' }}
            @if($e->dept) | {{ $e->dept->name }} @endif
            &nbsp;|&nbsp; Inicial: {{ $sec['initialFmt'] }} &rarr; Final: {{ $sec['closingFmt'] }}
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:10%">Data</th>
                    <th style="width:14%">Tipo</th>
                    <th>Descrição</th>
                    <th class="r" style="width:10%">Mov.</th>
                    <th class="r" style="width:10%">Saldo após</th>
                </tr>
            </thead>
            <tbody>
            @forelse($sec['txRows'] as $row)
                <tr>
                    <td>{{ $row['ref'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['desc'] }}</td>
                    <td class="r">{{ $row['signedFmt'] }}</td>
                    <td class="r">{{ $row['balFmt'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align:center">Sem movimentos no mês</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endforeach
</body>
</html>
