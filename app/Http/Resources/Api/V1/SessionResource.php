<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token'  => $this->resource['access_token'],
            'refresh_token' => $this->resource['refresh_token'],
            'expires_at'    => $this->resource['expires_at'] ?? null,
            'user'          => new UserResource($this->resource['user']),
        ];
    }
}
