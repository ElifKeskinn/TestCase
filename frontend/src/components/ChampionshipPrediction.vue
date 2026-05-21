<script setup lang="ts">
import { computed } from 'vue';
import type { Prediction } from '@/types/league';

interface Props {
  predictions: Prediction[];
  currentWeek: number;
  totalWeeks: number;
}

const props = defineProps<Props>();

/**
 * Generic trigger condition (US-E-01, §4.4):
 *   currentWeek > totalWeeks - 3
 * No hard-coded `week >= 4` (US-E-01 AC-4).
 */
const shouldShow = computed(
  () => props.totalWeeks > 0 && props.currentWeek > props.totalWeeks - 3,
);

const sortedPredictions = computed(() =>
  [...props.predictions].sort((a, b) => b.champion_probability - a.champion_probability),
);

const remainingWeeks = computed(() => Math.max(props.totalWeeks - props.currentWeek, 0));

function formatPct(value: number): string {
  // decimal(5,2) per spec — show 2 decimals if non-zero, else 0.
  return Number(value).toFixed(2);
}
</script>

<template>
  <section class="card" aria-labelledby="prediction-heading">
    <h2 id="prediction-heading">Championship Prediction</h2>

    <div v-if="!shouldShow" class="placeholder" data-testid="prediction-placeholder">
      <p class="text-muted">
        Predictions appear in the final 3 weeks of the season.
      </p>
      <p class="text-faint placeholder-detail">
        Remaining: <strong>{{ remainingWeeks }}</strong> week{{ remainingWeeks === 1 ? '' : 's' }}.
      </p>
    </div>

    <ul v-else class="prediction-list" data-testid="prediction-list">
      <li
        v-for="p in sortedPredictions"
        :key="p.team_id"
        class="prediction-row"
      >
        <span class="prediction-team">{{ p.team_name }}</span>
        <div
          class="prediction-bar"
          role="progressbar"
          :aria-valuenow="p.champion_probability"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="`${p.team_name} championship probability`"
        >
          <div class="prediction-bar-fill" :style="{ width: `${p.champion_probability}%` }"></div>
        </div>
        <span class="prediction-pct">{{ formatPct(p.champion_probability) }}%</span>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.placeholder {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  text-align: center;
  padding: var(--space-4) 0;
}

.placeholder-detail {
  margin: 0;
}

.prediction-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.prediction-row {
  display: grid;
  grid-template-columns: 1fr 2fr auto;
  align-items: center;
  gap: var(--space-3);
  font-size: 0.9rem;
}

.prediction-team {
  font-weight: 600;
}

.prediction-bar {
  height: 8px;
  background: var(--color-surface-alt);
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.prediction-bar-fill {
  height: 100%;
  background: linear-gradient(to right, var(--color-primary), #3b82f6);
  transition: width 240ms ease;
}

.prediction-pct {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  min-width: 4rem;
  text-align: right;
}
</style>
