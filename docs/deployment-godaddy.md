# Deploying `server/` to GoDaddy cPanel shared hosting

## 0. One-time preflight (cPanel)

1. **Select PHP Version** → **8.2** or **8.3**. Enable extensions:
   `bcmath ctype curl dom fileinfo intl mbstring openssl pdo_mysql tokenizer xml zip gd`.
2. **MySQL Databases** → create a database + user, add the user to the database
   with *All Privileges*. Note the full names (cPanel prefixes them, e.g.
   `cpuser_dailzo`).
3. Decide the layout:
   - **A (recommended): subdomain.** *Domains → Create a new domain* →
     `api.yourdomain.com`, and set its **document root** to
     `dailzo/server/public`.
   - **B: primary domain.** Keep the app outside `public_html` and use the
     loader files in step 3B.
4. Check if **SSH** is enabled (cPanel → *SSH Access*). It makes deploys much
   easier; everything below has a no-SSH fallback.

## 1. Get the code onto the server

**With Git (cPanel → Git Version Control):** clone the repo to `~/dailzo`.

**Without Git:** upload the repo as a zip and extract to `~/dailzo`.

Either way you still need `vendor/` (it is git-ignored):

- **SSH:** `cd ~/dailzo/server && composer install --no-dev --optimize-autoloader`
- **No SSH:** run that command locally, then upload `server/vendor/` via SFTP.

## 2. Configure

```bash
cd ~/dailzo/server
cp .env.example .env
```

Edit `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com
APP_KEY=                      # generate below

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpuser_dailzo
DB_USERNAME=cpuser_dailzo
DB_PASSWORD=********

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=uploads

OTP_DEBUG=false               # set true only until an SMS gateway is wired
```

Generate the key:
- **SSH:** `php artisan key:generate`
- **No SSH:** run `php artisan key:generate --show` locally and paste the
  `base64:...` value into `APP_KEY`.

## 3. Web root

### 3A. Subdomain (document root = `server/public`)
Nothing else to do — `server/public/.htaccess` (shipped) handles routing.

### 3B. Primary domain (app outside `public_html`)
Put these two files in `public_html/`:

`public_html/.htaccess`
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ server-public/$1 [L]
</IfModule>
```

Then symlink (or copy) the app's public dir:
```bash
ln -s ~/dailzo/server/public ~/public_html/server-public
```
If symlinks are disabled, instead copy `server/public/*` into
`public_html/server-public/` and edit its `index.php` paths to point at
`~/dailzo/server/vendor` and `~/dailzo/server/bootstrap/app.php`.

## 4. Migrate + optimise

**SSH:**
```bash
cd ~/dailzo/server
php artisan migrate --force --seed        # --seed only on the very first deploy
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
php artisan storage:link || true          # harmless if it fails; uploads don't need it
```

**No SSH:** add a **Cron Job** that runs once, or temporarily add a protected
route that calls `Artisan::call('migrate', ['--force' => true])`, hit it once,
then remove it.

## 5. Cron (cPanel → Cron Jobs)

Laravel scheduler, every minute:
```
/usr/local/bin/php /home/CPUSER/dailzo/server/artisan schedule:run >> /dev/null 2>&1
```
(Use the PHP binary path from cPanel → *Select PHP Version* → "current PHP CLI".)

## 6. Filament admin

- Visit `https://api.yourdomain.com/admin`.
- Log in with the seeded admin (`admin@dailzo.app` / `password`) and
  **change the password immediately**, or create a fresh admin:
  `php artisan make:filament-user` (SSH), or set `role = ADMIN` + a password on
  a user via the User resource.

## 7. Uploads

`server/public/uploads/` must be writable (755/775). It already contains the
images migrated from the old backend. No `storage:link` needed for these.

## On every subsequent deploy

```bash
git pull                      # or re-upload
composer install --no-dev --optimize-autoloader   # if composer.lock changed
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
```

`server/bin/deploy.sh` bundles the post-pull steps.
