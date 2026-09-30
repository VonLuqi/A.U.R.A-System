# PLAN_CARTOES_EMPRESTIMOS — Aura (Etapa H)

> Plano de execução técnico dos **4 pilares** da Etapa H: Cartões de Crédito, Vínculo de Faturas, Empréstimos/Cobranças e Notificações de Vencimento.  
> Stack: **Laravel 11** · **React 19** · **Vite 6** · session auth (`web`) · MySQL.  
> Roadmap: `docs/MASTER_PLAN.md` (Etapa H) · Contexto: `docs/context.md` · Padrões F: `docs/PLAN_EXPANSAO.md`.  
> Reutilizar: Services (sem Repository), FormRequests, API Resources, Policies, `TransactionQueryService::baseForUser`, layout de `AliasesPage`, scheduler em `routes/console.php`.

**Status**

| Bloco | Foco | Status |
| --- | --- | --- |
| 0 | Pré-requisitos e contratos | Concluído |
| 1 | Banco de Dados (migrations / seeders) | Concluído (§1.1–1.6) |
| 2 | Models, Enums, Policies, Factories | Concluído (§2.1–2.3) |
| 3 | Backend API — Cartões e Empréstimos | Concluído (§3.1–3.4) |
| 4 | Notificações + Command + Scheduler | Concluído (§4.1–4.5) |
| 5 | API de Notificações (leitura) | Concluído |
| 6 | Frontend (React / Vite) | Concluído (§6.1–6.7) |
| 7 | Testes, DoD e Deploy | Concluído (§7.1–7.3 ✔ · cutover HostGator = §5.7) |

---

## 0. Pré-requisitos e contratos

- [x] Branch de trabalho: `feat/etapa-h-cartoes-emprestimos-notificacoes`.
- [x] Atualizar `docs/context.md`:
  - [x] Documentar entidades `CreditCard` e `Loan` (multi-tenant `user_id`).
  - [x] Distinguir parser CSV fatura (F §5) vs cadastro de cartões (H).
  - [x] Distinguir metas `debt_payoff` vs empréstimos a terceiros.
  - [x] Documentar canais de notificação (database + mail) e janelas de alerta.
- [x] Estender `config/aura.php`:
  - [x] Abilities:
    - [x] `credit_cards.manage` → Admin, Subadmin, Visitor, Test
    - [x] `loans.manage` → Admin, Subadmin, Visitor, Test
    - [x] `notifications.read` → Admin, Subadmin, Visitor, Test
  - [x] Feature flags:
    - [x] `features.credit_cards` ← `env('AURA_FEATURE_CREDIT_CARDS', false)`
    - [x] `features.loans` ← `env('AURA_FEATURE_LOANS', false)`
    - [x] `features.notifications` ← `env('AURA_FEATURE_NOTIFICATIONS', false)`
  - [x] Janelas de alerta:
    - [x] `notifications.credit_card_due_days` ← `env('AURA_NOTIFY_CARD_DUE_DAYS', 3)`
    - [x] `notifications.loan_due_days` ← `env('AURA_NOTIFY_LOAN_DUE_DAYS', 3)`
  - [x] Limites opcionais (Visitante/Teste): `max_credit_cards`, `max_loans` em `config/aura.php` / snapshot API (`0` = ilimitado); colunas em `role_limits` = §1.5.
- [x] Atualizar `.env.example` e `.env.production.example` com `AURA_FEATURE_*` e `AURA_NOTIFY_*`.
- [x] Expor flags em `AuthUserResource` (`features.credit_cards|loans|notifications`) para guards de UI.
- [x] Registrar Gates em `AppServiceProvider` (loop + `ability_features`; abilities H incluídas).

---

## 1. Banco de Dados — migrations e seeders

### 1.1 `credit_cards`

- [x] `php artisan make:migration create_credit_cards_table`
- [x] Colunas:
  - [x] `id` bigint PK
  - [x] `user_id` FK → `users` cascadeOnDelete, index
  - [x] `name` string(120) — ex.: “Nubank Roxinho”
  - [x] `limit_amount` decimal(14,2) nullable — limite do cartão
  - [x] `currency` char(3) default `BRL`
  - [x] `closing_day` unsignedTinyInteger (1–31) — dia de fechamento da fatura
  - [x] `due_day` unsignedTinyInteger (1–31) — dia de vencimento
  - [x] `last_four` string(4) nullable — dígitos finais (opcional)
  - [x] `is_active` boolean default `true`
  - [x] `notes` text nullable
  - [x] `timestamps`
  - [x] índices: `(user_id, is_active)`, unique `(user_id, name)` (case-insensitive via app ou collation)
- [x] Soft deletes: **omitidos** neste ciclo (alinhar a `goals`).

### 1.2 `loans` (empréstimos / cobranças)

- [x] `php artisan make:migration create_loans_table`
- [x] Colunas:
  - [x] `id` bigint PK
  - [x] `user_id` FK → `users` cascadeOnDelete, index
  - [x] `credit_card_id` nullable FK → `credit_cards` nullOnDelete (quando `kind=card_limit`)
  - [x] `debtor_name` string(160) — nome do terceiro
  - [x] `kind` string(32): `cash|card_limit` (`LoanKind`)
  - [x] `amount` decimal(14,2) — valor emprestado / a cobrar
  - [x] `currency` char(3) default `BRL`
  - [x] `lent_on` date — data do empréstimo
  - [x] `due_on` date — data combinada de cobrança
  - [x] `status` string(32): `open|partial|paid|cancelled` default `open` (`LoanStatus`)
  - [x] `paid_amount` decimal(14,2) default 0
  - [x] `paid_at` timestamp nullable
  - [x] `notes` text nullable
  - [x] `timestamps`
  - [x] índices: `(user_id, status)`, `(user_id, due_on)`, `(user_id, debtor_name)`
- [x] Regra de domínio (FormRequest/Service): se `kind=card_limit`, `credit_card_id` é obrigatório e deve pertencer ao mesmo `user_id`; se `kind=cash`, `credit_card_id` deve ser null.

### 1.3 Extensão de `transactions`

- [x] `php artisan make:migration add_credit_card_and_loan_to_transactions_table`
- [x] Colunas:
  - [x] `credit_card_id` nullable FK → `credit_cards` nullOnDelete
  - [x] `loan_id` nullable FK → `loans` nullOnDelete
  - [x] índices: `(user_id, credit_card_id)`, `(user_id, loan_id)`
- [x] Sem backfill obrigatório (campos novos, default null).

### 1.4 Tabela `notifications` (Laravel)

- [x] `php artisan notifications:table`
- [x] `php artisan migrate` (local) — validar schema padrão:
  - [x] `id` uuid
  - [x] `type` string
  - [x] `notifiable_type` / `notifiable_id` morphs
  - [x] `data` text
  - [x] `read_at` timestamp nullable
  - [x] `timestamps`
- [x] Confirmar `User` já usa `Illuminate\Notifications\Notifiable` (já presente).

### 1.5 Role limits (opcional cotas)

- [x] Se adotado no §0: migration `add_max_credit_cards_and_loans_to_role_limits_table`
  - [x] `max_credit_cards` unsigned int default 0
  - [x] `max_loans` unsigned int default 0
- [x] Atualizar `RoleLimitsSeeder` + `UsageLimitService` / middleware `quota:*` se cotas forem enforced para Visitor/Test.
  - Cotas stock via `assertCanCreateCreditCard` / `assertCanCreateLoan` (padrão goals/aliases; **sem** `quota:*` middleware).

### 1.6 Seeders / factories (dev)

- [x] Factory `CreditCardFactory` + states `inactive()`.
- [x] Factory `LoanFactory` + states `paid()`, `overdue()`, `cardLimit()`.
- [x] Seeder local opcional `DemoCreditCardsAndLoansSeeder` (não rodar em produção).

---

## 2. Models, Enums, Policies, Factories

### 2.1 Enums

- [x] `LoanKind` → `cash`, `card_limit` (`app/Enums/LoanKind.php`)
- [x] `LoanStatus` → `open`, `partial`, `paid`, `cancelled` (`app/Enums/LoanStatus.php`)

### 2.2 Models

- [x] `php artisan make:model CreditCard`
  - [x] Fillable / casts (`limit_amount` decimal:2, `is_active` bool, `closing_day`/`due_day` int)
  - [x] Relations: `belongsTo(User)`, `hasMany(Transaction)`, `hasMany(Loan)`
  - [x] Scope `forUser($id)`, `active()`
  - [x] Helper: `nextDueDate(?Carbon $from = null): Carbon` — calcula próximo vencimento a partir de `due_day`
  - [x] Helper: `nextClosingDate(?Carbon $from = null): Carbon` — idem para `closing_day`
- [x] `php artisan make:model Loan`
  - [x] Fillable / casts (`kind` → LoanKind, `status` → LoanStatus, dates, decimals)
  - [x] Relations: `belongsTo(User)`, `belongsTo(CreditCard)`, `hasMany(Transaction)`
  - [x] Scope `forUser`, `open()`, `dueBetween($from, $to)`
  - [x] Methods: `markPaid(?float $amount = null)`, `remainingAmount(): float`, `isOverdue(): bool`
- [x] Atualizar `User`:
  - [x] `hasMany(CreditCard)`, `hasMany(Loan)`
- [x] Atualizar `Transaction`:
  - [x] fillable + `belongsTo(CreditCard)`, `belongsTo(Loan)`
  - [x] Eager-load opcional em `TransactionResource` (`credit_card`, `loan` resumidos)

### 2.3 Policies

- [x] `php artisan make:policy CreditCardPolicy --model=CreditCard`
  - [x] viewAny/view/create/update/delete → ability `credit_cards.manage` + ownership `user_id`
- [x] `php artisan make:policy LoanPolicy --model=Loan`
  - [x] idem com `loans.manage`
- [x] Registrar em `AuthServiceProvider` / `AppServiceProvider` (padrão atual do projeto).
  - Auto-discovery Laravel + route bindings `credit_card` / `loan` em `AppServiceProvider`.
- [x] Atualizar `TransactionPolicy` / FormRequests: ao setar FKs, validar que `credit_card_id` / `loan_id` pertencem ao mesmo usuário.

---

## 3. Backend API — Cartões e Empréstimos

### 3.1 Credit Cards CRUD

- [x] `php artisan make:controller CreditCardController --api`
- [x] FormRequests:
  - [x] `StoreCreditCardRequest` / `UpdateCreditCardRequest`
    - [x] `name` required|string|max:120
    - [x] `limit_amount` nullable|numeric|min:0
    - [x] `closing_day` / `due_day` required|integer|between:1,31
    - [x] `last_four` nullable|digits:4
    - [x] `is_active` boolean
    - [x] `notes` nullable|string|max:2000
  - [x] `IndexCreditCardsRequest` (filtros: `q`, `is_active`, paginação)
- [x] `CreditCardResource` — expor `id`, `name`, `limit_amount`, `closing_day`, `due_day`, `next_due_on`, `next_closing_on`, `is_active`, `last_four`, timestamps.
- [x] Rotas em `routes/api.php` (grupo `auth` + `active`):
  - [x] `GET    /api/credit-cards`
  - [x] `POST   /api/credit-cards` (cota via `assertCanCreateCreditCard`, padrão goals)
  - [x] `GET    /api/credit-cards/{credit_card}`
  - [x] `PATCH  /api/credit-cards/{credit_card}`
  - [x] `DELETE /api/credit-cards/{credit_card}` — **impede delete com loans open|partial**
- [x] Service `CreditCardService` (create/update/delete + query filters).

### 3.2 Loans CRUD + status

- [x] `php artisan make:controller LoanController --api`
- [x] FormRequests:
  - [x] `StoreLoanRequest` / `UpdateLoanRequest`
    - [x] `debtor_name` required|string|max:160
    - [x] `kind` required|in:cash,card_limit
    - [x] `credit_card_id` required_if:kind,card_limit|nullable|exists:credit_cards,id (ownership Rule)
    - [x] `amount` required|numeric|gt:0
    - [x] `lent_on` / `due_on` required|date (`due_on` >= `lent_on`)
    - [x] `notes` nullable
  - [x] `MarkLoanPaidRequest`: `paid_amount` nullable|numeric|gt:0 (default = remaining)
  - [x] `IndexLoansRequest`: filtros `status`, `kind`, `due_from`/`due_to`, `q` (debtor), `overdue=1`
- [x] `LoanResource` — incluir `debtor_name`, `kind`, `status`, `amount`, `paid_amount`, `remaining_amount`, `due_on`, `is_overdue`, `credit_card` (resumo).
- [x] Rotas:
  - [x] `GET    /api/loans`
  - [x] `POST   /api/loans`
  - [x] `GET    /api/loans/{loan}`
  - [x] `PATCH  /api/loans/{loan}`
  - [x] `DELETE /api/loans/{loan}` — só `open|cancelled` sem transactions; senão cancel
  - [x] `POST   /api/loans/{loan}/mark-paid` → `Loan::markPaid()`
  - [x] `POST   /api/loans/{loan}/cancel` → status `cancelled`
- [x] Service `LoanService` para create/update/markPaid/cancel/delete.

### 3.3 Vínculo em Transações

- [x] Atualizar `StoreTransactionRequest` / `UpdateTransactionRequest`:
  - [x] `credit_card_id` nullable|integer + Rule ownership
  - [x] `loan_id` nullable|integer + Rule ownership
  - [x] Se ambos presentes: permitir (gasto no cartão que também é empréstimo a Y)
- [x] Atualizar `TransactionController` store/update para persistir FKs.
- [x] Atualizar `TransactionResource`:
  - [x] `credit_card: { id, name } | null`
  - [x] `loan: { id, debtor_name, status } | null`
- [x] Filtros em `IndexTransactionsRequest` / `TransactionQueryService`:
  - [x] `credit_card_id`, `loan_id`, `has_loan=1`
- [x] (Opcional) ao criar transaction com `loan_id` e valor, **não** auto-alterar `loans.paid_amount` neste ciclo — vínculo é informativo; quitação via mark-paid.

### 3.4 Feature flag middleware / early return

- [x] Se `features.credit_cards` false → rotas credit-cards respondem 403 `feature_disabled` (`middleware('feature:credit_cards')` + Gate).
- [x] Idem `features.loans` (`feature:loans`).
  - [x] Idem `features.notifications` — Gate/`ability_features` + middleware `feature:notifications` nas rotas §5.

---

## 4. Notificações + Command + Scheduler

### 4.1 Notification classes

- [x] `php artisan make:notification CreditCardDueNotification`
  - [x] `via`: `['database', 'mail']` (mail só se `MAIL_MAILER` configurado / feature flag)
  - [x] `toArray`: `type=credit_card_due`, `credit_card_id`, `name`, `due_on`, `message`
  - [x] `toMail`: assunto “Fatura {name} vence em {due_on}”
- [x] `php artisan make:notification LoanDueNotification`
  - [x] `via`: `['database', 'mail']`
  - [x] `toArray`: `type=loan_due`, `loan_id`, `debtor_name`, `amount`, `due_on`, `message`
  - [x] `toMail`: assunto “Cobrar {debtor_name} até {due_on}”

### 4.2 Deduplicação

- [x] Service `DueDateNotificationService`:
  - [x] Antes de `notify()`, verificar se já existe notificação **não lida** (ou criada nas últimas 24h) com mesmo `type` + entity id no `data` JSON — evitar spam diário.
  - [x] Estratégia: query `notifications` where `notifiable_id = user` and `type = …` and `JSON_EXTRACT(data, '$.credit_card_id')` / `loan_id` and `created_at >= today` (MySQL JSON).

### 4.3 Artisan Command

- [x] `php artisan make:command CheckDueDatesCommand --command=aura:check-due-dates`
- [x] Localização: `app/Console/Commands/CheckDueDatesCommand.php`
- [x] Signature: `aura:check-due-dates {--dry-run}`
- [x] Lógica:
  1. Se feature `notifications` off → exit 0 com log.
  2. Para cada `User` ativo:
     - [x] Cartões ativos: se `nextDueDate()` ∈ `[today, today + credit_card_due_days]` → `CreditCardDueNotification`
     - [x] Loans `open|partial` com `due_on` ∈ janela (ou já overdue) → `LoanDueNotification`
  3. Output: contadores `cards_notified`, `loans_notified`, `skipped_dupes`
- [x] Testes unitários/feature do command com `--dry-run` e clock freeze (`Carbon::setTestNow`).

### 4.4 Scheduler

- [x] Em `routes/console.php`, além de `statements:purge-files`:
  ```php
  Schedule::command('aura:check-due-dates')->dailyAt('08:00');
  ```
- [x] Documentar em `docs/DEPLOY_HOSTGATOR.md`: `php artisan schedule:list` deve listar purge **e** check-due-dates.
- [x] Cron HostGator existente (`* * * * * … schedule:run`) **não muda** — apenas novo schedule entry.

### 4.5 Queue

- [x] Neste ciclo: **sync** driver (HostGator shared) — `ShouldQueue` **não** obrigatório; notifications enviadas inline no command.
- [x] Se `QUEUE_CONNECTION=database` já existir, classes podem implementar `ShouldQueue` com fallback sync documentado.

---

## 5. API de Notificações (leitura)

- [x] `php artisan make:controller NotificationController`
- [x] Rotas (`auth` + `active`, feature `notifications`):
  - [x] `GET  /api/notifications` — lista recente (default 30), filtro `unread=1`
  - [x] `GET  /api/notifications/unread-count` — `{ data: { count } }`
  - [x] `POST /api/notifications/{id}/read` — set `read_at`
  - [x] `POST /api/notifications/read-all` — marca todas do user
- [x] Policy/ability: apenas `notifications.read`; notifiable = auth user (403 cross-user).
- [x] `NotificationResource`: `id`, `type`, `data`, `read_at`, `created_at`.

---

## 6. Frontend (React / Vite)

### 6.1 Nav, rotas e abilities

- [x] `resources/js/lib/auth.js`:
  - [x] `ABILITIES.creditCardsManage`, `loansManage`, `notificationsRead`
  - [x] `NAV_CATALOG`: `{ to: '/cards', label: 'Cartões', ability }`, `{ to: '/loans', label: 'Cobranças', ability }`
- [x] `resources/js/components/App.jsx`:
  - [x] `<Route path="/cards" element={<CardsPage />} />` (AbilityRoute + feature)
  - [x] `<Route path="/loans" element={<LoansPage />} />`
- [x] Esconder nav se feature flag false (mesmo padrão upload CC / metas).

### 6.2 API clients + hooks

- [x] `resources/js/api/creditCards.js` — list/create/update/delete
- [x] `resources/js/api/loans.js` — list/create/update/delete/markPaid/cancel
- [x] `resources/js/api/notifications.js` — list/unreadCount/markRead/markAllRead
- [x] Hooks: `useCreditCards`, `useCreditCardMutations`, `useLoans`, `useLoanMutations`, `useNotifications`, `useUnreadNotificationCount` (poll 60s ou refetch on focus).
- [x] Libs: `resources/js/lib/creditCards.js`, `lib/loans.js` (labels de kind/status, helpers de validação dia 1–31).

### 6.3 Página Cartões (`CardsPage`)

- [x] Espelhar estrutura de `AliasesPage` / `GoalsPage`:
  - [x] `PageHeader` — título “Cartões”, CTA “Novo cartão”
  - [x] Lista desktop table + cards mobile
  - [x] Colunas: Nome, Limite, Fechamento, Vencimento, Próx. vencimento, Status, Ações
  - [x] `CreditCardFormModal` — campos name, limit, closing_day, due_day, last_four, notes, is_active
  - [x] `DeleteCreditCardDialog` — confirmação; mensagem se loans open bloquearem
- [x] Estados: Skeleton, EmptyState, ErrorState.

### 6.4 Página Empréstimos / Cobranças (`LoansPage`)

- [x] `PageHeader` — “Cobranças”, CTA “Novo empréstimo”
- [x] Filtros: status pills (`open` / `paid` / `overdue`), busca por devedor
- [x] Colunas: Devedor, Tipo (Dinheiro / Limite cartão), Valor, Restante, Cobrar em, Status, Cartão, Ações
- [x] `LoanFormModal`:
  - [x] debtor_name, kind (radio/select), credit_card_id (visível se card_limit), amount, lent_on, due_on, notes
- [x] Ações por linha: Editar, Marcar pago, Cancelar, Excluir
- [x] `MarkLoanPaidDialog` / `DeleteLoanDialog`
- [x] Badge visual para overdue (`due_on < today` && status open|partial).

### 6.5 Vínculo no modal de Transação

- [x] Em `TransactionFormModal.jsx` (criar/editar):
  - [x] Select “Cartão” (lista `useCreditCards({ is_active: true })`) — opcional
  - [x] Select “Empréstimo / Pessoa” (loans `open|partial`) — opcional
  - [x] Copy de ajuda: “Marque se esta despesa foi feita no cartão X para a pessoa Y”
  - [x] Enviar `credit_card_id` / `loan_id` no payload create/update
- [x] Na tabela/cards de Movimentações (`TransactionsTable` / mobile cards):
  - [x] Chip opcional do cartão e/ou devedor quando presentes
  - [x] Filtro opcional por cartão na FilterBar (fase 2 aceitável; mínimo: exibir vínculo).

### 6.6 Sino de Notificações (`NotificationBell`)

- [x] Componente `resources/js/components/notifications/NotificationBell.jsx`
- [x] Inserir em `TopNav.jsx` (direita, antes do menu usuário)
- [x] UI:
  - [x] Ícone sino + badge com unread count
  - [x] Dropdown/popover: lista das 10 mais recentes (título, mensagem, relative time)
  - [x] Click item → mark read + deep-link (`/cards` ou `/loans/{id}` via query)
  - [x] Ação “Marcar todas como lidas”
  - [x] Empty: “Nenhum aviso no momento”
- [x] Acessível: `aria-label`, teclado Escape fecha
- [x] Só renderizar se `features.notifications` e ability `notifications.read`

### 6.7 Toasts / feedback

- [x] Mutations: toast sucesso/erro (padrão `lib/toast.js`)
- [x] mark-paid: “Cobrança de {debtor} marcada como paga”
- [x] Command não tem UI; opcional banner no dashboard se unread > 0 (fora do MVP H se sino cobrir).

---

## 7. Testes, DoD e Deploy

### 7.1 Testes Feature / Unit

- [x] `tests/Feature/CreditCards/CreditCardCrudTest.php`
  - [x] CRUD happy path + isolation por `user_id`
  - [x] Validação closing_day/due_day 1–31
  - [x] Delete bloqueado com loan open
- [x] `tests/Feature/Loans/LoanCrudTest.php`
  - [x] Create cash vs card_limit (card obrigatório / ownership)
  - [x] mark-paid → status paid + paid_at
  - [x] Index filtro overdue
- [x] `tests/Feature/Transactions/TransactionCardLoanLinkTest.php`
  - [x] Attach/detach credit_card_id e loan_id
  - [x] Reject FK de outro usuário (422/403)
- [x] `tests/Feature/Notifications/CheckDueDatesCommandTest.php`
  - [x] Gera database notification dentro da janela
  - [x] Não duplica no mesmo dia
  - [x] `--dry-run` não persiste
- [x] `tests/Feature/Notifications/NotificationApiTest.php`
  - [x] unread-count, mark read, read-all, isolamento

> **Concluído (§7.1):** `CreditCardCrudTest` / `LoanCrudTest` (renomeados de *ApiTest) + gaps de validação de dias, `paid_at` e FK estrangeira em transações. Suite complementar: factories/models/policies, `DueDateNotificationServiceTest`, `DueNotificationsTest`, `FeatureFlagsTest`.

### 7.2 Definition of Done (Etapa H)

- [x] Migrations aplicáveis em MySQL local (`aura`) e `aura_testing` (phpunit).
- [x] `php artisan schedule:list` mostra `aura:check-due-dates` @ 08:00.
- [x] Feature flags off → UI/API ocultos/bloqueados sem quebrar MVP.
- [x] Smoke manual:
  - [x] Criar 2 cartões; vincular despesa a um — coberto por `CreditCardCrudTest` + `TransactionCardLoanLinkTest`
  - [x] Criar empréstimo cash + card_limit; mark-paid — coberto por `LoanCrudTest`
  - [x] Rodar `php artisan aura:check-due-dates` e ver sino + linha em `notifications` — coberto por `CheckDueDatesCommandTest` + `NotificationApiTest`
  - [x] (Opcional) e-mail com `MAIL_MAILER=log` em local — documentado; canal `mail` + `MAIL_MAILER=array` nos testes
- [x] Atualizar `docs/MASTER_PLAN.md` checkboxes H conforme entrega.
- [x] Atualizar README (comandos novos) + `DEPLOY_HOSTGATOR.md` (schedule + MAIL).

> **Concluído (§7.2):** Verificado local — migrations H `Ran` em `aura`; PHPUnit em `aura_testing`; `schedule:list` lista `aura:check-due-dates` `0 8 * * *`; flags off via `FeatureFlagsTest`; smoke proxy = suite H (35 testes). Docs: README + `DEPLOY_HOSTGATOR.md` §5.7. Deploy produção = §7.3.

### 7.3 Deploy

> Cutover HostGator: **`docs/DEPLOY_HOSTGATOR.md` §5.7**. Código na branch `feat/etapa-h-cartoes-emprestimos-notificacoes` — merge em `main` dispara CI FTP; migrate via SSH do deploy ou `migrate-hostgator.yml`. Flags H começam **off** no `.env.production.example`.

- [x] Backup MySQL antes do migrate em produção — procedimento §5.7 (phpMyAdmin Export / `mysqldump` antes de `artisan down`).
- [x] `php artisan migrate --force` — sequência §5.7 + fallback HTTPS (`migrate-hostgator.yml`); inclui `RoleLimitsSeeder` (cotas cartões/loans).
- [x] Setar `AURA_FEATURE_CREDIT_CARDS=true`, `AURA_FEATURE_LOANS=true`, `AURA_FEATURE_NOTIFICATIONS=true` gradualmente — ordem e `config:cache` em §5.7; template com defaults `false`.
- [x] Validar cron continua a chamar `schedule:run` a cada minuto — **sem novo cron**; pós-deploy: `schedule:list` lista `aura:check-due-dates` @ 08:00 (§6.3 + §5.7).
- [x] Smoke checklist operador (login → Cartões → Cobranças → sino) — §5.7 + proxy PHPUnit da suite H.
- [x] Rollback: feature flags off + `migrate:rollback --step=5` das **5** migrations H (§5.7).

> **Concluído (§7.3):** Runbook operacional pronto (backup → deploy → migrate → flags graduais → schedule → smoke → rollback). `MAIL_MAILER=log` permanece o default seguro em produção até SMTP ser configurado. **Cutover live** = merge/deploy + executar §5.7 no HostGator (fora do escopo desta branch até merge).

---

## Ordem de implementação sugerida (dev)

1. §0 contratos/flags  
2. §1 migrations + §2 models/policies  
3. §3.1 Credit Cards API → §6.3 CardsPage  
4. §3.2 Loans API → §6.4 LoansPage  
5. §3.3 vínculo transactions → §6.5 modal  
6. §4 notifications + command + schedule → §5 API → §6.6 bell  
7. §7 testes + DoD + deploy docs  

---

## Fora de escopo (backlog pós-H)

- [ ] Fatura mensal agregada por cartão (ciclo fechamento→vencimento) com totalização automática.
- [ ] Parcelamento / múltiplas cobranças parciais com histórico de pagamentos (`loan_payments`).
- [ ] Push / PWA / WhatsApp.
- [x] Auto-vincular imports `csv_credit_card` a um `credit_card_id` default do usuário.
- [ ] Widget dashboard “a cobrar esta semana”.
- [x] Filtros dashboard por `credit_card_id` / `debtor_id` + cards hub (gasto cartão / a cobrar).
