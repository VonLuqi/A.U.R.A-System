<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Self-service profile mutations (Etapa I — PLAN_PERFIL_BRANDING §2.5 / §2.8).
 *
 * Segurança (§2.8):
 * - Arquivo nomeado com `Str::uuid()` (nunca o nome original do cliente).
 * - Path sempre sob `avatars/{user_id}/` no disk `public` (sem path traversal).
 * - Payload API expõe só `avatar_url` — `avatar_path` fica hidden no Model.
 * - Upload validado em `UploadAvatarRequest` (`image` + `mimes`) + throttle `10,1`.
 */
final class ProfileService
{
    private const DISK = 'public';

    /**
     * @param  array{
     *     name?: string,
     *     password?: string,
     *     expense_cycle_day?: int,
     *     income_cycle_day?: int
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('expense_cycle_day', $data)) {
            $user->expense_cycle_day = (int) $data['expense_cycle_day'];
        }

        if (array_key_exists('income_cycle_day', $data)) {
            $user->income_cycle_day = (int) $data['income_cycle_day'];
        }

        if (array_key_exists('password', $data) && is_string($data['password']) && $data['password'] !== '') {
            $user->password = $data['password'];
        }

        $user->save();

        return $user->fresh();
    }

    public function storeAvatar(User $user, UploadedFile $file): User
    {
        $extension = $this->normalizedExtension($file);
        $directory = 'avatars/'.$user->id;
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, self::DISK);

        if ($path === false) {
            throw new \RuntimeException('Falha ao gravar o avatar.');
        }

        $this->deleteAvatarFile($user->avatar_path);

        $user->avatar_path = $path;
        $user->save();

        return $user->fresh();
    }

    public function destroyAvatar(User $user): User
    {
        $this->deleteAvatarFile($user->avatar_path);

        if ($user->avatar_path !== null) {
            $user->avatar_path = null;
            $user->save();
        }

        return $user->fresh();
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function normalizedExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->extension());

        return match ($ext) {
            'jpeg' => 'jpg',
            'jpg', 'png', 'webp' => $ext,
            default => match ($file->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            },
        };
    }
}
