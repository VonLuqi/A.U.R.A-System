<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/profile/avatar — Etapa I §2.4.
 *
 * Aceita apenas jpeg/jpg/png/webp até 2 MB. SVG/GIF/BMP rejeitados (XSS / formatos fora do escopo).
 */
class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Envie uma imagem de perfil.',
            'avatar.file' => 'O avatar deve ser um arquivo válido.',
            'avatar.image' => 'O avatar deve ser uma imagem.',
            'avatar.mimes' => 'Use JPEG, PNG ou WebP (máx. 2 MB).',
            'avatar.max' => 'O avatar deve ter no máximo 2 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'avatar',
        ];
    }
}
