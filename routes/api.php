<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\MfaController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Family\FamilyController;
use App\Http\Controllers\Api\V1\Family\FamilyEventController;
use App\Http\Controllers\Api\V1\Family\FamilyEventGuestController;
use App\Http\Controllers\Api\V1\Family\FamilyEventInvitationController;
use App\Http\Controllers\Api\V1\Family\FamilyMedicationController;
use App\Http\Controllers\Api\V1\Family\FamilyMedicationLogController;
use App\Http\Controllers\Api\V1\Rewards\RewardItemController;
use App\Http\Controllers\Api\V1\Rewards\RewardRedeemController;
use App\Http\Controllers\Api\V1\Rewards\RewardSettingsController;
use App\Http\Controllers\Api\V1\Rewards\RewardsController;
use App\Http\Controllers\Api\V1\Family\ParentMessageController;
use App\Http\Controllers\Api\V1\Finance\ChildSupportController;
use App\Http\Controllers\Api\V1\Finance\FinanceController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Ocr\OcrController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\School\SchoolAdminController;
use App\Http\Controllers\Api\V1\School\SchoolAttendanceController;
use App\Http\Controllers\Api\V1\School\SchoolBroadcastController;
use App\Http\Controllers\Api\V1\School\SchoolEnrollmentController;
use App\Http\Controllers\Api\V1\Subscription\SubscriptionController;
use App\Http\Controllers\Api\V1\Subscription\WompiWebhookController;
use App\Http\Controllers\Api\V1\Support\SupportController;
use App\Http\Controllers\Api\V1\Shared\AttachmentFileController;
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

// Wompi: webhook público + launch/return WebView.
Route::post('/v1/webhooks/wompi', WompiWebhookController::class);
Route::get('/v1/subscriptions/wompi/pay/{payment}', [SubscriptionController::class, 'wompiPay']);
Route::get('/v1/subscriptions/wompi/return', [SubscriptionController::class, 'wompiReturn']);

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/join', [AuthController::class, 'join']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/device/verify', [AuthController::class, 'verifyDevice']);
    Route::get('/device/security-questions', [AuthController::class, 'securityQuestions']);
    Route::post('/mfa/verify', [MfaController::class, 'verify']);
    Route::post('/account/reactivate', [ProfileController::class, 'reactivate']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Adjuntos: solo auth + tenant (nunca device.recovery) para que el visor no falle.
Route::prefix('v1')->middleware(['api.auth', 'tenant'])->group(function () {
    Route::get('/attachments/{attachment}/file', [AttachmentFileController::class, 'show']);
});

Route::prefix('v1')->middleware(['api.auth', 'device.recovery', 'tenant'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/device/recovery', [AuthController::class, 'setupDeviceRecovery']);
    Route::get('/auth/device/security-questions', [AuthController::class, 'securityQuestions']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::get('/users/{user}/avatar', [ProfileController::class, 'showAvatar']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);
    Route::post('/profile/fcm-token', [ProfileController::class, 'updateFcmToken']);
    Route::get('/profile/plan-usage', [ProfileController::class, 'planUsage']);
    Route::post('/profile/mfa/setup', [ProfileController::class, 'setupMfa']);
    Route::post('/profile/mfa/enable', [ProfileController::class, 'enableMfa']);
    Route::post('/profile/mfa/disable', [ProfileController::class, 'disableMfa']);
    Route::get('/profile/notifications', [ProfileController::class, 'notificationPreferences']);
    Route::patch('/profile/notifications', [ProfileController::class, 'updateNotificationPreferences']);
    Route::post('/profile/account/deactivate', [ProfileController::class, 'deactivate']);
    Route::delete('/profile/account', [ProfileController::class, 'destroy']);

    Route::get('/families/memberships', [FamilyController::class, 'memberships']);
    Route::get('/families/activity-export', [FamilyController::class, 'activityExport']);
    Route::apiResource('families', FamilyController::class);
    Route::post('/families/{family}/invite', [FamilyController::class, 'invite']);
    Route::post('/families/{family}/children', [FamilyController::class, 'registerChild']);
    Route::put('/families/{family}/children/{child}/guardians', [FamilyController::class, 'syncChildGuardians']);
    Route::put('/families/{family}/members/{member}/child-access', [FamilyController::class, 'syncParentChildAccess']);
    Route::put('/families/{family}/members/{member}/access', [FamilyController::class, 'updateMemberAccess']);
    Route::get('/families/{family}/join-requests', [FamilyController::class, 'joinRequests']);
    Route::post('/families/{family}/join-requests/{joinRequest}/approve', [FamilyController::class, 'approveJoinRequest']);
    Route::post('/families/{family}/join-requests/{joinRequest}/reject', [FamilyController::class, 'rejectJoinRequest']);
    Route::post('/families/{family}/members/{member}/role', [FamilyController::class, 'assignRole']);
    Route::post('/families/{family}/members/{member}/deactivate', [FamilyController::class, 'deactivateMember']);
    Route::post('/families/{family}/members/{member}/reactivate', [FamilyController::class, 'reactivateMember']);
    Route::get('/families/{family}/messages', [ParentMessageController::class, 'index']);
    Route::post('/families/{family}/messages', [ParentMessageController::class, 'store']);
    Route::patch('/families/{family}/messages/{message}/read', [ParentMessageController::class, 'markRead']);

    Route::get('/events/invitations/me', [FamilyEventInvitationController::class, 'index']);
    Route::get('/events', [FamilyEventController::class, 'index']);
    Route::post('/events', [FamilyEventController::class, 'store']);
    Route::get('/events/{event}', [FamilyEventController::class, 'show']);
    Route::patch('/events/{event}', [FamilyEventController::class, 'update']);
    Route::post('/events/{event}/cancel', [FamilyEventController::class, 'cancel']);
    Route::get('/events/{event}/guests', [FamilyEventGuestController::class, 'index']);
    Route::post('/events/{event}/guests', [FamilyEventGuestController::class, 'store']);
    Route::put('/events/{event}/guests', [FamilyEventGuestController::class, 'sync']);
    Route::patch('/events/{event}/guests/{guest}/rsvp', [FamilyEventGuestController::class, 'rsvp']);

    Route::get('/medications', [FamilyMedicationController::class, 'index']);
    Route::post('/medications', [FamilyMedicationController::class, 'store']);
    Route::patch('/medications/{medication}', [FamilyMedicationController::class, 'update']);
    Route::post('/medications/{medication}/taken', [FamilyMedicationController::class, 'taken']);
    Route::get('/medications/{medication}/logs', [FamilyMedicationLogController::class, 'index']);

    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign']);
    Route::post('/tasks/{task}/attachments', [TaskController::class, 'storeAttachments']);
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment']);

    Route::get('/ocr', [OcrController::class, 'index']);
    Route::get('/ocr/usage', [OcrController::class, 'usage']);
    Route::post('/ocr/notebook', [OcrController::class, 'processNotebook']);
    Route::post('/ocr/document', [OcrController::class, 'processDocument']);
    Route::post('/ocr/invoice', [OcrController::class, 'processInvoice']);
    Route::get('/ocr/{document}', [OcrController::class, 'show']);

    Route::get('/transactions', [FinanceController::class, 'index']);
    Route::post('/transactions', [FinanceController::class, 'store']);
    Route::get('/transactions/{transaction}', [FinanceController::class, 'show']);
    Route::put('/transactions/{transaction}', [FinanceController::class, 'update']);
    Route::patch('/transactions/{transaction}', [FinanceController::class, 'update']);
    Route::delete('/transactions/{transaction}', [FinanceController::class, 'destroy']);
    Route::post('/transactions/{transaction}/attachments', [FinanceController::class, 'storeAttachments']);
    Route::delete('/transactions/{transaction}/attachments/{attachment}', [FinanceController::class, 'destroyAttachment']);

    Route::get('/budgets', [FinanceController::class, 'budgetsIndex']);
    Route::post('/budgets', [FinanceController::class, 'budgetsStore']);
    Route::put('/budgets/{budget}', [FinanceController::class, 'budgetsUpdate']);
    Route::patch('/budgets/{budget}', [FinanceController::class, 'budgetsUpdate']);
    Route::delete('/budgets/{budget}', [FinanceController::class, 'budgetsDestroy']);

    Route::get('/finance/reports/weekly', [FinanceController::class, 'weeklyReport']);
    Route::get('/finance/reports/monthly', [FinanceController::class, 'monthlyReport']);
    Route::get('/finance/reports/quarterly', [FinanceController::class, 'quarterlyReport']);
    Route::get('/finance/reports/annual', [FinanceController::class, 'annualReport']);

    Route::get('/finance/child-support', [ChildSupportController::class, 'index']);
    Route::post('/finance/child-support', [ChildSupportController::class, 'store']);
    Route::post('/finance/child-support/{agreement}/adjustments', [ChildSupportController::class, 'storeAdjustment']);
    Route::post('/finance/child-support/{agreement}/payments', [ChildSupportController::class, 'storePayment']);
    Route::post('/finance/child-support/{agreement}/end', [ChildSupportController::class, 'end']);
    Route::post('/finance/child-support/{agreement}/attachments', [ChildSupportController::class, 'storeAttachments']);

    Route::get('/rewards/summary', [RewardsController::class, 'summary']);
    Route::get('/rewards/items', [RewardItemController::class, 'index']);
    Route::post('/rewards/items', [RewardItemController::class, 'store']);
    Route::patch('/rewards/items/{item}', [RewardItemController::class, 'update']);
    Route::put('/rewards/settings', [RewardSettingsController::class, 'update']);
    Route::post('/rewards/redeem', [RewardRedeemController::class, 'store']);

    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);
    Route::get('/dashboard/task-daily-stats', [DashboardController::class, 'taskDailyStats']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/support/tickets', [SupportController::class, 'index']);
    Route::post('/support/tickets', [SupportController::class, 'store']);
    Route::get('/support/tickets/{ticket}', [SupportController::class, 'show']);
    Route::post('/support/tickets/{ticket}/messages', [SupportController::class, 'addMessage']);
    Route::patch('/support/tickets/{ticket}', [SupportController::class, 'update']);

    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);
    Route::get('/subscriptions/checkout-config', [SubscriptionController::class, 'checkoutConfig']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::get('/subscriptions/payment-method', [SubscriptionController::class, 'paymentMethod']);
    Route::delete('/subscriptions/payment-method', [SubscriptionController::class, 'deletePaymentMethod']);
    Route::get('/subscriptions/payment-methods', [SubscriptionController::class, 'paymentMethods']);
    Route::post('/subscriptions/payment-methods', [SubscriptionController::class, 'storePaymentMethod']);
    Route::patch('/subscriptions/payment-methods/{paymentMethod}/default', [SubscriptionController::class, 'setDefaultPaymentMethod']);
    Route::delete('/subscriptions/payment-methods/{paymentMethod}', [SubscriptionController::class, 'destroyPaymentMethod']);
    Route::get('/subscriptions/payments', [SubscriptionController::class, 'payments']);
    Route::get('/subscriptions/payments/{payment}', [SubscriptionController::class, 'showPayment']);
    Route::get('/subscriptions/payments/{payment}/receipt', [SubscriptionController::class, 'paymentReceipt']);
    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions/checkout/wompi', [SubscriptionController::class, 'checkoutWompi']);
    Route::post('/subscriptions/payments/{payment}/wompi-sync', [SubscriptionController::class, 'syncWompiPayment']);
    Route::post('/subscriptions/schedule-change', [SubscriptionController::class, 'scheduleChange']);
    Route::delete('/subscriptions/schedule-change', [SubscriptionController::class, 'cancelScheduledChange']);
    Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/resume', [SubscriptionController::class, 'resume']);

    Route::get('/school/lookup', [SchoolEnrollmentController::class, 'lookup']);
    Route::post('/school/register', [SchoolEnrollmentController::class, 'register']);
    Route::get('/school/enrollments', [SchoolEnrollmentController::class, 'index']);
    Route::post('/school/enrollments', [SchoolEnrollmentController::class, 'store']);
    Route::delete('/school/enrollments/{enrollment}', [SchoolEnrollmentController::class, 'destroy']);
    Route::get('/school/attendance/mine', [SchoolAttendanceController::class, 'index']);
    Route::post('/school/attendance', [SchoolAttendanceController::class, 'store']);

    Route::get('/school/teachers/schools', [SchoolBroadcastController::class, 'schools']);
    Route::get('/school/teachers/classes', [SchoolBroadcastController::class, 'classes']);
    Route::get('/school/broadcasts', [SchoolBroadcastController::class, 'index']);
    Route::post('/school/broadcasts', [SchoolBroadcastController::class, 'store']);
    Route::get('/school/broadcasts/{broadcast}', [SchoolBroadcastController::class, 'show']);

    Route::post('/school/admin/register', [SchoolAdminController::class, 'registerInstitution']);
    Route::post('/school/staff/accept-invite', [SchoolAdminController::class, 'acceptStaffInvite']);
    Route::post('/school/students/document', [SchoolAdminController::class, 'updateStudentDocument']);

    Route::get('/school/admin/{school}/overview', [SchoolAdminController::class, 'overview']);
    Route::get('/school/admin/{school}/campuses', [SchoolAdminController::class, 'listCampuses']);
    Route::post('/school/admin/{school}/campuses', [SchoolAdminController::class, 'storeCampus']);
    Route::patch('/school/admin/{school}/campuses/{campus}', [SchoolAdminController::class, 'updateCampus']);
    Route::get('/school/admin/{school}/classes', [SchoolAdminController::class, 'listClasses']);
    Route::post('/school/admin/{school}/classes', [SchoolAdminController::class, 'storeClass']);
    Route::get('/school/admin/{school}/students', [SchoolAdminController::class, 'listStudents']);
    Route::post('/school/admin/{school}/staff/invite', [SchoolAdminController::class, 'inviteStaff']);
    Route::get('/school/admin/{school}/staff', [SchoolAdminController::class, 'listStaff']);
    Route::get('/school/admin/{school}/staff/invites', [SchoolAdminController::class, 'listStaffInvites']);
    Route::patch('/school/admin/{school}/staff/{membership}', [SchoolAdminController::class, 'updateStaffMembership']);
    Route::get('/school/admin/{school}/students/lookup', [SchoolAdminController::class, 'lookupStudentByDocument']);
    Route::post('/school/admin/{school}/students/enroll', [SchoolAdminController::class, 'enrollByDocument']);
    Route::get('/school/admin/{school}/groups', [SchoolAdminController::class, 'listGroups']);
    Route::post('/school/admin/{school}/groups', [SchoolAdminController::class, 'storeGroup']);
    Route::put('/school/admin/{school}/groups/{group}/members', [SchoolAdminController::class, 'syncGroupMembers']);
    Route::post('/school/admin/{school}/announce', [SchoolAdminController::class, 'announce']);
    Route::post('/school/admin/{school}/meetings', [SchoolAdminController::class, 'storeMeeting']);
    Route::get('/school/admin/{school}/meetings', [SchoolAdminController::class, 'listMeetings']);
    Route::post('/school/admin/{school}/schedules', [SchoolAdminController::class, 'storeSchedule']);
    Route::get('/school/admin/{school}/schedules', [SchoolAdminController::class, 'listSchedules']);
    Route::post('/school/admin/{school}/report-sick', [SchoolAdminController::class, 'reportSick']);
    Route::post('/school/admin/{school}/cite-parents', [SchoolAdminController::class, 'citeParents']);
    Route::post('/school/admin/{school}/teacher-tasks', [SchoolAdminController::class, 'storeTeacherTask']);
    Route::get('/school/admin/{school}/teacher-tasks', [SchoolAdminController::class, 'listTeacherTasks']);
    Route::post('/school/admin/{school}/psych-cases', [SchoolAdminController::class, 'storePsychCase']);
    Route::get('/school/admin/{school}/psych-cases', [SchoolAdminController::class, 'listPsychCases']);
    Route::post('/school/admin/{school}/health-alerts', [SchoolAdminController::class, 'storeHealthAlert']);
    Route::get('/school/admin/{school}/health-alerts', [SchoolAdminController::class, 'listHealthAlerts']);
    Route::get('/school/admin/{school}/subscription', [SchoolAdminController::class, 'schoolSubscription']);
    Route::patch('/school/admin/{school}/subscription', [SchoolAdminController::class, 'updateSchoolSubscription']);

    Route::post('/school/meetings/{meeting}/rsvp', [SchoolAdminController::class, 'respondMeeting']);
    Route::get('/school/meetings/mine', [SchoolAdminController::class, 'myMeetings']);
    Route::patch('/school/schedules/{schedule}', [SchoolAdminController::class, 'updateSchedule']);
    Route::post('/school/schedules/{schedule}/share', [SchoolAdminController::class, 'shareSchedule']);
    Route::patch('/school/teacher-tasks/{task}', [SchoolAdminController::class, 'updateTeacherTask']);
    Route::post('/school/psych-cases/{case}/notes', [SchoolAdminController::class, 'addPsychNote']);
});
