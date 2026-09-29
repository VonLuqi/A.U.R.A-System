<?php

namespace App\Http\Requests\Statements;

use App\Models\StatementImport;
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
        return $this->user()?->can('create', StatementImport::class) ?? false;
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
            'source' => [
                'sometimes',
                'string',
                'in:'.implode(',', StatementFormatDetector::ALLOWED_SOURCES),
            ],
            'statement_kind' => [
                'sometimes',
                'nullable',
                'string',
                'in:'.implode(',', StatementFormatDetector::ALLOWED_KINDS),
            ],
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
            'source.in' => 'Fonte inválida. Use nubank, nubank_credit ou other.',
            'statement_kind.in' => 'Tipo de extrato inválido. Use checking ou credit_card.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $kind = StatementFormatDetector::normalizeKind($this->input('statement_kind'));
            $source = StatementFormatDetector::normalizeSource($this->input('source'));
            $wantsCreditCard = $kind === 'credit_card' || $source === 'nubank_credit';

            if ($wantsCreditCard && ! config('aura.features.credit_card_upload', true)) {
                $validator->errors()->add(
                    'statement_kind',
                    'Upload de fatura de cartão está temporariamente desabilitado.'
                );

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
     * Detected format for parsers / statement_imports: csv | csv_credit_card | ofx.
     */
    public function detectedFormat(): string
    {
        return StatementFormatDetector::detect(
            $this->file('file'),
            $this->input('source'),
            $this->input('statement_kind'),
        );
    }

    public function source(): string
    {
        return StatementFormatDetector::normalizeSource($this->input('source'));
    }

    public function statementKind(): ?string
    {
        return StatementFormatDetector::normalizeKind($this->input('statement_kind'));
    }

    /**
     * @return array{format: string, source: string}
     */
    public function detection(): array
    {
        return StatementFormatDetector::detectForImport(
            $this->file('file'),
            $this->input('source'),
            $this->input('statement_kind'),
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
