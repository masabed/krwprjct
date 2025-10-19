<script>
import { VueEcharts } from "vue3-echarts";

import Layout from "../../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

import {
  polarChart,
  lineChart,
  pieChart,
  bubbleChart,
  doughnutChart,
  mixedBarChart,
  guageChart,
  candleStickChart,
} from "./data";

export default {
  page: {
    title: "Echart Chart",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: { Layout, PageHeader, VueEcharts },
  data() {
    return {
      title: "ECharts",
      items: [
        {
          text: "Charts",
          href: "/"
        },
        {
          text: "ECharts",
          active: true
        }
      ]
    };
  },
  computed: {
    option() {
      return {
        polarChart: polarChart,
        lineChart: lineChart,
        pieChart: pieChart,
        bubbleChart: bubbleChart,
        doughnutChart: doughnutChart,
        mixedBarChart: mixedBarChart,
        candleStickChart: candleStickChart,
        guageChart: guageChart,
      };
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Line Chart</h4>
            <vue-echarts :option="option.lineChart" style="height: 300px" ref="chart" />
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Mix Line-Bar</h4>
            <vue-echarts :option="option.mixedBarChart" style="height: 300px" ref="chart" />
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Pie Chart</h4>
            <vue-echarts :option="option.pieChart" style="height: 300px" />
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Doughnut Chart</h4>
            <vue-echarts :option="option.doughnutChart" style="height: 300px" />
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Candle Stick Chart</h4>
            <vue-echarts :option="option.candleStickChart" style="height: 300px" />
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Gauge Chart</h4>
            <vue-echarts :option="option.guageChart" style="height: 300px" />
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Bubble Chart</h4>
            <vue-echarts :option="option.bubbleChart" style="height: 300px" />
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Polar Chart</h4>
            <vue-echarts :option="option.polarChart.polar" style="height: 300px" />
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style lang="scss" scoped>
.echarts {
  height: 350px;
  width: 558px;
}
</style>