import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LeagueTable from '@/components/LeagueTable.vue';
import { STANDINGS_AFTER_WEEK_1 } from '../fixtures';

describe('LeagueTable.vue', () => {
  it('renders the 7 documented columns: Team / PTS / P / W / D / L / GD (US-D-02)', () => {
    const wrapper = mount(LeagueTable, { props: { standings: STANDINGS_AFTER_WEEK_1 } });
    const headers = wrapper.findAll('th').map((th) => th.text());
    expect(headers).toEqual(['Team', 'PTS', 'P', 'W', 'D', 'L', 'GD']);
  });

  it('renders one row per standing in the order they were given', () => {
    const wrapper = mount(LeagueTable, { props: { standings: STANDINGS_AFTER_WEEK_1 } });
    const rows = wrapper.findAll('tbody tr');
    expect(rows.length).toBe(4);
    expect(rows[0].text()).toContain('Manchester City');
    expect(rows[3].text()).toContain('Liverpool');
  });

  it('shows an empty state when no standings are passed', () => {
    const wrapper = mount(LeagueTable, { props: { standings: [] } });
    expect(wrapper.find('[data-testid="empty-standings"]').exists()).toBe(true);
    expect(wrapper.find('table').exists()).toBe(false);
  });

  it('formats positive goal diff with a leading + sign', () => {
    const wrapper = mount(LeagueTable, { props: { standings: STANDINGS_AFTER_WEEK_1 } });
    expect(wrapper.text()).toContain('+2');
    expect(wrapper.text()).toContain('-2');
  });

  it('matches the snapshot for a known standings input', () => {
    const wrapper = mount(LeagueTable, { props: { standings: STANDINGS_AFTER_WEEK_1 } });
    expect(wrapper.html()).toMatchSnapshot();
  });
});
