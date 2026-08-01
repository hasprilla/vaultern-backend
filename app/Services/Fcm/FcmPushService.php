<?php

declare(strict_types=1);

namespace App\Services\Fcm;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    public function __construct(private readonly FcmAccessTokenProvider $tokens) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(User $user, string $title, string $body, string $type, array $data = []): void
    {
        if (! $this->shouldSend($user, $type)) {
            return;
        }

        $tokens = Device::query()
            ->where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $payload = array_merge($data, [
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
        ]);

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool true si se intentó enviar a al menos un token con credenciales válidas
     */
    public function sendVerificationCode(User $user, string $code): bool
    {
        $tokens = Device::query()
            ->where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            Log::error('FCM verificación omitido: el usuario no tiene fcm_token en devices.', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            return false;
        }

        if ($this->tokens->get(true) === null) {
            Log::error('FCM verificación omitido: sin credenciales Firebase.', [
                'credentials' => config('firebase.credentials'),
            ]);

            return false;
        }

        $title = 'Código de verificación';
        $body = "Tu código Zumifly es: {$code}";
        $data = [
            'type'  => 'email_verification',
            'code'  => $code,
            'email' => $user->email,
            'title' => $title,
            'body'  => $body,
        ];

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data, force: true);
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public function sendToToken(string $token, string $title, string $body, array $data = [], bool $force = false): void
    {
        if (! $force && ! config('firebase.enabled')) {
            return;
        }

        $accessToken = $this->tokens->get($force);
        if ($accessToken === null) {
            if ($force) {
                Log::error('FCM verificación omitido: sin credenciales Firebase.', [
                    'credentials' => config('firebase.credentials'),
                ]);
            }

            return;
        }

        $projectId = config('firebase.project_id');
        $stringData = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $stringData[(string) $key] = (string) $value;
            }
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'zumifly_alerts',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('FCM push falló', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    private function shouldSend(User $user, string $type): bool
    {
        $prefs = $user->resolvedNotificationPreferences();
        if (! ($prefs['push_enabled'] ?? true)) {
            return false;
        }

        if (str_starts_with($type, 'task_')) {
            return $prefs['tasks'] ?? true;
        }
        if (str_starts_with($type, 'finance_')) {
            return $prefs['finance'] ?? true;
        }
        if (str_starts_with($type, 'family_') || str_contains($type, 'join')) {
            return $prefs['family'] ?? true;
        }
        if (str_starts_with($type, 'ocr_')) {
            return $prefs['reminders'] ?? true;
        }

        if (str_starts_with($type, 'school_')) {
            return $prefs['family'] ?? true;
        }
        if (str_starts_with($type, 'support_')) {
            return $prefs['reminders'] ?? true;
        }
        if (str_starts_with($type, 'subscription_')) {
            return $prefs['finance'] ?? true;
        }
        if (str_starts_with($type, 'profile_')) {
            return $prefs['family'] ?? true;
        }

        return true;
    }
}
