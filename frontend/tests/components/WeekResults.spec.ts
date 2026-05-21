import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import WeekResults from '@/components/WeekResults.vue';
import { MATCHES_FULL_FIXTURE, TEAMS } from '../fixtures';

describe('WeekResults.vue', () => {
  it('shows matches for the selected week with scores when played', () => {
    const wrapper = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 1, totalWeeks: 6 },
    });
    expect(wrapper.text()).toContain('Week 1');
    const scores = wrapper.findAll('[data-testid="match-score"]');
    expect(scores.length).toBe(2);
    expect(scores[0].text()).toBe('1 - 3');
  });

  it('shows pending placeholder for unplayed matches', () => {
    const wrapper = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 2, totalWeeks: 6 },
    });
    expect(wrapper.findAll('[data-testid="match-pending"]').length).toBe(2);
  });

  it('disables Previous on week 1 and Next on the last week', () => {
    const w1 = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 1, totalWeeks: 6 },
    });
    expect((w1.find('[data-testid="prev-week-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((w1.find('[data-testid="next-week-btn"]').element as HTMLButtonElement).disabled).toBe(false);

    const w6 = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 6, totalWeeks: 6 },
    });
    expect((w6.find('[data-testid="prev-week-btn"]').element as HTMLButtonElement).disabled).toBe(false);
    expect((w6.find('[data-testid="next-week-btn"]').element as HTMLButtonElement).disabled).toBe(true);
  });

  it('emits change-week when the navigator is used', async () => {
    const wrapper = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 2, totalWeeks: 6 },
    });
    await wrapper.find('[data-testid="prev-week-btn"]').trigger('click');
    await wrapper.find('[data-testid="next-week-btn"]').trigger('click');

    const events = wrapper.emitted('change-week') ?? [];
    expect(events.length).toBe(2);
    expect(events[0]?.[0]).toBe(1);
    expect(events[1]?.[0]).toBe(3);
  });

  it('disables Edit Match while isMutating is true (NFR-15)', () => {
    const wrapper = mount(WeekResults, {
      props: {
        matches: MATCHES_FULL_FIXTURE,
        teams: TEAMS,
        selectedWeek: 1,
        totalWeeks: 6,
        isMutating: true,
      },
    });
    const btns = wrapper.findAll('[data-testid="edit-match-btn"]');
    expect(btns.length).toBeGreaterThan(0);
    btns.forEach((b) => {
      expect((b.element as HTMLButtonElement).disabled).toBe(true);
    });
  });

  it('disables Edit Match for unplayed matches', () => {
    const wrapper = mount(WeekResults, {
      props: { matches: MATCHES_FULL_FIXTURE, teams: TEAMS, selectedWeek: 2, totalWeeks: 6 },
    });
    const btns = wrapper.findAll('[data-testid="edit-match-btn"]');
    expect(btns.length).toBe(2);
    btns.forEach((b) => {
      expect((b.element as HTMLButtonElement).disabled).toBe(true);
    });
  });
});
