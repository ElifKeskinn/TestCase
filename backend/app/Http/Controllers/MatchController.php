<?php

namespace App\Http\Controllers;

use App\Domain\Actions\UpdateMatchResultAction;
use App\Domain\Services\LeagueStateBuilder;
use App\Http\Requests\EditMatchRequest;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function __construct(
        private readonly LeagueStateBuilder $builder,
    ) {}

    /**
     * PATCH /api/matches/{id}
     * Body: home_score, away_score, expected_version.
     *
     * Returns the full LeagueState envelope so the SPA can replace its
     * reactive store atomically (standings + predictions both depend on
     * the edited score and are recomputed in the same transaction).
     */
    public function update(EditMatchRequest $request, int $id, UpdateMatchResultAction $action): JsonResponse
    {
        $payload = $request->validated();
        $action->execute(
            $id,
            (int) $payload['home_score'],
            (int) $payload['away_score'],
            (int) $payload['expected_version'],
            $request->ip(),
        );
        return response()->json($this->builder->build(), 200);
    }
}
