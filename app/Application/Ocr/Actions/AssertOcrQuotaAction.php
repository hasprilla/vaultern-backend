<?php

declare(strict_types=1);

namespace App\Application\Ocr\Actions;

use App\Models\Family;
use App\Models\OcrJob;
use App\Models\User;
use App\Services\PlanFeatureService;

final class AssertOcrQuotaAction
{
    public function __construct(private readonly PlanFeatureService $planFeatures) {}

    /**
     * @return array{ok: false, status: int, message: string, code?: string}|null
     */
    public function execute(User $actor): ?array
    {
        $family = Family::query()->findOrFail($actor->family_id);
        $limit = $this->planFeatures->familyFeatureLimit($family, 'ocr_scans_monthly', 5);
        $used = OcrJob::query()
            ->where('family_id', $family->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used < $limit) {
            return null;
        }

        return [
            'ok' => false,
            'status' => 422,
            'message' => "Alcanzaste el límite de {$limit} escaneos OCR este mes. Mejora tu plan para continuar.",
            'code' => 'ocr_limit_reached',
        ];
    }
}
