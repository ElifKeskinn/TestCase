import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useLeagueStore } from '@/stores/league';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'setup',
    component: () => import('@/views/SetupView.vue'),
    meta: { title: 'Setup' },
  },
  {
    path: '/simulation',
    name: 'simulation',
    component: () => import('@/views/SimulationView.vue'),
    meta: { title: 'Simulation', requiresFixtures: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'setup' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

/**
 * Guard: simulation screen is reachable only after fixtures exist (US-B-05).
 * If the store hasn't been hydrated yet, fetch state first so this guard
 * survives a hard reload on /simulation.
 */
router.beforeEach(async (to) => {
  const store = useLeagueStore();
  if (to.meta.requiresFixtures) {
    if (store.matches.length === 0) {
      await store.fetchState();
    }
    if (store.matches.length === 0) {
      return { name: 'setup' };
    }
  }
  return true;
});

router.afterEach((to) => {
  const baseTitle = 'Insider One Champions League';
  const pageTitle = (to.meta.title as string | undefined) ?? '';
  document.title = pageTitle ? `${pageTitle} · ${baseTitle}` : baseTitle;
});

export default router;
