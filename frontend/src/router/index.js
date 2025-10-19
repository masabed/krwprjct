import { createWebHistory, createRouter } from 'vue-router';
import store from '@/state/store';
import routes from './routes';

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0, left: 0 };
  },
});

router.beforeEach(async (to, from, next) => {
  document.title = to.name ? `${to.name} | SI IMAH KABUPATEN KARAWANG` : 'SI IMAH KABUPATEN KARAWANG';

  const publicPages = ['/login', '/register', '/forgot-password'];
  const authRequired = !publicPages.includes(to.path);
  const loggedUser = localStorage.getItem('user');
  const parsedUser = loggedUser ? JSON.parse(loggedUser) : null;

  if (authRequired && !parsedUser) {
    return next({ name: 'Login', query: { redirectFrom: to.fullPath } });
  }

  // If route requires specific roles
  const routeRoles = to.meta?.roles;
  if (routeRoles && parsedUser && !routeRoles.includes(parsedUser.role)) {
    return next({ path: '/', replace:true }); // Unauthorized user, redirect to dashboard
  }

  next();
});

router.beforeResolve(async (to, from, next) => {
  try {
    for (const route of to.matched) {
      await new Promise((resolve, reject) => {
        if (route.meta?.beforeResolve) {
          route.meta.beforeResolve(to, from, (...args) => {
            if (args.length) {
              next(...args);
              reject(new Error('Redirected'));
            } else {
              resolve();
            }
          });
        } else {
          resolve();
        }
      });
    }
  } catch {
    return;
  }
  next();
});

export default router;
