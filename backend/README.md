# Insider One Champions League — Backend

PHP / Laravel 11 + SQLite backend for the Insider One Champions League take-home test case.

Specification: `../docs/DEVELOPMENT_DOCUMENT.md` (Türkçe).
Approach: `../docs/TEST_CASE_APPROACH.md`.

---

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # already present, but harmless
php artisan migrate --seed
php artisan serve                   # http://127.0.0.1:8000
```

## Tests

```bash
# Fast suite (excludes @group slow)
composer test
# or
vendor/bin/phpunit --exclude-group=slow

# Full suite incl. slow Monte Carlo / 10k-iter tests
vendor/bin/phpunit
```

The PHPUnit config (`phpunit.xml`) targets in-memory SQLite (`DB_DATABASE=:memory:`)
with `TEST_SIMULATION_SEED=42` and a reduced Monte Carlo budget (1000) for fast feedback.

## API endpoints

All routes are stateless (no CSRF, no session). All `/api/*` paths.

| Method | Path                              | Body                                                | Notes |
| ------ | --------------------------------- | --------------------------------------------------- | ----- |
| GET    | `/api/league/state`               | —                                                   | Snapshot: league, standings, weekly_results, predictions |
| GET    | `/api/league/predictions`         | —                                                   | Current-week predictions snapshot |
| POST   | `/api/league/generate-fixtures`   | —                                                   | Rate-limited (10/min). Replaces existing fixtures. |
| POST   | `/api/league/play-next-week`      | `{ expected_week: int }`                            | 409 on mismatch (§4.5.5) |
| POST   | `/api/league/play-all-weeks`      | —                                                   | Rate-limited (10/min). Per-week transactions (US-F-03). |
| POST   | `/api/league/reset`               | —                                                   | Rate-limited (3/min). |
| PATCH  | `/api/matches/{id}`               | `{ home_score, away_score, expected_version: int }` | Optimistic lock (§4.5.4). 409 on stale version. |

### Status codes

| Code | Trigger |
| ---- | ------- |
| 200  | Success |
| 404  | Match not found |
| 409  | Optimistic / idempotency conflict |
| 422  | Validation failure (score out of range, missing field, unplayed match edit, etc.) |
| 423  | League locked (status = running / resetting) |
| 429  | Rate limit exceeded |

## Architecture

- `app/Models/` — Eloquent models (`Team`, `LeagueSettings`, `MatchModel`, `Standing`, `Prediction`)
- `app/Domain/Services/` — Pure-domain services (`FixtureGenerator`, `MatchSimulator`, `StandingsCalculator`, `ChampionshipPredictor`)
- `app/Domain/Actions/` — Transactional orchestrators (one DB::transaction per action, §4.5.1)
- `app/Domain/Support/SeededRandom.php` — Deterministic PRNG (LCG + Poisson sampler)
- `app/Domain/Tiebreak/StandingsSorter.php` — Tiebreak chain `PTS → GD → GF → name asc`
- `app/Exceptions/` — Domain exceptions mapped to HTTP 423 / 409
- `app/Http/Controllers/` — Thin controllers, FormRequests for validation
- `routes/api.php` — Route definitions + throttle middlewares

### Concurrency & atomicity (§4.5)

- All mutations run inside `DB::transaction()`.
- `LeagueSettings.status` enum is the state machine; mutations reject when status = `running` or `resetting`.
- `matches.version` provides optimistic lock; PATCH requires `expected_version`.
- `expected_week` provides Play-Next-Week idempotency.
- Play All Weeks commits per week so the season can resume after timeout/crash.
- Subseed strategy: `subseed = hash(seed, match.id, week)` (§4.5.9).

### SQLite hardening

`AppServiceProvider::boot()` enables `PRAGMA journal_mode=WAL`, `foreign_keys=ON`,
`busy_timeout=5000`, `synchronous=NORMAL` on every connection (§4.5.10).

## Deploy

### Railway

1. Create a Railway project from this repo (root = `backend/`).
2. Set environment variables:
   - `APP_KEY` (`php artisan key:generate --show` locally → paste value, or omit and let `release` phase regenerate).
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `FRONTEND_URL=https://<your-frontend>.vercel.app` (for CORS allowlist, NFR-19)
3. Nixpacks will detect `nixpacks.toml` and run install/build/release phases.
4. Healthcheck path: `/up` (added by Laravel default routing).

### Local SQLite gotchas

- `database/database.sqlite` is committed empty (0 bytes) so migrations can target it.
- For production deploys to ephemeral filesystems, mount a volume at `database/`.

## Production hardening checklist (US-H-08)

- [x] `APP_DEBUG=false` in `.env.example`
- [x] `APP_KEY` empty in `.env.example` (must be generated per environment)
- [x] `.env` listed in `.gitignore`
- [x] Stack traces hidden when `APP_DEBUG=false` (Laravel default)
- [x] Rate limiting active on reset / play-all / generate-fixtures
- [x] CSRF disabled on `/api/*` (NFR-19); CORS allowlist defined

## License

For-evaluation / take-home assessment.
