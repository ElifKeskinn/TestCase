<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * matches table (§4.2)
 *
 * - CHECK home_team_id != away_team_id
 * - CHECK score both-or-neither null
 * - CHECK home_score / away_score between 0..20 when not null
 * - UNIQUE (week, home_team_id, away_team_id)
 * - Optimistic lock: version (NOT NULL DEFAULT 1)
 * - Audit: editions_count (NOT NULL DEFAULT 0)
 *
 * NOTE: "match" is a PHP reserved word — the table name is fine, only the
 *       Eloquent model is renamed to MatchModel (see app/Models/MatchModel.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE matches (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    week INTEGER NOT NULL,
                    home_team_id INTEGER NOT NULL,
                    away_team_id INTEGER NOT NULL,
                    home_score INTEGER NULL,
                    away_score INTEGER NULL,
                    played_at DATETIME NULL,
                    version INTEGER NOT NULL DEFAULT 1,
                    editions_count INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    CONSTRAINT fk_matches_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE RESTRICT,
                    CONSTRAINT fk_matches_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE RESTRICT,
                    CONSTRAINT chk_matches_week CHECK (week >= 1),
                    CONSTRAINT chk_matches_diff_teams CHECK (home_team_id <> away_team_id),
                    CONSTRAINT chk_matches_home_score CHECK (home_score IS NULL OR home_score BETWEEN 0 AND 20),
                    CONSTRAINT chk_matches_away_score CHECK (away_score IS NULL OR away_score BETWEEN 0 AND 20),
                    CONSTRAINT chk_matches_score_both CHECK (
                        (home_score IS NULL AND away_score IS NULL)
                        OR (home_score IS NOT NULL AND away_score IS NOT NULL)
                    ),
                    CONSTRAINT chk_matches_version CHECK (version >= 1),
                    CONSTRAINT chk_matches_editions CHECK (editions_count >= 0),
                    CONSTRAINT uq_matches_week_home_away UNIQUE (week, home_team_id, away_team_id)
                )
            SQL);

            DB::statement('CREATE INDEX idx_matches_week ON matches(week)');
            DB::statement('CREATE INDEX idx_matches_home_team_id ON matches(home_team_id)');
            DB::statement('CREATE INDEX idx_matches_away_team_id ON matches(away_team_id)');
            return;
        }

        // Fallback (MySQL/PostgreSQL) — kept for portability (NFR-08).
        Schema::create('matches', function ($table) {
            $table->id();
            $table->integer('week');
            $table->unsignedBigInteger('home_team_id');
            $table->unsignedBigInteger('away_team_id');
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->timestamp('played_at')->nullable();
            $table->integer('version')->default(1);
            $table->integer('editions_count')->default(0);
            $table->timestamps();
            $table->foreign('home_team_id')->references('id')->on('teams')->restrictOnDelete();
            $table->foreign('away_team_id')->references('id')->on('teams')->restrictOnDelete();
            $table->unique(['week', 'home_team_id', 'away_team_id'], 'uq_matches_week_home_away');
            $table->index('week');
            $table->index('home_team_id');
            $table->index('away_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
