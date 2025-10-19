<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../../components/page-header.vue";
import Stat from "./widget.vue";
import RevenueAnalytics from "./revenue.vue";
import SalesAnalytics from "./sales-analytics.vue";
import EarningReport from "./earning.vue";
import Sources from "./sources.vue";
import RecentActivity from "./recent-activity.vue";
import RevenueLocation from "./revenue-location.vue";
import Transaction from './transaction.vue';

/**
 * Dashboard component
 */
export default {
  components: {
    Layout,
    PageHeader,
    Stat,
    RevenueAnalytics,
    SalesAnalytics,
    EarningReport,
    Sources,
    RecentActivity,
    RevenueLocation,
    Transaction
  },
  data() {
    return {
      title: "Dashboard",
      items: [
        {
          text: "UNDER DEVELOPMENT"
        },
        {
          text: "Dashboard",
          active: true
        }
      ]
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-xl-8">
        <Stat />
        <RevenueAnalytics />
      </div>
      <div class="col-xl-4">
        <SalesAnalytics />
        <EarningReport />
      </div>
    </div>
    <div class="row">
      <div class="col-lg-4">
        <Sources />
      </div>
      <div class="col-lg-4">
        <RecentActivity />
      </div>
      <div class="col-lg-4">
        <RevenueLocation />
      </div>
    </div>
      <div class="col-lg-8">
        <Transaction />
      </div>
  </Layout>
</template>