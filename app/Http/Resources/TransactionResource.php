<?php

namespace App\Http\Resources;

use App\Models\TransactionAlias;
use App\Services\AliasResolutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable transaction row for transactions API (Etapa C §5.4.3 / PLAN_EXPANSAO §3.1).
 *
 * @mixin \App\Models\Transaction
 */
class TransactionResource extends JsonResource
{
    /** @var array<int, string|null> */
    private static array $aliasDisplayCache = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        $originalDescription = isset($payload['original_description'])
            && is_string($payload['original_description'])
            && $payload['original_description'] !== ''
            ? $payload['original_description']
            : null;

        return [
            'id' => (int) $this->id,
            'occurred_on' => $this->occurred_on?->format('Y-m-d') ?? (string) $this->occurred_on,
            'description' => (string) $this->description,
            'original_description' => $originalDescription,
            'alias' => $this->resolveAlias($request, $payload, $originalDescription),
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'type' => (string) $this->type,
            'category' => $this->when(
                $this->relationLoaded('category') && $this->category !== null,
                fn () => (new CategoryResource($this->category))->resolve(),
                null,
            ),
            'user_id' => (int) $this->user_id,
            'source_kind' => $this->source_kind instanceof \BackedEnum
                ? $this->source_kind->value
                : (string) $this->source_kind,
            'statement_import_id' => $this->statement_import_id !== null
                ? (int) $this->statement_import_id
                : null,
            'external_id' => $this->external_id !== null ? (string) $this->external_id : null,
            'notes' => $payload['notes'] ?? null,
            'editable' => $user !== null && $user->can('update', $this->resource),
            'deletable' => $user !== null && $user->can('delete', $this->resource),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int, display_name: string}|null
     */
    private function resolveAlias(Request $request, array $payload, ?string $originalDescription): ?array
    {
        $aliasId = isset($payload['alias_id']) ? (int) $payload['alias_id'] : null;

        if ($aliasId !== null && $aliasId > 0) {
            $displayName = null;

            if ($originalDescription !== null && (string) $this->description !== $originalDescription) {
                $displayName = (string) $this->description;
            } else {
                $displayName = $this->cachedAliasDisplayName($aliasId);
            }

            if ($displayName !== null && $displayName !== '') {
                return [
                    'id' => $aliasId,
                    'display_name' => $displayName,
                ];
            }
        }

        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $raw = $originalDescription ?? (string) $this->description;
        if ($raw === '') {
            return null;
        }

        $match = app(AliasResolutionService::class)->resolve($user, $raw);
        if ($match === null) {
            return null;
        }

        return [
            'id' => $match->aliasId,
            'display_name' => $match->displayName,
        ];
    }

    private function cachedAliasDisplayName(int $aliasId): ?string
    {
        if (! array_key_exists($aliasId, self::$aliasDisplayCache)) {
            self::$aliasDisplayCache[$aliasId] = TransactionAlias::query()
                ->whereKey($aliasId)
                ->value('display_name');
        }

        $name = self::$aliasDisplayCache[$aliasId];

        return is_string($name) && $name !== '' ? $name : null;
    }
}
