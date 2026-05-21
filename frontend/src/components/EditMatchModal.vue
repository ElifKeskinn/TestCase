<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import type { Match, Team } from '@/types/league';

interface Props {
  open: boolean;
  match: Match | null;
  teams: Team[];
  busy?: boolean;
}

const props = withDefaults(defineProps<Props>(), { busy: false });

const emit = defineEmits<{
  (e: 'cancel'): void;
  (e: 'save', payload: { id: number; home_score: number; away_score: number; expected_version: number }): void;
}>();

const homeScore = ref<string>('');
const awayScore = ref<string>('');
const validationError = ref<string | null>(null);
const firstField = ref<HTMLInputElement | null>(null);

const teamNameById = computed(() => {
  const map = new Map<number, string>();
  for (const t of props.teams) map.set(t.id, t.name);
  return map;
});

const homeName = computed(() => {
  if (!props.match) return '';
  return props.match.home_team ?? teamNameById.value.get(props.match.home_team_id) ?? 'Home';
});

const awayName = computed(() => {
  if (!props.match) return '';
  return props.match.away_team ?? teamNameById.value.get(props.match.away_team_id) ?? 'Away';
});

// Reset form fields whenever the modal opens or a new match is selected.
// `immediate: true` ensures the fields are populated when the component mounts
// already open (covers the test case + first-paint UX).
watch(
  () => [props.open, props.match?.id] as const,
  async ([open]) => {
    if (open && props.match) {
      homeScore.value = String(props.match.home_score ?? 0);
      awayScore.value = String(props.match.away_score ?? 0);
      validationError.value = null;
      await nextTick();
      firstField.value?.focus();
      firstField.value?.select();
    }
  },
  { immediate: true },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

function onKeydown(e: KeyboardEvent) {
  if (!props.open) return;
  if (e.key === 'Escape') {
    e.preventDefault();
    emit('cancel');
  }
}

function parseScore(raw: string): number | null {
  if (raw === '' || raw === null) return null;
  // Reject anything not a non-negative integer up to 20 (US-G-03 AC-5, OQ-08).
  if (!/^\d+$/.test(raw)) return null;
  const n = Number(raw);
  if (!Number.isInteger(n)) return null;
  if (n < 0 || n > 20) return null;
  return n;
}

function onSubmit(e: Event) {
  e.preventDefault();
  if (!props.match) return;
  const home = parseScore(homeScore.value);
  const away = parseScore(awayScore.value);
  if (home === null || away === null) {
    validationError.value = 'Scores must be integers between 0 and 20.';
    return;
  }
  validationError.value = null;
  emit('save', {
    id: props.match.id,
    home_score: home,
    away_score: away,
    expected_version: props.match.version,
  });
}
</script>

<template>
  <Transition name="modal-fade">
    <div
      v-if="open && match"
      class="modal-backdrop"
      role="dialog"
      aria-modal="true"
      aria-labelledby="edit-match-title"
      @click.self="!busy && emit('cancel')"
    >
      <form class="modal" @submit="onSubmit">
        <h2 id="edit-match-title" class="modal-title">Edit Match</h2>
        <p class="text-muted modal-subtitle">
          Week {{ match.week }} — {{ homeName }} vs {{ awayName }}
        </p>

        <div class="score-fields">
          <div class="field">
            <label :for="`home-score-${match.id}`">{{ homeName }} score</label>
            <input
              :id="`home-score-${match.id}`"
              ref="firstField"
              v-model="homeScore"
              type="number"
              inputmode="numeric"
              min="0"
              max="20"
              step="1"
              class="input"
              required
              data-testid="edit-home-score"
            />
          </div>

          <span class="score-divider" aria-hidden="true">–</span>

          <div class="field">
            <label :for="`away-score-${match.id}`">{{ awayName }} score</label>
            <input
              :id="`away-score-${match.id}`"
              v-model="awayScore"
              type="number"
              inputmode="numeric"
              min="0"
              max="20"
              step="1"
              class="input"
              required
              data-testid="edit-away-score"
            />
          </div>
        </div>

        <p class="text-faint help">
          Allowed range: 0–20 (server enforces validation).
        </p>

        <p
          v-if="validationError"
          class="text-danger validation"
          role="alert"
          data-testid="edit-validation-error"
        >
          {{ validationError }}
        </p>

        <!-- Hidden version field for explicit optimistic-lock documentation. -->
        <input
          type="hidden"
          name="expected_version"
          :value="match.version"
          data-testid="edit-expected-version"
        />

        <div class="modal-actions">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            data-testid="edit-cancel-btn"
            @click="emit('cancel')"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="busy"
            data-testid="edit-save-btn"
          >
            {{ busy ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </form>
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
  max-width: 460px;
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.modal-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
}

.modal-subtitle {
  margin: 0;
}

.score-fields {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: var(--space-3);
  align-items: end;
  margin-top: var(--space-2);
}

.score-divider {
  font-weight: 700;
  font-size: 1.5rem;
  color: var(--color-text-faint);
  padding-bottom: var(--space-2);
}

.help {
  margin: 0;
  font-size: 0.8rem;
}

.validation {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  margin-top: var(--space-2);
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
