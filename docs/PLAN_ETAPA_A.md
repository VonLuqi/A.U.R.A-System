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
- [ ] Decidir estratégia de criação do Laravel em diretório **não vazio** (já existe `docs/`):
  - [ ] Opção recomendada: criar em pasta temporária e mover arquivos para a raiz.
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

- [ ] `git check-ignore -v .env` reconhece `.env` (após existir).
- [ ] `git check-ignore -v vendor` e `node_modules` OK após instalação.
- [ ] Nenhum segredo ou upload aparece em `git status` como untracked a ser commitado.

---

## 3. Criar aplicação Laravel 11 + configurar `.env.example`

### 3.1 Criar o skeleton Laravel 11

- [ ] Na pasta pai ou na raiz, executar (diretório atual **não vazio** por causa de `docs/`):

```bash
composer create-project laravel/laravel tmp-laravel "11.*"
```

- [ ] Mover o conteúdo de `tmp-laravel/` para a raiz do repositório **sem sobrescrever** `docs/`:
  - [ ] Mover `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `vite.config.js`, `package.json`, etc.
  - [ ] Mesclar `.gitignore` do Laravel com o `.gitignore` do projeto (manter regras de uploads/docs).
  - [ ] Remover pasta vazia `tmp-laravel/`.
- [ ] Alternativa Windows (PowerShell), exemplo:

```powershell
composer create-project laravel/laravel tmp-laravel "11.*"
Get-ChildItem tmp-laravel -Force | ForEach-Object {
  $dest = Join-Path "." $_.Name
  if (-not (Test-Path $dest)) { Move-Item $_.FullName $dest }
  else { Write-Host "SKIP (já existe): $($_.Name)" }
}
Remove-Item tmp-laravel -Recurse -Force
```

- [ ] Validar: `php artisan --version` → Laravel Framework 11.x

### 3.2 Instalar dependências PHP

- [ ] `composer install`
- [ ] Confirmar pasta `vendor/` criada e ignorada pelo Git.

### 3.3 Arquivo `.env` local

- [ ] Copiar ambiente:
  - [ ] `copy .env.example .env` (Windows) ou `cp .env.example .env`
- [ ] Gerar chave: `php artisan key:generate`
- [ ] Validar presença de `APP_KEY=base64:...` no `.env`.

### 3.4 Configurar `.env.example` (template versionável)

- [ ] Editar `.env.example` com valores **sem segredos reais**, alinhados ao projeto:

```env
APP_NAME="Aura"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=America/Sao_Paulo
APP_URL=http://localhost:8000

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aura
DB_USERNAME=aura_dev
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=database
CACHE_PREFIX=aura_

MAIL_MAILER=log

VITE_APP_NAME="${APP_NAME}"
```

- [ ] Espelhar as mesmas chaves no `.env` local com senha real do MySQL.
- [ ] Rodar (após MySQL OK): `php artisan migrate` — **pode falhar se ainda não houver tabelas de session/cache**; se `SESSION_DRIVER=database` / `CACHE_STORE=database`, executar:
  - [ ] `php artisan session:table` (se necessário na versão)
  - [ ] `php artisan cache:table` / `php artisan queue:table` conforme drivers escolhidos
  - [ ] Ou, para esta etapa, usar temporariamente `SESSION_DRIVER=file` e `CACHE_STORE=file` até a Etapa B — **documentar a escolha**.

### 3.5 Ajustes iniciais de `config/`

- [ ] Confirmar `config/app.php`: timezone `America/Sao_Paulo`, locale `pt_BR` (ou via `.env`).
- [ ] Confirmar `config/filesystems.php` terá disco privado na Etapa C; nesta etapa apenas garantir `local` e `public` padrão.
- [ ] Remover ou não commitiar qualquer `.env` real.

### 3.6 Critério de sucesso — Laravel

- [ ] `php artisan about` exibe app name e environment.
- [ ] `php artisan serve` sobe em `http://127.0.0.1:8000` e a página welcome do Laravel carrega.
- [ ] `.env` **não** está no Git; `.env.example` **está**.

---

## 4. Configurar Vite + React no frontend (`resources/js`)

### 4.1 Dependências Node (package.json)

- [ ] Na raiz: `npm install`
- [ ] Instalar React e plugin Vite:

```bash
npm install react react-dom
npm install -D @vitejs/plugin-react
```

- [ ] (Opcional nesta etapa, útil depois) `npm install -D @types/react @types/react-dom` se adotar TypeScript — **default MVP: JSX**.
- [ ] Confirmar em `package.json`:
  - [ ] `dependencies`: `react`, `react-dom`
  - [ ] `devDependencies`: `vite`, `laravel-vite-plugin`, `@vitejs/plugin-react`
  - [ ] Scripts: `"dev": "vite"`, `"build": "vite build"`

### 4.2 Atualizar `vite.config.js`

- [ ] Editar `vite.config.js` na raiz para:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/app/private/**'],
        },
    },
});
```

- [ ] Garantir que o `input` aponta para `resources/js/app.jsx` (não `app.js`).

### 4.3 Bootstrap e entrypoint React

- [ ] Renomear/remover `resources/js/app.js` se existir; criar `resources/js/app.jsx`.
- [ ] Atualizar `resources/js/bootstrap.js` (manter axios se vier do skeleton; configurar CSRF depois na Etapa D):

```js
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

- [ ] Se axios não estiver instalado: `npm install axios`
- [ ] Criar estrutura mínima:

```text
resources/js/
├── app.jsx
├── bootstrap.js
├── components/
│   └── App.jsx
└── pages/
    └── Home.jsx
```

- [ ] Conteúdo inicial de `resources/js/app.jsx`:

```jsx
import './bootstrap';
import '../css/app.css';

import { createRoot } from 'react-dom/client';
import App from './components/App';

const el = document.getElementById('app');
if (el) {
    createRoot(el).render(<App />);
}
```

- [ ] Conteúdo inicial de `resources/js/components/App.jsx`:

```jsx
export default function App() {
    return (
        <main className="app-shell">
            <h1>Aura</h1>
            <p className="tagline">Inteligência invisível, controle absoluto.</p>
            <p>Setup Etapa A — React + Vite OK</p>
        </main>
    );
}
```

### 4.4 Blade host do SPA

- [ ] Criar/atualizar `resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Aura') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
```

- [ ] Atualizar `routes/web.php` para servir o shell (temporário até auth na Etapa C/D):

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/{any?}', 'app')->where('any', '.*');
```

- [ ] **Atenção:** catch-all SPA só após garantir que rotas de API (`/api/*`) não sejam engolidas — na Etapa C registrar API antes ou em `routes/api.php`.

### 4.5 Remover assets padrão não usados

- [ ] Remover scaffolding Vue/Alpine residual se existir e não for usado.
- [ ] Garantir que não há referência a `resources/js/app.js` em `vite.config.js` ou Blade.

### 4.6 Critério de sucesso — Vite + React

- [ ] Terminal 1: `php artisan serve`
- [ ] Terminal 2: `npm run dev`
- [ ] Abrir `http://127.0.0.1:8000` e ver o texto do `App.jsx` (HMR ativo).
- [ ] `npm run build` gera `public/build/manifest.json` sem erros.
- [ ] Com build: parar Vite, servir só Artisan e validar assets compilados.

---

## 5. Document root `public/` e regras Apache / `.htaccess` (HostGator)

### 5.1 Princípio

- [ ] O document root de produção **deve** apontar para a pasta `public/` do Laravel (nunca para a raiz do projeto).
- [ ] Arquivos sensíveis (`.env`, `app/`, `storage/`, `vendor/`) **fora** do document root ou inacessíveis via HTTP.

### 5.2 `.htaccess` dentro de `public/` (padrão Laravel — manter)

- [ ] Verificar/criar `public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

- [ ] Confirmar existência de `public/index.php` (front controller Laravel).

### 5.3 Cenário HostGator A — Document Root apontando para `public/` (preferencial)

- [ ] No cPanel → Domains → `vonluqi.com` → Document Root = `/home/USUARIO/caminho/do/projeto/public`
- [ ] **Não** é necessário `.htaccess` na raiz do projeto para rewrite.
- [ ] Validar que `https://vonluqi.com` carrega `public/index.php`.
- [ ] Documentar no `docs/context.md` o caminho absoluto usado no painel.

### 5.4 Cenário HostGator B — Apenas `public_html` (sem mudar document root)

- [ ] Opção B1 (recomendada neste cenário): colocar o código **acima** de `public_html` e apontar/copiar só o conteúdo de `public/` → `public_html/`, ajustando paths em `index.php`.
- [ ] Opção B2: manter Laravel dentro de `public_html` e adicionar `.htaccess` na **raiz do Laravel** (pai de `public/`) para forçar tudo para `public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Bloquear acesso direto a arquivos sensíveis na raiz (defesa em profundidade)
    RewriteRule ^(\.env|composer\.(json|lock)|artisan|phpunit\.xml) - [F,L]
    RewriteRule ^(app|bootstrap|config|database|resources|routes|storage|tests|vendor)/ - [F,L]

    # Redirecionar tudo para public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

- [ ] Em `public/index.php`, se a estrutura for aninhada de forma não padrão, ajustar os `require` dos autoloads (`../vendor/autoload.php`, etc.) conforme o path real.
- [ ] **Preferir Cenário A**; usar B só se o painel não permitir alterar document root.

### 5.5 Segurança adicional Apache

- [ ] Criar `public/storage` via `php artisan storage:link` quando houver uploads públicos (MVP: extratos são privados — link público **não** deve expor statements).
- [ ] Garantir que não exista symlink/alias que exponha `storage/app/private`.
- [ ] Em produção HostGator, confirmar `mod_rewrite` habilitado (padrão cPanel).

### 5.6 Desenvolvimento local sem Apache

- [ ] Usar `php artisan serve` (raiz já embute `public/`).
- [ ] (Opcional) Configurar VirtualHost Apache/Laragon local apontando para `.../ControleFinanceiroPessoal/public`.

### 5.7 Critério de sucesso — Apache / public

- [ ] Checklist documentado no repo: qual cenário HostGator (A ou B) será usado.
- [ ] Localmente, URL base resolve para a welcome/SPA sem expor `/vendor` ou `/.env`.
- [ ] Arquivos `public/.htaccess` e (se B) raiz `.htaccess` versionados.

---

## 6. Instalar fonte Poppins e espelhar tokens CSS (`docs/DESIGN-SYSTEM.MD`)

### 6.1 Fonte Poppins

- [ ] Escolher método de carga (um dos dois):
  - [ ] **A — Google Fonts (dev rápido):** link no `app.blade.php`
  - [ ] **B — Self-host (melhor para produção/LGPD/latência):** baixar arquivos WOFF2 e servir em `public/fonts/`
- [ ] Se Google Fonts (A), adicionar em `resources/views/app.blade.php` no `<head>`:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
```

- [ ] Se self-host (B):
  - [ ] Baixar pesos `400`, `500`, `600`, `700`.
  - [ ] Salvar em `public/fonts/poppins/`.
  - [ ] Declarar `@font-face` em `resources/css/fonts.css` e importar em `app.css`.

### 6.2 Criar folha de tokens

- [ ] Criar `resources/css/tokens.css` copiando os tokens de `docs/DESIGN-SYSTEM.MD` §4.6:

```css
:root {
  /* Color */
  --color-brand-primary: #DCCFFF;
  --color-bg-default: #151716;
  --color-surface-default: #1C1E1D;
  --color-surface-raised: #2C2E2D;
  --color-surface-sunken: #101211;
  --color-border-subtle: #2A2C2B;
  --color-border-default: #3A3C3B;
  --color-text-primary: #FCFDFC;
  --color-text-secondary: #9A9C9B;
  --color-text-muted: #6E706F;
  --color-text-on-brand: #151716;
  --color-interactive-dark: #0A0B0A;
  --color-brand-muted: rgba(220, 207, 255, 0.35);
  --color-feedback-positive: #A8E6C3;

  /* Typography */
  --font-sans: "Poppins", system-ui, sans-serif;
  --text-display: 2.25rem;
  --text-h1: 1.75rem;
  --text-h2: 1.375rem;
  --text-h3: 1.125rem;
  --text-body: 0.875rem;
  --text-caption: 0.75rem;
  --text-small: 0.6875rem;

  /* Shape */
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;
  --radius-2xl: 32px;
  --radius-full: 9999px;

  /* Spacing */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
}
```

- [ ] Atualizar `resources/css/app.css`:

```css
@import './tokens.css';

*,
*::before,
*::after {
  box-sizing: border-box;
}

html,
body,
#app {
  min-height: 100%;
}

body {
  margin: 0;
  font-family: var(--font-sans);
  font-size: var(--text-body);
  line-height: 1.5;
  color: var(--color-text-primary);
  background-color: var(--color-bg-default);
  -webkit-font-smoothing: antialiased;
}

.app-shell {
  padding: var(--space-8);
}

.app-shell h1 {
  font-size: var(--text-h1);
  font-weight: 700;
  margin: 0 0 var(--space-3);
}

.app-shell p {
  color: var(--color-text-secondary);
  margin: 0;
}

.app-shell .tagline {
  font-size: var(--text-caption);
  font-weight: 400;
  color: var(--color-text-secondary);
  margin-bottom: var(--space-4);
}
```

- [ ] Conferência cruzada: abrir `docs/DESIGN-SYSTEM.MD` e validar hex/radius/spacing idênticos.
- [ ] Atualizar `App.jsx` para classes que usam tokens (já coberto pelo `body` / `.app-shell`).

### 6.3 Critério de sucesso — Design tokens

- [ ] DevTools → Computed: `body` usa Poppins e background `#151716`.
- [ ] Variáveis CSS `--color-brand-primary` etc. visíveis em `:root`.
- [ ] Nenhum uso residual de fonte Inter/Roboto/system como primária.

---

## 7. Documentar variáveis de ambiente em `docs/context.md`

### 7.1 Seção nova ou atualização

- [ ] Abrir `docs/context.md` e adicionar/atualizar seção **“Variáveis de ambiente”** (ex.: após Arquitetura), listando:

| Variável | Obrigatória | Exemplo local | Exemplo produção | Descrição |
| --- | --- | --- | --- | --- |
| `APP_NAME` | sim | `Aura` | igual | Nome da app |
| `APP_ENV` | sim | `local` | `production` | Ambiente |
| `APP_KEY` | sim | `(gerada)` | `(gerada única)` | Chave de criptografia |
| `APP_DEBUG` | sim | `true` | `false` | Nunca `true` em prod |
| `APP_URL` | sim | `http://localhost:8000` | `https://vonluqi.com` | URL canônica |
| `APP_TIMEZONE` | sim | `America/Sao_Paulo` | igual | Fuso |
| `DB_CONNECTION` | sim | `mysql` | `mysql` | Driver |
| `DB_HOST` | sim | `127.0.0.1` | host cPanel | Host DB |
| `DB_PORT` | sim | `3306` | `3306` | Porta |
| `DB_DATABASE` | sim | `aura` | nome cPanel | Database |
| `DB_USERNAME` | sim | `aura_dev` | user cPanel | Usuário |
| `DB_PASSWORD` | sim | `(local)` | `(prod)` | Senha — nunca no Git |
| `SESSION_DRIVER` | sim | `file` ou `database` | `database` | Sessões |
| `CACHE_STORE` | sim | `file` ou `database` | `database`/`file` | Cache |
| `FILESYSTEM_DISK` | sim | `local` | `local` | Disco default |
| `QUEUE_CONNECTION` | sim | `sync` | `sync` | Filas (MVP sync) |
| `LOG_LEVEL` | sim | `debug` | `error` | Verbosity |
| `VITE_APP_NAME` | não | `${APP_NAME}` | igual | Exposto ao front |

- [ ] Registrar decisão: `SESSION_DRIVER` / `CACHE_STORE` escolhidos na Etapa A.
- [ ] Registrar caminho HostGator do document root quando conhecido.
- [ ] Atualizar `docs/MASTER_PLAN.md` — marcar itens da Etapa A concluídos (`- [x]`) somente após validação final.

### 7.2 Critério de sucesso — documentação

- [ ] `docs/context.md` reflete o `.env.example` atual.
- [ ] Não há senhas reais no Markdown.
- [ ] Qualquer mudança futura de env atualiza **ambos**: `.env.example` e `docs/context.md`.

---

## 8. Checklist transversal de arquivos (inventário Etapa A)

- [ ] `.gitignore` (raiz) — completo
- [ ] `.env.example` — completo e versionado
- [ ] `.env` — local apenas, ignorado
- [ ] `composer.json` / `composer.lock`
- [ ] `package.json` / `package-lock.json`
- [ ] `vite.config.js` — React plugin + inputs corretos
- [ ] `resources/js/app.jsx`
- [ ] `resources/js/bootstrap.js`
- [ ] `resources/js/components/App.jsx`
- [ ] `resources/css/tokens.css`
- [ ] `resources/css/app.css`
- [ ] `resources/views/app.blade.php`
- [ ] `routes/web.php` — shell SPA
- [ ] `public/.htaccess`
- [ ] `public/index.php`
- [ ] (Se HostGator B) `.htaccess` na raiz do Laravel
- [ ] `storage/app/private/statements/.gitignore`
- [ ] `docs/context.md` — seção de env atualizada
- [ ] `docs/MASTER_PLAN.md` — Etapa A sincronizada ao final

---

## 9. Validação final da Etapa A (Definition of Done)

- [ ] `composer install` limpo em máquina zerada (com PHP/MySQL OK).
- [ ] `npm ci` + `npm run build` OK.
- [ ] `php artisan key:generate` + `php artisan about` OK.
- [ ] `php artisan serve` + página React com fundo `#151716` e Poppins.
- [ ] `npm run dev` com HMR funcionando.
- [ ] Tentativa de acessar caminhos sensíveis localmente não lista `vendor`/`.env`.
- [ ] Git: `git status` não lista `.env`, `vendor/`, `node_modules/`, `public/build/` (ou build está ignorado conforme política).
- [ ] Documentação (`context.md` + este plano) atualizada.
- [ ] Marcar no `docs/MASTER_PLAN.md` todos os itens da **Etapa A** como concluídos.
- [ ] **Só então** iniciar `PLAN_ETAPA_B` / Etapa B (Banco de Dados).

---

## Ordem interna sugerida (Etapa A)

> **1 (Ambiente local)** → **2 (Git / .gitignore)** → **3 (Laravel 11 + .env)** → **4 (Vite + React)** → **5 (Apache / public)** → **6 (Poppins + tokens)** → **7 (Documentar env no context.md)** → **9 (DoD)**
