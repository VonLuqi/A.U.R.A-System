# MASTER_PLAN — Aura

> **A.U.R.A.** — Assistente Unificado de Recursos e Análises  
> *Aura: Inteligência invisível, controle absoluto.*  
> Mapa de execução do MVP (**Laravel 11 + React + Vite** · HostGator · `vonluqi.com`).  
> Fonte de contexto: `docs/context.md` · Design: `docs/DESIGN-SYSTEM.MD`.

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

- [ ] Migration `users` (+ seeder do admin único).
- [ ] Migration `categories` (seed opcional mínimo).
- [ ] Migration `statement_imports`.
- [ ] Migration `transactions` com índices (`occurred_on`, `type`, `unique_hash`, `category_id`).
- [ ] Constraints de unicidade para deduplicação.
- [ ] Factories/seeders de desenvolvimento (transações fake para UI).

---

## Etapa C — Backend / Auth / Parse

- [ ] Autenticação session (login, logout, middleware `auth`).
- [ ] Desabilitar/omitir registro público.
- [ ] Rate limiting em `login` e `statements/upload`.
- [ ] Service `StatementUploadService` (orquestração).
- [ ] `NubankCsvParser` com testes unitários (fixtures reais anonimizadas).
- [ ] `OfxParser` (ou adapter de lib) com testes.
- [ ] Persistência atômica (import + transactions em transação DB).
- [ ] Endpoint `POST` upload + resposta de resumo.
- [ ] Endpoints de leitura: listagem filtrada, agregados do dashboard.
- [ ] Storage privado e política de retenção simples dos arquivos.

---

## Etapa D — Frontend

- [ ] Tela de Login (brand **Aura** + tagline *Inteligência invisível, controle absoluto.* + Design System).
- [ ] Layout autenticado (nav, shell dark).
- [ ] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [ ] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [ ] Estados: loading, empty, error.
- [ ] Integração com API autenticada (cookies/CSRF conforme stack).
- [ ] Responsividade básica.

---

## Etapa E — Deploy (HostGator / vonluqi.com)

- [ ] Criar banco MySQL no cPanel e usuário com permissões mínimas.
- [ ] Configurar `.env` de produção (`APP_URL=https://vonluqi.com`, `APP_DEBUG=false`).
- [ ] Apontar domínio / subdomínio para `public/`.
- [ ] Instalar dependências (`composer install --no-dev`, `npm ci && npm run build`).
- [ ] Rodar migrations + seeder do admin.
- [ ] Garantir permissões em `storage/` e `bootstrap/cache/`.
- [ ] Validar HTTPS, login, upload Nubank e dashboard em produção.
- [ ] Backup inicial do banco e checklist de rollback.

---

## Ordem Sugerida de Entrega

> **A (Setup)** → **B (DB)** → **C.Auth** → **C.Parse/Upload** → **C.APIs Dashboard** → **D (UI)** → **E (Deploy)**
