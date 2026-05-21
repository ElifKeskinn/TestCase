import { setActivePinia, createPinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useLeagueStore } from '@/stores/league';
import * as api from '@/api/leagueApi';
import { ApiError } from '@/api/leagueApi';
import {
  STATE_AFTER_WEEK_1,
  STATE_INIT,
  STATE_WITH_FIXTURES,
} from '../fixtures';

describe('useLeagueStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('fetchState() hydrates teams / matches / settings', async () => {
    vi.spyOn(api, 'getLeagueState').mockResolvedValueOnce(STATE_AFTER_WEEK_1);
    const store = useLeagueStore();
    await store.fetchState();
    expect(store.teams.length).toBe(4);
    expect(store.matches.length).toBeGreaterThan(0);
    expect(store.currentWeek).toBe(1);
    expect(store.totalWeeks).toBe(6);
  });

  it('isMutating toggles true during a mutation and resets in finally', async () => {
    const store = useLeagueStore();

    let inFlightFlag = false;
    vi.spyOn(api, 'generateFixtures').mockImplementation(() => {
      inFlightFlag = store.isMutating;
      return Promise.resolve(STATE_WITH_FIXTURES);
    });

    expect(store.isMutating).toBe(false);
    const promise = store.generateFixtures();
    // Synchronously before the network call resolves, isMutating must be true.
    expect(store.isMutating).toBe(true);
    await promise;
    expect(inFlightFlag).toBe(true);
    expect(store.isMutating).toBe(false);
  });

  it('isMutating resets even on error and toasts the message', async () => {
    const store = useLeagueStore();
    vi.spyOn(api, 'playAllWeeks').mockRejectedValueOnce(
      new ApiError('Locked', 423, { message: 'Locked' }),
    );

    await store.playAllWeeks();

    expect(store.isMutating).toBe(false);
    expect(store.error).toMatch(/long-running/i);
    expect(store.toasts.length).toBeGreaterThan(0);
    expect(store.toasts[0]?.tone).toBe('error');
  });

  it('maps HTTP statuses to friendly toasts (409 / 422 / 423 / 429)', async () => {
    const cases: Array<[number, RegExp]> = [
      [409, /conflict/i],
      [422, /validation/i],
      [423, /long-running/i],
      [429, /too many/i],
    ];

    for (const [status, pattern] of cases) {
      setActivePinia(createPinia());
      const store = useLeagueStore();
      vi.spyOn(api, 'playNextWeek').mockRejectedValueOnce(
        new ApiError('err', status, status === 422 ? undefined : { message: 'err' }),
      );
      await store.playNextWeek();
      expect(store.error, `status ${status}`).toMatch(pattern);
    }
  });

  it('playNextWeek() sends expected_week = currentWeek + 1', async () => {
    vi.spyOn(api, 'getLeagueState').mockResolvedValueOnce(STATE_AFTER_WEEK_1);
    const store = useLeagueStore();
    await store.fetchState();
    expect(store.currentWeek).toBe(1);

    const spy = vi
      .spyOn(api, 'playNextWeek')
      .mockResolvedValueOnce({ ...STATE_AFTER_WEEK_1, settings: { ...STATE_AFTER_WEEK_1.settings, current_week: 2 } });

    await store.playNextWeek();
    expect(spy).toHaveBeenCalledWith({ expected_week: 2 });
    expect(store.currentWeek).toBe(2);
  });

  it('shouldShowPredictions follows currentWeek > totalWeeks - 3 (US-E-01 AC-4)', async () => {
    const store = useLeagueStore();
    // Hydrate
    vi.spyOn(api, 'getLeagueState').mockResolvedValueOnce(STATE_INIT);
    await store.fetchState();

    // Manually walk through the formula edges.
    store.settings.total_weeks = 6;
    store.settings.current_week = 3; // 3 > 6 - 3 = 3 → false
    expect(store.shouldShowPredictions).toBe(false);

    store.settings.current_week = 4; // 4 > 3 → true
    expect(store.shouldShowPredictions).toBe(true);

    // Flexibility check (US-B-03 / NFR-10): same formula works for N=6 totalWeeks=10.
    store.settings.total_weeks = 10;
    store.settings.current_week = 8;
    expect(store.shouldShowPredictions).toBe(true);
    store.settings.current_week = 7;
    expect(store.shouldShowPredictions).toBe(false);
  });

  it('sortedStandings sorts by PTS desc → GD desc → GF desc → name asc', async () => {
    const store = useLeagueStore();
    store.standings = [
      // PTS tie at 9, GD tie at 0, GF differs.
      { team_id: 1, team_name: 'Zeta', played: 5, won: 3, drawn: 0, lost: 2, goals_for: 10, goals_against: 10, goal_diff: 0, points: 9 },
      { team_id: 2, team_name: 'Alpha', played: 5, won: 3, drawn: 0, lost: 2, goals_for: 12, goals_against: 12, goal_diff: 0, points: 9 },
      // Higher PTS.
      { team_id: 3, team_name: 'Beta', played: 5, won: 4, drawn: 0, lost: 1, goals_for: 8, goals_against: 4, goal_diff: 4, points: 12 },
      // Same PTS/GD/GF as Alpha → name asc.
      { team_id: 4, team_name: 'Alpha2', played: 5, won: 3, drawn: 0, lost: 2, goals_for: 12, goals_against: 12, goal_diff: 0, points: 9 },
    ];

    const sorted = store.sortedStandings;
    expect(sorted[0].team_name).toBe('Beta'); // highest PTS
    // Then GF desc (12 > 10), then alphabetical.
    expect(sorted[1].team_name).toBe('Alpha');
    expect(sorted[2].team_name).toBe('Alpha2');
    expect(sorted[3].team_name).toBe('Zeta');
  });

  it('happy path: setup → generate → play next → play all → reset', async () => {
    const store = useLeagueStore();

    vi.spyOn(api, 'getLeagueState').mockResolvedValueOnce(STATE_INIT);
    await store.fetchState();
    expect(store.hasFixtures).toBe(false);

    vi.spyOn(api, 'generateFixtures').mockResolvedValueOnce(STATE_WITH_FIXTURES);
    await store.generateFixtures();
    expect(store.hasFixtures).toBe(true);
    expect(store.currentWeek).toBe(0);

    vi.spyOn(api, 'playNextWeek').mockResolvedValueOnce(STATE_AFTER_WEEK_1);
    await store.playNextWeek();
    expect(store.currentWeek).toBe(1);

    vi.spyOn(api, 'playAllWeeks').mockResolvedValueOnce({
      ...STATE_AFTER_WEEK_1,
      settings: { ...STATE_AFTER_WEEK_1.settings, current_week: 6, status: 'finished' },
    });
    await store.playAllWeeks();
    expect(store.currentWeek).toBe(6);
    expect(store.isSeasonFinished).toBe(true);

    vi.spyOn(api, 'resetLeague').mockResolvedValueOnce(STATE_INIT);
    await store.resetLeague();
    expect(store.hasFixtures).toBe(false);
    expect(store.currentWeek).toBe(0);
  });
});
