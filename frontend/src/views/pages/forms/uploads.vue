<script>
import DropZone from "@/components/dropzone.vue";
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

/**
 * File Uploads component
 */
export default {
  page: {
    title: "Form File Upload",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: { Layout, PageHeader, DropZone },
  data() {
    return {
      title: "Form File Upload",
      items: [
        {
          text: "Forms",
          href: "/"
        },
        {
          text: "Form File Upload",
          active: true
        }
      ],
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Dropzone</h4>
            <p class="card-title-desc">DropzoneJS is an open source library that provides drag’n’drop file uploads with image previews.</p>
            <!-- file upload -->
            <DropZone />
            <div class="text-center mt-4">
              <button type="button" class="btn btn-primary">Send Files</button>
            </div>
          </div>
          <!-- end card-body -->
        </div>
        <!-- end card -->
      </div>
      <!-- end col -->
    </div>
    <!-- end row -->
  </Layout>
</template>