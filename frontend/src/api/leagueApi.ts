import axios, { AxiosError, type AxiosInstance } from 'axios';
import type { LeagueState, ApiErrorPayload } from '@/types/league';

/**
 * Axios instance for the league API.
 *
 * Base URL resolution:
 *  - `VITE_API_BASE_URL` env (production / direct dev) — e.g. https://your-backend.up.railway.app
 *  - Empty string (default) → relies on Vite dev proxy (`/api` → `http://localhost:8000`).
 *
 * Stateless API: `withCredentials = false` (matches NFR-19 — CORS allowlist, no cookies).
 */
const baseURL = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '');

export const apiClient: AxiosInstance = axios.create({
  baseURL,
  withCredentials: false,
  timeout: 15_000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

/**
 * Normalized API error: keeps the HTTP status and a user-friendly message
 * so the Pinia store can map 409/422/423/429 to specific toasts (US-A-06 AC-4).
 */
export class ApiError extends Error {
  public readonly status: number;
  public readonly payload: ApiErrorPayload | undefined;

  constructor(message: string, status: number, payload?: ApiErrorPayload) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

function toApiError(err: unknown): ApiError {
  if (axios.isAxiosError(err)) {
    const axiosErr = err as AxiosError<ApiErrorPayload>;
    const status = axiosErr.response?.status ?? 0;
    const payload = axiosErr.response?.data;
    const message = payload?.message ?? axiosErr.message ?? 'Network error';
    return new ApiError(message, status, payload);
  }
  if (err instanceof Error) {
    return new ApiError(err.message, 0);
  }
  return new ApiError('Unknown error', 0);
}

async function call<T>(fn: () => Promise<{ data: T }>): Promise<T> {
  try {
    const res = await fn();
    return res.data;
  } catch (err) {
    throw toApiError(err);
  }
}

// -- League endpoints (docs/DEVELOPMENT_DOCUMENT.md §3.3) -------------------

export function getLeagueState(): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.get('/api/league/state'));
}

export function generateFixtures(): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.post('/api/league/generate-fixtures'));
}

export interface PlayNextWeekPayload {
  expected_week: number;
}

export function playNextWeek(payload: PlayNextWeekPayload): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.post('/api/league/play-next-week', payload));
}

export function playAllWeeks(): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.post('/api/league/play-all-weeks'));
}

export function resetLeague(): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.post('/api/league/reset'));
}

export function getPredictions(): Promise<LeagueState['predictions']> {
  return call<LeagueState['predictions']>(() => apiClient.get('/api/league/predictions'));
}

export interface EditMatchPayload {
  home_score: number;
  away_score: number;
  expected_version: number;
}

/**
 * PATCH /api/matches/:id
 *
 * Backend returns the full LeagueState envelope (standings + predictions are
 * recomputed in the same transaction as the score edit). The store calls
 * `applyState(state)` so a single round-trip refreshes the entire UI.
 */
export function editMatch(id: number, payload: EditMatchPayload): Promise<LeagueState> {
  return call<LeagueState>(() => apiClient.patch(`/api/matches/${id}`, payload));
}
