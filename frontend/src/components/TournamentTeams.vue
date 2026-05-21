<script setup lang="ts">
import type { Team } from '@/types/league';

interface Props {
  teams: Team[];
}
defineProps<Props>();
</script>

<template>
  <section class="card" aria-labelledby="tournament-teams-heading">
    <h2 id="tournament-teams-heading">Tournament Teams</h2>

    <ul v-if="teams.length > 0" class="team-list" data-testid="team-list">
      <li v-for="team in teams" :key="team.id" class="team-item">
        <span class="team-bullet" aria-hidden="true">●</span>
        <span class="team-name">{{ team.name }}</span>
      </li>
    </ul>

    <p v-else class="text-muted" data-testid="empty-teams">
      No teams found. Please seed the database first.
    </p>
  </section>
</template>

<style scoped>
.team-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--space-2);
}

.team-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-alt);
  font-weight: 600;
}

.team-bullet {
  color: var(--color-primary);
}
</style>
