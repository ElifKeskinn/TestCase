<script setup lang="ts">
import { computed } from 'vue';
import type { LeagueStatus } from '@/types/league';

interface Props {
  isMutating: boolean;
  currentWeek: number;
  totalWeeks: number;
  status: LeagueStatus;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'play-next'): void;
  (e: 'play-all'): void;
  (e: 'reset'): void;
}>();

const seasonFinished = computed(
  () => props.totalWeeks > 0 && props.currentWeek >= props.totalWeeks,
);

/**
 * US-A-06 AC-3: every mutation button is disabled while a request is in-flight.
 * Additionally, Play Next/Play All are disabled when the season is over.
 */
const playNextDisabled = computed(() => props.isMutating || seasonFinished.value);
const playAllDisabled = computed(() => props.isMutating || seasonFinished.value);
const resetDisabled = computed(() => props.isMutating);
</script>

<template>
  <section class="controls card" aria-label="Simulation controls">
    <div class="controls-row">
      <button
        type="button"
        class="btn btn-primary"
        :disabled="playNextDisabled"
        data-testid="play-next-btn"
        @click="emit('play-next')"
      >
        Play Next Week
      </button>

      <button
        type="button"
        class="btn btn-secondary"
        :disabled="playAllDisabled"
        data-testid="play-all-btn"
        @click="emit('play-all')"
      >
        Play All Weeks
      </button>

      <span class="spacer" aria-hidden="true"></span>

      <!-- Reset Data — destructive action, US-A-03 AC-3. -->
      <button
        type="button"
        class="btn btn-danger"
        :disabled="resetDisabled"
        title="Resets all matches, standings and predictions"
        data-testid="reset-btn"
        @click="emit('reset')"
      >
        <span aria-hidden="true">⚠</span>
        <span>Reset Data</span>
      </button>
    </div>

    <p v-if="seasonFinished" class="text-muted controls-note" data-testid="season-finished-note">
      Season finished. Use <strong>Reset Data</strong> to start over.
    </p>
    <p v-else-if="isMutating" class="text-muted controls-note" data-testid="mutation-note">
      Working…
    </p>
  </section>
</template>

<style scoped>
.controls-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.spacer {
  flex: 1;
  min-width: var(--space-2);
}

.controls-note {
  margin: var(--space-3) 0 0;
  font-size: 0.85rem;
}
</style>
