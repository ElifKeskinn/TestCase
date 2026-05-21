<script setup lang="ts">
import { onUnmounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useLeagueStore } from '@/stores/league';

const store = useLeagueStore();
const { toasts } = storeToRefs(store);

// Auto-dismiss after 5s.
const timers = new Map<number, ReturnType<typeof setTimeout>>();

watch(
  toasts,
  (list) => {
    for (const t of list) {
      if (timers.has(t.id)) continue;
      const handle = setTimeout(() => {
        store.dismissToast(t.id);
        timers.delete(t.id);
      }, 5000);
      timers.set(t.id, handle);
    }
  },
  { deep: true, immediate: true },
);

onUnmounted(() => {
  for (const h of timers.values()) clearTimeout(h);
  timers.clear();
});
</script>

<template>
  <div class="toast-container" role="status" aria-live="polite">
    <div
      v-for="t in toasts"
      :key="t.id"
      class="toast"
      :class="`toast-${t.tone}`"
      :data-tone="t.tone"
    >
      <span class="toast-message">{{ t.message }}</span>
      <button
        type="button"
        class="toast-close"
        aria-label="Dismiss notification"
        @click="store.dismissToast(t.id)"
      >
        ×
      </button>
    </div>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: var(--space-4);
  right: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  z-index: 1000;
  max-width: min(380px, 92vw);
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-md);
  font-size: 0.9rem;
  color: var(--color-text);
}

.toast-success {
  border-left: 4px solid var(--color-success);
}

.toast-error {
  border-left: 4px solid var(--color-danger);
}

.toast-warning {
  border-left: 4px solid var(--color-warning);
}

.toast-info {
  border-left: 4px solid var(--color-primary);
}

.toast-message {
  flex: 1;
}

.toast-close {
  background: transparent;
  border: 0;
  color: var(--color-text-muted);
  font-size: 1.25rem;
  line-height: 1;
  padding: 0;
  width: 1.25rem;
  height: 1.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.toast-close:hover {
  color: var(--color-text);
}
</style>
