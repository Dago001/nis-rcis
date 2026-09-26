# NIS-RCIS — Residence Card Issuance System

Nigeria Immigration Service, Directorate of Visa and Residency.
Online application, approval, biometrics capture, issuance, renewal and verification of residence cards for foreign nationals.

| Layer | Technology |
|---|---|
| Frontend | Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS 4 |
| Backend | Laravel 13, PHP 8.5, REST API (`/api/v1`) |
| Auth | **OAuth2** — Laravel Passport (authorization code + PKCE, refresh tokens, client credentials) |
| Data | PostgreSQL 16, Redis (sessions, cache, queues, rate limits), S3-compatible private storage |
| Infra | Nginx, PHP-FPM, systemd, Cloudflare — **no Docker** |

The previous plain-PHP application is kept, read-only, in [`legacy/`](legacy) for reference. Personal data that had been committed (uploads, SQLite database, SQL dumps) has been removed from the working tree — see [Security notes](#security-notes).

```
backend/    Laravel API + OAuth2 authorization server
frontend/   Next.js applicant portal (/portal) and staff console (/staff)
deploy/     Nginx, PHP-FPM, systemd and provisioning scripts (Ubuntu 24.04)
legacy/     Old PHP code (reference only, do not deploy)
```

---

## Architecture

```
 Browser ──HTTPS──▶ Cloudflare ──▶ Nginx ──▶ Next.js (BFF)  ──bearer token──▶ Nginx :8080 ──▶ PHP-FPM / Laravel
    │                                           │  encrypted httpOnly                         │
    │                                           │  session cookie                             ├─ PostgreSQL
    └──── /oauth/authorize, /login/* ───────────┼──────────────────────────────▶ Laravel      ├─ Redis
                                                                                               └─ S3 (private)
 Partner agency system ── client_credentials ──▶ /oauth/token, /api/v1/partner/cards/verify
```

* The **browser never sees an OAuth token.** Next.js acts as a backend-for-frontend: it performs the code exchange server-side, stores tokens in an AES-256-GCM encrypted, `httpOnly`, `SameSite=Lax` cookie, refreshes them silently, and proxies API calls (`/api/bff/{staff|applicant|public}/…`).
* **Laravel is the only authority.** Every API route checks the token, its scope and (for staff) the officer's role. The frontend only hides buttons.

## OAuth2 design

| Client | Grant | User provider | Allowed scopes | Consent |
|---|---|---|---|---|
| NIS-RCIS Staff Console | authorization_code + PKCE, refresh_token | `users` (officers) | `staff` | skipped (first-party) |
| NIS-RCIS Applicant Portal | authorization_code + PKCE, refresh_token | `applicants` | `applicant` | skipped (first-party) |
| Partner agency (per agency) | client_credentials | — | `cards:verify` | n/a |

* Two separate identities with separate guards: a staff token can never authenticate as an applicant or vice versa (Passport's provider check on `oauth_clients.provider`).
* The authorize endpoint picks the **staff** or **applicant** login page from the client's provider.
* Each client has a scope allow-list (`oauth_clients.scopes`). Requests for any other scope are **rejected** with `invalid_scope` (`App\OAuth\StrictScopeRepository`), not silently dropped.
* Password and implicit grants are disabled. Access tokens last 15 minutes, refresh tokens 12 hours, partner tokens 30 minutes. `passport:purge` runs daily.
* Staff must replace their one-time temporary password before any code is issued. Deactivated accounts lose their tokens immediately.
* Sign-out revokes the access and refresh tokens and ends the authorization-server session.

## Workflow (preserved from the legacy system)

```
Applicant                    Approving Officer           Issuing Officer             Approving Officer   Issuing Officer
─────────                    ─────────────────           ───────────────             ─────────────────   ───────────────
register → verify e-mail
7-step wizard (save & exit)
pay (Paystack, verified)
book biometrics slot
submit ─▶ PENDING_APPROVAL ─▶ APPROVE ─▶ APPROVED_FOR_BIOMETRICS ─▶ capture photo + signature
          ▲        │                                               ─▶ BIOMETRICS_CAPTURED
          │      QUERY ─▶ QUERIED                                     card created (APPROVED) ─▶ approve card ─▶ ISSUED
 re-upload + respond ─┘      │                                                                    (or QUERY → correct)
                           REJECT ─▶ REJECTED                                                     mark ready ─▶ READY_FOR_COLLECTION
                                                                                                  record collection ─▶ ISSUED
Card lifecycle: ISSUED ─renew─▶ RENEWED · revoke ─▶ REVOKED ─reinstate (SuperAdmin)─▶ ISSUED · watchlist on/off
```

* Every transition goes through `App\Services\ApplicationWorkflow`: validated against `App\Enums\ApplicationStatus`, row-locked, recorded in `application_status_histories` and the audit log, and **the applicant is e-mailed and notified in the portal** at each step.
* Staff-assisted walk-in applications (legacy `new-card.php`) enter the same queue.

### Roles (same permissions as the legacy `requireRole()` calls)

| Action | SuperAdmin | Approving | Issuing | Inspector | Auditor |
|---|:-:|:-:|:-:|:-:|:-:|
| View queue, applications, cards, reports | ✓ | ✓ | ✓ | ✓ | ✓ |
| Approve / query / reject applications | ✓ | ✓ | | | |
| Biometrics capture, mark ready, record collection, renew, edit card | ✓ | ✓ | ✓ | | |
| Approve / query card, revoke, watchlist | ✓ | ✓ | | | |
| Reinstate revoked card, assisted applications, CSV export, staff accounts | ✓ | | | | |
| Audit trail | ✓ | | | | ✓ |

The Auditor role now has read access to the audit trail. In the legacy system only SuperAdmin did.

---

## Local development (no Docker)

**On Windows, use the one-click scripts:** run `setup.bat` once, then `start.bat`. [TESTING.md](TESTING.md) has the step-by-step guide, the demo accounts and a full test plan.

Manual setup (any operating system):

Requirements: PHP 8.4+ (8.5 in production) with `pgsql`, `redis`, `gd`, `intl`; Composer; PostgreSQL 16; Redis; Node.js 22.

```bash
# 1. Database
sudo -u postgres psql -c "CREATE USER nis_rcis WITH PASSWORD 'secret' CREATEDB;" \
                      -c "CREATE DATABASE nis_rcis OWNER nis_rcis;" \
                      -c "CREATE DATABASE nis_rcis_test OWNER nis_rcis;"

# 2. Backend
cd backend
composer install
cp .env.example .env            # set DB_PASSWORD=secret; QUEUE_CONNECTION=sync is simplest locally
php artisan key:generate
php artisan passport:keys
php artisan migrate --seed      # schema + enrollment centers
php artisan nis:demo           # optional: demo officers/applicants (password NisDemo-2026!)
php artisan nis:create-staff --role=SuperAdmin   # prints a one-time temporary password
php artisan nis:oauth-clients                    # prints client IDs/secrets for the frontend
php artisan serve               # http://localhost:8000

# 3. Frontend
cd ../frontend
npm install
cp .env.example .env.local      # paste the OAuth client IDs/secrets; set SESSION_SECRET
npm run dev                     # http://localhost:3000
```

* E-mails go to `backend/storage/logs` (`MAIL_MAILER=log`), including the applicant verification link.
* `PAYMENTS_FAKE=true` simulates Paystack locally. It is ignored when `APP_ENV=production`.
* Documents are stored on the private local disk (`DOCUMENTS_DISK=local`) in development and in S3 in production.

### Tests

```bash
cd backend && vendor/bin/pest      # uses the nis_rcis_test Postgres database
cd frontend && npx tsc --noEmit && npx eslint src && npm run build
```

The backend suite drives the real OAuth2 authorization-code + PKCE flow for both portals, refresh and revocation, client credentials, scope and identity isolation, and the full application workflow (including queries, invalid transitions, forged uploads, payment reuse, renewal ownership and the append-only audit log). CI runs both on every push (`.github/workflows/ci.yml`).

---

## Production deployment (Ubuntu 24.04, no Docker)

1. **Provision** (once): `sudo DB_PASSWORD=$(openssl rand -base64 32) deploy/install-ubuntu.sh`
   This installs Nginx, PHP 8.5-FPM (dedicated pool), PostgreSQL 16, Redis and Node.js 22, creates the `nis-rcis` service user, and installs the Nginx sites, systemd units and Cloudflare real-IP updater.
2. **TLS / Cloudflare**: install a Cloudflare Origin CA certificate at `/etc/ssl/nis-rcis/origin.{pem,key}`. Set SSL mode to **Full (strict)** and set the `server_name` values in `/etc/nginx/sites-available/nis-rcis-*.conf`. Optionally enable Authenticated Origin Pulls.
3. **Configuration**
   * `/etc/nis-rcis/backend.env`: based on `backend/.env.example`, with `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api…`, `FRONTEND_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `DOCUMENTS_DISK=s3` plus `AWS_*`, `PAYSTACK_*`, `MAIL_*`, `PAYMENTS_FAKE=false`
   * `/etc/nis-rcis/frontend.env`: based on `frontend/.env.example`, with `API_URL=http://127.0.0.1:8080` (internal Nginx listener) and `API_PUBLIC_URL=https://api…`
   * Both files: `chmod 640`, `root:nis-rcis`
4. **Deploy**: `sudo deploy/deploy.sh main`. This clones a release, installs dependencies, generates Passport keys on first run, runs migrations, builds Next.js in standalone mode, switches the `current` symlink and restarts services.
5. **First run**: `sudo -u nis-rcis php /var/www/nis-rcis/current/backend/artisan nis:create-staff --role=SuperAdmin` and `… nis:oauth-clients --frontend=https://…`. Put the printed client credentials into `frontend.env` and restart `nis-rcis-web`.
6. **Partner agencies**: `php artisan nis:partner-client "Agency name"` issues a `client_credentials` client limited to `cards:verify`.

Services: `php8.5-fpm`, `nis-rcis-web` (Next.js), `nis-rcis-queue` (e-mails/notifications), `nis-rcis-scheduler.timer` (token purge).

**Data residency:** applicant data is personal data under the Nigeria Data Protection Act 2023. Host PostgreSQL, Redis and the S3-compatible storage in Nigeria. With Cloudflare proxying, TLS is terminated at Cloudflare's edge, so confirm this is acceptable under NIS policy.

---

## Security notes

Legacy findings and how the new stack resolves them:

| Legacy issue | Now |
|---|---|
| Uploads, SQLite DB and SQL dumps committed to git | Removed from the tree; `.gitignore` blocks them. **They are still in git history** (see below). |
| Default `password123` staff accounts; DB as `root` with no password | No default users; one-time temporary passwords with forced change; dedicated DB role |
| Print slips / tracking readable by sequential ID without login | Slips require the owning applicant's token; public tracking needs application number **and** passport number, is rate-limited and masks names |
| E-mail "verification" link shown on screen; registration attached other people's applications by e-mail | Signed link sent only by e-mail; no automatic linking by e-mail address |
| Renewal pre-fill leaked any card holder's details | Renewal only for the applicant's own card (or by proving card number + passport) |
| Payment status taken from the browser | Paystack verify API / signed webhook; amount and currency checked; one payment per application |
| Uploads checked by file extension only; public `uploads/` folder | Content sniffed with `finfo`, random names, private S3, 10-minute signed URLs |
| Any decision allowed at any status; `last + 1` card numbers; "ready" marked every application with the same passport | Enforced state machine with row locks; PostgreSQL sequences; ready/collection tied to the card's own application |
| No rate limiting | Login, registration, token, tracking and verification endpoints are rate-limited |
| Audit log editable | PostgreSQL trigger makes `audit_logs` append-only |
| Watchlist reason shown publicly | Public verification shows only "refer to NIS"; details go to authorised staff and partner agencies |
| SQL built by string concatenation | Eloquent / bound parameters only; search wildcards escaped (`App\Support\Like`); SQL-injection tests on every public lookup and staff search |
| PHP errors (with SQL) shown to users | Database errors are never rendered to API clients, even with `APP_DEBUG=true`; the BFF replaces every 5xx body with a generic message |
| No malware checks on uploads | `MalwareScanner`: PDFs with JavaScript/launch actions/attachments refused (including `#xx`-obfuscated names), images with embedded code refused and all images re-encoded (strips hidden payloads and GPS metadata), EICAR refused, optional ClamAV (`CLAMAV_SOCKET`, fails closed) |
| No browser security headers | CSP, `X-Frame-Options: DENY`, `nosniff`, COOP, Permissions-Policy and HSTS on both the website and the API/sign-in pages; sign-in pages run no JavaScript at all |

### Help assistant (chatbot)

A chat button at the bottom-right of the public site and the applicant portal (it replaces the Next.js development "N" button).

* **General questions** are answered from a curated knowledge base (`backend/app/Assistant/KnowledgeBase.php`). With `ANTHROPIC_API_KEY` set in `backend/.env`, Claude (`ASSISTANT_MODEL`, default `claude-opus-5`) phrases the answers from that knowledge base only; without a key the assistant still works, using keyword matching.
* **"My application" questions** (status, appointment, query, collection, card) are answered only for the signed-in applicant, from their **own** latest application, with fixed templates. Personal data is never sent to the language model. Guests are pointed to sign in or the Track page.
* **No database access for the model**: it receives only the public knowledge base and the visitor's question; the conversation history is kept server-side, bound to the visitor.
* **Guards**: SQL-injection, script, path-traversal and prompt-injection messages and requests for secrets or other people's records are refused before any processing, and recorded in the audit trail as `SECURITY_ASSISTANT_BLOCKED`. Model replies are scrubbed of HTML, external links, e-mail addresses, long numbers, passport-like codes, SQL and server paths. Replies render as plain text; only internal links are clickable. Rate-limited to 12 messages a minute.

**Action required:** the removed personal data (photos, passports, visas, database dumps) is still in this repository's git history. If the repository was ever shared, treat that data as exposed. Purge it with `git filter-repo`, force-push, and ask GitHub support to clear cached views. This is not done automatically because it rewrites history for everyone.

## Connections to other government systems

Interpol SLTD passport checks, the Ministry of Interior expatriate quota
register and fingerprint scanners are built as adapters that are switched on
once the agreements are signed. See [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md).

## Not yet implemented

* Importing records from the legacy MySQL/SQLite database (write a one-off `artisan` command that maps the old `residence_cards` and `card_renewals` tables)
* Notifications are sent by e-mail (and in the portal) only; there is deliberately no SMS channel
* Server-generated PDFs for booklets and certificates (the card and slips use print CSS; receipts and management reports are PDFs)
