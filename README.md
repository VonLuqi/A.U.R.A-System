# Aura

**A.U.R.A.** — Assistente Unificado de Recursos e Análises  
*Inteligência invisível, controle absoluto.*

<p align="left">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+" />
  <img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black" alt="React 19" />
  <img src="https://img.shields.io/badge/Vite-6-646CFF?logo=vite&logoColor=white" alt="Vite 6" />
  <img src="https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License" />
  <img src="https://img.shields.io/badge/status-MVP-blue" alt="MVP" />
</p>

SPA de controle financeiro pessoal com **multi-usuário RBAC**, importação de extratos Nubank, cartões/cobranças (feature flags) e dashboard analítico. Stack: **Laravel 11 + React 19 + Vite 6**, pronta para HostGator (`aura.vonluqi.com`).

---

## Sobre o Projeto

**Aura** centraliza extratos bancários (início: **Nubank**, CSV/OFX/QFX) em uma única fonte de verdade, elimina planilhas manuais e oferece visão clara de entradas, saídas, metas, cartões e cobranças — com isolamento por usuário e cotas por papel (Admin, Subadmin, Visitante, Teste). Sem registro público, Sanctum ou OAuth: usuários nascem via Admin ou seeders.

O produto cobre autenticação por sessão, upload com parse e deduplicação, CRUD/metas/aliases, pilares Etapa H sob feature flags e UI dark alinhada ao Design System (`docs/DESIGN-SYSTEM.MD`).

Documentação canônica:

| Documento | Uso |
| --- | --- |
| [`docs/context.md`](docs/context.md) | Fonte da verdade (produto, arquitetura, decisões) |
| [`docs/MASTER_PLAN.md`](docs/MASTER_PLAN.md) | Roadmap por etapas (A–H) |
| [`docs/PLAN_CARTOES_EMPRESTIMOS.md`](docs/PLAN_CARTOES_EMPRESTIMOS.md) | Etapa H — cartões, empréstimos, notificações |
| [`docs/DESIGN-SYSTEM.MD`](docs/DESIGN-SYSTEM.MD) | Tokens, tipografia, componentes |
| [`docs/DEPLOY_HOSTGATOR.md`](docs/DEPLOY_HOSTGATOR.md) | Deploy em produção |

---

## Principais Funcionalidades

- **Login multi-usuário (RBAC)** — sessão Laravel + CSRF; papéis Admin / Subadmin / Visitante / Teste; sem registro público, Sanctum ou OAuth.
- **Importação de extratos** — drag-and-drop (CSV / OFX / QFX conta; fatura de cartão Nubank via **CSV**); validação client+server (máx. 10 MB); resumo de importadas / ignoradas / erros.
- **Parse Nubank** — `NubankCsvParser`, `NubankCreditCardCsvParser` e `OfxParser` (banking) com fixtures e testes (`source` / `statement_kind` no upload). OFX de fatura = backlog.
- **Deduplicação** — hashes estáveis; reupload não duplica movimentações.
- **Dashboard analítico** — metric cards do período + hub Cartões / A cobrar (feature-gated), evolução e distribuição por categoria.
- **Movimentações** — aba `/transactions` com tabela paginada, CRUD e os mesmos filtros (período, tipo, categoria, cartão, pessoa, busca).
- **Filtros dinâmicos** — sincronizados na URL no dashboard e nas movimentações.
- **Datas em forms** — exibição **dd/mm/aaaa** (`DateInput`); API continua `YYYY-MM-DD`.
- **Estados de UX** — skeletons, empty states (CTA de upload), erros e toasts.
- **Storage privado** — arquivos fora do web root; retenção configurável (padrão 90 dias) + comando de purge.
- **Rate limiting** — login e upload protegidos contra abuso.
- **Cartões de crédito** (Etapa H, feature flag) — cadastro (limite, fechamento, vencimento, **padrão**); vínculo em transações; auto-stamp em imports `csv_credit_card`.
- **Pessoas + cobranças** (Etapa H, feature flag) — CRUD de pessoas (`debtors`); empréstimos cash/limite; mark-paid / cancel; aba Pessoas em `/loans`.
- **Notificações de vencimento** (Etapa H, feature flag) — sino in-app + e-mail opcional; command diário `aura:check-due-dates`.

---

## Stack Tecnológica

### Backend

| Tecnologia | Papel |
| --- | --- |
| **PHP 8.2+** | Runtime |
| **Laravel 11** | API JSON, auth session, Eloquent, storage, scheduler |
| **MySQL 8** | Banco principal (produção e local recomendado) |
| **PHPUnit 11** | Testes Feature / Unit |
| **cihansenturk/ofxparser** | Parse OFX/QFX |

### Frontend

| Tecnologia | Papel |
| --- | --- |
| **React 19** | SPA |
| **Vite 6** + **laravel-vite-plugin** | Bundling / HMR |
| **React Router 7** | Rotas client (`/login`, `/dashboard`, `/transactions`, `/upload`, `/cards`, `/loans`, …) |
| **Tailwind CSS 3** + **tokens.css** | Design System Aura (dark) |
| **Axios** | HTTP com cookies + CSRF |
| **Recharts** | Gráficos |
| **react-dropzone** | Upload DnD |
| **lucide-react** / **sonner** | Ícones / toasts |
| **Vitest** | Smoke tests de libs (`formatMoney`, validators) |

### Infra

| Item | Detalhe |
| --- | --- |
| Hospedagem alvo | **HostGator** (cPanel) — subdomínio `aura.vonluqi.com` |
| Document root | Apenas `public/` |
| CI / Deploy | GitHub Actions + FTP (ver `docs/DEPLOY_HOSTGATOR.md`) |
| Cron | `php artisan schedule:run` → `statements:purge-files` @ 03:15 · `aura:check-due-dates` @ 08:00 |

---

## Pré-requisitos

| Software | Versão mínima | Notas |
| --- | --- | --- |
| **PHP** | **8.2+** | Extensões usuais Laravel (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`) |
| **Composer** | 2.x | Dependências PHP |
| **Node.js** | **18+** (LTS recomendado) | Build Vite / React |
| **npm** | 9+ | Lockfile v3 |
| **MySQL** | **8.0+** | Database `aura` (ou equivalente) |
| **Git** | 2.x | Clone do repositório |

Opcional: fila Redis / workers Node **não** são obrigatórios no MVP (`QUEUE_CONNECTION=sync`).

---

## Instalação e Setup Local

### 1. Clonar o repositório

```bash
git clone https://github.com/VonLuqi/A.U.R.A-System.git
cd A.U.R.A-System
```

### 2. Dependências PHP e Node

```bash
composer install
npm ci
```

### 3. Ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Ajuste `DB_*`, `ADMIN_EMAIL` e `ADMIN_PASSWORD` no `.env` (veja a seção de variáveis).

### 4. Banco de dados

Crie o schema MySQL (exemplo alinhado ao `.env.example`):

```sql
CREATE DATABASE aura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE aura_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aura_dev'@'127.0.0.1' IDENTIFIED BY 'sua_senha';
GRANT ALL PRIVILEGES ON aura.* TO 'aura_dev'@'127.0.0.1';
GRANT ALL PRIVILEGES ON aura_testing.* TO 'aura_dev'@'127.0.0.1';
FLUSH PRIVILEGES;
```

`aura_testing` é o banco isolado do PHPUnit (`phpunit.xml`). Sem ele, `RefreshDatabase` apaga os dados do app local.
Em seguida:

```bash
php artisan migrate --seed
```

O seeder cria o **admin** (`ADMIN_EMAIL` / `ADMIN_PASSWORD`, `role=admin`), **cotas** (`RoleLimitsSeeder`), categorias base e, em `local`/`development`/`testing`, logins demo dos demais papéis (`DemoRoleUsersSeeder`: `DEMO_*_EMAIL` / `DEMO_*_PASSWORD`) e dados demo de transações.

**Expansão multi-usuário (Etapa F)** — se o banco já existia sem as migrations `2026_09_29_*`:

```bash
# Backup antes
mysqldump -u aura_dev -p aura > backup_pre_expansao.sql

php artisan migrate
php artisan aura:backfill-transaction-user-id   # idempotente
php artisan db:seed --class=Database\\Seeders\\RoleLimitsSeeder
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
```

Detalhes, ordem das migrations e rollback: `docs/PLAN_EXPANSAO.md` §1.6 · produção: `docs/DEPLOY_HOSTGATOR.md` §5.6.

**Produção (Etapa G / §9.3)** — após merge em `main` (CI faz FTP + migrate + backfill + `RoleLimitsSeeder`):

1. Backup SQL fresco no HostGator.
2. Confirmar `.env` live com `AURA_LIMIT_*` e, se rollout gradual, `AURA_FEATURE_*=false` nos pilares ainda não liberados.
3. Seguir sequência `down` → migrate → backfill → seeds → caches → `up` em `docs/DEPLOY_HOSTGATOR.md` §5.6.
4. Smoke operador (login multi-papel, CRUD, CC, metas, cotas).

**Etapa H (cartões / pessoas / cobranças / notificações)** — após migrations `2026_09_29_194*` e as de `is_default` / `debtors` / `debtor_id`:

```bash
# Backup antes (local ou prod)
mysqldump -u aura_dev -p aura > backup_pre_etapa_h.sql

php artisan migrate
# opcional (só local/dev/testing):
php artisan db:seed --class=Database\\Seeders\\DemoCreditCardsAndLoansSeeder

# Liberar pilares gradualmente no .env (depois: config:clear && config:cache em prod)
# AURA_FEATURE_CREDIT_CARDS=true
# AURA_FEATURE_LOANS=true
# AURA_FEATURE_NOTIFICATIONS=true

php artisan schedule:list   # deve listar aura:check-due-dates @ 08:00
php artisan aura:check-due-dates --dry-run
```

Janelas de alerta: `AURA_NOTIFY_CARD_DUE_DAYS` / `AURA_NOTIFY_LOAN_DUE_DAYS` (default `3`).  
E-mail local: `MAIL_MAILER=log`. Smoke: Cartões (padrão) → Cobranças/Pessoas → filtros hub no dashboard → sino.  
Detalhes: `docs/PLAN_CARTOES_EMPRESTIMOS.md` · produção: `docs/DEPLOY_HOSTGATOR.md` (§6.3 cron + §5.7).

### 5. Subir a aplicação (recomendado: same-origin)

Em dois terminais (ou use `composer run dev`):

```bash
# Terminal 1 — Laravel (origem da sessão / CSRF)
php artisan serve
# → http://127.0.0.1:8000

# Terminal 2 — Vite HMR
npm run dev
```

Acesse **http://127.0.0.1:8000**. O Blade carrega o SPA; assets vêm do Vite.  
Se abrir a origem do Vite (`5173`), o proxy `/api` → Laravel já está em `vite.config.js`.

**Alternativa all-in-one:**

```bash
composer run dev
```

(sobe `artisan serve`, queue listen, pail e Vite via `concurrently`.)

### 6. Build de assets (smoke)

```bash
npm run build
```

### 7. Testes

Os Feature tests usam `RefreshDatabase` no schema **`aura_testing`** (não no `aura` do app). Garanta que o schema exista e o user `DB_USERNAME` tenha grant nele.

```bash
# Backend
php artisan test

# Frontend (smoke Vitest)
npm test
```

---

## Configuração de Variáveis de Ambiente

Base: [`.env.example`](.env.example). Produção: [`.env.production.example`](.env.production.example).

| Variável | Propósito | Exemplo |
| --- | --- | --- |
| `APP_NAME` | Nome da aplicação | `Aura` |
| `APP_ENV` | Ambiente (`local`, `production`, …) | `local` |
| `APP_KEY` | Chave de criptografia Laravel | gerada por `key:generate` |
| `APP_DEBUG` | Stack traces / debug | `true` (local) / `false` (prod) |
| `APP_TIMEZONE` | Fuso conceitual das datas | `America/Sao_Paulo` |
| `APP_URL` | URL canônica (cookies / links) | `http://localhost:8000` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Locale da app | `pt_BR` |
| `APP_FAKER_LOCALE` | Locale do Faker (seeders) | `pt_BR` |
| `APP_MAINTENANCE_DRIVER` | Driver de manutenção | `file` |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | Logging | `stack` / `single` / `debug` |
| `DB_CONNECTION` | Driver do banco | `mysql` |
| `DB_HOST` / `DB_PORT` | Host MySQL | `127.0.0.1` / `3306` |
| `DB_DATABASE` | Nome do schema | `aura` |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciais DB | `aura_dev` / *(secreto)* |
| `SESSION_DRIVER` | Persistência de sessão | `database` |
| `SESSION_LIFETIME` | Minutos de vida da sessão | `120` |
| `SESSION_ENCRYPT` | Criptografar payload de sessão | `false` |
| `SESSION_PATH` / `SESSION_DOMAIN` | Cookie path/domain | `/` / `null` |
| `SESSION_SECURE_COOKIE` | Cookie só HTTPS | `false` local; `true` em prod |
| `SESSION_HTTP_ONLY` / `SESSION_SAME_SITE` | Hardening de cookie | `true` / `lax` |
| `RATE_LIMIT_LOGIN_PER_EMAIL` | Tentativas login / e-mail+IP | `5` |
| `RATE_LIMIT_LOGIN_PER_IP` | Tentativas login / IP | `20` |
| `RATE_LIMIT_UPLOAD_PER_USER` | Uploads / usuário autenticado | `10` |
| `STATEMENT_RETENTION_DAYS` | Dias até purge de arquivos | `90` |
| `FILESYSTEM_DISK` | Disk default | `local` |
| `QUEUE_CONNECTION` | Filas | `sync` (MVP) |
| `CACHE_STORE` / `CACHE_PREFIX` | Cache | `database` / `aura_` |
| `MAIL_*` | Mailer (local tipicamente `log`) | ver `.env.example` |
| `VITE_APP_NAME` | Nome exposto ao front Vite | `${APP_NAME}` |
| `ADMIN_EMAIL` | E-mail do admin (`AdminUserSeeder`) | `admin@aura.local` |
| `ADMIN_PASSWORD` | Senha do admin — **trocar** | `ChangeMeNow!123` |
| `AURA_FEATURE_CREDIT_CARDS` | CRUD cartões + UI `/cards` + filtro/hub dashboard | `false` (rollout) / `true` local |
| `AURA_FEATURE_LOANS` | Pessoas + cobranças + UI `/loans` + filtro/hub dashboard | `false` (rollout) / `true` local |
| `AURA_FEATURE_NOTIFICATIONS` | Sino + `aura:check-due-dates` | `false` (rollout) / `true` local |
| `AURA_NOTIFY_CARD_DUE_DAYS` | Janela (dias) alerta fatura | `3` |
| `AURA_NOTIFY_LOAN_DUE_DAYS` | Janela (dias) alerta cobrança | `3` |

Variáveis Redis / AWS / Memcached existem no skeleton Laravel; **não são necessárias** para o MVP padrão.

### Comandos Artisan úteis

| Comando | Uso |
| --- | --- |
| `php artisan migrate` | Schema app (`aura`) |
| `php artisan test` | Suite PHPUnit em `aura_testing` |
| `php artisan schedule:list` | Confirma purge @ 03:15 e due-dates @ 08:00 |
| `php artisan aura:check-due-dates` | Gera notificações de vencimento (feature on) |
| `php artisan aura:check-due-dates --dry-run` | Conta candidatos sem persistir |
| `php artisan statements:purge-files` | Purge de arquivos de extrato |

---

## Estrutura de Diretórios

```text
.
├── app/                    # Domínio Laravel (Models, Http, Services, Parsers…)
├── bootstrap/              # Bootstrap da aplicação
├── config/                 # Configurações (filesystems, session, …)
├── database/
│   ├── factories/          # Factories de desenvolvimento/teste
│   ├── migrations/         # Schema (users, categories, imports, transactions)
│   └── seeders/            # Admin, categorias, demo
├── docs/                   # Contexto, planos, Design System, deploy
├── public/                 # Document root (index.php, build Vite)
├── resources/
│   ├── css/                # tokens.css, fonts.css, app.css
│   ├── js/                 # SPA React (api/, components/, pages/, hooks/)
│   └── views/              # Blade shell (`app.blade.php` → #app)
├── routes/
│   ├── api.php             # Contratos JSON (/api/*)
│   └── web.php             # Catch-all SPA (não engole /api)
├── storage/                # Logs, cache, uploads privados (statements)
├── tests/                  # PHPUnit (+ Fixtures de extratos)
├── .env.example            # Template local
├── .env.production.example # Template produção
├── composer.json
├── package.json
└── vite.config.js          # Vite + proxy /api em dev
```

---

## Deploy / Produção

Alvo: **HostGator** · `https://aura.vonluqi.com` · document root → `…/aura/public`.

Checklist resumido:

1. MySQL no cPanel + usuário com privilégios mínimos no schema.
2. `.env` de produção a partir de `.env.production.example` (`APP_DEBUG=false`, `APP_URL=https://aura.vonluqi.com`, `SESSION_SECURE_COOKIE=true`).
3. Dependências e assets:

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

4. Migrations + seeder do admin; permissões em `storage/` e `bootstrap/cache/`.
5. Cron cPanel: `* * * * * php /caminho/para/aura/artisan schedule:run` (purge @ 03:15 + due-dates @ 08:00).
6. Validar HTTPS, login, upload Nubank e dashboard; Etapa H: Cartões (padrão) → Cobranças/Pessoas → filtros hub → sino.

Detalhes de FTP, CI e pitfalls de shell: **[`docs/DEPLOY_HOSTGATOR.md`](docs/DEPLOY_HOSTGATOR.md)** · Etapa E em [`docs/MASTER_PLAN.md`](docs/MASTER_PLAN.md).

> Nunca aponte o document root para a raiz do Laravel — apenas `public/`.

---

## Licença e Contato

**Licença:** [MIT](https://opensource.org/licenses/MIT) (conforme `composer.json`).

**Mantenedor:** [VonLuqi](https://github.com/VonLuqi)  
**Repositório:** [github.com/VonLuqi/A.U.R.A-System](https://github.com/VonLuqi/A.U.R.A-System)  
**Produção (planejada):** [aura.vonluqi.com](https://aura.vonluqi.com)

Para dúvidas de produto/arquitetura, consulte primeiro [`docs/context.md`](docs/context.md). Issues e PRs são bem-vindos no repositório GitHub.
