# Deploying to GoDaddy cPanel shared hosting

The API and the admin panel are **one Laravel app**. You deploy it once:

- `https://api.yourdomain.com/api/v1/*` — the REST API (mobile app)
- `https://api.yourdomain.com/admin` — the Filament admin panel (products,
  categories, coupons, orders, users, store settings, pincodes, delivery)

## Target layout on the server

```
/home/CPUSER/
└── public_html/                     ← yourdomain.com   (WordPress store)
    ├── index.php  wp-admin/  ...     (WordPress)
    └── dailzoapi/                    ← Laravel app root  (NOT web-served directly)
        ├── app/ bootstrap/ config/ database/ routes/ storage/ vendor/
        ├── artisan   .env   .htaccess   (".htaccess" here = "deny all")
        └── public/                   ← api.yourdomain.com document root
            ├── index.php  .htaccess
            └── uploads/  css/  js/
```

Future apps drop in the same way: `public_html/app2/` + a subdomain
`app2.yourdomain.com` → docroot `public_html/app2/public`.

> **Security note:** the app's `.env`, `vendor/`, etc. sit under `public_html`,
> so `yourdomain.com/dailzoapi/.env` would otherwise be downloadable. The
> `dailzoapi/.htaccess` shipped in the zip (`Require all denied`) blocks every
> direct hit through the main domain. It does **not** affect
> `api.yourdomain.com`, because that subdomain's document root is
> `dailzoapi/public` (Apache only reads `.htaccess` from a site's docroot
> downward). If you'd rather be extra safe, put `dailzoapi/` at
> `/home/CPUSER/dailzoapi/` (a sibling of `public_html`) instead — the steps are
> identical, only the paths change.

---

## Step 1 — Point your domain at the hosting (replace the temp site)

Your hosting shipped with a temp address like `ohj.3ea.mytemp.website` that
serves `public_html`. To use `yourdomain.com`:

1. **cPanel → Domains → Create A New Domain** → enter `yourdomain.com`,
   **Document Root = `public_html`** (same as the temp domain — both will serve
   WordPress). Do the same for `www.yourdomain.com` if it isn't added
   automatically.
2. **DNS:**
   - Domain registered at GoDaddy + hosting at GoDaddy → DNS is linked
     automatically. Check **cPanel → Zone Editor**: `A  @ → <hosting IP>` and
     `A/CNAME  www`.
   - Registered elsewhere → at that registrar set `A  @ → <hosting IP>` and
     `A  www → <hosting IP>`. The hosting IP is in **cPanel → right sidebar →
     "Shared IP Address"**. Propagation: minutes to a few hours.
3. **Delete the placeholder page:** cPanel → File Manager → `public_html/` →
   delete GoDaddy's default `index.html` / `_defaultwebpage.html` /
   "coming soon" file.
4. Install WordPress into `public_html` (cPanel → *Installatron* / *WordPress*,
   or manually).
5. The `*.mytemp.website` name stays attached to the account and is harmless —
   just stop using it. It cannot be fully removed.

## Step 2 — Create the API subdomain

1. **cPanel → Domains → Create A New Domain** → `api.yourdomain.com`.
2. Set **Document Root = `public_html/dailzoapi/public`**.
   (cPanel will offer `public_html/api.yourdomain.com` by default — change it.)
3. DNS for the subdomain is added automatically when GoDaddy manages the zone.

## Step 3 — PHP version + extensions

**cPanel → Select PHP Version** (MultiPHP Manager), for `api.yourdomain.com`:

- PHP **8.2** or **8.3**
- Enable: `bcmath ctype curl dom fileinfo intl mbstring openssl pdo_mysql tokenizer xml zip gd`
  (**`intl` is required** — the admin panel 500s without it.)

## Step 4 — Create the database

**cPanel → MySQL Databases:**

1. Create a database — note the full name, e.g. `cpuser_dailzo`.
2. Create a user — note `cpuser_dbuser` + the password.
3. **Add User To Database** → grant **All Privileges**.

## Step 5 — Upload the app

Use the prepared archive **`dailzo-deploy.zip`** (it already contains `vendor/`,
the built admin-panel assets, and the 26 product images; it does **not** contain
`.env`).

1. cPanel → File Manager → open `public_html/`.
2. **Upload** `dailzo-deploy.zip`.
3. Right-click → **Extract**. You get `public_html/dailzoapi/`.
4. Delete the zip.

(SFTP works too — upload so the tree is `public_html/dailzoapi/app`, `.../public`, …)

## Step 6 — Configure `.env`

1. Generate a key on your machine: `cd server && php artisan key:generate --show`
   → copy the `base64:...` string.
2. In File Manager, open `public_html/dailzoapi/`, **rename `.env.production` to
   `.env`** and edit:

```
APP_URL=https://api.yourdomain.com
APP_KEY=base64:...            # from step 1
DB_DATABASE=cpuser_dailzo
DB_USERNAME=cpuser_dbuser
DB_PASSWORD=your-db-password
OTP_DEBUG=true                # leave true until an SMS gateway is added
```

(File Manager hides dotfiles by default — enable **Settings → Show Hidden Files**.)

## Step 7 — Run migrations

**If you have SSH or cPanel → Terminal:**

```bash
cd ~/public_html/dailzoapi
php artisan migrate --force --seed        # --seed: first deploy only
php artisan storage:link || true
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
```

**No SSH / Terminal — use a one-off cron job:**

cPanel → **Cron Jobs** → add (any near-future time):

```
cd /home/CPUSER/public_html/dailzoapi && /usr/local/bin/php artisan migrate --force --seed
```

Wait for it to run (check `public_html/dailzoapi/storage/logs/laravel.log`), then
**delete that cron job**. If `/usr/local/bin/php` is wrong, get the CLI path from
cPanel → *Select PHP Version* (top of page) — often `/opt/cpanel/ea-php82/root/usr/bin/php`.

## Step 8 — Permissions

File Manager → set these to **755** (recursive), owner-writable is enough since
PHP runs as your user:

- `public_html/dailzoapi/storage`
- `public_html/dailzoapi/bootstrap/cache`
- `public_html/dailzoapi/public/uploads`

## Step 9 — SSL

cPanel → **SSL/TLS Status** → select `api.yourdomain.com` (and `yourdomain.com`,
`www`) → **Run AutoSSL**. HTTPS is forced automatically in production. Wait until
the padlock shows before testing.

## Step 10 — Scheduler cron (optional but recommended)

cPanel → **Cron Jobs** → **every minute**:

```
/usr/local/bin/php /home/CPUSER/public_html/dailzoapi/artisan schedule:run >> /dev/null 2>&1
```

## Step 11 — Verify

| URL | Expect |
|---|---|
| `https://api.yourdomain.com/api/v1/health` | `{"success":true,...}` |
| `https://api.yourdomain.com/api/v1/products` | product list JSON |
| `https://api.yourdomain.com/admin` | Filament login |
| `https://yourdomain.com/dailzoapi/.env` | **403 Forbidden** (must not download) |

Log in to `/admin` with **`admin@dailzo.app` / `password`** →
**change the password immediately** (Users → your row → set a new password).
That panel is where you manage products, categories, coupons, orders, store
settings, serviceable pincodes and delivery partners.

## Step 12 — Point the mobile app

Set its API base URL to `https://api.yourdomain.com/api/v1`.

---

## Redeploying later

Re-upload `dailzo-deploy.zip` and re-extract over `dailzoapi/` (keep your `.env`),
then re-run the Step 7 cache/migrate commands. Or, with SSH:
`cd ~/public_html/dailzoapi && bash bin/deploy.sh`.
