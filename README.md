# Insider One Champions League — Simulation

Premier League formatında 4 takımlı bir mini lig simülatörü. Olasılıksal maç motoru, dinamik şampiyonluk tahmini, "Play All Weeks" ve "Edit Match" özelliklerini içerir.

## 🚀 Live Demo

- **Frontend (Vue 3 SPA)**: https://test-case-black.vercel.app
- **Backend API**: https://testcase-production-ed2b.up.railway.app
- **API health check**: https://testcase-production-ed2b.up.railway.app/api/league/state

Frontend Vercel üzerinde, backend Railway üzerinde host ediliyor. Frontend'i açıp **Generate Fixtures → Start Simulation → Play Next Week / Play All Weeks** akışını deneyebilirsiniz.

## Tech Stack

| Katman | Teknoloji |
| --- | --- |
| Backend | PHP 8.2+ · Laravel 11 · SQLite |
| Frontend | Vue 3 · Vite · TypeScript · Pinia · Vue Router 4 · Axios |
| Test | PHPUnit (backend) · Vitest + @vue/test-utils (frontend) |
| Deploy | Railway (backend) · Vercel / Netlify (frontend) |

## Önkoşullar

Aşağıdakilerin sisteminde kurulu olması gerekiyor:

- **PHP 8.2+** (önerilen 8.3) — `php --version`
- **Composer 2.x** — `composer --version`
- **Node.js 20+** — `node --version`
- **npm 10+** — `npm --version`

PHP eklentileri: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `curl`, `zip`, `fileinfo` aktif olmalı.

## Hızlı Başlangıç

### 1) Backend (Laravel API)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Backend artık `http://localhost:8000` adresinde çalışıyor.

### 2) Frontend (Vue SPA)

Yeni bir terminal açın:

```bash
cd frontend
npm install
npm run dev
```

Frontend `http://localhost:5173` adresinde açılır ve `/api` isteklerini backend'e proxy eder (`vite.config.ts`).

### 3) Tarayıcıdan kullanım

`http://localhost:5173` adresine git ve aşağıdaki akışı izle:

1. **Setup ekranı** — 4 takım listelenir → **Generate Fixtures** tıkla → 6 haftalık fikstür görüntülenir.
2. **Start Simulation** → 3-panel simülasyon ekranına geçiş.
3. **Play Next Week** ile hafta hafta ilerle veya **Play All Weeks** ile sezonu tamamla.
4. 4. haftadan itibaren sağ panelde **şampiyonluk tahminleri** (yüzde) görünür.
5. Herhangi bir maçın üzerine tıklayıp skoru **Edit Match** modal'ından değiştir → tablo ve tahminler otomatik güncellenir.
6. **Reset Data** (kırmızı buton) ile sezonu sıfırlayıp baştan başlayabilirsin.

## Testleri Çalıştırma

### Backend (PHPUnit)

```bash
cd backend
php artisan test
```

61 test bekleniyor: 6 unit/domain (FixtureGenerator, MatchSimulator, ChampionshipPredictor, vb.) + 11 feature/api (Concurrency, EditMatch, RateLimit, Idempotency, vb.).

Kritik testler:
- `ChampionshipPredictorTest::test_faq_example_a_uncatchable_leader_returns_exactly_100_for_leader` — Yakalanamaz lider senaryosu (FAQ Örnek A).
- `ChampionshipPredictorTest::test_faq_example_b_top_two_in_range_and_others_zero` — Son hafta eşit puanlı senaryo (FAQ Örnek B).

### Frontend (Vitest)

```bash
cd frontend
npm run test:unit
```

46 test bekleniyor: 5 component spec + 1 store + 1 API + 1 view, toplam ~46 assertion.

### Production Build

```bash
cd frontend
npm run build
```

`dist/` klasörünü üretir (~65 kB gzip JS).

## Proje Yapısı

```
testCase/
├── backend/              # Laravel 11 API
│   ├── app/
│   │   ├── Domain/       # Saf OOP domain layer
│   │   │   ├── Actions/  # Transactional use-cases
│   │   │   ├── Services/ # FixtureGenerator, MatchSimulator, ChampionshipPredictor, ...
│   │   │   ├── Support/  # SeededRandom (deterministik PRNG)
│   │   │   └── Tiebreak/ # StandingsSorter
│   │   ├── Http/         # Controllers + FormRequests
│   │   └── Models/       # Eloquent: Team, MatchModel, Standing, Prediction, LeagueSettings
│   ├── database/
│   │   ├── migrations/   # 5 migration (CHECK constraints + indexes)
│   │   └── seeders/      # 4 takım + lig ayarları
│   ├── routes/api.php
│   ├── tests/            # 17 test sınıfı, 61 test
│   └── README.md
├── frontend/             # Vue 3 SPA
│   ├── src/
│   │   ├── api/          # Typed Axios client
│   │   ├── components/   # 11 component (Setup, Simulation, Modal, Toast)
│   │   ├── stores/       # Pinia store (mutation lock + HTTP code handling)
│   │   ├── types/        # TypeScript domain types
│   │   ├── views/        # SetupView, SimulationView
│   │   └── router/
│   ├── tests/            # 8 spec dosyası, 46 test
│   └── README.md
├── docs/
│   ├── DEVELOPMENT_DOCUMENT.md   # Spec & user stories (Türkçe)
│   └── TEST_CASE_APPROACH.md
├── instructions/         # Orijinal brief + FAQ + video
└── README.md             # Bu dosya
```

## Mimari Notlar

### Backend Layered Architecture

```
HTTP   →  Controller (thin)
       →  FormRequest (validation)
       →  Action       (transactional use-case, DB::transaction)
       →  Service      (domain logic: simulator, predictor, ...)
       →  Model        (Eloquent)
```

### Eşzamanlılık (Concurrency)

- **State machine**: `league_settings.status ∈ {idle, running, resetting, finished}`. Mutation çalışırken diğer mutation `HTTP 423 Locked` döner.
- **Optimistic lock**: `matches.version` integer. PATCH isteğinde `expected_version` mismatch → `HTTP 409 Conflict`.
- **Idempotency**: `play-next-week` body'sinde `expected_week`; eşleşmezse `409`.
- **Atomic ops**: Her action `DB::transaction()` içinde (matches + standings + predictions + current_week).
- **Rate limiting**: `reset` 3/dk, `play-all` ve `generate-fixtures` 10/dk per IP.

## Maç Simülasyon Algoritması

Poisson örnekleme ile per-side beklenen gol:

```
λ_home = 1.35 × (power_home / 50) × (1 + supporter_home/200) / (1 + keeper_away/200) × (1 + home_advantage)
λ_away = 1.35 × (power_away / 50) × (1 + supporter_away/200) / (1 + keeper_home/200)
home_score = Poisson(λ_home)   [0..20 ile sınırlı]
away_score = Poisson(λ_away)   [0..20 ile sınırlı]
```

- `home_advantage = 0.30` (config).
- Zayıf takım için `λ_away > 0` → **sürpriz galibiyet her zaman mümkün** (FAQ Q5).
- Deterministik: `subseed = hash(league.seed, match.id, week veya trial)`. Aynı seed → aynı skor.

## Şampiyonluk Tahmin Algoritması

1. **Tetikleme**: `currentWeek > totalWeeks - 3` (jenerik formül; 4 takım → hafta 4-5-6).
2. **Yakalanamaz lider kısa devresi** (FAQ Örnek A): Hiçbir takım liderin puanını matematiksel olarak yakalayamıyorsa → lider %100, diğerleri %0 (Monte Carlo'ya gerek yok).
3. **Monte Carlo** (FAQ Örnek B): `N=10.000` deneme. Her denemede kalan maçlar simüle edilir, sonra tabloyu sıralayıp 1. sıradaki şampiyon olarak sayılır.
4. **Sıralama tiebreak'i**: `PTS desc → GD desc → GF desc → name asc`.
5. **Normalizasyon**: **Largest remainder method** + "absorber pattern" (Sterbenz lemma) ile `sum(probabilities) == 100.00` tam değer garantisi.
6. **Snapshot**: `predictions` tablosuna `(team_id, week, champion_probability)` upsert.

## REST API

| Method | Path | Açıklama | Rate limit |
| --- | --- | --- | --- |
| GET | `/api/league/state` | Lig durumu (settings, teams, matches, standings, predictions) | — |
| POST | `/api/league/generate-fixtures` | Fikstür üret (rasgele seed, eskiyi siler) | 10/dk |
| POST | `/api/league/play-next-week` | Bir hafta simüle et (body: `expected_week`) | — |
| POST | `/api/league/play-all-weeks` | Sezonu tamamla | 10/dk |
| POST | `/api/league/reset` | Tüm veriyi sıfırla | 3/dk |
| GET | `/api/league/predictions` | Mevcut hafta tahminleri | — |
| PATCH | `/api/matches/{id}` | Skor düzenle (body: `home_score`, `away_score`, `expected_version`) | — |

### HTTP Hata Kodları
- `409 Conflict` — Stale `expected_version` / `expected_week`.
- `422 Unprocessable Entity` — Validation hatası (negatif skor, oynanmamış maç, vb.).
- `423 Locked` — State machine ihlali (örn. `Play All Weeks` çalışırken `Reset Data`).
- `429 Too Many Requests` — Rate limit aşıldı.

## Premier League Kuralları

- **Skor**: Galibiyet 3 pts, Beraberlik 1 pts, Mağlubiyet 0 pts (FAQ Q1).
- **Sıralama**: PTS → GD → GF → Team Name (alfabetik). H2H kapsam dışı.

## Geliştirme Dokümantasyonu

Detaylı user stories, acceptance criteria, NFR'lar, mimari kararlar ve açık sorular için:

- [`docs/DEVELOPMENT_DOCUMENT.md`](docs/DEVELOPMENT_DOCUMENT.md) — 8 epic, 40 story, ~70 AC, 20 NFR (Türkçe)
- [`docs/TEST_CASE_APPROACH.md`](docs/TEST_CASE_APPROACH.md) — Mimari ve sprint planı

## Dağıtım Notları

### Backend (Railway)

- `nixpacks.toml` ve `Procfile` mevcut.
- Env değişkenleri: `APP_KEY`, `APP_DEBUG=false`, `APP_ENV=production`, `FRONTEND_URL=https://<vercel-domain>`.
- Migrate ve seed `release` aşamasında otomatik çalışır.

### Frontend (Vercel veya Netlify)

- `vercel.json` ve `netlify.toml` SPA rewrite kurallarıyla hazır.
- Build env: `VITE_API_BASE_URL=https://<railway-domain>`.
- Build command: `npm run build`, output dir: `dist`.

## Lisans

Bu proje, Insider One değerlendirme süreci için hazırlanmış bir take-home test case'idir.
