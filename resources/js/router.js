import { createRouter, createWebHistory } from 'vue-router';
import WorkbenchView from './views/WorkbenchView.vue';

const basePath = window.Anima?.path ? `/${window.Anima.path}` : '/anima';

const routes = [
  {
    path: '/',
    name: 'workbench',
    component: WorkbenchView,
  },
  {
    path: '/:id',
    name: 'workbench.entry',
    component: WorkbenchView,
  },
];

const router = createRouter({
  history: createWebHistory(basePath),
  routes,
});

export default router;
