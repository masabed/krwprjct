import store from '@/state/store';
 


export default [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/pages/account/login.vue'),
    meta: {
      beforeResolve(routeTo, routeFrom, next) {
        if (store.getters['auth/loggedIn']) {
          next({ name: 'Dashboard' });
        } else {
          next();
        }
      },
    },
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('../views/pages/account/register.vue'),
    meta: {
      beforeResolve(routeTo, routeFrom, next) {
        if (store.getters['auth/loggedIn']) {
          next({ name: 'Dashboard' });
        } else {
          next();
        }
      },
    },
  },

  {
    path: '/',
    name: 'Dashboard',
    component: () => import('../views/pages/dashboard/index.vue'),
    meta: {
      authRequired: true,
    },
  },

  // Bidang Perumahan - only for admin & admin_bidang
  {
    path: '/bidPerumahan/list',
    name: 'listbidperumahan',
    component: () => import('@/views/pages/bidPerumahan/listBidPerumahan.vue'),
    meta: {
      authRequired: true,
      roles: ['admin', 'admin_bidang'],
    },
  },
  {
    path: '/bidPerumahan/addData',
    name: 'addDataPerumahan',
    component: () => import('@/views/pages/bidPerumahan/addDataPerumahan.vue'),
    meta: {
      authRequired: true,
      roles: ['admin', 'admin_bidang'],
    },
  },
  {
    path: '/bidPerumahan/editData/:id',
    name: 'editDataPerumahan',
    component: () => import('@/views/pages/bidPerumahan/formEditDataPerumahan.vue'),
    meta: {
      authRequired: true,
      roles: ['admin', 'admin_bidang'],
    },
  },

  // Pengawas
  {
    path: '/pengawas/list',
    name: 'pengawas',
    component: () => import('@/views/pages/pengawas/pengawas.vue'),
    meta: {
      authRequired: true,
    },
  },
  {
    path: '/pengawas/tambahPhoto/:id',
    name: 'editDataPengawas',
    component: () => import('@/views/pages/pengawas/viewpengawas.vue'),
    meta: {
      authRequired: true,
    },
  },
];
