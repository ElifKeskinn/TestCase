<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for POST /api/league/play-next-week (§4.5.5).
 */
class PlayNextWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_week' => ['required', 'integer', 'min:1', 'max:64'],
        ];
    }
}
