# MASTER_PLAN — Aura

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises  
> *Aura: Inteligência invisível, controle absoluto.*  
> Mapa de execução (**Laravel 11 + React 19 + Vite 6** · HostGator · `aura.vonluqi.com`).  
> Fonte de contexto: `docs/context.md` · Design: `docs/DESIGN-SYSTEM.MD` · Setup: `README.md`.  
> Expansão pós-MVP: `docs/PLAN_EXPANSAO.md`.

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

## Ordem Sugerida de Entrega

> **A–E (MVP)** ✅ → **F.DB (B+)** → **F.RBAC/Cotas** → **F.CRUD Manual** → **F.Aliases** → **F.CC Parser** → **F.Date Range** → **F.Metas** → **F.Admin UI** → **G (Deploy/Hardening)**

Detalhamento técnico exclusivo da expansão: **`docs/PLAN_EXPANSAO.md`**.
