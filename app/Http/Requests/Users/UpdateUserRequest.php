<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()?->can('update', $target) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'password' => ['sometimes', 'string', 'min:8', 'max:255'],
            'role' => ['sometimes', 'string', Rule::in(UserRole::assignableViaApi())],
            'is_active' => ['sometimes', 'boolean'],
            'reset_usage' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $target */
            $target = $this->route('user');

            if ($target->isAdmin()) {
                $validator->errors()->add(
                    'user',
                    'Contas Admin não podem ser alteradas por esta API. Use seeders/ops.'
                );
            }

            if ($this->has('role') && $this->input('role') === UserRole::Admin->value) {
                $validator->errors()->add(
                    'role',
                    'Não é permitido promover usuários a Admin pela API.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Papel inválido. Use subadmin, visitor ou test.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
        ];
    }
}
