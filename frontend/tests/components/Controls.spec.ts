import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Controls from '@/components/Controls.vue';

const defaultProps = {
  isMutating: false,
  currentWeek: 0,
  totalWeeks: 6,
  status: 'idle' as const,
};

describe('Controls.vue', () => {
  it('renders the three documented buttons (US-A-03, US-F-01, video glossary)', () => {
    const wrapper = mount(Controls, { props: defaultProps });
    expect(wrapper.find('[data-testid="play-next-btn"]').text()).toBe('Play Next Week');
    expect(wrapper.find('[data-testid="play-all-btn"]').text()).toBe('Play All Weeks');
    expect(wrapper.find('[data-testid="reset-btn"]').text()).toContain('Reset Data');
  });

  it('Reset Data button uses the danger class (US-A-03 AC-3)', () => {
    const wrapper = mount(Controls, { props: defaultProps });
    const reset = wrapper.find('[data-testid="reset-btn"]');
    expect(reset.classes()).toContain('btn-danger');
  });

  it('disables every mutation button while isMutating=true (US-A-06 AC-3)', () => {
    const wrapper = mount(Controls, { props: { ...defaultProps, isMutating: true } });
    expect((wrapper.find('[data-testid="play-next-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((wrapper.find('[data-testid="play-all-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((wrapper.find('[data-testid="reset-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect(wrapper.find('[data-testid="mutation-note"]').exists()).toBe(true);
  });

  it('disables Play Next / Play All when season is finished but keeps Reset enabled', () => {
    const wrapper = mount(Controls, {
      props: { ...defaultProps, currentWeek: 6, totalWeeks: 6, status: 'finished' },
    });
    expect((wrapper.find('[data-testid="play-next-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((wrapper.find('[data-testid="play-all-btn"]').element as HTMLButtonElement).disabled).toBe(true);
    expect((wrapper.find('[data-testid="reset-btn"]').element as HTMLButtonElement).disabled).toBe(false);
    expect(wrapper.find('[data-testid="season-finished-note"]').exists()).toBe(true);
  });

  it('emits events on click (play-next, play-all, reset)', async () => {
    const wrapper = mount(Controls, { props: defaultProps });
    await wrapper.find('[data-testid="play-next-btn"]').trigger('click');
    await wrapper.find('[data-testid="play-all-btn"]').trigger('click');
    await wrapper.find('[data-testid="reset-btn"]').trigger('click');
    expect(wrapper.emitted('play-next')).toHaveLength(1);
    expect(wrapper.emitted('play-all')).toHaveLength(1);
    expect(wrapper.emitted('reset')).toHaveLength(1);
  });
});
