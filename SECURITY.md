# Security policy — NIS Residence Card Issuance System

## Reporting a vulnerability

Report suspected vulnerabilities privately to **security@immigration.gov.ng**
(see also `/.well-known/security.txt` on the portal). Please include the
steps to reproduce, the affected address, and what an attacker could achieve.
Do not access, change or keep other people's personal data, and do not run
denial-of-service or social-engineering tests. We acknowledge reports within
**3 working days** and keep reporters informed until the issue is fixed.

## Independent penetration test (yearly)

An independent, accredited firm tests the system **at least once a year**,
and additionally before go-live and after major changes (new integrations,
sign-in changes, infrastructure moves).

**Scope**
- Applicant portal and public pages (`portal`), the staff console, and the
  BFF routes under `/api/bff`
- Laravel API and OAuth2 authorization server (`api`), including the Blade
  sign-in pages, two-factor sign-in, token handling and scopes
- Partner verification API (machine-to-machine client credentials)
- File uploads, document links (signed URLs), PDF generation
- Paystack payment flow and webhook, refunds
- Server hardening: Nginx/TLS, PHP-FPM, PostgreSQL, Redis, backups
- Physical biometrics desk setup (fingerprint service on localhost)

**Rules of engagement**
- Test on **staging** (see `docs/OPERATIONS.md`) with demo data. Tests on the
  live system only by written agreement, in an agreed window, without real
  applicants' data.
- Test accounts for every staff role are issued for the test and disabled
  afterwards.
- Findings are reported with CVSS scores and reproduction steps; the report
  is RESTRICTED.

**Fix times (from the report date)**

| Severity | Fix and verify within |
|---|---|
| Critical | 7 days (or take the affected function offline) |
| High | 30 days |
| Medium | 90 days |
| Low | next planned release |

After the fixes, the firm re-tests and confirms. Keep each year's report,
the fix record and the re-test letter with the NDPA compliance records.

**Checklist to prepare**
- [ ] Staging updated to the release being tested, with `ENVIRONMENT_LABEL=STAGING`
- [ ] Test accounts created (one per role) and the authenticator set up
- [ ] Monitoring alerts routed to the test coordinator during the window
- [ ] Previous year's findings listed for re-testing

## Security measures in the system (summary)

- OAuth2 (authorization code + PKCE) with separate staff and applicant
  providers; tokens held server-side only; password and implicit grants off
- Staff two-factor sign-in (authenticator app); Super Administrators can reset
  a lost authenticator, reset passwords, change roles and sign users out
- Two-person rule for revoking, reinstating and correcting cards
- Role checks on every staff action; append-only audit trail
- Fraud and duplicate checks; Interpol SLTD and quota checks when connected
- Encrypted fingerprint templates, signed short-lived document links,
  malware scanning of uploads (ClamAV when configured)
- Security headers and Content-Security-Policy; rate limits on sign-in,
  registration, payments and the help assistant
- Nigeria Data Protection Act 2023: consent, data download, retention rules,
  breach register with the 72-hour NDPC deadline
- Encrypted nightly backups with restore verification; monitoring and alerts
