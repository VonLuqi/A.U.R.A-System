# Aura — Contexto do Projeto

> **Fonte da verdade** para decisões de produto, arquitetura e implementação.
> Qualquer agente de IA ou desenvolvedor deve consultar este arquivo antes de propor ou alterar código.
> Atualize este documento quando escopo, stack ou restrições mudarem.
>
> **Status MVP (2026-09-29):** Etapas **A–E concluídas** (MVP HostGator live em `https://aura.vonluqi.com`).  
> Planos: `docs/PLAN_ETAPA_C.md` · `docs/PLAN_ETAPA_D.md` · `docs/PLAN_ETAPA_E.md` · Roadmap: `docs/MASTER_PLAN.md` · Deploy: `docs/DEPLOY_HOSTGATOR.md` · Setup: `README.md`.

---

## 1. Visão do Projeto

### Identidade


| Item                | Valor                                                                                    |
| ------------------- | ---------------------------------------------------------------------------------------- |
| Nome comercial      | **Aura**                                                                                 |
| Sigla               | **A.U.R.A.**                                                                             |
| Expansão            | **A**ssistente **U**nificado de **R**ecursos e **A**nálises                              |
| Posicionamento      | Foco em **inteligência** e **automação** (tecnologia preditiva + centralização de dados) |
| Tagline             | *Aura: Inteligência invisível, controle absoluto.*                                       |
| Domínio de produção | `aura.vonluqi.com`                                                                       |
| UI / Design tokens  | Ver `docs/DESIGN-SYSTEM.MD`                                                              |


**Por que o nome funciona**

- **Assistente** — destaca o papel de automação inteligente (ex.: categorização de compras; ML como evolução do produto).
- **Unificado** — remete à centralização dos extratos (início: Nubank) em uma única fonte de verdade.
- **Recursos e Análises** — cobre tanto o dinheiro/movimentos quanto o dashboard analítico.



### Propósito

Plataforma web de **controle financeiro pessoal** orientada a inteligência e automação, de uso **exclusivo e individual** (single-user). O administrador importa extratos, deixa o sistema organizar e analisar os dados, e acompanha a saúde financeira com o mínimo de atrito operacional.

### Objetivos

- Centralizar extratos bancários (início: **Nubank**, formatos CSV/OFX) em um banco estruturado (**Unificado**).
- Automatizar o máximo possível do pós-importação (categorização e análises; regras no curto prazo, ML no médio prazo — **Assistente**).
- Eliminar planilhas manuais como fonte primária de verdade.
- Oferecer visualização clara de entradas, saídas e tendências com filtros dinâmicos (**Análises**).
- Manter superfície de ataque mínima: **apenas o administrador** autenticado acessa o sistema.



### Voice & UI copy (marca)

- Na tela de login, header e Figma: marca **Aura** em hierarquia hero; tagline minimalista abaixo ou como apoio:
  - **Aura: Inteligência invisível, controle absoluto.**
- Evitar jargão técnico na UI (“ML”, “pipeline”); preferir linguagem de assistência e clareza.
- Tom: preciso, calmo, premium — inteligência que trabalha em segundo plano.



### Fora de escopo (MVP)

- Multi-usuário / multi-tenant
- Open Banking / APIs bancárias em tempo real
- App mobile nativo
- Contas a pagar/receber com recorrência automática (pode entrar em fases posteriores)
- Integrações com cartões além do fluxo de upload de extrato
- Motor de Machine Learning completo em produção (visão de produto; no MVP: importação + dashboard; categorização automática evolui após o core)



### Domínio e branding (resumo)


| Item                        | Valor                                                              |
| --------------------------- | ------------------------------------------------------------------ |
| Domínio de produção         | `vonluqi.com`                                                      |
| URL do app Aura (HostGator) | `https://aura.vonluqi.com`                                         |
| Nome do produto / marca     | **Aura** (A.U.R.A.)                                                |
| Tagline                     | Inteligência invisível, controle absoluto.                         |
| UI / Design tokens          | Ver `docs/DESIGN-SYSTEM.MD` (dark theme, Poppins, brand `#DCCFFF`) |


---



## 2. Arquitetura e Restrições



### 2.1 Ambiente de produção


| Item              | Detalhe                                                                                            |
| ----------------- | -------------------------------------------------------------------------------------------------- |
| Hospedagem        | **HostGator** (infraestrutura tradicional / cPanel)                                                |
| Conta cPanel      | `luca9682` · home `/home4/luca9682`                                                                |
| Domínio principal | `vonluqi.com` → Document Root fixo `/public_html` (não editável)                                   |
| App Aura          | Subdomínio `aura.vonluqi.com` → Document Root `/home4/luca9682/aura/public`                        |
| Código Laravel    | `/home4/luca9682/aura` (pai de `public/` — `.env`, `app/`, `vendor/`, `storage/` fora do web root) |
| SSL               | Obrigatório (Let's Encrypt / certificado do painel)                                                |
| Deploy            | GitHub Actions → FTP (`aura/`) + SSH (extract `vendor` / migrate); ver `docs/DEPLOY_HOSTGATOR.md`  |
| Cron              | Disponível via cPanel (jobs de limpeza, se necessário)                                             |


**Implicações:**

- Preferir stack **PHP + MySQL**, nativa e estável em shared/cPanel HostGator.
- Evitar dependência de workers Node persistentes, Redis obrigatório ou containers (não disponíveis no plano tradicional típico).
- Uploads de extrato devem respeitar limites de `upload_max_filesize` / `post_max_size` do PHP.
- Sessões e arquivos sensíveis **fora** do document root: só `aura/public` é servido; resto em `aura/`.
- Princípio: **nunca** apontar o document root para a raiz do Laravel.



### 2.2 Stack do MVP (confirmada)

> Stack fechada nas Etapas A–D. Alterações exigem registro aqui + `docs/MASTER_PLAN.md` + `README.md`.


| Camada             | Tecnologia                                                                     | Notas                                                       |
| ------------------ | ------------------------------------------------------------------------------ | ----------------------------------------------------------- |
| Linguagem backend  | **PHP 8.2+**                                                                   | Compatível com HostGator                                    |
| Framework backend  | **Laravel 11**                                                                 | Auth, validação, filas sync, Eloquent, storage              |
| Banco de dados     | **MySQL 8 / MariaDB**                                                          | Padrão cPanel                                               |
| Autenticação       | Session cookie (Laravel Auth, **sem Sanctum/Breeze**)                          | Single-admin; API sob `/api` com middleware `web` (Opção A) |
| Frontend           | **React 19 + Vite 6**                                                          | SPA Blade `#app`; API JSON; tokens do Design System         |
| Roteamento client  | **react-router-dom 7**                                                         | `/login`, `/dashboard`, `/upload`; catch-all Laravel        |
| Estilo             | **CSS variables (`tokens.css`) + Tailwind 3**                                  | Bridge em `tailwind.config.js`; dark only `#151716`       |
| HTTP client        | **Axios** (`withCredentials` + CSRF)                                           | Sem Sanctum; `resources/js/api/*`                           |
| Gráficos / Upload / Toasts / Ícones | **recharts** · **react-dropzone** · **sonner** · **lucide-react** | Deps MVP Etapa D; sem React Query / MUI / Chart.js |
| Testes front (smoke) | **Vitest** (`npm test`)                                                      | `formatMoney` / `isAllowedStatementFile`                    |
| Parse de extratos  | Parser próprio (CSV Nubank) + **`cihansenturk/ofxparser` ^1.0** (fork mantido PHP 8.1+ de `asgrim/ofxparser`; escolhido em 2026-09-28 — `asgrim/ofxparser` abandoned / incompatível PHP 8.2) | Endpoint dedicado e autenticado |
| Storage de uploads | Disco **`statements`** → `storage/app/private/statements` (privado) | Nunca `public` disk / nunca symlink de `private` |
| Limite upload MVP | **10 MB** (`max:10240` KB) via `UploadStatementRequest` | Conservador vs PHP ini local 20M / HostGator |
| Extensões aceitas | `csv`, `ofx`, **`qfx`** (QFX = OFX-like → parser OFX) | MIME: `csv,txt,ofx,xml` + check de extensão |
| Servidor web       | Apache (HostGator) + `public/` como document root                              | `.htaccess` do Laravel                                      |




### 2.3 Princípios arquiteturais

1. **Single-admin:** não há cadastro aberto; credenciais apenas do administrador.
2. **API-first interno:** backend expõe rotas JSON autenticadas; frontend é cliente.
3. **Parse no servidor:** o browser só envia o arquivo; normalização e persistência são responsabilidade do backend.
4. **Idempotência na importação:** evitar duplicar transações do mesmo extrato (hash / chave composta).
5. **Segurança por padrão:** CSRF (se session), rate limit no login e no upload, validação de MIME/extensão, sanitização.
6. **Design System como contrato visual:** componentes alinhados a `docs/DESIGN-SYSTEM.MD`.



### 2.4 Estrutura de pastas (alto nível)

```text
/
├── README.md                  ← setup local + visão do repositório
├── docs/
│   ├── context.md             ← este arquivo (fonte da verdade)
│   ├── MASTER_PLAN.md
│   ├── DESIGN-SYSTEM.MD
│   ├── PLAN_ETAPA_*.md
│   └── DEPLOY_HOSTGATOR.md
├── app/                       ← Laravel (Models, Http, Services, Parsers)
├── database/migrations|seeders|factories/
├── routes/web.php | api.php
├── resources/
│   ├── css/                   ← tokens.css, fonts.css, app.css
│   ├── js/                    ← SPA React (api/, components/, pages/, hooks/)
│   └── views/app.blade.php    ← shell #app
├── storage/app/private/statements/
├── tests/                     ← PHPUnit + Fixtures
└── public/                    ← document root no HostGator (+ build Vite)
```



### 2.5 Variáveis de ambiente

> Template versionado: `.env.example`. Valores reais ficam só em `.env` / `.env.production` (gitignored).
> Qualquer mudança de env atualiza **ambos**: `.env.example` e esta seção.


| Variável           | Obrigatória | Exemplo local           | Exemplo produção           | Descrição                                             |
| ------------------ | ----------- | ----------------------- | -------------------------- | ----------------------------------------------------- |
| `APP_NAME`         | sim         | `Aura`                  | igual                      | Nome da app                                           |
| `APP_ENV`          | sim         | `local`                 | `production`               | Ambiente                                              |
| `APP_KEY`          | sim         | `(gerada)`              | `(gerada única)`           | Chave de criptografia (`php artisan key:generate`)    |
| `APP_DEBUG`        | sim         | `true`                  | `false`                    | Nunca `true` em prod                                  |
| `APP_URL`          | sim         | `http://localhost:8000` | `https://aura.vonluqi.com` | URL canônica                                          |
| `APP_TIMEZONE`     | sim         | `America/Sao_Paulo`     | igual                      | Fuso                                                  |
| `DB_CONNECTION`    | sim         | `mysql`                 | `mysql`                    | Driver                                                |
| `DB_HOST`          | sim         | `127.0.0.1`             | `localhost` (cPanel)       | Host DB                                               |
| `DB_PORT`          | sim         | `3306`                  | `3306`                     | Porta                                                 |
| `DB_DATABASE`      | sim         | `aura`                  | `(nome cPanel)`            | Database                                              |
| `DB_USERNAME`      | sim         | `aura_dev`              | `(user cPanel)`            | Usuário                                               |
| `DB_PASSWORD`      | sim         | `(local)`               | `(prod)`                   | Senha — **nunca** no Git nem neste doc                |
| `SESSION_DRIVER`   | sim         | `database`              | `database`                 | Sessões                                               |
| `SESSION_LIFETIME` | sim         | `120`                   | `120`                      | Minutos de idle até expirar a sessão                  |
| `SESSION_SECURE_COOKIE` | sim    | `false`                 | `true`                     | Cookie só via HTTPS (`true` em produção)              |
| `SESSION_HTTP_ONLY` | não        | `true`                  | `true`                     | Bloqueia acesso JS ao cookie de sessão                |
| `SESSION_SAME_SITE` | não        | `lax`                   | `lax`                      | SameSite; adequado a SPA same-origin / Vite proxy     |
| `CACHE_STORE`      | sim         | `database`              | `database`                 | Cache                                                 |
| `FILESYSTEM_DISK`  | sim         | `local`                 | `local`                    | Disco default                                         |
| `QUEUE_CONNECTION` | sim         | `sync`                  | `sync`                     | Filas (MVP sync)                                      |
| `LOG_LEVEL`        | sim         | `debug`                 | `error`                    | Verbosity                                             |
| `VITE_APP_NAME`    | não         | `${APP_NAME}`           | igual                      | Exposto ao front                                      |
| `ADMIN_EMAIL`      | sim         | `admin@aura.local`      | `(email real do admin)`    | Email do único usuário admin (`AdminUserSeeder`)      |
| `ADMIN_PASSWORD`   | sim         | `ChangeMeNow!123`       | `(senha forte)`            | Senha do admin — **nunca** no Git; trocar em produção |
| `RATE_LIMIT_LOGIN_PER_EMAIL` | não | `5`                   | `5`                        | Tentativas/min por email+IP (`throttle:login`)        |
| `RATE_LIMIT_LOGIN_PER_IP` | não    | `20`                    | `20`                       | Tentativas/min por IP (`throttle:login`)              |
| `RATE_LIMIT_UPLOAD_PER_USER` | não | `10`                   | `10`                       | Uploads/min por user (`throttle:statements-upload`)   |
| `STATEMENT_RETENTION_DAYS` | não | `90`                    | `90`                       | Dias até purge de arquivos de extrato (`statements:purge-files`) |


**Decisões Etapa A**


| Decisão              | Valor                         | Motivo                                                                                            |
| -------------------- | ----------------------------- | ------------------------------------------------------------------------------------------------- |
| `SESSION_DRIVER`     | `database`                    | Persistência de sessão via MySQL (tabelas Laravel); adequado a HostGator sem Redis                |
| `CACHE_STORE`        | `database`                    | Cache via MySQL; evita dependência de Redis/Memcached no shared hosting                           |
| Document root (prod) | `/home4/luca9682/aura/public` | Cenário A: subdomínio `aura.vonluqi.com` → só `public/`; código Laravel em `/home4/luca9682/aura` |
| `APP_URL` (prod)     | `https://aura.vonluqi.com`    | URL canônica do app (domínio principal `vonluqi.com` permanece em `/public_html`)                 |


**Decisões Etapa C**


| Decisão | Valor | Motivo |
| --- | --- | --- |
| API session (Opção A) | `routes/api.php` carregado em `routes/web.php` com `prefix('api')` **antes** do catch-all SPA; **sem** `api:` em `bootstrap/app.php` | Stack `web` (session + CSRF) sem Sanctum; o grupo `api` default do Laravel é stateless e não serve SPA cookie-auth |
| Sanctum | Não usado no MVP | Evita dependência extra; same-origin / Vite proxy basta |
| Session cookies | `lifetime=120`, `http_only=true`, `same_site=lax`, `secure` via `SESSION_SECURE_COOKIE` | Cookie HttpOnly; Secure só em HTTPS prod; Lax suficiente para SPA same-origin |
| CSRF SPA | `GET /api/csrf-cookie` (204) + cookie `XSRF-TOKEN` / header `X-XSRF-TOKEN`; Axios `withCredentials` + `Accept: application/json` | Sem Sanctum; Vite proxy `/api` → Laravel em dev se origem ≠ Artisan |
| Registro público | **Omitido** — sem rotas/controllers de register; sem Breeze/Fortify | Single-admin; usuários só via `AdminUserSeeder` |
| Rate limiters nomeados | `login` (5/email+IP, 20/IP) + `statements-upload` (10/user) em `AppServiceProvider` | Middleware de rota na §1.7.2; envs `RATE_LIMIT_*` |
| Login idempotente (`guest`) | Já autenticado em `POST /api/login` → `200` + user atual (não `409`/redirect) | SPA: re-login seguro sem erro; middleware `App\Http\Middleware\RedirectIfAuthenticated` |
| Disk `statements` | `config/filesystems.php` → `storage/app/private/statements`, `visibility=private`, `throw=true`, `serve=false` | Extratos fora do web root; `local.serve=false` para não expor via `/storage/{path}` assinado |
| Upload validation | max 10 MB; extensões `csv\|ofx\|qfx`; `.qfx` → format `ofx` | `UploadStatementRequest`; HostGator/PHP ini tipicamente ≥ 20M |
| Retenção de extratos | **90 dias**; coluna `purged_at`; command `statements:purge-files` diário 03:15 | Opção B (auditoria); cron HostGator: `* * * * * php .../artisan schedule:run` |


**Decisões Etapa D**


| Decisão | Valor | Motivo |
| --- | --- | --- |
| Tipo de app | SPA React em Blade (`resources/views/app.blade.php` + `#app`) via Vite | Same-origin com Laravel; HostGator serve só `public/` |
| Entry | `resources/js/app.jsx` → `createRoot` | Padrão Laravel + React já na Etapa A |
| Roteamento | `react-router-dom` (BrowserRouter) | Rotas client `/login`, `/dashboard`, `/upload`; API fica em `/api/*` |
| Estilo | CSS variables (`tokens.css`) + Tailwind 3 + CSS Modules opcional | Espelha `docs/DESIGN-SYSTEM.MD`; dark only `#151716` |
| Fonte | Poppins (`resources/css/fonts.css`) | Design System §2 |
| Estado servidor | Context + hooks (React Query fora do MVP default) | Superfície pequena; evita deps extras |
| Estado UI | `useState` + AuthContext + filtros (Context ou URL) | Simples e alinhado ao single-admin |
| HTTP | Axios (`resources/js/api/client.js` + `bootstrap.js`) — **sem** Sanctum | Session cookie + CSRF já definidos na Etapa C |
| Toasts | `sonner` | Leve; themável com tokens Aura |
| Upload DnD | `react-dropzone` | Validação de extensão/tamanho no client; parse só no server |
| Gráficos | `recharts` | SVG themável; preferido a Chart.js no MVP |
| Ícones | `lucide-react` (outline) | Alinhado a `DESIGN-SYSTEM.MD` §4.5 |
| Datas/moeda | `resources/js/lib/format.js` (`Intl` pt-BR) | API envia money como string; TZ conceitual `America/Sao_Paulo` |
| Auth mecanismo | Session cookie Laravel (`web` guard) | Espelho Etapa C Opção A; same-origin / Vite proxy |
| CSRF SPA | `GET /api/csrf-cookie` → `XSRF-TOKEN` / `X-XSRF-TOKEN` via Axios | Sem Sanctum; `api/auth.js` → `ensureCsrf()` |
| Credentials | `withCredentials: true` (`api/client.js`) | Envia cookie de sessão nas chamadas `/api/*` |
| Sanctum | **Não usado** | Decisão Etapa C mantida |
| Registro UI | **Proibido** — zero tela/rota de register | Single-admin; só `AdminUserSeeder` |
| 401 no client | Interceptor Axios → `setUnauthorizedHandler` → limpar user + `/login` | Exceto `POST /api/login` e `GET /api/user` (guest bootstrap) |
| Guest em `/login` | Se sessão válida (`GET /api/user` 200) → redirect `/dashboard` | Implementação UI: `GuestRoute` (§1.2.3) |
| Rotas SPA (client) | `/login` (guest), `/` → redirect, `/dashboard` + `/upload` (auth), `*` → NotFound | `react-router-dom`; Laravel catch-all serve Blade; **sem** rotas `/api` no React |
| Deps frontend MVP | `react-router-dom`, `recharts`, `react-dropzone`, `lucide-react`, `sonner` (+ `axios`/`react`/`tailwind` Etapa A) | Instaladas na Etapa D §0.5; **sem** React Query, Redux, Zustand, MUI, Chakra, Ant, Chart.js |
| Tokens UI | Só `DESIGN-SYSTEM.MD` / `tokens.css` via Tailwind theme bridge | Sem hex solto nos componentes (exceto mapeamento recharts → vars) |
| Feedback danger | `#F5A9A9` → `--color-feedback-danger` | Adição Aura §0.6 (moodboard não tinha danger); saídas/erros |
| Copy UI | Tom preciso, calmo, premium; wordmark Aura hero + tagline de apoio | Sem jargão técnico (“pipeline”, “ML”, “parser”) |
| Upload UX (objetivo) | Admin envia CSV/OFX/QFX (Nubank) → resumo (importadas/puladas/erros) ou erro claro | Sem jargão; parse só no server; DnD + feedback na §3 |
| Dashboard UX | Cards + filtros pills + gráficos + tabela paginada, **mesmos filtros** (URL sync) | Coerência cards↔charts↔tabela; `useDashboardFilters` + APIs analytics/transactions |
| Filtros URL | `from`, `to`, `type`, `category_id`, `q`, `page`, `sort`, `direction` em searchParams | Shareable / refresh-safe |
| Dev same-origin | `php artisan serve` + `npm run dev` (Vite HMR); proxy `/api` se origem `:5173` | Cookies/CSRF estáveis |
| Smoke front | Vitest — `resources/js/lib/format.test.js` + `validators.test.js` | Opcional DoD; `npm test` |
| DoD Etapa D | Concluído (`PLAN_ETAPA_D.md` §9) | — |
| DoD Etapa E | Concluído (`PLAN_ETAPA_E.md` §9) · runbook `DEPLOY_HOSTGATOR.md` | Residual: cookie `Secure` + registro backup §8.1 |


Variáveis adicionais presentes em `.env.example` (locale, mail log, Redis opcional, AWS placeholders) seguem defaults Laravel; não são críticas ao MVP HostGator e podem permanecer como no template.

---



## 3. Detalhamento do MVP



### 3.1 Autenticação — acesso único do Administrador

**Objetivo:** garantir que somente o administrador autentique e use a plataforma.

**Requisitos funcionais**

- Tela de login (e-mail/usuário + senha).
- Sessão persistente segura (cookie HttpOnly, Secure, SameSite).
- Logout explícito.
- **Sem** rota de registro público; usuário admin criado via seeder/artisan ou instalação inicial.
- **Registro público omitido na Etapa C:** não há `RegisteredUserController`, Breeze/Fortify nem `POST /api/register` / `POST /register`. Único caminho de criação de usuário no MVP: `AdminUserSeeder` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`).
- Rotas protegidas: upload, listagem, dashboard e APIs de filtro exigem autenticação.
- Após N falhas de login, aplicar throttle (ex.: rate limit Laravel).

**Critérios de aceite**

- [x] Usuário não autenticado recebe `401` JSON nas rotas `/api/*` protegidas (SPA redireciona no client — Etapa D).
- [x] Admin autentica com `ADMIN_EMAIL` / `ADMIN_PASSWORD` (`AdminUserSeeder`) → `200` + sessão.
- [x] Sessão expira conforme `SESSION_LIFETIME`; logout invalida o acesso (`GET /api/user` → `401`).
- [x] Não existe endpoint/rota pública de registro (`POST /api/register` → 404).
- [x] Rate limit ativo no login (6ª falha → `429`).
- [x] Login inválido não revela se o email existe (mensagem única).

**Contrato CSRF / headers (Etapa C §5.8 → Etapa D)**

Sem Sanctum. A stack `web` em `/api/*` já emite o cookie `XSRF-TOKEN` (não HttpOnly) junto com o cookie de sessão.

1. **Bootstrap:** `GET /api/csrf-cookie` (204) **ou** `GET /` (shell SPA) para setar `XSRF-TOKEN` + sessão.
2. **Axios:** `withCredentials: true` (enviar cookies).
3. **Header:** `X-XSRF-TOKEN` = valor do cookie `XSRF-TOKEN` (Axios faz isso por default com `xsrfCookieName` / `xsrfHeaderName`).
4. **Accept:** `application/json` em todas as chamadas `/api/*`.

Dev: preferir same-origin (`php artisan serve` + Vite HMR). Se o browser abrir o Vite em `:5173`, o proxy em `vite.config.js` encaminha `/api` → Laravel (`VITE_DEV_PROXY_TARGET`, default `http://127.0.0.1:8000`) para cookies no mesmo host efetivo. Defaults em `resources/js/bootstrap.js`.

**Erros HTTP padronizados (Etapa C §5.9 → Etapa D)**

Handlers em `bootstrap/app.php` (`shouldRenderJsonWhen` para `/api/*`). Com `APP_DEBUG=false`, nunca vazar SQLSTATE, paths absolutos nem stack trace.

| Situação | Status | Payload (resumo) |
| --- | --- | --- |
| Não autenticado | `401` | `{ "message": "Unauthenticated." }` |
| Validação FormRequest | `422` | `{ "message": "...", "errors": { "campo": ["..."] } }` |
| Parse inválido / formato | `422` | `{ "message": "...", "error_code": "invalid_statement"\|"unsupported_format", "import_id"? }` |
| Rate limit | `429` | `{ "message": "..." }` + header `Retry-After` |
| Não encontrado (import) | `404` | JSON padrão Laravel (`message`) — id inexistente **ou** de outro user |
| Erro inesperado | `500` | `{ "message": "Não foi possível processar a solicitação." }` |

Teste de contrato: `tests/Feature/HttpErrorContractTest.php`.

---



### 3.2 Ingestão de Dados (CSV/OFX) — foco Nubank

**Objetivo:** endpoint seguro de upload que faz parse do extrato, extrai transações e persiste no banco.

**Requisitos funcionais**

- Endpoint autenticado: `POST /api/statements/upload`.
- Inventário API canônico (Etapa C §5.1 / Etapa D):
  - `GET /api/csrf-cookie`, `POST /api/login`, `POST /api/logout`, `GET /api/user`
  - `POST /api/statements/upload`, `GET /api/statements`, `GET /api/statements/{id}`
  - `GET /api/transactions`, `GET /api/analytics/dashboard`, `GET /api/categories`
- Aceitar, no mínimo:
  - **CSV** no padrão exportado pelo app/site Nubank.
  - **OFX** quando disponível.
- Fluxo:
  1. Validar autenticação e arquivo (tipo, tamanho, extensão).
  2. Armazenar o arquivo original em storage privado.
  3. Detectar formato e selecionar parser (`NubankCsvParser` / `OfxParser`).
  4. Normalizar campos: data, descrição, valor, tipo (crédito/débito), identificador externo se houver.
  5. Persistir `StatementImport` (metadados da importação) + `Transaction` (linhas).
  6. Retornar resumo: total importado, ignorados/duplicados, erros de linha.
- Deduplicação: `unique_hash` via `App\Support\TransactionHasher` (SHA-256 da string canônica `occurred_on|amount|type|description_normalized|external_id|source`). Cross-import (não inclui `statement_import_id`).
- **Política de duplicata (MVP):** em conflito de `unique_hash`, **skip** (não atualizar a linha existente). Incrementar `rows_skipped`. Preferir `insertOrIgnore` / `firstOrCreate` / captura de `QueryException` — **nunca upsert silencioso**.
- Categoria: campo nullable no MVP; regras de categorização automática podem ser fase 2.

**Modelo de dados mínimo (conceitual)**


| Entidade            | Campos principais                                                                                                                                                                                    |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `users`             | id, name, email, password (apenas admin)                                                                                                                                                             |
| `statement_imports` | id, user_id, filename, format (`csv`|`ofx`), source (`nubank`), status, rows_total, rows_imported, rows_skipped, checksum, created_at                                                                |
| `transactions`      | id, statement_import_id, external_id?, occurred_on, description, amount (`DECIMAL(14,2)` **absoluto ≥ 0**), type (`credit`|`debit`), category_id?, raw_payload (json), unique_hash (SHA-256, UNIQUE) |
| `categories`        | id, name, slug, type (opcional no MVP — seed básico)                                                                                                                                                 |


> **Decisão de amount:** valor sempre positivo; `type=credit` = entrada, `type=debit` = saída. Saldo do período = `SUM(credits) − SUM(debits)`.

**Critérios de aceite**

- [x] Upload autenticado de CSV Nubank popula `transactions` corretamente.
- [x] Reimportar o mesmo arquivo não duplica linhas (ou reporta skips).
- [x] Arquivo original não é acessível via URL pública.
- [x] Erros de parse retornam mensagem clara sem vazar paths do servidor.

> Backend Etapa C: `POST /api/statements/upload`, parsers CSV/OFX (`cihansenturk/ofxparser`), disk `statements` privado, dedupe por `unique_hash`.

---



### 3.3 Dashboard Analítico

**Objetivo:** visualizar dados processados com filtros inteligentes e dinâmicos.

**Requisitos funcionais**

- Visão geral (cards): saldo do período, total de entradas, total de saídas, quantidade de transações.
- Lista/tabela de transações paginada.
- Filtros dinâmicos (combináveis):
  - **Período** (de/até, atalhos: mês atual, últimos 30/90 dias).
  - **Tipo** (crédito, débito, todos).
  - **Categoria** (quando existir).
  - **Busca textual** na descrição.
  - **Importação de origem** (opcional).
- Gráficos alinhados ao Design System:
  - Barras/série por período (evolução).
  - Distribuição por categoria ou tipo (quando houver dados).
- Estados vazios: orientar o usuário a fazer o primeiro upload.
- UI dark, Poppins, tokens de `docs/DESIGN-SYSTEM.MD` (cards `radius.xl`, pills de filtro, progress/charts).

**Critérios de aceite**

- [x] APIs de leitura coerentes: `GET /api/transactions` (filtros + paginação) e `GET /api/analytics/dashboard` (cards/series/by_category) — Etapa C.
- [x] Filtros atualizam cards, lista e gráficos na UI de forma coerente (Etapa D).
- [x] Performance aceitável no backend com volume típico (agregações SQL + índices Etapa B).
- [x] Layout responsivo (desktop prioritário; mobile utilizável) — Etapa D.

---



## 4. Roadmap de Implementação

Checklist técnico do MVP. Marque itens conforme forem concluídos.

### Etapa A — Setup do projeto

- [x] Inicializar repositório Git e `.gitignore` (`.env`, `vendor`, `node_modules`, uploads).
- [x] Criar aplicação Laravel 11 + configurar `.env.example`.
- [x] Configurar Vite + React no frontend (`resources/js`).
- [x] Definir document root `public/` e regras Apache/`.htaccess`.
- [x] Instalar fonte Poppins e espelhar tokens CSS a partir de `docs/DESIGN-SYSTEM.MD`.
- [x] Configurar ambiente local (PHP, Composer, Node, MySQL).
- [x] Documentar variáveis de ambiente necessárias neste `context.md` se mudarem.



### Etapa B — Banco de Dados

- [x] Migration `users` (+ seeder do admin único).
- [x] Migration `categories` (seed opcional mínimo).
- [x] Migration `statement_imports`.
- [x] Migration `transactions` com índices (`occurred_on`, `type`, `unique_hash`, `category_id`).
- [x] Constraints de unicidade para deduplicação.
- [x] Factories/seeders de desenvolvimento (transações fake para UI).



### Etapa C — Backend / Auth / Parse

- [x] Autenticação session (login, logout, middleware `auth`).
- [x] Desabilitar/omitir registro público.
- [x] Rate limiting em `login` e `statements/upload`.
- [x] Service `StatementUploadService` (orquestração).
- [x] `NubankCsvParser` com testes unitários (fixtures reais anonimizadas).
- [x] `OfxParser` (ou adapter de lib) com testes.
- [x] Persistência atômica (import + transactions em transação DB).
- [x] Endpoint `POST` upload + resposta de resumo.
- [x] Endpoints de leitura: listagem filtrada, agregados do dashboard.
- [x] Storage privado e política de retenção simples dos arquivos.



### Etapa D — Frontend

> Concluída — detalhes e DoD em `docs/PLAN_ETAPA_D.md`.

- [x] Tela de Login (brand **Aura** + tagline + Design System).
- [x] Layout autenticado (nav, shell dark).
- [x] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [x] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [x] Estados: loading, empty, error.
- [x] Integração com API autenticada (cookies/CSRF conforme stack).
- [x] Responsividade básica.



### Etapa E — Deploy (HostGator / aura.vonluqi.com)

> **Concluída** (`PLAN_ETAPA_E.md` §9). Guia: `docs/DEPLOY_HOSTGATOR.md` · template: `.env.production.example`.

- [x] Criar banco MySQL no cPanel e usuário com permissões mínimas.
- [x] Configurar `.env` de produção (`APP_URL=https://aura.vonluqi.com`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`).
- [x] Apontar subdomínio `aura.vonluqi.com` para `…/aura/public` (nunca a raiz do Laravel).
- [x] Instalar dependências (`composer install --no-dev`, `npm ci && npm run build`).
- [x] Rodar migrations + seeder do admin (`ADMIN_EMAIL` / `ADMIN_PASSWORD` fortes).
- [x] Garantir permissões em `storage/` e `bootstrap/cache/`.
- [x] Configurar cron cPanel: `* * * * * php …/artisan schedule:run`.
- [x] Validar HTTPS, login, upload Nubank e dashboard em produção.
- [x] Backup inicial do banco e checklist de rollback.

### Ordem sugerida de entrega

```text
A (Setup) → B (DB) → C.Auth → C.Parse/Upload → C.APIs Dashboard → D (UI) ✅ → E (Deploy) ✅
```

---



## 5. Convenções para a IA (obrigatório)

1. Ler este `context.md`, `docs/DESIGN-SYSTEM.MD` e `README.md` antes de implementar UI ou novas features.
2. Não introduzir multi-usuário, OAuth social, Sanctum ou Open Banking no MVP sem atualizar este documento.
3. Não expor uploads ou `.env` publicamente.
4. Preferir mudanças pequenas e testáveis; parsers devem ter fixtures e testes; smoke front via Vitest quando fizer sentido.
5. Ao concluir uma etapa do roadmap, atualizar os checkboxes **deste arquivo** e de `docs/MASTER_PLAN.md`.
6. Stack default = **Laravel 11 + MySQL + React 19 / Vite 6** em HostGator; qualquer troca deve ser registrada na seção 2.2 com data/motivo.
7. Etapas D e E estão **fechadas**; mudanças de UI/API devem preservar contratos da Etapa C (`PLAN_ETAPA_C` / §3), o Design System e o runbook HostGator.

---



## 6. Glossário rápido


| Termo               | Significado                                                                  |
| ------------------- | ---------------------------------------------------------------------------- |
| Aura                | Nome comercial do produto                                                    |
| A.U.R.A.            | Assistente Unificado de Recursos e Análises                                  |
| Tagline             | “Aura: Inteligência invisível, controle absoluto.”                           |
| Statement / Extrato | Arquivo CSV ou OFX exportado do banco                                        |
| Import              | Registro de uma operação de upload/parse                                     |
| Transaction         | Linha normalizada de movimento financeiro                                    |
| Admin               | Único usuário autorizado do sistema                                          |
| Nubank CSV          | Formato de exportação do Nubank (primeiro parser prioritário)                |
| Assistente          | Pilar de automação/inteligência (categorização e análises; ML como evolução) |
| Unificado           | Centralização dos dados bancários em uma única plataforma                    |


