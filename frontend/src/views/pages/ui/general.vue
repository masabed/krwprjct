<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

/**
 * General-ui component
 */
export default {
  page: {
    title: "General UI",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: { Layout, PageHeader },
  data() {
    return {
      title: "General",
      items: [
        {
          text: "UI Elements",
          href: "/"
        },
        {
          text: "General",
          active: true
        }
      ],
      defaultvalue: 3,
      currentpage: 3
    };
  }
};
</script>

<template>
  <Layout>
    <PageHeader :title="title" :items="items" />
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <div class="row">
              <div class="col-lg-6">
                <div>
                  <h4 class="card-title">badges</h4>
                  <p class="card-title-desc">Add any of the below mentioned modifier classes to change the appearance of a badge.</p>
                  <div>
                    <BBadge variant="primary">Primary</BBadge>
                    <BBadge variant="success" class="ms-1">Success</BBadge>
                    <BBadge variant="info" class="ms-1">Info</BBadge>
                    <BBadge variant="warning" class="ms-1">Warning</BBadge>
                    <BBadge variant="danger" class="ms-1">Danger</BBadge>
                    <BBadge variant="dark" class="ms-1">Dark</BBadge>
                  </div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="mt-4 mt-lg-0">
                  <h4 class="card-title">Pill badges</h4>
                  <p class="card-title-desc">
                    Use the
                    <code>.badge-pill</code> modifier class to make
                    badges more rounded (with a larger
                    <code>border-radius</code>
                    and additional horizontal
                    <code>padding</code>).
                    Useful if you miss the badges from v3.
                  </p>

                  <div>
                    <BBadge pill variant="primary">Primary</BBadge>
                    <BBadge pill variant="success" class="ms-1">Success</BBadge>
                    <BBadge pill variant="info" class="ms-1">Info</BBadge>
                    <BBadge pill variant="warning" class="ms-1">Warning</BBadge>
                    <BBadge pill variant="danger" class="ms-1">Danger</BBadge>
                    <BBadge pill variant="dark" class="ms-1">Dark</BBadge>
                  </div>
                </div>
              </div>
            </div>
            <!-- end row -->
          </div>
        </div>
        <!-- end card -->
      </div>
    </div>
    <!-- end row -->

    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Popovers</h4>
            <p class="card-title-desc">Add small overlay content, like those found in iOS, to any element for housing secondary information.</p>

            <div class="button-items">
              <BButton v-b-popover.top="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'" variant="light">Popover on top</BButton>

              <BButton v-b-popover.bottom="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'" variant="light">Popover on bottom</BButton>
              <BButton v-b-popover.right="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'" variant="light">Popover on right</BButton>
              <BButton v-b-popover.left="'Vivamus sagittis lacus vel augue laoreet rutrum faucibus.'" variant="light">Popover on left</BButton>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Tooltips</h4>
            <p class="card-title-desc">Hover over the links below to see tooltips:</p>

            <div class="button-items">
              <BButton id="tooltip-top" variant="primary">Tooltip on top</BButton>
              <BTooltip target="tooltip-top">Tooltip on top</BTooltip>
              <BButton id="tooltip-button" variant="primary">Tooltip on bottom</BButton>
              <BTooltip target="tooltip-button" placement="bottom">Tooltip on bottom</BTooltip>
              <BButton id="tooltip-left-button" variant="primary">Tooltip on left</BButton>
              <BTooltip target="tooltip-left-button">Tooltip on left</BTooltip>
              <BButton id="tooltip-right-button" variant="primary">Tooltip on right</BButton>
              <BTooltip target="tooltip-right-button" placement="bottom">Tooltip on right</BTooltip>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- end row -->

    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Pagination</h4>

            <div class="row">
              <div class="col-lg-6">
                <h5 class="font-size-14">Default Example</h5>
                <p class="card-title-desc">Pagination links indicate a series of related content exists across multiple pages.</p>
                <BPagination v-model="defaultvalue" :total-rows="50" :per-page="10" aria-controls="my-table"></BPagination>

              </div>

              <div class="col-lg-6">
                <div class="mt-4">
                  <h5 class="card-title">Alignment</h5>
                  <p class="card-title-desc">
                    Change the alignment of pagination
                    components with flexbox utilities.
                  </p>

                  <BPagination :total-rows="50" prev-text="Previous" next-text="Next" align="center"></BPagination>

                  <BPagination :total-rows="50" prev-text="Previous" next-text="Next" align="right"></BPagination>
                </div>
              </div>
            </div>
            <!-- end row -->

            <div class="row">
              <div class="col-lg-6">
                <div class="mt-4">
                  <h5 class="font-size-14">Sizing</h5>
                  <p class="card-title-desc">
                    Fancy larger or smaller pagination? Add
                    <code>.pagination-lg</code> or
                    <code>.pagination-sm</code> for additional
                    sizes.
                  </p>
                  <BPagination size="lg" :total-rows="30" :per-page="10" aria-controls="my-table"></BPagination>

                  <BPagination size="sm" :total-rows="30" :per-page="10" aria-controls="my-table"></BPagination>
                </div>
              </div>
            </div>
            <!-- end row -->
          </div>
        </div>
        <!-- end card -->
      </div>
    </div>
    <!-- end row -->

    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Border spinner</h4>
            <p class="card-title-desc">Use the border spinners for a lightweight loading indicator.</p>
            <div>
              <BSpinner class="m-2" variant="primary" role="status"></BSpinner>
              <BSpinner class="m-2" variant="secondary" role="status"></BSpinner>
              <BSpinner class="m-2" variant="success" role="status"></BSpinner>
              <BSpinner class="m-2" variant="info" role="status"></BSpinner>
              <BSpinner class="m-2" variant="warning" role="status"></BSpinner>
              <BSpinner class="m-2" variant="danger" role="status"></BSpinner>
              <BSpinner class="m-2" variant="dark" role="status"></BSpinner>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Growing spinner</h4>
            <p class="card-title-desc">If you don’t fancy a border spinner, switch to the grow spinner. While it doesn’t technically spin, it does repeatedly grow!</p>
            <div>
              <BSpinner type="grow" class="m-2" variant="primary" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="secondary" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="success" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="info" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="warning" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="danger" role="status"></BSpinner>
              <BSpinner type="grow" class="m-2" variant="dark" role="status"></BSpinner>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- end row -->
  </Layout>
</template>