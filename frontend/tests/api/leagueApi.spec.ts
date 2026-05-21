import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ApiError,
  apiClient,
  editMatch,
  generateFixtures,
  getLeagueState,
  getPredictions,
  playAllWeeks,
  playNextWeek,
  resetLeague,
} from '@/api/leagueApi';
import { STATE_INIT, PREDICTIONS_WEEK_5, STATE_AFTER_WEEK_1 } from '../fixtures';

interface RecordedCall {
  method: string;
  url: string;
  data?: unknown;
}

describe('leagueApi', () => {
  const calls: RecordedCall[] = [];

  beforeEach(() => {
    calls.length = 0;
    vi.spyOn(apiClient, 'get').mockImplementation((url: string) => {
      calls.push({ method: 'GET', url });
      if (url === '/api/league/state') {
        return Promise.resolve({ data: STATE_INIT }) as any;
      }
      if (url === '/api/league/predictions') {
        return Promise.resolve({ data: PREDICTIONS_WEEK_5 }) as any;
      }
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    vi.spyOn(apiClient, 'post').mockImplementation((url: string, data?: unknown) => {
      calls.push({ method: 'POST', url, data });
      return Promise.resolve({ data: STATE_INIT }) as any;
    });

    vi.spyOn(apiClient, 'patch').mockImplementation((url: string, data?: unknown) => {
      calls.push({ method: 'PATCH', url, data });
      // Backend now returns the full LeagueState envelope on PATCH /matches/:id.
      return Promise.resolve({ data: STATE_AFTER_WEEK_1 }) as any;
    });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('GET /api/league/state returns LeagueState', async () => {
    const state = await getLeagueState();
    expect(calls[0]).toEqual({ method: 'GET', url: '/api/league/state' });
    expect(state.teams.length).toBe(4);
  });

  it('POST /api/league/generate-fixtures', async () => {
    await generateFixtures();
    expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/league/generate-fixtures' });
  });

  it('POST /api/league/play-next-week sends expected_week (idempotency)', async () => {
    await playNextWeek({ expected_week: 3 });
    expect(calls[0]).toEqual({
      method: 'POST',
      url: '/api/league/play-next-week',
      data: { expected_week: 3 },
    });
  });

  it('POST /api/league/play-all-weeks', async () => {
    await playAllWeeks();
    expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/league/play-all-weeks' });
  });

  it('POST /api/league/reset', async () => {
    await resetLeague();
    expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/league/reset' });
  });

  it('GET /api/league/predictions', async () => {
    const p = await getPredictions();
    expect(calls[0]).toMatchObject({ method: 'GET', url: '/api/league/predictions' });
    expect(p.length).toBe(4);
  });

  it('PATCH /api/matches/:id sends home/away/expected_version (optimistic lock)', async () => {
    await editMatch(42, { home_score: 2, away_score: 1, expected_version: 5 });
    expect(calls[0]).toEqual({
      method: 'PATCH',
      url: '/api/matches/42',
      data: { home_score: 2, away_score: 1, expected_version: 5 },
    });
  });

  it('wraps axios errors in ApiError with status', async () => {
    vi.spyOn(apiClient, 'post').mockImplementationOnce(() => {
      const err: any = new Error('Conflict');
      err.isAxiosError = true;
      err.response = { status: 409, data: { message: 'expected_week mismatch' } };
      // Mimic axios.isAxiosError detection.
      err.toJSON = () => ({});
      return Promise.reject(err);
    });

    try {
      await playNextWeek({ expected_week: 1 });
      throw new Error('expected ApiError');
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      const apiErr = err as ApiError;
      expect(apiErr.status).toBe(409);
      expect(apiErr.message).toBe('expected_week mismatch');
    }
  });
});
