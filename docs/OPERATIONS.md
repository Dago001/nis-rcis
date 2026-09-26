# Operations: monitoring, staging and testing

## 1. Monitoring and alerts

NIS-RCIS checks itself every 5 minutes (`php artisan nis:health-check`, run by
the scheduler) and e-mails the addresses in `ALERT_EMAILS` when a check starts
failing and again when it recovers. Checks: database, cache, document
storage, disk space, scheduler, backups (latest under 36 hours), OAuth2
signing keys and recent server errors. Super Administrators see the same
checks under **Staff console → System health**.

**Error tracking.** Every server error is recorded (grouped by where it
happened, with a count), and the first occurrence of each new error is
e-mailed to `ALERT_EMAILS` (at most hourly per error, and again if an error
marked *resolved* comes back). E-mail addresses and long numbers are removed
from the messages. Review and resolve them under **System health**.

**Uptime check (outside the server).** A monitor that lives elsewhere notices
when the whole server is down, which the server cannot report itself. Point
any uptime service (for example UptimeRobot, Better Stack, or a Cloudflare
Health Check) at both addresses, every 1–5 minutes, alerting on anything but
HTTP 200:

| URL | Meaning of 200 |
|---|---|
| `https://<portal>/api/health` | The website is up and can reach the API |
| `https://<api>/api/v1/health` | The API, database, cache and storage work |

Both return only `ok` / `warn` / `fail`, never details.

`backend/.env`:

```
ALERT_EMAILS=ict-ops@immigration.gov.ng,duty-officer@immigration.gov.ng
ALERT_BACKUP_MAX_AGE_HOURS=36
ALERT_DISK_MIN_FREE_PERCENT=10
ALERT_DISK_MIN_FREE_GB=20
```

## 2. Staging (a test copy, separate from live)

Keep a complete second installation for trying out updates before they reach
the live system. Use a **separate server (or VM)**, a separate database, a
separate domain and separate secrets; never copy the live `.env` files.

1. Build the staging server exactly like live: `deploy/install-ubuntu.sh`.
2. Use staging names, e.g. `staging-portal.immigration.gov.ng` and
   `staging-api.immigration.gov.ng`, ideally reachable only from the NIS
   network or VPN (Cloudflare Access or an Nginx `allow`/`deny` list).
3. In `/etc/nis-rcis/backend.env` on staging:
   ```
   APP_ENV=staging
   ENVIRONMENT_LABEL=STAGING
   PAYSTACK_SECRET_KEY=sk_test_...     # Paystack TEST keys only
   PAYSTACK_PUBLIC_KEY=pk_test_...
   MAIL_MAILER=log                     # or a test mailbox such as Mailpit: no real e-mails
   INTERPOL_SLTD_DRIVER=none           # never query the real systems from staging
   MOI_QUOTA_DRIVER=none
   ALERT_EMAILS=ict-dev@immigration.gov.ng
   ```
   and in `/etc/nis-rcis/frontend.env`: `ENVIRONMENT_LABEL=STAGING`.
   Every page then shows an orange **STAGING — test system** banner.
4. Load test data only: `php artisan nis:demo`. **Never copy live personal
   data to staging** (Nigeria Data Protection Act 2023). If realistic volumes
   are needed, generate them.
5. Deploy the version to be tested: `deploy/deploy.sh <branch-or-tag>`, run
   the checks in `TESTING.md`, and only then deploy the same tag to live.

## 3. Automated tests in the build pipeline

Every push runs `.github/workflows/ci.yml`:

| Job | What it checks |
|---|---|
| backend | Code style (Pint) and ~100 feature tests (Pest) on PostgreSQL |
| frontend | Type check, lint and production build |
| e2e | The real system in Chromium: public pages, French, card verification, health checks, installable app, applicant sign-up → sign-in → wizard, staff sign-in with the authenticator → approval queue (desktop and phone sizes) |

Run the end-to-end tests yourself against a running local copy:

```
cd frontend
npx playwright install chromium   # first time only
npx playwright test
```

`scripts/ci/e2e-setup.sh` prepares a throw-away copy exactly as CI does.
A failed CI run keeps the Playwright report and screenshots as a download.
