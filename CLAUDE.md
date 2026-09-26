# NIS-RCIS — notes for Claude

Monorepo: `backend/` (Laravel 13 API + Passport OAuth2), `frontend/` (Next.js 16 BFF + UI), `deploy/` (native Nginx/systemd, **no Docker**), `legacy/` (old PHP, reference only — never edit or deploy).

## Rules
- Never commit personal data, uploads, database dumps or `.env` files.
- Every application status change goes through `App\Services\ApplicationWorkflow::transition()`; allowed transitions live in `App\Enums\ApplicationStatus`. Card changes go through `App\Services\CardIssuance`.
- Role checks mirror the legacy `requireRole()` calls (see README table). SuperAdmin passes every role check.
- OAuth2: staff and applicants are separate providers/guards (`api` / `applicant-api`). Keep the password and implicit grants disabled. Clients are scope-restricted via `oauth_clients.scopes`.
- Next.js 16: `middleware` is now `src/proxy.ts`; `cookies()`, `params` and `searchParams` are async. Read `frontend/node_modules/next/dist/docs/` before using unfamiliar APIs. OAuth tokens must stay server-side (BFF in `src/app/api/bff`).

## Checks
- Backend: `cd backend && vendor/bin/pint --test && vendor/bin/pest` (needs Postgres DB `nis_rcis_test`)
- Frontend: `cd frontend && npx next typegen && npx tsc --noEmit && npx eslint src && npm run build`
