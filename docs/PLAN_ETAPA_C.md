# PLAN_ETAPA_C — Backend / Auth / Parse (Aura)

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises · *Inteligência invisível, controle absoluto.*  
> Planejamento técnico hiperdetalhado da **Etapa C** (autenticação session, parsers CSV/OFX, upload atômico, APIs de leitura).  
> Stack: **Laravel 11** · MySQL 8 / MariaDB · HostGator · SPA React (Etapa D).  
> Pré-requisito: Etapa B concluída (`docs/PLAN_ETAPA_B.md` — DoD `[x]`).  
> Referências: `docs/context.md` §3.1–3.3 · `docs/MASTER_PLAN.md` · `app/Support/TransactionHasher.php`.  
> Marque cada checkbox ao concluir. Não avance para a Etapa D sem a Definition of Done.

---

## Pré-requisitos e ordem de execução

- [x] Confirmar Etapa B DoD: `php artisan migrate:fresh --seed` OK; 1 admin; `TransactionHasher` + UNIQUE em `transactions.unique_hash`.
- [x] Confirmar envs: `SESSION_DRIVER=database`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `APP_TIMEZONE=America/Sao_Paulo`.
- [x] Confirmar Laravel 11 **sem** `RouteServiceProvider` (skeleton atual): rate limiters em `AppServiceProvider` + `bootstrap/app.php` / rotas.
- [x] Confirmar `routes/web.php` tem catch-all SPA — **registrar rotas `/api/*` e auth ANTES** do catch-all (ou em `routes/api.php` via `withRouting`).
- [x] Ordem interna sugerida (alinhar a `MASTER_PLAN`):

> **C.Auth** → **C.Storage/RateLimit** → **C.Parsers** → **C.UploadService** → **C.APIs Dashboard** → **C.Retention** → **C.Testes**

- [x] Convenções de pastas (criar se não existirem):

```text
app/
├── DTOs/
│   ├── ParsedTransaction.php
│   ├── ParseResult.php
│   └── UploadSummary.php
├── Exceptions/
│   ├── InvalidStatementException.php
│   └── UnsupportedStatementFormatException.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── AuthenticatedSessionController.php
│   │   │   └── CsrfCookieController.php   (opcional se usar Sanctum SPA)
│   │   ├── StatementUploadController.php
│   │   ├── TransactionController.php
│   │   ├── AnalyticsController.php
│   │   ├── CategoryController.php
│   │   └── StatementImportController.php
│   ├── Middleware/   (só se custom; preferir builtins)
│   ├── Requests/
│   │   ├── Auth/LoginRequest.php
│   │   ├── Statements/UploadStatementRequest.php
│   │   ├── Transactions/IndexTransactionsRequest.php
│   │   └── Analytics/DashboardAnalyticsRequest.php
│   └── Resources/   (opcional: TransactionResource, etc.)
├── Parsers/
│   ├── Contracts/StatementParserInterface.php
│   ├── NubankCsvParser.php
│   ├── OfxParser.php
│   └── StatementParserResolver.php
├── Services/
│   ├── StatementUploadService.php
│   ├── TransactionQueryService.php
│   ├── AnalyticsService.php
│   └── StatementRetentionService.php
└── Support/
    ├── Money.php              (sanitização monetária)
    ├── DateNormalizer.php     (pt-BR → Y-m-d)
    └── TransactionHasher.php  (já existe — Etapa B)
```

- [x] Dependência Composer OFX (decisão obrigatória — ver §3.4):

```bash
composer require cihansenturk/ofxparser
```

> **Escolhida (2026-09-28):** `cihansenturk/ofxparser` ^1.0 — fork mantido PHP 8.1+ da API `asgrim/ofxparser` (abandoned; latest exige PHP ~5.6\|~7.0). Documentado em `docs/context.md` §2.2.

> Alternativa aceitável: `ofxparser/ofxparser` / fork mantido — **documentar a escolhida** em `docs/context.md` §2.2 com data.

- [ ] **Não** instalar Breeze completo se o frontend for SPA React custom (Etapa D). Preferir auth manual JSON (session cookie + CSRF) — ver §1.
- [ ] Se optar por `laravel/sanctum` só para SPA cookie auth: instalar e configurar stateful domains; **não** expor tokens Bearer no MVP.

---

## 0. Decisões de arquitetura (contrato fechado)

### 0.1 Auth model

| Decisão | Valor MVP |
| --- | --- |
| Mecanismo | Session cookie Laravel (`web` guard) |
| Frontend | SPA React same-origin (Vite dev proxy → Laravel) |
| CSRF | Cookie `XSRF-TOKEN` + header `X-XSRF-TOKEN` (axios default) |
| Registro público | **Proibido** — zero rotas `register` |
| Reset de senha | Fora do MVP (admin troca via seeder / tinker / Etapa E) |
| Multi-user | Fora do escopo |

### 0.2 API style

| Decisão | Valor MVP |
| --- | --- |
| Prefixo | `/api` |
| Formato | JSON only (`Accept: application/json`) |
| Auth middleware | `auth` (session) + `EnsureFrontendRequestsAreStateful` se Sanctum; senão rotas em `web` middleware group com prefixo `/api` |
| Erros | JSON `{ "message": "...", "errors": {...} }` (Laravel default validation) |
| Upload path canônico | `POST /api/statements/upload` (resolve `[CONFIRMAR]` de `context.md`) |

### 0.3 Camadas

```text
Controller (fino)
  → FormRequest (validação HTTP)
    → Service (orquestração / regras)
      → Parser (I/O arquivo → DTOs)
      → Eloquent / DB::transaction (persistência)
  → JSON response / Resource
```

- [ ] Controllers **não** parseiam CSV/OFX nem montam `unique_hash`.
- [ ] Parsers **não** escrevem no banco.
- [ ] Services **não** leem `Request` diretamente (recebem DTOs / primitivos tipados).
- [ ] `TransactionHasher` é a **única** fonte de verdade do hash (já em `app/Support/`).

### 0.4 Política de duplicata (herdada Etapa B — imutável no MVP)

- [ ] Conflito de `unique_hash` → **SKIP** (não atualizar linha existente).
- [ ] Incrementar `statement_imports.rows_skipped`.
- [ ] Preferir `insertOrIgnore` em batch **ou** `firstOrCreate` por hash **ou** captura `QueryException` 23000.
- [ ] **Nunca** upsert silencioso de `amount`/`description`.

### 0.5 Amount / type (herdado)

- [ ] `amount` sempre `DECIMAL` absoluto `≥ 0`.
- [ ] `type=credit` entrada; `type=debit` saída.
- [ ] Saldo período = `SUM(credits) − SUM(debits)`.

---

## 1. Autenticação e Segurança

### 1.1 Bootstrap de rotas API no Laravel 11

- [x] Editar `bootstrap/app.php` para registrar `routes/api.php`:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

> **Decisão:** o snippet com `api:` acima **não** foi aplicado. Opção A (abaixo) carrega `routes/api.php` via `web.php` para herdar session/CSRF. Comentário documentando isso está em `bootstrap/app.php`.

- [x] Criar `routes/api.php` (vazio inicialmente).
- [x] Decidir estratégia de middleware de sessão na API (escolher **uma** e documentar):

#### Opção A (recomendada MVP — sem Sanctum) — **ESCOLHIDA**

- [x] Mover rotas `/api/*` para `routes/web.php` **antes** do catch-all SPA, agrupadas:

```php
Route::prefix('api')->group(base_path('routes/api.php'));
// catch-all SPA por último
Route::view('/{any?}', 'app')->where('any', '.*');
```

> `middleware('web')` explícito é desnecessário: rotas em `web.php` já recebem o grupo `web`.

- [x] Em `bootstrap/app.php`, **não** usar o `api` default (stateless) **ou** customizar `api` middleware para incluir `StartSession`, `VerifyCsrfToken`, `EncryptCookies`.

#### Opção B (Sanctum SPA) — **não adotada no MVP**

- [ ] `composer require laravel/sanctum` + publish config.
- [ ] `SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,aura.vonluqi.com`
- [ ] Middleware `EnsureFrontendRequestsAreStateful` no grupo `api`.
- [ ] Endpoint `GET /sanctum/csrf-cookie` antes do login.

> **Critério:** qualquer `POST/PUT/PATCH/DELETE` autenticado deve exigir CSRF válido; cookie session HttpOnly.

> **Concluído (§1.1):** `routes/api.php` criado; incluído em `web.php` com prefix `api` antes do SPA catch-all; `bootstrap/app.php` sem `api:` stateless; decisão documentada em `docs/context.md` (Decisões Etapa C).

### 1.2 Sessão e cookies

- [x] Revisar `config/session.php`:
  - [x] `driver=database` (já)
  - [x] `lifetime=120` (ou `env('SESSION_LIFETIME', 120)`)
  - [x] `same_site=lax` (default) — suficiente same-origin / Vite proxy
  - [x] `secure` => `env('SESSION_SECURE_COOKIE')` — `true` em produção HTTPS
  - [x] `http_only=true`
- [x] Adicionar ao `.env.example` / `docs/context.md`:

```env
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
# production: SESSION_SECURE_COOKIE=true
```

- [x] Confirmar tabela `sessions` migrada (Etapa B).

> **Concluído (§1.2):** `config/session.php` com `secure`/`http_only` tipados bool; envs em `.env.example` + `.env.production.example` (`SECURE=true` em prod); `docs/context.md` atualizado; tabela `sessions` confirmada no DB.

### 1.3 Controllers de Auth

- [x] Gerar controller:

```bash
php artisan make:controller Auth/AuthenticatedSessionController
```

#### 1.3.1 `POST /api/login`

- [x] Método `store(LoginRequest $request)`:
  1. Validar credenciais via `LoginRequest`.
  2. `Auth::attempt($credentials, $remember)` com guard `web`.
  3. Em falha: `422` ou `401` com `{ "message": "Credenciais inválidas." }` — **mesma mensagem** para email inexistente e senha errada (não vazar qual falhou).
  4. Em sucesso: `$request->session()->regenerate()`.
  5. Resposta `200`:

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@aura.local"
  }
}
```

- [x] **Não** retornar `password`, `remember_token`.
- [x] Suportar `remember` boolean opcional (default `false`).

> **Concluído (§1.3.1):** `AuthenticatedSessionController@store`, `LoginRequest`, rota `POST /api/login`, JSON em `/api/*` no `bootstrap/app.php`. Falha de auth → `401`. Testes: `tests/Feature/Auth/LoginTest.php` (3 PASS).

#### 1.3.2 `POST /api/logout`

- [x] Método `destroy(Request $request)`:
  1. Middleware `auth`.
  2. `Auth::guard('web')->logout()`.
  3. `$request->session()->invalidate()`.
  4. `$request->session()->regenerateToken()`.
  5. Resposta `204 No Content` (ou `200` `{ "message": "ok" }`).

> **Concluído (§1.3.2):** `destroy` + rota `POST /api/logout` (middleware `auth`); resposta `204`. Testes: `tests/Feature/Auth/LogoutTest.php`.

#### 1.3.3 `GET /api/user` (ou `/api/me`)

- [x] Método `show(Request $request)` — middleware `auth`.
- [x] Retornar o usuário autenticado (mesmo shape do login).
- [x] Usado pelo frontend (Etapa D) para bootstrap de sessão / redirect.

> **Concluído (§1.3.3):** `GET /api/user` → mesmo payload `{ user: { id, name, email } }`. Testes: `tests/Feature/Auth/UserTest.php`.

### 1.4 `LoginRequest` (FormRequest)

- [x] Gerar:

```bash
php artisan make:request Auth/LoginRequest
```

- [x] Rules:

| Campo | Rules |
| --- | --- |
| `email` | `required\|email\|max:255` |
| `password` | `required\|string` |
| `remember` | `sometimes\|boolean` |

- [x] `authorize(): true` (rota pública; rate limit protege).
- [x] (Opcional) método `authenticate()` espelhando Breeze — centraliza `Auth::attempt` + `RateLimiter` por email+IP.

> **Concluído (§1.4):** `LoginRequest` com `authenticate()`, `ensureIsNotRateLimited()`, `throttleKey()` (5 tentativas / email+IP). Falha → `401` genérico; excesso → `429` + `Retry-After`. Controller só chama `$request->authenticate()` + `session()->regenerate()`. Named limiter `throttle:login` (middleware de rota) permanece na §1.7.

### 1.5 Desabilitar / omitir registro público (obrigatório)

- [x] **Não** criar `RegisteredUserController`, rotas `register`, views de registro.
- [x] Grep de segurança após scaffolding:

```bash
rg -n "register|RegisteredUser" routes app
```

> Resultado: sem rotas/controllers de registro. Matches irrelevantes: `AppServiceProvider::register()`, comentários “Breeze” em `LoginRequest`.

- [x] Se algum pacote (Breeze/Fortify) tiver sido instalado por engano:
  - [x] Remover rotas `GET/POST /register`. *(N/A — pacotes não instalados)*
  - [x] Desabilitar features Fortify `Features::registration()`. *(N/A)*
- [x] Garantir que `AdminUserSeeder` é o **único** caminho de criação de usuário no MVP.
- [x] Teste feature: `POST /api/register` → `404` (ou rota inexistente).
- [x] Documentar em `docs/context.md` §3.1: “registro público omitido na Etapa C”.

> **Concluído (§1.5):** sem Breeze/Fortify; sem rotas register; `RegistrationDisabledTest` — `POST /api/register` → 404; `POST /register` → 404/405. Catch-all SPA atualizado para **não** engolir `/api/*` (`routes/web.php`). Documentado em `context.md` §3.1 + Decisões Etapa C.

### 1.6 Middleware `auth` e respostas JSON

- [x] Em `bootstrap/app.php` → `withMiddleware` / `withExceptions`:
  - [x] Unauthenticated API requests retornam `401 JSON`, **não** redirect HTML para `/login` (SPA cuida do redirect na Etapa D).

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->shouldRenderJsonWhen(function ($request, $e) {
        return $request->is('api/*') || $request->expectsJson();
    });
})
```

- [x] (Opcional) custom `unauthenticated` para forçar `{ "message": "Unauthenticated." }`.

> **Concluído (§1.6):** `shouldRenderJsonWhen` para `/api/*`; `renderable(AuthenticationException)` → `{ "message": "Unauthenticated." }` 401; `redirectGuestsTo('/')` (SPA, sem `route('login')`). Validado em `UserTest` / `LogoutTest` (com e sem `Accept: application/json`).

### 1.7 Rate limiting — login e upload

> Laravel 11: **não** existe `RouteServiceProvider` no skeleton. Registrar em `AppServiceProvider::boot()`.

#### 1.7.1 Definir limiters nomeados

- [x] Em `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('login', function (Request $request) {
        $email = (string) $request->input('email', '');
        return [
            Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ];
    });

    RateLimiter::for('statements-upload', function (Request $request) {
        $userId = $request->user()?->id ?: $request->ip();
        return Limit::perMinute(10)->by('upload|'.$userId);
    });
}
```

- [x] Valores MVP (ajustáveis via env se necessário):

| Limiter | Limite sugerido | By |
| --- | --- | --- |
| `login` | 5/min por email+IP; 20/min por IP | anti-bruteforce |
| `statements-upload` | 10/min por user | anti-abuso / DoS disco |

- [x] Resposta ao exceder: `429` com header `Retry-After` (default Laravel).
- [x] Mensagem amigável PT-BR opcional via `withMessage(...)` no `Limit`.

> **Concluído (§1.7.1):** limiters `login` e `statements-upload` em `AppServiceProvider::configureRateLimiting()`. Limites via env (`RATE_LIMIT_LOGIN_PER_EMAIL`, `RATE_LIMIT_LOGIN_PER_IP`, `RATE_LIMIT_UPLOAD_PER_USER`). Mensagens PT-BR via `Limit::response(...)` (Laravel 11 não tem `withMessage`). Aplicação nas rotas = §1.7.2.

#### 1.7.2 Aplicar nas rotas

- [x] `POST /api/login` → `middleware('throttle:login')`.
- [x] `POST /api/statements/upload` → `middleware(['auth', 'throttle:statements-upload'])`.
- [x] (Opcional) throttle genérico `api` 60/min nos endpoints de leitura.

> **Concluído (§1.7.2):** `throttle:login` em login; `auth` + `throttle:statements-upload` em upload (controller stub `501` até §4–5); `throttle:60,1` em `GET /api/user`.

#### 1.7.3 Testes de rate limit

- [x] Feature test: 6º login falho em <1min → `429`.
- [x] Feature test: upload além do limite → `429` (auth como admin).

> **Concluído (§1.7.3):** `tests/Feature/Auth/RateLimitTest.php` — 6º login falho → `429`; 11º upload (limite default 10) → `429` com mensagem do limiter `statements-upload`. `Cache::flush()` no `setUp` evita vazamento entre testes.

### 1.8 Mapa de rotas Auth (checklist)

| Método | Path | Middleware | Controller@action |
| --- | --- | --- | --- |
| `POST` | `/api/login` | `guest`, `throttle:login` | `AuthenticatedSessionController@store` |
| `POST` | `/api/logout` | `auth` | `AuthenticatedSessionController@destroy` |
| `GET` | `/api/user` | `auth` | `AuthenticatedSessionController@show` |

- [x] Middleware `guest`: se já autenticado, `POST /api/login` retorna `200` com user atual **ou** `409` — escolher e documentar (recomendado: retornar user atual sem erro).

> **Concluído (§1.8):** mapa acima refletido em `routes/api.php`. Decisão: **200 + user** (idempotente). `guest` → `App\Http\Middleware\RedirectIfAuthenticated` (API JSON; web → `/`). Documentado em `docs/context.md` (Decisões Etapa C). Teste: `LoginTest::test_login_when_already_authenticated_returns_current_user`.

### 1.9 Critérios de aceite — Auth

- [x] Não autenticado em rota protegida → `401` JSON.
- [x] Login com `ADMIN_EMAIL` / `ADMIN_PASSWORD` → session cookie setada + `200`.
- [x] Login inválido → `401/422` sem vazar existência do email.
- [x] Logout invalida sessão; próximo `GET /api/user` → `401`.
- [x] Nenhuma rota de registro pública.
- [x] Rate limit ativo em login.

> **Concluído (§1.9 / C.Auth DoD):** `tests/Feature/Auth/AuthAcceptanceTest.php` cobre os 6 critérios. Suite Auth completa deve permanecer verde.

### 1.10 Suite Auth — comando de validação

```bash
php artisan test --filter=Auth
```

- [x] Rodar suite Auth e confirmar todos PASS antes de avançar para §2 (Storage).

> **Suite Auth:** 20 passed (`php artisan test --filter=Auth`). **C.Auth concluída** — próximo: §2 Storage.

## 2. Storage privado e política de retenção

### 2.1 Disco `private` / `statements`

- [x] Confirmar `config/filesystems.php`: disk `local` já aponta para `storage_path('app/private')` (Laravel 11 default).
- [x] Criar disk explícito `statements` (clareza operacional):

```php
'statements' => [
    'driver' => 'local',
    'root' => storage_path('app/private/statements'),
    'visibility' => 'private',
    'throw' => true,
],
```

- [x] Garantir diretórios versionados via `.gitignore` pattern mas pasta criada em runtime:

```text
storage/app/private/statements/
storage/app/private/statements/.gitignore  # ignore all, keep folder
```

- [x] **Nunca** `Storage::disk('public')` para extratos.
- [x] **Nunca** criar symlink de `private` para `public/`.
- [x] Teste smoke: arquivo salvo **não** acessível via `https://.../storage/...`.

> **Concluído (§2.1):** disk `statements` em `config/filesystems.php`; pasta + `.gitignore` já versionados; `links` só aponta `public/storage` → `storage/app/public`. **`local.serve=false`** e **`statements.serve=false`** (extratos ficam sob o root do disk `local` — serve assinado não deve expô-los). Testes: `tests/Feature/Storage/StatementsDiskTest.php`.

### 2.2 Convenção de path armazenado

- [x] Path relativo no disk `statements`:

```text
{Y}/{m}/{userId}/{uuid}_{sanitizedOriginalName}.{ext}
```

Exemplo: `2026/09/1/9f3c…_nubank-setembro.csv`

- [x] Persistir em `statement_imports.stored_path` o path relativo (sem absolute path do servidor).
- [x] `original_filename`: nome original sanitizado (basename only; strip path traversal).
- [x] `checksum`: `hash_file('sha256', $absolutePath)` **antes** ou logo após store.

> **Concluído (§2.2):** helper `App\Support\StatementStorage` — `sanitizeOriginalFilename()`, `buildRelativePath()`, `checksum()`, `storeUploadedFile()` (retorna `stored_path` relativo + `original_filename` + `checksum` para o `StatementUploadService`). Testes: `tests/Unit/StatementStorageTest.php`.

### 2.3 Limites de upload (PHP + validação)

- [x] Alinhar FormRequest com PHP ini (Etapa A: `upload_max_filesize=20M`):
  - [x] Rule `file|max:10240` (10 MB) — conservador vs HostGator.
  - [x] Documentar em `context.md` se HostGator for menor.
- [x] Extensões: `csv`, `ofx`, `qfx` (se QFX = OFX-like — decidir: aceitar `qfx` mapeando para parser OFX **ou** rejeitar).
- [x] MIME rules (Laravel):

```text
mimes:csv,txt,ofx,xml
# + validação por extensão / conteúdo no Service (MIME de CSV varia)
```

> **Concluído (§2.3):** `UploadStatementRequest` — `max:10240`, `mimes:csv,txt,ofx,xml`, after-hook extensões `csv|ofx|qfx`. **Decisão:** aceitar `.qfx` → `detectedFormat()=ofx`. Mensagens PT-BR. Controller stub usa o FormRequest. Testes: `UploadStatementValidationTest`. Docs: `context.md` §2.2 + Decisões Etapa C.

### 2.4 Política de retenção simples

- [x] Criar `App\Services\StatementRetentionService`.
- [x] Regra MVP:

| Regra | Valor |
| --- | --- |
| Retenção de arquivo | **90 dias** após `statement_imports.created_at` |
| Escopo | apenas arquivos em disk `statements` referenciados por imports `completed`/`failed` |
| Ação | delete arquivo + setar `stored_path` para `null` **ou** manter path e marcar `purged_at` |

- [x] Decisão de schema (escolher **uma**):
  - [ ] **A (mínima):** só apagar arquivo; manter row; `stored_path` → string vazia/`null` (requer migration nullable já é string — permitir null).
  - [x] **B:** adicionar coluna `purged_at` nullable em `statement_imports` (migration Etapa C pequena).

> **Decisão:** **B** — apaga arquivo no disk, mantém `stored_path` (auditoria) e preenche `purged_at`.

- [x] Artisan command:

```bash
php artisan make:command Statements/PurgeOldStatementFilesCommand
```

- [x] Signature: `statements:purge-files {--days=90} {--dry-run}`.
- [x] Registrar schedule em `routes/console.php` (Laravel 11):

```php
Schedule::command('statements:purge-files')->dailyAt('03:15');
```

- [x] Documentar cron HostGator (Etapa E): `* * * * * php /home4/luca9682/aura/artisan schedule:run`.
- [x] Logs: quantos arquivos removidos; **não** logar conteúdo do extrato.

> **Concluído (§2.4):** migration `purged_at`; `StatementRetentionService`; command + schedule 03:15; env `STATEMENT_RETENTION_DAYS`; testes `StatementRetentionTest`. Cron HostGator documentado em Decisões Etapa C (`context.md`).

### 2.5 Critérios de aceite — Storage

- [x] Upload grava sob `storage/app/private/statements/...`.
- [x] URL pública direta retorna 404.
- [x] Command `--dry-run` lista candidatos sem apagar.
- [x] Após purge, import ainda listável; transações intactas.

> **Concluído (§2.5 / Storage DoD):** `StorageAcceptanceTest` cobre os 4 critérios. Catch-all SPA também exclui `/storage/*` (404 real). **C.Storage pronto** — próximo: §3 Parsers.

## 3. Arquitetura de Parsers

### 3.1 `StatementParserInterface`

- [x] Criar `app/Parsers/Contracts/StatementParserInterface.php`:

```php
namespace App\Parsers\Contracts;

use App\DTOs\ParseResult;
use SplFileInfo;

interface StatementParserInterface
{
    public function supports(string $format, string $source): bool;

    public function parse(SplFileInfo|string $file): ParseResult;
}
```

- [x] `format`: `csv` | `ofx`.
- [x] `source`: MVP sempre `nubank` (extensível depois).

> **Concluído (§3.1):** interface criada. DTOs `ParsedTransaction` (sem `uniqueHash`) + `ParseResult` adicionados como dependências de tipagem (contrato §3.2.1–3.2.2 / decisão hash no Service). Teste: `StatementParserInterfaceTest`.

### 3.2 DTOs

#### 3.2.1 `ParsedTransaction`

- [x] Criar `app/DTOs/ParsedTransaction.php` (readonly class PHP 8.2+):

| Propriedade | Tipo | Notas |
| --- | --- | --- |
| `occurredOn` | `string` | `Y-m-d` |
| `description` | `string` | texto original da linha (hasher normaliza) |
| `amount` | `string` | absoluto com 2 casas (`1234.56`) |
| `type` | `string` | `credit` \| `debit` |
| `externalId` | `?string` | FITID OFX / null CSV |
| `rawPayload` | `array` | linha original / campos OFX |
| ~~`uniqueHash`~~ | — | **omitido de propósito** — calculado no `StatementUploadService` via `TransactionHasher` |

- [x] Decisão: **calcular `uniqueHash` no Service** (não no parser) para garantir `source` único — parser só devolve campos brutos normalizados; Service chama `TransactionHasher::make(...)`.

> Recomendação: DTO **sem** `uniqueHash`; Service preenche na persistência. Parser permanece puro.

> **Concluído (§3.2.1):** `ParsedTransaction` readonly com invariantes (`Y-m-d`, amount `\d+\.\d{2}`, type `credit|debit`, description não vazia). Sem propriedade `uniqueHash`. Testes: `tests/Unit/DTOs/ParsedTransactionTest.php`.

#### 3.2.2 `ParseResult`

| Propriedade | Tipo | Notas |
| --- | --- | --- |
| `transactions` | `list<ParsedTransaction>` | linhas válidas |
| `rowsTotal` | `int` | linhas consideradas (inclui inválidas se contadas) |
| `rowErrors` | `list<array{line:int,message:string}>` | erros não fatais de linha |
| `format` | `string` | echo do formato detectado |
| `source` | `string` | `nubank` |

- [x] Erro fatal (arquivo ilegível / header ausente) → lançar `InvalidStatementException` (não retornar ParseResult vazio silencioso).

> **Concluído (§3.2.2):** `ParseResult` com invariantes + helpers `hasRowErrors()` / `truncatedRowErrors(20)`. `InvalidStatementException` criada (mensagem sanitizada, sem paths absolutos; `errorCode=invalid_statement` → HTTP 422 no upload). Testes: `ParseResultTest`.

#### 3.2.3 `UploadSummary` (resposta API)

| Campo | Tipo |
| --- | --- |
| `import_id` | int |
| `status` | string |
| `format` | string |
| `source` | string |
| `original_filename` | string |
| `rows_total` | int |
| `rows_imported` | int |
| `rows_skipped` | int |
| `row_errors` | array (max N, truncar) |
| `checksum` | string |

- [x] DTO `app/DTOs/UploadSummary.php` + `toArray()` (contrato §5.3) + `fromImport()`.
- [x] Truncar `row_errors` a **20** itens; expor `row_errors_count` com total real.

> **Concluído (§3.2.3):** `UploadSummary` readonly. Testes: `tests/Unit/DTOs/UploadSummaryTest.php`.

### 3.3 Helpers de normalização

#### 3.3.1 `App\Support\Money`

- [x] Métodos estáticos:
  - [x] `parseBrazilian(string $raw): string` — `"1.234,56"` / `"-1.234,56"` / `"1234,56"` → absoluto `"1234.56"`.
  - [x] `parseOfx(string|float $raw): string` — ponto decimal US → absoluto 2 casas.
  - [x] `assertNonNegative(string $amount): void`.
- [x] Remover `R$`, espaços, NBSP (`\xC2\xA0`).
- [x] Detectar sinal: se negativo → valor absoluto + tipo será `debit` (CSV Nubank) / conforme regra OFX.
- [x] Testes unitários cobrindo edge cases (ver §7).

> **Concluído (§3.3.1):** `Money` com `parseBrazilian` / `parseOfx` (+ variantes `*Signed` → `{amount,type,negative}`). Testes: `tests/Unit/MoneyTest.php`.

#### 3.3.2 `App\Support\DateNormalizer`

- [x] `fromBrazilian(string $raw): string` — `dd/mm/yyyy` → `Y-m-d`.
- [x] `fromOfx(string $raw): string` — `YYYYMMDD` ou `YYYYMMDDHHMMSS` → `Y-m-d`.
- [x] Invalid date → exception de linha (não fatal global se isolada).

> **Concluído (§3.3.2):** `DateNormalizer` com validação de calendário (`checkdate`). OFX aceita sufixo de timezone. Inválidos → `InvalidArgumentException` (parsers tratam por linha). Testes: `tests/Unit/DateNormalizerTest.php`.

### 3.4 `NubankCsvParser`

#### 3.4.1 Formato esperado (contrato)

> Export Nubank (conta / cartão) costuma variar. Fixar **um** perfil MVP e documentar fixture.

Perfil MVP sugerido — **extrato conta Nubank CSV**:

| Coluna (header) | Mapeamento |
| --- | --- |
| `Data` | `occurredOn` via `DateNormalizer::fromBrazilian` |
| `Valor` | amount + type via sinal (`Money::parseBrazilian`) |
| `Identificador` | `externalId` (se existir; senão null) |
| `Descrição` | `description` |

- [x] Aceitar BOM UTF-8 (`\xEF\xBB\xBF`) no header.
- [x] Delimiter: `,` (validar; se `;` aparecer em fixture real, auto-detect).
- [x] Enclosure: `"`.
- [x] Encoding: UTF-8; se inválido, tentar `Windows-1252` → UTF-8.
- [x] Pular linhas vazias.
- [x] Header case-insensitive / trim.
- [x] Se header obrigatório ausente → `InvalidStatementException`.

> **Concluído (§3.4.1):** contrato documentado em `tests/Fixtures/statements/README.md`; fixtures `sample_account*.csv`; `NubankCsvParser` lê BOM/`,`|`;`/UTF-8|1252/headers normalizados. Testes: `NubankCsvParserFormatTest` (6 PASS). Implementação completa de regras/linhas continua em §3.4.2–3.4.4.

#### 3.4.2 Regras de tipo (CSV Nubank)

- [x] Valor numérico **negativo** → `type=debit`, `amount=abs`.
- [x] Valor **positivo** → `type=credit`, `amount=abs`.
- [x] Valor zero → skip linha + rowError **ou** debit 0 (preferir **skip** com mensagem).

> **Concluído (§3.4.2):** `Money::parseBrazilianSigned` + `NubankCsvParser::mapRow` — negativo→`debit`, positivo→`credit`, `0.00`→skip + `rowError` `"Valor zero ignorado."`. Documentado em `tests/Fixtures/statements/README.md`. Testes: `NubankCsvParserTypeTest`.

#### 3.4.3 Implementação

- [x] Classe `app/Parsers/NubankCsvParser.php` implements `StatementParserInterface`.
- [x] `supports('csv', 'nubank') === true`.
- [x] Usar `fgetcsv` / `League\Csv` — **preferir PHP nativo** no MVP (zero dep extra).
- [x] Cada linha válida → `ParsedTransaction`.
- [x] `rawPayload` = array associativo da linha original.

> **Concluído (§3.4.3):** `NubankCsvParser` via `fgetcsv` nativo; `supports('csv','nubank')`; linhas válidas → `ParsedTransaction`; `rawPayload` com labels originais do header (`Data`/`Valor`/…). Testes: `NubankCsvParserImplementationTest`.

#### 3.4.4 Desafios obrigatórios (checklist de tratamento)

- [x] Sanitização monetária pt-BR (`1.234,56`).
- [x] Datas pt-BR → SQL.
- [x] Descrições com vírgulas / aspas.
- [x] Linhas com colunas a menos → rowError, não abortar arquivo inteiro (salvo header).
- [x] Geração de hash **cross-import** via `TransactionHasher` no Service.
- [x] Não incluir `statement_import_id` no hash.

> **Concluído (§3.4.4 / §3.4):** money/data/quotes via `Money`+`DateNormalizer`+`fgetcsv`; linhas incompletas → `rowError` `"Linha incompleta…"` sem abortar; parser **não** chama `TransactionHasher` (hash no Service, sem `statement_import_id`). Fixture `sample_account_invalid_rows.csv`. Testes: `NubankCsvParserChallengesTest`.

### 3.5 `OfxParser` (adapter de lib)

- [x] Instalar lib escolhida (`asgrim/ofxparser` recomendada).
- [x] Criar `app/Parsers/OfxParser.php` implements `StatementParserInterface`.
- [x] `supports('ofx', 'nubank') === true` (e `qfx` se aceito).
- [x] Adapter pattern:
  1. Lib lê arquivo → objetos/transações OFX.
  2. Mapear para `ParsedTransaction`.
- [x] Mapeamento típico:

| OFX | Aura |
| --- | --- |
| `DTPOSTED` | `occurredOn` |
| `TRNAMT` | amount + type (sinal) |
| `MEMO` / `NAME` | `description` (preferir MEMO; fallback NAME) |
| `FITID` | `externalId` |
| raw | `rawPayload` |

- [x] `TRNAMT` negativo → `debit`; positivo → `credit` (convenção OFX banking).
- [x] Validar que lib não explode com encoding SGML OFX (arquivos bancários BR às vezes “OFX sujo”).
- [x] Em falha de parse estrutural → `InvalidStatementException` com mensagem segura (sem path absoluto).

> **Concluído (§3.5):** lib `cihansenturk/ofxparser` ^1.0; `OfxParser` adapter (`supports` ofx|qfx + nubank); MEMO→NAME, FITID→externalId, sinal via `Money::parseOfxSigned`; zero→skip; malformed→`InvalidStatementException` sem path. Fixtures `tests/Fixtures/statements/ofx/*`. Testes: `OfxParserTest`.

### 3.6 `StatementParserResolver`

- [x] Criar `app/Parsers/StatementParserResolver.php`:
  - [x] Recebe lista de parsers (DI container binding).
  - [x] `resolve(string $format, string $source): StatementParserInterface`.
  - [x] Se nenhum supports → `UnsupportedStatementFormatException` → HTTP `422`.
- [x] Registrar binding em `AppServiceProvider`:

```php
$this->app->singleton(StatementParserResolver::class, function ($app) {
    return new StatementParserResolver([
        $app->make(NubankCsvParser::class),
        $app->make(OfxParser::class),
    ]);
});
```

> **Concluído (§3.6):** `StatementParserResolver` + `UnsupportedStatementFormatException`; singleton no `AppServiceProvider`; render JSON `422` em `bootstrap/app.php`. Testes: `StatementParserResolverTest`.

### 3.7 Detecção de formato no upload

- [x] Prioridade:
  1. Extensão do arquivo (`.csv` → csv, `.ofx`/`.qfx` → ofx).
  2. (Opcional) sniff conteúdo (`OFXHEADER` / tags `<OFX>`).
- [x] `source` default `nubank` (campo form opcional futuro; MVP fixo).
- [x] Guardar `format` + `source` em `statement_imports`.

> **Concluído (§3.7):** `App\Support\StatementFormatDetector` — extensão primeiro (`.qfx`→`ofx`), sniff `OFXHEADER`/`<OFX>` como fallback; `source` default `nubank`; `detectForImport()` alimenta `statement_imports.format|source`. `UploadStatementRequest` delega ao detector. Testes: `StatementFormatDetectorTest` (+ validação upload existente).

---

## 4. Serviço de Upload e Persistência

### 4.1 `StatementUploadService` — responsabilidade

Orquestração **única** do fluxo:

1. Validar already done no FormRequest.
2. Persistir arquivo no disk `statements`.
3. Calcular checksum.
4. Criar `StatementImport` (`status=processing`).
5. Resolver parser + parse.
6. Em `DB::transaction()`: inserir transactions + atualizar counters.
7. Marcar `completed` ou `failed`.
8. Retornar `UploadSummary`.

> **Concluído (§4.1):** `App\Services\StatementUploadService::handle` orquestra os 8 passos (store via `StatementStorage` → import `processing` → parse → persist atômico com skip-on-dupe → `UploadSummary`). Falha de parse marca `failed` e mantém arquivo. Testes: `StatementUploadServiceTest` (3 PASS). Detalhamento do fluxo em §4.3.

### 4.2 Assinatura sugerida

```php
namespace App\Services;

use App\DTOs\UploadSummary;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class StatementUploadService
{
    public function __construct(
        private StatementParserResolver $parsers,
        // Storage via Facade OK
    ) {}

    public function handle(User $user, UploadedFile $file, string $source = 'nubank'): UploadSummary
    {
        // ...
    }
}
```

> **Concluído (§4.2):** assinatura `handle(User, UploadedFile, string $source = 'nubank'): UploadSummary` + DI de `StatementParserResolver` em `StatementUploadService`. Teste de contrato: `StatementUploadServiceSignatureTest`.

### 4.3 Fluxo detalhado (sub-tasks)

#### 4.3.1 Store arquivo

- [x] `$format = $this->detectFormat($file)`.
- [x] `$path = $file->storeAs($directory, $filename, 'statements')`.
- [x] `$checksum = hash_file('sha256', Storage::disk('statements')->path($path))`.
- [x] Sanitizar `original_filename` (`basename`).

> **Concluído (§4.3.1):** `StatementFormatDetector::detect` + `StatementStorage::storeUploadedFile` (putFileAs no disk `statements`, path relativo `{Y}/{m}/{userId}/{uuid}_{name}`, checksum SHA-256, filename sanitizado). Teste: `StatementUploadServiceTest::test_store_step_*`.

#### 4.3.2 Criar import `processing`

- [x] `StatementImport::query()->create([... 'status' => 'processing', counters 0 ...])`.
- [x] Se store falhar antes → não criar row (ou criar `failed` sem arquivo — preferir **não criar**).

> **Concluído (§4.3.2):** import criado só após store OK, com `status=processing` e counters 0; falha de store não cria row. Testes: `test_creates_import_as_processing_*`, `test_store_failure_does_not_create_import_row`.

#### 4.3.3 Parse

- [x] `try { $result = $resolver->resolve($format, $source)->parse(...); }`
- [x] `catch (InvalidStatementException $e)`:
  - [x] Atualizar import `status=failed`, `error_message` sanitizado (max 1000 chars, sem paths).
  - [x] Re-throw ou retornar summary failed → Controller mapeia `422`.
- [x] Decisão MVP: em falha de parse, **manter arquivo** para debug admin (retenção ainda aplica).

> **Concluído (§4.3.3):** resolve+parse; `InvalidStatementException` → import `failed` + `error_message` sanitizado (paths→`[path]`, max 1000) + rethrow → JSON `422` (`bootstrap/app.php`); arquivo mantido. Testes: `test_invalid_parse_*`, `test_parse_failure_sanitizes_*`, `test_parse_invalid_statement_exception_renders_*`.

#### 4.3.4 Persistência atômica

- [x] Abrir `DB::transaction(function () use (...) { ... })`.
- [x] Para cada `ParsedTransaction`:
  1. Calcular `$hash = TransactionHasher::make($occurredOn, $amount, $type, $description, $externalId, $source)`.
  2. Tentar insert com `unique_hash`.
  3. Se duplicata → `rows_skipped++`.
  4. Se nova → `rows_imported++`; `category_id=null` no MVP.
- [x] Estratégias de insert (escolher **uma** e testar):

| Estratégia | Prós | Contras |
| --- | --- | --- |
| `insertOrIgnore` batch chunks de 100 | rápido | precisa montar arrays; timestamps manuais |
| loop `firstOrCreate(['unique_hash' => ...], $attrs)` | claro | N queries |
| `try/catch QueryException` 23000 | explícito | barulhento |

> **Escolhida:** pré-filtro `whereIn(unique_hash)` + `insert` em chunks de **200** (algoritmo recomendado abaixo).

> Recomendação MVP: **chunks `insertOrIgnore`** + contar `rows_imported = totalAttempted - skipped` via comparação prévia de hashes existentes **ou** `rows_imported = count(inserted)` se o driver retornar affected rows.

- [x] Algoritmo recomendado (claro + eficiente o bastante para extratos pessoais):

```text
1. Mapear DTOs → rows com unique_hash
2. $existing = Transaction::whereIn('unique_hash', $hashes)->pluck('unique_hash')
3. Filtrar $newRows
4. rows_skipped = count(existing ∩ hashes) + rowErrors
5. insert em chunks de 200 as $newRows
6. rows_imported = count($newRows)
7. rows_total = parseResult.rowsTotal (ou imported+skipped+errors)
8. Atualizar StatementImport status=completed + counters
```

- [x] Qualquer exception não tratada dentro da transaction → rollback das inserts; Service catch externo marca import `failed` e propaga.
- [x] **Importante:** criação do `StatementImport` pode ficar **fora** ou **dentro** da transaction:
  - [x] Preferir: criar import `processing` fora; updates + transactions dentro; se rollback das tx, import ainda existe — então em catch setar `failed`.
  - [ ] Alternativa: tudo dentro (import + txs) — se parse ok mas insert falha, nada persiste (exceto arquivo). Também válido.

> Recomendação: **arquivo + import processing fora**; **transactions + completed dentro de DB::transaction**; on failure update import failed **fora** do rollback (nova query).

> **Concluído (§4.3.4):** `persistParsed` — hash via `TransactionHasher`, pré-filtro de duplicatas, `insert` chunks 200, `category_id=null`, counters; rollback + `failed` se exception na transaction. Testes: `test_atomic_persist_*`, `test_persist_failure_rolls_back_*`, `test_reupload_*`.

#### 4.3.5 Idempotência de reupload do mesmo arquivo

- [x] Mesmo `checksum` **não** bloqueia reupload (Etapa B: sem UNIQUE em checksum).
- [x] Deduplicação é **por linha** (`unique_hash`).
- [x] Reupload → `rows_imported=0`, `rows_skipped=N` (todas duplicatas) + `200` com summary — **sucesso parcial**, não erro.

> **Concluído (§4.3.5):** reupload com mesmo checksum cria novo `StatementImport` `completed`; linhas deduplicadas por `unique_hash` (`rows_imported=0`, `rows_skipped=N`); summary de sucesso parcial (status HTTP 2xx no controller §5.3). Teste: `test_reupload_skips_duplicate_hashes_without_blocking_checksum`.

### 4.4 Exceções de domínio

- [x] `InvalidStatementException` — arquivo inválido / header / OFX quebrado → HTTP `422`.
- [x] `UnsupportedStatementFormatException` — extensão não suportada → HTTP `422`.
- [x] Não expor `SQLSTATE`, paths absolutos, stack traces em produção (`APP_DEBUG=false`).

> **Concluído (§4.4):** domain errors → JSON `422`; com `APP_DEBUG=false`, `QueryException` e throwables genéricos na API retornam `{ "message": "Não foi possível processar a solicitação." }` sem SQLSTATE/path/trace (`bootstrap/app.php`). Testes: `DomainExceptionSafetyTest`.

### 4.5 Status machine de `statement_imports`

```text
pending → processing → completed
                     ↘ failed
```

- [x] MVP pode pular `pending` e criar já `processing`.
- [x] Não há reprocessamento automático no MVP (manual = novo upload).

> **Concluído (§4.5):** constantes + `TRANSITIONS` em `StatementImport`; upload cria `processing` (pula `pending`) e termina em `completed`|`failed` (terminais, sem reprocess). Novo upload = novo import. Testes: `StatementImportStatusMachineTest`.

---

## 5. Rotas e Endpoints da API

### 5.1 Inventário completo

| Método | Path | Auth | Throttle | Controller | FormRequest |
| --- | --- | --- | --- | --- | --- |
| `GET` | `/api/csrf-cookie` | — | — | closure `204` | — |
| `POST` | `/api/login` | guest | `login` | `AuthenticatedSessionController@store` | `LoginRequest` |
| `POST` | `/api/logout` | auth | — | `AuthenticatedSessionController@destroy` | — |
| `GET` | `/api/user` | auth | — | `AuthenticatedSessionController@show` | — |
| `POST` | `/api/statements/upload` | auth | `statements-upload` | `StatementUploadController@store` | `UploadStatementRequest` |
| `GET` | `/api/statements` | auth | api | `StatementImportController@index` | — |
| `GET` | `/api/statements/{id}` | auth | api | `StatementImportController@show` | — |
| `GET` | `/api/transactions` | auth | api | `TransactionController@index` | `IndexTransactionsRequest` |
| `GET` | `/api/analytics/dashboard` | auth | api | `AnalyticsController@dashboard` | `DashboardAnalyticsRequest` |
| `GET` | `/api/categories` | auth | api | `CategoryController@index` | — |

> Nomes canônicos para Etapa D. Path de upload confirmado: `POST /api/statements/upload` (sem `[CONFIRMAR]`).

> **Concluído (§5.1):** rotas registradas em `routes/api.php` (auth + throttle); stubs `501` para endpoints ainda não implementados (§5.3+); `context.md` §3.2 atualizado (upload canônico + inventário). Testes: `ApiRouteInventoryTest`.

### 5.2 `UploadStatementRequest`

- [x] Gerar:

```bash
php artisan make:request Statements/UploadStatementRequest
```

- [x] `authorize()`: `$this->user() !== null`.
- [x] Rules:

```php
[
    'file' => [
        'required',
        'file',
        'max:10240', // KB
        'mimes:csv,txt,ofx,xml',
    ],
    'source' => ['sometimes', 'string', 'in:nubank'],
]
```

- [x] After validation hook (opcional): validar extensão real ∈ `csv,ofx,qfx`.
- [x] Mensagens PT-BR amigáveis (`file.required`, `file.max`, `file.mimes`).

> **Concluído (§5.2):** `UploadStatementRequest` (já de §2.3) — auth, `max:10240`, mimes, extensão `csv|ofx|qfx`, `source=nubank`, mensagens PT-BR + helpers `detection()`. Testes: `UploadStatementValidationTest`.

### 5.3 `StatementUploadController@store`

- [x] Injetar `StatementUploadService`.
- [x] Chamar `handle($request->user(), $request->file('file'), $request->input('source', 'nubank'))`.
- [x] Resposta `201 Created`:

```json
{
  "data": {
    "import_id": 12,
    "status": "completed",
    "format": "csv",
    "source": "nubank",
    "original_filename": "NU_123.csv",
    "checksum": "a1b2…",
    "rows_total": 120,
    "rows_imported": 100,
    "rows_skipped": 18,
    "row_errors_count": 2,
    "row_errors": [
      { "line": 45, "message": "Data inválida" }
    ]
  }
}
```

- [x] Truncar `row_errors` a no máximo **20** itens na resposta.
- [x] Falha de parse → `422`:

```json
{
  "message": "Não foi possível ler o extrato.",
  "error_code": "invalid_statement",
  "import_id": 13
}
```

> **Concluído (§5.3):** controller injeta `StatementUploadService` → `201` `{ data: UploadSummary }`; `row_errors` truncados (DTO); parse fail → `422` com `import_id` (`InvalidStatementException::withImportId`). Testes: `UploadStatementEndpointTest`.

### 5.4 `GET /api/transactions` — listagem filtrada

#### 5.4.1 Query params (`IndexTransactionsRequest`)

| Param | Tipo | Rules / default |
| --- | --- | --- |
| `from` | date `Y-m-d` | `nullable\|date\|before_or_equal:to` |
| `to` | date `Y-m-d` | `nullable\|date\|after_or_equal:from` |
| `type` | string | `nullable\|in:credit,debit` |
| `category_id` | int | `nullable\|exists:categories,id` |
| `q` | string | `nullable\|string\|max:120` (LIKE description) |
| `statement_import_id` | int | `nullable\|exists:statement_imports,id` |
| `page` | int | pagination default |
| `per_page` | int | `nullable\|integer\|min:1\|max:100` default **20** |
| `sort` | string | `nullable\|in:occurred_on,amount,created_at` default `occurred_on` |
| `direction` | string | `in:asc,desc` default `desc` |

> **Concluído (§5.4.1):** `App\Http\Requests\Transactions\IndexTransactionsRequest` — rules + defaults (`per_page=20`, `sort=occurred_on`, `direction=desc`) + helpers `filters()`. Wire no `TransactionController@index`. Testes: `IndexTransactionsRequestTest`.

#### 5.4.2 `TransactionQueryService`

- [x] Encapsular query Eloquent com scopes existentes (`betweenDates`, `credits`, `debits`).
- [x] Eager load `category:id,name,slug,color` (evitar N+1).
- [x] Ownership: filtrar via `whereHas('statementImport', fn ($q) => $q->where('user_id', $user->id))` — defesa mesmo single-admin.

> **Concluído (§5.4.2):** `App\Services\TransactionQueryService` — `forUser` / `baseForUser` + `applyFilters` (scopes + q/category/import) + sort; eager `category`. Testes: `TransactionQueryServiceTest`.

#### 5.4.3 Resposta

```json
{
  "data": [
    {
      "id": 1,
      "occurred_on": "2026-09-01",
      "description": "Supermercado Extra",
      "amount": "89.90",
      "type": "debit",
      "category": { "id": 2, "name": "Alimentação", "slug": "alimentacao", "color": "#DCCFFF" },
      "statement_import_id": 3,
      "external_id": null
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 300,
    "last_page": 15
  }
}
```

- [x] Usar `LengthAwarePaginator` Laravel.
- [x] (Opcional) `TransactionResource` para shape estável.

> **Concluído (§5.4.3 / §5.4):** `TransactionController@index` pagina via `paginate()` + `TransactionResource` / `CategoryResource`; payload `{ data, meta }`. Testes: `IndexTransactionsEndpointTest`.

### 5.5 `GET /api/analytics/dashboard` — agregados

#### 5.5.1 Query params (`DashboardAnalyticsRequest`)

| Param | Notes |
| --- | --- |
| `from` / `to` | obrigatórios **ou** default = mês corrente (`APP_TIMEZONE`) |
| `type` | opcional |
| `category_id` | opcional |
| `q` | opcional (mesmos filtros da listagem, para coerência cards↔tabela) |
| `group_by` | `day` \| `month` (default `month`) para série |

> **Concluído (§5.5.1):** `App\Http\Requests\Analytics\DashboardAnalyticsRequest` — from/to juntos ou default mês corrente (`APP_TIMEZONE`); `group_by` default `month`; filtros type/category/q; `filters()`. Wire no `AnalyticsController@dashboard`. Testes: `DashboardAnalyticsRequestTest`.

#### 5.5.2 `AnalyticsService`

- [x] Reutilizar mesma base de filtros que `TransactionQueryService` (extrair `AppliesTransactionFilters` trait/scope).
- [x] Cards:

| Métrica | Cálculo |
| --- | --- |
| `balance` | `sum(credit) - sum(debit)` |
| `total_income` | `sum(amount) where type=credit` |
| `total_expense` | `sum(amount) where type=debit` |
| `transactions_count` | `count(*)` |

- [x] Série temporal (`series`):

```json
[
  { "period": "2026-09", "income": "5000.00", "expense": "3200.50", "balance": "1799.50" }
]
```

- [x] Distribuição por categoria (`by_category`):

```json
[
  { "category_id": 2, "name": "Alimentação", "color": "#DCCFFF", "total": "450.00", "count": 12 }
]
```

- [x] Apenas `debit` na pizza de despesas (default); incluir `type` no payload.
- [x] Performance: **uma** query agregada por bloco (ou SQL com `CASE`), índices Etapa B já cobrem `occurred_on+type`.

> **Concluído (§5.5.2):** `App\Services\Concerns\AppliesTransactionFilters` + `App\Services\AnalyticsService` — cards/series/by_category via `TransactionQueryService::baseForUser`; money strings; by_category default debit + `type`. Testes: `AnalyticsServiceTest`. Wire HTTP em §5.5.3.

#### 5.5.3 Resposta completa

```json
{
  "data": {
    "filters": { "from": "2026-09-01", "to": "2026-09-30" },
    "cards": {
      "balance": "1799.50",
      "total_income": "5000.00",
      "total_expense": "3200.50",
      "transactions_count": 142
    },
    "series": [ /* ... */ ],
    "by_category": [ /* ... */ ]
  }
}
```

- [x] Valores monetários sempre **string** com 2 casas (evitar float JSON).

> **Concluído (§5.5.3 / §5.5):** `AnalyticsController@dashboard` → `{ data: { filters, cards, series, by_category } }`. Testes: `DashboardAnalyticsEndpointTest`.

### 5.6 `GET /api/categories`

- [x] Lista simples ordenada por `name`:

```json
{
  "data": [
    { "id": 1, "name": "Receitas", "slug": "receitas", "type": "income", "color": "#A8E6C3" }
  ]
}
```

- [x] Sem paginação (volume seed ~9).

> **Concluído (§5.6):** `CategoryController@index` + `CategoryResource` (inclui `type`); lista ordenada por `name`, sem `meta`. Testes: `IndexCategoriesEndpointTest`.

### 5.7 `GET /api/statements` (+ show)

- [x] Index: últimos imports do user, `per_page=20`, order `created_at desc`.
- [x] Campos: id, original_filename, format, source, status, counters, checksum, created_at, error_message (se failed).
- [x] **Não** expor `stored_path` absoluto; se expor path, apenas relativo (ou omitir no MVP).
- [x] Show: detalhe + (opcional) contagem já nos counters.

> **Concluído (§5.7):** `StatementImportController` + `StatementImportResource` — index paginado (`per_page=20`, `created_at desc`); show com ownership → `404`; `stored_path` omitido; `error_message` só em `failed`. Testes: `StatementImportEndpointTest`.

### 5.8 CSRF / headers (contrato Etapa D)

- [x] Documentar para o frontend:
  1. `GET` qualquer página / `csrf-cookie` para setar `XSRF-TOKEN`.
  2. Axios `withCredentials: true`.
  3. Header `X-XSRF-TOKEN` lido do cookie.
  4. `Accept: application/json`.
- [x] Vite proxy deve encaminhar cookies para o mesmo host efetivo.

> **Concluído (§5.8):** `GET /api/csrf-cookie` (204, sem Sanctum); defaults em `resources/js/bootstrap.js`; proxy `/api` em `vite.config.js`; contrato em `docs/context.md` §3.1. Testes: `CsrfCookieTest`.

### 5.9 Erros HTTP padronizados

| Situação | Status |
| --- | --- |
| Não autenticado | `401` |
| Validação FormRequest | `422` + `errors` |
| Parse inválido | `422` + `error_code` |
| Rate limit | `429` |
| Não encontrado (import id) | `404` |
| Erro inesperado | `500` (log + mensagem genérica) |

- [x] Matriz acima documentada e coberta por teste de contrato.

> **Concluído (§5.9 / §5):** payloads canônicos em `docs/context.md` §3.1; handlers já em `bootstrap/app.php` (§1.6 / §4.4). Testes: `HttpErrorContractTest`.

---

## 6. Controllers finos e wiring

### 6.1 Artisan batch

```bash
php artisan make:controller Auth/AuthenticatedSessionController
php artisan make:controller StatementUploadController
php artisan make:controller StatementImportController
php artisan make:controller TransactionController
php artisan make:controller AnalyticsController
php artisan make:controller CategoryController

php artisan make:request Auth/LoginRequest
php artisan make:request Statements/UploadStatementRequest
php artisan make:request Transactions/IndexTransactionsRequest
php artisan make:request Analytics/DashboardAnalyticsRequest

php artisan make:class Services/StatementUploadService
php artisan make:class Services/TransactionQueryService
php artisan make:class Services/AnalyticsService
php artisan make:class Services/StatementRetentionService

php artisan make:class Parsers/Contracts/StatementParserInterface
php artisan make:class Parsers/NubankCsvParser
php artisan make:class Parsers/OfxParser
php artisan make:class Parsers/StatementParserResolver

php artisan make:class DTOs/ParsedTransaction
php artisan make:class DTOs/ParseResult
php artisan make:class DTOs/UploadSummary

php artisan make:class Support/Money
php artisan make:class Support/DateNormalizer

php artisan make:exception InvalidStatementException
php artisan make:exception UnsupportedStatementFormatException

php artisan make:command Statements/PurgeOldStatementFilesCommand
```

- [x] Controllers / FormRequests / Services / Parsers / DTOs / Support / Exceptions / Command criados com namespaces corretos.

> Ajustar namespaces se `make:class` criar sob `App\` errado — mover para pastas acima.

> **Concluído (§6.1):** inventário acima já materializado nas seções §1–§5 (26 arquivos; namespaces `App\Http\…`, `App\Services`, `App\Parsers…`, `App\DTOs`, `App\Support`, `App\Exceptions`, `App\Console\Commands\Statements`). Sem re-scaffold.

### 6.2 `routes/api.php` (esqueleto de referência)

```php
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StatementImportController;
use App\Http\Controllers\StatementUploadController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/csrf-cookie', fn () => response()->noContent())->name('api.csrf-cookie');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:login'])
    ->name('api.login');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');
    Route::get('/user', [AuthenticatedSessionController::class, 'show'])->name('api.user');

    Route::post('/statements/upload', [StatementUploadController::class, 'store'])
        ->middleware('throttle:statements-upload')
        ->name('api.statements.upload');

    Route::get('/statements', [StatementImportController::class, 'index'])->name('api.statements.index');
    Route::get('/statements/{statementImport}', [StatementImportController::class, 'show'])->name('api.statements.show');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('api.transactions.index');
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard'])->name('api.analytics.dashboard');
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
});
```

- [x] Garantir que catch-all SPA **não** engole `/api/*`.
- [x] Route model binding: `StatementImport` deve pertencer ao user (Policy ou scope no controller).

> **Concluído (§6.2):** `routes/api.php` carregado em `web.php` **antes** do catch-all (`(?!api|storage)`); `Route::bind('statementImport')` owner-scoped em `AppServiceProvider` (404 se outro user / missing). Testes: `RoutesWiringTest`.

### 6.3 Policy (defesa em profundidade)

- [x] `php artisan make:policy StatementImportPolicy --model=StatementImport`
- [x] `view` / `viewAny`: `$user->id === $statementImport->user_id`.
- [x] Registrar em `AppServiceProvider` ou auto-discovery.
- [x] `Transaction` access apenas via import ownership (não precisa Policy se query já filtra).

> **Concluído (§6.3 / §6):** `App\Policies\StatementImportPolicy` (auto-discovery); `authorize` em `StatementImportController`; `AuthorizesRequests` no `Controller` base. Transactions continuam filtradas por `TransactionQueryService` / `whereHas(statementImport.user_id)`. Testes: `StatementImportPolicyTest`.

---

## 7. Testes e Fixtures

### 7.1 Estrutura de testes

- [x] Árvore Unit/Feature alinhada ao repo; path único de fixtures documentado.

```text
tests/
├── Fixtures/statements/                 ← path canônico (base_path('tests/Fixtures/...'))
│   ├── README.md
│   ├── nubank/
│   │   ├── sample_account.csv
│   │   ├── sample_account_bom.csv
│   │   ├── sample_account_semicolon.csv
│   │   ├── sample_account_missing_header.csv
│   │   ├── sample_account_invalid_rows.csv
│   │   └── sample_account_realistic_anon.csv
│   └── ofx/
│       ├── sample_nubank.ofx
│       └── sample_malformed.ofx
├── Unit/
│   ├── TransactionHasherTest.php
│   ├── MoneyTest.php
│   ├── DateNormalizerTest.php
│   ├── StatementStorageTest.php
│   ├── StatementFormatDetectorTest.php
│   ├── DTOs/ (ParsedTransaction, ParseResult, UploadSummary)
│   ├── Models/StatementImportStatusMachineTest.php
│   ├── Policies/StatementImportPolicyTest.php
│   ├── Services/StatementUploadServiceSignatureTest.php
│   └── Parsers/
│       ├── StatementParserInterfaceTest.php
│       ├── StatementParserResolverTest.php
│       ├── NubankCsvParser*Test.php
│       └── OfxParserTest.php
└── Feature/
    ├── ApiRouteInventoryTest.php
    ├── DomainExceptionSafetyTest.php
    ├── HttpErrorContractTest.php
    ├── RoutesWiringTest.php
    ├── Auth/ (Login, Logout, User, RateLimit, RegistrationDisabled, CsrfCookie, AuthAcceptance)
    ├── Statements/ (Upload*, StatementImport*, Retention, StatementUploadService)
    ├── Transactions/ (Index* Request + Endpoint)
    ├── Analytics/ (Dashboard* Request + Endpoint)
    ├── Categories/IndexCategoriesEndpointTest.php
    ├── Services/ (TransactionQueryService, AnalyticsService)
    └── Storage/ (StatementsDisk, StorageAcceptance)
```

> Path único: `tests/Fixtures/statements/...` via `base_path('tests/Fixtures/...')`. Não usar `tests/fixtures/` (minúsculo).

> **Concluído (§7.1):** estrutura real do repo documentada acima; fixtures centralizadas em `tests/Fixtures/statements` (README §3.4.1 / §3.5).

### 7.2 Fixtures reais anonimizadas

- [x] Obter 1 CSV Nubank real (dev) e anonimizar:
  - [x] Substituir nomes de pessoas / PIX keys / e-mails por placeholders.
  - [x] Manter formato de datas/valores/descrições comerciais genéricas.
  - [x] Remover CPF/CNPJ se aparecerem.
- [x] Commitar **apenas** fixtures anonimizadas (nunca extrato real).
- [x] Documentar no README da pasta fixtures a origem do formato (conta vs cartão).

> **Concluído (§7.2):** fixtures sintéticas no formato **conta** Nubank (não cartão); placeholders `[PESSOA*]`, `[PIX_KEY]`, `[EMPRESA]`; amostra estendida `sample_account_realistic_anon.csv`; política + checklist em `tests/Fixtures/statements/README.md`. Teste: `FixtureAnonymizationTest`.

### 7.3 Unit — `NubankCsvParserTest`

- [x] Parse fixture → N `ParsedTransaction` esperados (snapshot de count + 1ª/última linha).
- [x] Valor `"-1.234,56"` → amount `1234.56`, type `debit`.
- [x] Valor `"10,00"` → credit.
- [x] Data `"27/09/2026"` → `2026-09-27`.
- [x] BOM UTF-8 não quebra header.
- [x] Linha inválida incrementa `rowErrors` sem abortar.
- [x] Header ausente → exception.

> **Concluído (§7.3):** `tests/Unit/Parsers/NubankCsvParserTest.php` (contrato §7.3). Cobertura granular permanece em `NubankCsvParser{Format,Type,Implementation,Challenges}Test` (§3.4).

### 7.4 Unit — `OfxParserTest`

- [x] Parse fixture OFX → lista tipada.
- [x] `FITID` mapeado para `externalId`.
- [x] Arquivo malformed → `InvalidStatementException`.

> **Concluído (§7.4):** `tests/Unit/Parsers/OfxParserTest.php` — fixture tipada, `FITID`→`externalId`, malformed sem path absoluto. (5 PASS)

### 7.5 Unit — `Money` / `DateNormalizer`

- [x] Matriz de inputs pt-BR / OFX.
- [x] NBSP e `R$`.

> **Concluído (§7.5):** `MoneyTest` (DataProvider pt-BR + OFX, `R$`/`BRL`/NBSP) + `DateNormalizerTest` (pt-BR/OFX + NBSP).

### 7.6 Feature — Auth

- [x] Login sucesso + session.
- [x] Login falha.
- [x] Throttle login.
- [x] Logout.
- [x] `GET /api/user` 401 sem cookie.
- [x] `POST /api/register` 404.

> **Concluído (§7.6):** `LoginTest`, `LogoutTest`, `UserTest`, `RateLimitTest`, `RegistrationDisabledTest`, `AuthAcceptanceTest`.

### 7.7 Feature — Upload

- [x] Acting as admin + CSRF + attach fixture CSV → `201` + rows no DB.
- [x] Reupload mesmo arquivo → `rows_skipped` > 0, sem duplicar `unique_hash`.
- [x] Upload sem auth → `401`.
- [x] Upload `.exe` / mime inválido → `422`.
- [x] Arquivo OFX happy path.
- [x] Assert arquivo existe no disk `statements` (Storage::fake nos testes).

> **Concluído (§7.7):** `UploadStatementEndpointTest` (201 CSV/OFX, reupload HTTP, disk) + `UploadStatementValidationTest` (401/422 `.exe`) + `StatementUploadServiceTest` (dedupe).

### 7.8 Feature — Transactions / Analytics

- [x] Seed mínimo + filtros `from/to/type/q` alteram `meta.total`.
- [x] Dashboard cards coerentes com SQL manual (`SUM`).
- [x] Usuário não vê imports de outro user (criar 2º user só no teste — mesmo MVP single-admin, Policy deve aguentar).

> **Concluído (§7.8):** `TransactionsAnalyticsAcceptanceTest` + endpoints existentes (`IndexTransactions*`, `DashboardAnalytics*`, `StatementImportEndpointTest`).

### 7.9 Comandos de teste

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=NubankCsvParser
# ou: composer test:etapa-c
```

- [x] CI local mínimo: todos verdes antes de marcar DoD.

> **Concluído (§7.9):** Unit + Feature + filter `NubankCsvParser` verdes em sequência (`composer test:etapa-c`). Não rodar suites em paralelo na mesma DB MySQL (`RefreshDatabase` conflita).

### 7.10 Storage fake / DB

- [x] Feature tests usam `RefreshDatabase`.
- [x] `Storage::fake('statements')`.
- [x] Não depender de arquivos reais fora de `tests/Fixtures`.

> **Concluído (§7.10 / §7):** Feature DB/upload usam `RefreshDatabase` + `Storage::fake(StatementStorage::DISK)`; fixtures só em `tests/Fixtures/statements`. Contrato: `StorageFakeDbContractTest`.

---

## 8. Observabilidade, logs e segurança adicional

- [x] Logar upload: `user_id`, `import_id`, `format`, `rows_*`, `checksum` — **não** logar conteúdo de linhas.
- [x] Em `failed`: log `warning` com `error_message`.
- [x] Remover qualquer `dd()` / dump.
- [x] Validar que `APP_DEBUG=false` esconde trace na API.
- [x] Headers de segurança HostGator: adiar Etapa E; MVP local OK.
- [x] (Opcional) `Route::middleware('throttle:60,1')` group leitura.

> **Concluído (§8):** `StatementUploadService` → `statements.upload.completed` (info) / `statements.upload.failed` (warning); leituras agrupadas em `throttle:60,1`; sem `dd()`/`dump`. Testes: `StatementUploadLoggingTest`, `NoDebugDumpTest`. Headers HostGator → Etapa E.

---

## 9. Atualizações de documentação (obrigatório ao concluir)

- [x] Marcar checkboxes da **Etapa C** em `docs/MASTER_PLAN.md`.
- [x] Marcar checkboxes equivalentes em `docs/context.md` §4 Etapa C + critérios §3.1–3.2.
- [x] Resolver `[CONFIRMAR]` do endpoint → `POST /api/statements/upload`.
- [x] Registrar lib OFX escolhida em `docs/context.md` §2.2.
- [x] Documentar envs novos (`SESSION_SECURE_COOKIE` §1.2; `STATEMENT_RETENTION_DAYS=90` §2.4).
- [x] Anotar decisão Auth Opção A vs B (Sanctum).

> **Concluído (§9):** Etapa C marcada em `MASTER_PLAN` + `context.md` §4; critérios §3.1–3.2 (ingest) `[x]`; upload path canônico; OFX = `cihansenturk/ofxparser` ^1.0 em §2.2. Critérios de UI do dashboard (§3.3) permanecem para Etapa D.

---

## 10. Inventário de arquivos da Etapa C

- [x] `bootstrap/app.php` (api routes + JSON unauthenticated)
- [x] `routes/api.php`
- [x] `routes/web.php` (catch-all após API / group)
- [x] `app/Providers/AppServiceProvider.php` (RateLimiter + parser bindings)
- [x] `config/filesystems.php` (disk `statements`)
- [x] Controllers Auth + Statements + Transactions + Analytics + Categories
- [x] FormRequests listados em §6.1
- [x] Services: Upload, Query, Analytics, Retention
- [x] Parsers + Interface + Resolver
- [x] DTOs + Exceptions + Money + DateNormalizer
- [x] `app/Console/Commands/Statements/PurgeOldStatementFilesCommand.php`
- [x] (Opcional) migration `purged_at` em `statement_imports`
- [x] (Opcional) `StatementImportPolicy`
- [x] Fixtures anonimizadas + testes Unit/Feature
- [x] `composer.json` (dep OFX)
- [x] `.env.example` + `docs/context.md` atualizados

> **Concluído (§10):** inventário verificado no disco — Auth/Upload/Import/Transaction/Analytics/Category controllers; 4 FormRequests (§6.1); 4 Services + `AppliesTransactionFilters`; parsers/DTOs/Support/Exceptions; purge command + migration `purged_at`; Policy; fixtures Nubank/OFX + suite Unit/Feature; `cihansenturk/ofxparser` ^1.0; envs em `.env.example` / `context.md`.

---

## 11. Sequência de implementação (copy-paste mental)

1. [x] Rate limiters + disk `statements` + bootstrap api routes
2. [x] Auth controllers + LoginRequest + testes Auth
3. [x] Money + DateNormalizer + testes
4. [x] NubankCsvParser + fixtures + testes
5. [x] OfxParser + lib + testes
6. [x] StatementUploadService + upload endpoint + testes dedupe
7. [x] Transactions list + Categories
8. [x] Analytics dashboard
9. [x] Retention command
10. [x] Atualizar MASTER_PLAN / context (+ DoD formal → §12)

> **Concluído (§11):** sequência C.Auth → Storage/RateLimit → Parsers → Upload → APIs leitura → Retention → Docs executada. Matrix formal da Definition of Done fica na §12.

---

## 12. Definition of Done — Etapa C

- [x] Login/logout/session funcionam via JSON + cookie; registro público inexistente.
- [x] Rate limiting ativo em `login` e `statements/upload` (testes `429`).
- [x] `POST /api/statements/upload` autentica, valida MIME/tamanho, salva em storage privado.
- [x] CSV Nubank e OFX parseados via `StatementParserInterface` + DTOs.
- [x] Persistência atômica: import + transactions; reupload não duplica (`unique_hash` + skip).
- [x] Resposta de upload contém resumo (`rows_total/imported/skipped`).
- [x] `GET /api/transactions` filtra por período/tipo/categoria/q com paginação.
- [x] `GET /api/analytics/dashboard` retorna cards + series + by_category coerentes.
- [x] Arquivos não são servidos publicamente; comando de retenção existe (`--days`, `--dry-run`).
- [x] Suite de testes Unit (parsers/hasher/money) + Feature (auth/upload/list/analytics) verde.
- [x] `docs/MASTER_PLAN.md` e `docs/context.md` atualizados.
- [x] **Só então** iniciar Etapa D (Frontend React consumindo estes contratos).

> **Etapa C — DONE.** DoD validado (`composer test:etapa-c` verde: Unit + Feature + NubankCsvParser). Auth session JSON, upload atômico CSV/OFX, APIs de leitura, storage privado + retenção, docs sincronizados. Próximo: **Etapa D**.

---

## Ordem interna sugerida (Etapa C)

> **C.Auth + RateLimit** → **C.Storage** → **C.Parsers (CSV → OFX)** → **C.UploadService + POST** → **C.APIs leitura** → **C.Retention** → **C.Docs/DoD**
