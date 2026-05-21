<script setup lang="ts">
import { computed } from 'vue';
import type { Match, Team } from '@/types/league';

interface Props {
  matches: Match[];
  teams: Team[];
}
const props = defineProps<Props>();

const teamNameById = computed(() => {
  const map = new Map<number, string>();
  for (const t of props.teams) map.set(t.id, t.name);
  return map;
});

function teamName(match: Match, side: 'home' | 'away'): string {
  const preset = side === 'home' ? match.home_team : match.away_team;
  if (preset) return preset;
  const id = side === 'home' ? match.home_team_id : match.away_team_id;
  return teamNameById.value.get(id) ?? `Team #${id}`;
}

const weeks = computed(() => {
  const map = new Map<number, Match[]>();
  for (const m of props.matches) {
    const arr = map.get(m.week) ?? [];
    arr.push(m);
    map.set(m.week, arr);
  }
  return [...map.entries()]
    .sort(([a], [b]) => a - b)
    .map(([week, list]) => ({ week, matches: list }));
});
</script>

<template>
  <section class="card" aria-labelledby="fixtures-heading">
    <h2 id="fixtures-heading">Fixtures</h2>

    <div v-if="weeks.length === 0" class="text-muted" data-testid="empty-fixtures">
      No fixtures generated yet.
    </div>

    <div v-else class="fixture-grid" data-testid="fixture-grid">
      <article v-for="w in weeks" :key="w.week" class="fixture-week">
        <h3 class="fixture-week-title">Week {{ w.week }}</h3>
        <ul class="fixture-matches">
          <li v-for="m in w.matches" :key="m.id" class="fixture-row">
            <span class="fixture-team home">{{ teamName(m, 'home') }}</span>
            <span class="fixture-vs">vs</span>
            <span class="fixture-team away">{{ teamName(m, 'away') }}</span>
          </li>
        </ul>
      </article>
    </div>
  </section>
</template>

<style scoped>
.fixture-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-3);
}

.fixture-week {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  background: var(--color-surface-alt);
}

.fixture-week-title {
  margin: 0 0 var(--space-2);
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--color-primary);
}

.fixture-matches {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.fixture-row {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: var(--space-2);
  align-items: center;
  font-size: 0.9rem;
  padding: var(--space-1) var(--space-2);
  background: var(--color-surface);
  border-radius: var(--radius-sm);
}

.fixture-team.home {
  text-align: right;
  font-weight: 600;
}

.fixture-team.away {
  text-align: left;
}

.fixture-vs {
  color: var(--color-text-faint);
  font-size: 0.75rem;
}
</style>
