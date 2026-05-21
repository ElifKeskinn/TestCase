<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface Props {
  open: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  /** When true, the confirm button uses the danger style (Reset Data). */
  destructive?: boolean;
  busy?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  confirmLabel: 'Confirm',
  cancelLabel: 'Cancel',
  destructive: false,
  busy: false,
});

const emit = defineEmits<{
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

const cancelBtn = ref<HTMLButtonElement | null>(null);
const confirmBtn = ref<HTMLButtonElement | null>(null);

const confirmClass = computed(() => (props.destructive ? 'btn btn-danger' : 'btn btn-primary'));

watch(
  () => props.open,
  async (open) => {
    if (open) {
      await nextTick();
      // Focus the safer button by default: Cancel for destructive modals.
      (props.destructive ? cancelBtn.value : confirmBtn.value)?.focus();
    }
  },
);

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
});

function onKeydown(e: KeyboardEvent) {
  if (!props.open) return;
  if (e.key === 'Escape') {
    e.preventDefault();
    emit('cancel');
  }
}
</script>

<template>
  <Transition name="modal-fade">
    <div
      v-if="open"
      class="modal-backdrop"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`confirm-title-${title}`"
      @click.self="emit('cancel')"
    >
      <div class="modal">
        <h2 :id="`confirm-title-${title}`" class="modal-title">{{ title }}</h2>
        <p class="modal-message">{{ message }}</p>

        <div class="modal-actions">
          <button
            ref="cancelBtn"
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            @click="emit('cancel')"
          >
            {{ cancelLabel }}
          </button>
          <button
            ref="confirmBtn"
            type="button"
            :class="confirmClass"
            :disabled="busy"
            @click="emit('confirm')"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 900;
  padding: var(--space-4);
}

.modal {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  width: 100%;
  max-width: 420px;
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.modal-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.modal-message {
  margin: 0;
  color: var(--color-text-muted);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 120ms ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
