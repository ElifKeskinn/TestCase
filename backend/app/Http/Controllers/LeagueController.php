<?php

namespace App\Http\Controllers;

use App\Domain\Actions\GenerateFixturesAction;
use App\Domain\Actions\PlayAllWeeksAction;
use App\Domain\Actions\PlayWeekAction;
use App\Domain\Actions\ResetLeagueAction;
use App\Domain\Services\LeagueStateBuilder;
use App\Http\Requests\PlayNextWeekRequest;
use Illuminate\Http\JsonResponse;

/**
 * LeagueController — thin orchestrator over domain actions.
 *
 * Every read AND mutation endpoint returns the full LeagueState envelope
 * (see LeagueStateBuilder) so the SPA can replace its store with a single
 * payload — no follow-up GET /state is required after a mutation.
 */
class LeagueController extends Controller
{
    public function __construct(
        private readonly LeagueStateBuilder $builder,
    ) {}

    /**
     * GET /api/league/state
     * Returns the full LeagueState envelope.
     */
    public function state(): JsonResponse
    {
        return response()->json($this->builder->build());
    }

    public function generateFixtures(GenerateFixturesAction $action): JsonResponse
    {
        $action->execute();
        return response()->json($this->builder->build(), 200);
    }

    public function playNextWeek(PlayNextWeekRequest $request, PlayWeekAction $action): JsonResponse
    {
        $action->execute((int) $request->validated('expected_week'));
        return response()->json($this->builder->build(), 200);
    }

    public function playAllWeeks(PlayAllWeeksAction $action): JsonResponse
    {
        $action->execute();
        return response()->json($this->builder->build(), 200);
    }

    public function reset(ResetLeagueAction $action): JsonResponse
    {
        $action->execute();
        return response()->json($this->builder->build(), 200);
    }

    /**
     * GET /api/league/predictions
     * Returns the predictions array (matches frontend Prediction[] contract).
     */
    public function predictions(): JsonResponse
    {
        $state = $this->builder->build();
        return response()->json($state['predictions'], 200);
    }
}
