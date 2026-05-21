<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * standings table — snapshot, write-through (§4.2)
 *
 * - team_id UNIQUE (one row per team)
 * - CHECK P = W + D + L
 * - CHECK GD = GF - GA
 * - CHECK PTS = 3*W + D
 * - INDEX (points DESC, goal_diff DESC, goals_for DESC, team_id ASC)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE standings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    played INTEGER NOT NULL DEFAULT 0,
                    won INTEGER NOT NULL DEFAULT 0,
                    drawn INTEGER NOT NULL DEFAULT 0,
                    lost INTEGER NOT NULL DEFAULT 0,
                    goals_for INTEGER NOT NULL DEFAULT 0,
                    goals_against INTEGER NOT NULL DEFAULT 0,
                    goal_diff INTEGER NOT NULL DEFAULT 0,
                    points INTEGER NOT NULL DEFAULT 0,
                    updated_at DATETIME NULL,
                    CONSTRAINT fk_standings_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                    CONSTRAINT uq_standings_team UNIQUE (team_id),
                    CONSTRAINT chk_standings_played CHECK (played = won + drawn + lost),
                    CONSTRAINT chk_standings_gd CHECK (goal_diff = goals_for - goals_against),
                    CONSTRAINT chk_standings_points CHECK (points = 3*won + drawn),
                    CONSTRAINT chk_standings_nonneg CHECK (
                        played >= 0 AND won >= 0 AND drawn >= 0 AND lost >= 0
                        AND goals_for >= 0 AND goals_against >= 0
                    )
                )
            SQL);

            DB::statement(
                'CREATE INDEX idx_standings_sort '
                .'ON standings(points DESC, goal_diff DESC, goals_for DESC, team_id ASC)'
            );
            return;
        }

        Schema::create('standings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->integer('played')->default(0);
            $table->integer('won')->default(0);
            $table->integer('drawn')->default(0);
            $table->integer('lost')->default(0);
            $table->integer('goals_for')->default(0);
            $table->integer('goals_against')->default(0);
            $table->integer('goal_diff')->default(0);
            $table->integer('points')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->unique('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standings');
    }
};
