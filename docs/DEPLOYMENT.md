# Deploying CoffeeProject

Production runs without Docker on a Debian or Ubuntu server: nginx, PHP 8.5-FPM, MySQL 8.0, Supervisor (queue worker) and cron (scheduler).

Deploys are manual: push to `main`, then log into the server and run [`deploy.sh`](../deploy.sh) as `coffeeproject`. It does the following:

1. Pulls the new commit.
2. Installs dependencies and builds the frontend.
3. Runs migrations and caches the config.
4. Restarts the queue worker and reloads PHP-FPM.

The site shows a maintenance page for the minute or so this takes. Resend retries webhooks that arrive during that time, and `mail:sync` catches anything missed.

```
push to main ─▶ ssh into the server ─▶ sudo -u coffeeproject -i ─▶ ./deploy.sh
```

The server configs live in [`deploy/`](../deploy):

| File | Installed as |
| --- | --- |
| `deploy/nginx.conf` | `/etc/nginx/sites-available/coffeeproject` |
| `deploy/php-fpm-pool.conf` | `/etc/php/8.5/fpm/pool.d/coffeeproject.conf` |
| `deploy/php.ini` | `/etc/php/8.5/fpm/conf.d/99-coffeeproject.ini` and `/etc/php/8.5/cli/conf.d/99-coffeeproject.ini` |
| `deploy/supervisor.conf` | `/etc/supervisor/conf.d/coffeeproject.conf` |
| `deploy/crontab` | crontab of the `coffeeproject` user |
| `deploy/sudoers` | `/etc/sudoers.d/coffeeproject` |

The guide uses the following names; change them where yours differ:
- **App user:** `coffeeproject`. It owns the code and runs PHP-FPM, the queue worker, cron and deploys.
- **Path:** `/var/www/coffeeproject`
- **Domain:** `coffeeproject.example.com`

---

## Part 1: Server setup (once)

Run these as root or with `sudo`.

### 1. Install packages

PHP 8.5 comes from Ondřej Surý's repository, which is the standard source for current PHP on Debian/Ubuntu:

```bash
# Debian 12/13
apt update && apt install -y lsb-release ca-certificates curl
curl -sSLo /tmp/sury.deb https://packages.sury.org/debsuryorg-archive-keyring.deb && dpkg -i /tmp/sury.deb
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
  > /etc/apt/sources.list.d/php.list

# Ubuntu 24.04 instead
# add-apt-repository ppa:ondrej/php

apt update
apt install -y nginx git unzip supervisor certbot python3-certbot-nginx \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-intl php8.5-zip \
  php8.5-bcmath php8.5-gd php8.5-xml php8.5-curl
```

OPcache, pcntl and exif are part of the core PHP 8.5 packages. Check that everything is loaded:

```bash
php -v    # PHP 8.5.x
php -m | grep -E 'pdo_mysql|intl|zip|bcmath|gd|exif|pcntl|OPcache'
```

**Node LTS** (22.18 or newer), needed for the frontend build:

```bash
curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && apt install -y nodejs
node -v   # v22.18+ or v24.11+; older versions print EBADENGINE warnings and may fail to build
```

**Composer:**

```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

**MySQL 8.0:**
- Ubuntu 24.04: `apt install -y mysql-server`.
- Debian: its default repositories ship MariaDB instead. Add MySQL's APT repository from https://dev.mysql.com/downloads/repo/apt/, or use a managed MySQL 8.0 service.

### 2. Create the app user and directory

```bash
adduser --disabled-password --gecos "" coffeeproject
mkdir -p /var/www/coffeeproject && chown coffeeproject:coffeeproject /var/www/coffeeproject
```

### 3. Give the server read access to the GitHub repo

The server needs its own key to **pull** code from GitHub. It's called `coffeeproject_github` (see [SSH keys at a glance](#ssh-keys-at-a-glance)).

Run these as the `coffeeproject` user: `sudo -u coffeeproject -i` from root. `whoami` should print `coffeeproject`.

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -N "" -C "coffeeproject-github-pull" -f ~/.ssh/coffeeproject_github
cat ~/.ssh/coffeeproject_github.pub
```

On GitHub, go to **repo → Settings → Deploy keys → Add deploy key**. Name it `coffeeproject_github (server pull)`, paste the public key, and leave **Allow write access unchecked**.

Tell SSH to use this key for GitHub, since it doesn't have a default file name:

```bash
cat >> ~/.ssh/config <<'CONF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/coffeeproject_github
    IdentitiesOnly yes
CONF
chmod 600 ~/.ssh/config
```

Test it, then clone:

```bash
ssh -T git@github.com     # type "yes" once → "Hi pavel-ufimtsev/coffeeproject! You've successfully authenticated..."
git clone git@github.com:pavel-ufimtsev/coffeeproject.git /var/www/coffeeproject
exit                      # back to root
```

"Permission denied (publickey)" means the key or config belongs to another user. Check `whoami`, and see [Troubleshooting](#troubleshooting).

### 4. Create the database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE coffeeproject CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'coffeeproject'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL PRIVILEGES ON coffeeproject.* TO 'coffeeproject'@'localhost';
FLUSH PRIVILEGES;
```

Generate the password with `openssl rand -base64 24`. Optionally copy `docker/mysql/my.cnf` into `/etc/mysql/conf.d/` so the server defaults to utf8mb4 and UTC, the same as locally.

### 5. Configure and install the app

```bash
sudo -u coffeeproject -i
cd /var/www/coffeeproject/src
cp .env.example .env
nano .env
```

Set these values in `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://coffeeproject.example.com

DB_HOST=127.0.0.1
DB_DATABASE=coffeeproject
DB_USERNAME=coffeeproject
DB_PASSWORD=a-long-random-password

SESSION_SECURE_COOKIE=true

MAIL_MAILER=resend
MAIL_FROM_ADDRESS="hello@your-domain.com"
RESEND_API_KEY=re_...            # Full access key
MAIL_GATEWAY=resend
RESEND_WEBHOOK_SECRET=           # filled in Part 3

ADMIN_NAME=Your Name
ADMIN_EMAIL=you@your-domain.com
ADMIN_PASSWORD=a-strong-password

RETAILCRM_URL=https://coffeeproject.retailcrm.ru
RETAILCRM_API_KEY=               # Settings → Integration → API access keys
RETAILCRM_SITE=                  # store code, see `php artisan retailcrm:check`

SHOP_CONTACT_EMAIL=coffeeproject.ekb@gmail.com   # contact, wholesale and review emails
YANDEX_METRIKA_ID=47060055       # the old site's counter; loads only after analytics consent
```

Then install and build:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan storage:link
php artisan catalog:import          # first deploy only: restores the catalog
php artisan retailcrm:sync-catalog  # current prices; the scheduler repeats it every 30 minutes
exit
```

After the admin account is created, delete the `ADMIN_PASSWORD` value from `.env`. Run `php artisan retailcrm:check` to confirm the retailCRM connection.

### 6. PHP-FPM

```bash
cd /var/www/coffeeproject
cp deploy/php-fpm-pool.conf /etc/php/8.5/fpm/pool.d/coffeeproject.conf
cp deploy/php.ini /etc/php/8.5/fpm/conf.d/99-coffeeproject.ini
cp deploy/php.ini /etc/php/8.5/cli/conf.d/99-coffeeproject.ini
systemctl restart php8.5-fpm
ls -l /run/php/coffeeproject.sock   # the socket should exist
```

### 7. nginx and HTTPS

First point your domain's **A record** at the server's IP. Then:

```bash
cp deploy/nginx.conf /etc/nginx/sites-available/coffeeproject
sed -i 's/coffeeproject.example.com/your.real.domain/' /etc/nginx/sites-available/coffeeproject
ln -s /etc/nginx/sites-available/coffeeproject /etc/nginx/sites-enabled/coffeeproject
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
certbot --nginx -d your.real.domain
```

### 8. Queue worker, scheduler and deploy permission

```bash
cp deploy/supervisor.conf /etc/supervisor/conf.d/coffeeproject.conf
supervisorctl reread && supervisorctl update
supervisorctl status coffeeproject-queue      # RUNNING

crontab -u coffeeproject deploy/crontab

visudo -cf deploy/sudoers && install -m 0440 deploy/sudoers /etc/sudoers.d/coffeeproject
```

The sudoers rule lets `deploy.sh` run `systemctl reload php8.5-fpm` and nothing else.

### 9. Try a deploy by hand

```bash
sudo -u coffeeproject -i
cd /var/www/coffeeproject && ./deploy.sh
```

It should end with `==> Deployed <commit>`. Open `https://your.real.domain` and log in with the admin account.

---

## Part 2: Resend in production

1. Add each sending domain in CoffeeProject (**Domains → Add domain**), add the DNS records it shows, and click **Verify DNS**. Receiving adds an MX record that sends **all** mail for that domain to Resend (see the README).
2. In Resend, go to **Webhooks → Add endpoint**, enter `https://your.real.domain/resend/webhook`, and select the `email.*` events.
3. Copy the webhook's signing secret into `RESEND_WEBHOOK_SECRET` in `/var/www/coffeeproject/src/.env`, then run `./deploy.sh` so the cached config picks it up.

---

## SSH keys at a glance

There is one key, belonging to the `coffeeproject` user on the server.

| Key | Direction | Private half | Public half |
| --- | --- | --- | --- |
| `coffeeproject_github` | server → GitHub, to **pull** code (`git fetch` in `deploy.sh`) | `/home/coffeeproject/.ssh/coffeeproject_github` | GitHub → repo → Settings → **Deploy keys** |

## Troubleshooting

- **`Permission denied (publickey)` from `git fetch` / `deploy.sh`:** the pull key isn't set up for the user running the command. Run `whoami` (it must be `coffeeproject`), `ssh -T git@github.com`, and check that `~/.ssh/config` points at `~/.ssh/coffeeproject_github`.
- **`sudo: user coffeeproject is not allowed to execute …`:** you're already `coffeeproject`. Drop the `sudo -u coffeeproject` part, or `exit` to root first.
- **`dubious ownership` or permission errors in git:** the repo was created by root. As root, run `chown -R coffeeproject:coffeeproject /var/www/coffeeproject`.
- **Frontend build fails with `Could not load …/resources/js/actions/…` or `…/resources/js/routes/…`:** Wayfinder generated the route helpers from a stale route cache. As `coffeeproject`, run `cd /var/www/coffeeproject/src && php artisan optimize:clear`, then `./deploy.sh main` from the repo root. `deploy.sh` clears the caches before building, so this only happens with a `deploy.sh` older than that change.

## Everyday use

- **Release:** push to `main`, then on the server run `sudo -u coffeeproject -i`, `cd /var/www/coffeeproject && ./deploy.sh`.
- **Change the production `.env`:** edit `/var/www/coffeeproject/src/.env` and run `./deploy.sh` as `coffeeproject`. The config is cached, so edits have no effect until the next deploy.
- **Logs:**
  - `src/storage/logs/laravel.log` for the app
  - `src/storage/logs/queue.log` for the worker
  - `/var/log/nginx/error.log` for nginx
- **A failed deploy** leaves the site running (`deploy.sh` always turns maintenance mode off again) and prints the error in your terminal. Fix the problem, push, and run `./deploy.sh` again.
- **Backups:** mailboxes, threads and received mail exist only in MySQL. Schedule a daily `mysqldump --single-transaction coffeeproject` and copy it off the server.
