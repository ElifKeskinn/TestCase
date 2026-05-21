# Insider One Champions League — Frontend

Vue 3 + Vite + Pinia + Axios SPA for the "Insider One Champions League" football
league simulator. Full product spec: `../docs/DEVELOPMENT_DOCUMENT.md`.

## Stack

| Layer | Choice |
| --- | --- |
| Framework | Vue 3 (`<script setup>`, Composition API) |
| Build | Vite 5 + TypeScript (strict) |
| State | Pinia |
| Routing | vue-router 4 |
| HTTP | Axios |
| Tests | Vitest + @vue/test-utils + jsdom |

## Setup

```bash
# from the project root
cd frontend
npm install
cp .env.example .env
# edit .env if your backend runs on a different host
npm run dev
```

The dev server boots on <http://localhost:5173>. Requests to `/api/*` are proxied
to `http://localhost:8000` (see `vite.config.ts`), so when running the Laravel
backend locally on port 8000 you can leave `VITE_API_BASE_URL` empty.

To point the SPA at a deployed backend, set:

```env
VITE_API_BASE_URL=https://your-backend.up.railway.app
```

## Scripts

| Command | What it does |
| --- | --- |
| `npm run dev` | Start Vite dev server with `/api` proxy. |
| `npm run build` | Type-check + production build → `dist/`. |
| `npm run build:no-typecheck` | Pure Vite build (skip vue-tsc), useful for emergency deploys. |
| `npm run preview` | Serve the production build locally on port 5173. |
| `npm run test:unit` | Run Vitest test suite once. |
| `npm run test:watch` | Vitest in watch mode. |
| `npm run lint` | ESLint (config not bundled by default). |

## Architecture

```
src/
├── App.vue                 # Shell + global toast container
├── main.ts                 # createApp, pinia, router
├── api/
│   └── leagueApi.ts        # Typed Axios client (7 endpoints)
├── stores/
│   └── league.ts           # Pinia store: state, mutation lock, error handling
├── router/
│   └── index.ts            # /  /simulation + nav guard (requires fixtures)
├── views/
│   ├── SetupView.vue       # Teams + Generate Fixtures + Fixtures + Start
│   └── SimulationView.vue  # 3-panel layout + Controls + modals
└── components/
    ├── TournamentTeams.vue
    ├── GenerateFixturesButton.vue
    ├── FixtureDisplay.vue
    ├── StartSimulationButton.vue
    ├── LeagueTable.vue        # Team / PTS / P / W / D / L / GD
    ├── WeekResults.vue        # Week N + prev/next navigator + Edit Match
    ├── ChampionshipPrediction.vue
    ├── Controls.vue           # Play Next / Play All / Reset Data (red)
    ├── EditMatchModal.vue     # 0–20 validation + optimistic version
    ├── ConfirmModal.vue       # Reset Data confirmation (US-A-03 AC-4)
    └── ToastContainer.vue
```

## Key behaviours mapped to the spec

- **Mutation lock (NFR-15, US-A-06):** the Pinia store sets `isMutating = true`
  on every mutation and clears it in `finally`. All mutation buttons are
  disabled while in-flight; read-only panels are not.
- **HTTP error handling (US-A-06 AC-4 / §4.5.8):** 409 / 422 / 423 / 429
  responses are mapped to specific toasts via `describeError()`.
- **Reset Data button is red** (`btn-danger`, US-A-03 AC-3) and triggers a
  confirmation modal (US-A-03 AC-4).
- **Prediction trigger formula (US-E-01 AC-4):** `currentWeek > totalWeeks - 3`
  — never hard-coded as `week >= 4`. Both the store and
  `ChampionshipPrediction.vue` use the generic formula.
- **Optimistic lock (US-G-03):** `EditMatchModal` always sends
  `expected_version` from the loaded match.
- **Idempotency (§4.5.5):** `playNextWeek()` always sends the next
  `expected_week`.
- **Tiebreak (§4.4):** the store sorts standings defensively as
  `PTS desc → GD desc → GF desc → team_name asc`.
- **Navigation guard:** `/simulation` redirects to `/` if no fixtures exist
  (US-B-05).

## Testing

```bash
npm run test:unit
```

The suite covers:

- `tests/api/leagueApi.spec.ts` — Axios contract (endpoints + payloads).
- `tests/stores/league.spec.ts` — mutation lock, error mapping, action sequence.
- `tests/components/LeagueTable.spec.ts` — columns, ordering, snapshot.
- `tests/components/ChampionshipPrediction.spec.ts` — trigger formula, sum=100.
- `tests/components/Controls.spec.ts` — disabled state, Reset Data class.
- `tests/components/WeekResults.spec.ts` — week navigator.
- `tests/components/EditMatchModal.spec.ts` — 0–20 validation, `expected_version`.
- `tests/components/SetupView.spec.ts` — happy path: setup → generate → start.

## Deploy

### Vercel

`vercel.json` ships an SPA-rewrite rule. Steps:

1. `npm install -g vercel` (or use the dashboard).
2. From `frontend/`, run `vercel --prod`.
3. Set `VITE_API_BASE_URL` in the Vercel project settings to the Railway
   backend URL.

### Netlify

`netlify.toml` covers build command, publish dir and SPA fallback. Steps:

1. Connect the repo in Netlify; set base directory to `frontend/`.
2. Configure `VITE_API_BASE_URL` under Site settings → Environment variables.
3. Trigger a deploy.

### Backend CORS

Per `docs/DEVELOPMENT_DOCUMENT.md` §NFR-19, the backend must allowlist the
deployed frontend origin plus `http://localhost:5173`.

## License

For-evaluation / take-home assessment.
