<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #2d2d2d; font-size: 11px; }
        .header { border-bottom: 3px solid #E82A2A; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { color: #E82A2A; font-size: 20px; font-weight: bold; margin: 0; }
        .title { font-size: 15px; font-weight: bold; margin: 4px 0 0; }
        .subtitle { font-size: 10px; color: #666; margin: 4px 0 0; }
        .summary { margin-bottom: 16px; }
        .summary-box {
            display: inline-block;
            background-color: #FFF7D6;
            border: 1px solid #F5D97A;
            border-radius: 4px;
            padding: 8px 14px;
            margin-right: 8px;
        }
        .summary-label { font-size: 9px; color: #666; text-transform: uppercase; }
        .summary-value { font-size: 14px; font-weight: bold; color: #E82A2A; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th {
            background-color: #E82A2A;
            color: #ffffff;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
        }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #eee;
            font-size: 10px;
        }
        table.data tr:nth-child(even) td { background-color: #FAFAFA; }
        .footer { margin-top: 18px; font-size: 8px; color: #999; text-align: right; }
        .empty { padding: 20px; text-align: center; color: #999; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <p class="brand">Pollo Charly</p>
        <p class="title">{{ $title }}</p>
        @if(!empty($subtitle))
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if(!empty($summary))
        <div class="summary">
            @foreach($summary as $label => $value)
                <span class="summary-box">
                    <span class="summary-label">{{ $label }}</span><br>
                    <span class="summary-value">{{ $value }}</span>
                </span>
            @endforeach
        </div>
    @endif

    @if(count($rows) === 0)
        <div class="empty">No hay datos que coincidan con los filtros seleccionados.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generado el {{ $generatedAt }} — Sistema Pollo Charly</div>
</body>
</html>
