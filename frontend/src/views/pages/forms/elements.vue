<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "@/components/page-header.vue";
import appConfig from "@/app.config";

/**
 * Form-element component
 */
export default {
  page: {
    title: "Forms Elements",
    meta: [{ name: "description", content: appConfig.description }]
  },
  components: { Layout, PageHeader },
  data() {
    return {
      title: "Forms Elements",
      items: [
        {
          text: "Forms",
          href: "/"
        },
        {
          text: "Forms Elements",
          active: true
        }
      ],
      status: "not_accepted",
      checkCustom: "not_accepted",
      checked: true
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
            <h4 class="card-title">Textual inputs</h4>
            <p class="card-title-desc">
              Here are examples of
              <code>.form-control</code> applied to each
              textual HTML5
              <code>&lt;input&gt;</code>
              <code>type</code>.
            </p>

            <div class="row">
              <div class="col-12">
                <form class="form-horizontal" role="form">
                  <BFormGroup class="mb-3" id="example text" label-cols-sm="2" label-cols-lg="2" label="Text" label-for="text">
                    <BFormInput for="text" value="Artisanal kale"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-search" label-cols-sm="2" label-cols-lg="2" label="Search" label-for="search">
                    <BFormInput id="search" value="How do I shoot web" type="search" name="search"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-email" label-cols-sm="2" label-cols-lg="2" label="Email" label-for="email">
                    <BFormInput id="email" value="bootstrap@example.com"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-url" label-cols-sm="2" label-cols-lg="2" label="URL" label-for="url">
                    <BFormInput id="url" value="https://getbootstrap.com" type="url" name="url"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-tel" label-cols-sm="2" label-cols-lg="2" label="Telephone" label-for="tele">
                    <BFormInput id="tele" value="1-(555)-555-5555" type="tel" name="tel"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-password" label-cols-sm="2" label-cols-lg="2" label="Password" label-for="pwd">
                    <BFormInput id="pwd" type="password" value="hunter2"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-number" label-cols-sm="2" label-cols-lg="2" label="Number" label-for="number">
                    <BFormInput id="number" value="42" type="number" name="number"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-date-time" label-cols-sm="2" label-cols-lg="2" label="Date and time" label-for="date-time">
                    <BFormInput id="date-time" value="2019-08-19T13:45:00" type="datetime-local"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-date" label-cols-sm="2" label-cols-lg="2" label="Date" label-for="date">
                    <BFormInput id="date" value="2019-08-19" type="date"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-month" label-cols-sm="2" label-cols-lg="2" label="Month" label-for="month">
                    <BFormInput id="month" value="2019-08" type="month"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-week" label-cols-sm="2" label-cols-lg="2" label="Week" label-for="week">
                    <BFormInput id="week" value="2019-W33" type="week"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-time" label-cols-sm="2" label-cols-lg="2" label="Time" label-for="time">
                    <BFormInput id="time" value="13:45:00" type="time"></BFormInput>
                  </BFormGroup>

                  <BFormGroup class="mb-3" id="example-color" label-cols-sm="2" label-cols-lg="2" label="Color" label-for="color">
                    <BFormInput id="color" type="color" name="color" value="#556ee6"></BFormInput>
                  </BFormGroup>

                  <div class="mb-3 row">
                    <label class="col-md-2 col-form-label">Select</label>
                    <div class="col-md-10">
                      <select class="form-control">
                        <option>Select</option>
                        <option>Large select</option>
                        <option>Small select</option>
                      </select>
                    </div>
                  </div>
                  <div class="mb-3 row">
                    <label class="col-md-2 col-form-label">Custom Select</label>
                    <div class="col-md-10">
                      <select class="custom-select">
                        <option selected>Open this select menu</option>
                        <option value="1">One</option>
                        <option value="2">Two</option>
                        <option value="3">Three</option>
                      </select>
                    </div>
                  </div>
                </form>
              </div>
            </div>
            <!-- end row -->
          </div>
        </div>
        <!-- end card -->
      </div>
      <!-- end col -->
    </div>
    <!-- end row -->
    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Sizing</h4>
            <p class="card-title-desc">
              Set heights using size like
              <code>lg</code> and
              <code>sm</code>.
            </p>
            <div>
              <div class="mb-4">
                <BFormInput id="input-md" placeholder="Default input"></BFormInput>
              </div>
              <div class="mb-4">
                <BFormInput id="input-small" size="sm" placeholder=".form-control-sm"></BFormInput>
              </div>
              <div class="mb-4">
                <BFormInput id="input-lg" size="lg" placeholder=".form-control-lg"></BFormInput>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Range Inputs</h4>
            <p class="card-title-desc">
              Set horizontally scrollable range inputs using
              <code>.form-control-range</code>.
            </p>

            <div>
              <h5 class="font-size-14">Example</h5>
              <input id="formControlRange" type="range" class="form-control-range" />
            </div>
            <div class="mt-4">
              <h5 class="font-size-14">Custom Range</h5>
              <BFormInput id="custom-range" type="range"></BFormInput>

              <BFormInput id="customRange2" class="mt-4" type="range" min="0" max="5"></BFormInput>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Checkboxes</h4>

            <div class="row">
              <div class="col-md-6">
                <div>
                  <h5 class="font-size-14 mb-4">Default Checkboxes</h5>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value id="defaultCheck1" />
                    <label class="form-check-label" for="defaultCheck1">Default checkbox</label>
                  </div>
                  <div class="form-check form-check-right">
                    <input class="form-check-input" type="checkbox" value id="defaultCheck2" checked />
                    <label class="form-check-label" for="defaultCheck2">Default checkbox</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mt-4 mt-lg-0">
                  <h5 class="font-size-14 mb-4">Custom Checkboxes</h5>
                  <div class="form-check custom-checkbox mb-3">
                    <input type="checkbox" class="form-check-input" id="customCheck1" checked />
                    <label class="form-check-label" for="customCheck1">Custom checkbox</label>
                  </div>
                  <div class="form-check custom-checkbox custom-control-right">
                    <input type="checkbox" class="form-check-input" id="customCheck2" />
                    <label class="form-check-label" for="customCheck2">Custom checkbox</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title mb-4">Radios</h4>

            <div class="row">
              <div class="col-md-6">
                <div>
                  <h5 class="font-size-14 mb-4">Default Radios</h5>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="exampleRadios" id="exampleRadios1" value="option1" checked />
                    <label class="form-check-label" for="exampleRadios1">Default radio</label>
                  </div>
                  <div class="form-check form-check-right">
                    <input class="form-check-input" type="radio" name="exampleRadios" id="exampleRadios2" value="option2" />
                    <label class="form-check-label" for="exampleRadios2">Default radio</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mt-4 mt-lg-0">
                  <h5 class="font-size-14 mb-4">Custom Radios</h5>
                  <div class="form-check custom-radio mb-3">
                    <input type="radio" id="customRadio1" name="customRadio" class="form-check-input" />
                    <label class="form-check-label" for="customRadio1">Toggle this custom radio</label>
                  </div>
                  <div class="form-check custom-radio custom-control-right">
                    <input type="radio" id="customRadio2" name="customRadio" class="form-check-input" checked />
                    <label class="form-check-label" for="customRadio2">Or toggle this other custom radio</label>
                  </div>
                </div>
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
            <h4 class="card-title">Switches</h4>
            <p class="card-title-desc">
              A switch has the markup of a custom checkbox but uses the
              <code>.custom-switch</code> class to render a toggle switch. Switches also support the
              <code>disabled</code> attribute.
            </p>
            <BFormCheckbox v-model="checked" switch class="mb-1">
              <label>Toggle this switch element</label>
            </BFormCheckbox>
            <BFormCheckbox switch disabled>
              <label>Disabled switch element</label>
            </BFormCheckbox>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">File browser</h4>
            <p class="card-title-desc">
              The file input is the most gnarly of the bunch and requires additional JavaScript if you’d like to hook them up with functional
              <em>Choose file…</em> and selected file name text.
            </p>
            <div class="custom-file">
              <input id="customFile" type="file" class="custom-file-input" />
              <label class="custom-file-label" for="customFile">Choose file</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- end row -->
  </Layout>
</template>