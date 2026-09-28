# PLAN_ETAPA_B — Banco de Dados (Aura)

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises · *Inteligência invisível, controle absoluto.*  
> Planejamento técnico hiperdetalhado da **Etapa B** (modelagem relacional MySQL + Eloquent).  
> Stack: **Laravel 11** · MySQL 8 / MariaDB · HostGator.  
> Pré-requisito: Etapa A concluída (`docs/PLAN_ETAPA_A.md`).  
> Referências: `docs/context.md` §3.2 · `docs/MASTER_PLAN.md`.  
> Marque cada checkbox ao concluir. Não avance para a Etapa C sem a Definition of Done.

---

## Pré-requisitos e ordem de execução

- [ ] Confirmar Etapa A DoD: `php artisan about`, `.env` com `DB_*` apontando para database `aura`, MySQL acessível.
- [ ] Confirmar drivers: `SESSION_DRIVER=database`, `CACHE_STORE=database` (decisão Etapa A) — tabelas `sessions` / `cache` / `cache_locks` precisam existir antes ou junto desta etapa.
- [ ] Backup local opcional antes de migrate: `mysqldump -u aura_dev -p aura > backup_pre_etapa_b.sql`
- [ ] Ordem lógica de migrations (respeitar FKs):

> **users** → **categories** → **statement_imports** → **transactions**  
> (+ tabelas de framework: `sessions`, `cache`, `jobs` se ainda não migradas)

- [ ] Convenção de nomenclatura:
  - [ ] Models: singular PascalCase (`Transaction`, `StatementImport`, `Category`)
  - [ ] Tables: plural snake_case (`transactions`, `statement_imports`, `categories`)
  - [ ] FK columns: `{model}_id` (`statement_import_id`, `category_id`, `user_id`)
- [ ] Money: `DECIMAL(14,2)` — nunca `float`/`double`.
- [ ] Datas de movimento: coluna `date` `occurred_on` (sem timezone); timestamps Laravel em UTC/app timezone via `created_at`/`updated_at`.
- [ ] Charset: `utf8mb4` / `utf8mb4_unicode_ci` (default Laravel).

---

## 0. Tabelas de framework (sessions / cache)

> Necessárias porque `.env` usa `SESSION_DRIVER=database` e `CACHE_STORE=database`.

- [ ] Verificar se já existem migrations padrão do Laravel 11 para `sessions`, `cache`, `cache_locks`.
- [ ] Se **não** existirem, gerar:

```bash
php artisan make:session-table
php artisan make:cache-table
```

- [ ] (Opcional MVP) fila sync — pular `jobs` table, ou gerar se quiser trocar depois:

```bash
php artisan make:queue-table
```

- [ ] Rodar apenas estas primeiro (smoke test DB):

```bash
php artisan migrate
```

- [ ] Critério de sucesso: tabelas `sessions`, `cache`, `cache_locks` listadas em `SHOW TABLES;`.

---

## 1. Entidade `users` (+ seeder do admin único)

### 1.1 Migration `users`

> Laravel 11 já cria `database/migrations/*_create_users_table.php` no skeleton. **Não** duplicar — editar a existente.

- [x] Abrir a migration padrão de `users` e garantir o schema abaixo (ajustar se necessário):

| Coluna | Tipo Blueprint | Modifiers / notes |
| --- | --- | --- |
| `id` | `$table->id()` | PK bigint unsigned |
| `name` | `$table->string('name')` | varchar(255), NOT NULL |
| `email` | `$table->string('email')->unique()` | varchar(255), UNIQUE |
| `email_verified_at` | `$table->timestamp('email_verified_at')->nullable()` | opcional no MVP |
| `password` | `$table->string('password')` | hash bcrypt/argon |
| `remember_token` | `$table->rememberToken()` | nullable string(100) |
| `created_at` / `updated_at` | `$table->timestamps()` | |

- [x] Manter também a migration padrão `password_reset_tokens` (útil mesmo single-admin).
- [x] **Não** criar coluna `role` no MVP (só existe um usuário admin).
- [x] Validar índices: UNIQUE em `email`.

> **Concluído:** `database/migrations/0001_01_01_000000_create_users_table.php` já está conforme (inclui `sessions` no mesmo arquivo). Sem alterações necessárias.

### 1.2 Model `User`

- [x] Confirmar `app/Models/User.php` (já existe no skeleton).
- [x] `$fillable`: `name`, `email`, `password`.
- [x] `$hidden`: `password`, `remember_token`.
- [x] `$casts`: `password` => `hashed`, `email_verified_at` => `datetime`.
- [x] Relação futura: `hasMany(StatementImport::class)` — pode ser adicionada após criar o model de import.

> **Concluído:** `app/Models/User.php` já atende fillable/hidden/casts. Relação `statementImports()` adicionada na §3.3.

### 1.3 Factory `UserFactory`

- [x] Manter `database/factories/UserFactory.php` do skeleton.
- [x] Garantir estado default válido (name, unique email, password hasheado).
- [x] **Não** usar a factory para criar o admin de produção — admin vem do seeder com credenciais fixas/env.

> **Concluído:** factory com `fake()->name()`, `fake()->unique()->safeEmail()`, `Hash::make('password')` e estado `unverified()`. Admin continua exclusivo do `AdminUserSeeder` (§1.4).

### 1.4 Seeder `AdminUserSeeder`

- [x] Gerar seeder:

```bash
php artisan make:seeder AdminUserSeeder
```

- [x] Implementar em `database/seeders/AdminUserSeeder.php` com **upsert idempotente** (`updateOrCreate` por email):

| Campo | Valor MVP (local) | Nota |
| --- | --- | --- |
| `name` | `Admin` | ou `Aura Admin` |
| `email` | `env('ADMIN_EMAIL', 'admin@aura.local')` | documentar em `.env.example` |
| `password` | `Hash::make(env('ADMIN_PASSWORD', 'ChangeMeNow!123'))` | **trocar em produção** |
| `email_verified_at` | `now()` | evita fluxo de verificação |

- [x] Código esperado (referência):

```php
User::query()->updateOrCreate(
    ['email' => env('ADMIN_EMAIL', 'admin@aura.local')],
    [
        'name' => 'Admin',
        'password' => env('ADMIN_PASSWORD', 'ChangeMeNow!123'), // cast 'hashed' no Model
        'email_verified_at' => now(),
    ]
);
```

- [x] Adicionar ao `.env.example` (sem senha real de produção):

```env
ADMIN_EMAIL=admin@aura.local
ADMIN_PASSWORD=ChangeMeNow!123
```

- [x] Documentar as mesmas chaves em `docs/context.md` § Variáveis de ambiente.
- [x] Critério de sucesso: após `db:seed`, `SELECT id,email FROM users;` retorna **exatamente 1** linha admin; reexecutar seeder não duplica.

> **Concluído:** `AdminUserSeeder` registrado em `DatabaseSeeder`; validado localmente (1× `admin@aura.local`; re-seed idempotente).

---

## 2. Entidade `categories` (seed mínimo)

### 2.1 Artisan — Model + migration + factory + seeder

```bash
php artisan make:model Category -mfsc
```

> Flags: `-m` migration · `-f` factory · `-s` seeder · `-c` controller (controller pode ficar stub vazio nesta etapa; API real é Etapa C).

- [x] Se preferir sem controller agora:

```bash
php artisan make:model Category -mfs
```

> **Concluído:** gerados `app/Models/Category.php`, `database/factories/CategoryFactory.php`, `database/migrations/2026_09_27_212601_create_categories_table.php`, `database/seeders/CategorySeeder.php` (sem controller — API na Etapa C).

### 2.2 Migration `create_categories_table`

- [x] Schema exato:

| Coluna | Blueprint | Constraints |
| --- | --- | --- |
| `id` | `$table->id()` | PK |
| `name` | `$table->string('name', 100)` | NOT NULL |
| `slug` | `$table->string('slug', 120)` | UNIQUE |
| `type` | `$table->string('type', 20)` | `income` \| `expense` \| `transfer` (string ou enum) |
| `color` | `$table->string('color', 7)->nullable()` | opcional hex UI (`#DCCFFF`) |
| `is_system` | `$table->boolean('is_system')->default(true)` | seed vs user-defined (futuro) |
| `created_at` / `updated_at` | `$table->timestamps()` | |

- [x] Índices adicionais:
  - [x] `$table->index('type');`
  - [x] `$table->unique('slug');` (já no unique da coluna)

- [x] Exemplo Blueprint:

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('slug', 120)->unique();
    $table->string('type', 20); // income|expense|transfer
    $table->string('color', 7)->nullable();
    $table->boolean('is_system')->default(true);
    $table->timestamps();

    $table->index('type');
});
```

> **Concluído:** migration aplicada; colunas `id, name, slug, type, color, is_system, created_at, updated_at`.

### 2.3 Model `Category`

- [x] Arquivo: `app/Models/Category.php`
- [x] `$fillable`: `name`, `slug`, `type`, `color`, `is_system`
- [x] Relação: `hasMany(Transaction::class)`
- [x] (Opcional) cast `is_system` => `boolean`
- [ ] (Opcional) Enum PHP `CategoryType` — pode ficar para Etapa C; no MVP string validada no seeder.

> **Concluído:** fillable + cast `is_system` + `transactions()`. Relação aponta para `Transaction` (model ainda da §4). Enum `CategoryType` adiado.

### 2.4 Seeder `CategorySeeder` (mínimo obrigatório para UI)

- [x] Popular categorias básicas (idempotente via `slug`):

| name | slug | type | color (sugestão) |
| --- | --- | --- | --- |
| Receitas | `receitas` | `income` | `#A8E6C3` |
| Alimentação | `alimentacao` | `expense` | `#DCCFFF` |
| Transporte | `transporte` | `expense` | `#9A9C9B` |
| Moradia | `moradia` | `expense` | `#6E706F` |
| Saúde | `saude` | `expense` | `#FCFDFC` |
| Lazer | `lazer` | `expense` | `#DCCFFF` |
| Educação | `educacao` | `expense` | `#9A9C9B` |
| Transferências | `transferencias` | `transfer` | `#3A3C3B` |
| Outros | `outros` | `expense` | `#525554` |

- [x] Usar `Category::query()->updateOrCreate(['slug' => ...], [...])` para cada linha.
- [x] Critério de sucesso: `categories` com ≥ 8 linhas; re-seed não duplica.

> **Concluído:** 9 categorias seedadas; re-seed mantém count=9. Registrado em `DatabaseSeeder`.

### 2.5 Factory `CategoryFactory`

- [x] `database/factories/CategoryFactory.php`:
  - [x] `name` => fake words
  - [x] `slug` => `Str::slug($name) . '-' . fake()->unique()->numerify('###')`
  - [x] `type` => `fake()->randomElement(['income', 'expense', 'transfer'])`
  - [x] `color` => fake hex color
  - [x] `is_system` => `false` (factories = categorias “não seed”)

> **Concluído:** factory gera categorias não-sistema com slug único.

---

## 3. Entidade `statement_imports`

### 3.1 Artisan

- [x] Gerar scaffold:

```bash
php artisan make:model StatementImport -mfs
```

> **Concluído:** `app/Models/StatementImport.php`, factory, migration `2026_09_27_212743_create_statement_imports_table.php`, `StatementImportSeeder.php` (seeder dedicado opcional na §3.5).

### 3.2 Migration `create_statement_imports_table`

| Coluna | Blueprint | Constraints / notes |
| --- | --- | --- |
| `id` | `$table->id()` | PK |
| `user_id` | `$table->foreignId('user_id')` | FK → `users.id` |
| `original_filename` | `$table->string('original_filename', 255)` | nome enviado pelo browser |
| `stored_path` | `$table->string('stored_path', 500)` | path relativo no disk privado |
| `format` | `$table->string('format', 10)` | `csv` \| `ofx` |
| `source` | `$table->string('source', 30)->default('nubank')` | ex.: `nubank` |
| `status` | `$table->string('status', 20)` | `pending` \| `processing` \| `completed` \| `failed` |
| `rows_total` | `$table->unsignedInteger('rows_total')->default(0)` | |
| `rows_imported` | `$table->unsignedInteger('rows_imported')->default(0)` | |
| `rows_skipped` | `$table->unsignedInteger('rows_skipped')->default(0)` | duplicatas / inválidas |
| `checksum` | `$table->string('checksum', 64)->nullable()` | SHA-256 do arquivo |
| `error_message` | `$table->text('error_message')->nullable()` | falha de parse |
| `created_at` / `updated_at` | `$table->timestamps()` | |

- [x] Foreign key `user_id`:

```php
$table->foreignId('user_id')
    ->constrained('users')
    ->cascadeOnDelete();
```

- [x] Índices de performance:

```php
$table->index('status');
$table->index('created_at');
$table->index(['source', 'format']);
$table->index('checksum'); // detectar reupload do mesmo arquivo
```

- [x] Blueprint completo (referência):

```php
Schema::create('statement_imports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('original_filename', 255);
    $table->string('stored_path', 500);
    $table->string('format', 10); // csv|ofx
    $table->string('source', 30)->default('nubank');
    $table->string('status', 20)->default('pending');
    $table->unsignedInteger('rows_total')->default(0);
    $table->unsignedInteger('rows_imported')->default(0);
    $table->unsignedInteger('rows_skipped')->default(0);
    $table->string('checksum', 64)->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index('status');
    $table->index('created_at');
    $table->index(['source', 'format']);
    $table->index('checksum');
});
```

> **Concluído:** migration aplicada com FK `user_id` cascade e índices `status`, `created_at`, `(source, format)`, `checksum`.

### 3.3 Model `StatementImport`

- [x] `app/Models/StatementImport.php`
- [x] `$fillable`: todos os campos de negócio acima (exceto `id`/timestamps auto).
- [x] Relação `belongsTo(User::class)`
- [x] Relação `hasMany(Transaction::class)`
- [x] (Opcional) casts de inteiros para counters.

> **Concluído:** fillable + casts dos counters + `user()` / `transactions()`. Também adicionado `User::statementImports()` (pendência da §1.2).

### 3.4 Factory `StatementImportFactory`

- [x] Associar `user_id` => `User::factory()` ou `User::query()->first()->id` no seeder de dev.
- [x] `original_filename` => `nubank-YYYY-MM.csv`
- [x] `stored_path` => `statements/fake/nubank-YYYY-MM.csv`
- [x] `format` => `csv`
- [x] `source` => `nubank`
- [x] `status` => `completed`
- [x] counters coerentes (`rows_total` ≥ `rows_imported` + `rows_skipped`)
- [x] `checksum` => `fake()->sha256()`

> **Concluído:** factory gera imports Nubank CSV `completed` com counters coerentes (`rows_total = imported + skipped`).

### 3.5 Seeder dedicado (opcional)

- [x] Não obrigatório isolado — imports fake entram no `DemoTransactionSeeder` (seção 6).

> **Concluído:** `StatementImportSeeder` permanece vazio (não registrado em `DatabaseSeeder`). Dados demo na §6.

---

## 4. Entidade `transactions` (núcleo analítico)

### 4.1 Artisan

- [x] Gerar scaffold:

```bash
php artisan make:model Transaction -mfs
```

> **Concluído:** `app/Models/Transaction.php`, factory, migration `2026_09_27_233436_create_transactions_table.php`, `TransactionSeeder.php`.

### 4.2 Migration `create_transactions_table` — colunas

| Coluna | Blueprint | Constraints / notes |
| --- | --- | --- |
| `id` | `$table->id()` | PK |
| `statement_import_id` | `$table->foreignId('statement_import_id')` | FK → `statement_imports` |
| `category_id` | `$table->foreignId('category_id')->nullable()` | FK → `categories`, nullable no MVP |
| `external_id` | `$table->string('external_id', 120)->nullable()` | ID do OFX/banco se existir |
| `occurred_on` | `$table->date('occurred_on')` | data do movimento |
| `description` | `$table->string('description', 500)` | texto normalizado |
| `amount` | `$table->decimal('amount', 14, 2)` | **valor absoluto ≥ 0**; sinal via `type` |
| `type` | `$table->string('type', 10)` | `credit` \| `debit` |
| `unique_hash` | `$table->char('unique_hash', 64)` | SHA-256 hex; idempotência |
| `raw_payload` | `$table->json('raw_payload')->nullable()` | linha original / debug |
| `created_at` / `updated_at` | `$table->timestamps()` | |

> **Decisão de amount:** armazenar sempre positivo; `type=credit` = entrada, `type=debit` = saída.  
> Saldo do período = `SUM(credit) - SUM(debit)`. Documentar em `context.md` se ainda não estiver explícito.

- [x] Colunas criadas e migration aplicada.
- [x] Decisão de `amount` absoluto documentada em `docs/context.md`.

> **Concluído:** tabela `transactions` migrada. A migration já inclui FKs (§4.3), índices (§4.4) e UNIQUE em `unique_hash` (§4.5.2) conforme blueprint §4.6 — marcar essas seções ao revisar.

### 4.3 Foreign keys — regras de deleção

- [x] `statement_import_id` → **cascade on delete** (apagar import remove suas transações):

```php
$table->foreignId('statement_import_id')
    ->constrained('statement_imports')
    ->cascadeOnDelete();
```

- [x] `category_id` → **null on delete** (apagar categoria não apaga histórico financeiro):

```php
$table->foreignId('category_id')
    ->nullable()
    ->constrained('categories')
    ->nullOnDelete();
```

> **Concluído:** validado no MySQL — `statement_import_id` CASCADE; `category_id` SET NULL.

### 4.4 Índices de performance (obrigatórios)

- [x] Índice em `occurred_on` — filtros de período / gráficos:

```php
$table->index('occurred_on');
```

- [x] Índice em `type` — filtros crédito/débito + agregações:

```php
$table->index('type');
```

- [x] Índice em `category_id` — filtros e pizza por categoria:

```php
$table->index('category_id');
```

- [x] Índice composto recomendado para dashboard (período + tipo):

```php
$table->index(['occurred_on', 'type']);
```

- [x] Índice em `statement_import_id` (além da FK, se o motor não criar automaticamente — MySQL InnoDB cria índice na FK):

```php
// geralmente já coberto por constrained(); validar com SHOW INDEX
```

> **Concluído:** `SHOW INDEX` confirma `occurred_on`, `type`, `category_id`, composto `occurred_on+type`, `statement_import_id` (FK) e UNIQUE `unique_hash`.

### 4.5 `unique_hash` — definição e unicidade (deduplicação)

#### 4.5.1 Como compor o hash (contrato para Etapa C — Parsers)

- [x] Algoritmo: `hash('sha256', $canonical)` → 64 chars hex.
- [x] String canônica (ordem fixa, separador `|`):

```text
{occurred_on:Y-m-d}|{amount:2dec}|{type}|{description_normalized}|{external_id|empty}|{source}
```

- [x] `description_normalized`:
  - [x] `trim`
  - [x] colapsar espaços internos (`preg_replace('/\s+/', ' ', ...)`)
  - [x] lowercase (`mb_strtolower`)
- [x] `amount`: sempre com 2 casas (`number_format((float)$amount, 2, '.', '')`)
- [x] `external_id`: string vazia se null
- [x] `source`: ex. `nubank`
- [x] **Não** incluir `statement_import_id` no hash — a deduplicação é **cross-import** (reupload do mesmo extrato / overlapping periods).

> **Concluído:** contrato implementado em `app/Support/TransactionHasher.php` (`TransactionHasher::make(...)`). Normalização de descrição/amount validada (mesmo hash após trim/espaços; amount diferente → hash diferente).

#### 4.5.2 Constraint de unicidade

- [x] UNIQUE global em `unique_hash` (MVP single-user):

```php
$table->char('unique_hash', 64);
$table->unique('unique_hash');
```

- [x] Se no futuro houver multi-conta/multi-user, migrar para:

```php
$table->unique(['user_id', 'unique_hash']);
```

> No MVP **não** há `user_id` em `transactions` (ownership via `statement_imports.user_id`). Aceitável para single-admin.

> **Concluído:** índice `transactions_unique_hash_unique` presente (UNIQUE). Escopo multi-user adiado.

#### 4.5.3 Comportamento esperado na importação (contrato Etapa C)

- [x] Insert com `unique_hash` duplicado → capturar `QueryException` / usar `insertOrIgnore` / `firstOrCreate` → incrementar `rows_skipped`.
- [x] Nunca atualizar silenciosamente valor de transação existente no MVP (skip > upsert), salvo decisão futura documentada.

> **Concluído:** contrato registrado em `docs/context.md` §3.2 e no docblock de `TransactionHasher`. Implementação do upload/parse fica para a Etapa C.

### 4.6 Blueprint completo `transactions` (referência)

- [x] Blueprint aplicado em `database/migrations/2026_09_27_233436_create_transactions_table.php` (bate 1:1 com a referência abaixo).

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('statement_import_id')
        ->constrained('statement_imports')
        ->cascadeOnDelete();

    $table->foreignId('category_id')
        ->nullable()
        ->constrained('categories')
        ->nullOnDelete();

    $table->string('external_id', 120)->nullable();
    $table->date('occurred_on');
    $table->string('description', 500);
    $table->decimal('amount', 14, 2); // absoluto >= 0
    $table->string('type', 10); // credit|debit
    $table->char('unique_hash', 64);
    $table->json('raw_payload')->nullable();
    $table->timestamps();

    $table->unique('unique_hash');
    $table->index('occurred_on');
    $table->index('type');
    $table->index('category_id');
    $table->index(['occurred_on', 'type']);
});
```

> **Concluído:** migration em produção local já reflete o blueprint completo.

### 4.7 Model `Transaction`

- [x] `app/Models/Transaction.php`
- [x] `$fillable`: `statement_import_id`, `category_id`, `external_id`, `occurred_on`, `description`, `amount`, `type`, `unique_hash`, `raw_payload`
- [x] `$casts`:
  - [x] `occurred_on` => `date`
  - [x] `amount` => `decimal:2`
  - [x] `raw_payload` => `array`
- [x] Relações:
  - [x] `belongsTo(StatementImport::class)`
  - [x] `belongsTo(Category::class)`
- [x] (Opcional) scopes: `scopeCredits`, `scopeDebits`, `scopeBetweenDates($from, $to)`
- [x] (Opcional) helper estático `Transaction::makeUniqueHash(...)` — implementação pode nascer na Etapa B (testável) e ser usada na C.

> **Concluído:** model completo; `makeUniqueHash` delega para `TransactionHasher`.

### 4.8 Helper de hash (recomendado já na Etapa B)

- [x] Criar `app/Support/TransactionHasher.php` (ou method no Model):

```php
public static function makeUniqueHash(
    string $occurredOn,   // Y-m-d
    string $amount,       // 1234.56
    string $type,         // credit|debit
    string $description,
    ?string $externalId = null,
    string $source = 'nubank'
): string {
    $description = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $description) ?? ''));
    $amount = number_format((float) $amount, 2, '.', '');
    $externalId = $externalId ?? '';
    $canonical = implode('|', [$occurredOn, $amount, $type, $description, $externalId, $source]);

    return hash('sha256', $canonical);
}
```

- [x] Teste unitário mínimo (pode ser Etapa B ou C):

```bash
php artisan make:test TransactionHasherTest --unit
```

- [x] Casos: mesma entrada → mesmo hash; mudança de descrição/espaços → hash estável após normalize; mudança de amount → hash diferente.

> **Concluído:** `TransactionHasher::make` + `Transaction::makeUniqueHash` (alias). `tests/Unit/TransactionHasherTest.php` — 4 testes PASS.

---

## 5. Constraints de unicidade para deduplicação (checklist explícito)

- [x] `users.email` → UNIQUE
- [x] `categories.slug` → UNIQUE
- [x] `transactions.unique_hash` → UNIQUE (cross-import idempotency)
- [x] Validar no MySQL após migrate:

```sql
SHOW INDEX FROM transactions WHERE Key_name = 'transactions_unique_hash_unique';
SHOW INDEX FROM transactions WHERE Column_name IN ('occurred_on', 'type', 'category_id');
```

- [x] Teste manual de constraint:

```bash
php artisan tinker
```

```php
// criar duas Transaction com mesmo unique_hash deve falhar na segunda
```

- [x] (Opcional) UNIQUE parcial em `checksum` de imports **não** é desejável sozinho (mesmo arquivo pode ser reprocessado após falha); preferir lógica de aplicação + `unique_hash` nas linhas.

> **Concluído:** UNIQUE em `users.email`, `categories.slug`, `transactions.unique_hash` confirmados. Insert duplicado de `unique_hash` rejeitado (`SQLSTATE 23000`). Sem UNIQUE em `statement_imports.checksum` (decisão mantida).

---

## 6. Factories / seeders de desenvolvimento (transações fake para UI)

### 6.1 `TransactionFactory` — atributos realistas

- [x] Arquivo: `database/factories/TransactionFactory.php`
- [x] Dependências: garantir `statement_import_id` e opcionalmente `category_id`.

| Atributo | Estratégia Faker / lógica |
| --- | --- |
| `statement_import_id` | `StatementImport::factory()` ou injetado no seeder |
| `category_id` | `Category::inRandomOrder()->first()?->id` (após CategorySeeder) ou `null` em 15% dos casos |
| `external_id` | `null` ou `fake()->uuid()` em 30% |
| `occurred_on` | distribuir nos **últimos 6 meses**: `fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d')` |
| `description` | pool BR realista (ver lista abaixo) |
| `type` | ~25% `credit`, ~75% `debit` (perfil típico de cartão/conta) |
| `amount` | créditos: `fake()->randomFloat(2, 50, 8000)`; débitos: `fake()->randomFloat(2, 5, 600)` |
| `unique_hash` | `Transaction::makeUniqueHash(...)` / `TransactionHasher` com os campos gerados + sufixo único se necessário para evitar colisão no seed em massa |
| `raw_payload` | `['seed' => true, 'description' => $description]` |

- [x] Pool de descrições (Nubank-like / PT-BR), exemplos:

```text
Pagamento recebido
Transferência recebida pelo Pix
Supermercado Extra
Uber *Trip
iFood *Pedido
Farmácia Droga Raia
Netflix.Com
Spotify Ab
Amazon Marketplace
Posto Shell
Condomínio
Energia Elétrica
Salário
Rendimento Conta
Padaria
```

- [x] Para volume alto (ex. 200–500 linhas), garantir unicidade do hash:
  - [x] Incluir `fake()->unique()->numerify('####')` na description **ou**
  - [ ] Concatenar um nonce só no seed (não no parser real) via `external_id` único.

> **Concluído:** factory smoke-test OK (`Transaction::factory()->count(3)->create()`). Unicidade via sufixo `#####` na description.

### 6.2 `DemoTransactionSeeder` (ou `DatabaseSeeder` branch local)

- [x] Gerar seeder:

```bash
php artisan make:seeder DemoTransactionSeeder
```

- [x] Fluxo do seeder:

  1. [x] Garantir admin existe (`AdminUserSeeder`).
  2. [x] Garantir categories (`CategorySeeder`).
  3. [x] Criar 1–3 `StatementImport` para o admin (`status=completed`, `format=csv`, `source=nubank`).
  4. [x] Para cada import, criar **N** transactions (recomendado **300** no total) via factory.
  5. [x] Atualizar counters do import (`rows_total`, `rows_imported`, `rows_skipped=0`).
  6. [x] Distribuir categorias: mapear description keywords → category (simples `match`/str_contains) para gráficos do dashboard fazerem sentido.

- [x] Exemplo de comando de massa:

```php
Transaction::factory()
    ->count(100)
    ->for($import)
    ->create();
```

- [x] **Guard clause:** rodar demo **apenas** em `local` / `APP_ENV !== production`:

```php
if (! app()->environment('local', 'development', 'testing')) {
    return;
}
```

> **Concluído:** `DemoTransactionSeeder` cria 3 imports × 100 tx, mapeia categorias por keyword, guard de ambiente ativo. Validado com `php artisan db:seed --class=DemoTransactionSeeder`.

### 6.3 `DatabaseSeeder` — orquestração

- [x] Editar `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([
        AdminUserSeeder::class,
        CategorySeeder::class,
        DemoTransactionSeeder::class, // só local
    ]);
}
```

- [x] Produção (Etapa E): chamar **somente** `AdminUserSeeder` + `CategorySeeder`:

```bash
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=CategorySeeder
```

> **Concluído:** `DatabaseSeeder` orquestra os 3 seeders. `DemoTransactionSeeder` já tem guard de ambiente (seguro se `db:seed` rodar em prod por engano). Em produção preferir seeders isolados (Etapa E).

### 6.4 Comandos de reset / populate (dev)

```bash
php artisan migrate:fresh --seed
```

- [x] Validar contagens:

```sql
SELECT COUNT(*) FROM users;              -- 1
SELECT COUNT(*) FROM categories;         -- >= 8
SELECT COUNT(*) FROM statement_imports;  -- >= 1 (local)
SELECT COUNT(*) FROM transactions;       -- ~300 (local)
SELECT type, COUNT(*), SUM(amount) FROM transactions GROUP BY type;
SELECT DATE_FORMAT(occurred_on, '%Y-%m') m, COUNT(*) FROM transactions GROUP BY m ORDER BY m;
```

> **Concluído:** `migrate:fresh --seed` OK. Resultado: users=1, categories=9, imports=3, transactions=300; créditos e débitos presentes; meses distribuídos nos últimos 6 meses.

### 6.5 Critério de sucesso — dados fake para Dashboard

- [x] Há créditos e débitos nos últimos 6 meses.
- [x] Há múltiplas categorias preenchidas (não tudo `NULL`).
- [x] Agregados mensais retornam > 0 linhas (base para gráfico de barras).
- [x] Frontend (Etapa D) consegue prototipar cards sem parser real.

> **Concluído:** credits=74, debits=226 (6m); 300/300 com categoria; 7 categorias distintas; 7 meses com dados. Base pronta para Dashboard (Etapa D) sem parser.

---

## 7. Models — mapa de relações (após criar tudo)

```text
User
 └── hasMany StatementImport
      └── hasMany Transaction
           └── belongsTo Category (nullable)

Category
 └── hasMany Transaction
```

- [x] Implementar relações inversas em todos os models.
- [x] (Opcional) `User::transactions()` via `hasManyThrough(Transaction::class, StatementImport::class)`.

> **Concluído:** mapa validado — `User::statementImports/transactions`, `StatementImport::user/transactions`, `Transaction::statementImport/category`, `Category::transactions`.

---

## 8. Inventário de arquivos da Etapa B

- [x] `database/migrations/*_create_users_table.php` (ajustada)
- [x] `database/migrations/*_create_cache_table.php` / `sessions` (framework)
- [x] `database/migrations/*_create_categories_table.php`
- [x] `database/migrations/*_create_statement_imports_table.php`
- [x] `database/migrations/*_create_transactions_table.php`
- [x] `app/Models/User.php`
- [x] `app/Models/Category.php`
- [x] `app/Models/StatementImport.php`
- [x] `app/Models/Transaction.php`
- [x] `app/Support/TransactionHasher.php` (recomendado)
- [x] `database/factories/UserFactory.php`
- [x] `database/factories/CategoryFactory.php`
- [x] `database/factories/StatementImportFactory.php`
- [x] `database/factories/TransactionFactory.php`
- [x] `database/seeders/AdminUserSeeder.php`
- [x] `database/seeders/CategorySeeder.php`
- [x] `database/seeders/DemoTransactionSeeder.php`
- [x] `database/seeders/DatabaseSeeder.php`
- [x] `.env.example` + `docs/context.md` — `ADMIN_EMAIL`, `ADMIN_PASSWORD`
- [x] (Opcional) `tests/Unit/TransactionHasherTest.php`

> **Concluído:** inventário 20/20 presente. `sessions` vive em `0001_01_01_000000_create_users_table.php`; cache em `0001_01_01_000001_create_cache_table.php`.

---

## 9. Sequência de comandos (copy-paste)

- [x] Sequência de referência documentada (já executada ao longo da Etapa B — **não** reexecutar `make:*` em repo existente).

```bash
# Framework tables (se ainda não existirem)
php artisan make:session-table
php artisan make:cache-table

# Domain
php artisan make:model Category -mfs
php artisan make:model StatementImport -mfs
php artisan make:model Transaction -mfs
php artisan make:seeder AdminUserSeeder
php artisan make:seeder DemoTransactionSeeder

# Após editar migrations/models/factories/seeders:
php artisan migrate:fresh --seed

# Smoke
php artisan tinker --execute="echo App\Models\User::count().' '.App\Models\Transaction::count();"
```

> **Concluído:** smoke atual = `1 300` (users / transactions). `make:session-table` desnecessário — `sessions` já está na migration de users.

---

## 10. Definition of Done — Etapa B

- [x] `php artisan migrate:fresh --seed` completa sem erro em ambiente local.
- [x] Existe **1** admin com email de `ADMIN_EMAIL`.
- [x] Categorias seed mínimas presentes e com `slug` único.
- [x] `statement_imports` e `transactions` criadas com FKs corretas (`cascade` / `nullOnDelete`).
- [x] Índices existem em `transactions`: `occurred_on`, `type`, `category_id`, `unique_hash` (UNIQUE), composto `occurred_on+type`.
- [x] Tentativa de inserir `unique_hash` duplicado falha (constraint OK).
- [x] ~centenas de transactions fake cobrem vários meses (base do Dashboard).
- [x] `DemoTransactionSeeder` não roda em `production`.
- [x] `docs/context.md` atualizado com decisão `amount` absoluto + `type`, e envs `ADMIN_*`.
- [x] Marcar checkboxes da **Etapa B** em `docs/MASTER_PLAN.md` como `[x]`.
- [x] **Só então** iniciar Etapa C (Auth / Parse / Upload).

> **Etapa B — DONE.** DoD validado (admin=1, categories≥8, FKs CASCADE/SET NULL, índices OK, unique_hash rejeita duplicata, 300 tx demo, guard de production no DemoTransactionSeeder, context/MASTER_PLAN atualizados). Próximo: **Etapa C**.

---

## Ordem interna sugerida (Etapa B)

> **0 (sessions/cache)** → **1 (users + AdminUserSeeder)** → **2 (categories + CategorySeeder)** → **3 (statement_imports)** → **4 (transactions + unique_hash/FKs/indexes)** → **5 (validar constraints)** → **6 (factories + DemoTransactionSeeder)** → **10 (DoD)**
