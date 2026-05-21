<script setup lang="ts">
import { onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useLeagueStore } from '@/stores/league';
import ToastContainer from '@/components/ToastContainer.vue';

const store = useLeagueStore();
const { isLoading } = storeToRefs(store);

onMounted(async () => {
  await store.fetchState();
});
</script>

<template>
  <div class="app-shell">
    <header class="app-header">
      <h1 class="app-title">Insider One Champions League</h1>
      <p class="app-subtitle">Football League Simulator</p>
    </header>

    <main class="app-main" :aria-busy="isLoading">
      <RouterView />
    </main>

    <footer class="app-footer text-faint">
      <small>4 teams · double round-robin · Premier League scoring (W=3, D=1, L=0).</small>
    </footer>

    <ToastContainer />
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  max-width: 1280px;
  margin: 0 auto;
  padding: var(--space-5);
  gap: var(--space-5);
}

.app-header {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding-bottom: var(--space-4);
  border-bottom: 1px solid var(--color-border);
}

.app-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--color-primary);
}

.app-subtitle {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.95rem;
}

.app-main {
  flex: 1;
}

.app-footer {
  border-top: 1px solid var(--color-border);
  padding-top: var(--space-3);
  text-align: center;
}

@media (max-width: 720px) {
  .app-shell {
    padding: var(--space-3);
  }
}
</style>
