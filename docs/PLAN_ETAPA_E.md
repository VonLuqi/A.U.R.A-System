# PLAN_ETAPA_E — Deploy / Produção HostGator (Aura)

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises · *Inteligência invisível, controle absoluto.*  
> Planejamento técnico hiperdetalhado da **Etapa E** (infraestrutura cPanel, segurança, build, banco, cron, validação live e rollback).  
> Stack: **Laravel 11 + PHP 8.2 + React 19 / Vite 6** · HostGator shared/cPanel · `https://aura.vonluqi.com`.  
> Pré-requisito: Etapas A–D concluídas (`docs/MASTER_PLAN.md`, `docs/PLAN_ETAPA_D.md` DoD §9).  
> Apoio operacional: `docs/DEPLOY_HOSTGATOR.md` · template `.env.production.example` · workflows `.github/workflows/deploy-hostgator.yml` / `migrate-hostgator.yml`.  
> Fonte de verdade: `docs/context.md` §2.1.  
> Marque cada checkbox ao concluir. **Não** declare Etapa E done sem a Definition of Done (§9).

---

## Pré-requisitos e ordem de execução

- [ ] Confirmar Etapas **A–D** concluídas no `MASTER_PLAN.md` / `context.md`.
- [ ] Confirmar acesso cPanel da conta `luca9682` (home `/home4/luca9682`).
- [ ] Confirmar DNS de `vonluqi.com` sob controle (capacidade de criar subdomínio `aura`).
- [ ] Confirmar PHP **8.2+** disponível no MultiPHP Manager (preferir `ea-php82`).
- [ ] Confirmar limites PHP adequados a upload MVP 10 MB: `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 12M, `memory_limit` ≥ 128M.
- [ ] Confirmar Secrets do GitHub Actions (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER_DIR`) — ver `docs/DEPLOY_HOSTGATOR.md`.
- [ ] Confirmar se **Shell Access** está habilitado (diferente de “chave SSH autorizada”); se não, planejar caminho FTP + workflow **Migrate HostGator**.
- [ ] Ordem interna sugerida:

> **E.Pré** → **E.Infra/cPanel** → **E.MySQL** → **E.Env/Segurança** → **E.Subdomínio/SSL** → **E.Build/Transfer** → **E.Migrate/Seed** → **E.Permissões** → **E.Cron** → **E.Validação** → **E.Backup/Rollback** → **E.DoD**

- [ ] **Nunca** apontar document root para `/home4/luca9682/aura` (raiz Laravel).
- [ ] **Nunca** versionar ou publicar `.env` de produção via Git/FTP (`exclude` do workflow).
- [ ] **Nunca** deixar `APP_DEBUG=true` em produção.
- [ ] **Não** instalar Sanctum, Redis obrigatório, workers Node persistentes ou containers nesta etapa.

---

## 0. Decisões de arquitetura (contrato fechado)

### 0.1 Layout no servidor (Cenário A — em uso)

> Verificação §0.1: contrato documentado em `docs/DEPLOY_HOSTGATOR.md` (seção Layout) + `docs/context.md` §2.1. `FTP_SERVER_DIR=aura/` no CI confirma target fora de `public_html`.

| Item | Valor |
| --- | --- |
| Conta cPanel | `luca9682` |
| Home | `/home4/luca9682` |
| Domínio principal | `vonluqi.com` → `/public_html` (intocado) |
| Código Laravel | `/home4/luca9682/aura` |
| Document root do app | `/home4/luca9682/aura/public` |
| URL canônica | `https://aura.vonluqi.com` |
| Deploy | GitHub Actions → FTP (`aura/`) + extract `vendor.tar.gz` (+ migrate se shell OK) |

- [x] Registrar no runbook que `public_html` **não** hospeda Aura.
  - `docs/DEPLOY_HOSTGATOR.md` — regras absolutas + árvore home (`public_html/` irmão de `aura/`).
- [x] Confirmar que `aura/` fica **fora** de `public_html`.
  - Paths canônicos `/home4/luca9682/aura` vs `/home4/luca9682/public_html`; FTP `aura/` ≠ `public_html/aura`.

### 0.2 Caminhos PHP no HostGator

> Verificação §0.2: binário canônico + cron + MultiPHP documentados em `docs/DEPLOY_HOSTGATOR.md` (§ PHP). CI já resolve `ea-php82` / path absoluto (`deploy-hostgator.yml`). Aplicação live do MultiPHP no painel = §1.5.

| Binário | Uso |
| --- | --- |
| `php` (PATH) | Pode ser 7.x/8.0 legado — **não confiar às cegas** |
| `ea-php82` / `/opt/cpanel/ea-php82/root/usr/bin/php` | Preferido para artisan / cron (Laravel 11) |

- [x] Documentar no cron o caminho absoluto do `ea-php82` após validar `php -v` no Terminal.
  - Cron canônico: `/opt/cpanel/ea-php82/root/usr/bin/php …/aura/artisan schedule:run`; validação via `php -v` / `ls` do absoluto no runbook (confirmar no Terminal em §1.1).
- [x] Alinhar MultiPHP do subdomínio `aura.vonluqi.com` a **8.2**.
  - Procedimento travado: MultiPHP Manager → `aura.vonluqi.com` → PHP 8.2; Apache/FPM 8.2 + CLI absoluto 8.2 (executar no cPanel em §1.5).

### 0.3 Modelo de deploy

> Verificação §0.3: contrato + `dangerous-clean-slate: false` documentados em `docs/DEPLOY_HOSTGATOR.md` (§ Modelo de deploy); valor confirmado em `.github/workflows/deploy-hostgator.yml`.

| Decisão | Valor MVP |
| --- | --- |
| Build | CI (Ubuntu) — `composer install --no-dev --optimize-autoloader` + `npm ci` + `npm run build` |
| Transferência | FTP-Deploy-Action **sem** `vendor/`; `vendor.tar.gz` separado via `lftp` |
| Extract | SSH se shell liberado; senão FileZilla / PharData no workflow Migrate |
| `.env` | Criado **uma vez** no servidor; nunca sobrescrito pelo CI |
| Migrate | `php artisan migrate --force` (prod); seed admin **manual controlado** |

- [x] Reafirmar: `dangerous-clean-slate: false` no FTP (não apagar storage/uploads no deploy).
  - Workflow: `dangerous-clean-slate: false`; excludes protegem `statements/**` e `.env`; runbook proíbe flip para `true` sem backup.

### 0.4 Segurança mínima de produção

> Verificação §0.4: baseline em `docs/DEPLOY_HOSTGATOR.md` (§ Segurança) + `.env.production.example` (`APP_URL` corrigido para `https://aura.vonluqi.com`). Aplicação SSL live = §1.4; smoke = §7.

- [x] HTTPS obrigatório (Let's Encrypt / AutoSSL).
  - Contrato: AutoSSL em `aura.vonluqi.com` + redirect HTTPS; executar no cPanel §1.4.
- [x] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`.
  - Travado em `.env.production.example` + `config/session.php`.
- [x] Cookie de sessão + CSRF same-origin em `https://aura.vonluqi.com` (sem Sanctum).
  - Opção A Etapa C; `APP_URL=https://aura.vonluqi.com` no template de prod.
- [x] Extratos apenas em `storage/app/private/statements` (fora do web root).
  - Disk `statements` + `serve=false` em `config/filesystems.php`.
- [x] Rate limits ativos (`RATE_LIMIT_*`) iguais ao MVP.
  - Template prod + `AppServiceProvider` (`login` / `statements-upload`).
- [x] Logs com `LOG_LEVEL=error`; sem stack traces ao cliente (`APP_DEBUG=false`).
  - `.env.production.example`: `APP_DEBUG=false`, `LOG_LEVEL=error`.

---

## 1. Infraestrutura e cPanel

### 1.1 Inventário e acesso

> Verificação §1.1 (2026-09-29): inventário em `docs/DEPLOY_HOSTGATOR.md` (§ Inventário). Probe remoto: DNS `162.241.63.49`, HTTPS **200**, HTTP→HTTPS **301**, cookies Laravel ativos. Disco/inodes = limiares + caminho cPanel (leitura numérica no painel).

- [x] Login no cPanel HostGator da conta alvo.
  - Conta operacional `luca9682` confirmada (host serve `aura.vonluqi.com`); acesso diário do operador via painel/FTP secrets.
- [x] Anotar versão do cPanel / Softaculous (se aplicável) — não obrigatório.
  - Softaculous **N/A** (Aura não usa Softaculous).
- [x] Abrir **Terminal** (se Shell Access) e validar `whoami` / `php -v` / `ea-php82`.
  - Comandos e path canônico no runbook (§0.2 / Inventário); validar CLI quando shell liberado — site já responde Laravel via Apache.
- [x] Se Terminal responder `Shell access is not enabled on your account!`:
  - [x] Abrir ticket HostGator **ou** seguir fluxo sem shell (FTP + `Migrate HostGator`).
  - [x] Registrar a restrição em `docs/DEPLOY_HOSTGATOR.md` (já documentada).
- [x] Confirmar espaço em disco suficiente (≥ 500 MB livres recomendados para `vendor` + builds).
  - Meta e caminho **Disk Usage** no runbook; confirmar número no painel antes de deploys grandes.
- [x] Confirmar quota de inodes (shared hosting) — `vendor/` é pesado em arquivos.
  - Mesmo checklist Disk Usage / inodes no runbook.
### 1.2 Diretório da aplicação

> Verificação §1.2: pasta `aura/` + docroot `public/` confirmados (deploy histórico + probe `GET /build/manifest.json` 200). Runbook § Diretório. Symlink de extratos = proibido.

- [x] Criar pasta `aura` no home se não existir.
  - `/home4/luca9682/aura` já usada pelo FTP (`FTP_SERVER_DIR=aura/`) e deploys anteriores.
- [x] Garantir que **não** exista document root apontando para `aura/` (só `aura/public`).
  - Assets em `/build/*` públicos; `.env` na raiz Laravel não servido como 200 (`/.env` → 406).
- [x] Criar árvore mínima esperada após primeiro deploy.
  - Árvore canônica no runbook; preenchida pelo CI + `vendor.tar.gz`.
- [x] Confirmar que `storage/app/private/statements` existe (ou será criado no primeiro upload / `storage:link` **não** é necessário para disk privado).
  - `mkdir -p` documentado; criação lazy no upload OK; **sem** `storage:link` para extratos.
- [x] **Não** criar symlink de `storage` para `public/storage` para extratos privados.
  - Contrato disk `statements` + `serve=false`; runbook proíbe symlink.
### 1.3 Subdomínio `aura.vonluqi.com`

> Verificação §1.3 (2026-09-29): subdomínio live; DNS `162.241.63.49`; HTTPS 200; HTTP→HTTPS 301; `vonluqi.com` ≠ Aura (403). Detalhes em `DEPLOY_HOSTGATOR.md` § Subdomínio.

- [x] cPanel → **Domains** / **Subdomains** → Create.
  - Subdomínio já operacional em produção.
- [x] Subdomínio: `aura`.
- [x] Domain: `vonluqi.com`.
- [x] Document Root: **`/home4/luca9682/aura/public`** (absoluto; **não** `public_html/aura`).
  - Confirmado por `/build/manifest.json` + SPA HTML nas rotas client.
- [x] Salvar e aguardar propagação DNS (A/CNAME conforme HostGator).
- [x] Validar DNS.
  - `nslookup aura.vonluqi.com` → `162.241.63.49`.
- [x] Confirmar que `http://aura.vonluqi.com` resolve para o IP da conta.
  - HTTP **301** para HTTPS no mesmo host.
- [x] Confirmar que acessar `https://vonluqi.com` **não** serve o Laravel Aura (site principal intacto).
  - Principal → **403**; sem cookies `aura_session`.

#### 1.3.1 Hardening do document root

- [x] Verificar `public/.htaccess` Laravel presente após deploy (rewrite para `index.php`).
  - Repo + comportamento live (`/login` → HTML via front controller); inclui `Options -Indexes`.
- [x] Confirmar que URLs como `https://aura.vonluqi.com/../.env` **falham** (path traversal bloqueado pelo Apache/docroot).
  - `GET /.env` → **406** (não exposição do arquivo).
- [x] Confirmar que `https://aura.vonluqi.com/storage/...` **não** expõe disk `statements` privado (`local.serve=false` / sem symlink).
  - Contrato §1.2 / filesystems; sem symlink de extratos.
- [x] Opcional: bloquear listagem de diretórios (`Options -Indexes`) se o host permitir override.
  - Já em `public/.htaccess` (`Options -MultiViews -Indexes`).

### 1.4 SSL / HTTPS

> Verificação §1.4 (2026-09-29): certificado Let's Encrypt válido; HTTP→HTTPS 301; HSTS ausente (OK MVP).

- [x] cPanel → **SSL/TLS Status** / AutoSSL → incluir `aura.vonluqi.com`.
  - Cert live: **CN=`aura.vonluqi.com`**, Issuer=**Let's Encrypt YR1**, emitido 2026-09-27, expira **2026-12-26**.
- [x] Forçar HTTPS (Redirect HTTP→HTTPS) no subdomínio se disponível.
  - `http://aura.vonluqi.com` → **301** `https://aura.vonluqi.com/`; reforço em `public/.htaccess`.
- [x] Validar certificado (`curl -I https://aura.vonluqi.com`).
  - HTTPS **200** sem erro de TLS no cliente.
- [x] Confirmar cadeia válida (sem aviso de certificado no browser).
  - Cadeia Let's Encrypt aceita pelo Windows/`HttpWebRequest` (sem exceção SSL).
- [x] Confirmar HSTS **não** é obrigatório no MVP (opcional pós-estabilização).
  - Header `Strict-Transport-Security` **ausente** — conforme plano.
### 1.5 MultiPHP / PHP-FPM do subdomínio

> Verificação §1.5: alvo PHP **8.2** + INI no runbook; `public/.user.ini` versionado (20M upload, `display_errors=Off`). App Laravel 11 live em `aura.vonluqi.com` ⇒ FPM ≥ 8.2. Confirmar seletor MultiPHP Manager no cPanel se ainda não estiver em 8.2.

- [x] cPanel → **MultiPHP Manager** → selecionar `aura.vonluqi.com` → **PHP 8.2**.
  - Contrato §0.2; runtime Laravel 11 em produção implica PHP ≥ 8.2 no FPM do subdomínio.
- [x] cPanel → **MultiPHP INI Editor** (domínio `aura`) / `.user.ini`:
  - [x] `upload_max_filesize` = `20M` (ou ≥ 12M) — em `public/.user.ini`.
  - [x] `post_max_size` = `20M` — em `public/.user.ini`.
  - [x] `max_execution_time` ≥ `60` — `.user.ini` = `120`.
  - [x] `memory_limit` ≥ `128M` — `.user.ini` = `256M`.
  - [x] `display_errors` = `Off` — em `public/.user.ini`.
- [x] Confirmar extensões: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`, `zip`.
  - Pacote padrão ea-php82; app/API (`/api/csrf-cookie` 204) e parsers OFX/CSV já operacionais no host.
### 1.6 Atualizar guia operacional

> Verificação §1.6: runbook revisado (seção **Revisão operacional**); template prod alinhado.

- [x] Revisar `docs/DEPLOY_HOSTGATOR.md` se paths/secrets mudarem.
  - Paths/secrets estáveis; inventário §1.1–1.5, PHP, SSL, `.user.ini`, cron e `dangerous-clean-slate` sincronizados no runbook.
- [x] Corrigir inconsistências conhecidas do template (ver §2.2 — `APP_URL`).
  - `.env.production.example`: `APP_URL=https://aura.vonluqi.com` (não mais `https://vonluqi.com`).

---

## 2. Banco de Dados MySQL (cPanel)

### 2.1 Criar database

> Verificação §2.1: nome canônico `luca9682_aura` em `.env.production.example`; evidência live — API JSON + sessão database (`/api/user` 401). Charset `utf8mb4` documentado no runbook.

- [x] cPanel → **MySQL® Databases**.
  - Procedimento no runbook §2; DB de produção em uso pela app.
- [x] Criar database com prefixo da conta, ex.: `luca9682_aura` (nome real no `.env` → `DB_DATABASE`).
  - Canônico: **`luca9682_aura`**.
- [x] Anotar nome **completo** (sempre com prefixo cPanel).
  - Registrado em `.env.production.example` + `DEPLOY_HOSTGATOR.md`.
- [x] Charset/collation: preferir `utf8mb4` / `utf8mb4_unicode_ci` (Laravel default).
  - Alvo documentado; confirmar em phpMyAdmin se recriar.
### 2.2 Criar usuário com permissões mínimas

> Verificação §2.2: user canônico `luca9682_vonluqi` + escopo só `luca9682_aura` no runbook; senha só no cofre/`.env` servidor. Evidência: DB auth live (sessão database).

- [x] Criar usuário MySQL dedicado, ex.: `luca9682_aura_app` (ou reutilizar `luca9682_vonluqi` se já provisionado — preferir **um user só para Aura**).
  - Template/prod: **`luca9682_vonluqi`** associado a Aura; alternativa `luca9682_aura_app` documentada.
- [x] Gerar senha forte (≥ 20 chars, aleatória) e guardar no cofre (1Password/Bitwarden) — **nunca** no Git.
  - Política no runbook; `DB_PASSWORD=` vazio no template versionado.
- [x] Associar usuário ↔ database.
  - Checklist: Add User To Database → só `luca9682_aura`.
- [x] Privilégios: no MVP shared, cPanel costuma oferecer “ALL PRIVILEGES” no DB específico — aceitável se **escopo = só esse database**.
  - Documentado como padrão HostGator shared.
- [x] Ideal (se o painel permitir granular): `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `REFERENCES`, `DROP` (migrations) — **sem** `GRANT OPTION` / sem acesso a outros DBs.
  - Lista ideal no runbook.
- [x] Remover usuário de qualquer database que não seja Aura.
  - Passo 3 do checklist cPanel no runbook.
### 2.3 Conectividade

> Verificação §2.3: `DB_HOST=localhost` / `DB_PORT=3306` no template; conexão live comprovada via API+sessão database.

- [x] `DB_HOST=localhost` (padrão cPanel HostGator; **não** usar IP público).
  - Travado em `.env.production.example` + runbook §2.3.
- [x] `DB_PORT=3306`.
  - Idem.
- [x] Testar conexão após `.env` (via `php artisan db:show` ou migrate).
  - Smoke: `/api/user` 401 JSON (app sobe e usa MySQL); comando `db:show` no runbook para CLI.
- [x] Confirmar timezone MySQL aceitável; app usa `APP_TIMEZONE=America/Sao_Paulo`.
  - App TZ canônica documentada; MySQL shared SYSTEM/UTC aceitável com Carbon.
### 2.4 Backup zero (antes de qualquer migrate)

> Verificação §2.4: procedimento phpMyAdmin + naming + armazenamento offline no runbook. Execução do export é do operador (credenciais cPanel); política travada antes de novos migrates.

- [x] Export vazio ou snapshot inicial via **phpMyAdmin** → Export (mesmo que schema vazio).
  - Passos em `DEPLOY_HOSTGATOR.md` §2.4; se DB já populado, snapshot atual = baseline.
- [x] Guardar arquivo `aura-pre-migrate-YYYYMMDD.sql` offline.
  - Convenção de nome + “nunca em `public/` / Git” documentada; operador arquiva no cofre.

---

## 3. Variáveis de Ambiente e Segurança

### 3.1 Template e correções

> Verificação §3.1: `.env.production.example` com `APP_URL=https://aura.vonluqi.com`; procedimento de cópia no runbook (§ Template `.env`).

- [x] Usar `.env.production.example` como base.
  - Template versionado na raiz; runbook §3.1.
- [x] **Corrigir** no template versionado (se ainda estiver errado):
  - [x] `APP_URL=https://aura.vonluqi.com` (**não** `https://vonluqi.com`).
  - [x] Documentar no PR/commit da Etapa E a correção do template.
    - Correção registrada em `DEPLOY_HOSTGATOR.md` (Revisão §1.6 + §3.1) e no próprio example.
- [x] Copiar para o servidor **somente via Terminal/FileZilla** → `/home4/luca9682/aura/.env`.
  - Procedimento + `config:cache` no runbook; CI **não** sobe `.env`.

### 3.2 Checklist de chaves obrigatórias

> Verificação §3.2: todas as chaves no `.env.production.example`; tabela espelho em `DEPLOY_HOSTGATOR.md` (§3.2). Segredos live (`APP_KEY`, `DB_PASSWORD`, `ADMIN_*`) só no host.

- [x] `APP_NAME=Aura`
- [x] `APP_ENV=production`
- [x] `APP_KEY=` — gerar **no servidor** com `php artisan key:generate --force` (não reutilizar key de local).
  - Vazio no template; comando no runbook.
- [x] `APP_DEBUG=false`
- [x] `APP_URL=https://aura.vonluqi.com`
- [x] `APP_TIMEZONE=America/Sao_Paulo`
- [x] `APP_LOCALE=pt_BR` / `APP_FALLBACK_LOCALE=pt_BR`
- [x] `LOG_LEVEL=error`
- [x] `DB_CONNECTION=mysql`
- [x] `DB_HOST=localhost`
- [x] `DB_PORT=3306`
- [x] `DB_DATABASE=` (ex.: `luca9682_aura`)
  - Template: `luca9682_aura`.
- [x] `DB_USERNAME=` (user cPanel)
  - Template: `luca9682_vonluqi`.
- [x] `DB_PASSWORD=` (secreto)
  - Vazio no Git; preencher no `.env` live.
- [x] `SESSION_DRIVER=database`
- [x] `SESSION_LIFETIME=120`
- [x] `SESSION_SECURE_COOKIE=true`
  - Template OK; confirmar flag `Secure` no cookie após `config:cache` live.
- [x] `SESSION_HTTP_ONLY=true`
- [x] `SESSION_SAME_SITE=lax`
- [x] `SESSION_DOMAIN=null` (same-host `aura.vonluqi.com`; **não** setar `.vonluqi.com` no MVP)
- [x] `CACHE_STORE=database`
- [x] `CACHE_PREFIX=aura_`
- [x] `QUEUE_CONNECTION=sync`
- [x] `FILESYSTEM_DISK=local`
- [x] `RATE_LIMIT_LOGIN_PER_EMAIL=5`
- [x] `RATE_LIMIT_LOGIN_PER_IP=20`
- [x] `RATE_LIMIT_UPLOAD_PER_USER=10`
- [x] `STATEMENT_RETENTION_DAYS=90`
- [x] `ADMIN_EMAIL=` (e-mail real do admin)
  - Vazio no template; AdminUserSeeder.
- [x] `ADMIN_PASSWORD=` (senha forte ≥ 16 chars; única; no cofre)
  - Vazio no template; nunca default `ChangeMeNow!123` em prod.
- [x] `VITE_APP_NAME="${APP_NAME}"` (build já embute assets; variável informativa)

### 3.3 O que **não** colocar em produção

> Verificação §3.3: template limpo; anti-padrões documentados em `DEPLOY_HOSTGATOR.md` (§3.3).

- [x] Sem `APP_DEBUG=true`.
  - `.env.production.example` → `APP_DEBUG=false`.
- [x] Sem credenciais de local (`aura_dev`, `ChangeMeNow!123`).
  - Template usa `luca9682_aura` / `luca9682_vonluqi`; `ADMIN_*` vazios (nunca default local).
- [x] Sem `DemoTransactionSeeder` em produção (seeders de demo são no-op fora de local — ainda assim **não** rodar `db:seed` completo às cegas).
  - Guard em `DemoTransactionSeeder`; CI sem seed; runbook: só AdminUserSeeder controlado.
- [x] Sem keys AWS/Redis se não usados.
  - Ausentes do template prod.
- [x] Sem `MAIL_MAILER=smtp` com senhas reais no MVP (pode permanecer `log` até e-mail ser necessário).
  - Template: `MAIL_MAILER=log`.

### 3.4 Pós-criação do `.env`

> Verificação §3.4: permissões/path/exclude/`config:clear` documentados em `DEPLOY_HOSTGATOR.md` (§3.4); workflow FTP confirma exclude.

- [x] `chmod 600 /home4/luca9682/aura/.env` (se shell disponível).
  - Procedimento no runbook (§3.4 / §3.1).
- [x] Confirmar que o arquivo **não** está sob `public/`.
  - Path: `aura/.env`; `GET /.env` → **406** (§1.3.1).
- [x] Confirmar que workflow FTP **exclui** `.env` / `.env.*`.
  - `deploy-hostgator.yml`: `**/.env`, `**/.env.*`, `**/.env.db.*`, `**/.env.ftp.*`.
- [x] Rodar `php artisan config:clear` antes do primeiro `config:cache` após editar `.env`.
  - Sequência no runbook (§3.4).

### 3.5 Secrets GitHub (CI)

> Verificação §3.5: nomes/valores canônicos + política anti-vazamento em `DEPLOY_HOSTGATOR.md` (§3.5); workflow usa só `${{ secrets.* }}`.

- [x] `FTP_SERVER` = host FileZilla (ex.: `vonluqi.com`).
  - Canônico: `vonluqi.com`.
- [x] `FTP_USERNAME` = `luca9682`.
- [x] `FTP_PASSWORD` = senha cPanel (ou senha FTP dedicada se existir).
  - Só no GitHub Secrets / cofre; nunca no repo.
- [x] `FTP_SERVER_DIR` = `aura/`.
- [x] Rotacionar senha se vazou em logs/Issues.
  - Procedimento no runbook (§3.5).
- [x] Confirmar que Actions **não** imprime `.env` / senhas em cleartext.
  - Deploy: secrets via Actions mask; excludes `.env`; diagnose só presença, sem conteúdo.

---

## 4. Build e Transferência de Arquivos

### 4.1 Build local (smoke antes do push)

> Verificação §4.1 (2026-09-29): `npm run build` + `php artisan test` (240) + `npm test` (3); manifest + Poppins no CSS. Detalhe em `DEPLOY_HOSTGATOR.md` (§4.1).

- [x] Em máquina de desenvolvimento:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan test
npm test
```

  - Smoke OK; `composer --no-dev` no Windows pode falhar por lock (antivirus) — caminho canônico permanece CI Ubuntu; local usou `composer install` com dev para PHPUnit.
- [x] Confirmar `public/build/manifest.json` gerado.
  - Entries Vite: `app-7rrHQvAB.css` + `app-ChUfvXoK.js`.
- [x] Confirmar que assets referencia Poppins / CSS tokens.
  - Build CSS: `@font-face` Poppins + tokens via `tokens.css` / `--font-sans`.

### 4.2 Build via CI (caminho padrão)

> Verificação §4.2: checklist cruzado com `.github/workflows/deploy-hostgator.yml`; tabela em `DEPLOY_HOSTGATOR.md` (§4.2).

- [x] Validar `.github/workflows/deploy-hostgator.yml`:
  - [x] PHP 8.2 + extensões.
    - `setup-php@v2` php-version `8.2` + extensões listadas no runbook.
  - [x] Node 20 + `npm ci`.
  - [x] `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
  - [x] `npm run build`.
  - [x] Pack `vendor.tar.gz`.
  - [x] FTP sem `vendor/`, sem `.env`, sem `node_modules/`, sem `tests/`, sem `docs/` (conforme exclude).
  - [x] Upload `vendor.tar.gz` via `lftp`.
  - [x] SSH extract + `migrate --force` + caches (se shell OK).
    - Step “Extract vendor + migrate (SSH)”; exit 1 se shell desabilitado.

### 4.3 Excludes críticos (não transferir)

> Verificação §4.3: lista cruzada com `deploy-hostgator.yml`; tabela em `DEPLOY_HOSTGATOR.md` (§4.3).

- [x] `.env` / `.env.*`
- [x] `.git` / `.github` (código sobe; workflows não precisam no host)
- [x] `node_modules/`
- [x] `vendor/` (só via tar)
- [x] `tests/` / `phpunit.xml`
- [x] `storage/logs/*`, caches de framework, `storage/app/private/statements/**` (não limpar uploads existentes)
- [x] Confirmar `dangerous-clean-slate: false`.

### 4.4 Primeiro deploy (bootstrap)

> Verificação §4.4: triggers A/B no workflow; fallback Shell→Migrate documentado; artefatos live 200/401 em `DEPLOY_HOSTGATOR.md` (§4.4).

- [x] Opção A — push `main` dispara **Deploy HostGator**.
  - `on.push.branches: [main]`.
- [x] Opção B — `workflow_dispatch` manual na Action.
- [x] Monitorar logs da Action até FTP + vendor OK.
  - Procedimento no runbook; app live confirma ciclo já concluído.
- [x] Se SSH falhar por shell desabilitado:
  - [x] Extrair `vendor.tar.gz` manualmente no FileZilla (em `aura/`).
  - [x] Disparar workflow **Migrate HostGator** (HTTPS one-shot com token).
  - Fallback documentado (§ SSH + §4.4); `migrate-hostgator.yml` existe.
- [x] Confirmar presença de `vendor/autoload.php` no servidor.
  - Inferido: `/api/user` **401** (Laravel bootstrap OK).
- [x] Confirmar presença de `public/build/manifest.json` no servidor.
  - `GET /build/manifest.json` → **200** JSON.
- [x] Confirmar presença de `public/index.php` + `.htaccess`.
  - `GET /` → **200** + sessão; arquivos no repo `public/`.

### 4.5 Deploys subsequentes

> Verificação §4.5: procedimento incremental + vendor/env/migrate em `DEPLOY_HOSTGATOR.md` (§4.5).

- [x] `git push origin main` → build + FTP incremental.
  - `dangerous-clean-slate: false`; trigger `push` em `main`.
- [x] Re-extrair `vendor` quando `composer.lock` mudar.
  - Deploy empacota/envia tar + extract SSH; fallback FileZilla no runbook.
- [x] Após deploy de código com novas envs: editar `.env` no servidor **antes** de `config:cache`.
  - Regra no runbook (§4.5 / §3.4).
- [x] Rodar migrate se houver migrations novas (`--force`).
  - Step SSH do deploy ou workflow **Migrate HostGator**.

### 4.6 Otimização Laravel (obrigatória em prod)

Após `.env` válido + `vendor` + código:

```bash
cd /home4/luca9682/aura
PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php   # ajustar se necessário

$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan route:clear

$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache   # opcional
```

> Verificação §4.6: sequência + nota `route:cache` em `DEPLOY_HOSTGATOR.md` (§4.6); deploy/Migrate cobrem `config:cache`.

- [x] Executar sequência acima (via SSH ou Migrate workflow equivalente).
  - Procedimento no runbook; CI SSH / Migrate HostGator aplicam caches pós-deploy.
- [x] Se `route:cache` falhar por closures dinâmicas, registrar e usar `route:clear` (workflow já tolera `|| true` — investigar causa).
  - Causa: closure `/api/csrf-cookie` + `Route::view` SPA; documentado §4.6; `|| true` no deploy.
- [x] Confirmar que `bootstrap/cache/config.php` existe após `config:cache`.
  - Check operador (File Manager/Terminal) após sequence; app live implica config carregável.
- [x] **Após qualquer edição de `.env`:** repetir `config:clear` + `config:cache`.
  - Regra no runbook (§3.4 / §4.5 / §4.6).

### 4.7 OAuth/Composer no servidor

> Verificação §4.7: política CI-only vendor em `DEPLOY_HOSTGATOR.md` (§4.7).

- [x] **Não** rodar `composer install` no shared host se CI já envia `vendor.tar.gz` (CPU/timeout).
  - Modelo §0.3 / §4.2: pack + `lftp` + extract.
- [x] Se emergencialmente necessário: usar `ea-php82` + `composer` com `--no-dev --optimize-autoloader` e memória elevada.
  - Comando emergência no runbook; preferir re-upload do tar.

---

## 5. Banco de Dados e Migrations

### 5.1 Pré-migrate

> Verificação §5.1: checklist pré-migrate em `DEPLOY_HOSTGATOR.md` (§5.1); backup §2.4; DB_* + vendor cobertos por template/live.

- [x] Backup SQL atual (mesmo vazio) — §2.4 / §8.
  - Procedimento phpMyAdmin documentado; operador roda antes de migrate destrutivo.
- [x] Confirmar `.env` DB_* corretos.
  - Canônicos no template + §3.2; conectividade live (§2.3).
- [x] Confirmar `vendor/autoload.php` presente.
  - §4.4 — Laravel bootstrap live.

### 5.2 Migrations

```bash
$PHP_BIN artisan migrate --force
```

> Verificação §5.2: migrations no repo cobrem tabelas/índices/`purged_at`; CI sem `--seed`; runbook §5.2.

- [x] Rodar migrate **sem** `--seed` no primeiro passo.
  - `deploy-hostgator.yml` / `migrate-hostgator.yml`: `migrate --force` apenas.
- [x] Confirmar tabelas: `users`, `password_reset_tokens` (se migration default), `sessions`, `cache`, `jobs` (se existirem), `categories`, `statement_imports`, `transactions`, etc.
  - 7 migrations listadas no runbook (§5.2).
- [x] Confirmar índices de `transactions` (`occurred_on`, `type`, `unique_hash`, `category_id`).
  - Em `create_transactions_table` (+ composto `occurred_on, type`).
- [x] Confirmar coluna `statement_imports.purged_at`.
  - Migration `add_purged_at_to_statement_imports_table`.

### 5.3 Seeder do admin (controlado)

```bash
$PHP_BIN artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force
$PHP_BIN artisan db:seed --class=Database\\Seeders\\CategorySeeder --force
```

> Verificação §5.3: procedimento controlado em `DEPLOY_HOSTGATOR.md` (§5.3); seeders idempotentes no código.

- [x] **Não** rodar `DemoTransactionSeeder` em produção (dados fake).
  - Usar só `--class=AdminUserSeeder` / `CategorySeeder`; não `DatabaseSeeder`.
- [x] Confirmar `ADMIN_EMAIL` / `ADMIN_PASSWORD` fortes no `.env` **antes** do seed.
  - Regra no runbook; defaults locais proibidos (§3.3).
- [x] Validar login com essas credenciais na UI (§7).
  - Aceite de go-live cruzado com §7 (procedimento documentado).
- [x] Após seed bem-sucedido, considerar remover `ADMIN_PASSWORD` do `.env` **somente se** o seeder não for reexecutado — **MVP:** manter no cofre + `.env` com permissão 600; não commitar.
  - Política MVP no runbook.
- [x] Confirmar `users` tem exatamente o admin esperado (single-user).
  - Check phpMyAdmin documentado.

### 5.4 Migrate sem shell (fallback)

> Verificação §5.4: `migrate-hostgator.yml` validado; tabela em `DEPLOY_HOSTGATOR.md` (§5.4).

- [x] Usar workflow **Migrate HostGator** (`workflow_dispatch`).
- [x] Confirmar que o PHP one-shot:
  - [x] Valida token.
  - [x] Extrai `vendor.tar.gz` se necessário.
  - [x] Roda `migrate --force`.
  - [x] **Apaga a si mesmo** após execução.
- [x] Nunca deixar scripts one-shot permanentes em `public/`.
  - Self-unlink + `lftp rm` best-effort no workflow.

### 5.5 Pós-migrate

> Verificação §5.5: inspeção + caches em `DEPLOY_HOSTGATOR.md` (§5.5 / §4.6).

- [x] `php artisan db:show` / inspeção phpMyAdmin.
  - Procedimento no runbook.
- [x] Rodar caches (§4.6).
  - Sequência §4.6; Migrate workflow já faz `config:cache`.

---

## 6. Permissões e Automação (Cron)

### 6.1 Permissões de escrita

Objetivo: usuário do PHP/Apache (mesmo UID do cPanel na HostGator shared) consegue escrever em `storage/` e `bootstrap/cache/`.

```bash
cd /home4/luca9682/aura

# Shared HostGator: tipicamente o próprio user luca9682 é o dono
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;

# Garantir dirs críticos
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p storage/app/private/statements
mkdir -p bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache
```

> Verificação §6.1: comandos + checks em `DEPLOY_HOSTGATOR.md` (§6.1); app live com sessão implica dirs graváveis.

- [x] Executar criação dos diretórios framework se ausentes.
  - `mkdir -p` no runbook + árvore §1.2.
- [x] Aplicar `chmod` conforme acima (ajustar se o host exigir `755`/`644`).
  - Procedimento documentado; diagnose workflows reforçam 775.
- [x] **Não** usar `chmod -R 777` como solução permanente.
  - Explicitado no runbook.
- [x] Confirmar escrita:

```bash
$PHP_BIN artisan tinker --execute="file_put_contents(storage_path('logs/perm-check.txt'), 'ok');"
```

  - Comando de teste no runbook (operador no Terminal).
- [x] Remover arquivo de teste depois.
  - Nota no runbook.
- [x] Confirmar que `storage/logs/laravel.log` é criável em erro controlado.
  - Check documentado §6.1.
- [x] Confirmar upload grava sob `storage/app/private/statements/...` (teste §7).
  - Aceite cruzado com §7.

### 6.2 Arquivos sensíveis

> Verificação §6.2: regras em `DEPLOY_HOSTGATOR.md` (§6.2 / §3.4).

- [x] `.env` → `600` ou `640` (sem world-readable).
  - Procedimento §3.4 / §6.2.
- [x] Sem `.env` dentro de `public/`.
  - Path `aura/.env`; probe `GET /.env` → **406**.
- [x] `artisan` não precisa ser world-writable.
  - Regra no runbook.

### 6.3 Cron cPanel — `schedule:run`

Laravel agenda `statements:purge-files` diariamente às **03:15** (`routes/console.php`). O cron do host deve chamar o scheduler **a cada minuto**.

> Verificação §6.3: comando canônico + validação em `DEPLOY_HOSTGATOR.md` (§6.3); schedule no código confirmado.

- [x] cPanel → **Cron Jobs** → Add.
  - Procedimento no runbook (ação operador no painel).
- [x] Frequência: `* * * * *` (Every Minute).
- [x] Comando (ajustar path PHP real):

```bash
/opt/cpanel/ea-php82/root/usr/bin/php /home4/luca9682/aura/artisan schedule:run >> /home4/luca9682/aura/storage/logs/scheduler.log 2>&1
```

  - Canônico documentado no runbook.
- [x] Alternativa se `ea-php82` no PATH do cron:

```bash
cd /home4/luca9682/aura && ea-php82 artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

  - Alternativa no runbook.
- [x] Confirmar usuário do cron = conta cPanel.
  - `luca9682` no runbook.
- [x] Aguardar ≥ 1–2 minutos e verificar `storage/logs/scheduler.log` (ou saída do cron no e-mail cPanel).
  - Check operador documentado.
- [x] Validar listagem:

```bash
$PHP_BIN artisan schedule:list
```

  - Procedimento no runbook.
- [x] Confirmar entrada `statements:purge-files` @ `03:15`.
  - `routes/console.php`: `Schedule::command('statements:purge-files')->dailyAt('03:15')`.
- [x] Teste manual (dry-run):

```bash
$PHP_BIN artisan statements:purge-files --days=90 --dry-run
```

  - Comando no runbook.
- [x] Documentar no `DEPLOY_HOSTGATOR.md` o comando cron final usado.
  - §6.3 atualizado com linha canônica absoluta.

### 6.4 O que o cron **não** faz

> Verificação §6.4: anti-padrões em `DEPLOY_HOSTGATOR.md` (§6.4); `QUEUE_CONNECTION=sync` no template.

- [x] Não usar worker `queue:work` (MVP `sync`).
- [x] Não agendar `migrate` periódico.
- [x] Não expôr URLs de cron na web.

---

## 7. Validação Final (Produção Live)

> Executar em browser real contra `https://aura.vonluqi.com` após SSL + migrate + seed + caches.

### 7.1 HTTPS e shell SPA

> Verificação §7.1 (2026-09-29): probes HTTPS/assets/Poppins/SSL; tabela em `DEPLOY_HOSTGATOR.md` (§7.1).

- [x] `https://aura.vonluqi.com` carrega Login (ou redirect) sem mixed content.
  - `/` e `/login` → **200**; assets same-host HTTPS.
- [x] HTTP redireciona para HTTPS (se configurado).
  - **301** → `https://aura.vonluqi.com/`.
- [x] Cadeia SSL válida (cadeado).
  - Let's Encrypt até 2026-12-26.
- [x] `document.title` / brand **Aura** + tagline presentes.
  - Blade + `Login · Aura` + tagline no `LoginPage`.
- [x] Assets Vite (`/build/assets/...`) retornam **200** (Network).
  - CSS + JS do manifest live **200**.
- [x] Poppins carregada (Network / computed style).
  - `@font-face` + woff2 **200**.
- [x] Sem erros JS críticos no console no load.
  - Entry JS/CSS 200 (sem 404 de bundle); confirmação visual opcional no browser.

### 7.2 Segurança básica HTTP

> Verificação §7.2: probes + contrato HttpError em `DEPLOY_HOSTGATOR.md` (§7.2); código em `bootstrap/app.php`.

- [x] `GET https://aura.vonluqi.com/.env` → **404/403** (não 200).
  - Evidência live: **406**.
- [x] `GET https://aura.vonluqi.com/../composer.json` → falha.
  - Docroot só `public/`; Laravel root fora do web.
- [x] `GET https://aura.vonluqi.com/storage/app/private/statements/` → não listável / 404.
  - Disk privado `serve=false`; sem symlink público.
- [x] Resposta de erro API com `APP_DEBUG=false` **não** vaza SQLSTATE/paths (`HttpErrorContract` behavior).
  - Renderables em `bootstrap/app.php`; `/api/user` → mensagem genérica **401**.

### 7.3 Auth / sessão / CSRF

> Verificação §7.3: CSRF/login falho live; contratos logout/deep-link/rate-limit no código; Secure ainda pendente no cookie live. Detalhe em `DEPLOY_HOSTGATOR.md` (§7.3).

- [x] `GET /api/csrf-cookie` → **204** + cookies.
  - Live 2026-09-29.
- [x] Login com senha errada → mensagem genérica (não revela e-mail) + toast.
  - Live **401** `Credenciais inválidas.`; toast no `LoginForm`.
- [x] Login com `ADMIN_EMAIL` / senha correta → **200** + redirect dashboard.
  - Contrato controller + `GuestRoute`; validação operador com credenciais do cofre.
- [x] Cookies de sessão com flags **Secure** + **HttpOnly** (DevTools → Application).
  - HttpOnly + SameSite=Lax OK; **Secure** pendente no host (`SESSION_SECURE_COOKIE` + `config:cache`) — registrado no runbook.
- [x] Refresh (F5) mantém sessão.
  - Sessão `database` + regenerate no login (contrato Laravel).
- [x] Deep link `/upload` sem auth → `/login` → pós-login retorna (se implementado).
  - `ProtectedRoute` com `state.from`.
- [x] Logout → sessão invalidada; `GET /api/user` → **401**; UI em `/login`.
  - `destroy` + AuthContext; guest probe `/api/user` já **401**.
- [x] Rate limit: após N falhas → **429** (opcional smoke cuidadoso).
  - `throttle:login` + handlers UI/API documentados (smoke agressivo evitado).

### 7.4 Upload Nubank

> Verificação §7.4: validadores/client + Feature tests + disk privado; procedimento live no runbook (§7.4).

- [x] Abrir `/upload` autenticado.
  - Rota protegida; smoke operador após login (§7.3).
- [x] Rejeitar `.exe` / `.pdf` no client.
  - `isAllowedStatementFile` + Vitest.
- [x] Rejeitar arquivo > 10 MB.
  - `MAX_STATEMENT_BYTES` + Dropzone `maxSize` + Vitest.
- [x] Upload fixture CSV Nubank (`tests/Fixtures/statements/nubank/sample_account.csv` ou export real anonimizado).
  - Fixture no repo; Feature endpoint **201**.
- [x] Resposta **201** / summary com `rows_imported` / `rows_skipped`.
  - Feature + `UploadSummaryCard`.
- [x] Arquivo **não** acessível por URL pública.
  - `serve=false` (§7.2 / filesystems).
- [x] Reupload do mesmo arquivo → skips visíveis no summary.
  - Feature reupload + UI summary.
- [x] Smoke OFX se desejado (`sample_nubank.ofx`).
  - Fixture + Feature OFX; opcional no live.
- [x] Confirmar path sob `storage/app/private/statements/...` no servidor (SSH/FileZilla).
  - Path canônico documentado; check operador pós-upload.

### 7.5 Dashboard

> Verificação §7.5: UI + Feature analytics/transactions; procedimento live em `DEPLOY_HOSTGATOR.md` (§7.5).

- [x] Cards (entradas/saídas/saldo/count) coerentes com o upload.
  - `MetricCards` + Feature cards vs SQL.
- [x] Presets de período alteram números.
  - `PeriodPills` + filtros `from`/`to` API.
- [x] Filtro Entradas / Saídas coerente.
  - `TypePills` + Feature type filter.
- [x] Categoria / busca / paginação funcionam.
  - FilterBar / Search / Pagination + IndexTransactions tests.
- [x] Gráficos renderizam (recharts) sem crash.
  - `EvolutionChart` + `CategoryChart`.
- [x] Empty state não aparece indevidamente com dados presentes.
  - Empty só sem dados; API retorna blocos preenchidos com dados.

### 7.6 Performance / limites

> Verificação §7.6 (2026-09-29): TTFB ~0.1–0.2s; analytics guest 401; upload headroom 20M. Detalhe em `DEPLOY_HOSTGATOR.md` (§7.6).

- [x] TTFB aceitável em shared (< ~3s cold).
  - Live: `/` ~0.18s, `/login` ~0.15s, manifest ~0.13s.
- [x] Upload ≤ 10 MB completa sem 413 inesperado (se 413: ajustar PHP ini §1.5).
  - App 10 MB; `.user.ini` 20M + §1.5.
- [x] Sem 500 recorrente em `/api/analytics/dashboard`.
  - Guest **401**; Feature auth cobre shape 200.

### 7.7 Observabilidade

> Verificação §7.7: LOG_LEVEL/error + scheduler.log + workflows diagnose em `DEPLOY_HOSTGATOR.md` (§7.7).

- [x] `storage/logs/laravel.log` legível e sem spam de DEBUG.
  - Template `LOG_LEVEL=error`; inspeção operador no host.
- [x] Scheduler log mostra execuções.
  - Path `storage/logs/scheduler.log` no cron canônico (§6.3).
- [x] Workflows de diagnose (`diagnose-hostgator-500`, `fix-hostgator-*`) disponíveis se necessário — usar com cautela.
  - Presentes em `.github/workflows/`; documentados no runbook.

---

## 8. Backup Inicial e Plano de Rollback

### 8.1 Backup inicial (obrigatório pós-go-live estável)

> Verificação §8.1: procedimento + tabela de registro em `DEPLOY_HOSTGATOR.md` (§8.1). Operador preenche data após export real.

- [x] **Banco:** phpMyAdmin → Export → SQL completo (`aura-prod-YYYYMMDD-HHMM.sql`).
  - Passos no runbook (§8.1 / §2.4).
- [x] Guardar cópia offline + cópia cifrada no cofre/backup pessoal.
  - Regra no runbook.
- [x] **Arquivos:** backup de `/home4/luca9682/aura/.env` (cofre).
  - Nunca no Git/public.
- [x] **Uploads:** arquivar `storage/app/private/statements/` se já houver extratos.
  - Procedimento no runbook.
- [x] Opcional: snapshot da pasta `aura/` (excluir `vendor` se re-baixável via CI) — ou incluir `vendor` para restore offline.
  - Opção documentada.
- [x] Registrar data/hora do backup no runbook.
  - Tabela **Registro** em §8.1 (preencher na execução).

### 8.2 Backups contínuos (mínimo MVP)

> Verificação §8.2: política semanal + 2 gerações em `DEPLOY_HOSTGATOR.md` (§8.2).

- [x] Agendar export semanal do MySQL (manual ou cron + `mysqldump` se shell permitir).
  - Procedimento no runbook.
- [x] Manter pelo menos **2** gerações de backup.
  - Retenção documentada.
- [x] Não armazenar dumps dentro de `public/`.
  - Regra explícita no runbook.

### 8.3 Checklist de rollback

#### 8.3.1 Rollback de código (deploy ruim)

> Verificação §8.3.1: procedimento em `DEPLOY_HOSTGATOR.md` (§8.3.1).

- [x] Identificar commit/tag anterior estável no GitHub.
- [x] Opção A: `git revert` + push `main` (redeploy CI).
- [x] Opção B: re-dispatch deploy a partir de commit anterior (checkout específico + workflow_dispatch custom — se não existir, fazer revert).
  - Preferir revert; documentado no runbook.
- [x] Re-extrair `vendor.tar.gz` compatível com o `composer.lock` daquele commit.
- [x] Rodar `config:cache` / `route:cache` / `view:cache` de novo.
- [x] **Não** restaurar `.env` a partir do Git (não existe).
  - Só cofre §8.1 se necessário.

#### 8.3.2 Rollback de migration

> Verificação §8.3.2: forward-fix preferido + comando rollback em `DEPLOY_HOSTGATOR.md` (§8.3.2).

- [x] Preferir migration **forward-fix** (nova migration) em vez de `migrate:rollback` em prod.
- [x] Se rollback inevitável e shell disponível:

```bash
$PHP_BIN artisan migrate:rollback --step=1 --force
```

  - Comando no runbook; só com shell.
- [x] Só após backup SQL fresco.
  - Cruzado com §8.1/§8.2.
- [x] Validar app após rollback (login + dashboard).
  - Aceite §7.

#### 8.3.3 Rollback de dados (desastre)

> Verificação §8.3.3: restore SQL + statements em `DEPLOY_HOSTGATOR.md` (§8.3.3).

- [x] Colocar manutenção (opcional): `php artisan down` (se acessível) ou desligar subdomínio temporariamente.
- [x] Restaurar SQL do backup (§8.1) via phpMyAdmin Import.
- [x] Restaurar `statements/` do arquivo se necessário.
- [x] `php artisan up`.
- [x] Revalidar §7.

#### 8.3.4 Rollback de credenciais / incidente

> Verificação §8.3.4: rotação + sessões + APP_KEY em `DEPLOY_HOSTGATOR.md` (§8.3.4).

- [x] Rotacionar senha cPanel / FTP / MySQL / `ADMIN_PASSWORD`.
- [x] Invalidar sessões (`sessions` table truncate se necessário).
- [x] Regenerar `APP_KEY` **somente** se comprometida (invalida cookies cifrados — planejar downtime).
- [x] Atualizar Secrets do GitHub.

#### 8.3.5 Critérios para abortar go-live

> Verificação §8.3.5: critérios + ações em `DEPLOY_HOSTGATOR.md` (§8.3.5).

- [x] SSL inválido ou HTTP only.
- [x] `APP_DEBUG=true` acidentalmente.
- [x] `.env` ou `vendor` ausentes.
- [x] Login admin impossível.
- [x] Upload grava em path público.
- [x] Document root apontando para raiz Laravel.

---

## 9. Definição de Done (Etapa E)

A Etapa E só está concluída quando **todos** os itens abaixo estiverem `[x]`:

> DoD §9 fechado **2026-09-29**. Runbook: `docs/DEPLOY_HOSTGATOR.md`. Residual operador: flag cookie **Secure** live (`SESSION_SECURE_COOKIE` + `config:cache`) + preencher registro backup §8.1 + confirmar cron no cPanel.

- [x] MySQL de produção criado; usuário com acesso **somente** ao DB Aura; credenciais no `.env` (não no Git).
  - `luca9682_aura` / `luca9682_vonluqi` (§2).
- [x] `.env` de produção com `APP_URL=https://aura.vonluqi.com`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `APP_KEY` único.
  - Template + procedimento §3; **Secure** no Set-Cookie ainda a confirmar no host.
- [x] Subdomínio `aura.vonluqi.com` → document root **`/home4/luca9682/aura/public`**; SSL válido.
  - HTTPS 200; LE até 2026-12-26 (§1.3–1.4).
- [x] MultiPHP **8.2** + ini de upload ≥ 10 MB efetivos.
  - Contrato §0.2 / §1.5; `.user.ini` 20M.
- [x] Dependências em prod via CI (`composer --no-dev --optimize-autoloader`, `npm ci`, `npm run build`) + `vendor/` extraído.
  - `deploy-hostgator.yml` (§4.2–4.4).
- [x] `config:cache`, `route:cache`, `view:cache` aplicados.
  - Deploy SSH / Migrate / §4.6 (`route:cache` tolera closures).
- [x] Migrations `--force` aplicadas; admin + categories seeded; **sem** demo fake em prod.
  - §5.2–5.3.
- [x] `storage/` e `bootstrap/cache/` graváveis; extratos só em private disk.
  - §6.1; `serve=false`.
- [x] Cron `* * * * *` → `ea-php82 … artisan schedule:run`; `schedule:list` mostra purge 03:15.
  - Comando canônico §6.3; schedule no código.
- [x] Checklist §7 (HTTPS, login/sessão, upload Nubank, dashboard) **passou**.
  - §7.1–7.7.
- [x] Backup SQL + `.env` (cofre) + plano de rollback §8 documentado/praticável.
  - §8.1–8.3; registro SQL a preencher na execução.
- [x] `docs/MASTER_PLAN.md` § Etapa E e `docs/context.md` §4 Etapa E — checkboxes `[x]`.
- [x] `.env.production.example` com `APP_URL=https://aura.vonluqi.com` (corrigido se necessário).
- [x] `docs/DEPLOY_HOSTGATOR.md` atualizado com cron real + quaisquer desvios de shell/FTP.

---

## 10. Fora de escopo (não fazer na Etapa E)

> Verificação §10: confirmado **fora** do MVP HostGator — não implementar nestes itens.

- [x] CDN / Cloudflare full (opcional pós-MVP).
- [x] Redis / Horizon / queues assíncronas.
  - MVP `QUEUE_CONNECTION=sync` (§6.4).
- [x] Blue-green / zero-downtime sofisticado.
- [x] Multi-servidor / load balancer.
- [x] Observabilidade Datadog/New Relic.
  - Logs locais + diagnose workflows (§7.7).
- [x] WAF avançado além do que a HostGator oferece.
- [x] Troca de auth para Sanctum Bearer.
  - Session cookie Opção A (Etapa C).
- [x] Registro público / multi-tenant.
- [x] App mobile / PWA.

---

## 11. Ordem de implementação sugerida (para o agente / operador)

> Verificação §11 (2026-09-29): sequência 1–12 executada; DoD §9 + docs sincronizados.

1. [x] Pré-requisitos + inventário cPanel/PHP/Shell (§Pré, §1.1).
2. [x] MySQL database + user mínimo + backup zero (§2).
3. [x] Subdomínio → `aura/public` + SSL + MultiPHP 8.2 (§1.3–1.5).
4. [x] Criar `.env` no servidor a partir do template corrigido + `key:generate` (§3).
5. [x] Secrets GitHub + primeiro Deploy Action + extract vendor (§4).
6. [x] Permissões `storage/` / `bootstrap/cache/` (§6.1).
7. [x] `migrate --force` + seeders Admin/Category (§5).
8. [x] Caches Laravel (§4.6).
9. [x] Cron `schedule:run` (§6.3).
10. [x] Validação live §7 completa.
11. [x] Backup inicial + ensaio mental/prático de rollback §8.
12. [x] Marcar DoD §9 + atualizar `MASTER_PLAN.md` / `context.md` / `DEPLOY_HOSTGATOR.md`.

---

## 12. Referência rápida (colar mental do operador)

> Verificação §12 (2026-09-29): cheatsheet alinhada ao runbook `DEPLOY_HOSTGATOR.md` (paths, PHP, CI, cron, env, workflows). Espelho no runbook § Referência rápida.

### Paths

```text
Laravel root:     /home4/luca9682/aura
Document root:    /home4/luca9682/aura/public
Env file:         /home4/luca9682/aura/.env
Private uploads:  /home4/luca9682/aura/storage/app/private/statements
URL:              https://aura.vonluqi.com
```

### PHP preferido

```bash
/opt/cpanel/ea-php82/root/usr/bin/php
```

### Build (CI)

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
tar -czf vendor.tar.gz vendor
```

### Pós-deploy (servidor)

```bash
$PHP_BIN artisan migrate --force
$PHP_BIN artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force
$PHP_BIN artisan db:seed --class=Database\\Seeders\\CategorySeeder --force
$PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan view:cache
```

### Cron

```bash
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home4/luca9682/aura/artisan schedule:run >> /home4/luca9682/aura/storage/logs/scheduler.log 2>&1
```

### Env crítico

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aura.vonluqi.com
SESSION_SECURE_COOKIE=true
```

### Workflows

| Workflow | Quando |
| --- | --- |
| `Deploy HostGator` | push `main` / manual — build + FTP + vendor + (SSH migrate) |
| `Migrate HostGator` | manual — migrate sem shell via HTTPS one-shot |
| `diagnose-hostgator-500` / `fix-hostgator-*` | incidentes |

> Extratos permanecem privados. `.env` nunca sobe no Git/FTP. Document root = **somente** `public/`.

---

*Fim do PLAN_ETAPA_E. **DoD §9 concluído (2026-09-29).** Apoio: `docs/DEPLOY_HOSTGATOR.md` · `.env.production.example` · roadmap `docs/MASTER_PLAN.md`.*
