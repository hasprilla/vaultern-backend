<?php

use App\Providers\AppServiceProvider;
use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;

return [
    AppServiceProvider::class,
    // Registro explícito: en cPanel a veces falta bootstrap/cache/packages.php
    // y el facade Pdf revienta con 500 al generar comprobantes.
    DomPdfServiceProvider::class,
];
