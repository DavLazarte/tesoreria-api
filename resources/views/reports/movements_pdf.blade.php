<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Movimientos - Tesorería</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
        }
        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        .summary-box {
            margin-bottom: 18px;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .summary-box table {
            width: 100%;
        }
        .summary-box td {
            padding: 4px 8px;
        }
        .value {
            font-weight: bold;
            color: #0f172a;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        table.data-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .voided-row {
            color: #94a3b8;
            text-decoration: line-through;
        }
        .badge-voided {
            color: #b91c1c;
            font-weight: bold;
            font-size: 9px;
        }
        .footer {
            margin-top: 25px;
            text-align: right;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">FUNDACIÓN - INFORME DE MOVIMIENTOS</h1>
        <div class="subtitle">Período: {{ $period }} | Emitido el: {{ $generated_at }} por {{ $user_name }}</div>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td>Total Ingresos: <span class="value">${{ number_format((float)$total_incomes, 2, ',', '.') }}</span></td>
                <td>Total Egresos: <span class="value">${{ number_format((float)$total_expenses, 2, ',', '.') }}</span></td>
                <td>Saldo Neto del período: <span class="value">${{ number_format((float)$net_balance, 2, ',', '.') }}</span></td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 70px;">Fecha</th>
                <th style="width: 55px;">Tipo</th>
                <th>Categoría</th>
                <th>Entidad / Destinatario</th>
                <th>Concepto</th>
                <th style="width: 80px;" class="text-right">Monto</th>
                <th style="width: 55px;" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $m)
                <tr class="{{ $m->isVoided() ? 'voided-row' : '' }}">
                    <td>{{ $m->movement_date?->format('d/m/Y') }}</td>
                    <td>{{ ucfirst($m->type->value) }}</td>
                    <td>{{ $m->category?->name ?? '—' }}</td>
                    <td>{{ $m->entity?->name ?? '—' }}</td>
                    <td>{{ $m->concept }}</td>
                    <td class="text-right">${{ number_format((float)$m->amount, 2, ',', '.') }}</td>
                    <td class="text-center">
                        @if ($m->isVoided())
                            <span class="badge-voided">ANULADO</span>
                        @else
                            ACTIVO
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento oficial de control interno - Sistema de Tesorería
    </div>
</body>
</html>
