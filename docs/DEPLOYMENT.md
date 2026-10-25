# Deploying CoffeeProject

Production runs without Docker on a Debian or Ubuntu server: nginx, PHP 8.5-FPM, MySQL 8.0, Supervisor (queue worker) and cron (scheduler).

Every push to `main` runs the checks in GitHub Actions. When they pass, Actions connects to the server over SSH and runs [`deploy.sh`](../deploy.sh), which does the following:

1. Pulls the new commit.
2. Installs dependencies and builds the frontend.
3. Runs migrations and caches the config.
4. Restarts the queue worker and reloads PHP-FPM.

The site shows a maintenance page for the minute or so this takes. Resend retries webhooks that arrive during that time, and `mail:sync` catches anything missed.

```
push to main ─▶ GitHub Actions: tests & checks ─▶ ssh coffeeproject@server ./deploy.sh
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

## Part 2: Let GitHub Actions deploy (once)

GitHub Actions needs its own key to **log into the server** as `coffeeproject` and run `deploy.sh`. It's called `coffeeproject_actions`. Everything below runs **on the server as `coffeeproject`** (`sudo -u coffeeproject -i`). You only copy values into GitHub.

### 1. Create the Actions key on the server

```bash
ssh-keygen -t ed25519 -N "" -C "coffeeproject-actions-deploy" -f ~/.ssh/coffeeproject_actions

# allow this key to log in as coffeeproject
cat ~/.ssh/coffeeproject_actions.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Test the login the way GitHub will use it:

```bash
ssh -i ~/.ssh/coffeeproject_actions -o IdentitiesOnly=yes coffeeproject@localhost 'cd /var/www/coffeeproject && git log -1 --oneline'
# type "yes" once; it should print the latest commit
```

Now collect the values for GitHub. The private key goes into a GitHub secret. The server's host key lets Actions check that it's talking to your server. Use the server's public IP or hostname here, the same value you'll put in `SSH_HOST`:

```bash
cat ~/.ssh/coffeeproject_actions          # → SSH_PRIVATE_KEY (copy everything, BEGIN…END lines included)
ssh-keyscan -p 22 your.server.ip      # → SSH_KNOWN_HOSTS (copy all lines)
```

When the secrets are saved on GitHub (next step), delete the private key from the server. Only GitHub needs it, and the public key in `authorized_keys` stays:

```bash
rm ~/.ssh/coffeeproject_actions
```

### 2. Add the secrets on GitHub

Go to **repo → Settings → Environments → New environment** and name it `production`. Optionally, under "Deployment branches", allow only `main`, and add yourself as a required reviewer if you want to approve each deploy.

In the `production` environment, add these **environment secrets**:

| Secret | Value |
| --- | --- |
| `SSH_HOST` | server IP or hostname |
| `SSH_USER` | `coffeeproject` |
| `SSH_PRIVATE_KEY` | the output of `cat ~/.ssh/coffeeproject_actions` (the private key, `BEGIN`/`END` lines included) |
| `SSH_KNOWN_HOSTS` | the `ssh-keyscan` output |
| `SSH_PORT` | only if SSH isn't on port 22 |

### 3. Turn deploys on

Go to **repo → Settings → Secrets and variables → Actions → Variables tab → New repository variable** and add:

| Variable | Value |
| --- | --- |
| `DEPLOY_ENABLED` | `true` |
| `DEPLOY_PATH` | only if the app isn't in `/var/www/coffeeproject` |

`DEPLOY_ENABLED` has to be a **repository** variable, not an environment variable, because it decides whether the deploy job starts at all.

### 4. Deploy

Push to `main`, or open **Actions → CI & Deploy → Run workflow**. The run shows two jobs, **Tests & checks** and **Deploy to production**. Pull requests only run the checks.

---

## Part 3: Resend in production

1. Add each sending domain in CoffeeProject (**Domains → Add domain**), add the DNS records it shows, and click **Verify DNS**. Receiving adds an MX record that sends **all** mail for that domain to Resend (see the README).
2. In Resend, go to **Webhooks → Add endpoint**, enter `https://your.real.domain/resend/webhook`, and select the `email.*` events.
3. Copy the webhook's signing secret into `RESEND_WEBHOOK_SECRET` in `/var/www/coffeeproject/src/.env`, then run `./deploy.sh` (or push anything) so the cached config picks it up.

---

## SSH keys at a glance

There are two keys, both belonging to the `coffeeproject` user on the server. They must be different keys.

| Key | Direction | Private half | Public half |
| --- | --- | --- | --- |
| `coffeeproject_github` | server → GitHub, to **pull** code (`git fetch` in `deploy.sh`) | `/home/coffeeproject/.ssh/coffeeproject_github` | GitHub → repo → Settings → **Deploy keys** |
| `coffeeproject_actions` | GitHub Actions → server, to **run** `deploy.sh` | GitHub → Environment `production` → secret **`SSH_PRIVATE_KEY`** (deleted from the server) | `/home/coffeeproject/.ssh/authorized_keys` |

## Troubleshooting

- **`Permission denied (publickey)` from `git fetch` / `deploy.sh`:** the pull key isn't set up for the user running the command. Run `whoami` (it must be `coffeeproject`), `ssh -T git@github.com`, and check that `~/.ssh/config` points at `~/.ssh/coffeeproject_github`.
- **`sudo: user coffeeproject is not allowed to execute …`:** you're already `coffeeproject`. Drop the `sudo -u coffeeproject` part, or `exit` to root first.
- **`dubious ownership` or permission errors in git:** the repo was created by root. As root, run `chown -R coffeeproject:coffeeproject /var/www/coffeeproject`.
- **`Permission denied (publickey)` in the Actions deploy log, but `./deploy.sh main` works when you run it as `coffeeproject`:** Actions logs in as a different user. The `SSH_USER` secret in the `production` environment must be exactly `coffeeproject`.
- **Frontend build fails with `Could not load …/resources/js/actions/…` or `…/resources/js/routes/…`:** Wayfinder generated the route helpers from a stale route cache. As `coffeeproject`, run `cd /var/www/coffeeproject/src && php artisan optimize:clear`, then `./deploy.sh main` from the repo root. `deploy.sh` clears the caches before building, so this only happens with a `deploy.sh` older than that change.
- **The Actions deploy job fails at SSH:** check the `production` secrets. `SSH_HOST` must be the address `ssh-keyscan` was run against, and `SSH_PRIVATE_KEY` must be the whole private key.

## Everyday use

- **Release:** merge or push to `main`. That's it.
- **Change the production `.env`:** edit `/var/www/coffeeproject/src/.env` and run `./deploy.sh` as `coffeeproject`. The config is cached, so edits have no effect until the next deploy.
- **Logs:**
  - `src/storage/logs/laravel.log` for the app
  - `src/storage/logs/queue.log` for the worker
  - `/var/log/nginx/error.log` for nginx
- **A failed deploy** leaves the site running (`deploy.sh` always turns maintenance mode off again) and shows the error in the Actions log. Fix the problem and push again.
- **Backups:** mailboxes, threads and received mail exist only in MySQL. Schedule a daily `mysqldump --single-transaction coffeeproject` and copy it off the server.
