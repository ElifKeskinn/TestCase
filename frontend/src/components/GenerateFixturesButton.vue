<script setup lang="ts">
interface Props {
  /** Disable while a mutation is in-flight (NFR-15). */
  disabled?: boolean;
  /** Show "Regenerate Fixtures" when fixtures already exist (US-B-04 AC-3). */
  regenerate?: boolean;
}
const props = withDefaults(defineProps<Props>(), { disabled: false, regenerate: false });

const emit = defineEmits<{ (e: 'generate'): void }>();

function onClick() {
  if (props.disabled) return;
  emit('generate');
}
</script>

<template>
  <button
    type="button"
    class="btn btn-primary"
    :disabled="disabled"
    data-testid="generate-fixtures-btn"
    @click="onClick"
  >
    {{ regenerate ? 'Regenerate Fixtures' : 'Generate Fixtures' }}
  </button>
</template>
