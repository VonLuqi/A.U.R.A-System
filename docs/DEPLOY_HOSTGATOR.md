# Deploy HostGator (Aura)

> Runbook operacional da **Etapa E**. Plano detalhado: `docs/PLAN_ETAPA_E.md`.  
> Fonte de arquitetura: `docs/context.md` §2.1.

## Revisão operacional (§1.6 — 2026-09-29)

| Item | Status |
|------|--------|
| Paths / secrets FTP | Inalterados: `FTP_SERVER_DIR=aura/`, home `/home4/luca9682`, docroot `…/aura/public` |
| URL canônica | **`https://aura.vonluqi.com`** em todo o runbook + `.env.production.example` |
| Template prod `APP_URL` | Corrigido (era `https://vonluqi.com` — **errado**) → `https://aura.vonluqi.com` |
| PHP / cron | `ea-php82` absoluto + MultiPHP 8.2 + `public/.user.ini` |
| SSL | Let's Encrypt live (expira 2026-12-26) |
| Pendência servidor | Confirmar no `.env` **live** `SESSION_SECURE_COOKIE=true` + `config:cache` (cookie sem `Secure` no probe) |

**Consistência cruzada:** `MASTER_PLAN` / `context.md` / `README.md` / `PLAN_ETAPA_E` usam `APP_URL=https://aura.vonluqi.com`. Domínio principal `vonluqi.com` = só `public_html`, **não** é a URL do app.

### Template `.env` de produção (§3.1)

1. Base: arquivo versionado **`.env.production.example`** (raiz do repo).
2. Correção já aplicada no Git: `APP_URL=https://aura.vonluqi.com` (não usar `https://vonluqi.com`).
3. Copiar para o servidor **só** por FileZilla/Terminal → `/home4/luca9682/aura/.env` (fora de `public/`).
4. Preencher `APP_KEY` (gerar no servidor), `DB_PASSWORD`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`.
5. `chmod 600 .env` se shell disponível.
6. **Nunca** `git add .env` / nunca incluir `.env` no FTP Deploy (exclude do workflow).

``bash
# Local (off-repo): preparar .env a partir do example, depois upload FTP para aura/.env
# No servidor, após editar:
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
cd /home4/luca9682/aura
$PHP_BIN artisan key:generate --force   # se APP_KEY vazio
$PHP_BIN artisan config:clear && $PHP_BIN artisan config:cache
``

### Checklist de chaves obrigatórias (§3.2)

Fonte: `.env.production.example` (valores canônicos). No servidor, preencher só as células **secreto**.

| Chave | Valor template / regra |
|-------|-------------------------|
| `APP_NAME` | `Aura` |
| `APP_ENV` | `production` |
| `APP_KEY` | **vazio no Git** → gerar no host: `$PHP_BIN artisan key:generate --force` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://aura.vonluqi.com` |
| `APP_TIMEZONE` | `America/Sao_Paulo` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `pt_BR` |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` | `localhost` / `3306` |
| `DB_DATABASE` | `luca9682_aura` |
| `DB_USERNAME` | `luca9682_vonluqi` |
| `DB_PASSWORD` | **secreto** (cofre + `.env` live) |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `120` |
| `SESSION_SECURE_COOKIE` | `true` (re-`config:cache` se cookie live sem `Secure`) |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `SESSION_DOMAIN` | `null` (same-host; **não** `.vonluqi.com`) |
| `CACHE_STORE` / `CACHE_PREFIX` | `database` / `aura_` |
| `QUEUE_CONNECTION` | `sync` |
| `FILESYSTEM_DISK` | `local` |
| `RATE_LIMIT_LOGIN_PER_EMAIL` | `5` |
| `RATE_LIMIT_LOGIN_PER_IP` | `20` |
| `RATE_LIMIT_UPLOAD_PER_USER` | `10` |
| `STATEMENT_RETENTION_DAYS` | `90` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | **secreto** (≥16 chars; AdminUserSeeder) |
| `VITE_APP_NAME` | `"${APP_NAME}"` (informativa; assets já no build) |

### O que **não** colocar em produção (§3.3)

| Proibido | Evidência / regra |
|----------|-------------------|
| `APP_DEBUG=true` | Template: `false`; forçar erro live → JSON genérico |
| Credenciais locais | Sem `aura_dev` / `ChangeMeNow!123`; DB = `luca9682_*`; admin real no cofre |
| `db:seed` completo às cegas | `DemoTransactionSeeder` é no-op fora de `local`/`development`/`testing`, mas **não** rodar DatabaseSeeder em prod; admin só via `AdminUserSeeder` controlado |
| Keys AWS / Redis | Ausentes do `.env.production.example`; MVP não usa |
| `MAIL_MAILER=smtp` + senhas | Template: `MAIL_MAILER=log` até e-mail ser necessário |

### Pós-criação do `.env` (§3.4)

1. `chmod 600 /home4/luca9682/aura/.env` (Terminal/SSH se Shell Access OK).
2. Path canônico: **`/home4/luca9682/aura/.env`** — **nunca** sob `public/` (docroot). `GET /.env` via HTTPS → **406** (evidência §1.3.1).
3. CI: `deploy-hostgator.yml` exclude inclui `**/.env`, `**/.env.*`, `**/.env.db.*`, `**/.env.ftp.*` + `dangerous-clean-slate: false`.
4. Após editar `.env` no host:

``bash
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
cd /home4/luca9682/aura
$PHP_BIN artisan config:clear
$PHP_BIN artisan config:cache
``

## Secrets do GitHub (Settings → Secrets and variables → Actions) · §3.5

| Secret | Valor canônico |
|--------|----------------|
| `FTP_SERVER` | `vonluqi.com` (mesmo host FileZilla) |
| `FTP_USERNAME` | `luca9682` |
| `FTP_PASSWORD` | senha cPanel (ou FTP dedicada); **nunca** no Git |
| `FTP_SERVER_DIR` | `aura/` |

**Uso:** `deploy-hostgator.yml` (e workflows de diagnóstico) via `${{ secrets.* }}` / `env:`.

**Não vazar:**
- Não `echo` / `cat` de `.env` ou senhas nos steps.
- Secrets do GitHub são mascarados nos logs quando o valor aparece; ainda assim evitar `printenv` / dump de `FTP_PASSWORD`.
- Workflows de fix/diagnose só checam presença (`.env OK` / `env=yes`), **sem** imprimir conteúdo.
- Se senha vazou em Issue/log/chat → rotacionar no cPanel + atualizar `FTP_PASSWORD` nos Secrets.

## Layout no servidor (Cenário A — contrato §0.1)

| Item | Path / valor |
|------|----------------|
| Conta cPanel | `luca9682` |
| Home | `/home4/luca9682` |
| Domínio principal | `vonluqi.com` → Document Root **`/public_html`** (fixado pela HostGator; **não** hospeda Aura) |
| Código Laravel | **`/home4/luca9682/aura`** (irmão de `public_html`, **fora** de `public_html`) |
| Document root do app | **`/home4/luca9682/aura/public`** |
| URL canônica | `https://aura.vonluqi.com` |
| FTP target | `aura/` (= `~/aura`, **nunca** `public_html/aura`) |

### Regras absolutas

- **`public_html` não hospeda Aura.** O site principal `vonluqi.com` permanece em `/public_html` e não deve receber código Laravel, `.env`, `vendor/` nem uploads de extrato.
- **`aura/` fica fora de `public_html`.** Layout: `/home4/luca9682/aura/...` e `/home4/luca9682/public_html/...` são pastas irmãs sob o home — **não** aninhar Aura em `public_html/aura`.
- Document root do subdomínio aponta **somente** para `aura/public`. Nunca para `/home4/luca9682/aura` (raiz Laravel).

``text
/home4/luca9682/
├── public_html/          ← vonluqi.com (intocado; NÃO é Aura)
└── aura/                 ← Laravel (FTP_SERVER_DIR)
    ├── .env              ← fora do web root
    ├── app/ vendor/ …
    ├── storage/          ← privado
    └── public/           ← document root de aura.vonluqi.com
``

## Subdomínio (Cenário A — Em uso) · §1.3

| Check | Evidência (2026-09-29) |
|-------|-------------------------|
| Subdomínio `aura.vonluqi.com` | Criado; DNS **A** → `162.241.63.49` |
| Document Root | `/home4/luca9682/aura/public` (Cenário A; **não** `public_html`) |
| HTTPS | **200** + cookies `aura_session` / `XSRF-TOKEN` |
| HTTP | **301** → `https://aura.vonluqi.com/` |
| SPA rewrite | `/login`, `/dashboard` → **200** `text/html` (front controller) |
| Assets | `/build/manifest.json` → **200** JSON |
| Domínio principal | `https://vonluqi.com` → **403** (não é Aura; sem sessão Laravel) |

### Hardening docroot (§1.3.1)

| Check | Status |
|-------|--------|
| `public/.htaccess` | No repo: rewrite → `index.php`, `Options -MultiViews -Indexes`, force HTTPS |
| Path traversal / `.env` | `GET /.env` → **406** (não 200) |
| Extratos privados | Sem `storage:link`; disk `serve=false`; rotas SPA não devem servir CSV |
| Directory listing | `-Indexes` no `.htaccess` Laravel |

## PHP no HostGator (contrato §0.2)


Laravel 11 exige **PHP 8.2+**. No shared HostGator, o `php` do PATH do cron/Terminal **pode ser legado** — **não** usar às cegas.

| Binário | Path | Uso |
|--------|------|-----|
| Preferido (absoluto) | `/opt/cpanel/ea-php82/root/usr/bin/php` | Artisan, cron `schedule:run`, migrate, caches |
| Alias cPanel | `ea-php82` | Se estiver no PATH do shell |
| `php` (PATH) | variável | Só após `php -v` confirmar ≥ 8.2 |

### Validar no Terminal (antes de fixar o cron)

``bash
php -v
command -v ea-php82
ls -la /opt/cpanel/ea-php82/root/usr/bin/php
/opt/cpanel/ea-php82/root/usr/bin/php -v
``

Esperado: saída **PHP 8.2.x**. Se o absoluto existir, **esse** é o binário canônico do cron e dos scripts de pós-deploy.

O workflow `deploy-hostgator.yml` já tenta, nesta ordem: `ea-php82` → `/opt/cpanel/ea-php82/root/usr/bin/php` → `php`.

### MultiPHP do subdomínio (obrigatório) · §1.5

1. cPanel → **MultiPHP Manager**.
2. Selecionar **`aura.vonluqi.com`**.
3. Versão: **PHP 8.2** (ea-php82) — Laravel 11 exige ≥ 8.2; app já responde em produção (prova FPM compatível).
4. Salvar.
5. **MultiPHP INI Editor** (domínio `aura`) **ou** confiar no `public/.user.ini` versionado:

| Diretiva | Valor alvo |
|----------|------------|
| `upload_max_filesize` | `20M` (≥ 12M; MVP upload 10 MB) |
| `post_max_size` | `20M` |
| `max_execution_time` | `60`–`120` |
| `memory_limit` | ≥ `128M` (`.user.ini` usa `256M`) |
| `display_errors` | `Off` |

6. Extensões necessárias: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip` (padrão EA-PHP 8.2).

> O MultiPHP define o PHP do **Apache/FPM** que serve a SPA. O cron usa o binário CLI absoluto (§0.2) — ambos devem ser 8.2.  
> Arquivo `public/.user.ini` sobe no deploy FTP e reforça limites sem painel (quando o host honrar `.user.ini`).

### Cron canônico (`schedule:run`) · §6.3

Laravel agenda `statements:purge-files` diariamente às **03:15** (`routes/console.php`). O cron do host chama o scheduler **a cada minuto**.

**cPanel → Cron Jobs → Add**

| Campo | Valor |
|-------|--------|
| Frequência | `* * * * *` (Every Minute) |
| Usuário | conta cPanel `luca9682` |
| Comando canônico | abaixo |

``bash
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home4/luca9682/aura/artisan schedule:run >> /home4/luca9682/aura/storage/logs/scheduler.log 2>&1
``

Alternativa se `ea-php82` estiver no PATH do cron:

``bash
* * * * * cd /home4/luca9682/aura && ea-php82 artisan schedule:run >> storage/logs/scheduler.log 2>&1
``

**Validar após criar:**

1. Aguardar 1–2 min → ler `storage/logs/scheduler.log` (ou e-mail de saída do cron).
2. `$PHP_BIN artisan schedule:list` → entrada `statements:purge-files` @ `03:15`.
3. Dry-run: `$PHP_BIN artisan statements:purge-files --days=90 --dry-run`.

Se o path real do `ea-php82` for outro, atualizar **esta** linha no cron e neste runbook. Não usar `php` genérico sem `php -v` ≥ 8.2.

### O que o cron **não** faz (§6.4)

| Proibido | Motivo |
|----------|--------|
| `queue:work` / Horizon | MVP `QUEUE_CONNECTION=sync` — sem worker |
| `migrate` periódico no cron | Migrate só no deploy / Migrate HostGator / manual |
| URLs web de cron (wget/curl em `public/`) | Scheduler só via CLI `artisan schedule:run` |

### Artisan no servidor (pós-deploy)

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php

$PHP_BIN artisan --version
$PHP_BIN artisan key:generate --force
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
``

## Modelo de deploy (contrato §0.3)

| Decisão | Valor MVP |
|--------|-----------|
| Build | CI Ubuntu — `composer install --no-dev --optimize-autoloader` + `npm ci` + `npm run build` |
| Transferência | FTP-Deploy-Action **sem** `vendor/`; `vendor.tar.gz` via `lftp` |
| Extract | SSH se shell OK; senão FileZilla / PharData (`Migrate HostGator`) |
| `.env` | Criado **uma vez** no servidor; **nunca** sobrescrito pelo CI (exclude `.env` / `.env.*`) |
| Migrate | `migrate --force` no pós-deploy; seed admin **manual/controlado** (não demo) |
| Clean slate FTP | **`dangerous-clean-slate: false`** — deploy **não** apaga o remoto antes de subir |

### `dangerous-clean-slate: false` (obrigatório)

Confirmado em `.github/workflows/deploy-hostgator.yml`:

``yaml
dangerous-clean-slate: false
``

**Por quê:** com `true`, o FTP apagaria o destino e destruiria `storage/` (logs, sessions, **extratos privados** em `storage/app/private/statements/`), caches e qualquer arquivo só-servidor. Com `false`, o sync é incremental e preserva uploads/dados locais do host.

Excludes relevantes (além de `.env`): `storage/logs/**`, caches de framework, `storage/app/private/statements/**`, `vendor/`, `node_modules/`, `tests/`.

> **Nunca** alterar para `dangerous-clean-slate: true` sem plano de backup + restore de `statements/` e `.env`.

### Build local — smoke (§4.1)

Rodar **antes** do push para `main` (espelha o que o CI faz + tests):

``bash
# Prod-like (CI Ubuntu). No Windows, --no-dev pode falhar por file lock → OK confiar no CI.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Dev local (necessário para php artisan test):
composer install --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build   # ou: npx vite build
php artisan test
npm test
``

| Check | Evidência (2026-09-29) |
|-------|------------------------|
| `public/build/manifest.json` | Gerado — entries `app.css` + `app.jsx` → `assets/app-7rrHQvAB.css`, `assets/app-ChUfvXoK.js` |
| Poppins | CSS build contém `@font-face{font-family:Poppins…}` + `/fonts/poppins/*.woff2` |
| Tokens | `resources/css/tokens.css` importado via `app.css`; `--font-sans: "Poppins"` no DS |
| PHPUnit | **240** passed |
| Vitest | **3** passed (2 files) |

Nota Windows: se `bootstrap/cache` estiver ReadOnly, `attrib -R bootstrap\cache`; se `esbuild.exe` locked, matar `node`/`vite` e re-`npm ci`.

### Build via CI — `deploy-hostgator.yml` (§4.2)

Validação estática do workflow (caminho padrão de produção):

| Step | Evidência no YAML |
|------|-------------------|
| PHP 8.2 + extensões | `setup-php@v2` → `8.2`; `mbstring, xml, ctype, curl, zip, pdo_mysql, fileinfo, openssl, tokenizer, gd` |
| Node 20 + `npm ci` | `setup-node@v4` → `20` + cache npm; step `npm ci` |
| Composer prod | `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader` |
| Frontend | `npm run build` |
| Pack vendor | `tar -czf vendor.tar.gz vendor` |
| FTP app | `FTP-Deploy-Action@v4.3.5`; exclude: `vendor/`, `.env*`, `node_modules/`, `tests/`, `docs/`, …; `dangerous-clean-slate: false` |
| Upload vendor | `lftp` → `put vendor.tar.gz` em `FTP_SERVER_DIR` |
| Pós-deploy SSH | extract tar → `migrate --force` → `config:cache` (+ `route`/`view` cache); falha explícita se shell desabilitado |

Triggers: `push` em `main` + `workflow_dispatch`. Concurrency: `deploy-hostgator` (cancel-in-progress).

### Excludes críticos FTP (§4.3)

Confirmados em `deploy-hostgator.yml` (`dangerous-clean-slate: false` — sync incremental; **não** apaga remoto):

| Exclude | Padrão no workflow |
|---------|-------------------|
| `.env` / `.env.*` | `**/.env`, `**/.env.*`, `**/.env.db.*`, `**/.env.ftp.*` |
| `.git` / `.github` | `**/.git*`, `**/.git*/**`, `**/.github/**` |
| `node_modules/` | `**/node_modules/**` |
| `vendor/` (só via tar/`lftp`) | `**/vendor/**` + `**/vendor.tar.gz` no Deploy-Action (tar sobe no step `lftp`) |
| `tests/` / `phpunit.xml` | `**/tests/**`, `**/phpunit.xml` |
| Storage sensível | `**/storage/logs/**`, `**/storage/framework/{cache/data,sessions,views}/**`, `**/storage/app/private/statements/**` |
| Outros | `docs/`, `.vscode/`, `README.md`, `.editorconfig`, caches PHPUnit, `.DS_Store` |

### Primeiro deploy — bootstrap (§4.4)

| Opção | Como |
|-------|------|
| A | `git push origin main` → dispara **Deploy HostGator** |
| B | Actions → **Deploy HostGator** → `Run workflow` (`workflow_dispatch`) |

**Monitorar** até steps FTP + `lftp` `vendor.tar.gz` OK.

**Se SSH falhar** (`Shell access is not enabled`):

1. FileZilla → `aura/` → extrair `vendor.tar.gz` (gerar `vendor/`).
2. Disparar **Migrate HostGator** (`workflow_dispatch` — FTP + PHP one-shot HTTPS com token).

**Evidência live (2026-09-29) — bootstrap já efetivo:**

| Artefato | Probe / inferência |
|----------|-------------------|
| `public/build/manifest.json` | `GET /build/manifest.json` → **200** `application/json` |
| `public/index.php` + front controller | `GET /` → **200** + cookie `aura_session` |
| `public/.htaccess` | No repo + rewrite SPA; HTTP→HTTPS 301; paths `/login` 200 |
| `vendor/autoload.php` | App Laravel sobe (`/api/user` → **401** JSON auth); sem vendor = fatal |

### Deploys subsequentes (§4.5)

| Passo | Regra |
|-------|--------|
| Trigger | `git push origin main` (ou `workflow_dispatch`) → build + FTP **incremental** (`dangerous-clean-slate: false`) |
| `vendor/` | Re-extrair `vendor.tar.gz` **sempre que `composer.lock` mudar** (SSH no deploy, ou FileZilla + Migrate se shell off) |
| Novas envs | Editar `/home4/luca9682/aura/.env` no host **antes** de `config:cache` (CI não sobe `.env`) |
| Migrations | `migrate --force` no pós-deploy SSH; se shell off → **Migrate HostGator** |

``bash
# Manual (shell OK), após editar .env ou se SSH step falhou:
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
# se composer.lock mudou e vendor.tar.gz ainda no host:
tar -xzf vendor.tar.gz && rm -f vendor.tar.gz
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:clear && $PHP_BIN artisan config:cache
``

### Otimização Laravel em prod (§4.6)

Após `.env` válido + `vendor/` + código no host:

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php

$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan route:clear

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache   # pode falhar — ver nota
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache   # opcional
``

| Item | Status / regra |
|------|----------------|
| Sequência | Rodar via SSH; deploy já faz `config:cache` + `route:cache \|\| true` + `view:cache \|\| true`; **Migrate HostGator** faz `config:cache` |
| `route:cache` | Pode falhar: `routes/api.php` tem closure em `/csrf-cookie`; `web.php` usa `Route::view`. Workflow tolera com `\|\| true`. Se falhar: `route:clear` e seguir (app OK sem route cache) |
| `bootstrap/cache/config.php` | Deve existir após `config:cache` (File Manager / Terminal) |
| Após editar `.env` | Sempre `config:clear` + `config:cache` de novo |

### Composer no servidor (§4.7)

| Regra | Detalhe |
|-------|---------|
| Padrão | **Não** rodar `composer install` no shared HostGator — CI já envia `vendor.tar.gz` (CPU/timeout/OOM) |
| Emergência | Só se extract falhar e shell OK: |

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
# se composer no PATH via ea-php:
$PHP_BIN -d memory_limit=512M $(which composer) install --no-dev --no-interaction --prefer-dist --optimize-autoloader
# preferível: re-upload vendor.tar.gz do CI + tar -xzf
``

Preferir sempre re-deploy / re-extract do tar em vez de `composer` no host.

## Como o deploy funciona

1. CI: `composer install --no-dev --optimize-autoloader` + `npm ci` + `npm run build`
2. Empacota `vendor/` em **um** arquivo `vendor.tar.gz` (FTP arquivo-a-arquivo estoura timeout)
3. FTP do código **sem** `vendor/` (`dangerous-clean-slate: false`)
4. FTP do `vendor.tar.gz`
5. SSH extrai o `vendor.tar.gz` em `~/aura` (+ migrate/caches se shell OK)

### SSH / Shell Access

**Chave SSH autorizada ≠ shell liberado.** Em “Gerenciar chaves do SSH” dá para criar `aura_rsa`, mas a conta ainda pode responder:

`Shell access is not enabled on your account!`

Nesse caso o migrate via SSH **não roda**. O workflow **Migrate HostGator** usa FTP + PHP one-shot por HTTPS (não depende de shell).

Para extract de `vendor.tar.gz` no deploy completo, shell ainda ajuda. Sem shell: no FileZilla, em `aura/`, se existir `vendor.tar.gz`, extraia/descompacte para gerar a pasta `vendor/`, ou peça à HostGator para habilitar **Shell Access** (não só chaves).

## Preparação única após 1º deploy

1. Criar `.env` em `/home4/luca9682/aura/.env` (use `.env.production.example` como base; `APP_URL=https://aura.vonluqi.com`).
2. Confirmar MultiPHP **8.2** em `aura.vonluqi.com` (§0.2).
3. No Terminal/SSH (binário 8.2):

``bash
cd ~/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
$PHP_BIN artisan key:generate --force
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
``

## Fluxo

`git push origin main` → Action faz build + FTP + extract.

`.env` de produção **nunca** sobe pelo Git/FTP (está no exclude).

## Diretório da aplicação (§1.2)

Contrato + status (2026-09-29):

| Check | Status |
|-------|--------|
| Pasta `/home4/luca9682/aura` | Existe (histórico deploy + File Manager; FTP `aura/`) |
| Document root | **`…/aura/public`** — `GET /build/manifest.json` → **200** `application/json` (assets Vite no web root) |
| Docroot ≠ raiz Laravel | `GET /.env` → **406** (não 200); app responde cookies `aura_session` via `public/index.php` |
| `storage:link` para extratos | **Proibido** — disk `statements` privado; sem symlink `public/storage` → private |
| `storage/app/private/statements` | Criar no servidor se ausente (`mkdir -p`); ou no 1º upload (`StatementStorage`) |

Árvore esperada após deploy:

``text
/home4/luca9682/aura/
├── .env                 ← manual; nunca via Git/FTP
├── app/ bootstrap/ config/ database/
├── public/              ← document root do subdomínio
│   ├── index.php
│   ├── .htaccess
│   ├── .user.ini        ← limites PHP §1.5 (upload 20M, display_errors Off)
│   └── build/           ← Vite (manifest.json)
├── resources/ routes/
├── storage/
│   └── app/private/statements/
├── vendor/              ← extract vendor.tar.gz
├── artisan
└── composer.json
``

Criar pasta (se ainda não existir):

``bash
mkdir -p /home4/luca9682/aura
mkdir -p /home4/luca9682/aura/storage/app/private/statements
mkdir -p /home4/luca9682/aura/storage/framework/{cache,sessions,views}
mkdir -p /home4/luca9682/aura/storage/logs
mkdir -p /home4/luca9682/aura/bootstrap/cache
``

### Permissões de escrita (§6.1)

Objetivo: PHP (UID `luca9682` no shared) escreve em `storage/` e `bootstrap/cache/`.

``bash
cd /home4/luca9682/aura
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/private/statements bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod -R ug+rwx storage bootstrap/cache
# NUNCA chmod -R 777 permanente
``

| Check | Como |
|-------|------|
| Escrita | `$PHP_BIN artisan tinker --execute="file_put_contents(storage_path('logs/perm-check.txt'), 'ok');"` → remover o arquivo depois |
| Logs | Erro controlado cria/atualiza `storage/logs/laravel.log` |
| Upload | Extrato sob `storage/app/private/statements/...` (§7) |
| Live | Cookie `aura_session` (driver `database` + framework dirs) implica storage/bootstrap utilizáveis |

Diagnose workflows (`fix-hostgator-403` / `diagnose-hostgator-500`) já aplicam `chmod 775` nesses dirs se necessário.

### Arquivos sensíveis (§6.2)

| Arquivo | Permissão / regra |
|---------|-------------------|
| `/home4/luca9682/aura/.env` | `chmod 600` (ou `640`); **nunca** world-readable |
| `.env` em `public/` | **Proibido** — path canônico só na raiz Laravel; `GET /.env` → **406** |
| `artisan` | Executável pelo dono; **não** world-writable (`644`/`755` ok; sem `666`/`777`) |

## Inventário e acesso (§1.1)


Preenchido em **2026-09-29** (probe remoto + contrato cPanel). Atualizar após login interativo no Terminal.

| Item | Valor / status |
|------|----------------|
| Conta cPanel | `luca9682` |
| Home | `/home4/luca9682` |
| Servidor (histórico) | `sh-pro106` (HostGator shared) |
| IP DNS `aura.vonluqi.com` | `162.241.63.49` (nslookup 2026-09-29) |
| HTTPS | `https://aura.vonluqi.com` → **200** (Apache, `text/html`, cookies Laravel) |
| HTTP→HTTPS | `http://…` → **301** `Location: https://aura.vonluqi.com/` |
| Cookies observados | `XSRF-TOKEN` (SameSite=Lax); `aura_session` (**HttpOnly** + SameSite=Lax) |
| Softaculous | N/A para Aura (app própria; não obrigatório anotar versão) |
| Shell Access | Pode estar desabilitado — ver § SSH; fallback **Migrate HostGator** |
| Disco livre | Meta ≥ **500 MB** — checar cPanel → **Disk Usage** / estatísticas da conta |
| Inodes | Shared: `vendor/` é pesado — checar cPanel → Disk Usage → **inodes**; liberar se próximo do limite |

### Achado (seguir em §3 / validação)

- No probe HTTPS, `aura_session` veio com `HttpOnly` + `SameSite=Lax`, mas **sem** flag `Secure` no header. Confirmar no `.env` do servidor `SESSION_SECURE_COOKIE=true` e `config:cache` (§0.4 / §3).

### Comandos Terminal (quando Shell Access OK)

``bash
whoami          # esperado: luca9682
pwd             # esperado: /home4/luca9682 ou ~
php -v
command -v ea-php82 || ls /opt/cpanel/ea-php82/root/usr/bin/php
df -h ~         # ou Disk Usage no cPanel
``

Se imprimir `Shell access is not enabled on your account!` → **não** depende de SSH para migrate; usar workflow **Migrate HostGator** + extract `vendor.tar.gz` via FileZilla.

### Onde checar disco / inodes (cPanel)

1. Login cPanel da conta `luca9682`.
2. **Disk Usage** (or **Estatísticas** / **Uso de disco**).
3. Confirmar espaço livre ≥ 500 MB e inodes com folga antes de novo `vendor.tar.gz`.

## Banco de Dados MySQL (§2)

### 2.1 Database (canônico)

| Item | Valor |
|------|--------|
| Painel | cPanel → **MySQL® Databases** |
| Nome completo | **`luca9682_aura`** (prefixo conta + `_aura`) — espelhar em `DB_DATABASE` |
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` (default Laravel) |
| Evidência live (2026-09-29) | `GET /api/user` → **401** JSON + cookie `aura_session` com `SESSION_DRIVER=database` ⇒ MySQL acessível e tabelas de sessão tipicamente migradas |

> Sempre anotar o nome **completo** com prefixo cPanel. Nunca usar só `aura` em produção HostGator.

Criar DB (se ainda não existir):

1. MySQL® Databases → Create Database → `aura` (painel prefixa → `luca9682_aura`).
2. Confirmar na lista o nome completo.
3. phpMyAdmin → Operations → Collation `utf8mb4_unicode_ci` se necessário.

### 2.2 Usuário MySQL (permissões mínimas)

| Item | Valor |
|------|--------|
| Usuário canônico (template) | **`luca9682_vonluqi`** — já referenciado em `.env.production.example` (`DB_USERNAME`) |
| Alternativa dedicada | `luca9682_aura_app` (criar no cPanel se quiser user exclusivo Aura) |
| Database associado | **somente** `luca9682_aura` |
| Senha | Forte (≥ 20 chars) no cofre + `.env` do servidor (`chmod 600`); **nunca** no Git |
| Privilégios MVP (cPanel) | ALL PRIVILEGES **no DB Aura apenas** (escopo do painel shared) |
| Ideal (se granular) | `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `REFERENCES`, `DROP` — **sem** `GRANT OPTION` |
| Evidência live | Sessão `database` + `/api/user` 401 ⇒ credenciais DB no `.env` do host autenticam |

Checklist cPanel:

1. MySQL® Databases → Create User (ou reutilizar `luca9682_vonluqi`).
2. Add User To Database → user + `luca9682_aura` → ALL PRIVILEGES (só nesse DB).
3. Remover o user de qualquer outro database da conta.
4. Atualizar `DB_USERNAME` / `DB_PASSWORD` no `/home4/luca9682/aura/.env` e `config:cache`.

### 2.3 Conectividade

| Item | Valor |
|------|--------|
| `DB_HOST` | **`localhost`** (cPanel HostGator — **não** IP público / remoto) |
| `DB_PORT` | **`3306`** |
| `DB_CONNECTION` | `mysql` |
| Timezone app | `APP_TIMEZONE=America/Sao_Paulo` (datas Laravel); MySQL tipicamente `SYSTEM`/UTC no shared — OK com Carbon |
| Teste | `php artisan db:show` / `migrate --force` no servidor; ou smoke API |

Evidência live (2026-09-29): `GET https://aura.vonluqi.com/api/user` → **401** `{"message":"Unauthenticated."}` com cookies de sessão — stack `SESSION_DRIVER=database` + `CACHE_STORE=database` exige MySQL `localhost` funcional.

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
$PHP_BIN artisan db:show
``

### 2.4 Backup zero (pré-migrate / pré-mudança)

Obrigatório **antes** de `migrate --force` em schema novo ou destrutivo. Mesmo DB “vazio” merece snapshot.

| Item | Ação |
|------|------|
| Ferramenta | cPanel → **phpMyAdmin** → selecionar `luca9682_aura` → **Export** |
| Formato | SQL (Quick ou Custom: estrutura + dados) |
| Nome arquivo | `aura-pre-migrate-YYYYMMDD.sql` (ou `aura-prod-YYYYMMDD-HHMM.sql`) |
| Armazenamento | Offline + cofre cifrado — **nunca** em `public/` nem no Git |
| Frequência mínima pós-go-live | Semanal (§8); sempre antes de migration arriscada |

Passos rápidos:

1. phpMyAdmin → `luca9682_aura` → Export → Go.
2. Baixar o `.sql`.
3. Guardar fora do servidor web (PC/cofre).
4. Só então rodar migrate / seed.

> Se o DB já estava migrado antes desta checklist, fazer **backup atual** agora e tratar como baseline §8.1 (não reescrever histórico).

### Pré-migrate (§5.1)

Checklist obrigatório **antes** de qualquer `migrate --force`:

| Check | Evidência / ação |
|-------|------------------|
| Backup SQL | §2.4 — phpMyAdmin Export `luca9682_aura` → `aura-pre-migrate-YYYYMMDD.sql` (mesmo DB vazio) |
| `.env` `DB_*` | Template: `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_DATABASE=luca9682_aura`, `DB_USERNAME=luca9682_vonluqi`, `DB_PASSWORD` filled; live: sessão `database` + `/api/user` 401 |
| `vendor/autoload.php` | Presente após extract `vendor.tar.gz` (§4.4); app Laravel sobe |

Só após os três → §5.2 migrate.

### Migrations (§5.2)

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
$PHP_BIN artisan migrate --force   # SEM --seed
``

Deploy SSH / **Migrate HostGator** já usam `migrate --force` sem seed.

| Tabela / coluna | Migration |
|-----------------|-----------|
| `users`, `password_reset_tokens`, `sessions` | `0001_01_01_000000_create_users_table` |
| `cache`, `cache_locks` | `0001_01_01_000001_create_cache_table` |
| `jobs`, `job_batches`, `failed_jobs` | `0001_01_01_000002_create_jobs_table` |
| `categories` | `2026_09_27_212601_create_categories_table` |
| `statement_imports` | `2026_09_27_212743_create_statement_imports_table` |
| `statement_imports.purged_at` (+ index) | `2026_09_28_001133_add_purged_at_to_statement_imports_table` |
| `transactions` | `2026_09_27_233436_create_transactions_table` |

Índices `transactions`: `unique(unique_hash)`, `index(occurred_on)`, `index(type)`, `index(category_id)`, `index(occurred_on, type)`.

Inspeção: phpMyAdmin → `luca9682_aura` → Structure; ou `$PHP_BIN artisan db:show` / `migrate:status`.

### Seeder admin controlado (§5.3)

**Nunca** `db:seed` completo em prod (puxa `DemoTransactionSeeder` via `DatabaseSeeder`). Demo é no-op fora de local, mas ainda assim usar classes explícitas:

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php

# ANTES: ADMIN_EMAIL + ADMIN_PASSWORD fortes no .env (≥16 chars); NUNCA ChangeMeNow!123
$PHP_BIN artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force
$PHP_BIN artisan db:seed --class=Database\\Seeders\\CategorySeeder --force
``

| Regra | Detalhe |
|-------|---------|
| Idempotência | `AdminUserSeeder` = `updateOrCreate` por e-mail; `CategorySeeder` por slug |
| MVP senha | Manter `ADMIN_PASSWORD` no cofre + `.env` `chmod 600`; **nunca** no Git |
| Single-user | phpMyAdmin → `users`: 1 linha = admin esperado |
| Login | Validar na UI (§7) com as mesmas credenciais |

### Migrate sem shell — fallback (§5.4)

Workflow: **Migrate HostGator** (`migrate-hostgator.yml`, só `workflow_dispatch`).

| Passo one-shot (`public/__migrate_once.php`) | Evidência no YAML |
|----------------------------------------------|-------------------|
| Token | `hash_equals` vs token rand 32 hex; senão **403** |
| Extract `vendor.tar.gz` | Se `vendor/autoload.php` ausente → PharData decompress/extract |
| `migrate --force` | `Artisan::call('migrate', ['--force' => true])` |
| Self-delete | `@unlink(__FILE__)` no sucesso **e** em `fail()`; CI ainda `rm` via FTP |

**Nunca** deixar `__migrate_once.php` permanente em `public/` — o workflow apaga após o curl.

### Pós-migrate (§5.5)

1. Inspecionar schema: `$PHP_BIN artisan db:show` **ou** phpMyAdmin → `luca9682_aura` (tabelas §5.2 presentes).
2. Rodar caches Laravel (§4.6): `config:clear` → `config:cache` (+ `view:cache`; `route:cache` se não falhar por closures).
3. Migrate HostGator já embute `config:cache` após migrate — reforçar se `.env` foi editado depois.

## Segurança mínima de produção (contrato §0.4)


Baseline obrigatório antes do go-live. Template: `.env.production.example`.

| Controle | Valor / evidência |
|----------|-------------------|
| HTTPS | AutoSSL / Let's Encrypt em `aura.vonluqi.com`; HTTP→HTTPS (cPanel §1.4) |
| `APP_URL` | `https://aura.vonluqi.com` (nunca `http://` nem domínio principal sem subdomínio) |
| `APP_DEBUG` | `false` |
| `LOG_LEVEL` | `error` — sem stack traces ao cliente |
| Sessão | `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` (`config/session.php`) |
| Auth | Session cookie + CSRF same-origin; **sem Sanctum** (Etapa C Opção A) |
| Extratos | Disk `statements` → `storage/app/private/statements`; `serve=false` (`config/filesystems.php`) |
| Rate limit | `RATE_LIMIT_LOGIN_PER_EMAIL=5`, `RATE_LIMIT_LOGIN_PER_IP=20`, `RATE_LIMIT_UPLOAD_PER_USER=10` (`AppServiceProvider`) |

### Checklist rápido pós-SSL

1. Cadeado válido em `https://aura.vonluqi.com` — **OK** (2026-09-29): CN=`aura.vonluqi.com`, Issuer=Let's Encrypt `YR1`, válido até **2026-12-26**.
2. HTTP→HTTPS: `http://aura.vonluqi.com` → **301** `https://…` (AutoSSL/host + `public/.htaccess` force HTTPS).
3. HSTS: **não** enviado (MVP — opcional pós-estabilização).
4. DevTools → cookie de sessão: **Secure** + **HttpOnly** (confirmar `SESSION_SECURE_COOKIE=true` no `.env` — probe ainda sem flag Secure).
5. `GET /.env` e paths sob `storage/app/private/` → 404/403/406.
6. Login + upload: extrato **não** acessível por URL pública.
7. Forçar erro com `APP_DEBUG=false` → JSON genérico (sem SQLSTATE/paths).

## Validação final live (§7)

### HTTPS e shell SPA (§7.1) — 2026-09-29

| Check | Evidência |
|-------|-----------|
| `https://aura.vonluqi.com` / `/login` | **200** `text/html`; shell Vite referencia `app-CKWowohf.js` + `app-XBQZuNTe.css` |
| Mixed content | Assets same-origin `/build/...` e `/fonts/...` sob HTTPS |
| HTTP→HTTPS | `http://aura.vonluqi.com/` → **301** `https://aura.vonluqi.com/` |
| SSL | Let's Encrypt YR1, CN=`aura.vonluqi.com`, válido até **2026-12-26** (§1.4) |
| Brand / title | Blade `title` = `config('app.name')` Aura; UI `Login · Aura` + tagline “Inteligência invisível, controle absoluto.” |
| Vite assets | `/build/manifest.json` **200** JSON; CSS/JS **200** |
| Poppins | CSS `@font-face` Poppins; `/fonts/poppins/latin-400-normal.woff2` → **200** `font/woff2` |
| Console JS | Assets 200 (sem 404 crítico); smoke browser final no operador se desejado |

### Segurança básica HTTP (§7.2) — 2026-09-29

| Check | Evidência |
|-------|-----------|
| `GET /.env` | **406** (não 200) — §1.3.1 |
| Path traversal / `composer.json` | Docroot = `aura/public` apenas; raiz Laravel fora do web root |
| `storage/app/private/statements/` | Disk `serve=false`; sem `storage:link`; não listável por URL pública |
| API erro `APP_DEBUG=false` | `bootstrap/app.php` sanitiza `QueryException` (sem SQLSTATE/paths); `GET /api/user` → **401** `{"message":"Unauthenticated."}` |
### Auth / sessão / CSRF (§7.3) — 2026-09-29

| Check | Evidência |
|-------|-----------|
| `GET /api/csrf-cookie` | **204** + XSRF-TOKEN + aura_session |
| Login falho | POST + CSRF → **401** Credenciais inválidas. (sem revelar e-mail); toast no LoginForm |
| Login OK | Contrato: controller → **200** {user}; GuestRoute → /dashboard (operador com ADMIN_*) |
| Cookie flags | Live: HttpOnly + SameSite=Lax; **Secure ausente** no probe — SESSION_SECURE_COOKIE=true + config:cache no host |
| F5 / sessão | Driver database; regenerate no login |
| Deep link /upload | ProtectedRoute → /login com state.from; LoginForm restaura path |
| Logout | destroy invalida sessão; UI → /login; /api/user **401** |
| Rate limit | throttle:login + handlers 429 (smoke agressivo evitado) |
### Upload Nubank (§7.4)

Smoke autenticado (browser) + contratos já cobertos por testes/código:

| Check | Evidência |
|-------|-----------|
| Abrir `/upload` | Rota protegida (`ProtectedRoute`); operador logado |
| Rejeitar `.exe` / `.pdf` / >10 MB | `validators.js` + Vitest; Dropzone `accept` / `maxSize` |
| Fixture CSV | `tests/Fixtures/statements/nubank/sample_account.csv` |
| API **201** + summary | Feature `UploadStatementEndpointTest`; UI `UploadSummaryCard` (`rows_imported` / `rows_skipped`) |
| Não público | Disk `statements` `serve=false`; path `storage/app/private/statements/...` |
| Reupload skips | Feature reupload duplicate hashes; summary mostra skips |
| OFX opcional | `tests/Fixtures/statements/ofx/sample_nubank.ofx` + feature OFX happy path |

**Operador live:** login → Upload → CSV fixture → confirmar summary → tentar URL pública do arquivo (deve falhar) → reupload → FileZilla path privado.

### Dashboard (§7.5)

| Check | Evidência |
|-------|-----------|
| Cards income/expense/balance/count | `MetricCards` + Feature `DashboardAnalyticsEndpointTest` / acceptance SQL match |
| Presets de período | `PeriodPills` + `useDashboardFilters`; API `from`/`to` |
| Entradas / Saídas | `TypePills`; filter `type` altera cards/series (Feature) |
| Categoria / busca / paginação | `FilterBar` / `SearchField` / `Pagination` + `IndexTransactions*` tests |
| Gráficos recharts | `EvolutionChart` + `CategoryChart` no `DashboardPage` |
| Empty state | Só quando sem dados; com dados Feature retorna cards/series preenchidos |

**Operador live:** após upload (§7.4) validar números/filtros/gráficos no browser.
### Performance / limites (§7.6) — 2026-09-29

| Check | Evidência |
|-------|-----------|
| TTFB shared | `/` ~0.18s; `/login` ~0.15s; `/build/manifest.json` ~0.13s (<< 3s) |
| Upload ≤ 10 MB | App max 10 MB; `public/.user.ini` `upload_max_filesize/post_max_size=20M` (folga vs 413) |
| `/api/analytics/dashboard` | Guest → **401** genérico (não **500**); auth Feature tests cobrem 200 |
### Observabilidade (§7.7)

| Item | Regra / evidência |
|------|-------------------|
| `storage/logs/laravel.log` | `LOG_LEVEL=error` + `display_errors=Off`; inspecionar via FileZilla/Terminal — sem spam DEBUG |
| Scheduler | Cron redireciona para `storage/logs/scheduler.log` (§6.3); após cron ativo, linhas de `schedule:run` |
| Diagnose CI | `diagnose-hostgator-500`, `fix-hostgator-500`, `fix-hostgator-403` — `workflow_dispatch`; usar com cautela (one-shots / chmod) |
## Backup e rollback (§8)

### Backup inicial pós-go-live (§8.1)

Obrigatório quando a produção estiver estável (após §7).

| Artefato | Ação |
|----------|------|
| Banco | phpMyAdmin → `luca9682_aura` → Export → SQL completo → `aura-prod-YYYYMMDD-HHMM.sql` |
| Cópias | Offline + cofre cifrado — **nunca** em `public/` nem Git |
| `.env` | Copiar `/home4/luca9682/aura/.env` só para o cofre |
| Uploads | Arquivar `storage/app/private/statements/` se houver extratos |
| Snapshot opcional | Pasta `aura/` (`vendor` opcional — re-baixável via CI) |

**Registro:**

| Campo | Valor |
|-------|-------|
| Data/hora (UTC-3) | _preencher após export_ |
| Arquivo SQL | `aura-prod-YYYYMMDD-HHMM.sql` |
| Local offline / cofre | _preencher_ |
| Incluiu statements? | sim / não |
| Incluiu .env no cofre? | sim / não |
### Backups contínuos MVP (§8.2)

| Regra | Detalhe |
|-------|---------|
| Frequência mínima | Export MySQL **semanal** (phpMyAdmin manual; ou cron + `mysqldump` se shell OK) |
| Retenção | Manter ≥ **2** gerações (ex.: `aura-prod-semana-N.sql` + N-1) |
| Local | Offline / cofre — **nunca** dentro de `public/` nem no Git |
| Extra | Antes de migration arriscada: backup fresco (§2.4 / §8.1), além do semanal |
### Rollback de código (§8.3.1)

Deploy ruim após push em `main`:

1. Identificar commit/tag estável anterior no GitHub.
2. **Opção A (preferida):** `git revert <bad>` + `git push origin main` → redeploy CI.
3. **Opção B:** se não houver workflow de commit específico, usar revert (não force-push em `main`).
4. Após redeploy: re-extrair `vendor.tar.gz` se `composer.lock` mudou (compatível com o commit restaurado).
5. `config:cache` / `route:cache` / `view:cache` (§4.6).
6. **Não** restaurar `.env` a partir do Git (não versionado) — só do cofre (§8.1) se corrompido.
### Rollback de migration (§8.3.2)

1. Preferir **forward-fix** (nova migration) em prod — evitar `migrate:rollback`.
2. Se rollback inevitável e shell OK (após backup SQL fresco §8.1/§8.2):

``bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php
$PHP_BIN artisan migrate:rollback --step=1 --force
``

3. Validar app: login + dashboard (§7).
4. Sem shell: avaliar restore SQL (§8.3.3) em vez de rollback artisan.
### Rollback de dados — desastre (§8.3.3)

1. Manutenção opcional: `php artisan down` (shell) ou desligar subdomínio temporariamente no cPanel.
2. phpMyAdmin → `luca9682_aura` → Import do SQL de §8.1/§8.2.
3. Restaurar `storage/app/private/statements/` do arquivo, se necessário.
4. `php artisan up` (ou reativar subdomínio).
5. Revalidar §7 (HTTPS, login, upload, dashboard).
### Rollback de credenciais / incidente (§8.3.4)

1. Rotacionar senhas: cPanel / FTP / MySQL (`luca9682_vonluqi`) / `ADMIN_PASSWORD`.
2. Atualizar `.env` live + Secrets GitHub (`FTP_PASSWORD`, etc.) + `config:cache`.
3. Invalidar sessões: truncate tabela `sessions` (phpMyAdmin) se necessário.
4. Regenerar `APP_KEY` **somente** se comprometida (`key:generate --force`) — invalida cookies cifrados; planejar downtime breve.
5. Re-seed admin se senha admin mudou: `AdminUserSeeder` (§5.3).
6. Validar login + §7 smoke.
### Critérios para abortar go-live (§8.3.5)

Abortar / não declarar Etapa E done se qualquer item for verdadeiro:

| Critério | Ação típica |
|----------|-------------|
| SSL inválido ou HTTP only | Corrigir AutoSSL / force HTTPS (§1.4) antes de go-live |
| `APP_DEBUG=true` acidental | `false` + `config:cache`; investigar vazamento |
| `.env` ou `vendor` ausentes | Restaurar `.env` do cofre; extract `vendor.tar.gz` / redeploy |
| Login admin impossível | Seed controlado (§5.3); checar `ADMIN_*` / DB |
| Upload grava em path público | Verificar disk `statements` + sem `storage:link` |
| Document root = raiz Laravel | Reapontar para `aura/public` (§0.1 / §1.3) |
## DoD Etapa E (§9) — 2026-09-29

Etapa E **concluída** conforme `PLAN_ETAPA_E.md` §9. MVP live: `https://aura.vonluqi.com`.

**Residuais operador (não bloqueiam documentação DoD):**

1. Confirmar cookie `Secure`: `SESSION_SECURE_COOKIE=true` no `.env` live + `config:cache`.
2. Preencher tabela Registro do backup §8.1 após export real.
3. Confirmar Cron Job criado no cPanel com a linha canônica §6.3.
## Fora de escopo Etapa E (§10)

Não fazer no MVP HostGator: CDN/Cloudflare full, Redis/Horizon, blue-green, multi-servidor, APM Datadog/New Relic, WAF avançado, Sanctum Bearer, registro público/multi-tenant, PWA/mobile.
## Referência rápida (§12)

``text
Laravel root:     /home4/luca9682/aura
Document root:    /home4/luca9682/aura/public
Env file:         /home4/luca9682/aura/.env
Private uploads:  /home4/luca9682/aura/storage/app/private/statements
URL:              https://aura.vonluqi.com
PHP:              /opt/cpanel/ea-php82/root/usr/bin/php
``

Cron:

``bash
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home4/luca9682/aura/artisan schedule:run >> /home4/luca9682/aura/storage/logs/scheduler.log 2>&1
``

Env crítico: `APP_ENV=production` · `APP_DEBUG=false` · `APP_URL=https://aura.vonluqi.com` · `SESSION_SECURE_COOKIE=true`

Workflows: Deploy HostGator (push main) · Migrate HostGator (manual) · diagnose/fix-hostgator-* (incidentes)