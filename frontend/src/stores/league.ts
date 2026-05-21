import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
  ApiError,
  editMatch as editMatchApi,
  generateFixtures as generateFixturesApi,
  getLeagueState as getLeagueStateApi,
  playAllWeeks as playAllWeeksApi,
  playNextWeek as playNextWeekApi,
  resetLeague as resetLeagueApi,
} from '@/api/leagueApi';
import type {
  LeagueSettings,
  LeagueState,
  LeagueStatus,
  Match,
  Prediction,
  Standing,
  Team,
} from '@/types/league';

const DEFAULT_SETTINGS: LeagueSettings = {
  team_count: 0,
  current_week: 0,
  total_weeks: 0,
  status: 'idle',
  seed: null,
};

export type ToastTone = 'info' | 'success' | 'error' | 'warning';

export interface ToastItem {
  id: number;
  tone: ToastTone;
  message: string;
}

/**
 * Maps documented HTTP error codes from the backend (DEVELOPMENT_DOCUMENT.md §4.5.8)
 * to a friendly message — used by every mutation action (US-A-06 AC-4).
 */
function describeError(err: ApiError): string {
  switch (err.status) {
    case 404:
      return 'Resource not found. Please refresh the page.';
    case 409:
      return 'Conflict: the league state changed in the meantime. Refresh and try again.';
    case 422:
      return err.payload?.message ?? 'Validation failed. Please check the inputs.';
    case 423:
      return 'A long-running action is in progress. Please wait a moment.';
    case 429:
      return 'Too many requests. Please slow down and try again in a minute.';
    case 0:
      return 'Network error: cannot reach the backend.';
    default:
      return err.payload?.message ?? err.message ?? 'Unexpected error.';
  }
}

export const useLeagueStore = defineStore('league', () => {
  // -- State ---------------------------------------------------------------
  const settings = ref<LeagueSettings>({ ...DEFAULT_SETTINGS });
  const teams = ref<Team[]>([]);
  const matches = ref<Match[]>([]);
  const standings = ref<Standing[]>([]);
  const predictions = ref<Prediction[]>([]);

  /** True while a mutation API request is in-flight (NFR-15, US-A-06 AC-1..AC-3). */
  const isMutating = ref(false);
  /** True for the initial GET /api/league/state (separate from `isMutating`). */
  const isLoading = ref(false);

  const error = ref<string | null>(null);
  const toasts = ref<ToastItem[]>([]);
  let toastSeq = 0;

  // -- Getters / computed --------------------------------------------------
  const currentWeek = computed(() => settings.value.current_week);
  const totalWeeks = computed(() => settings.value.total_weeks);
  const status = computed<LeagueStatus>(() => settings.value.status);
  const hasFixtures = computed(() => matches.value.length > 0);

  /** Has the season finished (current_week === total_weeks > 0)? */
  const isSeasonFinished = computed(
    () => totalWeeks.value > 0 && currentWeek.value >= totalWeeks.value,
  );

  /**
   * Generic prediction trigger from §4.4: `currentWeek > totalWeeks - 3`.
   * Hard-coding `week >= 4` is explicitly forbidden by US-E-01 AC-4.
   */
  const shouldShowPredictions = computed(
    () => totalWeeks.value > 0 && currentWeek.value > totalWeeks.value - 3,
  );

  const matchesByWeek = computed(() => {
    const map = new Map<number, Match[]>();
    for (const m of matches.value) {
      const list = map.get(m.week) ?? [];
      list.push(m);
      map.set(m.week, list);
    }
    return map;
  });

  const sortedStandings = computed(() =>
    // Backend ALREADY returns sorted standings (idx_standings_sort).
    // Defensive client sort guarantees the documented tiebreak chain
    // PTS desc → GD desc → GF desc → team_name asc (§4.4) if backend ordering drifts.
    [...standings.value].sort((a, b) => {
      if (b.points !== a.points) return b.points - a.points;
      if (b.goal_diff !== a.goal_diff) return b.goal_diff - a.goal_diff;
      if (b.goals_for !== a.goals_for) return b.goals_for - a.goals_for;
      return a.team_name.localeCompare(b.team_name);
    }),
  );

  // -- Toast helpers -------------------------------------------------------
  function pushToast(tone: ToastTone, message: string): void {
    toastSeq += 1;
    toasts.value.push({ id: toastSeq, tone, message });
  }

  function dismissToast(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
  }

  // -- Internals -----------------------------------------------------------
  function applyState(state: LeagueState | undefined | null): void {
    if (!state) return;
    settings.value = { ...DEFAULT_SETTINGS, ...state.settings };
    teams.value = state.teams ?? [];
    matches.value = state.matches ?? [];
    standings.value = state.standings ?? [];
    predictions.value = state.predictions ?? [];

    // Defensive invariant: sum(predictions) should equal 100 when triggered (NFR-12).
    if (shouldShowPredictions.value && predictions.value.length > 0) {
      const sum = predictions.value.reduce((acc, p) => acc + Number(p.champion_probability), 0);
      if (Math.round(sum) !== 100) {
        // Non-fatal — only warn in dev; production strips console.* (vite.config.ts).
        console.warn('[league] predictions sum != 100', sum, predictions.value);
      }
    }
  }

  async function runMutation<T>(fn: () => Promise<T>, opts?: { successMessage?: string }): Promise<T | null> {
    if (isMutating.value) {
      // US-A-06 AC-3: client-side guard against double-clicks; the backend also
      // protects via state machine (423) + idempotency (409), but blocking here
      // avoids unnecessary round-trips.
      return null;
    }
    isMutating.value = true;
    error.value = null;
    try {
      const result = await fn();
      if (opts?.successMessage) pushToast('success', opts.successMessage);
      return result;
    } catch (err) {
      const apiErr = err instanceof ApiError ? err : new ApiError((err as Error).message, 0);
      const msg = describeError(apiErr);
      error.value = msg;
      pushToast('error', msg);
      return null;
    } finally {
      // US-A-06 AC-2: cleared on both success and error.
      isMutating.value = false;
    }
  }

  // -- Actions -------------------------------------------------------------
  async function fetchState(): Promise<void> {
    isLoading.value = true;
    error.value = null;
    try {
      const state = await getLeagueStateApi();
      applyState(state);
    } catch (err) {
      const apiErr = err instanceof ApiError ? err : new ApiError((err as Error).message, 0);
      const msg = describeError(apiErr);
      error.value = msg;
      pushToast('error', msg);
    } finally {
      isLoading.value = false;
    }
  }

  async function generateFixtures(): Promise<boolean> {
    const result = await runMutation(
      async () => {
        const state = await generateFixturesApi();
        applyState(state);
      },
      { successMessage: 'Fixtures generated.' },
    );
    return result !== null;
  }

  async function playNextWeek(): Promise<boolean> {
    const expected_week = currentWeek.value + 1;
    const result = await runMutation(
      async () => {
        const state = await playNextWeekApi({ expected_week });
        applyState(state);
      },
      { successMessage: `Week ${expected_week} played.` },
    );
    return result !== null;
  }

  async function playAllWeeks(): Promise<boolean> {
    const result = await runMutation(
      async () => {
        const state = await playAllWeeksApi();
        applyState(state);
      },
      { successMessage: 'All remaining weeks played.' },
    );
    return result !== null;
  }

  async function resetLeague(): Promise<boolean> {
    const result = await runMutation(
      async () => {
        const state = await resetLeagueApi();
        applyState(state);
      },
      { successMessage: 'League reset.' },
    );
    return result !== null;
  }

  async function editMatch(
    matchId: number,
    homeScore: number,
    awayScore: number,
    expectedVersion: number,
  ): Promise<boolean> {
    const result = await runMutation(
      async () => {
        // Backend PATCH returns the full LeagueState envelope (the edit, the
        // recomputed standings and the new predictions snapshot all live in
        // the same transaction — US-G-02), so a single applyState() refreshes
        // the entire reactive store without a follow-up GET /state.
        const state = await editMatchApi(matchId, {
          home_score: homeScore,
          away_score: awayScore,
          expected_version: expectedVersion,
        });
        applyState(state);
      },
      { successMessage: 'Match updated.' },
    );
    return result !== null;
  }

  return {
    // state
    settings,
    teams,
    matches,
    standings,
    predictions,
    isMutating,
    isLoading,
    error,
    toasts,

    // getters
    currentWeek,
    totalWeeks,
    status,
    hasFixtures,
    isSeasonFinished,
    shouldShowPredictions,
    matchesByWeek,
    sortedStandings,

    // actions
    fetchState,
    generateFixtures,
    playNextWeek,
    playAllWeeks,
    resetLeague,
    editMatch,
    pushToast,
    dismissToast,
  };
});
