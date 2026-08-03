<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Actividad familiar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #5b21b6; }
        .muted { color: #6b7280; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-top: 14px; }
        .row { margin: 6px 0; }
        .label { color: #6b7280; }
        .value { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Zumifly — Resumen de actividad</h1>
    <div class="muted">{{ $family->name }} · {{ $periodLabel }}</div>
    <div class="muted">Generado por {{ $actor->name }} el {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="card">
        <div class="row"><span class="label">Periodo:</span> <span class="value">{{ $start->format('d/m/Y') }} — {{ $end->format('d/m/Y') }}</span></div>
        <div class="row"><span class="label">Tareas completadas:</span> <span class="value">{{ $tasksDone }}</span></div>
        <div class="row"><span class="label">Tareas pendientes:</span> <span class="value">{{ $tasksPending }}</span></div>
        <div class="row"><span class="label">Tareas escolares nuevas:</span> <span class="value">{{ $schoolTasks }}</span></div>
        <div class="row"><span class="label">Gastos del periodo:</span> <span class="value">${{ number_format($expenses, 0, ',', '.') }} COP</span></div>
    </div>

    <p class="muted" style="margin-top: 24px;">
        Documento de evidencia familiar generado por Zumifly. No sustituye documentos legales.
    </p>
</body>
</html>
