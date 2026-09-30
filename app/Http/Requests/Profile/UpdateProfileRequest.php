<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/profile — self-service (Etapa I §2.3).
 *
 * Aceita apenas `name` e troca de senha. E-mail, role, cotas e is_active
 * não entram nas rules (ignorados se enviados).
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'current_password' => ['required_with:password', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.min' => 'O nome deve ter no mínimo 2 caracteres.',
            'name.max' => 'O nome deve ter no máximo 120 caracteres.',
            'password.required' => 'Informe a nova senha.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'current_password.required_with' => 'Informe a senha atual para definir uma nova.',
            'current_password.current_password' => 'A senha atual está incorreta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'password' => 'senha',
            'password_confirmation' => 'confirmação da senha',
            'current_password' => 'senha atual',
        ];
    }
}
