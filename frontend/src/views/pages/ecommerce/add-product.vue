<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";

import Multiselect from "@vueform/multiselect";
import DropZone from "@/components/dropZone.vue";
import { FormWizard, TabContent } from 'vue3-form-wizard'
import 'vue3-form-wizard/dist/style.css'

/**
 * Add Product Component
 */
export default {
  components: {
    Layout,
    PageHeader,
    FormWizard,
    TabContent,
    Multiselect,
    DropZone
  },
  data() {
    return {
      title: "Add Product",
      items: [
        {
          text: "Ecommerce"
        },
        {
          text: "Add Product",
          active: true
        }
      ],
      value: null,
      value1: [],
      options: [
        "Touchscreen",
        "Call Function",
        "Notifications",
        "Fitness",
        "Outdoor"
      ],
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
            <form-wizard color="#5664d2">
              <tab-content title="Basic Info">
                <div class="tab-pane" id="basic-info">
                  <h4 class="card-title">Basic Information</h4>
                  <p class="card-title-desc">Fill all information below</p>
                  <form>
                    <div class="mb-3">
                      <label for="productname">Product Name</label>
                      <input id="productname" name="productname" type="text" class="form-control" />
                    </div>
                    <div class="row">
                      <div class="col-lg-4">
                        <div class="mb-3">
                          <label for="manufacturername">Manufacturer Name</label>
                          <input id="manufacturername" name="manufacturername" type="text" class="form-control" />
                        </div>
                      </div>
                      <div class="col-lg-4">
                        <div class="mb-3">
                          <label for="manufacturerbrand">Manufacturer Brand</label>
                          <input id="manufacturerbrand" name="manufacturerbrand" type="text" class="form-control" />
                        </div>
                      </div>
                      <div class="col-lg-4">
                        <div class="mb-3">
                          <label for="price">Price</label>
                          <input id="price" name="price" type="text" class="form-control" />
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Category</label>
                          <multiselect v-model="value" :options="['Electronic', 'Fashion', 'Fitness']"></multiselect>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="mb-3">
                          <label class="form-label">Features</label>
                          <multiselect v-model="value1" :options="options" mode="tags" :searchable="true"></multiselect>
                        </div>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label for="productdesc">Product Description</label>
                      <textarea class="form-control" id="productdesc" rows="5"></textarea>
                    </div>
                  </form>
                </div>
              </tab-content>
              <tab-content title="Product Img">
                <DropZone />
              </tab-content>
              <tab-content title="Meta Data">
                <div class="tab-pane" id="metadata">
                  <h4 class="card-title">Meta Data</h4>
                  <p class="card-title-desc">Fill all information below</p>

                  <form>
                    <div class="row">
                      <div class="col-sm-6">
                        <div class="mb-3">
                          <label for="metatitle">Meta title</label>
                          <input id="metatitle" name="metatitle" type="text" class="form-control" />
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="mb-3">
                          <label for="metakeywords">Meta Keywords</label>
                          <input id="metakeywords" name="metakeywords" type="text" class="form-control" />
                        </div>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label for="metadescription">Meta Description</label>
                      <textarea class="form-control" id="metadescription" rows="5"></textarea>
                    </div>
                  </form>

                  <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary me-2 waves-effect waves-light">Save Changes</button>
                    <button type="submit" class="btn btn-light waves-effect">Cancel</button>
                  </div>
                </div>
              </tab-content>
            </form-wizard>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>