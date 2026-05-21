<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for PATCH /api/matches/{id} (US-G-03 AC-1..5).
 *
 * - home_score / away_score: integer in [0..20] (matches CHECK constraint).
 * - expected_version: integer >= 1 (optimistic lock, §4.5.4).
 */
class EditMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('league.min_score', 0);
        $max = (int) config('league.max_score', 20);
        return [
            'home_score' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'away_score' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
