# Grantly API

The Laravel 13 REST API that powers [Grantly](../), a Community Grant Application Portal for Australian organisations. It handles authentication, grant round management, application submissions, document uploads, status workflows, and the grounded AI chatbot.

The API is versioned at `/api/v1` and consumed by the Next.js frontend in [`../frontend`](../frontend).

## Tech Stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.3+ |
| Framework | Laravel 13 |
| Database | Supabase Postgres (via Eloquent) |
| Auth | Supabase Auth (JWT verified with `firebase/php-jwt`) |
| Storage | Supabase Storage (S3-compatible via `league/flysystem-aws-s3-v3`) |
| Email | Resend (`resend/resend-laravel`) |
| AI | OpenRouter (streamed completions) |
| Tests | PHPUnit 12 |
| Tooling | Laravel Pint, Laravel Pail |

## Prerequisites

- PHP 8.3 or higher with the usual Laravel extensions (`mbstring`, `pdo_pgsql`, `openssl`, `bcmath`, `intl`)
- Composer 2.x
- Node.js 20+ and npm (for asset builds)
- A Supabase project (URL, anon key, JWT signing keys via JWKS)
- An OpenRouter API key (optional, only needed for the `/ai/chat` endpoint)

## Quickstart

```bash
git clone <repo-url>
cd grantly-code/backend

composer install
cp .env.example .env
php artisan key:generate

# Fill in Supabase, S3, and OpenRouter credentials in .env (see below)

php artisan migrate --seed
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`.

To run the full dev stack (HTTP server, queue worker, log tail, Vite) in parallel:

```bash
composer dev
```

## Environment Variables

Copy `.env.example` to `.env` and fill in the project-specific values. The variables that need real credentials are:

| Variable | Purpose |
|---|---|
| `APP_KEY` | Run `php artisan key:generate` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Postgres connection to your Supabase database |
| `SUPABASE_URL` | Your Supabase project URL (e.g. `https://xxx.supabase.co`) |
| `SUPABASE_ANON_KEY` | Public anon key from Supabase Dashboard → API |
| `SUPABASE_JWT_SECRET` | JWT secret from Supabase Dashboard → Settings → API |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT` | Supabase Storage credentials (S3-compatible) |
| `RESEND_API_KEY` | Resend API key for transactional email |
| `OPENROUTER_API_KEY` | OpenRouter API key for the chatbot |
| `OPENROUTER_MODEL` | Defaults to `openai/gpt-oss-120b:free` |

## Project Structure

```
app/
  Http/
    Controllers/Api/V1/   REST controllers grouped by resource
    Middleware/           Supabase JWT verification (required + optional variants)
    Requests/             Form-request validators (extend ApiFormRequest for consistent error shape)
    Resources/            JSON resource transformers
  Models/                 Eloquent models (User, GrantRound, Application, etc.)
config/                   Laravel config (cors.php and services.php are project-specific)
database/
  migrations/             Schema definitions
  seeders/                GrantRoundSeeder ships 10 realistic AU funding rounds
  factories/              Model factories
routes/
  api.php                 All /api/v1 routes
tests/                    PHPUnit feature + unit tests
```

## Running the App

| Command | What it does |
|---|---|
| `php artisan serve` | Starts the HTTP server on `http://localhost:8000` |
| `php artisan migrate` | Runs database migrations |
| `php artisan migrate:fresh --seed` | Drops everything, re-migrates, and seeds 10 grant rounds |
| `php artisan db:seed --class=GrantRoundSeeder` | Seeds grant rounds only |
| `php artisan queue:listen` | Processes queued jobs (transactional email, etc.) |
| `php artisan pail` | Tails application logs in the terminal |
| `composer dev` | Runs server, queue, logs, and Vite concurrently |

## Testing

```bash
php artisan test                          # Run the full suite
php artisan test --filter TestClassName   # Run a single test class
composer test                             # Clears config cache, then runs the suite
```

Focus areas for coverage: Supabase JWT middleware, application submission flow, status transition logic, and file validation.

## API Overview

The full request/response shapes for every endpoint live in the root [`CLAUDE.md`](../CLAUDE.md) under the **API Reference** section. The short version:

| Resource | Auth | Notes |
|---|---|---|
| `POST /auth/register`, `POST /auth/login` | Public | Proxies Supabase Auth |
| `GET /grant-rounds`, `GET /grant-rounds/{id}` | Public (optional token) | Admins see all; everyone else sees published + open |
| `POST/PATCH/DELETE /grant-rounds/*` | Admin | Round lifecycle: draft, open, closed, completed |
| `GET/POST/PATCH/DELETE /applications/*` | Authenticated | Applicants manage their own; admins read all |
| `POST /applications/{id}/submit` | Applicant | One-way draft to submitted transition |
| `PATCH /applications/{id}/status` | Admin | Free-form status change, logged to audit trail |
| `GET /applications/{id}/status-history` | Authenticated | Append-only audit log |
| `* /applications/{id}/review-notes` | Admin | Internal review notes (never visible to applicants) |
| `GET/PATCH /profile` | Authenticated | The caller's own profile |
| `POST /ai/chat` | Authenticated | Grounded SSE chatbot via OpenRouter |

All responses are JSON. Errors follow the shape `{ error: { code, message, details? } }`.

## Conventions

- **Routes** are versioned under `/api/v1` and grouped by middleware in `routes/api.php`.
- **Validation** lives in `app/Http/Requests`. Every API form request extends `ApiFormRequest` so validation errors match the project's error shape.
- **Authorization** lives in the controller (role checks, ownership checks) rather than in form requests, so the response uses the project's error shape rather than Laravel's default 403.
- **Eloquent resources** in `app/Http/Resources` are the single source of truth for response shapes. Use `whenLoaded` and `whenCounted` to keep payloads small.
- **Migrations** are immutable once shipped. Add a new migration to change schema; do not edit old ones.
- **Comments** are section-level only. See `CLAUDE.md` for the full commenting rule.

## Security

- Supabase JWTs are verified against Supabase's published JWKS on every protected request. Public keys are cached for one hour.
- File uploads are validated server-side (type and size) before being brokered to Supabase Storage via signed URLs. Never trust the client.
- Row Level Security (RLS) policies in Supabase enforce per-user data isolation as a second line of defence.
- All traffic must run over HTTPS in production.
- See the root `CLAUDE.md` for the full security model.

## Deployment

Production runs on Laravel Cloud. Before deploying:

1. Set every variable from the `.env.example` table above in the Laravel Cloud environment.
2. Run `php artisan migrate --force` as part of the release pipeline.
3. Confirm the Supabase project's CORS settings allow the production frontend origin.
4. Smoke test the `/api/v1/grant-rounds` endpoint and a login round-trip before flipping DNS.

## Further Reading

- Project overview, domain rules, and full API reference: [`../CLAUDE.md`](../CLAUDE.md)
- Backend-specific conventions and commenting rules: [`./CLAUDE.md`](./CLAUDE.md)
- Frontend integration: [`../frontend/CLAUDE.md`](../frontend/CLAUDE.md)
- Requirements spec (FR/NFR references): `../Docs/srs.md`

## License

Proprietary. Internal to the Grantly project.
