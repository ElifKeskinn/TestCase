<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Team Eloquent model.
 *
 * @property int    $id
 * @property string $name
 * @property int    $power
 * @property ?int   $supporter
 * @property ?int   $keeper
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'power', 'supporter', 'keeper'];

    protected $casts = [
        'power' => 'integer',
        'supporter' => 'integer',
        'keeper' => 'integer',
    ];

    public function standing(): HasOne
    {
        return $this->hasOne(Standing::class, 'team_id');
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'away_team_id');
    }
}
