# Insider One Champions League — Geliştirme Dokümanı

> Bu doküman, "Insider One Champions League" futbol ligi simülatörü test case'i için hazırlanmıştır.
> Kaynaklar: `instructions/InsiderOne-ChampionsLeague-.pdf` (Brief), `instructions/FAQ for the Project.pdf` (FAQ), `instructions/Game Sim (2).mp4` (Video).
> Doküman dili Türkçe'dir; teknik terimler gerektiğinde İngilizce bırakılmıştır.
> İlgili strateji notu: `docs/TEST_CASE_APPROACH.md` (mimari, AI kullanımı, sprint planı). Bu doküman scope/feature/story tarafıdır; iki doküman birbiriyle örtüşmez.

---

## İçindekiler

1. Proje Özeti
2. Teknik Stack ve Kısıtlar
3. Yüksek Seviye Mimari
4. Domain Model / Veri Modeli
   - 4.5 Transaction & Eşzamanlılık (Concurrency)
5. Features (Üst Seviye Özellik Listesi)
6. Epics ve User Stories
7. Detaylı Kabul Kriterleri Tablosu
8. Non-Functional Requirements (NFR)
9. Test Stratejisi
10. Riskler ve Bağımlılıklar
11. Out of Scope / Açık Sorular
12. Definition of Done (DoD)
13. Güvenlik Notları

---

## 1. Proje Özeti

**Insider One Champions League**, Premier League'in 4 köklü takımı (Liverpool, Manchester City, Chelsea ve Arsenal) arasında oynanan mini bir turnuvayı simüle eden web tabanlı bir uygulamadır. Kullanıcı, fikstürü tek tuşla üretir, haftaları teker teker veya toptan oynatır ve sezon ilerledikçe dinamik olarak güncellenen şampiyonluk tahminlerini izler.

### 1.1. Hedef

- Bir futbol ligi sezonunu (çift devreli round-robin) hızlı ve görsel olarak simüle etmek.
- Premier League puanlama (G=3, B=1, M=0) kurallarına göre dinamik bir lig tablosu sunmak.
- Sezonun son haftalarında olasılıksal şampiyonluk tahmini üretmek.
- Geliştiricinin yazılım mühendisliği, OOP, test ve dağıtım yetkinliklerini değerlendirmek (test case amacı).

### 1.2. Kapsam

- **Varsayılan 4 takım**, çift devreli round-robin → 6 hafta × 2 maç = **12 maç**.
- **Esnek takım sayısı (Flexibility)**: mimari ve fikstür algoritması, takım sayısı değişse bile (örn. 4, 6, 8) bozulmadan çalışmalıdır. 4 sadece varsayılan.
- **İki ekranlı akış**:
  1. **Setup ekranı** — "Tournament Teams" listesi + "Generate Fixtures" butonu + üretilen fikstürün önizlemesi + "Start Simulation" butonu.
  2. **Simulation ekranı** — Sol panel (Lig Tablosu), Orta panel (Haftalık Maçlar / Week N), Sağ panel (Championship Predictions) + kontrol butonları (Play Next Week, Play All Weeks, Reset Data).
- Probabilistik fakat sürprize açık maç motoru (zayıf takım sıfır olmayan olasılıkla kazanabilir).
- Şampiyonluk tahminleri: sezonun son 3 haftası boyunca (week 4, 5, 6) yüzde olarak; toplam = %100.
- Extras (artı puan): "Play All Weeks", "Edit Match Results".
- Public deploy (canlıya alma).

### 1.3. Kapsam Dışı

- Kullanıcı yönetimi, kimlik doğrulama, hesap, profil.
- Gerçek maç istatistikleri (xG, pass map, vb.).
- Çoklu lig, çoklu sezon arşivi.
- Native mobil uygulama.

### 1.4. Ana Aktörler

| Aktör | Rol |
| --- | --- |
| **Ziyaretçi (Visitor)** | Public deploy üzerinde uygulamayı kullanır; turnuva üretir, oynatır, izler. |
| **Geliştirici (Developer)** | Domain kurallarını uygulayan ve test eden kişi. |
| **Değerlendirici (Reviewer)** | Insider One tarafında testi inceleyen kişi; brief'e uyumu, kod kalitesini ve deploy'u kontrol eder. |

---

## 2. Teknik Stack ve Kısıtlar

### 2.1. Zorunlu Stack (Brief gereği)

| Katman | Teknoloji | Not |
| --- | --- | --- |
| Backend | **PHP + Laravel** | Brief tarafından zorunlu kılınmış. |
| Frontend | **Vue.js** veya **Native JS + component pattern** | İki seçenekten biri seçilebilir; component pattern uygulanmalı. |
| Programlama yaklaşımı | **OOP (Object Oriented Programming)** | Tüm domain ve servis katmanı OOP. |
| Testler | **Unit tests** | En azından domain ve match engine için zorunlu. |
| Dağıtım | **Public deploy** | URL paylaşılabilir, herkese açık. |

### 2.2. Önerilen Stack (TEST_CASE_APPROACH.md ile tutarlı)

| Katman | Seçim | Gerekçe |
| --- | --- | --- |
| Backend framework | Laravel 10/11 | Brief gereği. |
| Veritabanı | **SQLite** | 4 takım / 12 maç için fazlasıyla yeterli; tek dosya, deploy kolay. |
| Frontend | **Vue 3 + Vite + Pinia + Axios** | Vue Test Utils + Vitest ekosistemi. |
| HTTP | RESTful JSON API | Stateless, tahmin/skor sorguları için uygun. |
| Test (BE) | PHPUnit | Laravel ile entegre. |
| Test (FE) | Vitest + Vue Test Utils | Bileşen ve store testleri. |
| Deploy (BE) | Railway / Laravel Cloud / Render | Public URL. |
| Deploy (FE) | Laravel `public/` (tek deploy) veya Vercel/Netlify | CORS karmaşası olmaması için tek deploy önerilir. |

### 2.3. Kısıtlar

1. **PHP/Laravel** dışında bir backend framework kullanılamaz.
2. **OOP** ihlal edilemez (procedural kod kabul edilmez).
3. **Match engine deterministik test edilebilir** olmalı → seed alınabilir bir RNG zorunlu.
4. **Public deploy çalışır durumda** olmalı; sadece localhost teslim kabul edilmez.
5. **README** açık ve eksiksiz olmalı (kurulum, çalıştırma, AI kullanımı notu).
6. **AI kullanımı şeffaf** belirtilmeli (commit notlarında AI-assisted etiketi).

---

## 3. Yüksek Seviye Mimari

### 3.1. Mimari Diyagram (Mantıksal)

```
┌────────────────────────────────────────────────────────────────┐
│                       Browser (Public)                          │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │  Vue 3 SPA (or Native JS)                                 │  │
│  │  ┌──────────────┐         ┌──────────────────────────┐   │  │
│  │  │ SetupScreen  │ ──────► │ SimulationScreen          │   │  │
│  │  │  - Teams     │         │  - LeagueTable            │   │  │
│  │  │  - Generate  │         │  - WeekResults            │   │  │
│  │  │  - Fixtures  │         │  - ChampionshipPrediction │   │  │
│  │  │  - Start     │         │  - Controls (Next/All/    │   │  │
│  │  │              │         │     Reset)                │   │  │
│  │  └──────────────┘         └──────────────────────────┘   │  │
│  └─────────────────────┬────────────────────────────────────┘  │
└────────────────────────┼───────────────────────────────────────┘
                         │ REST/JSON (Axios)
┌────────────────────────▼───────────────────────────────────────┐
│                  Laravel Application                            │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ HTTP Layer                                              │   │
│  │  - Routes (api.php)                                     │   │
│  │  - Controllers (LeagueController, MatchController)      │   │
│  │  - FormRequests (validation)                            │   │
│  └──────────────────┬──────────────────────────────────────┘   │
│                     │                                          │
│  ┌──────────────────▼──────────────────────────────────────┐   │
│  │ Application Layer (Actions / Services)                  │   │
│  │  - GenerateFixturesAction                               │   │
│  │  - PlayWeekAction / PlayAllWeeksAction                  │   │
│  │  - ResetLeagueAction                                    │   │
│  │  - UpdateMatchResultAction                              │   │
│  │  - PredictionService (Strategy)                         │   │
│  └──────────────────┬──────────────────────────────────────┘   │
│                     │                                          │
│  ┌──────────────────▼──────────────────────────────────────┐   │
│  │ Domain Layer (saf OOP, framework-agnostik)              │   │
│  │  - MatchEngine (probabilistic)                          │   │
│  │  - FixtureGenerator (round-robin, N takım esnek)        │   │
│  │  - LeagueStanding (PTS/P/W/D/L/GD)                      │   │
│  │  - SeededRandom                                         │   │
│  │  - MonteCarloPredictionStrategy                         │   │
│  └──────────────────┬──────────────────────────────────────┘   │
│                     │                                          │
│  ┌──────────────────▼──────────────────────────────────────┐   │
│  │ Persistence (Eloquent)                                  │   │
│  │  - Team, Match, Fixture, LeagueSettings                 │   │
│  └─────────────────────────────────────────────────────────┘   │
└────────────────────────┬───────────────────────────────────────┘
                         │
                  ┌──────▼──────┐
                  │  SQLite DB   │
                  └─────────────┘
```

### 3.2. Frontend Components (Video özetine göre)

Vue.js (veya Native JS + component pattern) üzerinde, video özetiyle birebir uyumlu bileşen ağacı:

- `<SetupScreen/>` — üst seviye view, fikstür üretimi öncesi/sonrası.
  - `<TournamentTeams/>` — 4 (veya N) takımın listelenmesi.
  - `<FixtureDisplay/>` — 6 (veya 2×(N−1)) haftalık program tablosu.
  - Setup butonları: `Generate Fixtures`, `Start Simulation`.
- `<SimulationScreen/>` — üst seviye view, 3 panelli görünüm.
  - `<LeagueTable/>` — sol panel, sütunlar: Team, PTS, P, W, D, L, GD.
  - `<WeekResults/>` — orta panel, "Week N" başlığı + o haftanın maçları.
  - `<ChampionshipPrediction/>` — sağ panel, her takım için yüzde.
  - `<Controls/>` — alt/üst kontrol satırı: `Play Next Week`, `Play All Weeks`, `Reset Data` (kırmızı).
  - `<EditMatchModal/>` — Epic G (extras) maç sonucu düzenleme modali.

### 3.3. REST API (Taslak)

| Method | Endpoint | Amaç | Not |
| --- | --- | --- | --- |
| GET | `/api/league/state` | Mevcut lig durumunu (teams, fixtures, currentWeek, standings, predictions) döner. | Frontend ilk yüklemede çağırır. |
| POST | `/api/league/generate-fixtures` | Mevcut fikstürü siler ve seçili takımlar için yeniden üretir. | **Idempotent değildir**; her çağrı yeni fikstür anlamına gelir. |
| POST | `/api/league/play-next-week` | Sıradaki haftayı simüle eder, skorları/puanları döner. | `currentWeek` artar. |
| POST | `/api/league/play-all-weeks` | Kalan tüm haftaları simüle eder. | Sezonu tamamlar. |
| POST | `/api/league/reset` | Tüm sonuçları ve fikstürü sıfırlar; setup ekranına döner. | `Reset Data` butonu. |
| GET | `/api/league/predictions` | Şampiyonluk tahminlerini döner (`currentWeek > totalWeeks - 3` ise dolu, aksi halde boş). | |
| PATCH | `/api/matches/{id}` | Bir maçın skorunu günceller; tablo ve tahminler yeniden hesaplanır. Body: `{home_score, away_score, expected_version}`. | Epic G (extras); optimistic lock. |

**Not — Idempotency parametreleri (bkz. §4.5)**:
- `POST /api/league/play-next-week` body'sinde `expected_week` zorunludur (çift tıklama koruması; uyumsuzsa 409).
- `PATCH /api/matches/{id}` body'sinde `expected_version` zorunludur (eski version → 409 Conflict).
- State machine ihlali → **423 Locked**.
- Validation hatası → **422 Unprocessable Entity**.
- Rate limit aşımı → **429 Too Many Requests**.

### 3.4. Akış (Sequence Özeti)

1. Kullanıcı uygulamayı açar → `GET /api/league/state` → Setup ekranı (takımlar listelenir).
2. Kullanıcı "Generate Fixtures" → `POST /api/league/generate-fixtures` → 6 haftalık fikstür gösterilir.
3. Kullanıcı "Start Simulation" → Simulation ekranına geçiş (frontend route).
4. Kullanıcı "Play Next Week" → `POST /api/league/play-next-week` → tablo + tahmin güncellenir.
5. Kullanıcı "Play All Weeks" → `POST /api/league/play-all-weeks` → tüm haftalar oynanır.
6. Kullanıcı "Reset Data" → `POST /api/league/reset` → Setup ekranına döner.

---

## 4. Domain Model / Veri Modeli

### 4.1. Varlıklar (Entities)

| Entity | Açıklama |
| --- | --- |
| `Team` | Bir takımı temsil eder. İsim ve güç (power) parametreleri içerir. |
| `Match` | İki takım arasındaki tek bir karşılaşma. Skor + hafta numarası + optimistic lock version. |
| `Fixture` | Sezon boyunca tüm maçların listesi (haftalara dağılmış). |
| `LeagueSettings` | Lig ayarları: takım sayısı, seed, currentWeek, status (state machine). |
| `Standing` | Bir takıma ait kalıcı puan/maç istatistik snapshot'ı (derived; write-through). |
| `Prediction` | Belirli bir hafta + takım için şampiyonluk olasılığı snapshot'ı. |

### 4.2. Şema (SQLite — Eloquent migrations)

**teams**
| Sütun | Tip | Not |
| --- | --- | --- |
| id | PK | |
| name | VARCHAR(50) NOT NULL UNIQUE | Örn. "Liverpool" |
| power | integer NOT NULL | CHECK (power BETWEEN 1 AND 100) |
| supporter | integer NULL | CHECK (supporter IS NULL OR supporter BETWEEN 0 AND 100), opsiyonel motor parametresi |
| keeper | integer NULL | CHECK (keeper IS NULL OR keeper BETWEEN 0 AND 100), opsiyonel motor parametresi |

**matches**
| Sütun | Tip | Not |
| --- | --- | --- |
| id | PK | |
| week | integer NOT NULL | CHECK (week >= 1); 1..2×(N−1) |
| home_team_id | FK teams.id ON DELETE RESTRICT NOT NULL | |
| away_team_id | FK teams.id ON DELETE RESTRICT NOT NULL | |
| home_score | nullable int | CHECK (home_score IS NULL OR home_score BETWEEN 0 AND 20) |
| away_score | nullable int | CHECK (away_score IS NULL OR away_score BETWEEN 0 AND 20) |
| played_at | nullable datetime | |
| version | integer NOT NULL DEFAULT 1 | **Optimistic lock** (US-G-03). |
| editions_count | integer NOT NULL DEFAULT 0 | Audit counter (kaç kez manuel edit edildi). |
| created_at, updated_at | timestamps | |

CHECK constraints (matches):
- `chk_diff_teams`: `CHECK (home_team_id <> away_team_id)`
- `chk_score_both_or_neither`: `CHECK ((home_score IS NULL AND away_score IS NULL) OR (home_score IS NOT NULL AND away_score IS NOT NULL))`

Indexes (matches):
- `UNIQUE (week, home_team_id, away_team_id)`
- `INDEX (week)`
- `INDEX (home_team_id)`, `INDEX (away_team_id)`

**league_settings** (singleton, id = 1)
| Sütun | Tip | Not |
| --- | --- | --- |
| id | PK | tek satır (singleton) |
| team_count | integer NOT NULL | CHECK (team_count BETWEEN 2 AND 8) |
| current_week | integer NOT NULL DEFAULT 0 | CHECK (current_week >= 0); 0..total_weeks |
| total_weeks | integer NOT NULL | Derived: `2*(team_count - 1)`. |
| seed | bigint NULL | Reproducibility için. |
| status | ENUM('idle','running','resetting','finished') NOT NULL DEFAULT 'idle' | State machine (bkz. §4.5.2). |
| status_updated_at | datetime NULL | Stuck-state tespit için. |

**standings** (snapshot, write-through)
| Sütun | Tip | Not |
| --- | --- | --- |
| id | PK | |
| team_id | FK teams.id ON DELETE CASCADE UNIQUE | Her takım için tek satır. |
| played | integer NOT NULL DEFAULT 0 | |
| won | integer NOT NULL DEFAULT 0 | |
| drawn | integer NOT NULL DEFAULT 0 | |
| lost | integer NOT NULL DEFAULT 0 | |
| goals_for | integer NOT NULL DEFAULT 0 | |
| goals_against | integer NOT NULL DEFAULT 0 | |
| goal_diff | integer NOT NULL DEFAULT 0 | Stored = GF − GA. |
| points | integer NOT NULL DEFAULT 0 | Stored = 3*W + D. |
| updated_at | timestamp | |

CHECK constraints (standings):
- `CHECK (played = won + drawn + lost)`
- `CHECK (goal_diff = goals_for - goals_against)`
- `CHECK (points = 3*won + drawn)`

Index (standings):
- `INDEX idx_standings_sort (points DESC, goal_diff DESC, goals_for DESC, team_id ASC)` — tiebreak zinciri ile birebir.

**predictions** (snapshot, per team × week)
| Sütun | Tip | Not |
| --- | --- | --- |
| id | PK | |
| team_id | FK teams.id ON DELETE CASCADE | |
| week | integer NOT NULL | Hangi haftadaki snapshot. |
| champion_probability | decimal(5,2) NOT NULL | CHECK (champion_probability BETWEEN 0 AND 100). |
| computed_at | timestamp NOT NULL | |

- `UNIQUE (team_id, week)`
- `INDEX (week)`

> Not: "sum(probability) per week == 100" — uygulama-seviye (application-level) invariant'tır (PredictionService garanti eder). DB CHECK ile sağlanamaz çünkü aggregate'tir; testle (US-H-02) ve service-level assertion ile korunur.

### 4.3. Domain Sınıfları (saf PHP, framework-agnostik)

```
App\Domain\Engine\MatchEngine
App\Domain\Engine\SeededRandom
App\Domain\Fixture\FixtureGenerator
App\Domain\League\LeagueStanding
App\Domain\Prediction\PredictionStrategy        (interface)
App\Domain\Prediction\MonteCarloStrategy
```

### 4.4. Lig Mantığı

- **Puanlama**: Galibiyet = 3, Beraberlik = 1, Mağlubiyet = 0 (Premier League standartı, FAQ Q1).
- **Sıralama (tiebreak — KARAR alındı, sabit kural)**: **`PTS desc → GD desc → GF desc → team_name asc (alfabetik)`**. H2H kapsam dışıdır. Bu zincir hem `standings` index'inde hem de PHP-side `LeagueStanding::sort()` içinde birebir kullanılır. (OQ-01 kapatıldı; bkz. §11.)
- **Tablo sütunları (UI)**: Team, **PTS**, P, W, D, L, GD.
  > Not: Video özetinde "Team name, P, W, D, L, GD" listelendi; ancak Brief PDF'inin figürlerinde PTS sütunu net şekilde mevcuttur. Brief otoriter kabul edilmiş ve PTS korunmuştur (video özeti muhtemelen kısaltılmış anlatımdır).
- **Fikstür**: Round-robin, çift devreli. 4 takım için 12 maç / 6 hafta. Genel formül: maç sayısı = N×(N−1), hafta sayısı = 2×(N−1).
- **Tahmin tetikleme (jenerik, N takım için)**: **`currentWeek > totalWeeks - 3`** (yani "kalan hafta ≤ 3"). 4 takım için bu `week >= 4` ile eşdeğerdir; 6 takım (totalWeeks=10) için `week >= 8`; 8 takım (totalWeeks=14) için `week >= 12`. **Hard-coded `week >= 4` referansı kullanılmaz** — yalnızca jenerik formül.
- **Tahmin**: tetikleme koşulu sağlandığında tahminler dolu olur; **`sum(predictions) == 100` (tam değer, largest remainder method ile)**.

### 4.5. Transaction & Eşzamanlılık (Concurrency)

Bu alt bölüm; çift tıklama, paralel istek, partial failure, server crash ve multi-tab senaryolarında veri tutarlılığını garanti altına alır.

#### 4.5.1 Transaction Boundary (Atomic Operations)

Aşağıdaki action'lar tek bir `DB::transaction()` içinde çalışır (hepsi-veya-hiçbiri):

| Action | Kapsanan Tablolar | Açıklama |
| --- | --- | --- |
| `GenerateFixturesAction` | matches, league_settings | Eski fikstürü sil + yeni fikstür oluştur + `current_week = 0` reset + status idle. |
| `PlayWeekAction` | matches, standings, predictions, league_settings | Week maçları simüle + skor yazımı + standings upsert + predictions upsert (koşul sağlanırsa) + `current_week` artırımı. |
| `PlayAllWeeksAction` | (her hafta için ayrı transaction) | Her hafta için ayrı commit — resume edilebilirlik için (bkz. §4.5.9). |
| `UpdateMatchResultAction` | matches, standings, predictions | Maç skoru update + tüm matches'tan baştan standings yeniden hesap + predictions yeniden hesap (kapsam dahilinde). |
| `ResetLeagueAction` | matches, standings, predictions, league_settings | `DELETE FROM matches; DELETE FROM standings; DELETE FROM predictions; UPDATE league_settings SET current_week=0, status='idle';` |

#### 4.5.2 State Machine

`league_settings.status` enum: `idle | running | resetting | finished`.

- `idle`: Hiçbir mutasyon çalışmıyor. Mutasyon kabul edilir.
- `running`: Play All Weeks devam ediyor.
- `resetting`: Reset Data devam ediyor.
- `finished`: Sezon tamamlandı (`current_week == total_weeks`).

Geçişler:

```
idle ──► running           (Play All Weeks başlar)
running ──► idle           (Play All Weeks biter, sezon devam ediyor)
running ──► finished       (Play All Weeks sezonu bitirir)
idle ──► resetting         (Reset Data başlar)
finished ──► resetting     (Reset Data başlar)
resetting ──► idle         (Reset Data biter)
```

#### 4.5.3 Pessimistic Lock (league_settings)

Her mutasyon action'ı transaction başında:

- **PostgreSQL/MySQL**: `SELECT * FROM league_settings WHERE id = 1 FOR UPDATE`
- **SQLite**: `BEGIN IMMEDIATE` (tek-yazıcı varsayımı yeterli; WAL ile birlikte)

Eğer `status != 'idle'` ise (ve istenen geçiş izinli değilse, örn. `finished → reset` hariç) mutasyon **HTTP 423 Locked** döner.

#### 4.5.4 Optimistic Lock (matches)

`matches` tablosuna `version integer NOT NULL DEFAULT 1` sütunu eklenir.

`PATCH /api/matches/{id}` body'sinde `expected_version` zorunludur:

```sql
UPDATE matches
   SET home_score = ?, away_score = ?, version = version + 1, editions_count = editions_count + 1
 WHERE id = ?
   AND version = ?
```

Affected rows = 0 ise **HTTP 409 Conflict** döner ve istek istemcinin GET ile durumu yenilemesi gerekir.

#### 4.5.5 Play Next Week Idempotency

`POST /api/league/play-next-week` body'sinde `expected_week` zorunludur:

- DB'deki `current_week + 1 != expected_week` ise **HTTP 409 Conflict** döner.
- Çift tıklama bu yolla engellenir; aynı `expected_week` ile gelen ikinci istek 409 alır.

#### 4.5.6 Frontend Mutation Lock

Pinia `leagueStore` içinde `isMutating: boolean` global flag:

- Mutasyon API çağrısı başlarken `true` set edilir.
- Yanıt dönünce (success **veya** error) `false` set edilir.
- `isMutating === true` iken **Play Next Week, Play All Weeks, Reset Data, Edit Match (Save)** butonları `disabled` olur.
- Read-only UI (LeagueTable, WeekResults, ChampionshipPrediction) etkilenmez.

#### 4.5.7 Snapshot Yenileme Sırası (Transaction İçinde)

Her mutasyonel transaction içinde sıra **şu şekilde** olmalıdır:

1. `matches` skor yazımı (UPDATE veya INSERT) — kaynak gerçek.
2. `standings` upsert (etkilenen takımlar için tüm `matches`'tan baştan hesaplanır — derived state, idempotent yeniden hesap).
3. `predictions` upsert — yalnızca `currentWeek > totalWeeks - 3` ise (jenerik tetikleme koşulu).
4. `league_settings.current_week` artırımı — sadece Play actions için (Edit Match'te yapılmaz).

#### 4.5.8 HTTP Hata Kodları

| Kod | Durum | Tetikleyici |
| --- | --- | --- |
| 200 OK | Başarılı | |
| 404 Not Found | Var olmayan kaynak | Bilinmeyen `match_id`. |
| 409 Conflict | State machine ihlali, optimistic/idempotency lock fail | Çift tıklama (`expected_week` uyumsuz), eski `expected_version`. |
| 422 Unprocessable Entity | Validation hatası | Invalid score (negatif, >20, non-integer), eksik alan, henüz oynanmamış maç edit denemesi. |
| 423 Locked | Long-running action devam ediyor | Play All Weeks sırasında diğer mutasyon; status='running' veya 'resetting'. |
| 429 Too Many Requests | Rate limit aşıldı | DoS koruması (NFR-17). |

#### 4.5.9 Replay / Partial Failure Davranışı

- **Play All Weeks her hafta için ayrı transaction commit eder**: timeout/crash sonrası tekrar çağrıldığında `current_week`'ten devam edilebilir.
- **İdempotent**: aynı seed + match_id → aynı skor (deterministik replay).
- **RNG seed yönetimi**: `subseed = hash(league_settings.seed, match.id, week)`. Aynı `match_id` ile yeniden simülasyon → aynı skor.
- Bir hafta içindeki herhangi bir maçta exception fırlatılırsa, o haftanın transaction'ı rollback olur ve `current_week` artmaz; istemci aynı `expected_week` ile yeniden deneyebilir.

#### 4.5.10 SQLite Konfigürasyonu

`config/database.php` SQLite connection'da:

- `PRAGMA journal_mode=WAL` — concurrent read + tek writer.
- `PRAGMA foreign_keys=ON` — FK constraint'leri aktif.
- `PRAGMA busy_timeout=5000` — kilit beklemede 5sn'lik retry penceresi.

> Not: SQLite tek-yazıcı modeliyle birlikte `BEGIN IMMEDIATE` + WAL kombinasyonu, bu uygulamanın iş yükü (single-user, düşük throughput) için yeterli concurrency garantisi sağlar.

---

## 5. Features (Üst Seviye Özellik Listesi)

| ID | Feature | Tip | Öncelik |
| --- | --- | --- | --- |
| F-01 | Lig Kurulumu (4 takım, varsayılan power) | Must | Core |
| F-02 | Takım Güç Parametreleri | Must | Core |
| F-03 | Fikstür Üretimi (round-robin, çift devre) | Must | Core |
| F-04 | Maç Simülasyon Motoru (probabilistic) | Must | Core |
| F-05 | Lig Tablosu (PTS/P/W/D/L/GD + sıralama) | Must | Core |
| F-06 | Haftalık Maç Sonuçları Görünümü | Must | Core |
| F-07 | "Play Next Week" Akışı | Must | Core |
| F-08 | Şampiyonluk Tahmini (Monte Carlo) | Must | Core |
| F-09 | Tahmin Toplamı = %100 Doğrulaması | Must | Core |
| F-10 | "Play All Weeks" (Extras) | Should | Plus |
| F-11 | Maç Sonucu Düzenleme (Extras) | Should | Plus |
| F-12 | Reproducibility (seed) | Should | Core |
| F-13 | Public Deploy | Must | Core |
| F-14 | README ve AI kullanım notu | Must | Core |
| F-15 | Unit Test Suite | Must | Core |
| F-16 | Component Pattern (frontend) | Must | Core |
| **F-17** | **Esnek Takım Sayısı (N takım için çalışır mimari)** | **Should** | **Core (NFR)** |
| **F-18** | **Setup Ekranı (Tournament Teams + Fixtures preview)** | **Must** | **Core** |
| **F-19** | **Start Simulation Geçişi** | **Must** | **Core** |
| **F-20** | **Reset Data (kırmızı, yıkıcı eylem)** | **Must** | **Core** |

---

## 6. Epics ve User Stories

> Her story formatı: **ID**, başlık, açıklama, Acceptance Criteria (AC) (Verilen / Ne zaman / O zaman), MoSCoW önceliği, tahmini büyüklük (S/M/L), teknik notlar.

### Epic A — Lig ve Takım Yapılandırması

#### US-A-01 — Lig Kurulumu (Seed Data)
- **Açıklama**: Uygulama ilk açıldığında 4 Premier League takımı (Liverpool, Manchester City, Chelsea, Arsenal) seed olarak yüklü olmalıdır.
- **AC-1**: *Verilen* uygulama temiz veritabanı, *ne zaman* migrate + seed komutları çalıştırılır, *o zaman* `teams` tablosunda 4 takım kaydı bulunur.
- **AC-2**: *Verilen* her takım, *ne zaman* seed çalışır, *o zaman* her takımın power, supporter, keeper alanları sıfırdan büyük varsayılan değerlere sahiptir.
- **Öncelik**: Must
- **Estimate**: S
- **Teknik not**: `database/seeders/TeamSeeder.php`.

#### US-A-02 — Takım Güç Parametreleri
- **Açıklama**: Her takımın `power` (1–100) ve opsiyonel `supporter`, `keeper` faktörleri tanımlı olmalıdır. Bu değerler MatchEngine tarafından kullanılır.
- **AC-1**: *Verilen* bir takım, *ne zaman* engine bir maçta kullanır, *o zaman* `attack` ve `defense` hesabı bu üç parametreyi referans alır.
- **AC-2**: *Verilen* boş veya negatif power, *ne zaman* migration/validation çalışır, *o zaman* hata fırlatılır.
- **Öncelik**: Must
- **Estimate**: S

#### US-A-03 — Reset Data (Lig Sıfırlama)
- **Açıklama**: Kullanıcı, "Reset Data" butonuyla tüm fikstürü, maç sonuçlarını ve geçerli hafta sayacını sıfırlayabilmelidir. Buton **kırmızı** renkte olmalı (yıkıcı eylem işareti) ve sıfırlamanın ardından kullanıcı Setup ekranına dönmelidir.
- **AC-1**: *Verilen* en az 1 hafta oynanmış lig, *ne zaman* kullanıcı "Reset Data" butonuna basar, *o zaman* tüm `matches.home_score` ve `matches.away_score` `null` olur, `current_week = 0`, fikstür temizlenir.
- **AC-2**: *Verilen* sıfırlama işlemi tamamlanır, *ne zaman* UI güncellenir, *o zaman* kullanıcı **Setup ekranına döner** (Simulation ekranı kapanır).
- **AC-3**: *Verilen* Reset Data butonu, *ne zaman* render edilir, *o zaman* **kırmızı renkte** (örn. `#dc2626` veya benzeri danger token) görünür ve "destructive" etiketi/tooltip taşır.
- **AC-4**: *Verilen* kullanıcı yanlışlıkla basabilir, *ne zaman* tıklanır, *o zaman* isteğe bağlı bir confirmation diyaloğu gösterilir (developer kararı).
- **Öncelik**: **Must** (video gereği)
- **Estimate**: S

#### US-A-04 — Tournament Teams Görünümü
- **Açıklama**: Setup ekranında "Tournament Teams" başlığı altında 4 (veya N) takım listelenmeli, "Generate Fixtures" butonu görünür olmalıdır.
- **AC-1**: *Verilen* henüz fikstür üretilmemiş, *ne zaman* kullanıcı uygulamayı açar, *o zaman* Setup ekranında "Tournament Teams" başlığı ve takım isimleri (Liverpool, Manchester City, Chelsea, Arsenal) listelenir.
- **AC-2**: *Verilen* Tournament Teams listelenir, *ne zaman* render tamamlanır, *o zaman* "Generate Fixtures" butonu aktif olarak görünür.
- **AC-3**: *Verilen* fikstür henüz üretilmemiş, *ne zaman* kullanıcı simulation ekranına manuel gitmeye çalışır, *o zaman* "Start Simulation" butonu inaktif veya gizli olur.
- **Öncelik**: Must
- **Estimate**: S

#### US-A-05 — Lig State Machine ve HTTP 423
- **Açıklama**: Lig `status` enum ile durumunu yönetir (`idle | running | resetting | finished`). Long-running action devam ederken gelen diğer mutasyon istekleri **HTTP 423 Locked** ile reddedilir.
- **AC-1**: *Verilen* `status='running'`, *ne zaman* `POST /api/league/play-next-week` veya `POST /api/league/reset` veya `PATCH /api/matches/{id}` gelirse, *o zaman* **423 Locked** döner.
- **AC-2**: *Verilen* `status='resetting'`, *ne zaman* herhangi mutasyon gelirse, *o zaman* **423 Locked** döner.
- **AC-3**: *Verilen* Play All Weeks normal akışta tamamlanır, *ne zaman* son hafta commit edilir, *o zaman* `status` `finished` (sezon bitti) veya `idle` (sezon devam) olur.
- **AC-4**: *Verilen* `status='finished'`, *ne zaman* Reset Data gelirse, *o zaman* izinli (status `resetting`'e geçer).
- **Öncelik**: Must
- **Estimate**: S
- **Teknik not**: Geçişler §4.5.2'de tanımlı.

#### US-A-06 — Frontend Mutation Lock
- **Açıklama**: Pinia `leagueStore` içinde global `isMutating` flag; mutasyon in-flight iken kullanıcı çift tıklayamaz.
- **AC-1**: *Verilen* herhangi bir mutasyon API çağrısı, *ne zaman* başlar, *o zaman* `isMutating = true`.
- **AC-2**: *Verilen* mutasyon API çağrısı, *ne zaman* yanıt döner (success **veya** error), *o zaman* `isMutating = false`.
- **AC-3**: *Verilen* `isMutating === true`, *ne zaman* UI render edilir, *o zaman* Play Next Week, Play All Weeks, Reset Data, Edit Match (Save) butonları `disabled` olur.
- **AC-4**: *Verilen* backend `409 / 422 / 423 / 429` döner, *ne zaman* yanıt yakalanır, *o zaman* kullanıcıya açıklayıcı toast/alert gösterilir.
- **AC-5**: *Verilen* read-only paneller (LeagueTable, WeekResults, ChampionshipPrediction), *ne zaman* mutasyon devam ederken render edilir, *o zaman* **disabled olmaz**.
- **Öncelik**: Must
- **Estimate**: S

### Epic B — Fikstür Üretimi

#### US-B-01 — Çift Devreli Round-Robin Algoritması
- **Açıklama**: 4 takım için 6 haftalık, her takımın diğer 3 takımla biri evinde biri deplasmanda olmak üzere 2 kez oynadığı (toplam 12 maç) fikstür üretilmelidir.
- **AC-1**: *Verilen* 4 takım, *ne zaman* `FixtureGenerator::generate(teams)` çağrılır, *o zaman* `count(matches) == 12` ve `count(weeks) == 6`.
- **AC-2**: *Verilen* herhangi iki takım, *ne zaman* tüm fikstür tarandığında, *o zaman* aralarında tam **2** maç vardır (biri ev, biri deplasman).
- **AC-3**: *Verilen* tek bir hafta, *ne zaman* maçlar listelenir, *o zaman* her takım bu haftada tam 1 maç oynar (eksik veya çift maç olmaz).
- **AC-4**: *Verilen* video örneği, *ne zaman* generator çalışır, *o zaman* Hafta 1 için Arsenal-Liverpool ve Manchester City-Chelsea benzeri bir eşleşme (anchor team + rotation) üretilebilir.
- **Öncelik**: Must
- **Estimate**: M
- **Teknik not**: Circle method (anchor team + rotation) önerilir; tek sayı takım için "bye" team eklenir.

#### US-B-02 — Reproducibility (seed)
- **Açıklama**: Geliştirici testlerde aynı seed ile aynı fikstürü ve aynı maç sonuçlarını üretebilmelidir.
- **AC-1**: *Verilen* sabit seed = 42, *ne zaman* `FixtureGenerator::generate(teams, seed=42)` iki kez çağrılır, *o zaman* iki çıktı **birebir aynı**dır.
- **AC-2**: *Verilen* sabit seed = 42, *ne zaman* `MatchEngine::simulate(home, away, seed=42)` iki kez çağrılır, *o zaman* iki skor aynıdır.
- **Öncelik**: Should
- **Estimate**: S
- **Teknik not**: `App\Domain\Engine\SeededRandom` (LCG veya `mt_srand($seed) + mt_rand`).

#### US-B-03 — Esnek Takım Sayısı (Flexibility)
- **Açıklama**: FixtureGenerator algoritması, takım sayısı değişse bile bozulmadan çalışmalıdır. 4 sadece varsayılandır; mimari N≥2 (tercihen çift) için jenerik olmalıdır.
- **AC-1**: *Verilen* 6 takım, *ne zaman* `FixtureGenerator::generate(teams)` çağrılır, *o zaman* maç sayısı 30 ve hafta sayısı 10 olur (formül: N×(N−1) / 2×(N−1)).
- **AC-2**: *Verilen* 8 takım, *ne zaman* generator çalışır, *o zaman* maç sayısı 56, hafta sayısı 14 olur.
- **AC-3**: *Verilen* takım sayısı kod tabanında, *ne zaman* aranır, *o zaman* hiçbir yerde `4` olarak **hard-code edilmemiş** olmalıdır (config veya parametre).
- **AC-4**: *Verilen* tek sayı takım, *ne zaman* generator çağrılır, *o zaman* algoritma "bye team" ekler ve her haftada bir takım maç oynamaz (developer ufak ek, opsiyonel).
- **Öncelik**: **Should**
- **Estimate**: M
- **Teknik not**: PHPUnit'te hem 4 hem 6 takım için test koşulmalıdır.

#### US-B-04 — Generate Fixtures Akışı
- **Açıklama**: Kullanıcı, Setup ekranında "Generate Fixtures" butonuna tıkladığında fikstür otomatik üretilir ve 6 haftalık program tablosu setup ekranında gösterilir.
- **AC-1**: *Verilen* Tournament Teams listesi görünür, *ne zaman* kullanıcı "Generate Fixtures" butonuna tıklar, *o zaman* `POST /api/league/generate-fixtures` çağrılır.
- **AC-2**: *Verilen* API başarılı dönüş, *ne zaman* yanıt alınır, *o zaman* Setup ekranında "Fixtures" başlığı altında 6 haftalık tablo (Week 1..6, her hafta için 2 maç) render edilir.
- **AC-3**: *Verilen* fikstür önceden vardı, *ne zaman* "Generate Fixtures" yeniden tıklanır, *o zaman* eski fikstür silinir ve yenisi üretilir (idempotent değil).
- **AC-4**: *Verilen* fikstür gösterimi, *ne zaman* render edilir, *o zaman* Hafta 1 ve Hafta 4 (örnek video) gibi eşleşmeler okunabilir formatta gösterilir.
- **Öncelik**: Must
- **Estimate**: S

#### US-B-05 — Start Simulation Geçişi
- **Açıklama**: Fikstür hazır olduğunda "Start Simulation" butonu aktif olur ve kullanıcıyı Simulation ekranına yönlendirir.
- **AC-1**: *Verilen* fikstür üretildi, *ne zaman* fixtures görüntülenir, *o zaman* "Start Simulation" butonu **aktif** olur.
- **AC-2**: *Verilen* fikstür henüz üretilmedi, *ne zaman* setup ekranı render edilir, *o zaman* "Start Simulation" butonu **inaktif/gizli**dir.
- **AC-3**: *Verilen* kullanıcı "Start Simulation" butonuna basar, *ne zaman* tıklama gerçekleşir, *o zaman* Simulation ekranı (3 panel görünümü) açılır ve `currentWeek = 0` ile başlar.
- **Öncelik**: Must
- **Estimate**: S

### Epic C — Maç Simülasyon Motoru

#### US-C-01 — Olasılıksal Skor Üretimi
- **Açıklama**: MatchEngine, takım gücüne dayalı ama olasılıksal bir skor üretir. Aynı eşleşme her seferinde aynı sonucu vermez.
- **AC-1**: *Verilen* aynı iki takım (eşit güç), *ne zaman* engine 1000 kez çağrılır (sabit seed olmadan), *o zaman* en az 3 farklı skor sonucu görülür.
- **AC-2**: *Verilen* skor üretimi, *ne zaman* test edilir, *o zaman* `home_score >= 0` ve `away_score >= 0` (negatif skor yok).
- **Öncelik**: Must
- **Estimate**: M

#### US-C-02 — Home/Away Etkisi
- **Açıklama**: Ev sahibi takım için belirli bir bonus uygulanır (home advantage).
- **AC-1**: *Verilen* aynı güç, *ne zaman* takım A 10.000 kez ev sahibi olarak takım B ile oynatılır, *o zaman* A'nın galibiyet yüzdesi B'nin galibiyet yüzdesinden anlamlı şekilde yüksek olur.
- **AC-2**: *Verilen* takım ev veya deplasman, *ne zaman* engine `simulate(...)` çağrılır, *o zaman* `home_advantage` faktörü hesaba katılır.
- **Öncelik**: Must
- **Estimate**: S

#### US-C-03 — Supporter / Keeper Faktörleri
- **Açıklama**: Engine; supporter (atak) ve keeper (savunma) parametrelerini hesaba katar.
- **AC-1**: *Verilen* yüksek keeper'lı bir takım, *ne zaman* engine çalışır, *o zaman* rakip skorunun beklenen değeri düşer.
- **AC-2**: *Verilen* yüksek supporter'lı bir takım, *ne zaman* ev sahibiyse, *o zaman* atak λ'sı artar.
- **Öncelik**: Should
- **Estimate**: S

#### US-C-04 — Seed Kabulü
- **Açıklama**: Engine, opsiyonel seed parametresi alır ve aynı seedde aynı sonucu üretir (US-B-02 ile birlikte).
- **AC-1**: *Verilen* seed=42, *ne zaman* engine iki kez çağrılır, *o zaman* aynı skor üretilir.
- **Öncelik**: Should
- **Estimate**: S

#### US-C-05 — Sürpriz / Gerçekçilik (FAQ Q5)
- **Açıklama**: Zayıf takım da sıfır olmayan bir olasılıkla kazanabilmelidir.
- **AC-1**: *Verilen* takım A power=100, takım B power=10, *ne zaman* engine 10.000 kez çağrılır, *o zaman* B'nin galibiyet sayısı **> 0**'dır (yüzde küçük olabilir).
- **AC-2**: *Verilen* aynı senaryo, *ne zaman* test koşar, *o zaman* B'nin galibiyet yüzdesi A'nınkinden anlamlı şekilde **düşük**tür (gerçekçilik).
- **Öncelik**: Must
- **Estimate**: M
- **Teknik not**: Bu story FAQ Q5'in birebir doğrulamasıdır.

### Epic D — Lig Tablosu / Standings

#### US-D-01 — Premier League Puanlama (3/1/0)
- **Açıklama**: Galibiyet 3 puan, beraberlik 1 puan, mağlubiyet 0 puan (FAQ Q1).
- **AC-1**: *Verilen* 1 maç oynandı (galibiyet/beraberlik/mağlubiyet), *ne zaman* tablo hesaplanır, *o zaman* takım PTS'i sırasıyla 3 / 1 / 0 artar.
- **AC-2**: *Verilen* W+D+L sayıları, *ne zaman* tablo render edilir, *o zaman* P = W + D + L (invariant).
- **Öncelik**: Must
- **Estimate**: S

#### US-D-02 — Tablo Sütunları
- **Açıklama**: Lig tablosu sütunları: **Team, PTS, P, W, D, L, GD** olmalıdır.
- **AC-1**: *Verilen* lig tablosu render edilir, *ne zaman* header satırı incelenir, *o zaman* tüm 7 sütun (Team, PTS, P, W, D, L, GD) gösterilir.
- **AC-2**: *Verilen* GD, *ne zaman* hesaplanır, *o zaman* `GD = GF − GA` invariant'i sağlanır.
- **Öncelik**: Must
- **Estimate**: S
- **Not**: Video özetinde PTS açıkça yazılmamış olabilir ancak Brief figürlerinde mevcuttur ve otoriter alınmıştır.

#### US-D-03 — Sıralama (Tiebreak)
- **Açıklama**: Sıralama **`PTS desc → GD desc → GF desc → team_name asc`** zinciriyle yapılır (sabit kural, H2H kapsam dışı). Bkz. §4.4.
- **AC-1**: *Verilen* iki takımın PTS'i eşit, *ne zaman* sıralama hesaplanır, *o zaman* yüksek GD'li takım üstte yer alır.
- **AC-2**: *Verilen* PTS ve GD eşit, *ne zaman* sıralama hesaplanır, *o zaman* yüksek GF'li takım üstte yer alır.
- **AC-3**: *Verilen* 3 takımın PTS+GD'si eşit (GF farklı), *ne zaman* sıralama hesaplanır, *o zaman* GF değerine göre azalan sırada listelenirler.
- **AC-4**: *Verilen* PTS+GD+GF tamamen eşit (örn. tüm istatistikler 0'da başlangıç durumu), *ne zaman* sıralama hesaplanır, *o zaman* takım adı alfabetik artan sıralı (deterministik, sabit kural).
- **AC-5**: *Verilen* `standings` tablosu, *ne zaman* `idx_standings_sort` index'i kontrol edilir, *o zaman* `(points DESC, goal_diff DESC, goals_for DESC, team_id ASC)` sırası birebir mevcuttur.
- **Öncelik**: Must
- **Estimate**: S

### Epic E — Şampiyonluk Tahmini

#### US-E-01 — Tahmin Tetikleme Koşulu (N takım için jenerik)
- **Açıklama**: Tahminler **`currentWeek > totalWeeks - 3`** (yani kalan hafta ≤ 3) olduğunda hesaplanır ve gösterilir. Tek doğru formül budur; **hard-coded `week >= 4` kullanılmaz**. 4 takım için `week >= 4`, 6 takım için `week >= 8`, 8 takım için `week >= 12` eşdeğer sonuçlanır.
- **AC-1**: *Verilen* `currentWeek <= totalWeeks - 3`, *ne zaman* prediction paneli render edilir, *o zaman* her takım için "—" (placeholder) gösterilir (UX kararı, OQ-09).
- **AC-2**: *Verilen* `currentWeek > totalWeeks - 3`, *ne zaman* panel render edilir, *o zaman* her takım için bir yüzde gösterilir.
- **AC-3**: *Verilen* `totalWeeks = 2*(N-1)` ve `currentWeek > totalWeeks - 3`, *ne zaman* panel render edilir, *o zaman* her takım için yüzde gösterilir (formül N'den bağımsızdır).
- **AC-4**: *Verilen* kod tabanı, *ne zaman* aranır, *o zaman* hiçbir yerde `>= 4` veya `> 3` gibi hard-coded hafta sayıları **yoktur**; yalnızca `currentWeek > totalWeeks - 3` formülü kullanılır.
- **Öncelik**: Must
- **Estimate**: S

#### US-E-02 — Toplam = %100 Doğrulaması (largest remainder)
- **Açıklama**: Tüm takımların tahmin yüzdelerinin toplamı **tam 100** olmalıdır. Yuvarlama paradoksunu önlemek için **largest remainder method** kullanılır.
- **AC-1**: *Verilen* tahminler hesaplandı ve largest remainder uygulandı, *ne zaman* toplama yapılır, *o zaman* `sum(predictions) == 100` (**tam değer, tolerans yok**).
- **AC-2**: *Verilen* her takımın yüzdesi, *ne zaman* görüntülenir, *o zaman* `[0, 100]` aralığındadır ve `decimal(5,2)` precision'a sahiptir (örn. `33.33`).
- **AC-3**: *Verilen* Monte Carlo ham sonuçları (kayan nokta), *ne zaman* yuvarlama uygulanır, *o zaman* en büyük artığa (remainder) sahip takımlara +0.01 dağıtılarak toplam tam 100 olur.
- **Öncelik**: Must
- **Estimate**: S

#### US-E-03 — FAQ Örnek A (Yakalanamaz Lider)
- **Açıklama**: FAQ'nin Örnek A'sı: 2 hafta kala lider, ikinciden +9 puan önde → tahmin **100 / 0 / 0 / 0** olmalıdır.
- **AC-1**: *Verilen* FAQ Örnek A senaryosu, *ne zaman* prediction service çalışır, *o zaman* lider için yüzde = 100, diğerleri = 0.
- **Öncelik**: Must (kritik test)
- **Estimate**: M

#### US-E-04 — FAQ Örnek B (Son Hafta Eşitlik)
- **Açıklama**: FAQ'nin Örnek B'si: 1 hafta kala iki takım eşit puanda ve birbirleriyle oynayacak → tahmin **50 / 50** veya **65 / 35** olabilir (developer kararı).
- **AC-1**: *Verilen* FAQ Örnek B senaryosu, *ne zaman* prediction service çalışır, *o zaman* iki lider takımın toplam yüzdesi ≥ 95.
- **AC-2**: *Verilen* aynı senaryo, *ne zaman* hesaplanır, *o zaman* ev avantajına sahip takımın yüzdesi biraz daha yüksek olabilir.
- **Öncelik**: Must (kritik test)
- **Estimate**: M

#### US-E-05 — Brief Figürleri Uyum
- **Açıklama**: Brief'teki figürlerle uyumlu davranış: Week 4 tahmin (45/25/25/5), Week 5 tahmin (60/20/15/5).
- **AC-1**: *Verilen* Brief'teki Week 4 tablo, *ne zaman* aynı state engine'e verilir, *o zaman* sonuç ±10 puan toleransla benzer dağılım gösterir.
- **Öncelik**: Should
- **Estimate**: M

#### US-E-06 — Algoritma Seçimi
- **Açıklama**: Tahmin algoritması Monte Carlo (N=10.000) önerilir. Strategy pattern ile alternatif (closed-form, heuristic) takılabilmelidir.
- **AC-1**: *Verilen* `PredictionStrategy` arayüzü, *ne zaman* `MonteCarloStrategy` enjekte edilir, *o zaman* test ortamında alternatif strategy ile değiştirilebilir.
- **Öncelik**: Should
- **Estimate**: M

### Epic F — Play All Weeks (Extras)

#### US-F-01 — "Play All Weeks" Butonu
- **Açıklama**: Kullanıcı "Play All Weeks" butonuyla kalan tüm haftaları tek seferde simüle edebilmelidir.
- **AC-1**: *Verilen* currentWeek=0 ve fikstür hazır, *ne zaman* kullanıcı "Play All Weeks" basar, *o zaman* tüm 6 hafta oynanır ve `current_week = 6` olur.
- **AC-2**: *Verilen* tüm haftalar oynandı, *ne zaman* tablo render edilir, *o zaman* her takımın P = 6 (4 takım, 6 hafta varsayımı).
- **Öncelik**: Should (Extras)
- **Estimate**: S

#### US-F-02 — Haftalık Döküm
- **Açıklama**: "Play All Weeks" sonrası, kullanıcı orta panelde her haftanın sonuçlarını ayrı ayrı görüntüleyebilmelidir (örn. son hafta görünür).
- **AC-1**: *Verilen* "Play All Weeks" sonrası, *ne zaman* simulation ekranı render edilir, *o zaman* orta panel "Week 6" sonuçlarını gösterir.
- **Öncelik**: Should
- **Estimate**: S

#### US-F-03 — Play All Weeks Resume / Per-Week Transaction
- **Açıklama**: Play All Weeks **her hafta için ayrı transaction** commit eder. Server timeout veya crash sonrası tekrar çağrıldığında `current_week`'ten devam edilir (idempotent — aynı seed deterministik replay sağlar).
- **AC-1**: *Verilen* Play All Weeks başladı, *ne zaman* her hafta commit edilir, *o zaman* o haftanın değişiklikleri kalıcılaşır (bir sonraki hafta crash etse bile).
- **AC-2**: *Verilen* Play All Weeks sırasında server timeout/crash, *ne zaman* istemci tekrar çağırır, *o zaman* `current_week + 1`'den devam edilir; daha önce commit edilmiş haftalar tekrar simüle edilmez.
- **AC-3**: *Verilen* aynı `league_settings.seed` + `match.id`, *ne zaman* replay edilir, *o zaman* **aynı skor** üretilir (deterministik; `subseed = hash(seed, match.id, week)`).
- **AC-4**: *Verilen* bir hafta içinde exception fırlatılır, *ne zaman* transaction rollback olur, *o zaman* `current_week` artmaz ve standings/predictions o haftanın hesaplamasını içermez.
- **Öncelik**: Should
- **Estimate**: M

### Epic G — Maç Sonucu Düzenleme (Extras)

#### US-G-01 — Edit Match Modal
- **Açıklama**: Kullanıcı, oynanmış bir maçın skorunu manuel olarak düzenleyebilmelidir.
- **AC-1**: *Verilen* skoru olan bir maç, *ne zaman* kullanıcı tıklar, *o zaman* `EditMatchModal` açılır ve mevcut skor görünür.
- **AC-2**: *Verilen* modal açık, *ne zaman* kullanıcı yeni skoru kaydeder, *o zaman* `PATCH /api/matches/{id}` çağrılır ve maç güncellenir.
- **Öncelik**: Should (Extras)
- **Estimate**: M

#### US-G-02 — Yeniden Hesaplama
- **Açıklama**: Skor değişiminden sonra lig tablosu ve tahminler yeniden hesaplanmalıdır.
- **AC-1**: *Verilen* maç skoru güncellendi, *ne zaman* yanıt frontend'e döner, *o zaman* sol panel (tablo) ve sağ panel (tahmin) güncellenir.
- **Öncelik**: Should
- **Estimate**: S

#### US-G-03 — Edit Match Atomicity ve Optimistic Lock
- **Açıklama**: Edit Match operasyonu **tek transaction** içinde çalışır ve `version` sütunu ile optimistic lock korunur (bkz. §4.5.4).
- **AC-1**: *Verilen* `PATCH /api/matches/{id}` çağrısı, *ne zaman* body `expected_version` içermiyorsa, *o zaman* **422 Unprocessable Entity** döner.
- **AC-2**: *Verilen* `expected_version` DB'deki `version` ile uyumsuz, *ne zaman* update denenir, *o zaman* affected rows = 0 ve **409 Conflict** döner.
- **AC-3**: *Verilen* başarılı edit, *ne zaman* tek bir DB transaction içinde çalışır, *o zaman* `matches` update + `standings` recompute + `predictions` recompute (kapsam dahilinde) atomic'tir.
- **AC-4**: *Verilen* `played_at IS NULL` olan bir maç (henüz oynanmamış), *ne zaman* edit denenir, *o zaman* **422 Unprocessable Entity** döner (oynanmamış maç edit edilemez).
- **AC-5**: *Verilen* `home_score` veya `away_score` integer değil veya `0..20` aralığı dışında, *ne zaman* request gelir, *o zaman* **422** döner.
- **AC-6**: *Verilen* var olmayan `match_id`, *ne zaman* request gelir, *o zaman* **404 Not Found** döner.
- **AC-7**: *Verilen* başarılı edit, *ne zaman* tamamlanır, *o zaman* `version = version + 1` ve `editions_count = editions_count + 1` olur; structured log atılır (NFR-20).
- **Öncelik**: Must
- **Estimate**: M

### Epic H — Kalite, Test ve Dağıtım

#### US-H-01 — OOP İlkesine Uyum
- **Açıklama**: Tüm domain mantığı OOP ile yazılmalı; procedural kod kullanılmamalıdır.
- **AC-1**: *Verilen* domain kodu, *ne zaman* incelenir, *o zaman* MatchEngine, FixtureGenerator, LeagueStanding birer sınıftır.
- **AC-2**: *Verilen* SOLID, *ne zaman* uygulanır, *o zaman* SRP gözetilmiştir (engine yalnızca skor üretir, repository persistansa odaklıdır).
- **Öncelik**: Must
- **Estimate**: M

#### US-H-02 — Unit Test Suite (FAQ A & B kritik)
- **Açıklama**: PHPUnit ile en azından domain katmanı test edilmelidir. FAQ Örnek A ve B kritik test olarak işaretlenir.
- **AC-1**: *Verilen* test suite, *ne zaman* `vendor/bin/phpunit` çalıştırılır, *o zaman* MatchEngine, FixtureGenerator, LeagueStanding, Prediction testleri başarılı geçer.
- **AC-2**: *Verilen* FAQ Örnek A, *ne zaman* PredictorTest çalışır, *o zaman* `test_faq_example_a_predicts_100_for_uncatchable_leader` testi yeşildir.
- **AC-3**: *Verilen* FAQ Örnek B, *ne zaman* PredictorTest çalışır, *o zaman* `test_faq_example_b_predicts_balanced_split` testi yeşildir.
- **AC-4**: *Verilen* coverage, *ne zaman* ölçülür, *o zaman* domain katmanında en az **%80**.
- **Öncelik**: Must
- **Estimate**: M

#### US-H-03 — Component Pattern (frontend)
- **Açıklama**: Frontend, component pattern uygulamalıdır (Vue SFC veya native Web Component).
- **AC-1**: *Verilen* frontend kodu, *ne zaman* incelenir, *o zaman* `SetupScreen`, `SimulationScreen`, `LeagueTable`, `WeekResults`, `ChampionshipPrediction`, `Controls`, `EditMatchModal` ayrı dosyalar olarak vardır.
- **AC-2**: *Verilen* state, *ne zaman* okunur/yazılır, *o zaman* tek bir store (Pinia veya basit reactive store) üstünden yapılır.
- **Öncelik**: Must
- **Estimate**: M

#### US-H-04 — Public Deploy
- **Açıklama**: Uygulama herkesin erişebileceği bir public URL'de yayında olmalıdır.
- **AC-1**: *Verilen* deploy tamamlandı, *ne zaman* URL açılır, *o zaman* uygulama sorunsuz yüklenir.
- **AC-2**: *Verilen* uygulama, *ne zaman* incognito tarayıcıda açılır, *o zaman* ek auth gerektirmez.
- **Öncelik**: Must
- **Estimate**: M

#### US-H-05 — README ve AI Kullanım Notu
- **Açıklama**: README; kurulum, çalıştırma, test, deploy adımları ve AI kullanımı notunu içermelidir.
- **AC-1**: *Verilen* README, *ne zaman* okunur, *o zaman* "Setup", "Run", "Test", "Deploy", "AI Usage" başlıkları vardır.
- **AC-2**: *Verilen* AI yardımıyla yazılan dosyalar, *ne zaman* commit edilir, *o zaman* commit mesajında `(AI-assisted)` notu bulunur.
- **Öncelik**: Must
- **Estimate**: S

#### US-H-06 — Concurrency Test Suite
- **Açıklama**: PHPUnit ile paralel istek, çift tıklama ve partial failure senaryolarının doğrulanması.
- **AC-1**: *Verilen* `test_concurrent_play_next_week_returns_409`, *ne zaman* aynı `expected_week` ile iki paralel istek gönderilir, *o zaman* biri 200, diğeri **409** döner.
- **AC-2**: *Verilen* `test_concurrent_patch_match_returns_409`, *ne zaman* aynı `expected_version` ile iki paralel PATCH gönderilir, *o zaman* biri 200, diğeri **409** döner.
- **AC-3**: *Verilen* `test_transaction_rollback_on_failure`, *ne zaman* PlayWeek sırasında ikinci maçta exception fırlatılır, *o zaman* `current_week` artmaz, standings/predictions o haftaya ait güncelleme içermez (rollback doğrulanır).
- **AC-4**: *Verilen* `test_state_machine_blocks_during_running`, *ne zaman* `status='running'` iken reset denenir, *o zaman* **423 Locked** döner.
- **Öncelik**: Should
- **Estimate**: M

#### US-H-07 — CI Pipeline
- **Açıklama**: `.github/workflows/ci.yml` mevcuttur; her push ve PR'da otomatik koşar.
- **AC-1**: *Verilen* PR açılır veya main'e push gelir, *ne zaman* GitHub Actions tetiklenir, *o zaman* `backend` (PHPUnit), `frontend` (Vitest + build), `e2e` (Playwright smoke) job'ları çalışır.
- **AC-2**: *Verilen* backend ve frontend job'ları, *ne zaman* koşar, *o zaman* paralel çalışır (`needs:` yalnızca e2e için).
- **AC-3**: *Verilen* en az 1 happy-path E2E (Playwright), *ne zaman* koşar, *o zaman* Setup → Generate → Start → Play All → Reset akışı yeşil geçer.
- **Öncelik**: Should
- **Estimate**: S

#### US-H-08 — Production Hardening
- **Açıklama**: Production ortamında stack trace sızıntısı engellenir, secret'lar VCS'de değildir.
- **AC-1**: *Verilen* production env, *ne zaman* `.env` okunur, *o zaman* `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning` ayarlıdır.
- **AC-2**: *Verilen* repo, *ne zaman* `.gitignore` okunur, *o zaman* `.env` ignore listesindedir; `.env.example` repo'da mevcuttur.
- **AC-3**: *Verilen* README, *ne zaman* okunur, *o zaman* `cp .env.example .env && php artisan key:generate` adımı belgelidir.
- **AC-4**: *Verilen* production 5xx yanıtı, *ne zaman* incelenir, *o zaman* stack trace içermez (Laravel default davranışı `APP_DEBUG=false` ile).
- **Öncelik**: Must
- **Estimate**: S

#### US-H-09 — Rate Limiting
- **Açıklama**: Anonim mutasyonel endpoint'ler için IP-başına rate limit ile DoS koruması.
- **AC-1**: *Verilen* `POST /api/league/reset`, *ne zaman* aynı IP'den dakikada 4. istek gelir, *o zaman* **429 Too Many Requests** döner.
- **AC-2**: *Verilen* `POST /api/league/play-all-weeks`, *ne zaman* aynı IP'den dakikada 11. istek gelir, *o zaman* **429** döner.
- **AC-3**: *Verilen* `POST /api/league/generate-fixtures`, *ne zaman* aynı IP'den dakikada 11. istek gelir, *o zaman* **429** döner.
- **AC-4**: *Verilen* Laravel `throttle:N,1` middleware, *ne zaman* `routes/api.php` incelenir, *o zaman* yukarıdaki endpoint'lere uygulanmıştır.
- **Öncelik**: Must
- **Estimate**: S

---

## 7. Detaylı Kabul Kriterleri Tablosu

| Story | AC | Verilen | Ne zaman | O zaman |
| --- | --- | --- | --- | --- |
| US-A-01 | 1 | Temiz DB | Migrate + seed | 4 takım yüklenir |
| US-A-01 | 2 | Seed çalıştı | Takımlar | power/supporter/keeper > 0 |
| US-A-02 | 1 | Bir takım | Engine maç oynar | attack/defense bu paramları kullanır |
| US-A-03 | 1 | Oynanmış lig | Reset Data tıklanır | Tüm skorlar null, week=0 |
| US-A-03 | 2 | Reset tamamlandı | UI yenilenir | Setup ekranına dönülür |
| US-A-03 | 3 | Reset butonu | Render edilir | Kırmızı renkte görünür |
| US-A-04 | 1 | Fikstür yok | Uygulama açılır | Tournament Teams listesi gösterilir |
| US-A-04 | 2 | Liste hazır | Render tamamlanır | Generate Fixtures butonu aktif |
| US-A-04 | 3 | Fikstür yok | Simulation'a gidilmeye çalışılır | Start Simulation inaktif |
| US-B-01 | 1 | 4 takım | generate() | 12 maç, 6 hafta |
| US-B-01 | 2 | Herhangi 2 takım | Tüm fikstür | 2 maç (ev+deplasman) |
| US-B-01 | 3 | Tek hafta | Maçlar listelenir | Her takım tam 1 maç |
| US-B-02 | 1 | seed=42 | generate iki kez | Aynı fikstür |
| US-B-03 | 1 | 6 takım | generate() | 30 maç, 10 hafta |
| US-B-03 | 2 | 8 takım | generate() | 56 maç, 14 hafta |
| US-B-03 | 3 | Kod araması | Aranır | 4 hard-code yok |
| US-B-04 | 1 | Setup ekranı | Generate Fixtures tıklanır | API çağrılır |
| US-B-04 | 2 | API başarılı | Yanıt alınır | 6 haftalık tablo render |
| US-B-04 | 3 | Eski fikstür var | Generate tekrar | Silinip yeniden üretilir |
| US-B-05 | 1 | Fikstür hazır | Setup render | Start Simulation aktif |
| US-B-05 | 2 | Fikstür yok | Setup render | Start Simulation inaktif |
| US-B-05 | 3 | Buton tıklanır | Geçiş yapılır | Simulation ekranı açılır |
| US-C-01 | 1 | Eşit güç takımlar | 1000 simülasyon | ≥3 farklı skor |
| US-C-02 | 1 | Aynı güç, A ev | 10.000 simülasyon | A galibiyet > B galibiyet |
| US-C-05 | 1 | A=100, B=10 | 10.000 simülasyon | B galibiyet > 0 |
| US-D-01 | 1 | 1 maç oynandı | Tablo hesap | PTS 3/1/0 sırasıyla |
| US-D-02 | 1 | Tablo render | Header | Team/PTS/P/W/D/L/GD |
| US-D-03 | 1 | PTS eşit | Sıralama | Yüksek GD üstte |
| US-E-01 | 1 | week < 4 | Panel render | Boş veya "—" |
| US-E-02 | 1 | Tahminler hazır | Toplama | sum = 100 (±1) |
| US-E-03 | 1 | FAQ Örnek A | Predictor | Lider 100, diğerleri 0 |
| US-E-04 | 1 | FAQ Örnek B | Predictor | İki lider toplamı ≥ 95 |
| US-F-01 | 1 | currentWeek=0 | Play All Weeks | current_week=6 |
| US-G-01 | 1 | Oynanmış maç | Tıklanır | EditMatchModal açılır |
| US-G-02 | 1 | Skor güncellendi | Yanıt döner | Tablo + tahmin güncellenir |
| US-H-02 | 2 | FAQ A testi | PHPUnit | Yeşil |
| US-H-02 | 3 | FAQ B testi | PHPUnit | Yeşil |
| US-H-04 | 1 | Deploy hazır | URL açılır | Uygulama yüklenir |
| US-A-05 | 1 | status='running' | Mutasyon gelir | 423 Locked |
| US-A-05 | 2 | status='resetting' | Mutasyon gelir | 423 Locked |
| US-A-05 | 3 | Play All Weeks biter | Son hafta commit | status=idle veya finished |
| US-A-06 | 1 | Mutasyon başlar | isMutating set | true |
| US-A-06 | 3 | isMutating=true | Butonlar render | disabled |
| US-A-06 | 4 | 409/422/423/429 | Yanıt yakalanır | Toast gösterilir |
| US-D-03 | 3 | 3 takım PTS+GD eşit | Sıralama | GF desc |
| US-D-03 | 4 | Tam eşitlik | Sıralama | team_name asc |
| US-E-01 | 3 | totalWeeks=2(N-1) | currentWeek > totalWeeks-3 | Yüzdeler render |
| US-E-01 | 4 | Kod araması | "week >= 4" aranır | Hard-code yok |
| US-E-02 | 1 | Largest remainder uygulandı | Toplama | sum == 100 (tam) |
| US-E-02 | 2 | Yüzdeler render | Görüntüleme | decimal(5,2), [0,100] |
| US-F-03 | 2 | Crash / timeout | Tekrar çağrı | current_week'ten devam |
| US-F-03 | 3 | Aynı seed + match_id | Replay | Aynı skor |
| US-G-03 | 1 | expected_version yok | PATCH gelir | 422 |
| US-G-03 | 2 | expected_version uyumsuz | Update | 409 |
| US-G-03 | 4 | played_at IS NULL | Edit denenir | 422 |
| US-G-03 | 5 | Score < 0 veya > 20 | PATCH | 422 |
| US-G-03 | 6 | Bilinmeyen match_id | PATCH | 404 |
| US-H-06 | 1 | İki paralel play-next | Aynı expected_week | Biri 200, biri 409 |
| US-H-06 | 2 | İki paralel PATCH | Aynı expected_version | Biri 200, biri 409 |
| US-H-06 | 3 | İkinci maçta exception | Transaction | Rollback, current_week artmaz |
| US-H-07 | 1 | PR açılır | CI tetiklenir | backend+frontend+e2e yeşil |
| US-H-08 | 1 | Production env | .env okunur | APP_DEBUG=false |
| US-H-08 | 4 | Production 5xx | Yanıt incelenir | Stack trace yok |
| US-H-09 | 1 | Reset endpoint | 4. istek/dk | 429 |
| US-H-09 | 2 | Play-all endpoint | 11. istek/dk | 429 |

---

## 8. Non-Functional Requirements (NFR)

| NFR ID | Kategori | Gereksinim |
| --- | --- | --- |
| NFR-01 | Performans | "Play Next Week" yanıt süresi ≤ 500ms (4 takım için). |
| NFR-02 | Performans | Monte Carlo N=10.000 için tahmin yanıtı ≤ 2s. |
| NFR-03 | Güvenilirlik | Match engine seed alındığında deterministik. |
| NFR-04 | Test edilebilirlik | Domain sınıfları framework-agnostik (Laravel'siz instantiate edilebilir). |
| NFR-05 | Bakım yapılabilirlik | SOLID ilkelerine uyum, anlamlı isimlendirme, küçük dosyalar (<400 LOC). |
| NFR-06 | Erişilebilirlik | Reset Data butonu rengi (kırmızı) yanında ikon/tooltip de bulunur (renk körü kullanıcı için). |
| NFR-07 | Güvenlik | Public API mutasyonel uçlar yalnızca POST/PATCH; GET cache uyumlu. |
| NFR-08 | Taşınabilirlik | SQLite ile çalışır; MySQL/Postgres'e taşınabilir (env değişimi). |
| NFR-09 | Loglama | Sunucu tarafı: simülasyon hataları log'lanır (Laravel log). |
| **NFR-10** | **Esneklik (Flexibility)** | **Takım sayısı 4'e hard-code edilmemeli; N takım için çalışan jenerik fikstür algoritması.** |
| NFR-11 | Erişilebilirlik (UX) | Buton isimleri video özetine birebir uyumlu: Generate Fixtures / Start Simulation / Play Next Week / Play All Weeks / Reset Data. |
| NFR-12 | Yuvarlama | Tahmin yüzdeleri toplam 100 olacak şekilde yuvarlanır (largest remainder method, **tam değer, tolerans yok**). |
| NFR-13 | Concurrency Safety | Tüm mutasyonel endpoint'ler state machine + optimistic locking ile çift tıklama, paralel istek, partial failure'a dayanıklı. Default: idempotent davranış. (Bkz. §4.5) |
| NFR-14 | Atomic Operations | Maç simülasyonu + standings güncelleme + predictions upsert + `current_week` artırımı tek DB transaction içinde. (Bkz. §4.5.1) |
| NFR-15 | Frontend Mutation Lock | Mutasyon in-flight iken tüm mutasyon butonları `disabled`; sadece read-only UI açık. (Bkz. US-A-06) |
| NFR-16 | Production Hardening | Production deploy'da `APP_DEBUG=false`, `APP_KEY` set, `.env` dosyası VCS'de yok, `.env.example` repo'da. Stack trace 5xx yanıtlarında sızdırılmaz. |
| NFR-17 | Rate Limiting | Reset (3/dk), Play All Weeks (10/dk), Generate Fixtures (10/dk) IP başına throttle (`throttle:N,1` middleware). |
| NFR-18 | HTTPS | Public URL HTTPS üzerinden servis edilir; deploy platform default davranışı kabul edilir (Railway/Vercel/Render HTTPS default). |
| NFR-19 | CSRF/CORS | Tüm `/api/*` rotaları `routes/api.php` içinde stateless; CSRF middleware yok; CORS allowlist: deploy domain + `localhost:5173`. |
| NFR-20 | Audit Logging | Edit Match her başarılı işlemde structured log atılır (match_id, old_score, new_score, ip, timestamp). |

---

## 9. Test Stratejisi

### 9.1. Test Piramidi

```
        ┌───────────────────────┐
        │  E2E (smoke, 1-2)     │
        ├───────────────────────┤
        │  Integration (≈10)    │
        ├───────────────────────┤
        │  Unit (≈40+, domain)  │
        └───────────────────────┘
```

### 9.2. PHPUnit Test Sınıfları

| Test Sınıfı | Konu | Önemli Testler |
| --- | --- | --- |
| `MatchEngineTest` | Engine | Seed deterministik; eşit güçte 1000 simülasyonda ≥3 farklı skor; FAQ Q5 sürpriz (A=100, B=10 → B kazanır > 0). |
| `FixtureGeneratorTest` | Fikstür | 4 takım → 12 maç, 6 hafta; her çift takım 2 kez (ev+deplasman); her hafta her takım 1 maç; seed deterministik; **6 takım → 30 maç, 10 hafta**; **8 takım → 56 maç, 14 hafta**; **4 hard-code yok**. |
| `LeagueStandingTest` | Tablo | PTS=3W+D, GD=GF−GA, sıralama PTS desc → GD desc; P = W+D+L invariant. |
| `MonteCarloStrategyTest` | Tahmin | sum=100; FAQ Örnek A → 100/0/0/0; FAQ Örnek B → iki lider toplam ≥ 95; N=10.000 stabil. |
| `SeededRandomTest` | RNG | Aynı seed aynı diziyi üretir. |
| `IdempotencyTest` | API | `play_next_week_at_season_end_returns_409`, `reset_idempotent`, `generate_fixtures_replaces_old`. |
| `EditMatchValidationTest` | API | `rejects_negative_score`, `rejects_over_20`, `rejects_non_integer`, `requires_played_match`, `requires_expected_version`. |
| `ConcurrencyTest` | API + DB | `concurrent_play_next_week_returns_409`, `concurrent_patch_returns_409`, `transaction_rollback_on_failure`, `state_machine_blocks_during_running`. |
| `RateLimitTest` | API | `reset_throttle_returns_429`, `play_all_throttle_returns_429`, `generate_fixtures_throttle_returns_429`. |
| `ProductionHardeningTest` | Config | `debug_mode_off_in_production`, `env_example_exists`, `gitignore_excludes_env`. |

### 9.3. Vitest (Frontend) Test Sınıfları

| Test Dosyası | Konu |
| --- | --- |
| `LeagueTable.spec.ts` | Sütun başlıkları (Team/PTS/P/W/D/L/GD), satır sayısı, sıralama. |
| `Controls.spec.ts` | Play Next Week / Play All Weeks / Reset Data butonları render edilir; Reset Data kırmızı. |
| `SetupScreen.spec.ts` | Tournament Teams listesi, Generate Fixtures akışı, Start Simulation aktiflik mantığı. |
| `ChampionshipPrediction.spec.ts` | Toplam = 100, week<4 boş davranış. |
| `EditMatchModal.spec.ts` | Modal açılış, kaydetme. |

### 9.4. Seed Politikası ve Invariant / Property Testleri

**SEED POLİTİKASI** (test flakiness kontrolü için):

- **Deterministik testler** (FAQ A/B, US-C-04, US-B-02): `seed=42` sabit, sonuç **tam karşılaştırma**.
- **İstatistiksel davranış testleri** (US-C-01, US-C-02, US-C-05): seed yok, **generous tolerance + en fazla 2 retry**.
- **Property testleri** (P=W+D+L, GD=GF-GA, sum=100): her invocation invariant doğrular (seed bağımsız).

`phpunit.xml` ENV:
```xml
<env name="TEST_SIMULATION_SEED" value="42"/>
<env name="TEST_FLAKY_RETRY" value="2"/>
```

Test grupları:
- `@group fast` — N=1.000 testleri (her commit'te koşar).
- `@group slow` — N=10.000 testleri (CI'da bir kez, nightly).

**Invariant / Property listesi**:

- **GD = GF − GA**: Her tablo satırında.
- **P = W + D + L**: Her tablo satırında.
- **points = 3*W + D**: Her standings satırında (DB CHECK + test).
- **sum(predictions per week) = 100**: tetikleme koşulu (`currentWeek > totalWeeks - 3`) sağlandığı her hafta için **tam** (tolerans yok).
- **maç sayısı = N × (N − 1)**: Fikstür sonrası.
- **hafta sayısı = 2 × (N − 1)**: Fikstür sonrası.

**FAQ Örnek A — Net Test Spesifikasyonu**:

```
test_faq_example_a_uncatchable_leader_returns_exactly_100_for_leader:
  - Setup: Liverpool 18 PTS, Manchester City 9 PTS, Chelsea 7 PTS, Arsenal 5 PTS
  - Kalan: 2 hafta
  - Engine: seed=42, MatchEngine deterministik
  - Assert: predictions[Liverpool] == 100.00 (largest remainder sonrası, exact)
  - Assert: predictions[Manchester City] == 0.00
  - Assert: predictions[Chelsea] == 0.00
  - Assert: predictions[Arsenal] == 0.00
  - Assert: sum == 100.00
```

**FAQ Örnek B — Net Test Spesifikasyonu**:

```
test_faq_example_b_top_two_in_range_and_others_zero:
  - Setup: 1 hafta kala lider 2 takım eşit puanda, karşılıklı oynayacak
  - Assert: predictions[A] BETWEEN 35 AND 65
  - Assert: predictions[B] BETWEEN 35 AND 65
  - Assert: predictions[A] + predictions[B] >= 95
  - Assert: predictions[other_teams] < 5 (her biri)
  - Assert: sum(all) == 100
```

### 9.5. Smoke / E2E

- Setup → Generate Fixtures → Start Simulation → Play All Weeks → Reset Data akışı tek bir Cypress (veya Playwright) testi olarak koşulabilir (opsiyonel).

### 9.6. Test Verisi (Fixtures for Tests)

Aşağıdaki sabit test verileri PHPUnit testlerinde tekrar kullanılır:

**Senaryo F-Test-1: FAQ Örnek A (uncatchable leader)**
- Liverpool: 18 PTS, Manchester City: 9 PTS, Chelsea: 7 PTS, Arsenal: 5 PTS
- Kalan hafta: 2 (week 5 ve 6)
- Kalan maçlar: brief'in Week 4 görüntüsündeki maç planı
- Beklenen: Liverpool %100, diğerleri %0

**Senaryo F-Test-2: FAQ Örnek B (last-week tie)**
- Liverpool: 12 PTS, Manchester City: 12 PTS, Chelsea: 7 PTS, Arsenal: 4 PTS
- Kalan hafta: 1 (week 6)
- Kalan maçlar: Liverpool vs Manchester City (head-to-head)
- Beklenen: Liverpool + Manchester City toplam ≥ %95; diğerleri ≤ %5

**Senaryo F-Test-3: Brief Week 4 Figürü (tam değerler)**
- Chelsea:         PTS=10, P=4, W=3, D=1, L=0, GF=12, GA=1,  GD=+11
- Arsenal:         PTS=8,  P=4, W=2, D=2, L=0, GF=8,  GA=2,  GD=+6
- Manchester City: PTS=8,  P=4, W=2, D=2, L=0, GF=6,  GA=2,  GD=+4
- Liverpool:       PTS=4,  P=4, W=1, D=1, L=2, GF=4,  GA=4,  GD=0
- Week 4 maçları (Brief PDF Figure 1.a): Chelsea 3-2 Liverpool, Arsenal 3-3 Manchester City
- Week 4 tahmini (referans): 45 / 25 / 25 / 5
- Week 5 tablosu (referans): Chelsea 13, Arsenal 9, Manchester City 8, Liverpool 5
- Week 5 maçları: Manchester City 2-3 Chelsea, Arsenal 2-2 Liverpool
- Week 5 tahmini: 60 / 20 / 15 / 5
- **Tolerans**: her takım için `|observed − expected| ≤ 10 yüzde puanı`.

### 9.7. Coverage Hedefi

| Katman | Hedef |
| --- | --- |
| Domain (`App\Domain`) | ≥ 80% |
| Application (`App\Actions`, `App\Services`) | ≥ 60% |
| HTTP (Controllers) | smoke düzeyi |
| Frontend store + critical components | ≥ 60% |

### 9.8. Test Fixture Klasör Yapısı

```
tests/
├── Fixtures/
│   ├── FaqExampleA.php             # Uncatchable leader senaryosu
│   ├── FaqExampleB.php             # Son hafta eşitlik senaryosu
│   ├── BriefWeek4.php              # PTS+P+W+D+L+GF+GA+GD tam değerleriyle (F-Test-3)
│   └── TeamPowerProfiles.php       # Liverpool 88, MC 90, Chelsea 82, Arsenal 80
├── Unit/
│   └── Domain/
│       ├── MatchEngineTest.php
│       ├── FixtureGeneratorTest.php
│       ├── LeagueStandingTest.php
│       ├── SeededRandomTest.php
│       └── ChampionshipPredictorTest.php
├── Unit/
│   └── Services/
│       ├── PredictionServiceTest.php
│       └── ...
└── Feature/
    └── Api/
        ├── LeagueTest.php
        ├── MatchTest.php
        ├── IdempotencyTest.php
        ├── ConcurrencyTest.php
        ├── RateLimitTest.php
        └── ProductionHardeningTest.php
```

### 9.9. CI Pipeline (Örnek YAML)

`.github/workflows/ci.yml`:

```yaml
name: CI
on: [push, pull_request]
jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', coverage: xdebug }
      - run: composer install --no-progress
      - run: php artisan migrate --env=testing
      - run: vendor/bin/phpunit --coverage-clover coverage.xml --exclude-group=slow
  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 20 }
      - run: npm ci && npm run test:unit && npm run build
  e2e:
    needs: [backend, frontend]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: npx playwright install --with-deps
      - run: npm run test:e2e
```

> Not: `@group slow` (N=10.000) testleri nightly cron'da ayrı koşulur; her commit'te `--exclude-group=slow` ile atlanır.

---

## 10. Riskler ve Bağımlılıklar

| ID | Risk | Olasılık | Etki | Önlem |
| --- | --- | --- | --- | --- |
| R-01 | Monte Carlo performansı (N=10.000) deploy ortamında yavaş kalabilir | Orta | Orta | N'i 5.000'e düşür; cache kullan. |
| R-02 | Tiebreak kuralı belirsiz (PTS/GD sonrası) | Yüksek | Düşük | Developer kararı: GF veya alfabetik; README'de belgele. |
| R-03 | FAQ Örnek B beklentisi (50/50 vs 65/35) belirsiz | Orta | Orta | Acceptance: iki liderin yüzdesi ≥95; tek değer dayatma. |
| R-04 | Fikstür algoritması tek sayı takım için bozulabilir | Düşük | Düşük | Tek sayı için "bye team" desteği ekle (opsiyonel). |
| R-05 | Frontend ve backend ayrı deploy edilirse CORS sorunu | Orta | Düşük | Tek deploy (Laravel public/) önerilir. |
| R-06 | Reset Data yanlışlıkla basılır → veri kaybı | Orta | Düşük | Confirm modal (opsiyonel). |
| R-07 | Seed kullanımı production'da deterministik sonuçlara yol açar | Düşük | Düşük | Seed yalnızca test ortamında zorlanır; prod random. |
| R-08 | AI tarafından üretilen kod hatalı tiebreak/yuvarlama yapabilir | Orta | Orta | İlgili kısımlar invariant testlerle korunur. |
| R-09 | Çift tıklama / race condition (Play Next Week, Edit Match) | Yüksek | Yüksek | State machine + optimistic lock (`expected_version`) + idempotency (`expected_week`) + frontend mutation lock (US-A-06). |
| R-10 | Multi-tab kullanımı veri çakışmasına yol açabilir | Orta | Orta | README'de "single-tab" notu + WebSocket out-of-scope (OQ-12). |
| R-11 | DoS / abuse (anonim public mutasyon endpoint'leri) | Orta | Yüksek | NFR-17 rate limiting (`throttle:N,1`). |
| R-12 | APP_DEBUG=true ile stack trace / secret sızıntısı | Düşük | Yüksek | NFR-16 + DoD-20 + ProductionHardeningTest. |
| R-13 | İstatistiksel testlerin (Monte Carlo, US-C-05) flaky olması | Orta | Orta | Seed-fix politikası (§9.4) + tolerance bantları + `@group slow` ayrımı + 2 retry. |

### Bağımlılıklar

- PHP ≥ 8.1, Composer.
- Node.js ≥ 18, npm/pnpm.
- SQLite (PHP extension).
- Deploy hesabı (Railway / Vercel / Netlify / Render).

### Eskalasyon / Karar Akışı

| Durum | Aksiyon |
| --- | --- |
| FAQ Örnek A veya B testi kırılır | İlgili story'i bloker olarak işaretle; engine veya predictor düzeltilmeden release yok. |
| Public deploy başarısız | Sprint 3'ün ilk önceliği; gerekirse Render → Railway veya tersi geçişi. |
| Monte Carlo > 2s yanıt verir | N'i 5.000'e düşür; sonuçları cache'le; UI'da spinner ekle. |
| Tiebreak senaryosu test sırasında belirsiz çıkar | README'de uygulanan tiebreak kuralını belgele (örn. "PTS → GD → GF"). |

---

## 11. Out of Scope / Açık Sorular

### Out of Scope

- Kullanıcı kayıt/giriş, oturum yönetimi.
- Çoklu sezon arşivi, geçmiş istatistikler.
- Gerçek zamanlı çok kullanıcılı izleme.
- Native mobil uygulamalar.
- Detaylı maç içi olay simülasyonu (gol dakikası, kart, vb.).

### Açık Sorular (OQ)

| OQ ID | Soru | Karar Sahibi | Karar / Mevcut Varsayım | Durum |
| --- | --- | --- | --- | --- |
| OQ-01 | Tiebreak (PTS=GD eşitliğinde) ne olacak? | Developer | **PTS → GD → GF → team_name asc (alfabetik); H2H kapsam dışı.** | **KAPALI** |
| OQ-02 | FAQ Örnek B'de beklenen tam yüzde nedir (50/50 mi, 65/35 mi)? | Developer | Her iki lider için `35..65` aralığı; toplam ≥95; diğerleri <5. | Açık (yeterli) |
| OQ-03 | Reset Data confirmation modali şart mı? | Developer | Zorunlu (US-A-03 AC-4); kırmızı renk + confirm. | Karar verildi |
| OQ-04 | Frontend ayrı host'a mı, Laravel `public/` altına mı deploy edilecek? | Developer | **Ayrı host: Frontend Vercel/Netlify, Backend Railway. CORS allowlist (NFR-19) zorunlu, iki ayrı URL.** | **KAPALI** |
| OQ-05 | Takım power/supporter/keeper default değerleri ne olmalı? | Developer | **Liverpool 88, Manchester City 90, Chelsea 82, Arsenal 80. Supporter/Keeper 60–80 aralığında.** | **KAPALI** |
| OQ-06 | Monte Carlo N parametresi UI'dan ayarlanmalı mı? | Developer | Hayır, kod sabit (N=10.000) | Karar verildi |
| OQ-07 | "Play All Weeks" sırasında ara kareler (animasyon) gerekli mi? | UX | Hayır, sonuç doğrudan gösterilir | Karar verildi |
| OQ-08 | Edit Match Results sınırlı bir alana mı izinli? | Developer | **0..20 (CHECK constraint + 422 validation)** | **KAPALI** |
| OQ-09 | Tahmin paneli tetikleme koşulu sağlanmadığında boş mu, "—" mı? | UX | "—" placeholder | Karar verildi |
| OQ-10 | Public deploy için tercih edilen platform? | DevOps | **Backend: Railway (Laravel API + SQLite). Frontend: Vercel veya Netlify (Vue build). Her ikisi de ücretsiz tier + otomatik HTTPS.** | **KAPALI** |
| OQ-11 | Üretim için maksimum kaç takım desteklenmeli? | Product | **8 (CHECK constraint: team_count BETWEEN 2 AND 8)** | **KAPALI** |
| OQ-12 | Multi-tab davranışı / WebSocket gerekli mi? | Product | **Single-tab varsayımı**; README'de belgelenir. WebSocket out-of-scope. | Açık (kabul edildi) |
| OQ-13 | Play All Weeks sync mi async mi (job queue)? | Developer | **Default: sync + per-week transaction** (US-F-03). N≤8 takım için yeterli. | Açık (kabul edildi) |
| OQ-14 | Predictions persist mi cache mi? | Developer | **Snapshot tablosu** (`predictions`) + transaction içinde upsert. | Açık (kabul edildi) |

---

## 12. Definition of Done (DoD)

Bir özellik / story aşağıdaki maddelerin tamamı sağlandığında "Done" sayılır:

1. **Kod tamamlandı** ve PR (veya commit) açıldı.
2. **OOP** prensiplerine uyuldu (procedural kod yok).
3. **Unit testler** yazıldı ve yeşil; ilgili domain kapsam ≥ %80.
4. **FAQ Örnek A ve B** testleri yeşil.
5. **Lig tablosu invariant'ları** (P=W+D+L, GD=GF−GA) testlerle doğrulandı.
6. **Tahmin toplamı = 100** invariant'i geçti.
7. **FixtureGenerator N takım için test edildi** (4 ve 6 takım, başarılı).
8. **Buton isimleri** video özetiyle uyumlu (Generate Fixtures / Start Simulation / Play Next Week / Play All Weeks / Reset Data).
9. **Reset Data butonu kırmızı** ve setup ekranına döndürür.
10. **Component pattern** uygulandı (Vue SFC veya Web Component).
11. **Two-screen flow** çalışıyor (Setup → Simulation).
12. **README** güncellendi (setup/run/test/deploy/AI usage).
13. **AI-assisted commit notu** ilgili commit'lerde mevcut.
14. **Public deploy** çalışır durumda; URL paylaşıldı.
15. **Smoke akış** (Generate → Start → Play All → Reset) manuel olarak başarılı.
16. **No console errors** (frontend) ve **no PHP notices/warnings** (backend).
17. **Code review** yapıldı veya self-review checklist tamamlandı.
18. **Doküman tutarlılığı**: bu doküman ile `TEST_CASE_APPROACH.md` çelişmiyor.
19. **Concurrency testleri** (US-H-06: `ConcurrencyTest`) yeşil.
20. **Production hardening**: `APP_DEBUG=false`, `APP_KEY` set, `.env.example` mevcut, `.env` `.gitignore`'da (US-H-08).
21. **Dependency audit**: `composer audit` + `npm audit` çıktısı **0 critical**.
22. **Rate limiting** middleware aktif endpoint'lerde test edilmiş (US-H-09 / `RateLimitTest`).
23. **CI yaml** (`.github/workflows/ci.yml`) mevcut ve son commit'te yeşil (US-H-07).
24. **E2E happy path** (Playwright, Setup → Generate → Start → Play All → Reset) yeşil.

---

## 13. Güvenlik Notları

Bu uygulama **public-facing demo**'dur, kullanıcı kimlik doğrulaması yoktur (brief gereği).

### 13.1. Kabul Edilen Mimari Kararlar (Yazılı Risk)

- Tüm mutasyonel endpoint'ler **anonim**dir → rate limiting (NFR-17) ile abuse savunması.
- Reset Data tek tıkla tüm veriyi sıfırlar → **confirmation modal (US-A-03 AC-4 zorunlu) + kırmızı renk (US-A-03 AC-3)** ile UX koruması.
- State machine (§4.5.2) + frontend mutation lock (US-A-06) yarı-savunmadır; auth değildir.

### 13.2. Uygulanan Güvenlik Önlemleri

| Kontrol | Referans |
| --- | --- |
| Input validation: PATCH /api/matches/{id} score `0..20` | NFR-19 + matches CHECK constraint (§4.2) + US-G-03 AC-5 |
| CSRF/CORS: stateless `/api/*`, allowlist'li CORS | NFR-19 |
| HTTPS: public URL HTTPS | NFR-18 |
| Production hardening: `APP_DEBUG=false`, stack trace gizli | NFR-16 + US-H-08 |
| Audit logging: Edit Match structured log | NFR-20 |
| Rate limiting: reset / play-all / generate-fixtures | NFR-17 + US-H-09 |
| Optimistic lock: `version` sütunu + `expected_version` | §4.5.4 + US-G-03 |
| State machine: 423 Locked while `running` / `resetting` | §4.5.2 + US-A-05 |

### 13.3. Threat Model — Out of Scope (Yazılı Karar)

- **Auth/authorization** — brief gereği yok; kabul edilmiş risk.
- **Multi-user concurrent edit conflict** — single-user varsayımı (OQ-12).
- **Persistent XSS** — Vue default escape yeterli; `v-html` kullanımı yasak (lint kuralı önerilir).
- **SSRF** — outbound HTTP çağrısı yok.
- **SQL Injection** — Eloquent parameterized query default; raw query yasak.
- **Mass assignment** — Eloquent `$fillable` listesi her modelde explicit.

---

> Bu doküman, brief + FAQ + video özeti kaynaklarına dayanarak hazırlanmıştır. Video özetindeki "Tournament Teams / Generate Fixtures / Start Simulation / Play Next Week / Play All Weeks / Reset Data" buton ve akışları, doğrudan UI gereksinimi olarak işlenmiştir. Esneklik (N takım) ve PTS sütununun korunması gibi karar noktaları kaynaklar arasında uyum sağlanarak alınmıştır.

---

## Ek A — UI Akış Şeması (Detay)

```
┌─────────────────────────────────────────────────────────────┐
│                       APP YÜKLENİR                           │
│  GET /api/league/state                                       │
└─────────────────┬───────────────────────────────────────────┘
                  │
       ┌──────────┴──────────┐
       │ fikstür var mı?     │
       └──────────┬──────────┘
        Hayır     │      Evet
       ┌──────────┘      └──────────┐
       │                            │
       ▼                            ▼
┌──────────────────────┐   ┌────────────────────────────┐
│   SETUP SCREEN       │   │     SIMULATION SCREEN      │
│   ┌──────────────┐   │   │  ┌──────┬─────────┬─────┐  │
│   │ Tournament   │   │   │  │ Lig  │ Week N  │Pred-│  │
│   │ Teams        │   │   │  │Table │ Results │iction│ │
│   │ (Liverpool,  │   │   │  │      │         │      │ │
│   │ Manchester   │   │   │  ├──────┴─────────┴──────┤ │
│   │ City,        │   │   │  │ [Play Next Week]      │ │
│   │ Chelsea,     │   │   │  │ [Play All Weeks]      │ │
│   │ Arsenal)     │   │   │  │ [Reset Data] (kırmızı)│ │
│   └──────────────┘   │   │  └───────────────────────┘ │
│   [Generate          │   │                            │
│    Fixtures]         │   │  Reset Data → Setup Screen │
│                      │   │                            │
│   ┌──────────────┐   │   └────────────────────────────┘
│   │ Fixtures     │   │            ▲
│   │ (6 hafta)    │   │            │
│   │ W1: Ars-Liv  │   │            │
│   │ W1: MC-Che   │   │            │
│   │ ...          │   │            │
│   │ W4: Liv-Ars  │   │            │
│   │ W4: Che-MC   │   │            │
│   └──────────────┘   │            │
│   [Start Simulation] ├────────────┘
│                      │
└──────────────────────┘
```

## Ek B — Veri Akış Sekansı (Play Next Week)

```
User           Frontend             Backend                  DB
 │                │                    │                     │
 │  click Next    │                    │                     │
 ├───────────────►│                    │                     │
 │                │ POST play-next-week│                     │
 │                ├───────────────────►│                     │
 │                │                    │ load week N matches │
 │                │                    ├────────────────────►│
 │                │                    │◄────────────────────┤
 │                │                    │                     │
 │                │                    │ MatchEngine.simulate│
 │                │                    │ (her maç için)      │
 │                │                    │                     │
 │                │                    │ save scores         │
 │                │                    ├────────────────────►│
 │                │                    │                     │
 │                │                    │ LeagueStanding      │
 │                │                    │ .recalculate()      │
 │                │                    │                     │
 │                │                    │ PredictionService   │
 │                │                    │ .compute() (week≥4) │
 │                │                    │                     │
 │                │  { matches,        │                     │
 │                │    standings,      │                     │
 │                │    predictions,    │                     │
 │                │    currentWeek }   │                     │
 │                │◄───────────────────┤                     │
 │                │                    │                     │
 │   UI update    │                    │                     │
 │◄───────────────┤                    │                     │
 │                │                    │                     │
```

## Ek C — Buton İsim Sözlüğü (Video Özetiyle Birebir)

| UI Etiketi | Konum | Eylem | Renk/Stil |
| --- | --- | --- | --- |
| `Generate Fixtures` | Setup Screen | Fikstürü üretir, mevcut varsa siler. | Birincil (mavi/yeşil) |
| `Start Simulation` | Setup Screen | Simulation ekranına geçer; fikstür yoksa inaktif. | Birincil |
| `Play Next Week` | Simulation Screen | Sıradaki haftayı oynatır. | Birincil |
| `Play All Weeks` | Simulation Screen | Kalan tüm haftaları oynatır. | İkincil |
| `Reset Data` | Simulation Screen | Tüm veriyi sıfırlar; Setup'a döner. | **Kırmızı (danger)** |

## Ek D — Story → AC → Test Eşleşmesi (İz Sürme Matrisi)

| Story | İlişkili AC | Doğrulayan Test |
| --- | --- | --- |
| US-A-01 | AC-1, AC-2 | `TeamSeederTest::test_seeds_four_teams` |
| US-A-03 | AC-1, AC-3 | `ResetLeagueActionTest`, `Controls.spec.ts::reset_is_red` |
| US-A-04 | AC-1..3 | `SetupScreen.spec.ts::shows_tournament_teams` |
| US-B-01 | AC-1..3 | `FixtureGeneratorTest::test_four_teams_double_round_robin` |
| US-B-03 | AC-1..3 | `FixtureGeneratorTest::test_six_teams`, `test_eight_teams`, `test_no_hardcoded_team_count` |
| US-B-04 | AC-1..3 | `SetupScreen.spec.ts::generate_button_calls_api`, `GenerateFixturesActionTest` |
| US-B-05 | AC-1..3 | `SetupScreen.spec.ts::start_simulation_enabled_after_fixtures` |
| US-C-05 | AC-1 | `MatchEngineTest::test_weak_team_can_win_at_least_once_in_10k` |
| US-D-01 | AC-1 | `LeagueStandingTest::test_premier_league_points_3_1_0` |
| US-D-02 | AC-1 | `LeagueTable.spec.ts::headers_team_pts_p_w_d_l_gd` |
| US-E-02 | AC-1 | `MonteCarloStrategyTest::test_sum_equals_100` |
| US-E-03 | AC-1 | `MonteCarloStrategyTest::test_faq_example_a_uncatchable_leader` |
| US-E-04 | AC-1 | `MonteCarloStrategyTest::test_faq_example_b_last_week_tie` |
| US-F-01 | AC-1 | `PlayAllWeeksActionTest::test_plays_all_remaining_weeks` |
| US-G-01 | AC-1 | `EditMatchModal.spec.ts::opens_with_existing_score` |
| US-G-03 | AC-1..7 | `EditMatchValidationTest`, `ConcurrencyTest::test_concurrent_patch_match_returns_409` |
| US-A-05 | AC-1..4 | `ConcurrencyTest::test_state_machine_blocks_during_running` |
| US-A-06 | AC-1..5 | `leagueStore.spec.ts::isMutating_disables_buttons` |
| US-F-03 | AC-1..4 | `PlayAllWeeksActionTest::test_per_week_transaction`, `test_resumes_from_current_week` |
| US-H-06 | AC-1..4 | `ConcurrencyTest::*` |
| US-H-07 | AC-1..3 | `.github/workflows/ci.yml` (GitHub Actions yeşil) |
| US-H-08 | AC-1..4 | `ProductionHardeningTest::*` |
| US-H-09 | AC-1..4 | `RateLimitTest::*` |
| US-H-04 | AC-1 | Manuel smoke (canlı URL) |

## Ek E — Sürüm / Etiketleme

| Versiyon | Kapsam |
| --- | --- |
| `v0.1.0-alpha` | Setup ekranı + Generate Fixtures çalışıyor; engine taslak. |
| `v0.2.0-alpha` | Match engine + LeagueTable çalışıyor; Play Next Week çalışıyor. |
| `v0.3.0-beta` | Prediction çalışıyor; FAQ A/B testleri yeşil. |
| `v0.4.0-beta` | Play All Weeks + Edit Match Results (extras) çalışıyor. |
| `v1.0.0` | Public deploy + README + AI usage notu tamam; tüm DoD maddeleri geçti. |
