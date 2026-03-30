# Production setup (GitHub → Hostinger)

This guide assumes you **push the project to GitHub** and **pull (or deploy) it on Hostinger**—typically via **SSH + Git** on a plan that supports it (Business / Cloud VPS–style hosting). Shared hosting details vary; adapt paths to what Hostinger shows in **hPanel** (document root, PHP version, Node availability).

---

## 1. Before you push (on your PC)

1. **Do not commit secrets**  
   `.env` is ignored by Git. Never commit real passwords or API keys.

2. **Run tests locally** (optional but recommended)  
   `php artisan test`

3. **Build frontend assets**  
   The repo ignores `public/build`. You must produce assets **on the server** (if Node is available) **or** build locally and upload `public/build` when deploying (see step 6).

---

## 2. Hostinger prerequisites

- **PHP 8.3+** (project requires `^8.3`; match `composer.json`).
- Extensions Laravel needs: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, and typically `intl`. Enable **OPcache** if offered.
- **Composer** available on the server (SSH) or run `composer install` locally and upload `vendor` (not ideal; Composer on server is better).
- **MySQL** database created in hPanel; note **host**, **database name**, **user**, **password**.
- **Document root** should point to the app’s **`public`** folder (not the project root). On Hostinger this is often done with **“public_html” → `public`** or a subdomain pointing to `.../yourapp/public`.

---

## 3. Get the code on the server

**Option A — Git clone (SSH)**

```bash
cd ~
git clone https://github.com/YOUR_USER/YOUR_REPO.git healthsys
cd healthsys
```

**Option B — hPanel Git deployment**  
Connect the repo in the panel and deploy to your target directory; then SSH in for the commands below.

---

## 4. Environment file

1. Copy the example env:

   ```bash
   cp .env.example .env
   ```

2. Edit `.env` (use `nano .env` or hPanel file manager). Set at minimum:

   | Variable | Production guidance |
   |----------|---------------------|
   | `APP_NAME` | Your clinic name |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | **`false`** |
   | `APP_URL` | `https://your-domain.com` (no trailing slash) |
   | `APP_TIMEZONE` | e.g. `Asia/Karachi` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | From Hostinger MySQL |
   | `SESSION_DRIVER` | `database` (ensure sessions table exists—Laravel default migrations include it) |
   | `CACHE_STORE` | `database` is fine if Redis is not available |
   | `QUEUE_CONNECTION` | `database` (fine if you are not relying on async queues yet) |
   | `HMS_SKIP_ROLE_PAGE_GUARDS` | **`false`** — enforces role-based access |
   | `HMS_CLINIC_NAME` | Printed on receipts |
   | SMS vars | Set `HMS_SMS_ENABLED`, `VEEVOTECH_*`, shift-close phones only if you use SMS |

3. Generate app key:

   ```bash
   php artisan key:generate
   ```

---

## 5. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

If Composer is not global:

```bash
php composer.phar install --no-dev --optimize-autoloader
```

---

## 6. Frontend build (`public/build`)

**If Node.js + npm exist on the server:**

```bash
npm ci
npm run build
```

**If Hostinger has no Node:** on your PC, after `npm ci`, run `npm run build`, then upload the generated **`public/build`** folder to the server (same path inside the project). Re-run this whenever you change JS/CSS sources.

---

## 7. Database migrations (and optional seed)

```bash
php artisan migrate --force
```

**First admin user** (only if you use the default seeder): set in `.env` before seeding:

- `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD`

Then:

```bash
php artisan db:seed --force
```

The seeder creates the admin **only if that email does not exist**; it also seeds default services. **Change the admin password** after first login.

---

## 8. Storage link

```bash
php artisan storage:link
```

---

## 9. Optimize Laravel for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After you change `.env` or routes/config, clear and rebuild:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 10. Permissions

Ensure the web user can write to:

- `storage/`
- `bootstrap/cache/`

Typical approach (adjust user/group to Hostinger’s `www-data` or panel guidance):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

---

## 11. HTTPS & `APP_URL`

- Enable **SSL** in hPanel (Let’s Encrypt).
- Set `APP_URL` to `https://...` to avoid mixed content and wrong links.

---

## 12. Token screen & optional TV controls

- Public waiting URL: **`/token-screen`** (bookmark with `?queue_id=...` after picking a queue).
- If you use **corner buttons** on the TV without staff login, set a strong random `TOKEN_SCREEN_CONTROL_SECRET` in `.env` and configure the client to send it as **`X-HMS-Control-Secret`** on control API calls (see technical doc).

---

## 13. Ongoing deploys (after the first setup)

On the server, from the app directory:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build   # or upload public/build from CI/local
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 14. Troubleshooting

| Symptom | What to check |
|---------|----------------|
| 500 error, blank page | `storage/logs/laravel.log`, `APP_DEBUG` temporarily true **only on a staging** copy |
| Assets missing / unstyled | `npm run build`, `public/build` exists, `APP_URL` correct |
| Login/session issues | `SESSION_DRIVER`, database connection, `php artisan migrate` |
| “No application encryption key” | `php artisan key:generate` |
| Old CSS after deploy | Hard refresh, `php artisan view:clear` |

---

## 15. Queue workers & cron (future-proofing)

Current HMS features are largely **synchronous**; you may not need a queue worker yet. If you later add queued jobs or scheduled tasks:

- Run a **queue worker** (Supervisor or Hostinger’s process manager if available): `php artisan queue:work`
- Add a **cron** entry: `* * * * * cd /path/to/healthsys && php artisan schedule:run >> /dev/null 2>&1`

---

If your Hostinger plan only supports **FTP** without SSH, you can still deploy by uploading a **zip** of the project (including `vendor` and `public/build` built locally), but Git + SSH is easier to repeat safely.
