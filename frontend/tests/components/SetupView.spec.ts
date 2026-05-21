import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createRouter, createMemoryHistory } from 'vue-router';

import SetupView from '@/views/SetupView.vue';
import { useLeagueStore } from '@/stores/league';
import * as api from '@/api/leagueApi';
import { STATE_INIT, STATE_WITH_FIXTURES } from '../fixtures';

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'setup', component: { template: '<div />' } },
    { path: '/simulation', name: 'simulation', component: { template: '<div />' } },
  ],
});

describe('SetupView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('lists tournament teams; Start Simulation is disabled until fixtures exist', async () => {
    const store = useLeagueStore();
    vi.spyOn(api, 'getLeagueState').mockResolvedValue(STATE_INIT);
    await store.fetchState();

    const wrapper = mount(SetupView, { global: { plugins: [router] } });
    await flushPromises();

    expect(wrapper.text()).toContain('Tournament Teams');
    expect(wrapper.text()).toContain('Liverpool');
    expect(
      (wrapper.find('[data-testid="start-simulation-btn"]').element as HTMLButtonElement).disabled,
    ).toBe(true);
  });

  it('happy path: generate fixtures enables Start Simulation and navigates on click', async () => {
    const store = useLeagueStore();
    vi.spyOn(api, 'getLeagueState').mockResolvedValueOnce(STATE_INIT);
    await store.fetchState();

    const wrapper = mount(SetupView, { global: { plugins: [router] } });
    await flushPromises();

    vi.spyOn(api, 'generateFixtures').mockResolvedValueOnce(STATE_WITH_FIXTURES);
    await wrapper.find('[data-testid="generate-fixtures-btn"]').trigger('click');
    await flushPromises();

    expect(store.hasFixtures).toBe(true);
    const startBtn = wrapper.find('[data-testid="start-simulation-btn"]');
    expect((startBtn.element as HTMLButtonElement).disabled).toBe(false);

    await router.push({ name: 'setup' });
    await startBtn.trigger('click');
    await flushPromises();
    expect(router.currentRoute.value.name).toBe('simulation');
  });
});
