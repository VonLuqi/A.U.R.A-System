# Aura — Contexto do Projeto

> **Fonte da verdade** para decisões de produto, arquitetura e implementação.
> Qualquer agente de IA ou desenvolvedor deve consultar este arquivo antes de propor ou alterar código.
> Atualize este documento quando escopo, stack ou restrições mudarem.

---

## 1. Visão do Projeto

### Identidade

| Item | Valor |
| --- | --- |
| Nome comercial | **Aura** |
| Sigla | **A.U.R.A.** |
| Expansão | **A**ssistente **U**nificado de **R**ecursos e **A**nálises |
| Posicionamento | Foco em **inteligência** e **automação** (tecnologia preditiva + centralização de dados) |
| Tagline | *Aura: Inteligência invisível, controle absoluto.* |
| Domínio de produção | `vonluqi.com` |
| UI / Design tokens | Ver `docs/DESIGN-SYSTEM.MD` |

**Por que o nome funciona**

- **Assistente** — destaca o papel de automação inteligente (ex.: categorização de compras; ML como evolução do produto).
- **Unificado** — remete à centralização dos extratos (início: Nubank) em uma única fonte de verdade.
- **Recursos e Análises** — cobre tanto o dinheiro/movimentos quanto o dashboard analítico.

### Propósito

Plataforma web de **controle financeiro pessoal** orientada a inteligência e automação, de uso **exclusivo e individual** (single-user). O administrador importa extratos, deixa o sistema organizar e analisar os dados, e acompanha a saúde financeira com o mínimo de atrito operacional.

### Objetivos

- Centralizar extratos bancários (início: **Nubank**, formatos CSV/OFX) em um banco estruturado (**Unificado**).
- Automatizar o máximo possível do pós-importação (categorização e análises; regras no curto prazo, ML no médio prazo — **Assistente**).
- Eliminar planilhas manuais como fonte primária de verdade.
- Oferecer visualização clara de entradas, saídas e tendências com filtros dinâmicos (**Análises**).
- Manter superfície de ataque mínima: **apenas o administrador** autenticado acessa o sistema.

### Voice & UI copy (marca)

- Na tela de login, header e Figma: marca **Aura** em hierarquia hero; tagline minimalista abaixo ou como apoio:
  - **Aura: Inteligência invisível, controle absoluto.**
- Evitar jargão técnico na UI (“ML”, “pipeline”); preferir linguagem de assistência e clareza.
- Tom: preciso, calmo, premium — inteligência que trabalha em segundo plano.

### Fora de escopo (MVP)

- Multi-usuário / multi-tenant
- Open Banking / APIs bancárias em tempo real
- App mobile nativo
- Contas a pagar/receber com recorrência automática (pode entrar em fases posteriores)
- Integrações com cartões além do fluxo de upload de extrato
- Motor de Machine Learning completo em produção (visão de produto; no MVP: importação + dashboard; categorização automática evolui após o core)

### Domínio e branding (resumo)

| Item | Valor |
| --- | --- |
| Domínio de produção | `vonluqi.com` |
| URL do app Aura (HostGator) | `https://aura.vonluqi.com` |
| Nome do produto / marca | **Aura** (A.U.R.A.) |
| Tagline | Inteligência invisível, controle absoluto. |
| UI / Design tokens | Ver `docs/DESIGN-SYSTEM.MD` (dark theme, Poppins, brand `#DCCFFF`) |

---

## 2. Arquitetura e Restrições

### 2.1 Ambiente de produção

| Item | Detalhe |
| --- | --- |
| Hospedagem | **HostGator** (infraestrutura tradicional / cPanel) |
| Conta cPanel | `luca9682` · home `/home4/luca9682` |
| Domínio principal | `vonluqi.com` → Document Root fixo `/public_html` (não editável) |
| App Aura | Subdomínio `aura.vonluqi.com` → Document Root `/home4/luca9682/aura/public` |
| Código Laravel | `/home4/luca9682/aura` (pai de `public/` — `.env`, `app/`, `vendor/`, `storage/` fora do web root) |
| SSL | Obrigatório (Let's Encrypt / certificado do painel) |
| Deploy | GitHub Actions → FTP (`aura/`) + SSH (extract `vendor` / migrate); ver `docs/DEPLOY_HOSTGATOR.md` |
| Cron | Disponível via cPanel (jobs de limpeza, se necessário) |

**Implicações:**

- Preferir stack **PHP + MySQL**, nativa e estável em shared/cPanel HostGator.
- Evitar dependência de workers Node persistentes, Redis obrigatório ou containers (não disponíveis no plano tradicional típico).
- Uploads de extrato devem respeitar limites de `upload_max_filesize` / `post_max_size` do PHP.
- Sessões e arquivos sensíveis **fora** do document root: só `aura/public` é servido; resto em `aura/`.
- Princípio: **nunca** apontar o document root para a raiz do Laravel.

### 2.2 Stack recomendada (MVP)

> Placeholders marcados com `[CONFIRMAR]` podem ser trocados; a recomendação abaixo é a default para HostGator tradicional.

| Camada | Tecnologia | Notas |
| --- | --- | --- |
| Linguagem backend | **PHP 8.2+** `[CONFIRMAR]` | Compatível com HostGator |
| Framework backend | **Laravel 11** `[CONFIRMAR]` | Auth, validação, filas sync, Eloquent, storage |
| Banco de dados | **MySQL 8 / MariaDB** | Padrão cPanel |
| Autenticação | Session cookie (Laravel Auth / Breeze) | Um único usuário admin; sem registro público |
| Frontend | **React 18 + Vite** `[CONFIRMAR]` | SPA consumindo API JSON do Laravel; tokens do Design System |
| Estilo | CSS variables + CSS Modules / Tailwind `[CONFIRMAR]` | Tokens espelhando `docs/DESIGN-SYSTEM.MD` |
| Parse de extratos | Parser próprio (CSV Nubank) + lib OFX (ex.: `asgrim/ofxparser` ou equivalente) | Endpoint dedicado e autenticado |
| Storage de uploads | Disco privado (`storage/app/private/statements`) | Não servir arquivos brutos publicamente |
| Servidor web | Apache (HostGator) + `public/` como document root | `.htaccess` do Laravel |

### 2.3 Princípios arquiteturais

1. **Single-admin:** não há cadastro aberto; credenciais apenas do administrador.
2. **API-first interno:** backend expõe rotas JSON autenticadas; frontend é cliente.
3. **Parse no servidor:** o browser só envia o arquivo; normalização e persistência são responsabilidade do backend.
4. **Idempotência na importação:** evitar duplicar transações do mesmo extrato (hash / chave composta).
5. **Segurança por padrão:** CSRF (se session), rate limit no login e no upload, validação de MIME/extensão, sanitização.
6. **Design System como contrato visual:** componentes alinhados a `docs/DESIGN-SYSTEM.MD`.

### 2.4 Estrutura de pastas sugerida (alto nível)

```text
/
├── context.md                 ← este arquivo
├── docs/
│   └── DESIGN-SYSTEM.MD
├── app/                       ← Laravel (Models, Http, Services, Parsers)
├── database/migrations/
├── routes/web.php | api.php
├── resources/js/              ← React (Dashboard, Auth, Upload)
├── storage/app/private/
└── public/                    ← document root no HostGator
```

### 2.5 Variáveis de ambiente

> Template versionado: `.env.example`. Valores reais ficam só em `.env` / `.env.production` (gitignored).
> Qualquer mudança de env atualiza **ambos**: `.env.example` e esta seção.

| Variável | Obrigatória | Exemplo local | Exemplo produção | Descrição |
| --- | --- | --- | --- | --- |
| `APP_NAME` | sim | `Aura` | igual | Nome da app |
| `APP_ENV` | sim | `local` | `production` | Ambiente |
| `APP_KEY` | sim | `(gerada)` | `(gerada única)` | Chave de criptografia (`php artisan key:generate`) |
| `APP_DEBUG` | sim | `true` | `false` | Nunca `true` em prod |
| `APP_URL` | sim | `http://localhost:8000` | `https://aura.vonluqi.com` | URL canônica |
| `APP_TIMEZONE` | sim | `America/Sao_Paulo` | igual | Fuso |
| `DB_CONNECTION` | sim | `mysql` | `mysql` | Driver |
| `DB_HOST` | sim | `127.0.0.1` | `localhost` (cPanel) | Host DB |
| `DB_PORT` | sim | `3306` | `3306` | Porta |
| `DB_DATABASE` | sim | `aura` | `(nome cPanel)` | Database |
| `DB_USERNAME` | sim | `aura_dev` | `(user cPanel)` | Usuário |
| `DB_PASSWORD` | sim | `(local)` | `(prod)` | Senha — **nunca** no Git nem neste doc |
| `SESSION_DRIVER` | sim | `database` | `database` | Sessões |
| `CACHE_STORE` | sim | `database` | `database` | Cache |
| `FILESYSTEM_DISK` | sim | `local` | `local` | Disco default |
| `QUEUE_CONNECTION` | sim | `sync` | `sync` | Filas (MVP sync) |
| `LOG_LEVEL` | sim | `debug` | `error` | Verbosity |
| `VITE_APP_NAME` | não | `${APP_NAME}` | igual | Exposto ao front |

**Decisões Etapa A**

| Decisão | Valor | Motivo |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | Persistência de sessão via MySQL (tabelas Laravel); adequado a HostGator sem Redis |
| `CACHE_STORE` | `database` | Cache via MySQL; evita dependência de Redis/Memcached no shared hosting |
| Document root (prod) | `/home4/luca9682/aura/public` | Cenário A: subdomínio `aura.vonluqi.com` → só `public/`; código Laravel em `/home4/luca9682/aura` |
| `APP_URL` (prod) | `https://aura.vonluqi.com` | URL canônica do app (domínio principal `vonluqi.com` permanece em `/public_html`) |

Variáveis adicionais presentes em `.env.example` (locale, mail log, Redis opcional, AWS placeholders) seguem defaults Laravel; não são críticas ao MVP HostGator e podem permanecer como no template.

---

## 3. Detalhamento do MVP

### 3.1 Autenticação — acesso único do Administrador

**Objetivo:** garantir que somente o administrador autentique e use a plataforma.

**Requisitos funcionais**

- Tela de login (e-mail/usuário + senha).
- Sessão persistente segura (cookie HttpOnly, Secure, SameSite).
- Logout explícito.
- **Sem** rota de registro público; usuário admin criado via seeder/artisan ou instalação inicial.
- Rotas protegidas: upload, listagem, dashboard e APIs de filtro exigem autenticação.
- Após N falhas de login, aplicar throttle (ex.: rate limit Laravel).

**Critérios de aceite**

- [ ] Usuário não autenticado é redirecionado ao login.
- [ ] Apenas o admin consegue autenticar com sucesso.
- [ ] Sessão expira conforme configuração e o logout invalida o acesso.

---

### 3.2 Ingestão de Dados (CSV/OFX) — foco Nubank

**Objetivo:** endpoint seguro de upload que faz parse do extrato, extrai transações e persiste no banco.

**Requisitos funcionais**

- Endpoint autenticado: `POST /api/statements/upload` (nome final `[CONFIRMAR]`).
- Aceitar, no mínimo:
  - **CSV** no padrão exportado pelo app/site Nubank.
  - **OFX** quando disponível.
- Fluxo:
  1. Validar autenticação e arquivo (tipo, tamanho, extensão).
  2. Armazenar o arquivo original em storage privado.
  3. Detectar formato e selecionar parser (`NubankCsvParser` / `OfxParser`).
  4. Normalizar campos: data, descrição, valor, tipo (crédito/débito), identificador externo se houver.
  5. Persistir `StatementImport` (metadados da importação) + `Transaction` (linhas).
  6. Retornar resumo: total importado, ignorados/duplicados, erros de linha.
- Deduplicação: chave estável (ex.: hash de `data + valor + descrição + conta` ou ID do OFX).
- Categoria: campo nullable no MVP; regras de categorização automática podem ser fase 2.

**Modelo de dados mínimo (conceitual)**

| Entidade | Campos principais |
| --- | --- |
| `users` | id, name, email, password (apenas admin) |
| `statement_imports` | id, user_id, filename, format (`csv`\|`ofx`), source (`nubank`), status, rows_total, rows_imported, rows_skipped, checksum, created_at |
| `transactions` | id, import_id, external_id?, occurred_on, description, amount (decimal signed), type (`credit`\|`debit`), category_id?, raw_payload (json), unique_hash |
| `categories` | id, name, slug, type (opcional no MVP — seed básico) |

**Critérios de aceite**

- [ ] Upload autenticado de CSV Nubank popula `transactions` corretamente.
- [ ] Reimportar o mesmo arquivo não duplica linhas (ou reporta skips).
- [ ] Arquivo original não é acessível via URL pública.
- [ ] Erros de parse retornam mensagem clara sem vazar paths do servidor.

---

### 3.3 Dashboard Analítico

**Objetivo:** visualizar dados processados com filtros inteligentes e dinâmicos.

**Requisitos funcionais**

- Visão geral (cards): saldo do período, total de entradas, total de saídas, quantidade de transações.
- Lista/tabela de transações paginada.
- Filtros dinâmicos (combináveis):
  - **Período** (de/até, atalhos: mês atual, últimos 30/90 dias).
  - **Tipo** (crédito, débito, todos).
  - **Categoria** (quando existir).
  - **Busca textual** na descrição.
  - **Importação de origem** (opcional).
- Gráficos alinhados ao Design System:
  - Barras/série por período (evolução).
  - Distribuição por categoria ou tipo (quando houver dados).
- Estados vazios: orientar o usuário a fazer o primeiro upload.
- UI dark, Poppins, tokens de `docs/DESIGN-SYSTEM.MD` (cards `radius.xl`, pills de filtro, progress/charts).

**Critérios de aceite**

- [ ] Filtros atualizam cards, lista e gráficos de forma coerente.
- [ ] Performance aceitável com volume típico de extratos pessoais (milhares de linhas).
- [ ] Layout responsivo (desktop prioritário; mobile utilizável).

---

## 4. Roadmap de Implementação

Checklist técnico do MVP. Marque itens conforme forem concluídos.

### Etapa A — Setup do projeto

- [x] Inicializar repositório Git e `.gitignore` (`.env`, `vendor`, `node_modules`, uploads).
- [x] Criar aplicação Laravel 11 + configurar `.env.example`.
- [x] Configurar Vite + React no frontend (`resources/js`).
- [x] Definir document root `public/` e regras Apache/`.htaccess`.
- [x] Instalar fonte Poppins e espelhar tokens CSS a partir de `docs/DESIGN-SYSTEM.MD`.
- [x] Configurar ambiente local (PHP, Composer, Node, MySQL).
- [x] Documentar variáveis de ambiente necessárias neste `context.md` se mudarem.

### Etapa B — Banco de Dados

- [ ] Migration `users` (+ seeder do admin único).
- [ ] Migration `categories` (seed opcional mínimo).
- [ ] Migration `statement_imports`.
- [ ] Migration `transactions` com índices (`occurred_on`, `type`, `unique_hash`, `category_id`).
- [ ] Constraints de unicidade para deduplicação.
- [ ] Factories/seeders de desenvolvimento (transações fake para UI).

### Etapa C — Backend / Auth / Parse

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

### Etapa D — Frontend

- [ ] Tela de Login (brand **Aura** + tagline + Design System).
- [ ] Layout autenticado (nav, shell dark).
- [ ] Página de Upload (drag-and-drop, feedback de sucesso/erro/resumo).
- [ ] Dashboard: metric cards, filtros (pills), tabela, gráficos.
- [ ] Estados: loading, empty, error.
- [ ] Integração com API autenticada (cookies/CSRF conforme stack).
- [ ] Responsividade básica.

### Etapa E — Deploy (HostGator / vonluqi.com)

- [ ] Criar banco MySQL no cPanel e usuário com permissões mínimas.
- [ ] Configurar `.env` de produção (`APP_URL=https://vonluqi.com`, `APP_DEBUG=false`).
- [ ] Apontar domínio / subdomínio para `public/`.
- [ ] Instalar dependências (`composer install --no-dev`, `npm ci && npm run build`).
- [ ] Rodar migrations + seeder do admin.
- [ ] Garantir permissões em `storage/` e `bootstrap/cache/`.
- [ ] Validar HTTPS, login, upload Nubank e dashboard em produção.
- [ ] Backup inicial do banco e checklist de rollback.

### Ordem sugerida de entrega

```text
A (Setup) → B (DB) → C.Auth → C.Parse/Upload → C.APIs Dashboard → D (UI) → E (Deploy)
```

---

## 5. Convenções para a IA (obrigatório)

1. Ler este `context.md` e `docs/DESIGN-SYSTEM.MD` antes de implementar UI ou novas features.
2. Não introduzir multi-usuário, OAuth social ou Open Banking no MVP sem atualizar este documento.
3. Não expor uploads ou `.env` publicamente.
4. Preferir mudanças pequenas e testáveis; parsers devem ter fixtures e testes.
5. Ao concluir uma etapa do roadmap, atualizar os checkboxes deste arquivo.
6. Stack default = **Laravel + MySQL + React/Vite** em HostGator; qualquer troca deve ser registrada na seção 2.2 com data/motivo.

---

## 6. Glossário rápido

| Termo | Significado |
| --- | --- |
| Aura | Nome comercial do produto |
| A.U.R.A. | Assistente Unificado de Recursos e Análises |
| Tagline | “Aura: Inteligência invisível, controle absoluto.” |
| Statement / Extrato | Arquivo CSV ou OFX exportado do banco |
| Import | Registro de uma operação de upload/parse |
| Transaction | Linha normalizada de movimento financeiro |
| Admin | Único usuário autorizado do sistema |
| Nubank CSV | Formato de exportação do Nubank (primeiro parser prioritário) |
| Assistente | Pilar de automação/inteligência (categorização e análises; ML como evolução) |
| Unificado | Centralização dos dados bancários em uma única plataforma |
