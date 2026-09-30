# MASTER_PLAN — Aura

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises  
> *Aura: Inteligência invisível, controle absoluto.*  
> Mapa de execução (**Laravel 11 + React 19 + Vite 6** · HostGator · `aura.vonluqi.com`).  
> Fonte de contexto: `docs/context.md` · Design: `docs/DESIGN-SYSTEM.MD` · Setup: `README.md`.  
> Expansão pós-MVP: `docs/PLAN_EXPANSAO.md`.  
> Expansão Etapa H: `docs/PLAN_CARTOES_EMPRESTIMOS.md`.  
> Expansão Etapa I: `docs/PLAN_PERFIL_BRANDING.md`.

**Status do produto**

| Etapa | Nome | Status |
| --- | --- | --- |
| **A** | Setup | Concluída |
| **B** | Banco de Dados (MVP) | Concluída |
| **C** | Backend / Auth / Parse (MVP) | Concluída — `docs/PLAN_ETAPA_C.md` |
| **D** | Frontend (MVP) | Concluída — `docs/PLAN_ETAPA_D.md` (DoD §9) |
| **E** | Deploy HostGator | Concluída — `docs/PLAN_ETAPA_E.md` · `docs/DEPLOY_HOSTGATOR.md` |
| **F** | Multi-usuário, Metas e CRUD Avançado | Concluída (DoD §9.2) — `docs/PLAN_EXPANSAO.md` |
| **G** | Hardening, Cotas e Deploy da Expansão | Concluída (runbook §9.3) — cutover live = operador |
| **H** | Cartões, Empréstimos e Notificações | Concluída (DoD §7) — cutover live = operador — `docs/PLAN_CARTOES_EMPRESTIMOS.md` |
| **I** | Perfil, Branding e UX (Aura) | Pendente — `docs/PLAN_PERFIL_BRANDING.md` |

---

## Etapa A — Setup do projeto

- [x] Inicializar repositório Git e `.gitignore` (`.env`, `vendor`, `node_modules`, uploads).
- [x] Criar aplicação Laravel 11 + configurar `.env.example`.
- [x] Configurar Vite + React no frontend (`resources/js`).
- [x] Definir document root `public/` e regras Apache/`.htaccess`.
- [x] Instalar fonte Poppins e espelhar tokens CSS a partir de `docs/DESIGN-SYSTEM.MD`.
- [x] Configurar ambiente local (PHP, Composer, Node, MySQL).
- [x] Documentar variáveis de ambiente necessárias neste `context.md` se mudarem.
- [x] **(Pós-MVP / Etapa F)** Atualizar `.env.example` e `.env.production.example` com flags de cotas (`AURA_LIMIT_*`).
- [x] **(Pós-MVP / Etapa F)** Atualizar `docs/context.md`: escopo multi-usuário RBAC, limites e contratos (`config/aura.php`).
- [x] **(Etapa H)** Atualizar `.env.example` / `.env.production.example` com `AURA_FEATURE_CREDIT_CARDS`, `AURA_FEATURE_LOANS`, `AURA_FEATURE_NOTIFICATIONS` e janelas de alerta (`AURA_NOTIFY_*_DAYS`).
- [x] **(Etapa H)** Atualizar `docs/context.md`: cartões, empréstimos/cobranças, notificações in-app/e-mail e scheduler.
- [x] **(Etapa I)** Atualizar `docs/context.md`: perfil editável (nome/senha), `avatar_path`, URL pública de avatar e identidade visual (logo SVG + loading Aura).
- [x] **(Etapa I)** Atualizar `docs/DESIGN-SYSTEM.MD`: logo SVG canônica, tokens de glow/aura e estados de loading premium.
- [x] **(Etapa I)** Documentar em `docs/DEPLOY_HOSTGATOR.md` o `storage:link` **somente** para disk `public` (avatars) — extratos continuam privados, sem link para `statements`.

---

## Etapa B — Banco de Dados (MVP)

- [x] Migration `users` (+ seeder do admin único).
- [x] Migration `categories` (seed opcional mínimo).
- [x] Migration `statement_imports`.
- [x] Migration `transactions` com índices (`occurred_on`, `type`, `unique_hash`, `category_id`).
- [x] Constraints de unicidade para deduplicação.
- [x] Factories/seeders de desenvolvimento (transações fake para UI).

### B+ — Extensões de schema (Etapa F)

- [x] Adicionar `role` (enum/string) e campos de cotas em `users` + tabela `role_limits` (`docs/PLAN_EXPANSAO.md` §1.1).
- [x] Adicionar `user_id` direto em `transactions` (multi-tenant scope) e redefinir `unique_hash` para unicidade composta `(user_id, unique_hash)`.
- [x] Migration `goals` (metas de poupança e amortização de dívidas).
- [x] Migration `transaction_aliases` / `categorization_rules` (apelidos e regras memorizáveis).
- [x] Colunas de origem manual vs. importada em `transactions` (`source_kind`: `import|manual`, `statement_import_id` nullable).
- [x] Extender `statement_imports.format` / `source` para faturas de cartão (`csv_credit_card`, `nubank_credit`, etc.).
- [x] Seeders: papéis Admin / Subadmin / Visitante / Teste + limites default (`RoleLimitsSeeder` + `AdminUserSeeder` + `DemoRoleUsersSeeder` local).

### B++ — Extensões de schema (Etapa H)

- [x] Migration `credit_cards` (cadastro de cartões por `user_id`: nome, limite, dia de fechamento, dia de vencimento).
- [x] Migration `loans` (empréstimos a terceiros: devedor, valor, data de cobrança, status, kind cash|card_limit).
- [x] Migration: `transactions.credit_card_id` e `transactions.loan_id` (FKs nullable).
- [x] Migration nativa Laravel `notifications` (`php artisan notifications:table`).
- [x] Factories/seeders de desenvolvimento para cartões e empréstimos.

### B+++ — Extensões de schema (Etapa I)

- [x] Migration: `users.avatar_path` string nullable (path relativo no disk `public`, ex.: `avatars/{user_id}/{uuid}.webp`).
- [x] Sem backfill obrigatório (default `null` = fallback visual de iniciais / BrandMark).
- [x] Factory `UserFactory`: estado opcional `withAvatar()` para testes de upload/remoção.

---

## Etapa C — Backend / Auth / Parse (MVP)

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

### C+ — APIs e domínio da expansão (Etapa F)

- [x] Middleware / Gates / Policies RBAC (`Admin`, `Subadmin`, `Visitante`, `Teste`) — padrão Laravel `Gate` + Policies (§2.1).
- [x] Middleware de cotas de uso (uploads, CRUD manual) por papel (`quota:*` + `UsageLimitService`).
- [x] CRUD manual de transações: `POST/PUT/PATCH/DELETE /api/transactions` + FormRequests + `TransactionPolicy` (§3.1).
- [x] `NubankCreditCardCsvParser` (Strategy) + detecção no `StatementFormatDetector` / `StatementParserResolver`.
- [x] Motor de Aliases no pipeline de parse (`AliasResolutionService` antes de persistir) (§4.1).
- [x] Endpoints de aliases/regras (`/api/aliases` + preview + remember-alias) (§4.2).
- [x] Endpoints de metas (`/api/goals`) + resumo `goals` (+ cards §7.3) no `GET /api/analytics/dashboard`.
- [x] Isolamento multi-tenant: `TransactionQueryService` filtra `transactions.user_id` + bindings por owner (§2.2).
- [x] Gestão de usuários (Admin): `GET/POST/PATCH/DELETE /api/users` + reset de cotas (§2.3).
- [x] Cap de date range por papel nos FormRequests (`WithinRoleDateRangeLimit` / §2.4); presets flexíveis completos na §6.
- [x] Adaptar `DashboardAnalyticsRequest` / `IndexTransactionsRequest` para presets (`preset=…`) e date range livre completo (§6.1); contrato URL/JSDoc na §6.2 ✔.

### C++ — APIs e domínio (Etapa H)

- [x] Abilities `credit_cards.manage`, `loans.manage`, `notifications.read` em `config/aura.php` + Gates.
- [x] CRUD `/api/credit-cards` + `CreditCardPolicy` + FormRequests.
- [x] CRUD `/api/loans` (+ mark-paid / status) + `LoanPolicy` + FormRequests.
- [x] Extender store/update de transações para aceitar `credit_card_id` e `loan_id` (ownership check).
- [x] Notifications Laravel (database + mail) + endpoints `/api/notifications`.
- [x] Command `aura:check-due-dates` + schedule diário em `routes/console.php`.

### C+++ — APIs e domínio (Etapa I)

- [x] `ProfileController` (`php artisan make:controller ProfileController`) — self-service do usuário autenticado (não confundir com `UserController` admin).
- [x] Rotas autenticadas: `PATCH /api/profile`, `POST /api/profile/avatar`, `DELETE /api/profile/avatar` (+ throttle).
- [x] FormRequests: `UpdateProfileRequest`, `UploadAvatarRequest`.
- [x] Upload seguro no disk `public` sob `avatars/{user_id}/`; validar mime (`jpeg|png|webp`), tamanho máx. e apagar arquivo antigo ao substituir/remover.
- [x] Extender `AuthUserResource` com `avatar_url` (URL pública via `Storage::disk('public')->url(...)` ou `null`).
- [x] Extender `User` model: `avatar_path` fillable + accessor/helper `avatarUrl()`.

---

## Etapa D — Frontend (MVP)

> DoD: `docs/PLAN_ETAPA_D.md` §9 — SPA Login / Shell / Upload / Dashboard + Axios session/CSRF + responsividade.

- [x] Tela de Login (brand **Aura** + tagline *Inteligência invisível, controle absoluto.* + Design System).
- [x] Layout autenticado (nav, shell dark).
- [x] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [x] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [x] Estados: loading, empty, error.
- [x] Integração com API autenticada (cookies/CSRF conforme stack).
- [x] Responsividade básica.

### D+ — UI da expansão (Etapa F)

- [x] Modais de Criar / Editar / Excluir transação (confirmação destrutiva).
- [x] Date Range Picker flexível no dashboard (substituir/ampliar `PeriodPills` + sync URL).
- [x] Upload: detecção/parser de fatura Cartão de Crédito CSV (backend §5.1); seletor UI em §8.
- [x] UI de Metas no Dashboard (progresso poupança / amortização).
- [x] Painel Admin: tabela de usuários, papéis, cotas e status.
- [x] UI de Apelidos/Regras (lista + criar a partir de lançamento).
- [x] Guards de UI por papel (`can` / hide actions para Visitante/Teste conforme limites).

### D++ — UI (Etapa H)

- [x] Aba **Cartões** (`/cards`) — CRUD de cartões cadastrados.
- [x] Aba **Empréstimos / Cobranças** (`/loans`) — CRUD + status de pagamento.
- [x] `TransactionFormModal`: vínculo “feito no cartão X” e/ou “para a pessoa Y” (loan).
- [x] Sino de notificações no `TopNav` (lista in-app + marcar lida).
- [x] Entradas em `NAV_CATALOG` + `AbilityRoute` + feature flags no `AuthUserResource`.

### D+++ — UI (Etapa I)

- [x] Página/modal **Conta / Perfil** (`/account` ou modal a partir do `TopNav`): editar nome, alterar senha, upload/remoção de avatar.
- [x] API client `resources/js/api/profile.js` + atualização imediata do `AuthContext` (`setUser` / `refreshUser`) após PATCH/upload.
- [x] Substituir o ponto `bg-brand` do `BrandMark` por logo SVG inline “aura abstrata” (`#DCCFFF` sobre `#151716`).
- [x] Loading global “Aura / Instinto Superior”: componente `AuraLoader` + keyframes Tailwind (pulse, glow multicamadas) no splash de sessão e rotas protegidas.

---

## Etapa E — Deploy (HostGator / aura.vonluqi.com)

> Guia: `docs/DEPLOY_HOSTGATOR.md` · template: `.env.production.example`.  
> DoD: `docs/PLAN_ETAPA_E.md` §9.

- [x] Criar banco MySQL no cPanel e usuário com permissões mínimas.
- [x] Configurar `.env` de produção (`APP_URL=https://aura.vonluqi.com`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`).
- [x] Apontar subdomínio `aura.vonluqi.com` para `…/aura/public` (nunca a raiz do Laravel).
- [x] Instalar dependências (`composer install --no-dev`, `npm ci && npm run build`).
- [x] Rodar migrations + seeder do admin (`ADMIN_EMAIL` / `ADMIN_PASSWORD` fortes).
- [x] Garantir permissões em `storage/` e `bootstrap/cache/`.
- [x] Configurar cron cPanel: `* * * * * php …/artisan schedule:run` (purge de extratos).
- [x] Validar HTTPS, login, upload Nubank e dashboard em produção.
- [x] Backup inicial do banco e checklist de rollback.
- [x] **(Etapa H)** Confirmar cron `schedule:run` lista `aura:check-due-dates` além de `statements:purge-files` — agendado em `routes/console.php`; validação pós-deploy em `DEPLOY_HOSTGATOR.md` §5.7 / §6.3.
- [x] **(Etapa H)** Configurar `MAIL_*` em produção se canal e-mail de notificações estiver ativo — default `MAIL_MAILER=log` (`.env.production.example`); SMTP só se e-mail real for desejado (§5.7).
- [x] **(Etapa I)** Backup MySQL antes da migration `avatar_path` — runbook `DEPLOY_HOSTGATOR.md` §5.8.
- [x] **(Etapa I)** `php artisan migrate --force` + `php artisan storage:link` (symlink `public/storage` → `storage/app/public`) — **não** expor disk `statements` — §5.8 + CI deploy/migrate.
- [x] **(Etapa I)** Validar `GET https://aura.vonluqi.com/storage/avatars/...` após upload de avatar em produção — checklist smoke §5.8 (cutover live pós-merge).
- [x] **(Etapa I)** Smoke: editar nome, trocar senha, upload/remoção de avatar, splash com `AuraLoader`, logo SVG no Login e TopNav — §5.8.

---

## Etapa F — Multi-usuário, Metas e CRUD Avançado

> Plano hiperdetalhado: `docs/PLAN_EXPANSAO.md`.  
> Objetivo: sair do single-admin e entregar os 6 pilares de produto abaixo, sem quebrar o MVP live.

### Pilares de produto

| # | Pilar | Camadas |
| --- | --- | --- |
| 1 | CRUD manual de transações | DB + API + Modal UI |
| 2 | Parser CSV de fatura Cartão de Crédito | Detector + Parser + Upload UX |
| 3 | Date Range Picker flexível | Request validation + Dashboard filters |
| 4 | Multi-usuário + RBAC + cotas | Schema + Middleware/Policies + Admin UI |
| 5 | Metas financeiras (poupança / dívidas) | `goals` + Analytics + widgets |
| 6 | Motor de Apelidos/Regras (Aliases) | `transaction_aliases` + parse pipeline + UI |

### Checklist macro

- [x] Atualizar `docs/context.md` (escopo multi-usuário, papéis, limites, parsers) — §0.
- [x] Migrations + seeders RBAC / goals / aliases / `user_id` em transactions (+ `DemoRoleUsersSeeder` local).
- [x] Backend: Policies, middleware de cotas, CRUD, CC parser, aliases, goals, date range.
- [x] Frontend: modais CRUD, range picker, metas, admin users, aliases, upload CC.
- [x] Testes Feature/Unit cobrindo isolamento por `user_id`, RBAC e dedupe por usuário.
- [x] Feature flags / env para rollout gradual em produção (Etapa G) — `AURA_FEATURE_*`.

### Papéis (RBAC) — contrato de produto

| Papel | Capacidade resumida |
| --- | --- |
| **Admin** | CRUD total, gestão de usuários, cotas, metas, aliases, uploads ilimitados (ou teto alto). |
| **Subadmin** | CRUD de dados financeiros próprios (e, se definido, assistidos); sem gestão global de usuários. |
| **Visitante** | Leitura + cotas baixas de upload/CRUD; sem gestão de usuários. |
| **Teste** | Ambiente limitado (cotas agressivas, dados isolados); ideal para demos/QA. |

> **Etapa H:** Admin/Subadmin/Visitante/Teste recebem `credit_cards.manage` e `loans.manage` (com cotas Visitante/Teste se definidas); `notifications.read` para todos autenticados ativos.  
> **Etapa I:** qualquer usuário autenticado ativo edita **o próprio** perfil (nome, senha, avatar); e-mail permanece imutável via self-service (alteração só via Admin se necessário).

---

## Etapa G — Hardening, Cotas e Deploy da Expansão

- [x] Backup completo do MySQL antes das migrations de multi-tenant — procedimento em `DEPLOY_HOSTGATOR.md` §5.6 (obrigatório no cutover).
- [x] Rodar migrations em staging/local espelhando produção (`php artisan migrate --force` só após backup) — migrations + CI SSH.
- [x] Backfill: `aura:backfill-transaction-user-id` (idempotente; também na `141100`).
- [x] Recriar índice único: dropar `unique_hash` global → unique `(user_id, unique_hash)` (migration `141100`).
- [x] Validar cotas Visitante/Teste (HTTP 429 / 403 com mensagens claras).
- [x] Smoke checklist: suite Expansion + checklist operador DEPLOY §5.6 (execução live pós-merge).
- [x] Atualizar `docs/DEPLOY_HOSTGATOR.md` e README com novos comandos/seeders / feature flags.
- [x] Monitorar storage de extratos e cron de purge — retenção `STATEMENT_RETENTION_DAYS` + cron cPanel já no runbook Etapa E; reforçado em §5.6 sob multi-usuário.

---

## Etapa H — Cartões, Empréstimos e Notificações

> Plano hiperdetalhado: `docs/PLAN_CARTOES_EMPRESTIMOS.md`.  
> Objetivo: cadastrar cartões de crédito, vincular despesas a faturas/cartões, controlar empréstimos a terceiros (dinheiro ou limite do cartão) e alertar vencimentos/cobranças via notificações in-app e e-mail.  
> Pré-requisito: Etapas A–G concluídas (multi-tenant + cron HostGator ativos).

### Pilares de produto

| # | Pilar | Camadas |
| --- | --- | --- |
| 1 | Gestão de Cartões de Crédito | `credit_cards` + CRUD API + aba Cartões |
| 2 | Vínculo de Faturas / Transações | `transactions.credit_card_id` (+ UI no modal) |
| 3 | Empréstimos / Cobranças | `loans` + `transactions.loan_id` + aba Cobranças |
| 4 | Sistema de Notificações | `notifications` + `aura:check-due-dates` + sino no Shell |

### Distinção importante

- **Parser CSV de fatura** (Etapa F §5) importa lançamentos de extrato Nubank crédito — **não** substitui o cadastro de cartões.
- **Cadastro de cartões** (Etapa H) é entidade de domínio (limite, fechamento, vencimento) à qual transações podem ser associadas.
- **Metas `debt_payoff`** (Etapa F) = amortização de dívida própria; **Empréstimos** (Etapa H) = valores a cobrar de terceiros.

### Checklist macro

- [x] Atualizar `docs/context.md` + env examples + `config/aura.php` (abilities, features, notify windows) — §0 do plano H.
- [x] Migrations: `credit_cards`, `loans`, FKs em `transactions`, tabela `notifications`.
- [x] Backend: Models, Policies, Controllers, Services, Notifications, Command + schedule.
- [x] Frontend: páginas Cartões e Empréstimos, vínculo no modal de transação, NotificationBell.
- [x] Testes Feature/Unit (isolamento `user_id`, due-date command, mark-paid, unread).
- [x] Deploy: migrate + flags + validar `schedule:list` com `aura:check-due-dates` — runbook `DEPLOY_HOSTGATOR.md` §5.7 (cutover live pós-merge).

---

## Etapa I — Perfil, Branding e UX (Aura)

> Plano hiperdetalhado: `docs/PLAN_PERFIL_BRANDING.md`.  
> Objetivo: personalização de conta (perfil + avatar), identidade visual definitiva (logo SVG) e loading premium alinhado à tagline *Inteligência invisível, controle absoluto.*  
> Pré-requisito: Etapas A–H concluídas (auth session, `AuthUserResource`, Design System dark, deploy HostGator documentado).

### Pilares de produto

| # | Pilar | Camadas |
| --- | --- | --- |
| 1 | Gestão de Perfil | `users.avatar_path` + `ProfileController` + página/modal Conta |
| 2 | Nova Logo (SVG) | `BrandMark` SVG inline + Design System + Login / Splash / Nav |
| 3 | Loading State Avançado | `AuraLoader` + keyframes Tailwind (glow `#DCCFFF`) |

### Distinção importante

- **`UserController` (Admin)** gerencia outros usuários (papel, cotas, ativar/desativar) — **não** é o fluxo de “minha conta”.
- **`ProfileController` (self-service)** edita apenas o `auth()->user()`: nome, senha e avatar.
- **Disk `public` + `storage:link`** serve avatars em `/storage/...`.  
  **Disk `statements`** permanece privado (`serve=false`) — **nunca** usar `storage:link` para expor extratos.
- **Logo SVG** substitui o círculo sólido atual do `BrandMark`; tipografia wordmark e tagline permanecem conforme `docs/DESIGN-SYSTEM.MD`.
- **AuraLoader** eleva o splash/spinner atual (`AuthSplash`, `ProtectedRoute`) a uma animação de energia/aura premium — sem estética cartoon.

### Checklist macro

- [x] Atualizar `docs/context.md` + `DESIGN-SYSTEM.MD` + `DEPLOY_HOSTGATOR.md` (avatar público vs statements privados).
- [x] Migration `users.avatar_path` + Model/Factory.
- [x] Backend: `ProfileController`, FormRequests, rotas `/api/profile*`, `AuthUserResource.avatar_url`.
- [x] Frontend: API profile, UI Conta/Perfil, sync `AuthContext`, avatar no `TopNav`.
- [x] Logo SVG no `BrandMark` (+ Login / AbilityRoute / AuthSplash).
- [x] `AuraLoader` + keyframes em `tailwind.config.js` / CSS tokens.
- [x] Testes Feature (update profile, upload/delete avatar, validação mime/size) + smoke visual.
- [x] Deploy HostGator: backup → migrate → `storage:link` → smoke avatar URL — runbook §5.8; **cutover live** = merge `main` + CI.

---

## Ordem Sugerida de Entrega

> **A–E (MVP)** ✅ → **F** (expansão multi-user) ✅ → **G** (hardening) ✅ → **H** (cartões / empréstimos / notificações) ✅ → **I.DB** → **I.Profile API** → **I.Profile UI** → **I.Logo SVG** → **I.AuraLoader** → **I.Deploy**

Detalhamento técnico da expansão F–G: **`docs/PLAN_EXPANSAO.md`**.  
Detalhamento técnico exclusivo da Etapa H: **`docs/PLAN_CARTOES_EMPRESTIMOS.md`**.  
Detalhamento técnico exclusivo da Etapa I: **`docs/PLAN_PERFIL_BRANDING.md`**.
