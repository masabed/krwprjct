<script>
import DatePicker from "vue3-datepicker";
import Switches from "vue-switches";
import Multiselect from "@vueform/multiselect";
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

/**
 * Advanced-form component
 */
export default {
  page: {
    title: "Form Advanced",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: {
    DatePicker,
    Switches,
    Multiselect,
    Layout,
    PageHeader
  },
  data() {
    return {
      title: "Form Advanced",
      items: [
        {
          text: "Forms",
          href: "/"
        },
        {
          text: "Advanced",
          active: true
        }
      ],
      defaultdate: "",
      autoClose: "",
      daterange: "",
      demoMonth: "",
      demoYear: "",
      time: null,
      enabled: false,
      enabled1: false,
      enabled2: false,
      enabled3: false,
      enabled4: false,
      enabled5: false,
      small: false,
      small1: false,
      small2: false,
      small3: false,
      danger: false,
      info: false,
      success: false,
      primary: false,
      secondary: false,
      customColor: false,
      customColor1: false,
      customColor2: false,
      customColor3: false,
      value: null,
      value1: null,
      options: [
        "Alaska",
        "Hawaii",
        "California",
        "Nevada",
        "Oregon",
        "Washington",
        "Arizona",
        "Colorado",
        "Idaho",
        "Montana",
        "Nebraska",
        "New Mexico",
        "North Dakota",
        "Utah",
        "Wyoming",
        "Alabama",
        "Arkansas",
        "Illinois",
        "Iowa"
      ],
      maxlen: "",
      data: "",
      option: "",
      textarea: "",
      yourVModel: 10,
      defaultNull: null
    };
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
            <h4 class="card-title">Select2</h4>
            <p class="card-title-desc">A mobile and touch friendly input spinner component for Bootstrap</p>
            <label>Single Select</label>

            <Multiselect v-model="value" :options="options"></Multiselect>
            <label class="mt-3">Multiple Select</label>

            <Multiselect v-model="value1" :options="options" mode="tags"></Multiselect>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Example switch</h4>
            <p class="card-title-desc">Here are a few types of switches.</p>

            <div>
              <input type="checkbox" id="switch1" switch="none" checked />
              <label for="switch1" data-on-label="On" data-off-label="Off"></label>

              <input type="checkbox" id="switch2" switch="default" checked />
              <label for="switch2" data-on-label="" data-off-label=""></label>

              <input type="checkbox" id="switch3" switch="bool" checked />
              <label for="switch3" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch6" switch="primary" checked />
              <label for="switch6" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch4" switch="success" checked />
              <label for="switch4" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch7" switch="info" checked />
              <label for="switch7" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch5" switch="warning" checked />
              <label for="switch5" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch8" switch="danger" checked />
              <label for="switch8" data-on-label="Yes" data-off-label="No"></label>

              <input type="checkbox" id="switch9" switch="dark" checked />
              <label for="switch9" data-on-label="Yes" data-off-label="No"></label>
            </div>

            <h5 class="mt-4 font-size-14 mb-4">Square switch</h5>
            <div class="d-flex">
              <div class="square-switch">
                <input type="checkbox" id="square-switch1" switch="none" checked />
                <label for="square-switch1" data-on-label="On" data-off-label="Off"></label>
              </div>
              <div class="square-switch">
                <input type="checkbox" id="square-switch2" switch="info" checked />
                <label for="square-switch2" data-on-label="Yes" data-off-label="No"></label>
              </div>
              <div class="square-switch">
                <input type="checkbox" id="square-switch3" switch="bool" checked />
                <label for="square-switch3" data-on-label="Yes" data-off-label="No"></label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Bootstrap MaxLength</h4>
            <p class="card-title-desc">Uses the HTML5 attribute "maxlength" to work.</p>

            <label class="mb-1 font-weight-medium">Default usage</label>
            <p class="text-muted font-13">The badge will show up by default when the remaining chars are 10 or less:</p>
            <input v-model="maxlen" type="text" class="form-control" :maxlength="10" />
            <div v-if="maxlen" class="text-center">
              <p class="badge position-absolute" :class="{
                'badge-success': maxlen.length !== 10,
                'badge-danger': maxlen.length === 10
              }">{{ maxlen.length }} / 10</p>
            </div>

            <div>
              <label class="mb-1 mt-3 font-weight-medium">Threshold value</label>
              <p class="text-muted font-13">
                Do you want the badge to show up when there are 20 chars or less? Use the
                <code>threshold</code>
                option:
              </p>
              <input v-model="data" type="text" :maxlength="25" class="form-control" />
              <div class="text-center">
                <p v-if="data" class="badge position-absolute" :class="{
                  'badge-success': data.length !== 25,
                  'badge-danger': data.length === 25
                }">{{ data.length }} / 25</p>
              </div>
            </div>

            <div>
              <label class="mb-1 mt-3 font-weight-medium">All the options</label>
              <p class="text-muted font-13">
                Please note: if the
                <code>alwaysShow</code> option is enabled, the
                <code>threshold</code> option
                is
                ignored.
              </p>
              <input v-model="option" type="text" class="form-control" :maxlength="25" />
              <div class="text-center">
                <p v-if="option" class="badge position-absolute" :class="{
                  'badge-success': option.length !== 25,
                  'badge-danger': option.length === 25
                }">
                  You typed
                  {{ option.length }} out of 25 chars available.
                </p>
              </div>
            </div>

            <div>
              <label class="mb-1 mt-3 font-weight-medium">Textareas</label>
              <p class="text-muted font-13">Bootstrap maxlength supports textarea as well as inputs. Even on old IE.</p>
              <textarea v-model="textarea" class="form-control" :maxlength="225" rows="3" placeholder="This textarea has a limit of 225 chars."></textarea>
              <div class="text-center">
                <p v-if="textarea" class="badge position-absolute" :class="{
                  'badge-success': textarea.length !== 225,
                  'badge-danger': textarea.length === 225
                }">
                  {{ textarea.length }} /
                  225
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Date Picker</h4>
            <p class="card-title-desc">Examples of bootstrap datepicker.</p>

            <div>
              <div class="form-group mb-3">
                <label>Default Date Picker</label>
                <br />
                <date-picker class="form-control" v-model="defaultdate" :first-day-of-week="1" lang="en" confirm></date-picker>
              </div>

              <div class="form-group mb-3">
                <label>Auto close</label>
                <br />
                <date-picker class="form-control" v-model="autoClose" :first-day-of-week="1" lang="en"></date-picker>
              </div>

              <div class="form-group mb-3">
                <label>Month View</label>
                <br />
                <date-picker class="form-control" v-model="demoMonth" minimumView="month" lang="en" confirm placeholder="Select Month"></date-picker>
              </div>

              <div class="form-group mb-3">
                <label>Year View</label>
                <br />
                <date-picker class="form-control" v-model="demoYear" minimumView="year" lang="en" confirm placeholder="Select Year"></date-picker>
              </div>

              <div class="form-group mb-3">
                <label>Time View</label>
                <br />
                <date-picker class="form-control" v-model="time" minimumView="time" placeholder="hh:mm:ss a"></date-picker>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- End datepicker -->
    </div>
  </Layout>
</template>