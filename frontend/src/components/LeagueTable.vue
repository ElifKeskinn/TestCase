<script setup lang="ts">
import type { Standing } from '@/types/league';

interface Props {
  /**
   * Sorted standings — caller is responsible for ordering
   * (PTS desc → GD desc → GF desc → team_name asc, §4.4).
   * The Pinia store exposes `sortedStandings` for this.
   */
  standings: Standing[];
}
defineProps<Props>();
</script>

<template>
  <section class="card" aria-labelledby="league-table-heading">
    <h2 id="league-table-heading">League Table</h2>

    <div v-if="standings.length === 0" class="text-muted" data-testid="empty-standings">
      No matches played yet.
    </div>

    <table v-else class="data-table" data-testid="league-table" aria-describedby="league-table-heading">
      <thead>
        <tr>
          <th scope="col">Team</th>
          <th scope="col" class="numeric">PTS</th>
          <th scope="col" class="numeric">P</th>
          <th scope="col" class="numeric">W</th>
          <th scope="col" class="numeric">D</th>
          <th scope="col" class="numeric">L</th>
          <th scope="col" class="numeric">GD</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(s, idx) in standings" :key="s.team_id" :data-rank="idx + 1">
          <td>{{ s.team_name }}</td>
          <td class="numeric"><strong>{{ s.points }}</strong></td>
          <td class="numeric">{{ s.played }}</td>
          <td class="numeric">{{ s.won }}</td>
          <td class="numeric">{{ s.drawn }}</td>
          <td class="numeric">{{ s.lost }}</td>
          <td class="numeric">{{ s.goal_diff >= 0 ? `+${s.goal_diff}` : s.goal_diff }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
