<script setup lang="ts">
interface Props {
  /** Enable only when fixtures exist (US-B-05 AC-1/AC-2). */
  enabled: boolean;
}
const props = defineProps<Props>();

const emit = defineEmits<{ (e: 'start'): void }>();

function onClick() {
  if (!props.enabled) return;
  emit('start');
}
</script>

<template>
  <button
    type="button"
    class="btn btn-primary"
    :disabled="!enabled"
    data-testid="start-simulation-btn"
    @click="onClick"
  >
    Start Simulation
  </button>
</template>
