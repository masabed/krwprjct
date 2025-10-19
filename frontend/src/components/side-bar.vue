<script>
import SimpleBar from "simplebar-vue";
import { layoutComputed } from "@/state/helpers";
import { MetisMenu } from "metismenujs";
import { menuItems } from "./menu";

export default {
  components: {
    SimpleBar,
  },
  data() {
    return {
      fullMenuItems: menuItems,
      filteredMenuItems: [],
      userRole: null,
    };
  },
  computed: {
    ...layoutComputed,
  },
  mounted() {
    try {
      const user = JSON.parse(localStorage.getItem("user"));
      this.userRole = user?.role || null;
    } catch (e) {
      console.warn("Invalid user JSON", e);
      this.userRole = null;
    }

    this.filteredMenuItems = this.getFilteredMenu(this.fullMenuItems, this.userRole);

    const menuRef = new MetisMenu("#side-menu");
    const links = document.getElementsByClassName("side-nav-link-ref");
    let matchingMenuItem = null;
    for (let i = 0; i < links.length; i++) {
      if (window.location.pathname === links[i].pathname) {
        matchingMenuItem = links[i];
        break;
      }
    }
    if (matchingMenuItem) {
      matchingMenuItem.classList.add("active");
      let parent = matchingMenuItem.parentElement;
      if (parent) {
        parent.classList.add("mm-active");
        const parent2 = parent.parentElement.closest("ul");
        if (parent2 && parent2.id !== "side-menu") {
          parent2.classList.add("mm-show");
          const parent3 = parent2.parentElement;
          if (parent3) {
            parent3.classList.add("mm-active");
            const childAnchor = parent3.querySelector(".has-arrow");
            const childDropdown = parent3.querySelector(".has-dropdown");
            if (childAnchor) childAnchor.classList.add("mm-active");
            if (childDropdown) childDropdown.classList.add("mm-active");
            const parent4 = parent3.parentElement;
            if (parent4 && parent4.id !== "side-menu") {
              parent4.classList.add("mm-show");
              const parent5 = parent4.parentElement;
              if (parent5 && parent5.id !== "side-menu") {
                parent5.classList.add("mm-active");
                const childanchor = parent5.querySelector(".is-parent");
                if (childanchor && parent5.id !== "side-menu") {
                  childanchor.classList.add("mm-active");
                }
              }
            }
          }
        }
      }
    }
  },
  methods: {
    hasItems(item) {
      return item.subItems?.length > 0;
    },
    getFilteredMenu(menu, role) {
      return menu.filter(item => {
        if (item.roles && !item.roles.includes(role)) {
          return false;
        }
        if (item.subItems) {
          item.subItems = item.subItems.filter(sub => {
            if (sub.roles && !sub.roles.includes(role)) {
              return false;
            }
            return true;
          });
          return item.subItems.length > 0;
        }
        return true;
      });
    },
    onRoutechange() {
      setTimeout(() => {
        const currentPosition = document.getElementsByClassName("mm-active")[0]?.offsetTop || 0;
        if (currentPosition > 400) {
          this.$refs.currentMenu.SimpleBar.getScrollElement().scrollTop = currentPosition + 200;
        }
      }, 300);
    },
  },
  watch: {
    $route: {
      handler: "onRoutechange",
      immediate: true,
      deep: true,
    },
  },
};
</script>

<template>
  <div class="vertical-menu">
    <SimpleBar class="h-100" ref="currentMenu" id="my-element">
      <div id="sidebar-menu">
        <ul class="metismenu list-unstyled" id="side-menu">
          <template v-for="item in filteredMenuItems">
            <li class="menu-title" v-if="item.isTitle" :key="item.id">
              {{ $t(item.label) }}
            </li>
            <li v-if="!item.isTitle && !item.isLayout" :key="item.id">
              <a
                v-if="hasItems(item)"
                href="javascript:void(0);"
                class="is-parent"
                :class="{
                  'has-arrow': !item.badge,
                  'has-dropdown': item.badge,
                }"
              >
                <i :class="`bx ${item.icon}`" v-if="item.icon"></i>
                <span>{{ $t(item.label) }}</span>
                <span
                  :class="`badge rounded-pill bg-${item.badge.variant} float-end`"
                  v-if="item.badge"
                >
                  {{ $t(item.badge.text) }}
                </span>
              </a>
              <router-link
                :to="item.link"
                v-if="!hasItems(item)"
                class="side-nav-link-ref"
              >
                <i :class="`bx ${item.icon}`" v-if="item.icon"></i>
                <span>{{ $t(item.label) }}</span>
                <span
                  :class="`badge rounded-pill bg-${item.badge.variant} float-end`"
                  v-if="item.badge"
                >
                  {{ $t(item.badge.text) }}
                </span>
              </router-link>
              <ul v-if="hasItems(item)" class="sub-menu" aria-expanded="false">
                <li v-for="(subitem, index) of item.subItems" :key="index">
                  <router-link
                    :to="subitem.link"
                    v-if="!hasItems(subitem)"
                    class="side-nav-link-ref"
                  >
                    {{ $t(subitem.label) }}
                  </router-link>
                  <a
                    v-if="hasItems(subitem)"
                    class="side-nav-link-a-ref has-arrow"
                    href="javascript:void(0);"
                  >
                    {{ subitem.label }}
                  </a>
                  <ul
                    v-if="hasItems(subitem)"
                    class="sub-menu mm-collapse"
                    aria-expanded="false"
                  >
                    <li v-for="(subSubitem, index) of subitem.subItems" :key="index">
                      <router-link :to="subSubitem.link" class="side-nav-link-ref">
                        {{ $t(subSubitem.label) }}
                      </router-link>
                    </li>
                  </ul>
                </li>
              </ul>
            </li>
          </template>
        </ul>
      </div>
    </SimpleBar>
  </div>
</template>
