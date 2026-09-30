<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;

class IndexNotificationsRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 30;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DatabaseNotification::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unread' => ['sometimes', 'nullable'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function unreadOnly(): bool
    {
        if (! $this->exists('unread')) {
            return false;
        }

        return filter_var($this->input('unread'), FILTER_VALIDATE_BOOLEAN);
    }

    public function limit(): int
    {
        $value = $this->validated('limit');

        return $value !== null ? (int) $value : self::DEFAULT_LIMIT;
    }
}
