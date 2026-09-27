# Deploy HostGator (Aura)

## Secrets do GitHub (Settings → Secrets and variables → Actions)

| Secret | Valor |
|--------|--------|
| `FTP_SERVER` | `vonluqi.com` (o mesmo host que funciona no FileZilla) |
| `FTP_USERNAME` | `luca9682` |
| `FTP_PASSWORD` | senha do cPanel |
| `FTP_SERVER_DIR` | `aura/` |

## Subdomínio (Cenário A — Em uso)

- `aura.vonluqi.com` → Document Root = `/home4/luca9682/aura/public`
- Laravel em `/home4/luca9682/aura` (`.env`, `vendor/`, `app/` **fora** do web root)
- Domínio principal `vonluqi.com` permanece em `/public_html` (fixado pela HostGator)

## Como o deploy funciona

1. CI: `composer install` + `npm run build`
2. Empacota `vendor/` em **um** arquivo `vendor.tar.gz` (FTP arquivo-a-arquivo estoura timeout)
3. FTP do código **sem** `vendor/`
4. FTP do `vendor.tar.gz`
5. SSH extrai o `vendor.tar.gz` em `~/aura`

### SSH obrigatório

No cPanel, busque **SSH Access** / **Gerenciar acesso SSH** / **Manage Shell Access** → **Enable** para `luca9682`.

Se o log da Action mostrar `Shell access is not enabled on your account!`, o migrate/extract **não rodou** (a HostGator aceita o TCP e responde essa mensagem; workflows antigos marcavam verde à toa).

Sem shell habilitado:
- `vendor.tar.gz` não é extraído via SSH
- `php artisan migrate` não cria tabelas → site 500 / DB vazia

Depois de habilitar, rode **Migrate HostGator** (ou um novo deploy).

## Preparação única após 1º deploy

1. Criar `.env` em `/home4/luca9682/aura/.env` (use `.env.production.example` como base).
2. No Terminal/SSH:

```bash
cd ~/aura
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Fluxo

`git push origin main` → Action faz build + FTP + extract.

`.env` de produção **nunca** sobe pelo Git/FTP (está no exclude).
