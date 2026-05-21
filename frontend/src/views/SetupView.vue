<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useLeagueStore } from '@/stores/league';
import TournamentTeams from '@/components/TournamentTeams.vue';
import GenerateFixturesButton from '@/components/GenerateFixturesButton.vue';
import FixtureDisplay from '@/components/FixtureDisplay.vue';
import StartSimulationButton from '@/components/StartSimulationButton.vue';

const store = useLeagueStore();
const router = useRouter();
const { teams, matches, isMutating, isLoading, hasFixtures } = storeToRefs(store);

const showFixtures = computed(() => matches.value.length > 0);

async function onGenerate() {
  const ok = await store.generateFixtures();
  if (!ok) return;
  // Stay on setup screen so user can review fixtures before starting.
}

function onStart() {
  if (!hasFixtures.value) return;
  router.push({ name: 'simulation' });
}
</script>

<template>
  <div class="setup stack" :aria-busy="isLoading">
    <p v-if="isLoading" class="text-muted" data-testid="setup-loading">Loading league data…</p>

    <TournamentTeams :teams="teams" />

    <div class="row setup-actions">
      <GenerateFixturesButton
        :disabled="isMutating || teams.length === 0"
        :regenerate="hasFixtures"
        @generate="onGenerate"
      />

      <StartSimulationButton :enabled="hasFixtures && !isMutating" @start="onStart" />

      <p v-if="!hasFixtures" class="text-faint setup-hint">
        Generate fixtures to enable simulation.
      </p>
    </div>

    <FixtureDisplay v-if="showFixtures" :matches="matches" :teams="teams" />
  </div>
</template>

<style scoped>
.setup {
  max-width: 960px;
  margin: 0 auto;
}

.setup-actions {
  align-items: center;
}

.setup-hint {
  margin: 0;
  font-size: 0.85rem;
}
</style>
