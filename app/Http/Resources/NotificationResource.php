<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * In-app notification payload (PLAN_CARTOES_EMPRESTIMOS §5).
 *
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array{id: string, type: string, data: array<string, mixed>, read_at: string|null, created_at: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->data) ? $this->data : [];

        $type = $data['type'] ?? null;
        if (! is_string($type) || $type === '') {
            $type = class_basename((string) $this->type);
        }

        return [
            'id' => (string) $this->id,
            'type' => $type,
            'data' => $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
