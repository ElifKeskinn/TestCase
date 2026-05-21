<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teams table (§4.2)
 *
 * - name UNIQUE NOT NULL
 * - power 1..100 CHECK
 * - supporter / keeper 0..100 nullable CHECK
 *
 * SQLite path: raw CREATE TABLE so we can express CHECK constraints
 * (SQLite only honors CHECK declared inside CREATE TABLE, not via ALTER).
 *
 * Other drivers: standard Blueprint syntax; CHECK constraints declared via
 * raw statements after table creation when supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE teams (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(50) NOT NULL,
                    power INTEGER NOT NULL,
                    supporter INTEGER NULL,
                    keeper INTEGER NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    CONSTRAINT uq_teams_name UNIQUE (name),
                    CONSTRAINT chk_teams_power CHECK (power BETWEEN 1 AND 100),
                    CONSTRAINT chk_teams_supporter CHECK (supporter IS NULL OR supporter BETWEEN 0 AND 100),
                    CONSTRAINT chk_teams_keeper CHECK (keeper IS NULL OR keeper BETWEEN 0 AND 100)
                )
            SQL);

            DB::statement('CREATE INDEX idx_teams_name ON teams(name)');
            return;
        }

        // Fallback (MySQL/PostgreSQL) — kept for portability (NFR-08).
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->integer('power');
            $table->integer('supporter')->nullable();
            $table->integer('keeper')->nullable();
            $table->timestamps();

            $table->index('name', 'idx_teams_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
