<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $reference }}</title>
    <style>
        @page { margin: 36px 40px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.45;
        }
        .top {
            border-bottom: 2px solid #111827;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .brand {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-title {
            margin: 4px 0 0;
            font-size: 12px;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .meta-row {
            width: 100%;
            margin-top: 10px;
        }
        .meta-row td { vertical-align: top; }
        .badge {
            display: inline-block;
            border: 1px solid #059669;
            color: #065f46;
            background: #ecfdf5;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .amount-box {
            margin: 18px 0 20px;
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
        }
        .amount-label {
            margin: 0;
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .amount-value {
            margin: 4px 0 0;
            font-size: 26px;
            font-weight: 700;
            color: #111827;
        }
        .section-title {
            margin: 0 0 8px;
            font-size: 10px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        table.details th,
        table.details td {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 11px;
        }
        table.details th {
            width: 38%;
            color: #6b7280;
            font-weight: 600;
        }
        table.details td {
            color: #111827;
            font-weight: 500;
        }
        .cols {
            width: 100%;
            margin-top: 6px;
        }
        .cols td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }
        .note {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
        }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="top">
        <table class="meta-row">
            <tr>
                <td>
                    <p class="brand">Zumifly</p>
                    <p class="doc-title">Comprobante de pago</p>
                </td>
                <td class="right">
                    <span class="badge">Aprobado</span>
                    <p style="margin:8px 0 0;color:#6b7280;">Emitido {{ $issuedAt }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="amount-box">
        <p class="amount-label">Monto pagado</p>
        <p class="amount-value">{{ $currency }} {{ $amount }}</p>
    </div>

    <p class="section-title">Detalle del cobro</p>
    <table class="details">
        <tr><th>Referencia</th><td>{{ $reference }}</td></tr>
        <tr><th>Fecha de pago</th><td>{{ $paidAt }}</td></tr>
        <tr><th>Plan</th><td>{{ $planLabel }} · {{ $billing }}</td></tr>
        <tr><th>Medio de pago</th><td>{{ $card }}</td></tr>
        <tr><th>Titular</th><td>{{ $holder }}</td></tr>
        <tr><th>Procesador</th><td>{{ $provider }}</td></tr>
    </table>

    <table class="cols">
        <tr>
            <td>
                <p class="section-title">Pagador</p>
                <table class="details">
                    <tr><th>Nombre</th><td>{{ $payerName }}</td></tr>
                    <tr><th>Correo</th><td>{{ $payerEmail }}</td></tr>
                </table>
            </td>
            <td>
                <p class="section-title">Cuenta</p>
                <table class="details">
                    <tr><th>Familia</th><td>{{ $familyName }}</td></tr>
                    <tr><th>Producto</th><td>Suscripción Zumifly</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="note">
        Documento informativo emitido por Zumifly para el titular de la cuenta.
        No reemplaza el comprobante del procesador de pagos ({{ $provider }}).
        Conserva este archivo como respaldo de tu pago.
    </p>
</body>
</html>
