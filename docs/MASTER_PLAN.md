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

- [ ] Tela de Login (brand **Aura** + tagline *Inteligência invisível, controle absoluto.* + Design System).
- [ ] Layout autenticado (nav, shell dark).
- [ ] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [ ] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [ ] Estados: loading, empty, error.
- [ ] Integração com API autenticada (cookies/CSRF conforme stack).
- [ ] Responsividade básica.

---



## Etapa E — Deploy (HostGator / aura.vonluqi.com)

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

