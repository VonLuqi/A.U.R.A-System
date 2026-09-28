<?php

namespace App\Parsers;

use App\DTOs\ParsedTransaction;
use App\DTOs\ParseResult;
use App\Exceptions\InvalidStatementException;
use App\Parsers\Contracts\StatementParserInterface;
use App\Support\Money;
use CihanSenturk\OfxParser\Entities\Transaction as OfxTransaction;
use CihanSenturk\OfxParser\Exceptions\ParseException;
use CihanSenturk\OfxParser\Parser as LibOfxParser;
use DateTimeInterface;
use InvalidArgumentException;
use SplFileInfo;
use Throwable;

/**
 * OFX/QFX adapter (Etapa C §3.5).
 *
 * Wraps cihansenturk/ofxparser (maintained fork of asgrim/ofxparser) and maps
 * bank/credit-card STMTTRN nodes to ParsedTransaction.
 */
final class OfxParser implements StatementParserInterface
{
    public function __construct(
        private readonly ?LibOfxParser $lib = null,
    ) {}

    public function supports(string $format, string $source): bool
    {
        return in_array($format, ['ofx', 'qfx'], true) && $source === 'nubank';
    }

    public function parse(SplFileInfo|string $file): ParseResult
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            throw new InvalidStatementException('Arquivo de extrato ilegível ou inexistente.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            throw new InvalidStatementException('Não foi possível ler o arquivo de extrato.');
        }

        try {
            $ofx = $this->lib()->loadFromString($contents);
        } catch (ParseException|InvalidArgumentException $e) {
            throw new InvalidStatementException(
                'Extrato OFX inválido ou ilegível.',
                'invalid_statement',
                $e,
            );
        } catch (Throwable $e) {
            throw new InvalidStatementException(
                'Falha ao interpretar o extrato OFX.',
                'invalid_statement',
                $e,
            );
        }

        $transactions = [];
        $rowErrors = [];
        $rowsTotal = 0;
        $lineNumber = 0;

        foreach ($ofx->bankAccounts ?? [] as $account) {
            foreach ($account->statement->transactions ?? [] as $ofxTx) {
                $lineNumber++;
                $rowsTotal++;

                try {
                    $mapped = $this->mapTransaction($ofxTx);
                    if ($mapped === null) {
                        $rowErrors[] = ['line' => $lineNumber, 'message' => 'Valor zero ignorado.'];

                        continue;
                    }
                    $transactions[] = $mapped;
                } catch (InvalidArgumentException $e) {
                    $rowErrors[] = ['line' => $lineNumber, 'message' => $e->getMessage()];
                }
            }
        }

        if ($rowsTotal === 0 && $transactions === []) {
            // Structurally parsed but no accounts/transactions — treat as invalid for MVP.
            throw new InvalidStatementException('Extrato OFX sem transações legíveis.');
        }

        return new ParseResult(
            transactions: $transactions,
            rowsTotal: $rowsTotal,
            rowErrors: $rowErrors,
            format: 'ofx',
            source: 'nubank',
        );
    }

    private function lib(): LibOfxParser
    {
        return $this->lib ?? new LibOfxParser;
    }

    private function mapTransaction(OfxTransaction $ofxTx): ?ParsedTransaction
    {
        if (! $ofxTx->date instanceof DateTimeInterface) {
            throw new InvalidArgumentException('Data OFX ausente ou inválida.');
        }

        $occurredOn = $ofxTx->date->format('Y-m-d');
        $signed = Money::parseOfxSigned($ofxTx->amount);

        if ($signed['amount'] === '0.00') {
            return null;
        }

        $memo = trim((string) $ofxTx->memo);
        $name = trim((string) $ofxTx->name);
        $description = $memo !== '' ? $memo : $name;

        if ($description === '') {
            throw new InvalidArgumentException('Descrição OFX vazia (MEMO/NAME).');
        }

        $externalId = trim((string) $ofxTx->uniqueId);
        $externalId = $externalId !== '' ? $externalId : null;

        return new ParsedTransaction(
            occurredOn: $occurredOn,
            description: $description,
            amount: $signed['amount'],
            type: $signed['type'],
            externalId: $externalId,
            rawPayload: [
                'DTPOSTED' => $ofxTx->date->format('Ymd'),
                'TRNAMT' => (string) $ofxTx->amount,
                'MEMO' => $memo,
                'NAME' => $name,
                'FITID' => (string) $ofxTx->uniqueId,
                'TRNTYPE' => (string) $ofxTx->type,
            ],
        );
    }
}
