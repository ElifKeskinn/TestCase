<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * league_settings table (singleton, §4.2 + §4.5.2)
 *
 * - team_count 2..8 CHECK
 * - current_week >= 0 CHECK
 * - total_weeks = 2*(team_count - 1)  (application invariant)
 * - status ENUM('idle','running','resetting','finished')
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE league_settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    team_count INTEGER NOT NULL,
                    current_week INTEGER NOT NULL DEFAULT 0,
                    total_weeks INTEGER NOT NULL,
                    seed BIGINT NULL,
                    status VARCHAR(16) NOT NULL DEFAULT 'idle',
                    status_updated_at DATETIME NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    CONSTRAINT chk_ls_team_count CHECK (team_count BETWEEN 2 AND 8),
                    CONSTRAINT chk_ls_current_week CHECK (current_week >= 0),
                    CONSTRAINT chk_ls_total_weeks CHECK (total_weeks >= 0),
                    CONSTRAINT chk_ls_status CHECK (status IN ('idle','running','resetting','finished'))
                )
            SQL);
            return;
        }

        Schema::create('league_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('team_count');
            $table->integer('current_week')->default(0);
            $table->integer('total_weeks');
            $table->bigInteger('seed')->nullable();
            $table->string('status', 16)->default('idle');
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_settings');
    }
};
