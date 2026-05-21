<?php

namespace App\Domain\Actions;

use App\Domain\Services\ChampionshipPredictor;
use App\Domain\Services\StandingsCalculator;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\LeagueLockedException;
use App\Models\LeagueSettings;
use App\Models\MatchModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UpdateMatchResultAction (§4.5.4 optimistic lock + US-G-03)
 *
 * - Single DB::transaction()
 * - Pessimistic lock on league_settings (state machine)
 * - Optimistic UPDATE with WHERE version = ? → affected=0 ⇒ 409 Conflict
 * - Recomputes standings + predictions atomically
 * - Structured audit log (NFR-20)
 */
final class UpdateMatchResultAction
{
    public function __construct(
        private readonly StandingsCalculator $standings,
        private readonly ChampionshipPredictor $predictor,
    ) {}

    public function execute(int $matchId, int $homeScore, int $awayScore, int $expectedVersion, ?string $clientIp = null): array
    {
        return DB::transaction(function () use ($matchId, $homeScore, $awayScore, $expectedVersion, $clientIp) {
            $settings = LeagueSettings::singleton();
            $this->guardMutable($settings);

            /** @var MatchModel|null $match */
            $match = MatchModel::query()->lockForUpdate()->find($matchId);
            if ($match === null) {
                // Rendered as JSON via bootstrap/app.php NotFoundHttpException handler.
                throw new NotFoundHttpException('Match not found.');
            }
            if ($match->played_at === null || $match->home_score === null || $match->away_score === null) {
                // Laravel renders ValidationException as 422 JSON for API requests.
                throw ValidationException::withMessages([
                    'match' => ['Cannot edit a match that has not been played yet.'],
                ]);
            }

            $oldHome = (int) $match->home_score;
            $oldAway = (int) $match->away_score;

            // Optimistic UPDATE — affected = 0 ⇒ 409.
            $affected = DB::table('matches')
                ->where('id', $matchId)
                ->where('version', $expectedVersion)
                ->update([
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'version' => DB::raw('version + 1'),
                    'editions_count' => DB::raw('editions_count + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw new IdempotencyConflictException(
                    "Optimistic lock failed: expected_version={$expectedVersion} no longer matches."
                );
            }

            $this->standings->recompute();
            $this->predictor->predictAndSnapshot($settings);

            Log::info('match.edited', [
                'match_id' => $matchId,
                'old_home' => $oldHome, 'old_away' => $oldAway,
                'new_home' => $homeScore, 'new_away' => $awayScore,
                'expected_version' => $expectedVersion,
                'ip' => $clientIp,
                'ts' => now()->toIso8601String(),
            ]);

            $fresh = MatchModel::query()->findOrFail($matchId);
            return [
                'id' => $fresh->id,
                'home_score' => $fresh->home_score,
                'away_score' => $fresh->away_score,
                'version' => $fresh->version,
                'editions_count' => $fresh->editions_count,
            ];
        });
    }

    private function guardMutable(LeagueSettings $s): void
    {
        if (in_array($s->status, [LeagueSettings::STATUS_RUNNING, LeagueSettings::STATUS_RESETTING], true)) {
            throw new LeagueLockedException();
        }
    }
}
