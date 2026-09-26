# Deploy HostGator (Aura)

## Secrets do GitHub (Settings → Secrets and variables → Actions)

| Secret | Valor |
|--------|--------|
| `FTP_SERVER` | `162.241.63.46` ou `ftp.vonluqi.com` |
| `FTP_USERNAME` | `luca9682` |
| `FTP_PASSWORD` | senha do cPanel |
| `FTP_SERVER_DIR` | `aura/` (relativo ao home `/home4/luca9682`) |

## Preparação única no cPanel

1. Criar pasta `/home4/luca9682/aura` (File Manager).
2. Domínios → `vonluqi.com` → Document Root = `/home4/luca9682/aura/public`.
3. Após o **primeiro** deploy, criar `.env` em `/home4/luca9682/aura/.env` (não versionado) com valores de produção.
4. No Terminal/SSH do cPanel (se disponível), na pasta `aura`:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se não houver SSH: use o Terminal do cPanel ou rode migrate uma vez via script temporário (remover depois).

## Fluxo

`git push origin main` → Action faz `composer install`, `npm ci`, `npm run build` → FTP para `/aura/`.

`.env` de produção **nunca** sobe pelo Git/FTP deste workflow (está no exclude).
