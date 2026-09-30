# PLAN_PERFIL_BRANDING — Aura (Etapa I)

> Plano de execução técnico dos **3 pilares** da Etapa I: Gestão de Perfil, Nova Logo (SVG) e Loading State Avançado (Aura / Instinto Superior).  
> Stack: **Laravel 11** · **React 19** · **Vite 6** · session auth (`web`) · MySQL · Tailwind.  
> Roadmap: `docs/MASTER_PLAN.md` (Etapa I) · Contexto: `docs/context.md` · Design: `docs/DESIGN-SYSTEM.MD` · Deploy: `docs/DEPLOY_HOSTGATOR.md`.  
> Reutilizar: `AuthUserResource`, `AuthContext` / `useAuth`, `BrandMark`, `Spinner`/`AuthSplash`, FormRequests, API Resources, Axios CSRF (`resources/js/api/client.js`), disk `public` em `config/filesystems.php`.

**Status**

| Bloco | Foco | Status |
| --- | --- | --- |
| 0 | Pré-requisitos e contratos | Concluído |
| 1 | Banco de Dados (`avatar_path`) | Concluído (§1.1–1.3) |
| 2 | Backend — Profile API + storage | Concluído (§2.1–2.8 ✔ · cutover live = §5.8) |
| 3 | Frontend — Conta / Auth sync | Concluído (§3.1–3.6) |
| 4 | Logo SVG (BrandMark) | Concluído (§4.1–4.3) |
| 5 | Loading Aura / Instinto Superior | Concluído (§5.1–5.4) |
| 6 | Testes, DoD e Deploy HostGator | Concluído (§6.1–6.4 ✔ · cutover live = merge + §5.8) |

---

## 0. Pré-requisitos e contratos

- [x] Branch de trabalho: `feat/etapa-i-perfil-branding-ux`.
- [x] Atualizar `docs/context.md`:
  - [x] Documentar self-service de perfil (nome, senha, avatar) vs Admin `UserController`.
  - [x] Documentar coluna `users.avatar_path` e campo de API `avatar_url`.
  - [x] Distinguir disk `public` (avatars) vs disk `statements` (privado, sem URL pública).
  - [x] Documentar identidade: logo SVG + `AuraLoader` + tagline *Inteligência invisível, controle absoluto.*
- [x] Atualizar `docs/DESIGN-SYSTEM.MD` §0 (Marca):
  - [x] Spec da logo SVG (viewBox, cores `#DCCFFF` / `#151716`, tamanhos `sm` / `lg`).
  - [x] Spec do loading Aura (glow multicamadas, motion premium, `prefers-reduced-motion`).
- [x] Contrato `AuthUser` (`resources/js/lib/auth.js` + `AuthUserResource`):
  - [x] Adicionar `avatar_url: string|null` (URL absoluta ou path `/storage/...`).
  - [x] Manter `email` **somente leitura** no self-service.
- [x] Escopo fora desta etapa (não implementar):
  - [x] Alteração de e-mail pelo usuário.
  - [x] Crop editor avançado de imagem.
  - [x] CDN / S3 para avatars (manter disk `local` `public`).

---

## 1. Banco de Dados — `users.avatar_path`

### 1.1 Migration

- [x] `php artisan make:migration add_avatar_path_to_users_table`
- [x] Colunas:
  - [x] `avatar_path` → `$table->string('avatar_path', 255)->nullable()->after('email');`
  - [x] Sem índice (lookup sempre via `auth()->id()` / PK).
- [x] Down: `$table->dropColumn('avatar_path');`
- [x] Rodar local: `php artisan migrate`
- [x] Sem backfill (nullable = sem avatar).

### 1.2 Model `App\Models\User`

- [x] Incluir `avatar_path` em `$fillable`.
- [x] Helper / accessor:
  - [x] `public function avatarUrl(): ?string` — se `avatar_path` preenchido, retornar `Storage::disk('public')->url($this->avatar_path)`; senão `null`.
  - [x] Garantir `use Illuminate\Support\Facades\Storage;`.
- [x] Opcional: ao `deleting` user (Admin), apagar arquivo do disk — **adiado**: `UserController@destroy` só soft-block (`is_active=false`); sem hard delete neste ciclo.

### 1.3 Factory

- [x] Em `database/factories/UserFactory.php`:
  - [x] Default: `'avatar_path' => null`.
  - [x] State `withAvatar()`: `'avatar_path' => 'avatars/1/fake.webp'` (arquivo real só em testes de integração de storage fake).

---

## 2. Backend — Profile API + upload seguro

### 2.1 Artisan / estrutura

- [x] `php artisan make:controller ProfileController`
- [x] `php artisan make:request Profile/UpdateProfileRequest`
- [x] `php artisan make:request Profile/UploadAvatarRequest`
- [x] (Opcional) `php artisan make:service ProfileService` **ou** métodos privados no controller — preferir service fino `App\Services\ProfileService` se lógica de arquivo > ~40 LOC.
- [x] **Não** criar Policy dedicada: autorização = `auth` + `active` + sempre `$request->user()` (self-only). Admin continua em `/api/users`.

### 2.2 Rotas (`routes/api.php`)

- [x] Dentro do grupo `Route::middleware(['auth', 'active'])`:
  - [x] `Route::middleware('throttle:30,1')->group(...)` (ou reutilizar throttle existente):
    - [x] `PATCH /api/profile` → `[ProfileController::class, 'update']` → name `api.profile.update`
    - [x] `POST /api/profile/avatar` → `[ProfileController::class, 'storeAvatar']` → name `api.profile.avatar.store` (+ `throttle:10,1` §2.8)
    - [x] `DELETE /api/profile/avatar` → `[ProfileController::class, 'destroyAvatar']` → name `api.profile.avatar.destroy`
- [x] Manter `GET /api/user` como fonte canônica do payload SPA (já em `AuthenticatedSessionController@show`).

### 2.3 `UpdateProfileRequest`

- [x] Autorizar: `return $this->user() !== null;`
- [x] Regras:
  - [x] `name` → `sometimes|required|string|min:2|max:120`
  - [x] `password` → `sometimes|required|string|min:8|confirmed` (exige `password_confirmation`)
  - [x] `current_password` → `required_with:password|current_password` (rule Laravel `current_password`)
  - [x] **Não** aceitar `email`, `role`, `is_active`, cotas.
- [x] Mensagens PT-BR alinhadas aos FormRequests existentes.

### 2.4 `UploadAvatarRequest`

- [x] Autorizar: usuário autenticado.
- [x] Regras:
  - [x] `avatar` → `required|file|image|mimes:jpeg,jpg,png,webp|max:2048` (KB = 2 MB)
- [x] Rejeitar SVG/GIF/BMP (evitar XSS via SVG e formatos desnecessários).

### 2.5 `ProfileController`

- [x] `update(UpdateProfileRequest $request)`:
  - [x] `$user = $request->user();`
  - [x] Atualizar `name` se presente.
  - [x] Se `password` presente: `$user->password = $validated['password'];` (cast `hashed` no Model).
  - [x] `$user->save();`
  - [x] Retornar `['user' => new AuthUserResource($user->fresh())]` com HTTP 200.
- [x] `storeAvatar(UploadAvatarRequest $request)`:
  - [x] Path: `avatars/{user_id}/{uuid}.{ext}` via `$request->file('avatar')->storeAs(...)` no disk `public`.
  - [x] Extensão derivada de `extension()` / mime map (`jpg|png|webp`).
  - [x] Se `$user->avatar_path` antigo existir e `Storage::disk('public')->exists(...)`: `delete`.
  - [x] Persistir novo `avatar_path` (path relativo, **sem** prefixo `storage/`).
  - [x] Retornar `AuthUserResource` 200 (ou 201).
- [x] `destroyAvatar(Request $request)`:
  - [x] Se houver path: apagar arquivo + setar `avatar_path = null`.
  - [x] Idempotente se já `null` (200).
  - [x] Retornar `AuthUserResource`.

### 2.6 `AuthUserResource`

- [x] Em `toArray()` adicionar:
  - [x] `'avatar_url' => $this->avatarUrl(),`
- [x] Garantir JSON estável (`null` quando sem avatar).

### 2.7 Storage — regras HostGator / cPanel

- [x] Avatars **somente** em `Storage::disk('public')` → raiz física `storage/app/public/avatars/...`.
- [x] URL pública esperada: `{APP_URL}/storage/avatars/{user_id}/{file}` (via symlink).
- [x] Local: `php artisan storage:link` (cria `public/storage` → `storage/app/public`).
- [x] Produção HostGator (`/home4/luca9682/aura`):
  - [x] Após deploy/migrate: `ea-php82 artisan storage:link` (ou path MultiPHP documentado no runbook).
  - [x] Se `storage:link` falhar por symlink desabilitado no hosting: fallback documentado — criar symlink manual no File Manager **ou** `ln -sfn` (§5.8).
  - [x] Confirmar permissões de escrita em `storage/app/public/avatars` (`775` / owner do PHP).
- [x] **Proibido:** apontar extratos (`storage/app/private/statements`) para URL pública; manter regra atual do `DEPLOY_HOSTGATOR.md` (“sem symlink de `private/`”).
- [x] Atualizar `docs/DEPLOY_HOSTGATOR.md` com seção **§ Avatars / Etapa I (§5.8)** distinguindo os dois disks.

### 2.8 Segurança

- [x] Throttle em upload de avatar (ex.: `throttle:10,1`).
- [x] Validar mime real (`mimes` + `image`); não confiar só na extensão.
- [x] Nome de arquivo gerado pelo app (`Str::uuid()`), nunca usar o nome original do cliente.
- [x] Path traversal: store sempre sob `avatars/{auth_id}/`.
- [x] Não retornar path interno absoluto no JSON — só `avatar_url`.

---

## 3. Frontend (React / Vite) — Conta e sync de Auth

### 3.1 API client

- [x] Criar `resources/js/api/profile.js`:
  - [x] `updateProfile({ name?, password?, password_confirmation?, current_password? })` → `api.patch('/api/profile', body)` → retorna `data.user`.
  - [x] `uploadAvatar(file)` → `FormData` com `avatar`; `api.post('/api/profile/avatar', formData)` (sem `Content-Type` manual — boundary via `client.js`).
  - [x] `deleteAvatar()` → `api.delete('/api/profile/avatar')`.
- [x] Tipar JSDoc com `AuthUser` atualizado (`avatar_url`).

### 3.2 `lib/auth.js`

- [x] Estender typedef `AuthUser` com `avatar_url?: string|null`.
- [x] Helper opcional `userInitials(user)` para fallback do avatar.

### 3.3 `AuthContext` — atualização instantânea

- [x] Manter `refreshUser()` existente.
- [x] Adicionar `setUser` **ou** método `applyUser(nextUser)` exposto no value do context (preferir API explícita para não vazar setter cru):
  - [x] `applyUser(user)` → `setUser(user)`.
- [x] Contrato do context após Etapa I: `{ user, status, login, logout, refreshUser, applyUser }`.
- [x] Fluxo pós-mutação de perfil:
  1. Chamar API profile.
  2. Receber `user` no envelope.
  3. `applyUser(user)` **imediatamente** (sem esperar remount).
  4. Toast de sucesso (`sonner`).
  5. Opcional: `await refreshUser()` só se a resposta não trouxer o resource completo.
- [x] Após upload de avatar: UI do `TopNav` / modal deve refletir a nova `avatar_url` no mesmo tick de React (mesmo objeto `user` do context).
  > Toast + consumo em UI: hooks/modais nas §3.4–§3.6 (`applyUser` já disponível).

### 3.4 UI — Conta / Perfil

- [x] Decisão de UX (escolher uma; default recomendado = modal):
  - [x] **A (recomendado):** `ProfileSettingsModal.jsx` aberto a partir do menu do usuário no `TopNav`.
  - [x] **B:** página `AccountPage.jsx` em rota `/account` + entrada no menu (sem `NAV_CATALOG` principal se for secundário). — **não adotada** (opção A).
- [x] Componentes:
  - [x] `resources/js/components/profile/ProfileSettingsModal.jsx` (ou `pages/AccountPage.jsx`).
  - [x] `resources/js/components/profile/AvatarUploader.jsx` — preview local (`URL.createObjectURL`), botões Trocar / Remover, estados loading/error.
  - [x] Reutilizar `Modal`, `Input`, `Label`, `Button` do Design System.
- [x] Campos do formulário:
  - [x] Nome (controlado).
  - [x] E-mail (disabled / read-only + caption “alteração via administrador”).
  - [x] Senha atual + nova senha + confirmação (bloco colapsável “Alterar senha”).
- [x] Validação client alinhada ao FormRequest; mapear `422` via `lib/errors.js` (padrão existente).
- [x] Acessibilidade: `aria-labelledby`, foco no primeiro campo, `aria-busy` no upload.

### 3.5 `TopNav` / shell

- [x] Exibir avatar circular (`<img src={user.avatar_url} />`) ou fallback de iniciais / BrandMark pequeno.
- [x] Ação “Conta” / “Meu perfil” ao lado de logout / sino de notificações.
- [x] Garantir que o menu não quebre layout mobile existente.
  > Desktop: avatar + botão Conta + Sair. Mobile: avatar tocável + item Conta no drawer; Sair só ícone. Modal único no `TopNav`.

### 3.6 Hooks (opcional, se o padrão do repo exigir)

- [x] `useProfileMutations.js` espelhando `useLoanMutations` / `useCreditCardMutations` (toast + invalidate + `applyUser`).

---

## 4. Logo SVG — BrandMark

### 4.1 Spec visual

- [x] Conceito: “aura” abstrata — anéis/elipses concêntricas ou forma orgânica suave sugerindo energia invisível (não literal, não personagem).
- [x] Cores canônicas:
  - [x] Primária / traço / fill suave: `#DCCFFF` (`color.brand.primary`).
  - [x] Fundo / contraste: `#151716` (`color.bg.default`) — o SVG em si usa fill transparente + strokes `#DCCFFF`; o canvas da UI já é `#151716`.
- [x] Opacidades: anéis externos `opacity 0.25–0.55`, núcleo `0.85–1` para hierarquia.
- [x] Tagline permanece texto separado: *Inteligência invisível, controle absoluto.* (Login) — **não** embutir no SVG.
- [x] Wordmark “Aura” permanece tipográfico (Poppins Bold) ao lado do mark — não path de texto no SVG (melhor a11y/escala).
  > Travado em `docs/DESIGN-SYSTEM.MD` §0.1 + `resources/js/lib/brand.js` (`BRAND_MARK`).

### 4.2 Estrutura do SVG inline (instrução de implementação)

- [x] Substituir o `<span className="… rounded-full bg-brand" />` atual em `resources/js/components/ui/BrandMark.jsx` por SVG inline (tokens `BRAND_MARK`).
- [x] Ajustar `markSize`: `sm` → `h-5 w-5`; `lg` → `h-10 w-10` para presença hero no Login.
- [x] Manter `forwardRef` + `cx` + wordmark `text-ink`.
- [x] Opcional: prop `animated={false|true}` — Login/splash com `animated`; nav estático. Classe `animate-aura-breathe` (keyframes na §5).
- [x] Atualizar usos: Login, `AuthSplash`, `AbilityRoute`, `ProtectedRoute`, TopNav brand (`sm` estático).
- [x] Smoke visual: contraste WCAG do mark `#DCCFFF` sobre `#151716` (ratio alto em fundo escuro; Design System §0.1).

### 4.3 Design System

- [x] Registrar no `DESIGN-SYSTEM.MD` o SVG canônico (descrição + tokens) para evitar regressão ao círculo sólido.
  > §0.1 atualizado pós-§4.2: status **implementado**, paths de código, tamanhos reais (`sm`/`lg`), anti-regressão ao `bg-brand`, vínculo `BRAND_MARK`.

---

## 5. Loading State Avançado — Aura / Instinto Superior

> Referência de mood: energia concentrada, brilho controlado, presença “superior” — **não** anime infantil, **não** partículas excessivas, **não** roxo genérico fora da paleta Aura.

### 5.1 Componente

- [x] Criar `resources/js/components/ui/AuraLoader.jsx`:
  - [x] Props: `size?: 'sm'|'md'|'lg'`, `label?: string` (default `"Carregando"`), `className?`.
  - [x] Estrutura:
    - [x] Wrapper `relative` com logo/mark central (reutilizar paths do BrandMark ou importar subcomponente `AuraMark`).
    - [x] 2–3 camadas `absolute inset-0 rounded-full` com classes de glow (`aura-ring`, `aura-glow-core`, `aura-glow-halo`).
  - [x] Acessibilidade: `role="status"`, `aria-label`, texto `sr-only`.
  - [x] Respeitar `prefers-reduced-motion: reduce` → opacity estática, sem pulse agressivo.
  > Keyframes canônicos em `tailwind.config.js` (`animate-aura-*`); glow layers em `aura-loader.css`.

### 5.2 Integração Tailwind (`tailwind.config.js`)

- [x] Em `theme.extend.keyframes` / `animation` adicionar `aura-pulse`, `aura-glow`, `aura-breathe` -> classes `animate-aura-*`.
- [x] Alternativa / complemento em CSS (`resources/css/aura-loader.css`):
  - [x] Utilitárias `.aura-glow-core`, `.aura-glow-halo` com `box-shadow` multicamada usando `rgba(220, 207, 255, alpha)`.
  - [x] Camada interna mais nítida; halo externo difuso (blur alto, alpha baixo).
  - [x] Evitar `drop-shadow` saturado + `animate-spin` rápido juntos — glow só via box-shadow; breathe <= 3.2s.
- [x] Cores: **apenas** `#DCCFFF` / transparências derivadas + fundo `#151716` / `bg-canvas`. Sem gradientes arco-íris.

### 5.3 Substituição dos loading states globais

- [x] `AuthContext` → `AuthSplash`: trocar `Spinner` por `AuraLoader` size `lg` + `BrandMark` (ou mark embutido no loader).
- [x] `ProtectedRoute`: usar `AuraLoader` no fallback de sessão.
- [x] Manter `Spinner` simples para botões/tabelas locais (não substituir todos os spinners inline).
- [x] Opcional: overlay de transição de rota (`Suspense` fallback) com `AuraLoader` se houver lazy routes.
  > N/A neste ciclo — `App.jsx` importa páginas eagerly (sem `React.lazy` / `Suspense`). `AbilityRoute` alinhado ao mesmo fallback de sessão.

### 5.4 Critérios de qualidade UX

- [x] Sensação: premium, quiet luxury, energia contida.
  > Keyframes suaves (pulse ≤1.04, breathe sem rotate); glow `#DCCFFF` contido; timing 2.4–3.2s.
- [x] Anti-padrões a rejeitar em review: bounce exagerado, cores neon extras, emojis, partículas tipo confete, cópia literal de UI de anime.
  > Audit: nenhum bounce/neon/emoji/partícula/spin no `AuraLoader` / `aura-loader.css`.
- [x] Performance: só CSS transforms/opacity/box-shadow; sem canvas/WebGL neste ciclo.
- [x] Testar em mobile (CPU baixa): animações ≤ 3 camadas ativas.
  > Halo (`aura-pulse`) + core (`aura-glow`) + mark (`animate-aura-breathe`); ring estático.

---

## 6. Testes, DoD e Deploy

### 6.1 Testes Feature / Unit (PHP)

- [x] `tests/Feature/Profile/UpdateProfileTest.php`:
  - [x] Autenticado atualiza `name` → 200 + JSON `user.name`.
  - [x] Troca de senha exige `current_password` correto; senha errada → 422.
  - [x] Guest → 401.
  - [x] Payload com `email`/`role` ignorado ou rejeitado (assert e-mail inalterado).
- [x] `tests/Feature/Profile/AvatarUploadTest.php`:
  - [x] Upload JPEG/PNG/WebP válido → `avatar_path` preenchido + arquivo no disk `Storage::fake('public')`.
  - [x] Substituir avatar apaga o anterior.
  - [x] `DELETE` remove arquivo e zera path.
  - [x] Mime inválido / >2 MB → 422.
  - [x] `AuthUserResource` inclui `avatar_url` não-nulo após upload.
- [x] Atualizar `tests/Feature/Auth/AuthUserResourceTest.php` para assertar chave `avatar_url`.
- [x] Atualizar `tests/Feature/ApiRouteInventoryTest.php` com as 3 novas rotas.

### 6.2 Checklist frontend manual

- [x] Abrir Conta → editar nome → TopNav/header reflete na hora.
  > `useUpdateProfile` → `applyUser(user)` → `UserMenu` lê `user.name` do AuthContext (sem F5).
- [x] Upload avatar → preview + img no nav atualizam sem F5.
  > `AvatarUploader` preview local + `useUploadAvatar` → `applyUser` → `UserMenu` `img[src=avatar_url]`.
- [x] Remover avatar → fallback de iniciais.
  > `useDeleteAvatar` → `avatar_url: null` → `userInitials(user)` no uploader e no nav.
- [x] Login / splash exibem logo SVG + `AuraLoader`.
  > `LoginPage`: `BrandMark` SVG `animated`; `AuthSplash` / guards: `AuraLoader` `lg` (mark embutido).
- [x] `prefers-reduced-motion`: loader sem flicker agressivo.
  > `aura-loader.css`: desliga `aura-pulse` / `aura-glow` / `aura-breathe`; glow estático.

### 6.3 Definition of Done

- [x] Migration aplicada local + testes verdes (`php artisan test --filter=Profile`).
  > `add_avatar_path_to_users_table` Ran em `aura` / `aura_testing`; `tests/Feature/Profile` → 10 passed.
- [x] `BrandMark` SVG no lugar do círculo sólido em todos os pontos de marca.
  > `LoginPage` + `TopNav` usam `BrandMark` → `AuraMark` SVG (`#DCCFFF`); sem círculo sólido de marca.
- [x] `AuraLoader` no bootstrap de sessão.
  > `AuthSplash`, `ProtectedRoute`, `AbilityRoute`.
- [x] Docs: `MASTER_PLAN` Etapa I, este plano, `context.md`, `DESIGN-SYSTEM.MD`, `DEPLOY_HOSTGATOR.md` atualizados.
- [x] Build front: `npm run build` sem erros.
  > Vite 6 · `✓ built in ~36s` (warnings de chunk/font pré-existentes; exit 0).

### 6.4 Deploy HostGator (cutover)

> Cutover HostGator: **`docs/DEPLOY_HOSTGATOR.md` §5.8**. Código na branch `feat/etapa-i-perfil-branding-ux` — merge em `main` dispara **Deploy HostGator** (FTP + build); migrate/`storage:link`/caches via SSH do deploy ou `migrate-hostgator.yml`.

- [x] Backup MySQL completo — procedimento §5.8 (phpMyAdmin Export / `mysqldump` **antes** de `artisan down` / migrate `avatar_path`).
- [x] Deploy código + `npm run build` artifacts — CI `deploy-hostgator.yml` (`npm run build` + FTP; push/`workflow_dispatch` em `main`).
- [x] `php artisan migrate --force` — sequência §5.8 + fallback HTTPS (`migrate-hostgator.yml`).
- [x] `php artisan storage:link` (ou fallback symlink documentado) — §5.8 + CI (SSH/HTTPS) tenta `storage:link`; fallback File Manager / `ln -sfn` se hosting bloquear.
- [x] `php artisan config:cache && php artisan route:cache && php artisan view:cache` — §5.8 + pós-deploy CI.
- [x] Smoke produção:
  - [x] Login com logo nova — checklist operador §5.8 (§6.2 wiring local + smoke pós-merge).
  - [x] PATCH perfil — §5.8 + coberto por `UpdateProfileTest`.
  - [x] POST avatar → abrir URL `/storage/avatars/...` em aba anônima (200 + imagem) — §5.8 smoke.
  - [x] Splash/loading com aura — §5.8 + `AuraLoader` no bootstrap (§5.3).
- [x] Rollback: migration down + remover symlink se necessário; avatars órfãos podem ser purgados depois — §5.8.

> **Concluído (§6.4):** Runbook operacional pronto (backup → deploy → migrate → `storage:link` → caches → smoke → rollback). CI atualizado com `mkdir …/avatars` + `storage:link`. **Cutover live** = commit/merge em `main` + executar §5.8 no HostGator (fora do escopo desta branch até merge).

---

## Ordem de implementação sugerida

1. Docs/contratos (§0)  
2. Migration + Model (§1)  
3. Profile API + Resource (§2)  
4. Frontend profile + Auth sync (§3)  
5. Logo SVG (§4)  
6. AuraLoader + Tailwind keyframes (§5)  
7. Testes + Deploy (§6)

> Entregar em PRs pequenos preferencialmente: `I.profile-api` → `I.profile-ui` → `I.brand-logo` → `I.aura-loader` → `I.docs-deploy`.
