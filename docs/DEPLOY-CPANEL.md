# Test server on cPanel shared hosting

How to run a **test copy** of NIS-RCIS at **https://niscoreapps.com.ng/nis-rcis** while you keep developing on localhost.

| | Localhost (unchanged) | Test server |
|---|---|---|
| Website | http://localhost:3000 | https://niscoreapps.com.ng/nis-rcis |
| API | http://127.0.0.1:8000 | https://niscoreapps.com.ng/nis-rcis-api |
| Started by | `start.bat` | cPanel (Node.js app + Apache) |
| Settings | `backend\.env`, `frontend\.env.local` | `.env` on the server + Node.js app settings in cPanel |

The same code runs in both places. The only difference is that the test build is made for the `/nis-rcis` folder (`cpanel-package.bat` does this). Your localhost files are never changed.

> **Test data only.** The test server is on the public internet. Use demo data (`nis:demo`) and made-up applicants — never real applicants' personal data (Nigeria Data Protection Act 2023). The production system belongs on the Ubuntu setup in the README.

---

## Step 1 — Check the hosting can run it (5 minutes)

Log in to cPanel and confirm each item. If one is missing, ask the hosting company to enable it before going further.

| Needed | Where in cPanel | Requirement |
|---|---|---|
| Node.js apps | **Setup Node.js App** | Node.js **20.9 or newer** (22 preferred) |
| PostgreSQL | **PostgreSQL Databases** (and **phpPgAdmin**) | PostgreSQL **12 or newer** |
| PHP | **Select PHP Version** or **MultiPHP Manager** | PHP **8.3 or newer** for the domain, with extensions `pdo_pgsql`, `pgsql`, `gd`, `intl`, `bcmath`, `mbstring`, `zip`, `fileinfo`, `sodium` |
| Command line | **Terminal** (or SSH access) | To run the setup commands |
| Cron | **Cron Jobs** | One job every minute |
| HTTPS | **SSL/TLS Status** | Certificate for niscoreapps.com.ng (AutoSSL) |

**If there is no PostgreSQL:** the hosting cannot run this system (it uses PostgreSQL-only features). Ask the host to enable it, or use a small VPS with the Ubuntu setup instead.

To see the PostgreSQL version: open **phpPgAdmin**; the version is on its first page.

---

## Step 2 — Build the upload files on your PC

1. Commit your latest changes in Git (the API package contains **committed** code only; the script warns you if something is not committed).
2. Double-click **`cpanel-package.bat`** in the project folder.
3. After a few minutes you have two files in `dist\cpanel\`:
   - `nis-rcis-frontend.zip` — the website, built for `/nis-rcis`
   - `nis-rcis-backend.zip` — the API, with its PHP libraries

The zips never contain your `.env` files or passwords.

For other addresses: `cpanel-package.bat -SiteUrl https://example.com/folder -ApiUrl https://example.com/folder-api`

---

## Step 3 — Create the database

1. cPanel → **PostgreSQL Databases**.
2. **Create New Database**: `nisrcis`. cPanel adds your account name in front, e.g. `myacct_nisrcis`. Note the full name.
3. **Add New User**: `nisrcis`, with a strong password (use the generator). Note the full user name (e.g. `myacct_nisrcis`) and the password.
4. **Add User to Database**: choose the user and the database, then grant **ALL privileges**.

---

## Step 4 — Upload the API

1. cPanel → **File Manager** → your home folder (the one that contains `public_html`, **not** inside `public_html`).
2. Create the folders `nis-rcis` and inside it `backend`.
3. Open `nis-rcis/backend`, **Upload** `nis-rcis-backend.zip`, then right-click it → **Extract**. Delete the zip afterwards.
4. In the same folder, copy `.env.example` to `.env` (turn on **Settings → Show Hidden Files** to see it), then **Edit** `.env` and set:

```ini
APP_NAME="NIS-RCIS"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://niscoreapps.com.ng/nis-rcis-api
FRONTEND_URL=https://niscoreapps.com.ng/nis-rcis
ENVIRONMENT_LABEL="TEST SERVER"
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=myacct_nisrcis        # full name from step 3
DB_USERNAME=myacct_nisrcis        # full name from step 3
DB_PASSWORD=the-password-from-step-3

# Shared hosting has no Redis: keep sessions and cache in the database, send e-mails straight away.
SESSION_DRIVER=database
SESSION_PATH=/nis-rcis-api
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync

# An e-mail account created in cPanel → Email Accounts (e.g. no-reply@niscoreapps.com.ng)
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.niscoreapps.com.ng
MAIL_PORT=465
MAIL_USERNAME=no-reply@niscoreapps.com.ng
MAIL_PASSWORD=the-mailbox-password
MAIL_FROM_ADDRESS=no-reply@niscoreapps.com.ng

# Test payments (no money moves). For Paystack's test mode instead, put sk_test_/pk_test_ keys here.
PAYMENTS_FAKE=true
PAYSTACK_SECRET_KEY=
PAYSTACK_PUBLIC_KEY=

STAFF_TWO_FACTOR=true
```

Leave the other lines as they are.

---

## Step 5 — Set up the API (Terminal)

Open cPanel → **Terminal** and run these one at a time.

```bash
cd ~/nis-rcis/backend
php -v
```

`php -v` must show **8.3 or newer**. If it shows an older version, the Terminal's `php` differs from the website's. Use the full path instead of `php` in every command below (and in the cron job in step 8). It is usually one of these; `ls` shows which exists:

```bash
ls /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php
```

Then:

```bash
chmod -R 775 storage bootstrap/cache
php artisan key:generate --force
php artisan passport:keys
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Create the sign-in connections between the website and the API. **Copy the four values it prints** (two client IDs, two secrets); you need them in step 7:

```bash
php artisan nis:oauth-clients --frontend=https://niscoreapps.com.ng/nis-rcis
```

Create your Super Administrator account (use your real e-mail: the sign-in code is sent there):

```bash
php artisan nis:create-staff --role=SuperAdmin --username=admin --fullname="Your Name" --service-number=NIS0001 --email=you@example.com
```

It prints a **temporary password once**. Note it; you choose your own password at the first sign-in.

Optional: load demo officers, applicants and applications to test with.

```bash
php artisan nis:demo
```

---

## Step 6 — Put the API online

In the Terminal:

```bash
ln -s ~/nis-rcis/backend/public ~/public_html/nis-rcis-api
```

Open **https://niscoreapps.com.ng/nis-rcis-api/api/v1/health** in your browser. You should see `{"status":"ok",…}` or `"warn"`.

- `"fail"` is fine at this point. It usually means only that there is no backup yet (the backup check).
- **403 Forbidden:** the host does not follow links. Ask them to allow `FollowSymLinks` for `public_html`, or to point `nis-rcis-api` at `~/nis-rcis/backend/public`.
- **500 error:** read `~/nis-rcis/backend/storage/logs/` (newest file), usually a wrong database value in `.env`. After fixing `.env`, run `php artisan optimize` again.

---

## Step 7 — Put the website online

1. **File Manager** → `nis-rcis` → create the folder `frontend`. Upload `nis-rcis-frontend.zip` into it and **Extract**. Delete the zip afterwards.
2. cPanel → **Setup Node.js App** → **Create Application**:
   - **Node.js version:** 22 (or the newest 20.9+)
   - **Application mode:** Production
   - **Application root:** `nis-rcis/frontend`
   - **Application URL:** `niscoreapps.com.ng` / `nis-rcis`
   - **Application startup file:** `server.js`
3. Under **Environment variables**, add:

| Name | Value |
|---|---|
| `NODE_ENV` | `production` |
| `APP_URL` | `https://niscoreapps.com.ng/nis-rcis` |
| `API_URL` | `https://niscoreapps.com.ng/nis-rcis-api` |
| `API_PUBLIC_URL` | `https://niscoreapps.com.ng/nis-rcis-api` |
| `SESSION_SECRET` | a random text of 48+ characters (see below) |
| `OAUTH_STAFF_CLIENT_ID` | from step 5 |
| `OAUTH_STAFF_CLIENT_SECRET` | from step 5 |
| `OAUTH_APPLICANT_CLIENT_ID` | from step 5 |
| `OAUTH_APPLICANT_CLIENT_SECRET` | from step 5 |

   To make `SESSION_SECRET`, run this in PowerShell on your PC and paste the result:
   `[Convert]::ToBase64String((1..48 | ForEach-Object { Get-Random -Maximum 256 }))`

4. Click **Save**, then **Restart**. **Do not click "Run NPM Install"**: the zip already contains everything.
5. Open **https://niscoreapps.com.ng/nis-rcis**. You should see the home page.

---

## Step 8 — Scheduled jobs

cPanel → **Cron Jobs** → add a job with **Common Settings: Once Per Minute**. Command (with the full PHP path from step 5 if you needed one):

```bash
cd ~/nis-rcis/backend && php artisan schedule:run >> /dev/null 2>&1
```

It runs the reminders, clean-ups and health checks at their set times.

---

## Step 9 — Test it

1. https://niscoreapps.com.ng/nis-rcis/login → **Staff** → sign in as the admin from step 5 (code sent to your e-mail).
2. Register as an applicant with a second e-mail address, confirm the e-mail, pay (test mode) and submit an application.
3. Walk the application through approval, biometrics and card printing.

---

## Updating the test server after changes on localhost

1. Commit your changes.
2. Run `cpanel-package.bat`.
3. **Website:** File Manager → `nis-rcis/frontend` → delete everything in it → upload and extract the new `nis-rcis-frontend.zip` → **Setup Node.js App** → **Restart**. (The settings from step 7 are kept by cPanel.)
4. **API:** File Manager → `nis-rcis/backend` → upload the new `nis-rcis-backend.zip` → **Extract** and allow it to overwrite. Your `.env`, uploads and keys are not in the zip, so they are kept. Then in Terminal:

```bash
cd ~/nis-rcis/backend && php artisan migrate --force && php artisan optimize
```

Localhost keeps working as before throughout: keep using `start.bat`. Never copy the test server's `.env` values to your PC or the other way round.

---

## When something is wrong

| What you see | Cause and fix |
|---|---|
| Website shows **"Incomplete response"**, **503** or cPanel's error page | The Node.js app did not start. Open `nis-rcis/frontend/stderr.log` in File Manager: a missing environment variable is named there. Add it in **Setup Node.js App**, **Restart**. |
| **https://niscoreapps.com.ng/nis-rcis** shows a "404 — page not found" in the app's own design | The host removes `/nis-rcis` before passing requests to the app. Tell the developer: the build needs a small change for this host. |
| Page loads without styles or pictures | The zip was built for a different address. Re-run `cpanel-package.bat` with the right `-SiteUrl`. |
| Sign-in shows **"invalid redirect"** or **invalid_scope** | The sign-in connections were made for another address. Run step 5's `nis:oauth-clients` command again with the exact website address and update the four values in step 7. |
| **"Your sign-in session expired"** straight after signing in | `APP_URL` in the Node.js app settings does not match the address in the browser (check `https://` and `/nis-rcis`). |
| API returns **500** | Newest file in `~/nis-rcis/backend/storage/logs/`. After changing `.env`, always run `php artisan optimize`. |
| No e-mails arrive | Check the `MAIL_*` values; test with the mailbox's webmail. Look for "mail" in the API log. |
