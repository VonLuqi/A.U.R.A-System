<?php

use App\Enums\UserRole;

/**
 * Aura product contracts (PLAN_EXPANSAO §0 · PLAN_CARTOES_EMPRESTIMOS §0).
 *
 * - Multi-tenant: shared MySQL + row-level `user_id` (not schema-per-tenant).
 * - Limits: `0` means unlimited.
 * - Gate wiring (AppServiceProvider / Policies) consumes `abilities`.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Multi-tenant strategy
    |--------------------------------------------------------------------------
    |
    | HostGator shared hosting: one database, isolate rows by owner column.
    | Do not introduce schema-per-tenant or separate DB connections per user.
    |
    */
    'multi_tenant' => [
        'strategy' => 'shared_db_row_level',
        'owner_column' => 'user_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate abilities → roles allowed
    |--------------------------------------------------------------------------
    |
    | Visitante/Teste may hold write abilities but are still bounded by
    | `limits` (UsageLimitService / middleware) in §2.4.
    |
    */
    'abilities' => [
        'users.manage' => [
            UserRole::Admin->value,
        ],
        'transactions.manage' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'statements.upload' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'goals.manage' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'aliases.manage' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'credit_cards.manage' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'loans.manage' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
        'notifications.read' => [
            UserRole::Admin->value,
            UserRole::Subadmin->value,
            UserRole::Visitor->value,
            UserRole::Test->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles that must respect numeric quotas (non-zero limits)
    |--------------------------------------------------------------------------
    */
    'quota_enforced_roles' => [
        UserRole::Visitor->value,
        UserRole::Test->value,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default limits per role (env-overridable; seeded into role_limits)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        UserRole::Admin->value => [
            'max_uploads' => (int) env('AURA_LIMIT_UPLOAD_ADMIN', 0),
            'max_manual_transactions' => (int) env('AURA_LIMIT_MANUAL_TX_ADMIN', 0),
            'max_date_range_days' => (int) env('AURA_LIMIT_DATE_RANGE_DAYS_ADMIN', 0),
            'max_goals' => (int) env('AURA_LIMIT_GOALS_ADMIN', 0),
            'max_aliases' => (int) env('AURA_LIMIT_ALIASES_ADMIN', 0),
            'max_credit_cards' => (int) env('AURA_LIMIT_CREDIT_CARDS_ADMIN', 0),
            'max_loans' => (int) env('AURA_LIMIT_LOANS_ADMIN', 0),
        ],
        UserRole::Subadmin->value => [
            'max_uploads' => (int) env('AURA_LIMIT_UPLOAD_SUBADMIN', 50),
            'max_manual_transactions' => (int) env('AURA_LIMIT_MANUAL_TX_SUBADMIN', 0),
            'max_date_range_days' => (int) env('AURA_LIMIT_DATE_RANGE_DAYS_SUBADMIN', 0),
            'max_goals' => (int) env('AURA_LIMIT_GOALS_SUBADMIN', 20),
            'max_aliases' => (int) env('AURA_LIMIT_ALIASES_SUBADMIN', 0),
            'max_credit_cards' => (int) env('AURA_LIMIT_CREDIT_CARDS_SUBADMIN', 0),
            'max_loans' => (int) env('AURA_LIMIT_LOANS_SUBADMIN', 0),
        ],
        UserRole::Visitor->value => [
            'max_uploads' => (int) env('AURA_LIMIT_UPLOAD_VISITOR', 5),
            'max_manual_transactions' => (int) env('AURA_LIMIT_MANUAL_TX_VISITOR', 20),
            'max_date_range_days' => (int) env('AURA_LIMIT_DATE_RANGE_DAYS_VISITOR', 90),
            'max_goals' => (int) env('AURA_LIMIT_GOALS_VISITOR', 3),
            'max_aliases' => (int) env('AURA_LIMIT_ALIASES_VISITOR', 10),
            'max_credit_cards' => (int) env('AURA_LIMIT_CREDIT_CARDS_VISITOR', 3),
            'max_loans' => (int) env('AURA_LIMIT_LOANS_VISITOR', 5),
        ],
        UserRole::Test->value => [
            'max_uploads' => (int) env('AURA_LIMIT_UPLOAD_TEST', 3),
            'max_manual_transactions' => (int) env('AURA_LIMIT_MANUAL_TX_TEST', 10),
            'max_date_range_days' => (int) env('AURA_LIMIT_DATE_RANGE_DAYS_TEST', 60),
            'max_goals' => (int) env('AURA_LIMIT_GOALS_TEST', 1),
            'max_aliases' => (int) env('AURA_LIMIT_ALIASES_TEST', 5),
            'max_credit_cards' => (int) env('AURA_LIMIT_CREDIT_CARDS_TEST', 2),
            'max_loans' => (int) env('AURA_LIMIT_LOANS_TEST', 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alias retroactive apply (PLAN_EXPANSAO §4.3)
    |--------------------------------------------------------------------------
    |
    | Synchronous scan of the user's most recent transactions when
    | `apply_to_existing=true` on alias create. No queue/Redis — HostGator-safe.
    |
    */
    'aliases' => [
        'retroactive_limit' => (int) env('AURA_ALIAS_RETROACTIVE_LIMIT', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Due-date notification windows (Etapa H / PLAN_CARTOES_EMPRESTIMOS §0)
    |--------------------------------------------------------------------------
    |
    | Days ahead of credit-card due_day / loan due_on to fire reminders.
    | Consumed by `aura:check-due-dates` (command + schedule in later §§).
    |
    */
    'notifications' => [
        'credit_card_due_days' => (int) env('AURA_NOTIFY_CARD_DUE_DAYS', 3),
        'loan_due_days' => (int) env('AURA_NOTIFY_LOAN_DUE_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flags — gradual production rollout (PLAN_EXPANSAO §9.3 / Etapa H)
    |--------------------------------------------------------------------------
    |
    | Flip to false in production `.env` to disable a pillar without redeploying
    | code. Gates + AuthUserResource abilities honour these flags.
    | `credit_card_upload` gates statement_kind=credit_card on upload (Etapa F).
    | `credit_cards` / `loans` / `notifications` gate Etapa H modules.
    |
    */
    'features' => [
        'manual_transactions' => filter_var(
            env('AURA_FEATURE_MANUAL_TRANSACTIONS', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'goals' => filter_var(env('AURA_FEATURE_GOALS', true), FILTER_VALIDATE_BOOLEAN),
        'aliases' => filter_var(env('AURA_FEATURE_ALIASES', true), FILTER_VALIDATE_BOOLEAN),
        'credit_card_upload' => filter_var(
            env('AURA_FEATURE_CREDIT_CARD_UPLOAD', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'admin_users' => filter_var(
            env('AURA_FEATURE_ADMIN_USERS', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'credit_cards' => filter_var(
            env('AURA_FEATURE_CREDIT_CARDS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'loans' => filter_var(env('AURA_FEATURE_LOANS', false), FILTER_VALIDATE_BOOLEAN),
        'notifications' => filter_var(
            env('AURA_FEATURE_NOTIFICATIONS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Named ability → feature flag key (null = always on)
    |--------------------------------------------------------------------------
    */
    'ability_features' => [
        'users.manage' => 'admin_users',
        'transactions.manage' => 'manual_transactions',
        'statements.upload' => null,
        'goals.manage' => 'goals',
        'aliases.manage' => 'aliases',
        'credit_cards.manage' => 'credit_cards',
        'loans.manage' => 'loans',
        'notifications.read' => 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit-card CSV parse rules (PLAN_EXPANSAO §5.1)
    |--------------------------------------------------------------------------
    */
    'statements' => [
        'credit_card_skip_patterns' => [
            '/^pagamento\s+recebido/iu',
            '/^pagamento\s+(da|de)\s+fatura/iu',
            '/^payment\s+received/iu',
            '/^total(\s|$)/iu',
        ],
    ],

];
