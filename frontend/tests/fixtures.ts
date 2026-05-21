import type {
  LeagueState,
  Match,
  Prediction,
  Standing,
  Team,
} from '@/types/league';

export const TEAMS: Team[] = [
  { id: 1, name: 'Liverpool', power: 88 },
  { id: 2, name: 'Manchester City', power: 90 },
  { id: 3, name: 'Chelsea', power: 82 },
  { id: 4, name: 'Arsenal', power: 80 },
];

export const STANDINGS_AFTER_WEEK_1: Standing[] = [
  {
    team_id: 2,
    team_name: 'Manchester City',
    played: 1,
    won: 1,
    drawn: 0,
    lost: 0,
    goals_for: 3,
    goals_against: 1,
    goal_diff: 2,
    points: 3,
  },
  {
    team_id: 3,
    team_name: 'Chelsea',
    played: 1,
    won: 1,
    drawn: 0,
    lost: 0,
    goals_for: 2,
    goals_against: 1,
    goal_diff: 1,
    points: 3,
  },
  {
    team_id: 4,
    team_name: 'Arsenal',
    played: 1,
    won: 0,
    drawn: 0,
    lost: 1,
    goals_for: 1,
    goals_against: 2,
    goal_diff: -1,
    points: 0,
  },
  {
    team_id: 1,
    team_name: 'Liverpool',
    played: 1,
    won: 0,
    drawn: 0,
    lost: 1,
    goals_for: 1,
    goals_against: 3,
    goal_diff: -2,
    points: 0,
  },
];

export const MATCHES_WEEK_1_PLAYED: Match[] = [
  {
    id: 1,
    week: 1,
    home_team_id: 4,
    away_team_id: 1,
    home_team: 'Arsenal',
    away_team: 'Liverpool',
    home_score: 1,
    away_score: 3,
    played_at: '2026-05-21T10:00:00Z',
    version: 1,
  },
  {
    id: 2,
    week: 1,
    home_team_id: 2,
    away_team_id: 3,
    home_team: 'Manchester City',
    away_team: 'Chelsea',
    home_score: 2,
    away_score: 1,
    played_at: '2026-05-21T10:00:00Z',
    version: 1,
  },
];

export const MATCHES_FULL_FIXTURE: Match[] = [
  ...MATCHES_WEEK_1_PLAYED,
  {
    id: 3,
    week: 2,
    home_team_id: 1,
    away_team_id: 2,
    home_team: 'Liverpool',
    away_team: 'Manchester City',
    home_score: null,
    away_score: null,
    played_at: null,
    version: 1,
  },
  {
    id: 4,
    week: 2,
    home_team_id: 3,
    away_team_id: 4,
    home_team: 'Chelsea',
    away_team: 'Arsenal',
    home_score: null,
    away_score: null,
    played_at: null,
    version: 1,
  },
];

export const PREDICTIONS_WEEK_5: Prediction[] = [
  { team_id: 3, team_name: 'Chelsea', week: 5, champion_probability: 60 },
  { team_id: 4, team_name: 'Arsenal', week: 5, champion_probability: 20 },
  { team_id: 2, team_name: 'Manchester City', week: 5, champion_probability: 15 },
  { team_id: 1, team_name: 'Liverpool', week: 5, champion_probability: 5 },
];

export const STATE_INIT: LeagueState = {
  settings: {
    team_count: 4,
    current_week: 0,
    total_weeks: 0,
    status: 'idle',
    seed: null,
  },
  teams: TEAMS,
  matches: [],
  standings: [],
  predictions: [],
};

export const STATE_WITH_FIXTURES: LeagueState = {
  settings: {
    team_count: 4,
    current_week: 0,
    total_weeks: 6,
    status: 'idle',
    seed: null,
  },
  teams: TEAMS,
  matches: MATCHES_FULL_FIXTURE,
  standings: [],
  predictions: [],
};

export const STATE_AFTER_WEEK_1: LeagueState = {
  settings: {
    team_count: 4,
    current_week: 1,
    total_weeks: 6,
    status: 'idle',
    seed: null,
  },
  teams: TEAMS,
  matches: MATCHES_FULL_FIXTURE,
  standings: STANDINGS_AFTER_WEEK_1,
  predictions: [],
};
