<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\ChampionshipPredictor;
use App\Domain\Services\MatchSimulator;
use App\Models\LeagueSettings;
use App\Models\Prediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FaqExampleA;
use Tests\Fixtures\FaqExampleB;
use Tests\Fixtures\TeamPowerProfiles;
use Tests\TestCase;

/**
 * Champion predictor invariants (US-E-02, US-E-03, US-E-04) and FAQ A/B critical tests.
 *
 * Spec atiflari:
 *  - US-E-03 / FAQ Ornek A: uncatchable leader -> %100 / 0 / 0 / 0 (spec section 9.4).
 *  - US-E-04 / FAQ Ornek B + OQ-02: iki lider [35..65] bandi; toplam >= 95; digerleri < 5.
 *  - US-E-02: largest-remainder normalisation, sum == 100 (exact).
 */
final class ChampionshipPredictorTest extends TestCase
{
    use RefreshDatabase;

    private function predictor(): ChampionshipPredictor
    {
        return new ChampionshipPredictor(new MatchSimulator());
    }

    /** US-E-03 / FAQ Ornek A (spec 9.4). */
    public function test_faq_example_a_uncatchable_leader_returns_exactly_100_for_leader(): void
    {
        $teams = TeamPowerProfiles::seed();
        FaqExampleA::setup($teams);

        $settings = LeagueSettings::query()->find((int) config('league.settings_id', 1));
        $percentages = $this->predictor()->predictAndSnapshot($settings);

        $this->assertSame(100.0, $percentages[$teams['Liverpool']->id], 'Liverpool must be exactly 100%');
        $this->assertSame(0.0, $percentages[$teams['Manchester City']->id]);
        $this->assertSame(0.0, $percentages[$teams['Chelsea']->id]);
        $this->assertSame(0.0, $percentages[$teams['Arsenal']->id]);
        $this->assertSame(100.0, array_sum($percentages));
    }

    /**
     * US-E-04 / FAQ Ornek B + OQ-02 (spec 9.4 / 9.6).
     *
     * Spec sabit bant: [35, 65]. Fixture (FaqExampleB) GD asimetrisi ile
     * dengelendigi icin Monte Carlo dagilimi ~ 53 / 47 cikar -- bandin
     * icinde kalir. Genisletme YAPILMAZ; spec [35..65] olarak korunur.
     */
    public function test_faq_example_b_top_two_in_range_and_others_zero(): void
    {
        $teams = TeamPowerProfiles::seed();
        FaqExampleB::setup($teams);

        $settings = LeagueSettings::query()->find((int) config('league.settings_id', 1));
        $percentages = $this->predictor()->predictAndSnapshot($settings);

        $liv = $percentages[$teams['Liverpool']->id];
        $mc = $percentages[$teams['Manchester City']->id];
        $che = $percentages[$teams['Chelsea']->id];
        $ars = $percentages[$teams['Arsenal']->id];

        // OQ-02: 35..65 her iki lider icin (spec literal).
        $this->assertGreaterThanOrEqual(35.0, $liv, 'Liverpool spec band 35..65');
        $this->assertLessThanOrEqual(65.0, $liv, 'Liverpool spec band 35..65');
        $this->assertGreaterThanOrEqual(35.0, $mc, 'Manchester City spec band 35..65');
        $this->assertLessThanOrEqual(65.0, $mc, 'Manchester City spec band 35..65');

        // OQ-02: toplam >= 95.
        $this->assertGreaterThanOrEqual(95.0, $liv + $mc, 'Top two combined must be >= 95');

        // OQ-02: digerleri her biri < 5.
        $this->assertLessThan(5.0, $che, 'Chelsea must be < 5');
        $this->assertLessThan(5.0, $ars, 'Arsenal must be < 5');

        // US-E-02: sum tam olarak 100.
        $this->assertSame(100.0, round(array_sum($percentages), 2));
    }

    /** US-E-02: largest-remainder method invariant -- sum == 100 her zaman. */
    public function test_largest_remainder_yields_sum_exactly_100(): void
    {
        $counts = [1 => 333, 2 => 334, 3 => 333]; // 1000 total
        $pred = $this->predictor();
        $out = $pred->normalizeWithLargestRemainder($counts, 1000);
        $this->assertSame(100.0, array_sum($out));
    }

    /**
     * US-E-02 negative path: counts hepsi 0 (highly unlikely / N=0) icin
     * tum yuzdeler 0 olur (sum 0 -- bu durum tetik kosulu disinda kalsa da
     * normalize fonksiyonu dogru calismali).
     */
    public function test_largest_remainder_handles_all_zero_counts(): void
    {
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $pred = $this->predictor();
        $out = $pred->normalizeWithLargestRemainder($counts, 1000);
        $this->assertSame([1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0], $out);
        $this->assertSame(0.0, array_sum($out));
    }

    /** US-E-02: 100/0/0/0 dagilimi -- sum hala 100. */
    public function test_largest_remainder_handles_single_winner(): void
    {
        $counts = [1 => 1000, 2 => 0, 3 => 0, 4 => 0];
        $pred = $this->predictor();
        $out = $pred->normalizeWithLargestRemainder($counts, 1000);
        $this->assertSame(100.0, $out[1]);
        $this->assertSame(0.0, $out[2]);
        $this->assertSame(100.0, array_sum($out));
    }

    /**
     * US-E-02 binary-drift case: 1/3 dagilimi normalde 33.33 + 33.33 + 33.34 verir.
     * IEEE-754 -> her 33.33 yaklasik 33.329999... olur; absorber pattern
     * sum'i tam 100.0'a tasimakla yukumlu (Sterbenz lemma).
     */
    public function test_largest_remainder_thirds_sum_to_exactly_100(): void
    {
        $counts = [1 => 3333, 2 => 3333, 3 => 3334];
        $pred = $this->predictor();
        $out = $pred->normalizeWithLargestRemainder($counts, 10000);
        // Critical invariant: array_sum tam olarak 100.0 (binary drift'e bagisik).
        $this->assertSame(100.0, array_sum($out), 'Sum must equal 100.0 exactly');
        // Her paya ~33.33 / ~33.34 yakin olmali (1e-9 tolerans -- IEEE-754 drift).
        foreach ($out as $pct) {
            $this->assertTrue(
                abs($pct - 33.33) < 1e-9 || abs($pct - 33.34) < 1e-9,
                "Share must be ~33.33 or ~33.34, got {$pct}"
            );
        }
    }

    public function test_predictions_snapshot_persisted(): void
    {
        $teams = TeamPowerProfiles::seed();
        FaqExampleA::setup($teams);
        $settings = LeagueSettings::query()->find((int) config('league.settings_id', 1));

        $this->predictor()->predictAndSnapshot($settings);

        $count = Prediction::query()->where('week', $settings->current_week)->count();
        $this->assertSame(4, $count);
    }

    /**
     * US-E-01: Tetik kosulu currentWeek > totalWeeks - prediction_window (default window=3).
     * Erken haftada predictor tetiklenmemeli (bos array donmeli).
     */
    public function test_predictor_does_not_trigger_before_window(): void
    {
        TeamPowerProfiles::seed();
        $settings = LeagueSettings::query()->updateOrCreate(
            ['id' => (int) config('league.settings_id', 1)],
            [
                'team_count' => 4,
                'current_week' => 1,
                'total_weeks' => 6,
                'seed' => 42,
                'status' => LeagueSettings::STATUS_IDLE,
            ]
        );
        $out = $this->predictor()->predictAndSnapshot($settings);
        $this->assertSame([], $out, 'Trigger window: week 1 / total 6 -> not triggered');
        $this->assertSame(0, Prediction::query()->count(), 'No snapshot before trigger');
    }
}