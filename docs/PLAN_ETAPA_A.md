# PLAN_ETAPA_A — Setup do Projeto (Aura)

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises · *Inteligência invisível, controle absoluto.*  
> Planejamento técnico hiperdetalhado da **Etapa A** (infraestrutura e bootstrap).
> Stack: **Laravel 11 + React + Vite** · HostGator · `vonluqi.com`.
> Referências: `docs/context.md` · `docs/DESIGN-SYSTEM.MD` · `docs/MASTER_PLAN.md`.
> Marque cada checkbox ao concluir. Não avance para a Etapa B sem a seção de validação final.

---

## Pré-requisitos (antes de qualquer comando)

- [ ] Confirmar sistema operacional de desenvolvimento (Windows / macOS / Linux) e anotar versões alvo:
  - PHP **8.2+** (preferencial 8.3)
  - Composer **2.x**
  - Node.js **20 LTS** (ou 18+)
  - npm **10+** (ou pnpm/yarn, se padronizado — default: **npm**)
  - MySQL **8** ou MariaDB **10.6+**
  - Git **2.x**
- [ ] Garantir que a pasta do repositório é `d:\Projetos\ControleFinanceiroPessoal` (ou equivalente) e que `docs/` já contém `context.md`, `DESIGN-SYSTEM.MD` e `MASTER_PLAN.md`.
- [x] Decidir estratégia de criação do Laravel em diretório **não vazio** (já existe `docs/`):
  - [x] Opção recomendada: criar em pasta temporária e mover arquivos para a raiz.
  - [ ] Alternativa: `composer create-project` com flags adequadas / merge manual — documentar a escolhida no PR/commit.

---

## 1. Configurar ambiente local (PHP, Composer, Node, MySQL)

### 1.1 PHP

- [x] Instalar PHP 8.2+ (Windows: [php.net](https://windows.php.net/download/) / Scoop / Chocolatey; ou Laragon/XAMPP com PHP 8.2+).
  - Feito via **XAMPP** (`C:\xampp\php`) — PHP **8.2.12**; `C:\xampp\php` adicionado ao PATH do usuário.
- [x] Habilitar extensões obrigatórias do Laravel no `php.ini`:
  - [x] `extension=openssl` — já carregado no build XAMPP (linha permanece comentada para evitar warning de módulo duplicado)
  - [x] `extension=pdo_mysql`
  - [x] `extension=mbstring`
  - [x] `extension=tokenizer`
  - [x] `extension=xml`
  - [x] `extension=ctype`
  - [x] `extension=json`
  - [x] `extension=fileinfo`
  - [x] `extension=curl`
  - [x] `extension=zip` (Composer / uploads)
  - [x] `extension=gd` ou `imagick` (opcional nesta etapa)
- [x] Ajustar limites úteis para upload de extratos (alinhar depois com HostGator):
  - [x] `upload_max_filesize=20M`
  - [x] `post_max_size=25M`
  - [x] `max_execution_time=120`
- [x] Validar:
  - [x] `php -v` → 8.2+
  - [x] `php -m` → lista contém `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`

### 1.2 Composer

- [x] Instalar Composer 2 globalmente.
  - Composer **2.10.3** em `%LOCALAPPDATA%\Programs\Composer` (no PATH do usuário).
- [x] Validar: `composer -V`
- [ ] (Opcional) Configurar mirror/cache se a rede for lenta: `composer config -g repos.packagist composer https://packagist.org`

### 1.3 Node.js e npm

- [x] Instalar Node.js 20 LTS.
  - Já instalado: Node.js **v26.3.0** (`C:\Program Files\nodejs`) — atende o pré-requisito **18+** / alvo 20 LTS.
- [x] Validar: `node -v` e `npm -v`
  - npm **11.16.0** (atende **10+**).
- [ ] (Opcional) Habilitar corepack se for usar pnpm depois — **não obrigatório** para o MVP.

### 1.4 MySQL / MariaDB

- [x] Instalar MySQL 8 ou MariaDB localmente (serviço rodando).
  - Via **XAMPP**: MariaDB **10.4.32** (serviço `mysql` Running). Abaixo do alvo 10.6+, mas suficiente para o MVP local com Laravel; upgrade opcional depois.
  - `C:\xampp\mysql\bin` adicionado ao PATH do usuário.
- [x] Criar database e usuário de desenvolvimento:
  - [x] `CREATE DATABASE aura CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
  - [x] Criar usuário (ex.: `aura_dev`) com senha forte e `GRANT ALL` apenas nesse database.
- [x] Validar conexão: `mysql -u aura_dev -p -e "SHOW DATABASES;"`
- [x] Anotar host (`127.0.0.1`), porta (`3306`), database, user e password para o `.env`.
  - Credenciais em `.env.db.local` (gitignored): host `127.0.0.1`, porta `3306`, database `aura`, user `aura_dev`.

### 1.5 Ferramentas auxiliares

- [x] Confirmar Git instalado: `git --version`
  - Git **2.54.0.windows.1** (`C:\Program Files\Git\cmd`).
- [x] (Recomendado) Instalar cliente HTTP (curl / Postman / Insomnia) para testes futuros de API.
  - `curl` **8.21.0** nativo do Windows (`C:\Windows\System32\curl.exe`).
- [x] (Windows) Garantir que `php`, `composer`, `node`, `npm`, `mysql` estão no `PATH` do terminal usado pelo Cursor.
  - User PATH: `C:\xampp\php`, `%LOCALAPPDATA%\Programs\Composer`, `C:\xampp\mysql\bin`
  - Machine PATH: `C:\Program Files\nodejs`, `C:\Program Files\Git\cmd`
  - Todos resolvem neste terminal; abrir novo terminal no Cursor se algum comando antigo ainda falhar.

### 1.6 Critério de sucesso — ambiente local

- [x] Comandos abaixo executam sem erro:
  - [x] `php -v` → PHP **8.2.12**
  - [x] `composer -V` → Composer **2.10.3**
  - [x] `node -v` → **v26.3.0**
  - [x] `npm -v` → **11.16.0**
  - [x] `mysql --version` (ou equivalente) → MariaDB **10.4.32**
  - [x] Conexão MySQL ao database `aura` OK (`aura_dev@127.0.0.1` → `connection_ok`)

---

## 2. Inicializar repositório Git e `.gitignore`

### 2.1 Inicialização Git

- [x] Na raiz do projeto, se ainda não for um repo:
  - [x] `git init`
  - [x] `git branch -M main`
- [x] Confirmar: `git status` funciona.
  - Repo em `D:/Projetos/ControleFinanceiroPessoal/.git`, branch **main**, sem commits ainda.

### 2.2 Arquivo `.gitignore` (raiz)

- [x] Criar ou atualizar `.gitignore` na raiz do repositório com, no mínimo:
  - Conteúdo do plano aplicado; incluído também `.env.db.local`.
- [x] Garantir entradas explícitas para: `.env`, `vendor/`, `node_modules/`, uploads (`storage/app/private/statements/`).
- [x] Criar placeholders Gitkeep para pastas privadas:
  - [x] `storage/app/private/.gitignore` com `*` + `!.gitignore` + exceções `!statements/` e `!statements/.gitignore` (para o placeholder aninhado ser trackable)
  - [x] `storage/app/private/statements/.gitignore` com conteúdo `*` + `!.gitignore`

### 2.3 Primeiro commit de docs (se ainda não versionado)

- [x] `git add docs/ .gitignore .vscode/settings.json` (se aplicável)
  - Incluídos também os placeholders `storage/app/private/**/.gitignore`.
- [x] Commit inicial de documentação **somente se o usuário solicitar** (não commit automático sem pedido).
  - Solicitado via task 2.3.

### 2.4 Critério de sucesso — Git

- [x] `git check-ignore -v .env` reconhece `.env` (após existir).
  - `.gitignore:6:.env`
- [x] `git check-ignore -v vendor` e `node_modules` OK após instalação.
  - Regras confirmadas (`/vendor`, `/node_modules`); dirs ainda não existem (pré-Laravel).
- [x] Nenhum segredo ou upload aparece em `git status` como untracked a ser commitado.
  - Working tree limpa; `.env.db.local` listado apenas como ignored.

---

## 3. Criar aplicação Laravel 11 + configurar `.env.example`

### 3.1 Criar o skeleton Laravel 11

- [x] Na pasta pai ou na raiz, executar (diretório atual **não vazio** por causa de `docs/`):

```bash
composer create-project laravel/laravel tmp-laravel "11.*"
```

  - Estratégia: pasta temporária + move (recomendado). Composer bloqueou install por security advisories no Laravel 11; instalado com `audit.block-insecure=false` no projeto. Framework: **11.56.1**.
- [x] Mover o conteúdo de `tmp-laravel/` para a raiz do repositório **sem sobrescrever** `docs/`:
  - [x] Mover `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `vite.config.js`, `package.json`, etc.
  - [x] Mesclar `.gitignore` do Laravel com o `.gitignore` do projeto (manter regras de uploads/docs).
  - [x] Remover pasta vazia `tmp-laravel/`.
- [x] Alternativa Windows (PowerShell) — usada como base do merge.
- [x] Validar: `php artisan --version` → Laravel Framework **11.56.1**
  - Nota Windows: `bootstrap/cache` veio com atributo ReadOnly; removido para o Artisan funcionar.

### 3.2 Instalar dependências PHP

- [x] `composer install`
  - Lock file OK; `Nothing to install` (deps já presentes da 3.1). Autoload regenerado; packages discovered.
- [x] Confirmar pasta `vendor/` criada e ignorada pelo Git.
  - `vendor/autoload.php` existe; `git check-ignore -v vendor` → `.gitignore:2:/vendor`.

### 3.3 Arquivo `.env` local

- [x] Copiar ambiente:
  - [x] `copy .env.example .env` (Windows) ou `cp .env.example .env`
- [x] Gerar chave: `php artisan key:generate`
- [x] Validar presença de `APP_KEY=base64:...` no `.env`.
  - `.env` ignorado pelo Git (`!! .env`).

### 3.4 Configurar `.env.example` (template versionável)

- [x] Editar `.env.example` com valores **sem segredos reais**, alinhados ao projeto:
  - `APP_NAME="Aura"`, timezone `America/Sao_Paulo`, locale `pt_BR`, DB `aura` / `aura_dev`, `CACHE_PREFIX=aura_`, etc.
- [x] Espelhar as mesmas chaves no `.env` local com senha real do MySQL.
  - Senha a partir de `.env.db.local`; `APP_KEY` preservada.
- [x] Rodar (após MySQL OK): `php artisan migrate`
  - Migrations default Laravel 11 OK (`users`+`sessions`, `cache`, `jobs`).
  - Mantidos `SESSION_DRIVER=database` e `CACHE_STORE=database` (não foi necessário fallback para `file`).
  - `php artisan db:show` falha em `performance_schema` (user sem GRANT lá) — irrelevante; conexão/`migrate` no DB `aura` OK.

### 3.5 Ajustes iniciais de `config/`

- [x] Confirmar `config/app.php`: timezone `America/Sao_Paulo`, locale `pt_BR` (ou via `.env`).
  - Via `.env` + defaults em `config/app.php` atualizados (`Aura`, `America/Sao_Paulo`, `pt_BR`).
- [x] Confirmar `config/filesystems.php` terá disco privado na Etapa C; nesta etapa apenas garantir `local` e `public` padrão.
  - `local` → `storage/app/private`; `public` → `storage/app/public`. Disco dedicado de extratos fica para Etapa C.
- [x] Remover ou não commitiar qualquer `.env` real.
  - `.env` ignorado e não trackeado no Git.

### 3.6 Critério de sucesso — Laravel

- [x] `php artisan about` exibe app name e environment.
  - Application Name **Aura** · Environment **local** · Laravel **11.56.1** · Locale **pt_BR**.
- [x] `php artisan serve` sobe em `http://127.0.0.1:8000` e a página welcome do Laravel carrega.
  - HTTP **200**; welcome page OK (serve encerrado após o teste).
- [x] `.env` **não** está no Git; `.env.example` **está**.
  - `.env` ignorado/não trackeado; `.env.example` adicionado ao índice (commit junto com o restante do skeleton quando solicitado).

---

## 4. Configurar Vite + React no frontend (`resources/js`)

### 4.1 Dependências Node (package.json)

- [x] Na raiz: `npm install`
- [x] Instalar React e plugin Vite:
  - `react` / `react-dom` **19.3.0**
  - `@vitejs/plugin-react` **^4.7.0** (compatível com Vite 6 do Laravel; a v6 do plugin exige Vite 8)
- [ ] (Opcional nesta etapa, útil depois) `npm install -D @types/react @types/react-dom` se adotar TypeScript — **default MVP: JSX**.
- [x] Confirmar em `package.json`:
  - [x] `dependencies`: `react`, `react-dom`
  - [x] `devDependencies`: `vite`, `laravel-vite-plugin`, `@vitejs/plugin-react`
  - [x] Scripts: `"dev": "vite"`, `"build": "vite build"`

### 4.2 Atualizar `vite.config.js`

- [x] Editar `vite.config.js` na raiz para incluir `react()`, input `app.jsx` e ignore de `storage/app/private`.
- [x] Garantir que o `input` aponta para `resources/js/app.jsx` (não `app.js`).

### 4.3 Bootstrap e entrypoint React

- [x] Renomear/remover `resources/js/app.js` se existir; criar `resources/js/app.jsx`.
- [x] Atualizar `resources/js/bootstrap.js` (manter axios se vier do skeleton; configurar CSRF depois na Etapa D).
- [x] Se axios não estiver instalado: `npm install axios` — já presente no skeleton.
- [x] Criar estrutura mínima: `app.jsx`, `bootstrap.js`, `components/App.jsx`, `pages/Home.jsx`.
- [x] Conteúdo inicial de `resources/js/app.jsx`.
- [x] Conteúdo inicial de `resources/js/components/App.jsx`.

### 4.4 Blade host do SPA

- [x] Criar/atualizar `resources/views/app.blade.php`.
- [x] Atualizar `routes/web.php` para servir o shell (temporário até auth na Etapa C/D).
- [x] **Atenção:** catch-all SPA só após garantir que rotas de API (`/api/*`) não sejam engolidas — na Etapa C registrar API antes ou em `routes/api.php`.
  - Hoje `bootstrap/app.php` ainda não carrega `routes/api.php`; ao ativar API, registrar fora do catch-all web.

### 4.5 Remover assets padrão não usados

- [x] Remover scaffolding Vue/Alpine residual se existir e não for usado.
  - Removido `welcome.blade.php`; `tailwind.config.js` sem `.vue`, com `*.jsx`; cache de views limpo. Sem Vue/Alpine no projeto.
- [x] Garantir que não há referência a `resources/js/app.js` em `vite.config.js` ou Blade.
  - Apenas `app.jsx` em `vite.config.js` e `app.blade.php`.

### 4.6 Critério de sucesso — Vite + React

- [x] Terminal 1: `php artisan serve`
- [x] Terminal 2: `npm run dev`
- [x] Abrir `http://127.0.0.1:8000` e ver o texto do `App.jsx` (HMR ativo).
  - HTTP 200, `#app`, refs `@vite/client` / 5173; `App.jsx` servido pelo Vite com “Setup Etapa A”.
- [x] `npm run build` gera `public/build/manifest.json` sem erros.
  - Entries `resources/js/app.jsx` + `resources/css/app.css`.
- [x] Com build: parar Vite, servir só Artisan e validar assets compilados.
  - HTTP 200 com `/build/assets/...`, sem `@vite/client`.

---

## 5. Document root `public/` e regras Apache / `.htaccess` (HostGator)

### 5.1 Princípio

- [x] O document root de produção **deve** apontar para a pasta `public/` do Laravel (nunca para a raiz do projeto).
  - HostGator: domínio principal `vonluqi.com` fica preso em `/public_html` (não editável).
  - **Cenário A via subdomínio:** `aura.vonluqi.com` → Document Root = `/home4/luca9682/aura/public`.
  - App Laravel em `/home4/luca9682/aura` (fora do document root).
- [x] Arquivos sensíveis (`.env`, `app/`, `storage/`, `vendor/`) **fora** do document root ou inacessíveis via HTTP.
  - Ficam em `/home4/luca9682/aura/` (pai de `public/`); só `public/` é exposto pelo Apache.

### 5.2 `.htaccess` dentro de `public/` (padrão Laravel — manter)

- [x] Verificar/criar `public/.htaccess`:
  - Presente (Laravel 11 padrão). Inclui também handle de `X-XSRF-Token` além do Authorization / trailing slash / front controller.
- [x] Confirmar existência de `public/index.php` (front controller Laravel).
  - Presente; bootstrap via `vendor/autoload.php` + `bootstrap/app.php`.

### 5.3 Cenário HostGator A — Document Root apontando para `public/` (preferencial)

- [x] No cPanel → Domains → Document Root = `.../projeto/public`
  - Domínio principal `vonluqi.com` **não** permite alterar root (preso em `/public_html`).
  - **Cenário A via subdomínio:** `aura.vonluqi.com` → `/home4/luca9682/aura/public` (configurado no cPanel).
- [x] **Não** é necessário `.htaccess` na raiz do projeto para rewrite.
  - Sem `.htaccess` na raiz do Laravel; só `public/.htaccess`.
- [ ] Validar que `https://aura.vonluqi.com` carrega `public/index.php`.
  - Configuração do painel OK; resposta HTTP ainda **403** (permissões/`public` no servidor — em investigação; não bloqueia o restante da Etapa A).
- [x] Documentar no `docs/context.md` o caminho absoluto usado no painel.
  - Ver §2.1: `/home4/luca9682/aura/public`.

### 5.4 Cenário HostGator B — Apenas `public_html` (sem mudar document root)

- [x] **Não aplicável (N/A)** — adotado o **Cenário A via subdomínio** (`aura.vonluqi.com` → `/home4/luca9682/aura/public`).
  - Domínio principal permanece em `/public_html` sem hospedar o Laravel.
  - Opções B1/B2 e `.htaccess` na raiz do Laravel **não** serão usadas enquanto A estiver ativo.
- [x] **Preferir Cenário A**; usar B só se o painel não permitir alterar document root.
  - Cumprido: A viabilizado com subdomínio editável; B fica como fallback documentado no plano, sem implementação.

### 5.5 Segurança adicional Apache

- [x] Criar `public/storage` via `php artisan storage:link` quando houver uploads públicos (MVP: extratos são privados — link público **não** deve expor statements).
  - **MVP:** não criar `storage:link` ainda — não há assets públicos necessários.
  - `config/filesystems.php` `links` aponta só `public/storage` → `storage/app/public` (nunca `private/`).
  - Disco `local` (default) = `storage/app/private` (extratos); disco `public` = `storage/app/public`.
- [x] Garantir que não exista symlink/alias que exponha `storage/app/private`.
  - Sem `public/storage` no repo; placeholders em `storage/app/private/**` com gitignore (uploads não versionados).
  - Document root HostGator = apenas `aura/public` — `storage/` fica fora do web root.
- [x] Em produção HostGator, confirmar `mod_rewrite` habilitado (padrão cPanel).
  - cPanel/Apache HostGator habilita `mod_rewrite` por padrão; `public/.htaccess` usa `RewriteEngine On`.

### 5.6 Desenvolvimento local sem Apache

- [x] Usar `php artisan serve` (raiz já embute `public/`).
  - Validado: `php artisan serve --host=127.0.0.1 --port=8000` → HTTP **200** em `http://127.0.0.1:8000`.
  - Frontend: em outro terminal, `npm run dev` (HMR) ou usar assets de `npm run build`.
- [x] (Opcional) Configurar VirtualHost Apache/Laragon local — **não necessário**; XAMPP PHP CLI + `artisan serve` cobrem o MVP local.

### 5.7 Critério de sucesso — Apache / public

- [x] Checklist documentado no repo: qual cenário HostGator (A ou B) será usado.
  - **Cenário A** via subdomínio: `aura.vonluqi.com` → `/home4/luca9682/aura/public` (`docs/context.md` §2.1, `docs/DEPLOY_HOSTGATOR.md`). Cenário B = N/A.
- [x] Localmente, URL base resolve para a welcome/SPA sem expor `/vendor` ou `/.env`.
  - `php artisan serve`: `/` → SPA (`#app`); `/.env` e `/vendor/autoload.php` **não** devolvem segredos/código (catch-all serve o shell Blade, sem `APP_KEY`/`DB_PASSWORD`/autoload real).
- [x] Arquivos `public/.htaccess` e (se B) raiz `.htaccess` versionados.
  - `public/.htaccess` no Git; **sem** `.htaccess` na raiz (B não usado).

---

## 6. Instalar fonte Poppins e espelhar tokens CSS (`docs/DESIGN-SYSTEM.MD`)

### 6.1 Fonte Poppins

- [x] Escolher método de carga (um dos dois):
  - [ ] **A — Google Fonts (dev rápido):** link no `app.blade.php`
  - [x] **B — Self-host (melhor para produção/LGPD/latência):** baixar arquivos WOFF2 e servir em `public/fonts/`
- [x] Se self-host (B):
  - [x] Baixar pesos `400`, `500`, `600`, `700` (latin + latin-ext para pt-BR).
  - [x] Salvar em `public/fonts/poppins/`.
  - [x] Declarar `@font-face` em `resources/css/fonts.css` e importar em `app.css`.
  - Tailwind `fontFamily.sans` → Poppins.

### 6.2 Criar folha de tokens

- [x] Criar `resources/css/tokens.css` copiando os tokens de `docs/DESIGN-SYSTEM.MD` §4.6.
- [x] Atualizar `resources/css/app.css` (`@import` fonts + tokens, body/base, `.app-shell`).
- [x] Conferência cruzada: hex/radius/spacing idênticos ao DESIGN-SYSTEM §4.6.
- [x] Atualizar `App.jsx` para classes que usam tokens (já coberto pelo `body` / `.app-shell`).
  - `App.jsx` já usa `app-shell` + `tagline`.

### 6.3 Critério de sucesso — Design tokens

- [x] DevTools → Computed: `body` usa Poppins e background `#151716`.
  - Validado no CSS compilado (`public/build/assets/app-*.css`): `body{font-family:var(--font-sans);…background-color:var(--color-bg-default)}` com `--font-sans:"Poppins"…` e `--color-bg-default:#151716`.
- [x] Variáveis CSS `--color-brand-primary` etc. visíveis em `:root`.
  - `:root` no build inclui `--color-brand-primary:#DCCFFF` e demais tokens de §4.6.
- [x] Nenhum uso residual de fonte Inter/Roboto/system como primária.
  - Sem Inter/Roboto em `resources/`; Poppins é primária; `system-ui` só como fallback (design system).

---

## 7. Documentar variáveis de ambiente em `docs/context.md`

### 7.1 Seção nova ou atualização

- [x] Abrir `docs/context.md` e adicionar/atualizar seção **“Variáveis de ambiente”** (ex.: após Arquitetura).
  - Seção **§2.5 Variáveis de ambiente** criada; tabela alinhada a `.env.example`; `APP_URL` prod = `https://aura.vonluqi.com`.
- [x] Registrar decisão: `SESSION_DRIVER` / `CACHE_STORE` escolhidos na Etapa A.
  - Ambos = `database` (sem Redis no HostGator shared).
- [x] Registrar caminho HostGator do document root quando conhecido.
  - `/home4/luca9682/aura/public` (`aura.vonluqi.com`, Cenário A).
- [x] Atualizar `docs/MASTER_PLAN.md` — marcar itens da Etapa A concluídos (`- [x]`) somente após validação final (DoD §9).

### 7.2 Critério de sucesso — documentação

- [x] `docs/context.md` reflete o `.env.example` atual.
  - Conferido: exemplos locais de §2.5 batem com `.env.example` (`APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, timezone, DB_*, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `LOG_LEVEL`, `VITE_APP_NAME`).
- [x] Não há senhas reais no Markdown.
  - `docs/*.md`: apenas placeholders `(local)` / `(prod)` para `DB_PASSWORD`; sem `APP_KEY` real nem credenciais cPanel.
- [x] Qualquer mudança futura de env atualiza **ambos**: `.env.example` e `docs/context.md`.
  - Regra explícita em `docs/context.md` §2.5 (blockquote + nota).

---

## 8. Checklist transversal de arquivos (inventário Etapa A)

- [x] `.gitignore` (raiz) — completo
  - Ignora `.env*`, `vendor/`, `node_modules/`, `public/build/`, uploads `statements/*`.
- [x] `.env.example` — completo e versionado
  - Tracked no Git; espelha stack Aura (MySQL, session/cache database, Vite).
- [x] `.env` — local apenas, ignorado
  - `git check-ignore` confirma `.env` ignorado.
- [x] `composer.json` / `composer.lock`
- [x] `package.json` / `package-lock.json`
- [x] `vite.config.js` — React plugin + inputs corretos
  - `@vitejs/plugin-react`; inputs `resources/css/app.css` + `resources/js/app.jsx`.
- [x] `resources/js/app.jsx`
- [x] `resources/js/bootstrap.js`
- [x] `resources/js/components/App.jsx`
- [x] `resources/css/tokens.css`
- [x] `resources/css/app.css`
- [x] `resources/views/app.blade.php`
- [x] `routes/web.php` — shell SPA
  - Catch-all `Route::view('/{any?}', 'app')`.
- [x] `public/.htaccess`
- [x] `public/index.php`
- [x] (Se HostGator B) `.htaccess` na raiz do Laravel
  - **N/A** — Cenário A via `aura.vonluqi.com` (sem `.htaccess` na raiz).
- [x] `storage/app/private/statements/.gitignore`
- [x] `docs/context.md` — seção de env atualizada
- [x] `docs/MASTER_PLAN.md` — Etapa A sincronizada ao final
  - Itens da Etapa A marcados `[x]` após DoD §9.

---

## 9. Validação final da Etapa A (Definition of Done)

- [x] `composer install` limpo em máquina zerada (com PHP/MySQL OK).
  - `composer install --no-interaction` exit 0; lock OK; Laravel 11.56.1 / PHP 8.2.12.
- [x] `npm ci` + `npm run build` OK.
  - Ambos exit 0; `public/build/manifest.json` gerado.
- [x] `php artisan key:generate` + `php artisan about` OK.
  - `APP_KEY` já presente (`base64:…`); `php artisan about --only=environment` exit 0 (Aura / local / `America/Sao_Paulo`).
- [x] `php artisan serve` + página React com fundo `#151716` e Poppins.
  - HTTP 200, `#app`, assets `/build/assets/`; CSS build com `--color-bg-default: #151716` + Poppins.
- [x] `npm run dev` com HMR funcionando.
  - Vite ready `http://localhost:5173/`; HTML com refs `@vite/client` / `app.jsx`.
- [x] Tentativa de acessar caminhos sensíveis localmente não lista `vendor`/`.env`.
  - `/.env` e `/vendor/autoload.php` → SPA shell sem `APP_KEY`/`DB_PASSWORD`/autoload real.
- [x] Git: `git status` não lista `.env`, `vendor/`, `node_modules/`, `public/build/` (ou build está ignorado conforme política).
  - `git check-ignore` confirma; paths não tracked.
- [x] Documentação (`context.md` + este plano) atualizada.
- [x] Marcar no `docs/MASTER_PLAN.md` todos os itens da **Etapa A** como concluídos.
- [x] **Só então** iniciar `PLAN_ETAPA_B` / Etapa B (Banco de Dados).
  - Etapa A DoD satisfeita; Etapa B liberada (não iniciada neste passo).

---

## Ordem interna sugerida (Etapa A)

> **1 (Ambiente local)** → **2 (Git / .gitignore)** → **3 (Laravel 11 + .env)** → **4 (Vite + React)** → **5 (Apache / public)** → **6 (Poppins + tokens)** → **7 (Documentar env no context.md)** → **9 (DoD)**
