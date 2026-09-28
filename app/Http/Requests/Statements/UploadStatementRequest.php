<?php

namespace App\Http\Requests\Statements;

use App\Support\StatementFormatDetector;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class UploadStatementRequest extends FormRequest
{
    /** Max upload size in kilobytes (10 MB) — below typical HostGator / PHP 20M ini. */
    public const MAX_KILOBYTES = 10240;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['csv', 'ofx', 'qfx'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_KILOBYTES,
                // MIME sniffing is unreliable for CSV/OFX; extensions enforced in withValidator.
                'mimes:csv,txt,ofx,xml',
            ],
            'source' => ['sometimes', 'string', 'in:nubank'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Envie um arquivo de extrato.',
            'file.file' => 'O upload deve ser um arquivo válido.',
            'file.max' => 'O arquivo deve ter no máximo 10 MB.',
            'file.mimes' => 'Formato não suportado. Use CSV ou OFX (também .qfx).',
            'source.in' => 'Fonte inválida. No MVP apenas nubank é aceita.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var UploadedFile|null $file */
            $file = $this->file('file');

            if (! $file instanceof UploadedFile) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $validator->errors()->add(
                    'file',
                    'Extensão inválida. Permitidas: csv, ofx, qfx.'
                );
            }
        });
    }

    /**
     * Detected format for parsers / statement_imports: csv | ofx (.qfx → ofx).
     */
    public function detectedFormat(): string
    {
        return StatementFormatDetector::detect($this->file('file'));
    }

    public function source(): string
    {
        return StatementFormatDetector::normalizeSource($this->input('source'));
    }

    /**
     * @return array{format: 'csv'|'ofx', source: string}
     */
    public function detection(): array
    {
        return StatementFormatDetector::detectForImport(
            $this->file('file'),
            $this->input('source'),
        );
    }

    /**
     * @throws ValidationException
     */
    protected function failedAuthorization(): void
    {
        throw ValidationException::withMessages([
            'file' => ['Não autorizado.'],
        ]);
    }
}
