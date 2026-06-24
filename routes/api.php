<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Family\FamilyController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use App\Http\Controllers\Api\V1\Ocr\OcrController;
use App\Http\Controllers\Api\V1\Finance\FinanceController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;

// Health check
Route::get('/v1/health', fn() => response()->json(['status' => 'ok', 'app' => 'Vaultern API v1']));

// ── Auth (public) ──────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/mfa/verify', [MfaController::class, 'verify']);
});

// ── Protected ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {

    // Auth
    Route::post('/auth/logout',  [AuthController::class, 'logout']);
    Route::get('/auth/me',       [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Family
    Route::apiResource('families', FamilyController::class);
    Route::post('/families/{family}/invite',  [FamilyController::class, 'invite']);
    Route::post('/families/{family}/members/{member}/role', [FamilyController::class, 'assignRole']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/complete',  [TaskController::class, 'complete']);
    Route::patch('/tasks/{task}/assign',    [TaskController::class, 'assign']);

    // OCR
    Route::post('/ocr/notebook',  [OcrController::class, 'processNotebook']);
    Route::post('/ocr/document',  [OcrController::class, 'processDocument']);
    Route::post('/ocr/invoice',   [OcrController::class, 'processInvoice']);
    Route::get('/ocr/{document}', [OcrController::class, 'show']);

    // Finance
    Route::apiResource('transactions', FinanceController::class)->only(['index', 'store', 'show']);
    Route::apiResource('budgets',      FinanceController::class)->only(['index', 'store', 'update']);
    Route::get('/finance/reports/weekly',    [FinanceController::class, 'weeklyReport']);
    Route::get('/finance/reports/monthly',   [FinanceController::class, 'monthlyReport']);
    Route::get('/finance/reports/quarterly', [FinanceController::class, 'quarterlyReport']);
    Route::get('/finance/reports/annual',    [FinanceController::class, 'annualReport']);

    // Dashboard
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
});
