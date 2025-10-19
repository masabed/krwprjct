// src/main.js
import { createApp } from "vue";
import App from "./App.vue";

import router from "./router";
import store from "@/state/store";
import i18n from "./i18n";

import BootstrapVue from "bootstrap-vue-next";
import VueApexCharts from "vue3-apexcharts";
import VueSweetalert2 from "vue-sweetalert2";
import * as VueGoogleMaps from "vue3-google-map";
import VueYoutube from "vue3-youtube";
import Vue3Toastify from "vue3-toastify";
import { vMaska } from "maska";
import vco from "v-click-outside";

import axiosInstance from "@/services/axios";

import "bootstrap-vue-next/dist/bootstrap-vue-next.css";
import "@/assets/scss/app.scss";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

const app = createApp(App);

// ✅ Global Plugins
app.use(router);
app.use(store);
app.use(i18n);
app.use(BootstrapVue);
app.use(VueSweetalert2);
app.use(Vue3Toastify);
app.use(VueApexCharts);
app.use(VueYoutube);
app.use(vco);
app.directive("maska", vMaska);

// ✅ Axios: inject ke globalProperties
app.config.globalProperties.$axios = axiosInstance;

// ✅ Route Guard
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem("access_token");
  const requiresAuth = to.matched.some((r) => r.meta?.authRequired);

  if (requiresAuth && !token) {
    next({ name: "Login", query: { redirectFrom: to.fullPath } });
  } else {
    next();
  }
});

// ✅ Mount app
app.mount("#app");
