# Fixtures — Extratos (Aura)

> Path canônico (Etapa C §7.1): `tests/Fixtures/statements/...`  
> Nos testes: `base_path('tests/Fixtures/statements/...')`. **Não** usar `tests/fixtures/` (minúsculo).

## Política de anonimização (§7.2)

**Nunca** versionar extratos reais com PII. Fixtures neste diretório são **sintéticas**, no formato do export Nubank, sem dados de pessoas reais.

| Regra | Como aplicar |
| --- | --- |
| Origem do formato | Espelha export de **conta** Nubank (app/site → CSV). **Não** é fatura de cartão de crédito. |
| Nomes / PIX / e-mails | Placeholders: `[PESSOA A]`, `[PESSOA B]`, `[PIX_KEY]`, `[EMPRESA]` |
| CPF / CNPJ | Removidos (não devem aparecer) |
| Datas / valores | Mantêm formato real (`dd/mm/yyyy`, `1.234,56` / `-89,90`) |
| Descrições comerciais | Genéricas (`Supermercado Extra`, `Uber *Trip`, `Netflix.Com`) |
| Identificadores | UUIDs / tokens fictícios (`00000000-…`, `abc-001`, `nubank-fitid-001`) |
| Conta OFX | `ACCTID` anon (`0001-ANON`); org `NUBANK` / FID `260` são públicos |

Checklist antes de adicionar fixture:

1. Substituir nomes de pessoas, chaves PIX e e-mails por placeholders.
2. Remover CPF/CNPJ (e qualquer documento).
3. Manter só datas/valores/descrições comerciais genéricas.
4. Rodar `php artisan test --filter=FixtureAnonymizationTest`.

## Perfil MVP — Nubank conta CSV (§3.4.1)

Fonte do **formato**: exportação de **extrato de conta** do Nubank (não cartão de crédito).  
Headers observados no export de conta: `Data`, `Valor`, `Identificador`, `Descrição`.

| Header (case-insensitive) | Campo Aura |
| --- | --- |
| `Data` | `occurredOn` (`DateNormalizer::fromBrazilian`) |
| `Valor` | `amount` + `type` via sinal (`Money::parseBrazilianSigned`) |
| `Identificador` | `externalId` (opcional) |
| `Descrição` | `description` |

### Regras de arquivo

- UTF-8 com BOM opcional (`EF BB BF`)
- Delimiter `,` (auto-detect `;` se o header exigir)
- Enclosure `"`
- Fallback de encoding: Windows-1252 → UTF-8
- Linhas vazias ignoradas
- Header obrigatório: `Data`, `Valor`, `Descrição` (`Identificador` opcional)

### Regras de tipo (§3.4.2)

| Valor no CSV | `type` | `amount` |
| --- | --- | --- |
| Negativo (ex. `-1.234,56`) | `debit` | absoluto (`1234.56`) |
| Positivo (ex. `10,00`) | `credit` | absoluto (`10.00`) |
| Zero (`0,00`) | — | linha **ignorada** + `rowError` `"Valor zero ignorado."` |

### Desafios (§3.4.4)

- Money pt-BR (`1.234,56`) via `Money::parseBrazilianSigned`
- Datas `dd/mm/yyyy` → `Y-m-d` via `DateNormalizer`
- Descrições com vírgulas/aspas via `fgetcsv` enclosure
- Linhas curtas/inválidas → `rowError` (arquivo não aborta; header inválido sim)
- Hash **não** é gerado no parser — `TransactionHasher` fica no `StatementUploadService` (sem `statement_import_id`)

### Arquivos

| Arquivo | Propósito |
| --- | --- |
| `nubank/sample_account.csv` | Happy path mínimo (tests de parser; sintético) |
| `nubank/sample_account_bom.csv` | Mesmo conteúdo com BOM UTF-8 |
| `nubank/sample_account_semicolon.csv` | Delimiter `;` |
| `nubank/sample_account_missing_header.csv` | Sem coluna `Valor` → fatal |
| `nubank/sample_account_invalid_rows.csv` | Linhas curtas / valor inválido + válidas (§3.4.4) |
| `nubank/sample_account_realistic_anon.csv` | Amostra estendida estilo conta (PIX/boleto com placeholders §7.2) |

## Perfil MVP — OFX / QFX (§3.5)

Lib: `cihansenturk/ofxparser` (SGML OFX bancário BR + QFX).

| OFX | Aura |
| --- | --- |
| `DTPOSTED` | `occurredOn` (`Y-m-d`) |
| `TRNAMT` | `amount` + `type` via sinal (`Money::parseOfxSigned`) |
| `MEMO` / `NAME` | `description` (preferir MEMO) |
| `FITID` | `externalId` |

| Arquivo | Propósito |
| --- | --- |
| `ofx/sample_nubank.ofx` | Happy path SGML anonimizado (débito/crédito/MEMO/NAME/zero) |
| `ofx/sample_malformed.ofx` | Sem `<OFX>` → `InvalidStatementException` |
