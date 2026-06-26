<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Family\FamilyController;
use App\Http\Controllers\Api\V1\Finance\FinanceController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Ocr\OcrController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    $payload = ['status' => 'ok', 'app' => 'Vaultern API v1'];

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $payload['database'] = 'ok';
    } catch (\Throwable) {
        $payload['status'] = 'degraded';
        $payload['database'] = 'error';
    }

    return response()->json($payload, $payload['status'] === 'ok' ? 200 : 503);
});

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/join', [AuthController::class, 'join']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/mfa/verify', [MfaController::class, 'verify']);
    Route::post('/account/reactivate', [ProfileController::class, 'reactivate']);
});

Route::prefix('v1')->middleware(['api.auth', 'tenant'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::get('/profile/notifications', [ProfileController::class, 'notificationPreferences']);
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotificationPreferences']);
    Route::post('/profile/account/deactivate', [ProfileController::class, 'deactivate']);
    Route::delete('/profile/account', [ProfileController::class, 'destroy']);

    Route::apiResource('families', FamilyController::class);
    Route::post('/families/{family}/invite', [FamilyController::class, 'invite']);
    Route::post('/families/{family}/children', [FamilyController::class, 'registerChild']);
    Route::get('/families/{family}/join-requests', [FamilyController::class, 'joinRequests']);
    Route::post('/families/{family}/join-requests/{joinRequest}/approve', [FamilyController::class, 'approveJoinRequest']);
    Route::post('/families/{family}/join-requests/{joinRequest}/reject', [FamilyController::class, 'rejectJoinRequest']);
    Route::post('/families/{family}/members/{member}/role', [FamilyController::class, 'assignRole']);

    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign']);

    Route::post('/ocr/notebook', [OcrController::class, 'processNotebook']);
    Route::post('/ocr/document', [OcrController::class, 'processDocument']);
    Route::post('/ocr/invoice', [OcrController::class, 'processInvoice']);
    Route::get('/ocr/{document}', [OcrController::class, 'show']);

    Route::get('/transactions', [FinanceController::class, 'index']);
    Route::post('/transactions', [FinanceController::class, 'store']);
    Route::get('/transactions/{transaction}', [FinanceController::class, 'show']);

    Route::get('/budgets', [FinanceController::class, 'budgetsIndex']);
    Route::post('/budgets', [FinanceController::class, 'budgetsStore']);
    Route::put('/budgets/{budget}', [FinanceController::class, 'budgetsUpdate']);
    Route::patch('/budgets/{budget}', [FinanceController::class, 'budgetsUpdate']);

    Route::get('/finance/reports/weekly', [FinanceController::class, 'weeklyReport']);
    Route::get('/finance/reports/monthly', [FinanceController::class, 'monthlyReport']);
    Route::get('/finance/reports/quarterly', [FinanceController::class, 'quarterlyReport']);
    Route::get('/finance/reports/annual', [FinanceController::class, 'annualReport']);

    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
