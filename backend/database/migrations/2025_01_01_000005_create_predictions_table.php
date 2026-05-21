<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * predictions table — snapshot, per team × week (§4.2)
 *
 * - champion_probability decimal(5,2) CHECK 0..100
 * - UNIQUE (team_id, week)
 * - INDEX (week)
 *
 * NOTE: "sum(probability) per week == 100" is an APPLICATION-level invariant
 *       (PredictionService + largest remainder method); cannot be expressed
 *       as a single-row CHECK. Verified via PHPUnit (US-E-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE predictions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_id INTEGER NOT NULL,
                    week INTEGER NOT NULL,
                    champion_probability DECIMAL(5,2) NOT NULL,
                    computed_at DATETIME NOT NULL,
                    CONSTRAINT fk_predictions_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                    CONSTRAINT chk_predictions_prob CHECK (champion_probability BETWEEN 0 AND 100),
                    CONSTRAINT chk_predictions_week CHECK (week >= 1),
                    CONSTRAINT uq_predictions_team_week UNIQUE (team_id, week)
                )
            SQL);

            DB::statement('CREATE INDEX idx_predictions_week ON predictions(week)');
            return;
        }

        Schema::create('predictions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->integer('week');
            $table->decimal('champion_probability', 5, 2);
            $table->timestamp('computed_at');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->unique(['team_id', 'week'], 'uq_predictions_team_week');
            $table->index('week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
