<script setup lang="ts">
import { computed } from 'vue';
import type { Match, Team } from '@/types/league';

interface Props {
  matches: Match[];
  teams: Team[];
  selectedWeek: number;
  totalWeeks: number;
  /** Disable Edit Match button while a mutation is in-flight (NFR-15). */
  isMutating?: boolean;
}

const props = withDefaults(defineProps<Props>(), { isMutating: false });

const emit = defineEmits<{
  (e: 'change-week', week: number): void;
  (e: 'edit-match', match: Match): void;
}>();

const teamNameById = computed(() => {
  const map = new Map<number, string>();
  for (const t of props.teams) map.set(t.id, t.name);
  return map;
});

const weekMatches = computed(() =>
  props.matches
    .filter((m) => m.week === props.selectedWeek)
    .sort((a, b) => a.id - b.id),
);

const hasPrev = computed(() => props.selectedWeek > 1);
const hasNext = computed(() => props.selectedWeek < props.totalWeeks);

function teamName(m: Match, side: 'home' | 'away'): string {
  const preset = side === 'home' ? m.home_team : m.away_team;
  if (preset) return preset;
  const id = side === 'home' ? m.home_team_id : m.away_team_id;
  return teamNameById.value.get(id) ?? `Team #${id}`;
}

function goPrev() {
  if (hasPrev.value) emit('change-week', props.selectedWeek - 1);
}

function goNext() {
  if (hasNext.value) emit('change-week', props.selectedWeek + 1);
}

function isPlayed(m: Match): boolean {
  return m.home_score !== null && m.away_score !== null;
}
</script>

<template>
  <section class="card" aria-labelledby="week-results-heading">
    <div class="week-header">
      <h2 id="week-results-heading">Week {{ selectedWeek }}</h2>
      <div class="week-nav" role="group" aria-label="Week navigation">
        <button
          type="button"
          class="btn btn-secondary nav-btn"
          :disabled="!hasPrev"
          aria-label="Previous week"
          data-testid="prev-week-btn"
          @click="goPrev"
        >
          ‹
        </button>
        <span class="week-indicator" aria-live="polite">
          {{ selectedWeek }} / {{ totalWeeks }}
        </span>
        <button
          type="button"
          class="btn btn-secondary nav-btn"
          :disabled="!hasNext"
          aria-label="Next week"
          data-testid="next-week-btn"
          @click="goNext"
        >
          ›
        </button>
      </div>
    </div>

    <div v-if="weekMatches.length === 0" class="text-muted" data-testid="empty-week">
      No matches scheduled for this week.
    </div>

    <ul v-else class="match-list" data-testid="match-list">
      <li v-for="m in weekMatches" :key="m.id" class="match-row">
        <span class="team home">{{ teamName(m, 'home') }}</span>

        <span v-if="isPlayed(m)" class="score" data-testid="match-score">
          {{ m.home_score }} - {{ m.away_score }}
        </span>
        <span v-else class="score score-pending" data-testid="match-pending">
          – vs –
        </span>

        <span class="team away">{{ teamName(m, 'away') }}</span>

        <button
          type="button"
          class="btn btn-secondary btn-edit"
          :disabled="!isPlayed(m) || isMutating"
          :aria-label="`Edit match ${teamName(m, 'home')} vs ${teamName(m, 'away')}`"
          data-testid="edit-match-btn"
          @click="emit('edit-match', m)"
        >
          Edit Match
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.week-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.week-header h2 {
  margin: 0;
}

.week-nav {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.nav-btn {
  padding: var(--space-1) var(--space-3);
  font-size: 1rem;
  line-height: 1;
}

.week-indicator {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
  min-width: 3rem;
  text-align: center;
}

.match-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.match-row {
  display: grid;
  grid-template-columns: 1fr auto 1fr auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.team {
  font-weight: 600;
}

.team.home {
  text-align: right;
}

.team.away {
  text-align: left;
}

.score {
  font-weight: 700;
  font-size: 1.1rem;
  min-width: 4.5rem;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.score-pending {
  color: var(--color-text-faint);
  font-weight: 500;
  font-size: 0.95rem;
}

.btn-edit {
  padding: var(--space-1) var(--space-3);
  font-size: 0.8rem;
  font-weight: 600;
}

@media (max-width: 540px) {
  .match-row {
    grid-template-columns: 1fr auto 1fr;
  }

  .btn-edit {
    grid-column: 1 / -1;
    justify-self: stretch;
  }
}
</style>
