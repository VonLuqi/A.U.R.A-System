# PLAN_EXPANSAO — Aura (Pós-MVP)

> Plano de execução técnico dos **6 requisitos** de expansão.  
> Stack: **Laravel 11** · **React 19** · **Vite 6** · session auth (`web`) · MySQL.  
> Roadmap: `docs/MASTER_PLAN.md` (Etapas F–G) · Contexto: `docs/context.md`.  
> Padrões existentes a reutilizar: Services (sem Repository), FormRequests, API Resources, Policies, `StatementParserInterface` + Resolver, `TransactionQueryService::baseForUser`.

**Status**


| Bloco | Foco                                  | Status                                             |
| ----- | ------------------------------------- | -------------------------------------------------- |
| 0     | Pré-requisitos e contratos            | Concluído                                          |
| 1     | Banco de Dados (migrations / seeders) | Concluído (§1.1–1.6)                               |
| 2     | Multi-usuário, RBAC e cotas           | Concluído (§2.1–2.4 ✔)                             |
| 3     | CRUD manual de transações             | Concluído (§3.1–3.3 ✔)                             |
| 4     | Motor de Apelidos/Regras (Aliases)    | Concluído (§4.1–4.3 ✔)                             |
| 5     | Parser CSV Cartão de Crédito          | Concluído (§5.1–5.3 ✔; OFX cartão = backlog)       |
| 6     | Date Range Picker flexível            | Concluído (§6.1–6.2 ✔; UI picker avançado em §8.3) |
| 7     | Sistema de Metas Financeiras          | Concluído (§7.1–7.3 ✔)                             |
| 8     | Frontend (UI)                         | Concluído (§8.1–8.7 ✔)                             |
| 9     | Testes, DoD e Deploy                  | Concluído (§9.1–9.3 ✔; cutover live = operador §5.6) |


---

## 0. Pré-requisitos e contratos

- [x] Atualizar `docs/context.md`: remover “uso exclusivo single-admin” como restrição dura; documentar papéis Admin / Subadmin / Visitante / Teste e cotas.
- [x] Congelar contrato de papéis em `config/aura.php`: abilities + limits default.
- [x] Definir matriz de permissões (Gate abilities) em `config('aura.abilities')`:
  - `users.manage` → Admin
  - `transactions.manage` → Admin, Subadmin, Visitante, Teste (cota)
  - `statements.upload` → todos autenticados com cota
  - `goals.manage` → Admin, Subadmin, Visitante, Teste (cota via `max_goals`)
  - `aliases.manage` → Admin, Subadmin, Visitante, Teste (cota `max_aliases`; `0` = ilimitado)
- [x] Definir limites default (env-overridable) — `AURA_LIMIT_*` em `.env.example` / `.env.production.example` (`0` = ilimitado).
- [x] Atualizar `.env.example` e `.env.production.example` com as variáveis acima.
- [x] Estratégia multi-tenant: **shared DB + row-level `user_id`** (`config('aura.multi_tenant')`) — alinhado ao HostGator shared hosting.
- [x] Branch de trabalho: `feat/expansao-multiuser-metas-crud`.

---

## 1. Banco de Dados — migrations e seeders

### 1.1 Papéis, limites e usuários

- [x] Migration `add_role_and_limits_to_users_table`:
  - [x] `role` string/enum: `admin|subadmin|visitor|test` (default `visitor`), index.
  - [x] `is_active` boolean default `true`.
  - [x] Counters inline em `users` + `quota_period_starts_at` (reset mensal via `UsageLimitService` na §2.4).
  - [x] `uploads_used` / `manual_transactions_used` unsigned int default `0`.
  - [x] `quota_period_starts_at` nullable timestamp (janela de cota).
- [x] Migration `create_role_limits_table`:
  - [x] `role` unique, `max_uploads`, `max_manual_transactions`, `max_date_range_days`, `max_goals`, timestamps (`0` = ilimitado).
- [x] Atualizar `AdminUserSeeder`: `role = admin`.
- [x] Criar `RoleLimitsSeeder` + registro em `DatabaseSeeder` com defaults dos 4 papéis.
- [x] Factory `UserFactory`: states `admin()`, `subadmin()`, `visitor()`, `test()` (+ `inactive()`).
- [x] Enum `App\Enums\UserRole` + model `RoleLimit`.

### 1.2 Multi-tenant em transactions

- [x] Migration `add_user_id_and_source_kind_to_transactions_table`:
  - [x] `user_id` FK → `users` (nullable temporariamente para backfill).
  - [x] `source_kind` enum/string: `import|manual` default `import` (`TransactionSourceKind`).
  - [x] Tornar `statement_import_id` **nullable** (manuais não têm import).
  - [x] Índice composto `(user_id, occurred_on)`, `(user_id, type)`, `(user_id, category_id)`.
- [x] Migration `make_transaction_unique_hash_scoped_to_user`:
  - [x] Dropar unique index global em `unique_hash`.
  - [x] Criar unique `(user_id, unique_hash)` (+ backfill inline + `user_id` NOT NULL).
- [x] Comando Artisan `aura:backfill-transaction-user-id`:
  - [x] `UPDATE transactions t JOIN statement_imports si … SET t.user_id = si.user_id WHERE t.user_id IS NULL`.
  - [x] Após backfill: tornar `user_id` NOT NULL (idempotente; também coberto pela migration).
- [x] Atualizar `TransactionHasher` / docs: hash continua sem `user_id` no payload; isolamento via unique composto.
- [x] Atualizar model `Transaction`: `belongsTo(User)`, fillable/`casts`, scope `forUser($id)`.
- [x] Atualizar `User`: `hasMany(Transaction)` direto (`transactionsViaImports` = HasManyThrough legado).
- [x] `StatementUploadService` grava `user_id` + `source_kind=import` e dedupe escopado por usuário.

### 1.3 Metas (`goals`)

- [x] Migration `create_goals_table`:
  - [x] `id`, `user_id` FK cascade
  - [x] `name` string
  - [x] `kind` enum: `savings|debt_payoff` (`GoalKind`)
  - [x] `target_amount` decimal(14,2)
  - [x] `current_amount` decimal(14,2) default 0
  - [x] `currency` char(3) default `BRL`
  - [x] `deadline_on` date nullable
  - [x] `category_id` nullable FK (vínculo opcional a categoria de aporte/pagamento)
  - [x] `linked_description_pattern` string nullable (regex/like para auto-atualizar via aliases/transações)
  - [x] `status` enum: `active|completed|paused|cancelled` default `active` (`GoalStatus`)
  - [x] `metadata` json nullable
  - [x] timestamps (soft deletes omitidos neste ciclo)
  - [x] índices: `(user_id, status)`, `(user_id, kind)`
- [x] Model `Goal` + factory + `GoalPolicy` (+ `User::goals()`, enums `GoalKind`/`GoalStatus`).

### 1.4 Apelidos / regras (`transaction_aliases`)

- [x] Migration `create_transaction_aliases_table`:
  - [x] `id`, `user_id` FK cascade
  - [x] `match_type` enum: `exact|contains|starts_with|regex` default `contains` (`AliasMatchType`)
  - [x] `match_pattern` string (descrição original do extrato)
  - [x] `display_name` string (apelido exibido)
  - [x] `category_id` nullable FK (opcional: também força categoria)
  - [x] `priority` unsigned smallint default 100 (menor = mais prioritário)
  - [x] `is_active` boolean default true
  - [x] timestamps
  - [x] unique `(user_id, match_type, match_pattern)`
  - [x] índice `(user_id, is_active, priority)`
- [x] Model `TransactionAlias` + factory + `TransactionAliasPolicy` (+ `User::transactionAliases()`, `matches()`, `scopeForResolution()`).

### 1.5 Imports / formatos cartão

- [x] Migration `extend_statement_imports_for_credit_card`:
  - [x] Expandir `format` VARCHAR(32) para aceitar `csv|ofx|csv_credit_card` (string, não ENUM MySQL).
  - [x] Expandir `source` VARCHAR(32) para `nubank|nubank_credit|other`.
- [x] Atualizar `UploadStatementRequest`: `source` in `nubank,nubank_credit,other`.
- [x] Enums `StatementFormat` / `StatementSource` + constantes em `StatementImport` + `StatementFormatDetector::ALLOWED_*`.
- [x] Factory state `creditCard()`.

### 1.6 Comandos e ordem de migrate

> Runbook operacional da expansão DB (Etapa F §1). Produção: espelhar em `docs/DEPLOY_HOSTGATOR.md` §5.6.

#### Ordem local (fresh ou já com MVP)

As migrations `2026_09_29_141000` + `2026_09_29_141100` **já fazem** backfill de `user_id`, `NOT NULL` e unique `(user_id, unique_hash)` no `migrate`. O comando Artisan é idempotente (ops / mid-deploy / dry-run).

```bash
# 0) Backup local (obrigatório se houver dados)
mysqldump -u aura_dev -p aura > backup_pre_expansao_$(date +%Y%m%d).sql

# 1) Schema da expansão (roles, role_limits, transactions.user_id, goals, aliases, formats CC)
php artisan migrate

# 2) Backfill idempotente (no-op se a 141100 já rodou com sucesso)
php artisan aura:backfill-transaction-user-id
# Opcional: php artisan aura:backfill-transaction-user-id --dry-run

# 3) Cotas + admin com role=admin (idempotentes; NÃO usar DatabaseSeeder em prod)
php artisan db:seed --class=Database\\Seeders\\RoleLimitsSeeder
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder

# Local / dev com demo (opcional):
# php artisan db:seed
```

Ordem das migrations de expansão (timestamp):


| Migration                                                             | Efeito                                                            |
| --------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `2026_09_29_140000_add_role_and_limits_to_users_table`                | `users.role`, cotas inline                                        |
| `2026_09_29_140100_create_role_limits_table`                          | `role_limits`                                                     |
| `2026_09_29_141000_add_user_id_and_source_kind_to_transactions_table` | `user_id` nullable, `source_kind`, `statement_import_id` nullable |
| `2026_09_29_141100_make_transaction_unique_hash_scoped_to_user`       | backfill + `user_id` NOT NULL + unique `(user_id, unique_hash)`   |
| `2026_09_29_142000_create_goals_table`                                | `goals`                                                           |
| `2026_09_29_143000_create_transaction_aliases_table`                  | `transaction_aliases`                                             |
| `2026_09_29_144000_extend_statement_imports_for_credit_card`          | `format`/`source` VARCHAR(32)                                     |


#### Seeders

- [x] `php artisan db:seed --class=RoleLimitsSeeder` — defaults de `config/aura.php` (`0` = ilimitado).
- [x] `php artisan db:seed --class=AdminUserSeeder` — garante `role=admin` + `is_active=true`.
- [x] `php artisan db:seed --class=DemoRoleUsersSeeder` — Subadmin / Visitante / Teste (só `local`/`development`/`testing`).
- [x] Em local, `php artisan migrate --seed` chama `DatabaseSeeder` (RoleLimits → Admin → DemoRoleUsers → Category → Demo).

#### Rollback / segurança

- [x] **Backup obrigatório** antes de qualquer `migrate:rollback` que toque unique composto ou `user_id`.
- [x] Preferir **forward-fix** (nova migration) em produção; evitar rollback da `141100`.
- [x] Se rollback da `141100` for inevitável:
  1. Backup SQL fresco.
  2. Confirmar que **não** existem dois users com o mesmo `unique_hash` (senão o unique global falha ao recriar).
  3. `php artisan migrate:rollback --step=1` (reverte unique composto → unique global + `user_id` nullable).
  4. Validar app (login, listagem, upload).
- [x] Nunca dropar `unique(user_id, unique_hash)` sem plano de reindexação e backup.

Checklist:

- [x] Ordem local: `migrate` → `aura:backfill-transaction-user-id` → seeds de cotas/admin documentada.
- [x] `RoleLimitsSeeder` documentado e no `DatabaseSeeder`.
- [x] Rollback: backup obrigatório antes de dropar unique global / reverter `141100`.

---

## 2. Multi-usuário, RBAC e cotas (Backend)

### 2.1 Auth e papéis

- [x] Manter session cookie (`web` guard); **não** introduzir Sanctum neste ciclo (HostGator + SPA same-origin).
- [x] Cast/`App\Enums\UserRole` (`Admin`, `Subadmin`, `Visitor`, `Test`) — já na §1.1.
- [x] Helpers no model `User`: `isAdmin()`, `hasRole()`, `hasAnyRole()`, `canUpload()`, `remainingUploads()`.
- [x] Registrar Gates em `AppServiceProvider` a partir de `config/aura.php`:
  - [x] `Gate::define` para cada ability (`users.manage`, `transactions.manage`, …)
  - [x] `Gate::before` — Admin bypass só em abilities nomeadas; `is_active=false` sempre nega.
- [x] Middleware `EnsureUserIsActive` (`active`) — 403 se `is_active = false`.
- [x] Middleware `EnsureRole` (`role:admin,subadmin`) registrado em `bootstrap/app.php`.
- [x] Middleware `EnforceUsageQuota` (`quota:uploads|manual_transactions`) — 429 `{ message, error_code, metric, limit, used }`; incremento pós-sucesso no upload.
- [x] `UsageLimitService` (assert/increment/reset mensal) — base para §2.4.
- [x] Policies:
  - [x] Expandir `StatementImportPolicy` (create/delete + abilities).
  - [x] `TransactionPolicy` (`viewAny`/`view`/`create`/`update`/`delete`).
  - [x] `GoalPolicy`, `TransactionAliasPolicy` (via Gates), `UserPolicy` (Admin).
- [x] Route model binding: `{transaction}`, `{goal}`, `{alias}` escopados ao owner (404).
- [x] Rotas autenticadas sob `middleware(['auth', 'active'])`; upload com `quota:uploads`.
- [x] Login rejeita conta desativada (403).

### 2.2 Isolamento de dados (multi-tenant scope)

- [x] Refatorar `TransactionQueryService::baseForUser` / `forUser` para filtrar `transactions.user_id` direto (`Transaction::forUser`) — inclui manuais sem import.
- [x] Aplicar o mesmo escopo em `AnalyticsService` (via `baseForUser`), listagens HTTP e bindings; cascade de import → transactions permanece via FK `cascadeOnDelete`.
- [x] Categorias: **permanecem globais** neste ciclo (`is_system`); documentado em `docs/context.md` §2.6 + `CategoryController`.
- [x] Storage path já usa `{userId}` — mantido (`StatementStorage`).
- [x] Testes Feature: User A não vê transactions/statements/analytics/goals/aliases de User B (`MultiTenantIsolationTest` + query service).

### 2.3 Gestão de usuários (API Admin)

- [x] `UserController` (api):
  - [x] `GET /api/users` — paginado, filtros `role`, `q`, `is_active` — middleware `role:admin` + `UserPolicy`.
  - [x] `POST /api/users` — cria Subadmin/Visitante/Teste (nunca Admin via API).
  - [x] `PATCH /api/users/{user}` — role, `is_active`, `reset_usage` (counters).
  - [x] `DELETE /api/users/{user}` — soft-block (`is_active=false`); Admin/self bloqueados pela Policy.
  - [x] `GET /api/users/{user}` — detalhe.
- [x] FormRequests: `IndexUsersRequest`, `StoreUserRequest`, `UpdateUserRequest`.
- [x] `UserResource` (sem hash; `limits` + `usage`).
- [x] Rate limit `throttle:30,1` nas rotas admin.

### 2.4 Cotas — regras de negócio

- [x] Service `UsageLimitService`:
  - [x] `assertCan(User $user, string $metric): void` → `UsageLimitExceededException` → HTTP 429.
  - [x] `increment(User $user, string $metric): void`.
  - [x] `resetPeriodIfNeeded(User $user): void` (mensal calendário `APP_TIMEZONE`).
  - [x] `dateRangeDaysLimit` / `isDateRangeAllowed` / `inclusiveDaySpan` (cap de amplitude).
- [x] Integrar no upload: middleware `quota:uploads` + `increment` pós-sucesso em `StatementUploadService`.
- [x] Hook pronto para CRUD manual: middleware `quota:manual_transactions` (wire na rota `POST` na §3.1).
- [x] Limitar amplitude de date range para Visitante/Teste via Rule `WithinRoleDateRangeLimit` em `DashboardAnalyticsRequest` e `IndexTransactionsRequest` (`from`/`to` max N dias inclusivos; `0` = ilimitado).

---

## 3. CRUD manual de transações (Backend/API)

### 3.1 Endpoints

- [x] `POST   /api/transactions` — criar manual (`source_kind=manual`, `statement_import_id=null`).
- [x] `GET    /api/transactions/{transaction}` — detalhe (opcional; listagem já existe).
- [x] `PUT|PATCH /api/transactions/{transaction}` — editar (manuais: campos amplos; importadas: `category_id` / notes; Admin pode core fields).
- [x] `DELETE /api/transactions/{transaction}` — hard delete; contadores de import **não** reescritos.
- [x] Autorizar via `$this->authorize()` + `TransactionPolicy`.
- [x] Aplicar `EnforceUsageQuota:manual_transactions` no `store`.

### 3.2 Validação e persistência

- [x] `StoreTransactionRequest` / `UpdateTransactionRequest`:
  - [x] `occurred_on` date, `amount` > 0, `type` in `credit|debit`, `description` required string, `category_id` exists, `notes` nullable (em `raw_payload`).
- [x] Service `ManualTransactionService`:
  - [x] Gera `unique_hash` via `TransactionHasher` com marcador `manual:{userId}` + occurred_on + amount + type + description.
  - [x] Define `user_id = auth()->id()`, `raw_payload` com origem `manual`.
  - [x] Dispara reavaliação de metas (`GoalProgressService::touchFromTransaction` / `onTransactionWritten`).
- [x] `TransactionResource`: expor `source_kind`, `editable`, `deletable` (flags derivadas da Policy) + `notes`.
- [x] Ao editar lançamento **importado**: restringir campos mutáveis (core só Admin) — documentado no Policy / FormRequest / Service.
- [x] Ao excluir: se veio de import, apenas remove a linha (não reprocessa arquivo); contadores do import **não** reescrevem histórico.

### 3.3 Rotas (`routes/api.php`)

- [x] Agrupar sob `middleware(['auth', 'active'])`.
- [x] Manter `GET /api/transactions` existente; write verbs no mesmo `TransactionController` (+ `quota:manual_transactions` no store).

---

## 4. Motor de Apelidos/Regras — Aliases (Backend)

### 4.1 Resolução no parse

- [x] Criar `App\Services\AliasResolutionService`:
  - [x] `resolve(User $user, string $rawDescription): ?AliasMatch`  
    → retorna `display_name`, `category_id`, `alias_id`.
  - [x] Carregar aliases ativos do usuário ordenados por `priority ASC`, depois `id`.
  - [x] Matching:
    - `exact` → `strcasecmp`
    - `contains` → `stripos`
    - `starts_with` → `stripos === 0`
    - `regex` → `preg_match` com `@`/error handler safe (FormRequest inválidos na §4.2).
- [x] Integrar em `StatementUploadService::persistParsed` **antes** do insert:
  - [x] Para cada `ParsedTransaction`, aplicar alias → sobrescrever `description` + `category_id` se a regra definir.
  - [x] Persistir descrição original em `raw_payload.original_description` sempre (hash continua na descrição original).
- [x] Reutilizar o mesmo service no CRUD manual `store`/`update` (auto-apply).
- [x] Cache por request: `AliasResolutionService` memoiza lista do user (evitar N+1).

### 4.2 API de aliases

- [x] `GET    /api/aliases`
- [x] `POST   /api/aliases`
- [x] `PATCH  /api/aliases/{alias}`
- [x] `DELETE /api/aliases/{alias}`
- [x] `POST   /api/aliases/preview` — dry-run: dado `description`, retorna match.
- [x] `POST   /api/transactions/{transaction}/remember-alias` — atalho a partir do lançamento (`contains|exact`).
- [x] FormRequests + `TransactionAliasResource`.
- [x] Gate `aliases.manage` para Admin/Subadmin/Visitante/Teste + cota `max_aliases` (429 ao exceder).

### 4.3 Recategorização retroativa (opcional neste ciclo)

- [x] Flag `apply_to_existing` no `store` de alias (também em `remember-alias`):
  - [x] Se true: apply **síncrono** limitado às últimas N txs do user (`AURA_ALIAS_RETROACTIVE_LIMIT`, default 500) atualizando `description` / `category_id` onde `raw_payload.original_description` (fallback `description`) casa.
  - [x] HostGator: **sem queue/Redis** — `AliasRetroactiveApplyService` roda inline; resposta inclui `retroactive: { scanned, updated, limit }`.

---

## 5. Parser CSV de fatura de Cartão de Crédito

### 5.1 Detecção e Strategy

- [x] Criar fixtures em `tests/Fixtures/statements/nubank_credit_card_sample.csv` (anonimizado).
- [x] Documentar no `tests/Fixtures/statements/README.md` a diferença conta-corrente vs fatura.
- [x] Estender `StatementFormatDetector`:
  - [x] Além de extensão: sniff de headers (ex.: colunas típicas de fatura Nubank — `date`, `title`, `amount` ou equivalentes BR `Data`, `Descrição`, `Valor` com sinal/absolute distinto).
  - [x] Retornar enum interno `DetectedFormat::CsvChecking | CsvCreditCard | Ofx`.
- [x] Criar `App\Parsers\NubankCreditCardCsvParser` implementando `StatementParserInterface`.
  - [x] Normalizar datas via `DateNormalizer`.
  - [x] Valores: faturas costumam ser despesas → mapear para `type=debit` (créditos/estornos → `credit`).
  - [x] Ignorar linhas de totalizadores / “Pagamento recebido” conforme regras documentadas (configurável).
  - [x] Popular `raw_payload` com linha CSV + `card_source=nubank_credit`.
- [x] Registrar no `StatementParserResolver` (bind em `AppServiceProvider`).
- [x] Atualizar `StatementUploadService` para gravar `format=csv_credit_card`, `source=nubank_credit`.
- [x] Permitir override manual no upload: campo `source` / `statement_kind=checking|credit_card`.

### 5.2 Testes

- [x] Unit: parser com fixture → N lançamentos, tipos corretos, datas, amounts absolutos.
- [x] Feature: upload autenticado → `UploadSummary` com `rows_imported` > 0.
- [x] Garantir que `NubankCsvParser` de conta **não** quebra com CSV de cartão (detector deve escolher o parser certo ou 422 claro).

### 5.3 OFX cartão (fora do núcleo deste requisito)

- [x] Documentar dívida: `OfxParser` hoje itera só `$ofx->bankAccounts`; suporte de produto a fatura OFX fica para ciclo futuro.
  - **Estado atual:** `supports()` aceita só `format=ofx|qfx` + `source=nubank|other` (não `nubank_credit`).
  - **Lib:** `cihansenturk/ofxparser` pode mapear `CREDITCARDMSGSRSV1` → `bankAccounts` internamente; **Aura não valida** esse caminho (sem fixture, sem regras de sinal/skip de fatura).
  - **Caminho suportado para cartão:** CSV (`format=csv_credit_card`, `NubankCreditCardCsvParser`).
  - **Backlog:** item “OFX de cartão de crédito” em *Fora deste ciclo*; doc em `OfxParser`, `docs/context.md`, `tests/Fixtures/statements/README.md`.

---

## 6. Date Range Picker flexível (Backend + contrato API)

### 6.1 Backend

- [x] Atualizar `DashboardAnalyticsRequest` e `IndexTransactionsRequest`:
  - [x] `from` / `to` ISO date; se ambos omitidos → mês corrente (compat).
  - [x] Se um informado sem o outro → 422.
  - [x] `to >= from`.
  - [x] Cap por papel via `UsageLimitService` / Rule custom `WithinRoleDateRangeLimit` (§2.4).
  - [x] Remover qualquer hardcode que force `group_by` incompatível; manter `resolveGroupBy` (≤45 dias → `day`) — `App\Support\DateRangeQuery` + FormRequest + `AnalyticsService`.
- [x] Aceitar presets opcionais no query string `preset=current_month|last_30|last_90|custom` (custom exige from/to).
- [x] Analytics: garantir performance com índices `(user_id, occurred_on)` (migration `2026_09_29_141000_…`).

### 6.2 Contrato para o frontend

- [x] Documentar params em `docs/PLAN_EXPANSAO.md` (este arquivo) e espelhar em JSDoc de `useDashboardFilters`.
- [x] URL state: `?from=YYYY-MM-DD&to=YYYY-MM-DD&preset=custom`.

#### Contrato query string (analytics + transactions)


| Param                                      | Tipo                                               | Obrigatório        | Notas                                                                         |
| ------------------------------------------ | -------------------------------------------------- | ------------------ | ----------------------------------------------------------------------------- |
| `from`                                     | `YYYY-MM-DD`                                       | com `to`           | Par incompleto → **422**. Ambos omitidos → **mês corrente** (`APP_TIMEZONE`). |
| `to`                                       | `YYYY-MM-DD`                                       | com `from`         | `to >= from`; amplitude limitada por papel (`WithinRoleDateRangeLimit`).      |
| `preset`                                   | `current_month` | `last_30` | `last_90` | `custom` | não                | Named: backend/front recalculam bounds. `**custom` exige `from`+`to**`.       |
| `group_by`                                 | `day` | `month`                                    | não (só analytics) | Omitido → `DateRangeQuery::resolveGroupBy` (≤45 dias inclusivos → `day`).     |
| `type`                                     | `credit` | `debit`                                 | não                |                                                                               |
| `category_id`                              | int                                                | não                |                                                                               |
| `q`                                        | string ≤120                                        | não                | Front: debounce 300ms em `apiFilters`.                                        |
| `page` / `per_page` / `sort` / `direction` | —                                                  | não                | Só `GET /api/transactions`.                                                   |


**URL state (SPA):** `useDashboardFilters` grava sempre `from`, `to` e `preset` nos `searchParams`.

Exemplos:

```
/dashboard?from=2026-09-01&to=2026-09-30&preset=current_month
/dashboard?from=2026-08-17&to=2026-09-15&preset=last_30
/dashboard?from=2026-08-01&to=2026-08-31&preset=custom
```

Espelho no código: JSDoc de `resources/js/hooks/useDashboardFilters.js`, `resources/js/lib/dates.js`, `resources/js/lib/apiParams.js` (`preset` em `toAnalyticsParams` / `toTransactionsParams`).

---

## 7. Sistema de Metas Financeiras (Backend)

### 7.1 Service e regras

- [x] `GoalService`:
  - [x] `create` / `update` / `delete` / `listForUser` (+ `queryForUser`).
  - [x] `recalculate(Goal $goal)`: para `savings`, soma créditos (ou débitos para `debt_payoff`) no período/padrão vinculado; ou usa `current_amount` manual.
  - [x] Modo de progresso:
    - **manual** — usuário informa `current_amount`
    - **linked** — deriva de transações filtradas por `category_id` e/ou `linked_description_pattern` + range desde `created_at`/`deadline`
- [x] `GoalProgressService::onTransactionWritten(Transaction $tx)` / `touchFromTransaction` — atualiza metas linked `active|completed` (§3.2; API GoalService na §7.2).
- [x] Conclusão automática: se `current_amount >= target_amount` → `status=completed` (reabre se cair abaixo).
- [x] Cota stock `max_goals` via `UsageLimitService::assertCanCreateGoal` (espelha aliases).

### 7.2 API

- [x] `GET    /api/goals`
- [x] `POST   /api/goals`
- [x] `PATCH  /api/goals/{goal}`
- [x] `DELETE /api/goals/{goal}`
- [x] `POST   /api/goals/{goal}/recalculate`
- [x] Incluir resumo de metas no payload de `GET /api/analytics/dashboard` (chave `goals`: progresso %, restantes, status) — evitar N+1 (`with` / subquery).
- [x] Cotas: Visitante/Teste com `max_goals` baixo (`assertCanCreateGoal` → 429).

### 7.3 Integração Analytics

- [x] Estender `AnalyticsService` ou compor `DashboardGoalsAggregator`.
- [x] Cards sugeridos: “Progresso médio das metas”, “Meta mais próxima do prazo”.
  - `data.goals.cards.average_progress_percent` (média das metas `active`; `null` se nenhuma)
  - `data.goals.cards.nearest_deadline` (`id`, `name`, `deadline_on`, `days_remaining`, `is_overdue`, …; `null` se sem prazo)

---

## 8. Frontend (React 19)

### 8.1 Fundação

- [x] Estender `AuthContext` / `GET /api/user` resource: incluir `role`, `limits`, `usage`, `abilities` (lista de gates relevantes).
- [x] Helper `can(ability)` e `isRole(...)` em `resources/js/lib/auth.js`.
- [x] Rotas SPA: `/admin/users`, `/goals` (ou seção no dashboard), `/aliases` (ou drawer).
- [x] Esconder nav items conforme papel.

### 8.2 CRUD manual — modais

- [x] Componente `TransactionFormModal` (criar/editar):
  - [x] Campos: data, valor, tipo, descrição, categoria, notas.
  - [x] Validação client alinhada ao FormRequest; erros 422 da API.
  - [x] Usar tokens do Design System (sem cards desnecessários; modal como superfície de interação).
- [x] `DeleteTransactionDialog` com confirmação explícita.
- [x] Ações na tabela do dashboard: ícones Editar / Excluir / “Lembrar apelido” (se `can`).
- [x] Botão “Nova transação” no header do dashboard / lista.
- [x] Hooks: `useCreateTransaction`, `useUpdateTransaction`, `useDeleteTransaction` (Axios + toast `sonner`).
- [x] Invalidar/refetch analytics + listagem após mutação.

### 8.3 Date Range Picker

- [x] Biblioteca: avaliar `react-day-picker` v8/v9 (leve, headless) **ou** input nativo duplo estilizado — preferir `react-day-picker` + Poppins/tokens Aura.
- [x] Componente `DateRangePicker`:
  - [x] Presets existentes (`PeriodPills`) + modo Custom abrindo o calendário.
  - [x] Sync com `useDashboardFilters` / URL searchParams.
  - [x] Bloquear ranges > limite do papel (tooltip + toast).
  - [x] Acessível: teclado, labels PT-BR.
- [x] Manter `resolveGroupBy` no client.

### 8.4 Upload — cartão de crédito

- [x] Na página Upload: seletor `Conta corrente` | `Fatura cartão` (envia `source` / `statement_kind`).
- [x] Copy de ajuda: formatos aceitos; link para exemplo de CSV.
- [x] Feedback de resumo inalterado (`UploadSummary`).
- [x] Tratar 422 de formato/detector com mensagem acionável.

### 8.5 Metas — UI

- [x] Seção/widget no Dashboard: lista compacta de metas ativas com barra de progresso.
- [x] `GoalFormModal`: kind, nome, target, deadline, modo manual/linked, categoria/padrão.
- [x] Página ou painel `GoalsPanel` para CRUD completo.
- [x] Estados empty: CTA “Criar primeira meta”.
- [x] Respeitar Design System (brand `#DCCFFF`, dark shell).

### 8.6 Admin — usuários

- [x] Página `AdminUsersPage` (rota protegida `role===admin`):
  - [x] Tabela: email, role, active, uploads_used/limit, created_at.
  - [x] Ações: criar usuário, alterar papel, ativar/desativar, resetar cotas.
  - [x] Modal `UserFormModal`.
- [x] Sem exposição de hash; senha só no create/reset.

### 8.7 Apelidos — UI

- [x] `AliasesPage` ou Drawer:
  - [x] Lista de regras (pattern, tipo, apelido, categoria, prioridade, ativo).
  - [x] Form criar/editar + preview de match.
  - [x] Atalho a partir da linha da transação: “Memorizar como…”.
- [x] Toggle `apply_to_existing` com aviso de limite.

---

## 9. Testes, Definition of Done e Deploy

### 9.1 Testes automatizados

- [x] Feature: RBAC — Visitante não acessa `GET /api/users` (403).
- [x] Feature: isolamento — User B 404 em transaction de User A.
- [x] Feature: cota de upload esgotada → 429.
- [x] Feature: CRUD manual cria `source_kind=manual` e aparece no dashboard filtrado.
- [x] Feature: date range > limite Visitante → 422.
- [x] Unit: `AliasResolutionService` priority/matching.
- [x] Unit: `NubankCreditCardCsvParser` fixtures.
- [x] Unit: `GoalProgressService` savings vs debt_payoff (+ `GoalServiceTest` §7.1).
- [x] Unit: `TransactionHasher` manual vs import não colide no mesmo user; users distintos podem repetir hash.

### 9.2 DoD da Etapa F

- [x] Os 6 pilares entregues em ambiente local com seed multi-papel (`DemoRoleUsersSeeder` + `AdminUserSeeder`).
- [x] `docs/context.md` + `docs/MASTER_PLAN.md` + este plano atualizados (checkboxes refletindo progresso).
- [x] Nenhuma regressão no fluxo MVP: login, upload conta Nubank CSV/OFX, dashboard mês corrente (suite Feature).
- [x] Policies cobrindo write paths (`StatementImportPolicy` no upload; demais Policies nos Controllers); sem mass assignment de `role`/`user_id` em FormRequests de transação.

### 9.3 Deploy (Etapa G)

> Runbook canônico: `docs/DEPLOY_HOSTGATOR.md` §5.6. Cutover live no HostGator = operador após merge em `main` (CI FTP+SSH).

- [x] Backup MySQL (`mysqldump`) — procedimento obrigatório documentado (§5.6 / §2.4) antes do cutover.
- [x] Sequência `down` → deploy → `migrate --force` → `aura:backfill-transaction-user-id` → `RoleLimitsSeeder` → caches → `up` documentada; CI SSH atualizado (`deploy-hostgator.yml`).
- [x] Migrations finais NOT NULL + unique composto (já embutidas na `141100`).
- [x] `RoleLimitsSeeder` idempotente no pós-migrate (CI + runbook).
- [x] Feature flags `AURA_FEATURE_*` para rollout gradual (`config/aura.php` → Gates + AuthUser + upload CC).
- [x] Smoke checklist: cobertura automatizada (`ExpansionAcceptanceChecklistTest` + `FeatureFlagsTest`) + checklist operador em DEPLOY §5.6.
- [x] `docs/DEPLOY_HOSTGATOR.md` + README + `.env.production.example` atualizados.

---

## Ordem de implementação recomendada (checklist mestre)

- [x] 0. Contratos / env / context
- [x] 1. Migrations users.role + goals + aliases + transactions.user_id (backfill) + runbook §1.6
- [x] 2. Gates, Policies, middlewares de role/cota/ativo (§2.1)
- [x] 3. Escopar queries por `transactions.user_id` (§2.2)
- [x] 4. CRUD API transações manuais
- [x] 5. AliasResolutionService no pipeline de parse + API aliases
- [x] 6. NubankCreditCardCsvParser + detector + testes
- [x] 7. Date range validation por papel + contrato URL
- [x] 8. Goals API + agregação dashboard
- [x] 9. Frontend: auth abilities → DateRangePicker → CRUD modals → Upload CC → Goals → Aliases → Admin Users
- [x] 10. Suite de testes + DoD (§9.1–9.2)
- [x] 11. Deploy HostGator (Etapa G) — runbook + feature flags + CI; cutover live = operador

---

## Referência rápida de arquivos a tocar


| Área       | Arquivos / diretórios prováveis                                                                                                                                                                                 |
| ---------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Migrations | `database/migrations/*`                                                                                                                                                                                         |
| Models     | `app/Models/{User,Transaction,Goal,TransactionAlias}.php`                                                                                                                                                       |
| Parsers    | `app/Parsers/{NubankCreditCardCsvParser,StatementParserResolver}.php`, `app/Support/StatementFormatDetector.php`                                                                                                |
| Services   | `app/Services/{AliasResolutionService,ManualTransactionService,UsageLimitService,GoalService,GoalProgressService,DashboardGoalsAggregator,StatementUploadService,TransactionQueryService,AnalyticsService}.php` |


| HTTP | `app/Http/Controllers/*`, `app/Http/Requests/*`, `app/Http/Middleware/*`, `app/Policies/*` |
| Routes | `routes/api.php`, `bootstrap/app.php` |
| Frontend | `resources/js/components/**`, `resources/js/hooks/**`, `resources/js/api/**`, `resources/js/lib/dates.js` |
| Config | `config/aura.php`, `.env.example`, `.env.production.example` |
| Docs | `docs/context.md`, `docs/MASTER_PLAN.md`, `docs/DEPLOY_HOSTGATOR.md` |

---

## Fora deste ciclo (backlog explícito)

- [ ] Sanctum / OAuth / registro público aberto
- [ ] Categorias por usuário
- [ ] OFX de cartão de crédito (`CREDITCARDMSGSRSV1` / fatura): fixture + regras de sinal/skip + `source=nubank_credit` no `OfxParser` (hoje só `bankAccounts` banking; produto cartão = CSV §5.1)
- [ ] Open Banking
- [ ] Filas Redis / parse assíncrono
- [ ] Impersonação de usuários pelo Admin
- [ ] Motor ML de categorização (aliases são o passo intermediário inteligente)