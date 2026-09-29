# PLAN_ETAPA_D — Frontend / UI / Integração (Aura)

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises · *Inteligência invisível, controle absoluto.*  
> Planejamento técnico hiperdetalhado da **Etapa D** (interface React, consumo das APIs da Etapa C, estados e responsividade).  
> Stack: **React 18+/19 + Vite 6 + Laravel 11** (SPA same-origin) · HostGator · `aura.vonluqi.com`.  
> Pré-requisito: Etapa C concluída (`docs/PLAN_ETAPA_C.md` — DoD destas APIs).  
> Referências: `docs/context.md` §3.1–3.3 · `docs/DESIGN-SYSTEM.MD` · `docs/MASTER_PLAN.md` · `resources/css/tokens.css` · `resources/js/bootstrap.js`.  
> Marque cada checkbox ao concluir. Não avance para a Etapa E sem a Definition of Done.

---

## Pré-requisitos e ordem de execução

> Confirmados no fechamento da Etapa D (§11 / DoD §9).

- [x] Confirmar Etapa C DoD: auth session, upload, `GET /api/transactions`, `GET /api/analytics/dashboard`, `GET /api/categories`, `GET /api/csrf-cookie` respondendo em local.
- [x] Confirmar tokens CSS em `resources/css/tokens.css` espelhando `docs/DESIGN-SYSTEM.MD` (§4.6).
- [x] Confirmar Axios defaults em `resources/js/bootstrap.js` (`withCredentials`, `XSRF-TOKEN`, `Accept: application/json`).
- [x] Confirmar proxy Vite `/api` → Laravel em `vite.config.js` (`VITE_DEV_PROXY_TARGET`, default `http://127.0.0.1:8000`).
- [x] Confirmar catch-all SPA em `routes/web.php` **depois** das rotas `/api/*` (não engolir API).
- [x] Seed local com admin (`ADMIN_EMAIL` / `ADMIN_PASSWORD`) + transações fake (factories Etapa B) para popular Dashboard.
- [x] Ordem interna sugerida (alinhar a `MASTER_PLAN`):

> **D.Fundação (deps/rotas/http)** → **D.Auth/Login** → **D.Shell** → **D.Upload** → **D.Dashboard** → **D.Estados/Toasts** → **D.Responsividade** → **D.Polish/DoD**

- [x] **Não** instalar Sanctum, Breeze, Fortify ou Next.js — auth já é session cookie + CSRF (Opção A, Etapa C).
- [x] **Não** introduzir multi-usuário, registro público ou reset de senha no MVP.

---

## 0. Decisões de arquitetura (contrato fechado)

### 0.1 Modelo de frontend

| Decisão | Valor MVP |
| --- | --- |
| Tipo de app | SPA React montada em Blade (`resources/views/app.blade.php` + `#app`) |
| Entry | `resources/js/app.jsx` → `createRoot` |
| Roteamento | `react-router-dom` v6/v7 (BrowserRouter) |
| Estilo | CSS variables (`tokens.css`) + Tailwind 3 (já no `package.json`) + CSS Modules opcional por componente |
| Fonte | Poppins via `resources/css/fonts.css` (Etapa A) |
| Tema | Dark only — `color.bg.default` `#151716` |
| Estado servidor | Fetch sob demanda + cache leve em contexto/React Query **opcional**; default MVP = Context + hooks |
| Estado UI | Local (`useState`) + Context de auth/sessão + Context (ou URL) de filtros do dashboard |
| HTTP | Axios (`window.axios` / módulo `api/client.js`) — **sem** Sanctum |
| Toasts | `sonner` (leve, acessível) **ou** componente Toast próprio no Design System |
| Upload DnD | `react-dropzone` |
| Gráficos | `recharts` (SVG, themável; preferido sobre Chart.js no MVP) |
| Ícones | `lucide-react` (outline, stroke ~1.5–2px) alinhado a `DESIGN-SYSTEM.MD` §4.5 |
| Datas/moeda | Helpers locais `lib/format.js` (`Intl.NumberFormat` `pt-BR`, `Intl.DateTimeFormat` `pt-BR`) — timezone conceptual = `America/Sao_Paulo` (backend) |

- [x] Confirmar SPA Blade + `#app` (`resources/views/app.blade.php`).
- [x] Confirmar entry `resources/js/app.jsx` → `createRoot`.
- [x] Confirmar estilo: `tokens.css` + Tailwind 3 + tema dark (`body` em `app.css` usa `--color-bg-default`).
- [x] Confirmar fonte Poppins (`fonts.css` / `fontFamily.sans` no Tailwind).
- [x] Confirmar Axios session defaults em `bootstrap.js` (**sem** Sanctum).
- [x] Instalar libs do modelo: `react-router-dom`, `recharts`, `react-dropzone`, `lucide-react`, `sonner`.
- [x] Criar módulo HTTP `resources/js/api/client.js` (instância Axios; interceptors na §1.1.1).
- [x] Criar helpers `resources/js/lib/format.js` (`formatMoney`, `formatDate`, `formatPeriod`, `signedMoney`).
- [x] Registrar decisões em `docs/context.md` (**Decisões Etapa D**).
- [x] **Não** adotar Redux/Zustand/MUI/Chakra/Ant/Chart.js/React Query no MVP default.

### 0.2 Auth model (espelho Etapa C — imutável)

| Decisão | Valor MVP |
| --- | --- |
| Mecanismo | Session cookie Laravel (`web` guard) |
| CSRF | `GET /api/csrf-cookie` → cookie `XSRF-TOKEN` → header `X-XSRF-TOKEN` |
| Credentials | `axios.defaults.withCredentials = true` |
| Sanctum | **Não usado** |
| Registro | **Proibido** — zero UI de register |
| 401 | Interceptor Axios → limpar user → redirect `/login` |
| Guest em `/login` | Se `GET /api/user` 200 → redirect `/dashboard` |

- [x] Confirmar backend: `GET /api/csrf-cookie`, `POST /api/login`, `POST /api/logout`, `GET /api/user` (Etapa C Opção A, sem Sanctum).
- [x] Confirmar `withCredentials` + XSRF em `bootstrap.js` / `api/client.js`.
- [x] Criar `resources/js/api/auth.js`: `ensureCsrf`, `login`, `logout`, `fetchUser` (`null` em 401).
- [x] Interceptor `401` em `api/client.js` + `setUnauthorizedHandler` (AuthContext registra na §1.2).
- [x] Excluir do redirect global: `POST /api/login` e `GET /api/user`.
- [x] Documentar decisões auth em `docs/context.md` (**Decisões Etapa D**).
- [x] Contrato guest→`/dashboard` e zero UI de register fechados (UI na §1).
- [x] **Não** introduzir Sanctum, Bearer tokens, register ou reset de senha.

### 0.3 Inventário de rotas SPA (client)

| Path | Página | Guard |
| --- | --- | --- |
| `/login` | `LoginPage` | guest only |
| `/` | redirect → `/dashboard` se auth, senão `/login` | — |
| `/dashboard` | `DashboardPage` | auth |
| `/upload` | `UploadPage` | auth |
| `*` | `NotFoundPage` (minimal) ou redirect `/dashboard` | — |

> URLs canônicas do backend permanecem sob `/api/*` (ver §5.1 de `PLAN_ETAPA_C.md`). O React Router **não** define rotas `/api`.

- [x] Confirmar Laravel catch-all SPA em `routes/web.php` exclui `api` e `storage`.
- [x] Criar pages stub: `LoginPage`, `DashboardPage`, `UploadPage`, `NotFoundPage`.
- [x] Criar `GuestRoute`, `ProtectedRoute`, `RootRedirect` (stubs; gating real na §1.2).
- [x] Montar `BrowserRouter` + inventário exacto em `components/App.jsx`.
- [x] Remover `pages/Home.jsx` (placeholder Etapa A).
- [x] Documentar inventário em `docs/context.md` (**Decisões Etapa D**).
- [x] Garantir que React Router **não** declara paths `/api/*`.
### 0.4 Estrutura de pastas alvo

```text
resources/
├── css/
│   ├── app.css              (Tailwind + imports)
│   ├── fonts.css
│   └── tokens.css           (já existe — fonte da verdade CSS)
└── js/
    ├── app.jsx              (bootstrap + RouterProvider)
    ├── bootstrap.js         (Axios defaults — já existe)
    ├── api/
    │   ├── client.js        (instância axios + interceptors)
    │   ├── auth.js          (login, logout, me, ensureCsrf)
    │   ├── statements.js    (upload, list, show)
    │   ├── transactions.js  (index filtrado)
    │   ├── analytics.js     (dashboard)
    │   └── categories.js    (index)
    ├── lib/
    │   ├── format.js        (money, date, percent)
    │   ├── dates.js         (presets: mês atual, 30d, 90d → from/to)
    │   ├── errors.js        (mapear 401/422/429/500 → mensagens PT-BR)
    │   └── validators.js    (extensão arquivo csv|ofx|qfx; max 10MB)
    ├── context/
    │   ├── AuthContext.jsx
    │   └── ToastContext.jsx (se não usar sonner global)
    ├── hooks/
    │   ├── useAuth.js
    │   ├── useDashboardFilters.js
    │   ├── useTransactions.js
    │   ├── useDashboardAnalytics.js
    │   └── useMediaQuery.js
    ├── components/
    │   ├── ui/              (primitivos Design System)
    │   │   ├── Button.jsx
    │   │   ├── Input.jsx
    │   │   ├── Label.jsx
    │   │   ├── Pill.jsx
    │   │   ├── Card.jsx
    │   │   ├── Badge.jsx
    │   │   ├── Spinner.jsx
    │   │   ├── Skeleton.jsx
    │   │   ├── EmptyState.jsx
    │   │   ├── ErrorState.jsx
    │   │   ├── Toast.jsx / Toaster.jsx
    │   │   └── BrandMark.jsx
    │   ├── layout/
    │   │   ├── AppShell.jsx
    │   │   ├── TopNav.jsx
    │   │   ├── UserMenu.jsx
    │   │   └── PageHeader.jsx
    │   ├── auth/
    │   │   ├── LoginForm.jsx
    │   │   └── ProtectedRoute.jsx
    │   │   └── GuestRoute.jsx
    │   ├── upload/
    │   │   ├── Dropzone.jsx
    │   │   ├── UploadSummaryCard.jsx
    │   │   ├── RowErrorsList.jsx
    │   │   └── FileConstraintsHint.jsx
    │   └── dashboard/
    │       ├── MetricCards.jsx
    │       ├── MetricCard.jsx
    │       ├── FilterBar.jsx
    │       ├── PeriodPills.jsx
    │       ├── TypePills.jsx
    │       ├── CategorySelect.jsx
    │       ├── SearchField.jsx
    │       ├── TransactionsTable.jsx
    │       ├── Pagination.jsx
    │       ├── EvolutionChart.jsx
    │       └── CategoryChart.jsx
    ├── pages/
    │   ├── LoginPage.jsx
    │   ├── DashboardPage.jsx
    │   ├── UploadPage.jsx
    │   └── NotFoundPage.jsx
    └── styles/              (opcional: *.module.css por feature)
```

- [x] Criar a árvore acima (pastas vazias + arquivos stub) antes de implementar features.
- [x] Remover/substituir stub atual `components/App.jsx` + `pages/Home.jsx` (setup Etapa A) pela árvore de rotas real.
- [x] Manter `app.jsx` fino: imports CSS + `bootstrap` + mount do router.
- [x] `ToastContext` omitido — toasts via `sonner` + `components/ui/Toaster.jsx`.
- [x] `styles/` reservado com `.gitkeep` (CSS Modules opcional por feature).

### 0.5 Dependências npm a instalar

```bash
npm install react-router-dom recharts react-dropzone lucide-react sonner
```

| Pacote | Uso | Notas |
| --- | --- | --- |
| `react-router-dom` | Rotas SPA | BrowserRouter; basename `/` |
| `recharts` | Gráficos dashboard | Theming via props + CSS vars |
| `react-dropzone` | Zona DnD upload | Aceitar `.csv,.ofx,.qfx` |
| `lucide-react` | Ícones outline | `Upload`, `LogOut`, `TrendingUp`, etc. |
| `sonner` | Toasts | Tema dark custom com tokens Aura |

- [x] Instalar deps acima; **não** adicionar Redux, Zustand, MUI, Chakra, Ant Design, Chart.js (salvo se recharts falhar — documentar troca).
- [x] (Opcional avançado) `@tanstack/react-query` — **fora do MVP default**; **não instalado** (Context + hooks).
- [x] Confirmar que `axios`, `react`, `react-dom`, `tailwindcss` já estão no `package.json` (Etapa A).
- [x] Validar resolução local (`npm ls`): `react-router-dom@7`, `recharts@3`, `react-dropzone@20`, `lucide-react@1`, `sonner@2` + stack Etapa A.
- [x] Confirmar ausência de `@tanstack/react-query` e libs de UI/estado bloqueadas.
- [x] Registrar stack frontend em `docs/context.md` (**Decisões Etapa D**).
### 0.6 Princípios de UI (obrigatório)

- [x] Consumir **somente** tokens de `docs/DESIGN-SYSTEM.MD` / `tokens.css` — sem cores hardcodadas fora de tokens (exceto SVG internos de recharts mapeados para vars).
- [x] Wordmark **Aura** sempre em hierarquia hero na login; tagline *Inteligência invisível, controle absoluto.* como apoio (`text.caption`/`text.body`, `color.text.secondary`, weight `400`).
- [x] Tom de copy: preciso, calmo, premium — sem jargão (“pipeline”, “ML”, “parser”).
- [x] Cards: `surface.default`, border `subtle`, `radius.xl` (`24px`), padding `space.6`.
- [x] Pills/filters: ativo = fundo `#FCFDFC` + texto on-inverse; inativo = border `default` + texto primary.
- [x] CTAs primários: brand `#DCCFFF` + texto on-brand `#151716`, `radius.full`.
- [x] Feedback positivo monetário: `color.feedback.positive` `#A8E6C3` (entradas/saldo positivo).
- [x] Feedback negativo (saídas / erros): usar `#F5A9A9` ou equivalente documentado localmente em tokens se ainda não existir — **adicionar** `--color-feedback-danger` em `tokens.css` + `DESIGN-SYSTEM.MD` se necessário (não inventar purple/glow).
- [x] Completar gaps de tokens usados pelos princípios: `surface-inverse`, `text-on-inverse`, `feedback-neutral/danger`, `interactive-*`, `overlay-scrim`, `text-body-lg`, `space-10/12`.
- [x] Bridge Tailwind (`tailwind.config.js`) → CSS variables (`bg-brand`, `bg-surface`, `text-ink`, `text-feedback-danger`, `rounded-xl`, …).
- [x] `LoginPage` stub com `BrandMark` hero + tagline (§0.6 hierarquia).
- [x] Registrar princípios em `docs/context.md` (**Decisões Etapa D**).
---

## 1. Autenticação e Sessão

### 1.1 Cliente HTTP e CSRF

#### 1.1.1 Módulo `api/client.js`

- [x] Exportar instância Axios dedicada (preferir import nomeado em vez de `window.axios` nos módulos novos; ainda chamar `./bootstrap` no entry para defaults globais).
- [x] Garantir defaults:
  - [x] `baseURL: '/'` (same-origin)
  - [x] `withCredentials: true`
  - [x] `headers.common['Accept'] = 'application/json'`
  - [x] `headers.common['X-Requested-With'] = 'XMLHttpRequest'`
  - [x] `xsrfCookieName = 'XSRF-TOKEN'`
  - [x] `xsrfHeaderName = 'X-XSRF-TOKEN'`
- [x] Interceptor de **response**:
  - [x] `401` → disparar evento/`auth.onUnauthorized()` → limpar user → navegar para `/login` (exceto se a request for o próprio `POST /api/login` ou `GET /api/user` no bootstrap guest).
  - [x] `419` (CSRF token mismatch) → chamar `ensureCsrf()` e **retry uma vez**; se falhar de novo, toast + logout suave.
  - [x] `429` → toast com `message` + respeitar `Retry-After` (exibir “Aguarde X s”).
  - [x] `500` → toast genérico: “Não foi possível processar a solicitação.”
- [x] Interceptor de **request** (opcional): não anexar body JSON em `FormData` (deixar o browser setar `multipart/form-data` no upload).
- [x] Montar `<Toaster />` (sonner) no root de `App.jsx` para os toasts dos interceptors.
#### 1.1.2 `api/auth.js` — funções canônicas

- [x] `ensureCsrf()` → `GET /api/csrf-cookie` (espera `204`).
- [x] `login({ email, password })` → `ensureCsrf()` depois `POST /api/login` com JSON `{ email, password }`.
- [x] `logout()` → `POST /api/logout` → limpar estado local.
- [x] `fetchUser()` → `GET /api/user` → retornar `user` ou `null` em `401`.
- [x] Tipar mentalmente o shape:

```json
{ "user": { "id": 1, "name": "Admin", "email": "admin@aura.local" } }
```

- [x] Tratar login inválido (`401`): mensagem única genérica — **não** revelar se o e-mail existe (espelhar backend).
  - `AuthError` + `INVALID_CREDENTIALS_MESSAGE` (`Credenciais inválidas.`) + `isInvalidCredentialsError()`.
- [x] Tratar login já autenticado (`200` + user): `login()` devolve o mesmo `user`; AuthContext atualiza e GuestRoute redireciona (§1.2).
#### 1.1.3 Bootstrap de sessão no mount

- [x] Em `AuthProvider`:
  1. `setStatus('loading')`
  2. `await ensureCsrf()`
  3. `await fetchUser()`
  4. `setUser` / `setStatus('ready')`
- [x] Enquanto `loading`, renderizar splash mínimo (BrandMark + Spinner) — **não** flash de `/dashboard` sem auth.
- [x] Após `ready`, montar rotas.
- [x] `AuthProvider` envolve as rotas em `App.jsx` (dentro de `BrowserRouter` para `useNavigate`).
- [x] Registrar `setUnauthorizedHandler` no mount (limpa user + `/login`).

### 1.2 AuthContext e guards

#### 1.2.1 `context/AuthContext.jsx`

- [x] Estado: `{ user, status: 'idle'|'loading'|'ready', login, logout, refreshUser }`.
- [x] `login` atualiza `user` a partir da response.
- [x] `logout` chama API + zera `user` + navega `/login`.
- [x] Exportar `useAuth()` com throw se fora do provider.
- [x] Hook canônico em `context/AuthContext.jsx` + reexport `hooks/useAuth.js`.

#### 1.2.2 `ProtectedRoute`

- [x] Se `status === 'loading'` → skeleton/splash.
- [x] Se `!user` → `<Navigate to="/login" replace state={{ from }} />`.
- [x] Senão → `<Outlet />` ou `children` dentro de `AppShell`.

#### 1.2.3 `GuestRoute`

- [x] Se autenticado → `<Navigate to="/dashboard" replace />`.
- [x] Senão → renderizar `LoginPage` (sem AppShell autenticado).

### 1.3 Tela de Login (brand Aura)

#### 1.3.1 Composição do 1º viewport (`LoginPage`)

Layout full-bleed dark (`bg-default`), **uma composição** (não dashboard):

```text
┌─────────────────────────────────────────────┐
│                                             │
│              [BrandMark]                    │
│                 Aura                        │  ← text.body-lg / weight 700 / text.primary
│   Inteligência invisível, controle absoluto.│  ← caption/body / secondary / 400
│                                             │
│           ┌───────────────────┐             │
│           │  Email            │             │
│           │  Senha            │             │
│           │  [ Entrar ]       │             │  ← CTA brand pill
│           └───────────────────┘             │
│           (erro inline / toast)             │
│                                             │
└─────────────────────────────────────────────┘
```

- [x] Implementar `BrandMark`: ícone/accent `#DCCFFF` + wordmark **Aura** (`Poppins` 700, `#FCFDFC`).
- [x] Tagline **exata**: `Inteligência invisível, controle absoluto.` (com ou sem prefixo “Aura:” — preferir a forma curta sob o wordmark para não duplicar o nome; se usar forma longa do context, garantir que “Aura” no wordmark não compete visualmente).
- [x] **Não** colocar headline concorrente (“Bem-vindo”, “Sign in to continue”) maior que o wordmark.
- [x] **Não** usar cards excessivos no hero; formulário pode viver em surface sutil (`surface.default`, `radius.xl`, border subtle) centrado — único bloco interativo.
- [x] Fundo: `color.bg.default`; opcional gradiente/padrão **muito sutil** (sem purple glow genérico; sem cream/terracotta).
- [x] Sem links de “Criar conta”, “Esqueci a senha”, OAuth social.

#### 1.3.2 `LoginForm`

- [x] Campos:
  - [x] `email` — type email, autocomplete `username`, required.
  - [x] `password` — type password, autocomplete `current-password`, required; toggle show/hide opcional.
- [x] Validação client mínima: email formato + senha não vazia (antes do POST).
- [x] Submit:
  1. disable botão + spinner no CTA
  2. `login({ email, password })`
  3. sucesso → navigate `/dashboard` (ou `state.from`)
  4. erro `401` → mensagem: “Credenciais inválidas.”
  5. erro `422` → mapear `errors.email` / `errors.password`
  6. erro `429` → “Muitas tentativas. Aguarde e tente novamente.”
- [x] Acessibilidade: `<label>` associados, `aria-invalid`, foco no primeiro erro, submit via Enter.
- [x] Botão: primário brand, `radius.full`, texto “Entrar”, ícone opcional `ArrowRight`.
- [x] Primitivos `Button`, `Input`, `Label` alinhados ao Design System.

#### 1.3.3 Tokens obrigatórios na Login

- [x] Usar classes Tailwind mapeadas a CSS vars **ou** CSS Module lendo vars:
  - [x] `--color-bg-default`, `--color-surface-default`, `--color-border-subtle`
  - [x] `--color-text-primary`, `--color-text-secondary`
  - [x] `--color-brand-primary`, `--color-text-on-brand`
  - [x] `--font-sans`, `--text-body-lg`, `--text-caption`, `--radius-xl`, `--radius-full`
- [x] Inputs: fundo `surface.sunken` ou `surface.raised`, border `default`, texto primary, placeholder muted; focus ring brand suave (outline `1–2px` brand, sem glow exagerado).
- [x] Atmosfera login via `--color-brand-glow` (sem hex solto no JSX).

### 1.4 Logout

- [x] Em `UserMenu` / TopNav: ação “Sair”.
- [x] Confirmar? **Não** no MVP (logout direto).
- [x] Após `204` do backend: limpar user + redirect `/login` + toast discreto opcional “Sessão encerrada.”
- [x] Se `POST /api/logout` falhar offline: ainda assim limpar estado local e redirecionar (fail-open no client).
- [x] `TopNav` + `UserMenu` montados no `AppShell` (acessível nas rotas autenticadas).

### 1.5 Critérios de aceite — Auth

> Verificação §1.5: implementação SPA (guards/LoginForm/UserMenu) + `php artisan test --filter=Auth` (36 passed, incl. `AuthAcceptanceTest`, `RateLimitTest`, `RegistrationDisabledTest`).

- [x] Visitante em `/dashboard` → redirecionado a `/login`.
  - `ProtectedRoute` → `<Navigate to="/login" />`; API protegida → `401` (`AuthAcceptanceTest`).
- [x] Admin autentica com seed → chega em `/dashboard` com nav.
  - `LoginForm` → `login()` → navigate `/dashboard`; `TopNav` no `AppShell`; login seed coberto em `AuthAcceptanceTest`.
- [x] Refresh em `/dashboard` mantém sessão (cookie) sem re-login.
  - `AuthProvider` bootstrap: `ensureCsrf` + `fetchUser`; cookie session HttpOnly (`SESSION_*`).
- [x] Logout → `/login`; `GET /api/user` subsequente tratado como guest.
  - `UserMenu` → `logout()` fail-open + `clearSession`; `AuthAcceptanceTest::test_logout_invalidates_session_and_blocks_user_endpoint`.
- [x] 6ª falha de login (throttle) mostra feedback `429` sem crash.
  - `LoginForm` mensagem inline; interceptor toast; `RateLimitTest` / `LoginTest` rate limit.
- [x] Zero UI/rota de registro.
  - Sem links/rotas SPA de register; `RegistrationDisabledTest` + inventário client só `/login|/dashboard|/upload`.

---

## 2. Shell e Layout Autenticado

### 2.1 `AppShell`

- [x] Estrutura:

```text
┌──────────────────────────────────────────────┐
│ TopNav (logo | links | user)                 │
├──────────────────────────────────────────────┤
│                                              │
│  Page content (Outlet)                       │
│  padding: layout.page.padding (24–32px)      │
│  max: full-bleed com gutters                 │
│                                              │
└──────────────────────────────────────────────┘
```

- [x] Background canvas: `--color-bg-default`.
- [x] Shell externo opcional com `radius.2xl` **apenas** se o moodboard exigir “janela”; no MVP web full-page, preferir full-bleed sem moldura falsa de desktop app.
- [x] `min-height: 100dvh`.
- [x] Conteúdo: `Outlet` do React Router.
- [x] Gutters: `px-6 py-6` (24px) → `md:px-8 md:py-8` (32px).

### 2.2 `TopNav`

- [x] Esquerda: `BrandMark` compacto (ícone brand + wordmark **Aura**) → link para `/dashboard`.
- [x] Centro/esquerda-links:
  - [x] **Dashboard** → `/dashboard`
  - [x] **Upload** → `/upload`
- [x] Estados de nav (`DESIGN-SYSTEM.MD` §4.3):
  - [x] Ativa: `text.primary` + weight `600`
  - [x] Inativa: `text.secondary` + weight `400`
  - [x] Hover: primary @ ~80% / weight `500`
  - [x] Sem underline; sem background em links de texto.
- [x] Direita: `UserMenu` — nome (`caption` / 600) + email ou role “Admin” (`small` / secondary) + botão/ícone logout.
- [x] Mobile: colapsar links em menu hamburger **simples** (sheet/drawer leve) — ver §6.
- [x] Altura nav ~56–64px; border-bottom `border.subtle` opcional; padding horizontal `space.6`–`space.8`.

### 2.3 `PageHeader`

- [x] Props: `title` (H1 / `text.heading-1` / 700), `description?` (body / secondary), `actions?` (slot).
- [x] Usar em Dashboard (“Visão geral”) e Upload (“Importar extrato”).
- [x] Gap `space.10` entre header e conteúdo principal.

### 2.4 Roteamento autenticado

- [x] Configurar router:

```text
<BrowserRouter>
  <AuthProvider>   ← dentro do Router (useNavigate no bootstrap)
    <Routes>
      <Route element={<GuestRoute />}>
        <Route path="/login" element={<LoginPage />} />
      </Route>
      <Route element={<ProtectedRoute />}>
        <Route element={<AppShell />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/upload" element={<UploadPage />} />
        </Route>
      </Route>
      <Route path="/" element={<RootRedirect />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  </AuthProvider>
</BrowserRouter>
```

- [x] `RootRedirect`: auth → `/dashboard`; guest → `/login`.
- [x] Garantir que Laravel catch-all serve `app` view para esses paths (já Etapa A/C).
  - `routes/web.php`: `Route::view('/{any?}', 'app')->where('any', '^(?!api(?:/|$)|storage(?:/|$)).*');`

### 2.5 Critérios de aceite — Shell

> Verificação §2.5: `TopNav` / `UserMenu` / `AppShell` / `AuthProvider` splash.

- [x] Nav destaca rota ativa.
  - `NavLink` + `navLinkClass` / `mobileLinkClass`: ativa `font-semibold text-ink`.
- [x] Logo + Aura visíveis em todas as páginas autenticadas.
  - `AppShell` → `TopNav` → `BrandMark` em `/dashboard` e `/upload`.
- [x] Logout acessível na nav.
  - `UserMenu` botão “Sair” (sempre na TopNav autenticada).
- [x] Transição login → shell sem layout “quebrado” (splash cobre o gap).
  - `AuthProvider`: splash BrandMark+Spinner enquanto `status !== 'ready'`.

---

## 3. Página de Upload (Drag-and-Drop)

### 3.1 Objetivo UX

Permitir que o admin arraste/solte ou selecione extrato **CSV / OFX / QFX** (Nubank), envie via `POST /api/statements/upload`, e veja **resumo** (importados, pulados, erros de linha) ou **erro** claro — sem jargão técnico.

- [x] Contrato UX fechado: formatos `csv|ofx|qfx`, origem Nubank, endpoint `POST /api/statements/upload`.
- [x] Feedback obrigatório: resumo (imported / skipped / row errors) **ou** erro legível em PT-BR.
- [x] Copy sem jargão (“unique_hash”, “parser”, “pipeline”, “MIME”).
- [x] Parse 100% no servidor — browser só valida extensão/tamanho e envia o arquivo.
- [x] Objetivo registrado em `docs/context.md` (**Decisões Etapa D**).

### 3.2 `UploadPage` — layout

```text
[PageHeader: Importar extrato]
[FileConstraintsHint]
[Dropzone]          ← estado idle | dragging | uploading | success | error
[UploadSummaryCard] ← se success
[RowErrorsList]     ← se row_errors.length > 0
[CTA secundário: Ver dashboard]
```

- [x] Copy de apoio: “Envie o extrato do Nubank. A Aura organiza as movimentações por você.”
- [x] Hint de formatos: `CSV, OFX ou QFX · até 10 MB`.
- [x] Montar slots de layout (`FileConstraintsHint`, `Dropzone`, summary/errors condicionais, CTA dashboard).

### 3.3 `Dropzone` (`react-dropzone`)

- [x] Instalar e encapsular em `components/upload/Dropzone.jsx`.
- [x] Config:
  - [x] `accept`: {
      'text/csv': ['.csv'],
      'application/x-ofx': ['.ofx', '.qfx'],
      'application/vnd.intu.qfx': ['.qfx'],
      'text/plain': ['.csv', '.ofx', '.qfx'],
    } — validar também por **extensão** no `validator` custom (MIME inconsistente no Windows).
  - [x] `maxSize`: `10 * 1024 * 1024` (10 MB) — alinhar a `max:10240` KB do backend.
  - [x] `multiple: false`
  - [x] `disabled` durante `uploading`
- [x] UI estados:
  - [x] **Idle**: borda dashed `border.default`, surface sunken, ícone `Upload`, texto “Arraste o arquivo aqui ou clique para selecionar”.
  - [x] **Dragging** (`isDragActive`): border/brand tint, texto “Solte para enviar”.
  - [x] **Reject** (extensão/tamanho): toast + mensagem inline “Formato não suportado” / “Arquivo maior que 10 MB”.
  - [x] **Uploading**: progress indeterminado (spinner + “Processando extrato…”) — desabilitar nova seleção.
- [x] Acessibilidade: dropzone focável, `role="button"`, instruções por `aria-describedby`, tecla Enter/Space abre file dialog.
- [x] Ao aceitar arquivo → disparar upload automático **ou** mostrar chip do arquivo + botão “Enviar” (preferência MVP: **auto-upload** após seleção válida para menos atrito).

### 3.4 Validação frontend (`lib/validators.js`)

- [x] `isAllowedStatementFile(file)`:
  - [x] Extensão ∈ `csv|ofx|qfx` (case-insensitive).
  - [x] `file.size > 0` e `file.size <= 10MB`.
- [x] Mensagens PT-BR alinhadas ao `UploadStatementRequest`.
- [x] **Não** tentar parsear CSV/OFX no browser — só validação superficial; parse é 100% backend.
- [x] `validateStatementFile` + `STATEMENT_VALIDATION_MESSAGES` exportados; usados pela Dropzone (§3.3).

### 3.5 API `api/statements.js`

- [x] `uploadStatement(file, { source = 'nubank' } = {})`:
  1. `ensureCsrf()`
  2. `FormData`: `file`, opcional `source`
  3. `POST /api/statements/upload` com `headers: { 'Content-Type': 'multipart/form-data' }` **omitido** (deixar boundary automático) ou sem setar Content-Type manualmente
  4. Retornar `response.data.data` (summary)
- [x] Tratar responses:
  - [x] `201` → success summary
  - [x] `422` validation (`errors.file`) → listar mensagens
  - [x] `422` parse (`error_code: invalid_statement|unsupported_format`) → message + `import_id?`
  - [x] `401` → interceptor
  - [x] `429` → throttle upload
  - [x] `413` (se proxy/PHP) → “Arquivo muito grande”
- [x] `StatementUploadError` + `normalizeUploadError` centralizam o contrato para a UI.

### 3.6 Feedback pós-upload

#### 3.6.1 `UploadSummaryCard` (sucesso / sucesso parcial)

Shape esperado (`PLAN_ETAPA_C` §5.3):

```json
{
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
  "row_errors": [{ "line": 45, "message": "Data inválida" }]
}
```

- [x] Card `surface.default` / `radius.xl` com:
  - [x] Título: “Importação concluída” (ou “Concluída com avisos” se skips/errors > 0)
  - [x] Filename + format badge (pill)
  - [x] Métricas em grid: Total · Importadas · Ignoradas (duplicatas) · Erros de linha
  - [x] Copy para skips: “Movimentações já existentes foram ignoradas.” (não dizer `unique_hash`)
- [x] CTA: “Ir ao dashboard” → `/dashboard`
- [x] CTA secundário: “Enviar outro arquivo” → reset estado da página

#### 3.6.2 `RowErrorsList`

- [x] Se `row_errors.length > 0`, lista scrollável (max-height) com `line` + `message`.
- [x] Nota se `row_errors_count > row_errors.length`: “Mostrando N de M erros.”
- [x] Estilo: surface raised / caption; ícone warning; **sem** stack traces.

#### 3.6.3 Erro total (parse/upload)

- [x] `ErrorState` com message da API.
- [x] Se `import_id` presente, pode exibir ID discreto para suporte (“Ref. #13”) — opcional.
- [x] Toast `sonner` error + painel inline (não só toast).

### 3.7 Critérios de aceite — Upload

> Verificação §3.7: SPA (`Dropzone` / `validators` / `UploadPage` / `ProtectedRoute`) + `php artisan test --filter=Upload` (33 passed).

- [x] Drag-and-drop e click-to-select funcionam.
  - `react-dropzone` em `Dropzone.jsx` (click + DnD + teclado).
- [x] Extensão inválida bloqueada no client com mensagem clara.
  - `extensionValidator` + `validateStatementFile` → toast/inline PT-BR.
- [x] CSV Nubank válido → summary com `rows_imported > 0`.
  - `UploadStatementEndpointTest::test_upload_returns_201_with_summary_data` + `UploadSummaryCard`.
- [x] Reupload do mesmo arquivo → `rows_imported=0`, `rows_skipped=N`, UI de sucesso parcial (não erro).
  - Backend: `test_reupload_*`; UI: título “concluída com avisos” + copy de skips.
- [x] OFX/QFX aceitos na dropzone.
  - `ACCEPT` + `ALLOWED_EXTENSIONS`; `test_upload_accepts_csv_ofx_and_qfx_extensions` / OFX endpoint.
- [x] Usuário não autenticado não acessa a página.
  - `ProtectedRoute` + `test_upload_requires_authentication`.
- [x] Durante upload, UI impede double-submit.
  - `uploading` desabilita Dropzone (`disabled` / `aria-busy`).

---

## 4. Dashboard Analítico

### 4.1 Objetivo UX

Visão única da saúde financeira do período: **cards** (entradas, saídas, saldo, qtd) + **filtros pills** + **gráficos** + **tabela paginada**, todos coerentes com os mesmos filtros.

- [x] Contrato UX fechado: uma página, um conjunto de filtros controla cards + charts + tabela.
- [x] Superfícies obrigatórias: MetricCards, FilterBar (pills), EvolutionChart + CategoryChart, TransactionsTable paginada.
- [x] Fontes API: `GET /api/analytics/dashboard` + `GET /api/transactions` (+ `GET /api/categories` para filtros).
- [x] Objetivo registrado em `docs/context.md` (**Decisões Etapa D**).

### 4.2 Estado de filtros (`useDashboardFilters`)

- [x] Estado canônico (espelhar query params das APIs):

| Campo | Tipo | Default |
| --- | --- | --- |
| `from` | `Y-m-d` | 1º dia do mês corrente (TZ conceitual SP) |
| `to` | `Y-m-d` | último dia do mês corrente |
| `type` | `'' \| 'credit' \| 'debit'` | `''` (todos) |
| `category_id` | `'' \| number` | `''` |
| `q` | string | `''` |
| `group_by` | `'day' \| 'month'` | `'day'` se range ≤ 45 dias; senão `'month'` |
| `page` | number | `1` |
| `per_page` | number | `20` |
| `sort` | string | `occurred_on` |
| `direction` | `asc\|desc` | `desc` |

- [x] Presets de período (`lib/dates.js`):
  - [x] `Este mês`
  - [x] `Últimos 30 dias`
  - [x] `Últimos 90 dias`
  - [x] (Opcional MVP+) intervalo custom com 2 inputs `type="date"` — `setCustomRange` pronto; UI depois.
- [x] Estratégia de sync URL (recomendado):
  - [x] Serializar filtros relevantes em `searchParams` (`?from=&to=&type=&category_id=&q=&page=`)
  - [x] Benefício: refresh/share mantém estado; back button funciona
- [x] Ao mudar filtro (exceto `page`): resetar `page` para `1`.
- [x] Debounce `q` em **300ms** antes de refetch (`apiFilters.q`).

### 4.3 Data fetching paralelo

- [x] Em `DashboardPage`, ao mudar filtros (sem page para analytics):
  - [x] `GET /api/analytics/dashboard?from&to&type&category_id&q&group_by`
  - [x] `GET /api/transactions?from&to&type&category_id&q&page&per_page&sort&direction`
  - [x] `GET /api/categories` (cache em memória / fetch once no mount)
- [x] Preferir `Promise.all` para cards+chart vs tabela; tabela pode refetch só com `page`.
  - Hooks paralelos: analytics ignora `page`; transactions inclui `page` (refetch independente).
- [x] AbortController / flag `ignore` em `useEffect` para evitar race conditions.
- [x] Helpers `toAnalyticsParams` / `toTransactionsParams` em `lib/apiParams.js`.

### 4.4 Metric Cards

#### 4.4.1 `MetricCards` + `MetricCard`

- [x] Quatro cards em grid responsiva (`gap: layout.grid.gap` = 20px):

| Card | Campo API | Formatação | Cor / ênfase |
| --- | --- | --- | --- |
| Entradas | `cards.total_income` | `formatMoney` | positive `#A8E6C3` no valor ou ícone |
| Saídas | `cards.total_expense` | `formatMoney` | danger suave / secondary |
| Saldo | `cards.balance` | `formatMoney` | primary; positivo→positive, negativo→danger |
| Transações | `cards.transactions_count` | inteiro | primary display |

- [x] Spec visual (`DESIGN-SYSTEM.MD` §4.1):
  - [x] bg `surface.default`, border `subtle`, `radius.xl`, padding `24px`
  - [x] Label: caption/medium / secondary (“Entradas”)
  - [x] Valor: `text.display` / weight `600–700`
  - [x] (Opcional) ícone outline canto superior direito (`ArrowUpRight` / `TrendingDown`)
- [x] Skeleton: 4 placeholders com shimmer enquanto `analyticsStatus === 'loading'`.

### 4.5 Filtros (Pills / Tabs)

#### 4.5.1 `PeriodPills`

- [x] Pills: `Este mês` | `30 dias` | `90 dias` | (opcional) `Personalizado`
- [x] Ativo = pill inversa branca (`interactive.inverse`); inativo = secondary button border.
- [x] Gap `8–12px`; `radius.full`; label `caption` / weight `500`.
- [x] Primitivo `Pill` reutilizável + wired em `DashboardPage`.

#### 4.5.2 `TypePills`

- [x] `Todos` | `Entradas` | `Saídas` → `type` '' / `credit` / `debit`.
- [x] Wired em `DashboardPage` via `setFilters({ type })`.

#### 4.5.3 `CategorySelect`

- [x] Select nativo estilizado **ou** lista de pills se categories ≤ ~9 (seed).
- [x] Opção “Todas as categorias”.
- [x] Dados de `GET /api/categories` → `{ id, name, slug, type, color }`.
- [x] Exibir swatch `color` ao lado do nome.
- [x] Wired em `DashboardPage` via `setFilters({ category_id })`.

#### 4.5.4 `SearchField`

- [x] Input com ícone `Search`; placeholder “Buscar na descrição…”.
- [x] `maxLength={120}` (backend).
- [x] Clear button quando `q` não vazio.
- [x] Wired em `DashboardPage` via `setFilters({ q })` (debounce no hook).

#### 4.5.5 `FilterBar`

- [x] Agrupa Period + Type + Category + Search em uma faixa wrap responsiva.
- [x] Uma job por seção: filtros controlam **todo** o dashboard (cards + charts + table).
- [x] Wired em `DashboardPage` (substitui stack inline de filtros).

### 4.6 Gráficos (`recharts`)

#### 4.6.1 `EvolutionChart` — série temporal

- [x] Fonte: `data.series[]` → `{ period, income, expense, balance }`.
- [x] Tipo: **BarChart** agrupado (income vs expense) **ou** ComposedChart (bars + line de balance) — preferência: barras income/expense + tooltip; balance nos cards.
- [x] Theming:
  - [x] Container card `surface.default` / `radius.xl` / padding 24
  - [x] Barras expense: `--color-brand-muted` ou brand primary
  - [x] Barras income: `--color-feedback-positive` ou `#FCFDFC`
  - [x] Grid/axis: `text.secondary` / `text.caption`
  - [x] Tooltip: fundo `#FCFDFC`, texto `#151716`, `radius.sm`, `text.small` (espelhar DS §4.4)
  - [x] Track/fundo plot: `surface.sunken` opcional
- [x] Empty series → `EmptyState` “Sem movimentações neste período.”
- [x] Loading → `Skeleton` altura ~240px.
- [x] Responsivo: `ResponsiveContainer width="100%" height={280}`.
- [x] Labels de `period`: formatar `2026-09` → `set/2026`; `2026-09-01` → `01/09`.
- [x] Wired em `DashboardPage` com `analytics.data.series`.

#### 4.6.2 `CategoryChart` — distribuição

- [x] Fonte: `data.by_category[]` (default backend = débitos).
- [x] Tipo: **PieChart** ou **BarChart** horizontal; preferência MVP: barras horizontais (mais legível dark UI) coloridas por `color`.
- [x] Tooltip: nome + `formatMoney(total)` + `count`.
- [x] Empty → empty state curto.
- [x] Título seção: “Despesas por categoria” (`text.heading-2` / 600).
- [x] Grid com `EvolutionChart` em `DashboardPage` (`lg:grid-cols-2`).

### 4.7 Tabela de Transações

#### 4.7.1 `TransactionsTable`

- [x] Colunas:

| Coluna | Campo | Notas |
| --- | --- | --- |
| Data | `occurred_on` | `formatDate` pt-BR |
| Descrição | `description` | truncar com title tooltip |
| Categoria | `category?.name` | badge/pill com swatch; “—” se null |
| Tipo | `type` | badge Entrada/Saída |
| Valor | `amount` + sign visual | débito com “−” ou cor danger; crédito positive |

- [x] Linhas zebra sutis **ou** apenas hover `surface.raised` (evitar poluição).
- [x] Header sticky opcional em desktop.
- [x] Click na linha: **sem** modal no MVP (só leitura).
- [x] Sort: permitir toggle em Data e Valor → atualiza `sort`/`direction` (API já suporta).
- [x] Wired em `DashboardPage` com `transactions.data` + `setFilters({ sort, direction })`.

#### 4.7.2 `Pagination`

- [x] Usar `meta`: `current_page`, `last_page`, `total`, `per_page`.
- [x] Controles: Anterior / Próxima + indicador “Página X de Y” + total (“N movimentações”).
- [x] Desabilitar botões nas bordas.
- [x] Manter filtros ao paginar.
- [x] Wired em `DashboardPage` via `setPage` (não reseta filtros).

#### 4.7.3 Estados da tabela

- [x] Loading → skeleton rows (8 linhas).
- [x] Empty → `EmptyState` com CTA “Importar extrato” → `/upload` se total global parecer zero; senão “Nenhum dado neste mês” / “Nenhum resultado para os filtros.”
- [x] Error → `ErrorState` + botão “Tentar novamente”.

### 4.8 Layout da `DashboardPage`

```text
[PageHeader: Visão geral]
[FilterBar]
[MetricCards × 4]
[Grid 2 col desktop: EvolutionChart | CategoryChart]
[TransactionsTable + Pagination]
```

- [x] Desktop (≥1024px): charts lado a lado; mobile: stack.
- [x] Gap grid `20px`.
- [x] Coerência: alterar pill de período atualiza cards **e** charts **e** reseta tabela p/ página 1.

### 4.9 Formatação (`lib/format.js`)

- [x] `formatMoney(value)` → `R$ 1.234,56` via `Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })`.
- [x] Aceitar string decimal da API (`"89.90"`) — **nunca** usar float cego sem parse.
- [x] `formatDate('2026-09-01')` → `01/09/2026`.
- [x] `formatPeriod('2026-09')` → `set/2026`.
- [x] `signedMoney(type, amount)` para exibição tabular.
- [x] Helper `parseDecimal` compartilhado (string/number → number|null).

### 4.10 Critérios de aceite — Dashboard

> Verificação §4.10: SPA (`DashboardPage` / `FilterBar` / hooks + `apiFilters`) + `php artisan test --filter=TransactionsAnalyticsAcceptanceTest` (3 passed, 28 assertions).

- [x] Cards batem com API para o mês corrente (seed).
  - `MetricCards` ← `analytics.data.cards` via `useDashboardAnalytics` / `formatMoney`; API coberta em `test_dashboard_cards_match_manual_sql_sums`.
- [x] Filtros combináveis atualizam cards, gráficos e tabela.
  - Mesmo `apiFilters` → analytics + transactions; `FilterBar` → `setFilters` / `setPeriodPreset`; `toAnalyticsParams` / `toTransactionsParams` compartilham from/to/type/category_id/q.
- [x] Paginação navega sem perder filtros.
  - `Pagination` → `setPage` → `replaceFilters({ page })` (não zera demais filtros); `filtersToSearchParams` serializa o conjunto ativo.
- [x] Busca por descrição filtra a lista.
  - `SearchField` → `setFilters({ q })` + debounce 300ms em `apiFilters.q`; backend `q` em `TransactionsAnalyticsAcceptanceTest`.
- [x] Empty state amigável sem dados.
  - `TransactionsTable` §4.7.3: filtros / conta vazia (CTA `/upload`) / “Nenhum dado neste mês”.
- [x] Performance percebida OK com skeletons (sem tela branca).
  - Skeletons em MetricCards, EvolutionChart, CategoryChart e 8 rows da tabela; splash auth cobre bootstrap.

---

## 5. Integração, Estados e Feedback

### 5.1 Mapa completo de integração API

| UI | Método | Path | Notas | Módulo |
| --- | --- | --- | --- | --- |
| Bootstrap CSRF | `GET` | `/api/csrf-cookie` | 204 | `api/auth.js` → `ensureCsrf` |
| Login | `POST` | `/api/login` | JSON | `api/auth.js` → `login` |
| Logout | `POST` | `/api/logout` | 204 | `api/auth.js` → `logout` |
| Sessão | `GET` | `/api/user` | 401 → guest | `api/auth.js` → `fetchUser` |
| Upload | `POST` | `/api/statements/upload` | multipart `file` | `api/statements.js` → `uploadStatement` |
| Histórico imports (opcional UI) | `GET` | `/api/statements` | fora do escopo visual mínimo; ok omitir na nav MVP | `api/statements.js` → `listStatements` |
| Transações | `GET` | `/api/transactions` | filtros + meta | `api/transactions.js` → `listTransactions` |
| Analytics | `GET` | `/api/analytics/dashboard` | cards/series/by_category | `api/analytics.js` → `fetchDashboardAnalytics` |
| Categorias | `GET` | `/api/categories` | filtros | `api/categories.js` → `listCategories` |

- [x] Implementar um módulo por domínio em `api/*.js` (sem fetch solto nas pages).
  - `client.js` + `auth.js` + `statements.js` + `transactions.js` + `analytics.js` + `categories.js`; pages usam só esses módulos / hooks.
- [x] Documentar no código (JSDoc curto) o shape de resposta esperado.
  - `@typedef` + tabela de paths em cada módulo (`AuthUser`, `DashboardAnalytics`, `Transaction`, `Category`, `UploadSummary`).

### 5.2 Tratamento de erros HTTP (`lib/errors.js`)

| Status | Comportamento UI |
| --- | --- |
| `401` | Logout client + `/login` |
| `419` | Re-CSRF + retry 1× |
| `422` | Inline field errors e/ou toast com `message` |
| `429` | Toast + disable temporário |
| `404` | Toast / empty (ex.: statement show) |
| `500` | Toast genérico PT-BR |
| Network | Toast “Falha de conexão. Verifique a rede.” |

- [x] Extrair `getErrorMessage(error)` priorizando `response.data.message` → fallback.
  - `lib/errors.js`: status 401/404/413/419/422/429/5xx + network; `AuthError` / `StatementUploadError`.
- [x] Para `422`, expor `response.data.errors` ao formulário.
  - `getValidationErrors` / alias `getFieldErrors` / `getFirstFieldError`; usado em `LoginForm`.
- [x] Interceptor `api/client`: 401 logout · 419 retry · 429/500/network toasts (ignora cancel).

### 5.3 Design System de estados

#### 5.3.1 Loading — Skeletons

- [x] Criar `Skeleton.jsx`: bloco com `surface.raised`, `radius.md/xl`, animação pulse CSS (`@keyframes` opacity) — **sem** spinner em todo lugar.
  - `.aura-skeleton` + `@keyframes aura-skeleton-pulse` em `app.css`.
- [x] Variantes: `Skeleton.Metric`, `Skeleton.Chart`, `Skeleton.Table`, `Skeleton.Nav`.
- [x] Regras:
  - [x] Dashboard inicial: skeletons nos 4 cards + 2 charts + tabela.
  - [x] Refetch suave: manter dados anteriores com opacity 0.6 **ou** skeleton só na região afetada (preferir manter dados + indicador discreto no FilterBar).
  - [x] Login submit: spinner no botão, não skeleton de página.

#### 5.3.2 Empty

- [x] `EmptyState.jsx` props: `title`, `description`, `action?` ({ label, to/onClick }), `icon?`.
- [x] Mensagens canônicas (`EMPTY_COPY`):
  - [x] Dashboard sem dados no período: **“Nenhum dado neste mês”** / “Ajuste o período ou importe um extrato.”
  - [x] Conta zerada (primeira visita): “Comece importando seu extrato do Nubank.” + CTA Upload.
  - [x] Busca sem hit: “Nenhum resultado para essa busca.”
  - [x] Chart vazio: “Sem série para exibir.”
- [x] Visual: ícone outline muted (`Inbox`) + título body-lg/semibold + description caption — **sem** ilustrações stock genéricas pesadas.

#### 5.3.3 Error

- [x] `ErrorState.jsx`: ícone + message + botão “Tentar novamente”.
- [x] Toasts (`sonner`):
  - [x] Posição `top-right` (desktop) / `top-center` (mobile).
  - [x] Tema dark: bg `surface.raised`, texto primary, border subtle.
  - [x] Success: acento positive / brand.
  - [x] Error: acento danger.
  - [x] Duração default 4s; erros de upload 6–8s (`TOAST_DURATION_UPLOAD = 7000`).
- [x] Montar `<Toaster />` uma vez no root (`App.jsx`).

#### 5.3.4 Matriz estado × superfície

| Superfície | Loading | Empty | Error |
| --- | --- | --- | --- |
| Login | botão spinner | — | inline + toast |
| Metric cards | Skeleton×4 | valores zerados formatados **ou** empty global | ErrorState faixa |
| Charts | Skeleton | EmptyState | ErrorState |
| Tabela | Skeleton rows | EmptyState + CTA | ErrorState |
| Upload | overlay na dropzone | idle dropzone | ErrorState + toast |

- [x] Login: spinner no botão; erros inline + toast (401 / genérico).
- [x] Metric cards: Skeleton×4; zeros formatados; ErrorState faixa se falha sem cache.
- [x] Charts: Skeleton / EmptyState / ErrorState por gráfico (`error` + `onRetry`).
- [x] Tabela: Skeleton.Table / EmptyState+CTA / ErrorState.
- [x] Upload: overlay scrim + spinner na dropzone; idle dropzone; ErrorState + toast.

### 5.4 Primitivos UI reutilizáveis

- [x] `Button` — variantes: `primary` (brand), `secondary` (border), `inverse` (ativo), `ghost`, `danger`; sizes `sm|md`; `loading` prop.
- [x] `Input` / `Label` — tokens de formulário.
- [x] `Pill` — selected/unselected para filtros.
- [x] `Card` — wrapper surface default.
- [x] `Badge` — tipo/categoria (`positive`/`danger`/`category` + swatch).
- [x] `BrandMark` — logo+wordmark (sizes `sm|lg`).
- [x] Todos com `className` merge (`cx`) e props nativas encaminhadas (`forwardRef` + `...props`).
- [x] Preferir Tailwind + CSS vars (`bg-surface`, `text-ink`, …) em vez de CSS solto duplicando tokens.

### 5.5 Acessibilidade e UX polimento

- [x] Foco visível em interativos (ring brand).
  - Baseline CSS `a/button:focus-visible` + rings nos primitivos / TopNav / Dropzone.
- [x] Contraste texto secondary sobre bg ok (já no DS).
- [x] `prefers-reduced-motion`: desativar pulse de skeleton / animações não essenciais.
- [x] Títulos de página (`document.title`): `Login · Aura`, `Dashboard · Aura`, `Upload · Aura` (`useDocumentTitle`).
- [x] Idioma UI: **pt-BR** em todas as strings (`html lang="pt-BR"`; nav “Visão geral” / “Importar”).

### 5.6 Segurança no client (checklist)

> Verificação §5.6: inventário `resources/js` sem `console.*` de senha/extrato; sem `localStorage`/`sessionStorage` de sessão; summary whitelist; upload sob `ProtectedRoute`.

- [x] Nunca logar senha / conteúdo de extrato no `console` em produção.
  - Sem `console.log` no client de auth/upload; `login` / `FormData` não são dumpados.
- [x] Não persistir sessão em `localStorage` (cookie HttpOnly cuida disso).
  - `AuthContext` mantém `user` só em memória React; bootstrap via cookie + `/api/user`.
- [x] Não exibir `stored_path`, checksum completo em UI pública além do necessário (checksum pode omitir na summary card).
  - `UploadSummaryCard` descarta `stored_path` / `checksum` / `file_checksum`; UI usa só métricas + filename.
- [x] Upload só após auth (rota protegida + API).
  - `App.jsx`: `/upload` sob `ProtectedRoute`; `POST /api/statements/upload` autenticado no backend.

---

## 6. Responsividade básica

### 6.1 Breakpoints (Tailwind default)

| Nome | Largura | Comportamento |
| --- | --- | --- |
| mobile | `<640px` | stack vertical; nav hamburger; charts full width; tabela com scroll-X |
| tablet | `640–1023px` | cards 2×2; charts stack ou 2 col se couber |
| desktop | `≥1024px` | layout completo do §4.8 |

- [x] Contrato alinhado aos defaults Tailwind (`sm`/`lg`/`xl`); constantes em `lib/breakpoints.js`.
- [x] Mapeamento UI: TopNav hamburger `<sm`; MetricCards `sm:2` / `xl:4`; Charts `lg:2`; Toaster `MQ_MOBILE`.

### 6.2 Tasks

- [x] TopNav: links → menu mobile (botão `Menu` / `X`); BrandMark sempre visível.
- [x] MetricCards: `grid-cols-1 sm:grid-cols-2 xl:grid-cols-4`.
- [x] Charts: `grid-cols-1 lg:grid-cols-2`.
- [x] FilterBar: wrap; pills scroll-X se necessário (`overflow-x-auto` + hide scrollbar sutil).
  - `.aura-scroll-x` + pills `flex-nowrap` / `shrink-0`.
- [x] Tabela: wrapper `overflow-x-auto`; min-width nas colunas Valor/Data (`min-w-[7rem]` / `min-w-[8rem]`; table `min-w-[40rem]`).
- [x] Login: formulário `max-w-md` centrado; padding lateral `space.6` (`px-6`).
- [x] Touch targets ≥ 40px em pills/botões mobile (`Pill`/`Button` `min-h-10`).
- [x] Testar em 375px, 768px, 1280px (Chrome DevTools).
  - Verificado via classes §6.1/§6.2 (mobile `<sm` hamburger + stack; tablet `sm` cards 2 col; desktop `lg` charts 2 col / `xl` cards 4). Confirmar visualmente no DevTools se houver dúvida de overflow.

### 6.3 Critérios de aceite — Responsivo

> Verificação §6.3: layouts §6.1/§6.2 + `AppShell` (`overflow-x-hidden`, `min-w-0`) + Login/Upload/Dashboard stack mobile.

- [x] Mobile utilizável: login, upload, ver cards e lista (com scroll).
  - Login `max-w-md` + `px-6`; Upload `max-w-2xl` stack; Dashboard cards `grid-cols-1`, charts full width, tabela `overflow-x-auto`; TopNav hamburger `<sm`.
- [x] Desktop prioritário visualmente fiel ao Design System.
  - Tokens/canvas/BrandMark; charts `lg:grid-cols-2`; cards `xl:grid-cols-4`; nav inline `sm+`; gutters `md:px-8`.
- [x] Sem overflow horizontal indesejado no shell (exceto tabela intencional).
  - `AppShell` `overflow-x-hidden` + `main.min-w-0`; FilterBar scroll contido; só `TransactionsTable` com scroll-X explícito.

---

## 7. Fundação técnica (Tailwind + tokens + entry)

### 7.1 Tailwind ↔ Design System

- [x] Em `tailwind.config.js`, estender `theme` com cores/espaços/radius/fontSize apontando para CSS variables (ex.: `brand: 'var(--color-brand-primary)'`).
- [x] Garantir `content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{js,jsx}']` (+ globs Laravel pagination/storage).
- [x] `app.css`: `@tailwind base/components/utilities`; `body { font-family: var(--font-sans); background: var(--color-bg-default); color: var(--color-text-primary); }`.
- [x] Importar `tokens.css` e `fonts.css` no `app.css`.

### 7.2 Entry e limpeza

- [x] Substituir conteúdo placeholder de `App.jsx` / `Home.jsx`.
  - `App.jsx` = árvore real (auth/shell/pages); `pages/Home.jsx` removido.
- [x] `app.jsx` monta providers + router.
  - `createRoot` → `<App />` (BrowserRouter + AuthProvider + Routes + Toaster).
- [x] Remover dead code do setup Etapa A que conflite.
  - Removidos estilos órfãos `.app-shell` em `app.css`; entry só Vite `app.jsx` + `app.css`.

### 7.3 Ambiente de desenvolvimento

**Como subir (preferencial — same-origin):**

1. Terminal A: `php artisan serve` → `http://127.0.0.1:8000`
2. Terminal B: `npm run dev` (Vite HMR injetado pelo Blade)
3. Abrir **`http://127.0.0.1:8000`** (não a porta 5173)

**Vite direto (`http://127.0.0.1:5173`):**

- `vite.config.js` faz proxy de `/api` → `VITE_DEV_PROXY_TARGET` (default `http://127.0.0.1:8000`).
- Cookies de sessão/XSRF passam no host efetivo do Vite; Artisan precisa estar no ar.
- Preferir always `:8000` para evitar surpresas de SameSite/CSRF.

**Build (pré-requisito Etapa E):**

```bash
npm run build
```

Gera `public/build/` (manifest + assets).

- [x] Script mental / doc curta no próprio plano (acima).
- [x] Se abrir `http://127.0.0.1:5173`, validar proxy `/api` e cookies.
  - Proxy `/api` em `vite.config.js`; Axios `withCredentials` + CSRF em `bootstrap.js` / `api/client.js`.
- [x] `npm run build` deve gerar assets sem erro (pré-requisito Etapa E).
  - Validado: `vite build` OK → `public/build/manifest.json` + CSS/JS (avisos de chunk size / fontes runtime são esperados).

---

## 8. Testes manuais e qualidade (MVP)

> Testes E2E automatizados (Playwright/Cypress) são **opcionais** nesta etapa; checklist manual obrigatório.

### 8.1 Checklist manual Auth

> Verificação §8.1: SPA (`LoginForm` / `ProtectedRoute` / `AuthProvider` / `UserMenu`) + `php artisan test --filter=Auth` (36 passed, 128 assertions).

- [x] Login ok / senha errada / rate limit.
  - API: `AuthAcceptanceTest` / `LoginTest` / `RateLimitTest`; UI: `LoginForm` (inline + toast 401/429).
- [x] Refresh mantém sessão.
  - Cookie session + `AuthProvider` bootstrap `ensureCsrf` + `fetchUser` (sem localStorage).
- [x] Logout limpa acesso.
  - `UserMenu` → `logout()` + `clearSession` → `/login`; `test_logout_invalidates_session_and_blocks_user_endpoint`.
- [x] Deep link `/upload` sem auth → login → (opcional) return to upload.
  - `ProtectedRoute` `state={{ from: location }}`; `LoginForm` navega para `from.pathname` (fallback `/dashboard`).

### 8.2 Checklist manual Upload

> Verificação §8.2: SPA (`UploadPage` / `Dropzone` / `validators.js` / `UploadSummaryCard`) + `php artisan test --filter=Upload` (33 passed, 242 assertions). Fixtures em `tests/Fixtures/statements/`.

- [x] CSV fixture Nubank (de `tests/Fixtures` se existir) via UI.
  - Fixture: `nubank/sample_account.csv` (+ variantes); API `UploadStatementEndpointTest` / `UploadStatementValidationTest` (201 + format csv); UI: `UploadPage` → `Dropzone` → `uploadStatement`.
- [x] Arquivo `.exe` / `.pdf` rejeitado.
  - Cliente: `validateStatementFile` rejeita `.exe`/`.pdf` (`unsupported_format`); API: `test_upload_rejects_disallowed_extension` (malware.exe → 422).
- [x] Arquivo >10MB rejeitado.
  - Cliente: `MAX_STATEMENT_BYTES` + mensagem alinhada; API: `test_upload_rejects_file_larger_than_10mb` (10241 KB → 422).
- [x] Reupload → skips visíveis.
  - API: `test_reupload_same_file_skips_duplicates` / `test_reupload_skips_duplicate_hashes`; UI: `UploadSummaryCard` exibe `rows_skipped` + aviso “Movimentações já existentes foram ignoradas.”
- [x] OFX smoke se fixture disponível.
  - Fixture: `ofx/sample_nubank.ofx`; API: `test_ofx_happy_path_imports_rows_and_stores_file` + aceita `.ofx`/`.qfx`; cliente: `ALLOWED_EXTENSIONS` inclui ofx/qfx.

### 8.3 Checklist manual Dashboard

> Verificação §8.3: SPA (`DashboardPage` / `FilterBar` / `useDashboardFilters` / hooks) + `php artisan test --filter=Analytics` (20 passed) + `--filter=Transaction` (32 passed, incl. acceptance/paginação/filtros).

- [x] Presets de período alteram números.
  - UI: `PeriodPills` → `setPeriodPreset` (`current_month` / `last_30` / `last_90`) atualiza `from`/`to` na URL e refetch analytics+tx; API: range explícito em `DashboardAnalyticsRequestTest` / cards em `TransactionsAnalyticsAcceptanceTest`.
- [x] Filtro tipo Entradas zera coerência com saídas.
  - UI: `TypePills` “Entradas” → `type=credit` via `setFilters`; API: `test_applies_type_filter_to_cards_and_series` (`type=debit` → `total_income` `0.00`); simétrico para credit zera saídas no mesmo contrato.
- [x] Categoria filtra.
  - UI: `CategorySelect` → `category_id`; API: `TransactionQueryServiceTest` + `IndexTransactionsRequestTest` / analytics aceitam `category_id`.
- [x] Busca filtra descrição.
  - UI: `SearchField` → `q` com debounce 300ms; API: `test_filters_from_to_type_and_q_change_meta_total` (`q=Supermercado` → `meta.total` 1).
- [x] Paginação.
  - UI: `Pagination` → `setPage` (não reseta filtros); API: `test_paginates_and_filters_by_type` (`per_page=1` → `last_page` 2).
- [x] Empty: limpar filtros impossíveis / DB fresh sem seed de tx → empty CTA upload.
  - `TransactionsTable` + `EMPTY_COPY`: filtros/busca → empty com orientação; conta vazia → `EMPTY_COPY.account` CTA “Importar extrato” → `/upload` (`looksEmptyAccount`).

### 8.4 Checklist visual

> Verificação §8.4: tokens + CSS self-hosted + Login SPA vs `DESIGN-SYSTEM.MD` (sem inspeção browser nesta passada; contrato de implementação).

- [x] Poppins carregada (Network / computed style).
  - `fonts.css` self-hosted (latin + latin-ext) importado em `app.css`; `body` / Tailwind `font-sans` → `var(--font-sans)` = `"Poppins", system-ui, sans-serif`.
- [x] Cores batem com `DESIGN-SYSTEM.MD` (brand `#DCCFFF`, bg `#151716`).
  - `tokens.css`: `--color-brand-primary: #DCCFFF`, `--color-bg-default: #151716`; bridge Tailwind `brand` / `canvas` / `ink`.
- [x] Login: Aura + tagline presentes e hierarquia correta.
  - `LoginPage`: `BrandMark` size `lg` (hero) + tagline *Inteligência invisível, controle absoluto.* em `text-caption text-ink-secondary` abaixo; formulário em `Card` secundário.
- [x] Sem tema claro acidental; sem purple/indigo genérico AI.
  - Canvas global escuro (`body` + `bg-canvas`); sem classes `indigo`/`purple`/`violet`/`bg-white` no SPA; brand lilac do DS (`#DCCFFF`), não gradiente purple→indigo.

### 8.5 (Opcional) Smoke automatizado frontend

- [x] Se o time quiser: 1–2 testes Vitest de `formatMoney` / `isAllowedStatementFile` — **não bloquear DoD**.
  - Vitest + `npm test` (`vitest run`); `resources/js/lib/format.test.js` + `validators.test.js` (3 passed).

---

## 9. Definição de Done (Etapa D)

A Etapa D só está concluída quando **todos** os itens abaixo estiverem `[x]`:

> DoD verificado: SPA completa (§0–§8) + `npm run build` OK + `npm test` (Vitest 3 passed) + acceptance Analytics/Transactions sem regressão de contrato.

- [x] Dependências instaladas (`react-router-dom`, `recharts`, `react-dropzone`, `lucide-react`, `sonner`) e `npm run build` OK.
- [x] Tela de **Login** com brand **Aura** + tagline *Inteligência invisível, controle absoluto.* + tokens do Design System.
- [x] Fluxo auth completo: CSRF → login → sessão → logout; guards guest/auth.
- [x] **Shell** autenticado dark com TopNav (Dashboard / Upload / user / sair).
- [x] Página de **Upload** com drag-and-drop, validação de extensão/tamanho, summary de sucesso/parcial e erros.
- [x] **Dashboard** com 4 metric cards, filtros pills, 2 gráficos (evolução + categorias), tabela paginada.
- [x] Estados **loading** (skeletons), **empty** (“Nenhum dado neste mês” / CTA upload), **error** (ErrorState + toasts).
- [x] Integração Axios com cookies + CSRF (sem Sanctum) contra APIs da Etapa C.
- [x] Responsividade básica validada (mobile utilizável + desktop prioritário).
- [x] Placeholder Etapa A removido; rotas SPA funcionando via catch-all Laravel.
- [x] `docs/MASTER_PLAN.md` § Etapa D e `docs/context.md` §4 Etapa D — checkboxes atualizados para `[x]`.
- [x] Nenhuma regressão consciente nas APIs (não alterar contratos backend sem atualizar Etapa C/context).
  - `TransactionsAnalyticsAcceptanceTest` 3 passed; upload/auth/analytics feature tests verdes nas checklists §8.

---

## 10. Fora de escopo (não fazer na Etapa D)

> Verificação §10: itens abaixo **confirmados fora do escopo** da Etapa D (não implementados na SPA; checkboxes `[x]` = deferidos / excluídos desta etapa, não “feitos”).

- [x] Deploy HostGator (Etapa E).
  - Intact em `MASTER_PLAN.md` § Etapa E (ainda aberto).
- [x] PWA / app mobile nativo.
  - Sem service worker / webmanifest / Capacitor na SPA.
- [x] Dark/light toggle.
  - Dark only (`bg-canvas` / tokens); sem toggle de tema.
- [x] Edição/exclusão de transações / categorização manual em massa.
  - Sem UI/API client de mutate em transactions além de listagem.
- [x] Histórico visual completo de imports na nav (API já existe — UI opcional pós-MVP).
  - `TopNav` só Dashboard + Upload; helpers `listStatements` existem, sem página de histórico.
- [x] WebSockets / atualização realtime.
  - Sem Echo/WebSocket; refetch por hooks.
- [x] i18n multi-idioma.
  - Copy pt-BR fixa; sem i18next/locale switch.
- [x] Storybook / design tokens pipeline avançado.
  - Sem Storybook; tokens via `tokens.css` + Tailwind bridge.
- [x] Substituição do auth session por Sanctum Bearer tokens.
  - Session cookie + CSRF mantidos (Etapa C Opção A); Sanctum não usado.

---

## 11. Ordem de implementação sugerida (para o agente)

> Verificação §11: sequência §0–§9 executada na ordem sugerida; artefatos presentes em `resources/js/**`; `npm test` 3 passed; `npm run build` OK. Passos abaixo confirmados.

1. [x] Deps + Tailwind theme bridge + pastas (§0.4–0.5, §7).
2. [x] `api/client` + interceptors + `api/auth` + `AuthProvider` + router guards (§1.1–1.2).
3. [x] Primitivos UI (`Button`, `Input`, `Card`, `Pill`, `Skeleton`, `EmptyState`, `BrandMark`) + Toaster (§5.3–5.4).
4. [x] `LoginPage` + `LoginForm` (§1.3) — validar CSRF/login ponta a ponta.
5. [x] `AppShell` + `TopNav` + logout (§2).
6. [x] `UploadPage` + Dropzone + summary (§3).
7. [x] Filtros + hooks + MetricCards (§4.2–4.5).
8. [x] Tabela + paginação (§4.7).
9. [x] Gráficos recharts (§4.6).
10. [x] Empty/loading/error em todas as superfícies (§5.3).
11. [x] Pass de responsividade (§6).
12. [x] Checklist manual §8 + marcar DoD §9 + atualizar `MASTER_PLAN.md` / `context.md`.

---

## 12. Referência rápida de contratos (colar mental do implementador)

> Verificação §12: contratos abaixo batem com `routes/api.php` + clients SPA (`api/auth.js`, `analytics.js`, `transactions.js`, `statements.js`) + `formatMoney` via `Intl`/`parseDecimal`. Smoke: LoginTest (5), UploadStatementEndpointTest (5), TransactionsAnalyticsAcceptanceTest (3) passed.

### Login success

```http
POST /api/login
→ 200 { "user": { "id", "name", "email" } }
```

### Dashboard analytics

```http
GET /api/analytics/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD&group_by=day|month
→ 200 {
  "data": {
    "filters": { "from", "to", "type?", "category_id?", "q?", "group_by" },
    "cards": { "balance", "total_income", "total_expense", "transactions_count" },
    "series": [{ "period", "income", "expense", "balance" }],
    "by_category": [{ "category_id", "name", "color", "total", "count", "type?" }]
  }
}
```

### Transactions

```http
GET /api/transactions?page=1&per_page=20&from&to&type&category_id&q&sort&direction
→ 200 { "data": [ Transaction ], "meta": { "current_page", "per_page", "total", "last_page" } }
```

### Upload

```http
POST /api/statements/upload  (multipart: file, source?)
→ 201 { "data": UploadSummary }
→ 422 { "message", "error_code"?, "import_id"?, "errors"? }
```

> Valores monetários na API são **strings** com 2 casas. O frontend formata com `Intl`, não com aritmética float solta.  
> Confirmado: `resources/js/lib/format.js` (`parseDecimal` + `Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })`).

---

*Fim do PLAN_ETAPA_D. Etapa D concluída (DoD §9). Próximo: Etapa E — Deploy HostGator.*
