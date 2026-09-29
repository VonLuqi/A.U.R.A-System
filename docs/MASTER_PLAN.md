# MASTER_PLAN — Aura

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises  
> *Aura: Inteligência invisível, controle absoluto.*  
> Mapa de execução do MVP (**Laravel 11 + React 19 + Vite 6** · HostGator · `aura.vonluqi.com`).  
> Fonte de contexto: `docs/context.md` · Design: `docs/DESIGN-SYSTEM.MD` · Setup: `README.md`.

**Status do MVP**

| Etapa | Nome | Status |
| --- | --- | --- |
| **A** | Setup | Concluída |
| **B** | Banco de Dados | Concluída |
| **C** | Backend / Auth / Parse | Concluída — `docs/PLAN_ETAPA_C.md` |
| **D** | Frontend | Concluída — `docs/PLAN_ETAPA_D.md` (DoD §9) |
| **E** | Deploy HostGator | Concluída — `docs/PLAN_ETAPA_E.md` (DoD §9) · `docs/DEPLOY_HOSTGATOR.md` |

---

## Etapa A — Setup do projeto

- [x] Inicializar repositório Git e `.gitignore` (`.env`, `vendor`, `node_modules`, uploads).
- [x] Criar aplicação Laravel 11 + configurar `.env.example`.
- [x] Configurar Vite + React no frontend (`resources/js`).
- [x] Definir document root `public/` e regras Apache/`.htaccess`.
- [x] Instalar fonte Poppins e espelhar tokens CSS a partir de `docs/DESIGN-SYSTEM.MD`.
- [x] Configurar ambiente local (PHP, Composer, Node, MySQL).
- [x] Documentar variáveis de ambiente necessárias neste `context.md` se mudarem.

---

## Etapa B — Banco de Dados

- [x] Migration `users` (+ seeder do admin único).
- [x] Migration `categories` (seed opcional mínimo).
- [x] Migration `statement_imports`.
- [x] Migration `transactions` com índices (`occurred_on`, `type`, `unique_hash`, `category_id`).
- [x] Constraints de unicidade para deduplicação.
- [x] Factories/seeders de desenvolvimento (transações fake para UI).

---

## Etapa C — Backend / Auth / Parse

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

---

## Etapa D — Frontend

> DoD: `docs/PLAN_ETAPA_D.md` §9 — SPA Login / Shell / Upload / Dashboard + Axios session/CSRF + responsividade. README raiz atualizado.

- [x] Tela de Login (brand **Aura** + tagline *Inteligência invisível, controle absoluto.* + Design System).
- [x] Layout autenticado (nav, shell dark).
- [x] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [x] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [x] Estados: loading, empty, error.
- [x] Integração com API autenticada (cookies/CSRF conforme stack).
- [x] Responsividade básica.

---

## Etapa E — Deploy (HostGator / aura.vonluqi.com)

> Guia: `docs/DEPLOY_HOSTGATOR.md` · template: `.env.production.example`.  
> DoD: `docs/PLAN_ETAPA_E.md` §9 — HostGator Cenário A + CI FTP + SSL + migrate/seed + cron + validação live.

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

## Ordem Sugerida de Entrega

> **A (Setup)** → **B (DB)** → **C.Auth** → **C.Parse/Upload** → **C.APIs Dashboard** → **D (UI)** ✅ → **E (Deploy)** ✅
