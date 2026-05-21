# Test Case Yaklaşım Dokümanı — Insider One Champions League

Bu doküman, geliştirme dokümanındaki feature/story listesinden farklı olarak, **stratejik karar ve yaklaşım** notudur.

## 1. Önerilen Laravel Mimarisi
Katmanlı yapı: **Controller → Service → Repository → Eloquent Model**.
- `App\Domain\Match\MatchEngine` (Domain Service): maç simülasyonu burada; saf, framework-agnostik, kolay test edilebilir.
- `App\Actions\PlayWeekAction`, `PlayAllAction`: tek sorumluluk; Controller yalnızca delege eder.
- `App\Services\Prediction\PredictionService` + `PredictionStrategy` arayüzü: Monte Carlo şu an, ileride closed-form alternatifi `MonteCarloStrategy`/`HeuristicStrategy` olarak takılabilir (Strategy Pattern).
- Repository ince tutulur (Eloquent zaten repository benzeri); aşırı soyutlamadan kaçının.

## 2. Frontend Yaklaşımı
**Öneri: Vue 3 + Vite + Pinia + Axios.** Native JS + Web Components alternatif fakat development hızı ve test ekosistemi (Vitest, Vue Test Utils) Vue lehine.
Bileşenler: `LeagueTable`, `WeekResults`, `PredictionPanel`, `Controls` (NextWeek/PlayAll/Reset), `EditMatchModal`. Pinia store: `leagueStore` (teams, fixtures, currentWeek, predictions).

## 3. Match Engine — Probabilistic Determinism
- `random_int` güvenli ama **seed alınamaz**; testlerde kararsız. Çözüm: `SeededRandom` sınıfı (`mt_srand($seed)` + `mt_rand` veya kendi LCG). Constructor injection ile MatchEngine'e geçilir; testte sabit seed.
- Formül taslağı:
  - `attack = power * 0.7 + supporter * 0.2 + (home ? home_advantage : 0)`
  - `defense = opp_power * 0.6 + keeper * 0.4`
  - `λ = max(0.2, (attack - defense*0.5) / 20)`
  - Skor: Poisson örnekleme (`λ_home`, `λ_away`). Basitlik için weighted RNG da kabul.
- Zayıf takımın kazanma şansı `λ`'nın küçük ama sıfır olmamasıyla garanti.

## 4. Prediction Algoritması
**Öneri: Monte Carlo, N=10.000.** Kalan haftaları aynı engine ile N kez simüle et, şampiyonluk yüzdesini say. Avantaj: FAQ'daki iki örneği (yakalanamaz lider → %100; son hafta beraberlik → 50/50 veya 65/35) doğrudan test edilebilir. Trade-off: kapalı-form (puan farkı + GD ağırlığı) daha hızlı ama gerçekçilik düşük. Performans için N=5.000'e düşürülebilir; matematiksel tutarlılık önceliklidir.

## 5. Test Stratejisi
- **PHPUnit**: MatchEngine (seeded), FixtureGenerator (12 maç, çift devre), LeagueTable scoring (PTS=3W+D), Predictor (FAQ örnekleri acceptance test).
- **Vitest**: bileşen render + store mutasyonları.
- **Property/invariant testleri**: `GD = GF − GA`, tüm tahminler toplamı = %100, P = W+D+L.
- Kapsam hedefi: domain katmanında %80+.

## 6. AI Kullanım Stratejisi
- **AI'ın güçlü olduğu yerler**: scaffolding (migration, model, controller), fixture rotasyon algoritması (round-robin), PHPUnit case'leri, Vue bileşen iskeletleri, README/deploy notları.
- **Dikkat edilmesi gerekenler**: olasılık matematiği (mutlaka 10k simülasyonla doğrula), tiebreak zinciri (PTS → GD → GF → alfabetik; H2H kapsam dışı; DEVELOPMENT_DOCUMENT §4.4 ile birebir uyumlu), edge case'ler (eşitlik durumunda tahmin paylaşımı).
- AI yardımıyla yazılan commit'lere kısa not: `feat(engine): match simulation (AI-assisted)`.

## 7. Deploy
- **Backend**: Railway veya Laravel Cloud (en hızlısı). Render ücretsiz tier alternatif.
- **Frontend**: Vue build → Laravel `public/` altında servis (tek deploy, CORS yok) veya ayrı Vercel/Netlify.
- **DB**: SQLite yeterli (4 takım, 12 maç). MySQL gereksiz karmaşa.

## 8. Sprint Planı
- **Sprint 1 (3-4 gün)**: Setup, Team/Match/Fixture migration, FixtureGenerator, MatchEngine, LeagueTable, NextWeek (Epic A-D).
- **Sprint 2 (3-4 gün)**: Prediction (Monte Carlo), PlayAll, EditMatchModal, PHPUnit suite (Epic E-G).
- **Sprint 3 (1-2 gün)**: UI cila, deploy, README, AI-usage notu (Epic H).
