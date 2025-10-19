<script>
import simplebar from "simplebar-vue";
import i18n from "../i18n";

export default {
  data() {
    return {
      userName: ''
    };
  },
  components: { simplebar },
  mounted() {
    const user = JSON.parse(localStorage.getItem("user"));
    if (user && user.name) {
      this.userName = user.name;
    }
  },
  methods: {
    toggleMenu() {
      this.$parent.toggleMenu();
    },
    initFullScreen() {
      document.body.classList.toggle("fullscreen-enable");
      if (
        !document.fullscreenElement &&
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
      ) {
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
          document.documentElement.webkitRequestFullscreen(
            Element.ALLOW_KEYBOARD_INPUT
          );
        }
      } else {
        if (document.cancelFullScreen) {
          document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
          document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
          document.webkitCancelFullScreen();
        }
      }
    },
    toggleRightSidebar() {
      this.$parent.toggleRightSidebar();
    },
    setLanguage(locale, country, flag) {
      this.text = country;
      this.flag = flag;
      this.current_language = i18n.locale;
      i18n.global.locale = locale;
    },
    async handleLogout() {
      try {
        await fetch("/api/logout", { method: "POST" });
        localStorage.clear();
        this.$router.push("/login");
      } catch (error) {
        console.error("Logout failed", error);
        localStorage.clear();
        this.$router.push("/login");
      }
    }
  }
};
</script>

<template>
  <header id="page-topbar">
    <div class="navbar-header">
      <div class="d-flex justify-between align-items-center w-100">
        <!-- LEFT: Logo + Toggle Menu Inside Logo Box -->
        <div class="d-flex align-items-center">
          <div class="navbar-brand-box d-flex align-items-center">
            <router-link to="/" class="logo logo-dark">
              <span class="logo-sm">
                <img src="@/assets/images/dprkp.jpg" alt height="22" />
              </span>
              <span class="logo-lg">
                <img src="@/assets/images/SIIMAH.png" alt height="80" />
              </span>
            </router-link>

            <router-link to="/" class="logo logo-light flex flex-col items-center me-2">
              <span class="logo-sm">
                <img src="@/assets/images/dprkp.jpg" alt height="40" />
              </span>
              <span class="logo-lg">
                <img src="@/assets/images/SIIMAH.png" alt height="120" />
              </span>
            </router-link>

            <button @click="toggleMenu" type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
              <i class="ri-menu-2-line align-right"></i>
            </button>
          </div>
        </div>

        <!-- RIGHT: Profile Dropdown -->
        <div class="d-flex align-items-center ms-auto">
          <BDropdown variant="white" right toggle-class="header-item" menu-class="dropdown-menu-end" class="d-inline-block user-dropdown ms-2">
            <template #button-content>
              <img class="rounded-circle header-profile-user" src="@/assets/images/users/avatar0.jpg" alt="Header Avatar" />
              <span class="d-none d-xl-inline-block ms-1">{{ userName }}</span>
              <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
            </template>
            <BDropdownItem class="d-block" href="#">
              <span class="badge badge-success float-end mt-1">11</span>
              <i class="ri-settings-2-line align-middle me-1"></i>
              {{ $t("navbar.dropdown.kevin.list.settings") }}
            </BDropdownItem>
            <BDropdownDivider />
            <BDropdownItem class="text-danger" @click="handleLogout">
              <i class="ri-shut-down-line align-middle me-1 text-danger"></i>
              {{ $t("navbar.dropdown.kevin.list.logout") }}
            </BDropdownItem>
          </BDropdown>
        </div>
      </div>
    </div>
  </header>
</template>

<style lang="scss" scoped>
.notify-item {
  .active {
    color: #16181b;
    background-color: #f8f9fa;
  }
}
</style>