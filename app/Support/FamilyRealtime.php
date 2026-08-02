<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\FamilyPermissionsChanged;
use App\Events\FamilyStatsInvalidated;
use App\Events\FinanceChanged;
use App\Events\OcrJobUpdated;
use App\Events\TaskChanged;
use Illuminate\Support\Facades\Cache;

/** Orquesta broadcast familiar + invalidación de cache de analytics. */
final class FamilyRealtime
{
    public static function taskChanged(
        string $familyId,
        string $taskId,
        string $action,
        ?string $status = null,
        ?string $title = null,
        ?int $assigneeId = null,
        ?int $actorId = null,
    ): void {
        event(new TaskChanged(
            familyId: $familyId,
            taskId: $taskId,
            action: $action,
            status: $status,
            title: $title,
            assigneeId: $assigneeId,
            actorId: $actorId,
        ));

        self::invalidateStats($familyId, 'task');
    }

    public static function financeChanged(
        string $familyId,
        string $entityType,
        string $entityId,
        string $action,
        ?int $actorId = null,
        ?int $childId = null,
    ): void {
        event(new FinanceChanged(
            familyId: $familyId,
            entityType: $entityType,
            entityId: $entityId,
            action: $action,
            actorId: $actorId,
            childId: $childId,
        ));

        self::invalidateStats($familyId, 'finance');
    }

    public static function ocrJobUpdated(
        string $familyId,
        int $userId,
        string $jobId,
        string $status,
        string $ocrType,
        string $action = 'completed',
    ): void {
        event(new OcrJobUpdated(
            familyId: $familyId,
            userId: $userId,
            jobId: $jobId,
            status: $status,
            ocrType: $ocrType,
            action: $action,
        ));
    }

    /**
     * @param  list<int|string>  $childIds
     * @param  list<int|string>  $guardianIds
     */
    public static function permissionsChanged(
        string $familyId,
        string $action,
        ?string $childId = null,
        ?string $parentId = null,
        array $childIds = [],
        array $guardianIds = [],
        ?int $actorId = null,
    ): void {
        event(new FamilyPermissionsChanged(
            familyId: $familyId,
            action: $action,
            childId: $childId,
            parentId: $parentId,
            childIds: $childIds,
            guardianIds: $guardianIds,
            actorId: $actorId,
        ));

        self::invalidateStats($familyId, 'permissions');
    }

    public static function invalidateStats(string $familyId, string $reason = 'changed'): void
    {
        event(new FamilyStatsInvalidated($familyId, $reason));

        // Version bump: funciona con CACHE_STORE=file (cPanel) y redis (VPS).
        $key = self::analyticsVersionKey($familyId);
        Cache::put($key, ((int) Cache::get($key, 0)) + 1, now()->addDays(7));
    }

    public static function analyticsVersionKey(string $familyId): string
    {
        return "family.{$familyId}.analytics.version";
    }

    public static function analyticsCacheKey(string $familyId, string $period, int $userId, bool $canFinance): string
    {
        $version = (int) Cache::get(self::analyticsVersionKey($familyId), 0);

        return "family.{$familyId}.analytics.{$period}.v{$version}.u{$userId}.f".($canFinance ? '1' : '0');
    }
}
