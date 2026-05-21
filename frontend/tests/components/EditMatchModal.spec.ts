import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import EditMatchModal from '@/components/EditMatchModal.vue';
import { MATCHES_WEEK_1_PLAYED, TEAMS } from '../fixtures';

const baseMatch = MATCHES_WEEK_1_PLAYED[0];

describe('EditMatchModal.vue', () => {
  it('renders existing scores when opened (US-G-01 AC-1)', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    // Wait a microtask so watchers populate the v-model refs.
    await new Promise((r) => setTimeout(r, 0));

    const home = wrapper.find<HTMLInputElement>('[data-testid="edit-home-score"]').element;
    const away = wrapper.find<HTMLInputElement>('[data-testid="edit-away-score"]').element;
    expect(home.value).toBe(String(baseMatch.home_score));
    expect(away.value).toBe(String(baseMatch.away_score));
    wrapper.unmount();
  });

  it('emits save with expected_version pulled from the match (US-G-03 AC-1/AC-2)', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 0));

    await wrapper.find('[data-testid="edit-home-score"]').setValue('4');
    await wrapper.find('[data-testid="edit-away-score"]').setValue('2');
    await wrapper.find('form').trigger('submit.prevent');

    const events = wrapper.emitted('save') ?? [];
    expect(events.length).toBe(1);
    expect(events[0][0]).toEqual({
      id: baseMatch.id,
      home_score: 4,
      away_score: 2,
      expected_version: baseMatch.version,
    });
    wrapper.unmount();
  });

  it('rejects scores below 0 (US-G-03 AC-5)', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 0));

    await wrapper.find('[data-testid="edit-home-score"]').setValue('-1');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('save')).toBeFalsy();
    expect(wrapper.find('[data-testid="edit-validation-error"]').exists()).toBe(true);
    wrapper.unmount();
  });

  it('rejects scores above 20 (US-G-03 AC-5 / OQ-08)', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 0));

    await wrapper.find('[data-testid="edit-home-score"]').setValue('21');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('save')).toBeFalsy();
    expect(wrapper.find('[data-testid="edit-validation-error"]').text()).toMatch(/0 and 20/);
    wrapper.unmount();
  });

  it('rejects non-integer scores', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 0));

    await wrapper.find('[data-testid="edit-home-score"]').setValue('abc');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('save')).toBeFalsy();
    wrapper.unmount();
  });

  it('emits cancel when the Cancel button is pressed', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await wrapper.find('[data-testid="edit-cancel-btn"]').trigger('click');
    expect(wrapper.emitted('cancel')).toHaveLength(1);
    wrapper.unmount();
  });

  it('exposes the expected_version through a hidden field for visibility', async () => {
    const wrapper = mount(EditMatchModal, {
      props: { open: true, match: baseMatch, teams: TEAMS },
      attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 0));
    const hidden = wrapper.find<HTMLInputElement>('[data-testid="edit-expected-version"]');
    expect(hidden.exists()).toBe(true);
    expect(hidden.element.value).toBe(String(baseMatch.version));
    wrapper.unmount();
  });
});
