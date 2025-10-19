<script>
import { required, helpers } from "@vuelidate/validators";
import useVuelidate from "@vuelidate/core";

export default {
  setup() {
    return { v$: useVuelidate() };
  },
  data() {
    return {
      username: "",
      password: "",
      submitted: false,
      authError: null,
      isAuthError: false,
    };
  },
  created() {
    document.body.classList.add("auth-body-bg");
  },
  validations() {
    return {
      username: { required: helpers.withMessage("Username is required", required) },
      password: { required: helpers.withMessage("Password is required", required) },
    };
  },
  methods: {
    async tryToLogIn() {
      this.submitted = true;
      this.v$.$touch();
      if (this.v$.$invalid) return;

      try {
        const form = new FormData();
        form.append("username", this.username);
        form.append("password", this.password);

        const res = await this.$axios.post("/login", form); // 🔐 use global axios

        const { access_token, user } = res.data;
        localStorage.setItem("access_token", access_token);
        localStorage.setItem("user", JSON.stringify(user));

        this.isAuthError = false;

        this.$router.push(this.$route.query.redirectFrom || "/"); // 🔁 go to dashboard
      } catch (err) {
        this.authError = err?.response?.data?.message || "Login failed";
        this.isAuthError = true;
      }
    },
  },
};
</script>

<template>
  <div class="container-fluid p-0">
    <div class="row g-0">
      <div class="col-lg-4">
        <div class="authentication-page-content p-4 d-flex align-items-center min-vh-100">
          <div class="w-100">
            <div class="row justify-content-center">
              <div class="col-lg-9">
                <div class="text-center">
                  <router-link to="/" class="logo">
                    <img src="@/assets/images/SIIMAH.png" height="200" alt="logo" />
                  </router-link>
                  <h4 class="font-size-18 mt-4">Selamat Datang di SIIMAH</h4>
                  <p class="text-muted">Silahkan Masuk Untuk Melanjutkan</p>
                </div>

                <div class="p-2 mt-5">
                  <form class="form-horizontal" @submit.prevent="tryToLogIn">
                    <div class="form-group auth-form-group-custom mb-4">
                      <i class="ri-user-line auti-custom-input-icon"></i>
                      <label for="username">Username</label>
                      <input
                        v-model.trim="username"
                        type="text"
                        class="form-control"
                        id="username"
                        placeholder="Enter username"
                      />
                    </div>

                    <div class="form-group auth-form-group-custom mb-4">
                      <i class="ri-lock-2-line auti-custom-input-icon"></i>
                      <label for="userpassword">Password</label>
                      <input
                        v-model.trim="password"
                        type="password"
                        class="form-control"
                        id="userpassword"
                        placeholder="Enter password"
                      />
                    </div>


                    <div class="mt-4 text-center">
                      <button class="btn btn-primary w-md waves-effect waves-light" type="submit">
                        Log In
                      </button>
                    </div>


                    <div v-if="isAuthError" class="mt-3 text-danger text-center">
                      {{ authError }}
                    </div>
                  </form>
                </div>

                <div class="mt-5 text-center">
                  <p>© 2025 DPRKP. KABUPATEN KARAWANG</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="authentication-bg"><div class="bg-overlay"></div></div>
      </div>
    </div>
  </div>
</template>
