/**
 * Domain types — mirrors the backend payloads documented in
 * docs/DEVELOPMENT_DOCUMENT.md §3.3 (REST API) and §4.2 (Schema).
 *
 * Keep these in sync with the Laravel API. If the backend renames a field
 * (e.g. snake_case ↔ camelCase), update both sides.
 */

export type LeagueStatus = 'idle' | 'running' | 'resetting' | 'finished';

export interface Team {
  id: number;
  name: string;
  power?: number;
  supporter?: number | null;
  keeper?: number | null;
}

export interface Match {
  id: number;
  week: number;
  home_team_id: number;
  away_team_id: number;
  home_team?: string;
  away_team?: string;
  home_score: number | null;
  away_score: number | null;
  played_at: string | null;
  version: number;
}

export interface Standing {
  team_id: number;
  team_name: string;
  played: number;
  won: number;
  drawn: number;
  lost: number;
  goals_for: number;
  goals_against: number;
  goal_diff: number;
  points: number;
}

export interface Prediction {
  team_id: number;
  team_name: string;
  week: number;
  champion_probability: number;
}

export interface LeagueSettings {
  team_count: number;
  current_week: number;
  total_weeks: number;
  status: LeagueStatus;
  seed?: number | null;
}

export interface LeagueState {
  settings: LeagueSettings;
  teams: Team[];
  matches: Match[];
  standings: Standing[];
  predictions: Prediction[];
}

export interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
  code?: string;
}
