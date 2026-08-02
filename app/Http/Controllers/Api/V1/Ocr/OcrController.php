<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ocr;

use App\Application\Ocr\Actions\ProcessOcrJobAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Models\Family;
use App\Models\OcrJob;
use App\Services\PlanFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly ProcessOcrJobAction $processOcrJob,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = OcrJob::query()->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate($this->perPage($request)));
    }

    public function usage(Request $request): JsonResponse
    {
        $family = Family::query()->findOrFail($request->user()->family_id);
        $limit = $this->planFeatures->familyFeatureLimit($family, 'ocr_scans_monthly', 5);
        $used = OcrJob::query()
            ->where('family_id', $family->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'data' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'month' => now()->format('Y-m'),
            ],
        ]);
    }

    public function processNotebook(Request $request): JsonResponse
    {
        return $this->process($request, 'handwriting');
    }

    public function processDocument(Request $request): JsonResponse
    {
        return $this->process($request, 'document');
    }

    public function processInvoice(Request $request): JsonResponse
    {
        return $this->process($request, 'invoice');
    }

    public function show(string $document): JsonResponse
    {
        $job = OcrJob::query()->findOrFail($document);

        return response()->json(['data' => $job]);
    }

    private function process(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'file_path' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string'],
        ]);

        $storedPath = $request->input('file_path');
        $mimeType = $request->input('mime_type');

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storedPath = $uploaded->store('ocr/'.$request->user()->family_id, 'public');
            $mimeType = $uploaded->getClientMimeType();
        }

        $result = $this->processOcrJob->execute(
            $request->user(),
            $type,
            is_string($storedPath) ? $storedPath : null,
            is_string($mimeType) ? $mimeType : null,
        );

        if (($result['ok'] ?? false) !== true) {
            $payload = ['message' => $result['message']];
            if (isset($result['code'])) {
                $payload['code'] = $result['code'];
            }

            return response()->json($payload, $result['status']);
        }

        return response()->json(['data' => $result['job']], 202);
    }
}
