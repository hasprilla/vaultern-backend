<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprobante {{ $reference }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #1a1530; margin: 0; padding: 32px; background: #f7f5ff; }
        .sheet { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e6e2f0; padding: 32px; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; color: #6340E8; }
        .muted { color: #6b7280; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 10px 0; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        th { width: 42%; color: #6b7280; font-weight: 600; }
        .amount { font-size: 1.6rem; font-weight: 700; margin-top: 20px; }
        .footer { margin-top: 28px; font-size: 0.8rem; color: #6b7280; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: none; } }
    </style>
</head>
<body>
<main class="sheet">
    <h1>Zumifly</h1>
    <p class="muted">Comprobante de pago de suscripción</p>
    <p class="amount">{{ $currency }} {{ $amount }}</p>
    <table>
        <tr><th>Referencia</th><td>{{ $reference }}</td></tr>
        <tr><th>Estado</th><td>Aprobado</td></tr>
        <tr><th>Fecha de pago</th><td>{{ $paidAt }}</td></tr>
        <tr><th>Plan</th><td>{{ $planLabel }} ({{ $billing }})</td></tr>
        <tr><th>Medio de pago</th><td>{{ $card }}</td></tr>
        <tr><th>Titular</th><td>{{ $holder }}</td></tr>
        <tr><th>Procesado por</th><td>{{ $provider }}</td></tr>
        <tr><th>Pagador</th><td>{{ $payerName }} &lt;{{ $payerEmail }}&gt;</td></tr>
        <tr><th>Familia</th><td>{{ $familyName }}</td></tr>
    </table>
    <p class="footer">Emitido el {{ $issuedAt }}. Este comprobante es informativo para el titular de la cuenta Zumifly. El comprobante del procesador de pagos (p. ej. Wompi) puede consultarse aparte en su panel.</p>
</main>
</body>
</html>
