<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useLeagueStore } from '@/stores/league';
import LeagueTable from '@/components/LeagueTable.vue';
import WeekResults from '@/components/WeekResults.vue';
import ChampionshipPrediction from '@/components/ChampionshipPrediction.vue';
import Controls from '@/components/Controls.vue';
import EditMatchModal from '@/components/EditMatchModal.vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import type { Match } from '@/types/league';

const store = useLeagueStore();
const router = useRouter();
const {
  teams,
  matches,
  predictions,
  sortedStandings,
  currentWeek,
  totalWeeks,
  status,
  isMutating,
  hasFixtures,
} = storeToRefs(store);

// Default selected week = current week (or 1 if not started yet).
const selectedWeek = ref<number>(Math.max(currentWeek.value, 1));

watch(currentWeek, (cw) => {
  // When a new week is played, jump the user to that week's results.
  if (cw > 0) selectedWeek.value = cw;
});

// Edit Match modal state.
const editingMatch = ref<Match | null>(null);
const editOpen = computed(() => editingMatch.value !== null);

function openEdit(m: Match) {
  editingMatch.value = m;
}

function closeEdit() {
  editingMatch.value = null;
}

async function onSaveEdit(payload: {
  id: number;
  home_score: number;
  away_score: number;
  expected_version: number;
}) {
  const ok = await store.editMatch(payload.id, payload.home_score, payload.away_score, payload.expected_version);
  if (ok) closeEdit();
}

// Reset Data confirm flow (US-A-03 AC-4).
const resetConfirmOpen = ref(false);

function requestReset() {
  resetConfirmOpen.value = true;
}

async function confirmReset() {
  const ok = await store.resetLeague();
  resetConfirmOpen.value = false;
  if (ok) {
    // US-A-03 AC-2: redirect to setup after reset.
    router.push({ name: 'setup' });
  }
}

function cancelReset() {
  resetConfirmOpen.value = false;
}

// Defensive guard: if state was somehow loaded without fixtures, send user back.
watch(
  hasFixtures,
  (has) => {
    if (!has) router.replace({ name: 'setup' });
  },
  { immediate: false },
);
</script>

<template>
  <div class="simulation">
    <div class="sim-grid">
      <LeagueTable :standings="sortedStandings" />

      <WeekResults
        :matches="matches"
        :teams="teams"
        :selected-week="selectedWeek"
        :total-weeks="totalWeeks"
        :is-mutating="isMutating"
        @change-week="selectedWeek = $event"
        @edit-match="openEdit"
      />

      <ChampionshipPrediction
        :predictions="predictions"
        :current-week="currentWeek"
        :total-weeks="totalWeeks"
      />
    </div>

    <Controls
      :is-mutating="isMutating"
      :current-week="currentWeek"
      :total-weeks="totalWeeks"
      :status="status"
      @play-next="store.playNextWeek"
      @play-all="store.playAllWeeks"
      @reset="requestReset"
    />

    <EditMatchModal
      :open="editOpen"
      :match="editingMatch"
      :teams="teams"
      :busy="isMutating"
      @cancel="closeEdit"
      @save="onSaveEdit"
    />

    <ConfirmModal
      :open="resetConfirmOpen"
      title="Reset League Data?"
      message="This will erase all match scores, standings, predictions and the fixture. This action cannot be undone."
      confirm-label="Yes, Reset Data"
      cancel-label="Cancel"
      :destructive="true"
      :busy="isMutating"
      @confirm="confirmReset"
      @cancel="cancelReset"
    />
  </div>
</template>

<style scoped>
.simulation {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.sim-grid {
  display: grid;
  grid-template-columns: 1.1fr 1.2fr 1fr;
  gap: var(--space-4);
  align-items: start;
}

@media (max-width: 1080px) {
  .sim-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 720px) {
  .sim-grid {
    grid-template-columns: 1fr;
  }
}
</style>
