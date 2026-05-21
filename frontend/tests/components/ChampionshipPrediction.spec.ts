import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ChampionshipPrediction from '@/components/ChampionshipPrediction.vue';
import { PREDICTIONS_WEEK_5 } from '../fixtures';

describe('ChampionshipPrediction.vue', () => {
  it('hides predictions when currentWeek <= totalWeeks - 3 (US-E-01 AC-1)', () => {
    const wrapper = mount(ChampionshipPrediction, {
      props: { predictions: PREDICTIONS_WEEK_5, currentWeek: 3, totalWeeks: 6 },
    });
    expect(wrapper.find('[data-testid="prediction-placeholder"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="prediction-list"]').exists()).toBe(false);
  });

  it('shows predictions when currentWeek > totalWeeks - 3 (US-E-01 AC-2)', () => {
    const wrapper = mount(ChampionshipPrediction, {
      props: { predictions: PREDICTIONS_WEEK_5, currentWeek: 5, totalWeeks: 6 },
    });
    expect(wrapper.find('[data-testid="prediction-list"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="prediction-placeholder"]').exists()).toBe(false);
  });

  it('uses generic formula across team counts (N=6 totalWeeks=10, US-B-03 / US-E-01 AC-3)', () => {
    const wrapper = mount(ChampionshipPrediction, {
      props: { predictions: PREDICTIONS_WEEK_5, currentWeek: 8, totalWeeks: 10 },
    });
    expect(wrapper.find('[data-testid="prediction-list"]').exists()).toBe(true);
  });

  it('sum of probabilities equals 100 (NFR-12 / US-E-02)', () => {
    const sum = PREDICTIONS_WEEK_5.reduce((acc, p) => acc + p.champion_probability, 0);
    expect(sum).toBe(100);

    const wrapper = mount(ChampionshipPrediction, {
      props: { predictions: PREDICTIONS_WEEK_5, currentWeek: 5, totalWeeks: 6 },
    });
    // First row = highest probability (60) — Chelsea per the fixture.
    expect(wrapper.text()).toContain('Chelsea');
    expect(wrapper.text()).toContain('60.00%');
  });

  it('sorts predictions by probability descending', () => {
    const wrapper = mount(ChampionshipPrediction, {
      props: { predictions: PREDICTIONS_WEEK_5, currentWeek: 5, totalWeeks: 6 },
    });
    const rows = wrapper.findAll('.prediction-row');
    expect(rows[0].text()).toContain('Chelsea');
    expect(rows[1].text()).toContain('Arsenal');
    expect(rows[2].text()).toContain('Manchester City');
    expect(rows[3].text()).toContain('Liverpool');
  });
});
