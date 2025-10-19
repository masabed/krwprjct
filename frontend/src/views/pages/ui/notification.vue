<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
/**
 * Notification Component
 */
export default {
  page: {
    title: "Notification",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: { Layout, PageHeader },
  data() {
    return {
      title: "Notification",
      items: [
        {
          text: "UI Elements",
          href: "/"
        },
        {
          text: "Notification",
          active: true
        }
      ],
      toastCount: 0
    };
  },
  methods: {
    success() {
      toast.success('Wow so easy!');
    },
    danger() {
      toast.error('Wow so easy!');
    },
    warning() {
      toast.warning('Wow so easy!');
    },
    info() {
      toast.info('Wow so easy!');
    },
    topRight() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.TOP_RIGHT,
      });
    },
    topLeft() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.TOP_LEFT,
      });
    },
    topCenter() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.TOP_CENTER,
      });
    },
    topFull() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.TOP_CENTER,
      });
    },
    bottomRight() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.BOTTOM_RIGHT,
      });
    },
    bottomLeft() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.BOTTOM_LEFT,
      });
    },
    bottomCenter() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.BOTTOM_CENTER,
      });
    },
    bottomFull() {
      toast.success('Wow so easy!', {
        position: toast.POSITION.BOTTOM_CENTER,
      });
    }

  }
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <BButton @click="success">Default</BButton>
            <BButton variant="danger" @click="danger" class="ms-2">Danger</BButton>
            <BButton variant="warning" @click="warning" class="ms-2">Warning</BButton>
            <BButton variant="success" @click="success" class="ms-2">Success</BButton>
            <BButton variant="info" @click="info" class="ms-2">Info</BButton>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-8">
        <div class="card">
          <div class="card-body">
            <BButton @click="topRight" class="ms-2" variant="light">Top-right</BButton>
            <BButton @click="topLeft" class="ms-2" variant="light">Top-left</BButton>
            <BButton @click="topCenter" class="ms-2" variant="light">Top-center</BButton>
            <BButton @click="topFull" class="ms-2" variant="light">Top-full</BButton>
            <BButton @click="bottomRight" class="ms-2" variant="light">Bottom-right</BButton>
            <BButton @click="bottomLeft" class="ms-2" variant="light">Bottom-left</BButton>
            <BButton @click="bottomCenter" class="ms-2" variant="light">Bottom-center</BButton>
            <BButton @click="bottomFull" class="ms-2" variant="light">Bottom-full</BButton>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>