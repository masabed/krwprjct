<script>
import { required, email, helpers } from "@vuelidate/validators";
import {
  authMethods,
  authFackMethods,
} from "@/state/helpers";
export default {
  data() {
    return {
      user: { username: "", email: "", password: "" },
      submitted: false,
      regError: null,
      tryingToRegister: false,
      isRegisterError: false,
      registerSuccess: false
    };
  },
  validations: {
    validations: {
      user: {
        username: {
          required: helpers.withMessage("Username is required", required),
        },
        email: {
          required: helpers.withMessage("Email is required", required),
          email: helpers.withMessage("Please enter valid email", email),
        },
        password: {
          required: helpers.withMessage("Password is required", required),
        },
      },
    },
  },
  created() {
    document.body.classList.add("auth-body-bg");
  },
  methods: {
    ...authMethods,
    ...authFackMethods,
    // Try to register the user in with the email, username
    // and password they provided.
    tryToRegisterIn() {
      this.submitted = true;
      // stop here if form is invalid
      this.$touch;

      if (this.$invalid) {
        return;
      } else {
        if (import.meta.env.VITE_VUE_APP_DEFAULT_AUTH === "firebase") {
          this.tryingToRegister = true;
          // Reset the regError if it existed.
          this.regError = null;
          return (
            this.register({
              email: this.user.email,
              password: this.user.password
            })
              // eslint-disable-next-line no-unused-vars
              .then(token => {
                this.tryingToRegister = false;
                this.isRegisterError = false;
                this.registerSuccess = true;
                if (this.registerSuccess) {
                  this.$router.push(
                    this.$route.query.redirectFrom || { name: "home" }
                  );
                }
              })
              .catch(error => {
                this.tryingToRegister = false;
                this.regError = error ? error : "";
                this.isRegisterError = true;
              })
          );
        } else {
          const { email, username, password } = this.user;
          if (email && username && password) {
            this.registeruser(this.user);
          }
        }
      }
    }
  }
};
</script>

<template>
  <div>
    <div class="home-btn d-none d-sm-block">
      <router-link to="/">
        <i class="mdi mdi-home-variant h2 text-white"></i>
      </router-link>
    </div>
    <div>
      <div class="container-fluid p-0">
        <div class="row no-gutters">
          <div class="col-lg-4">
            <div class="authentication-page-content p-4 d-flex align-items-center min-vh-100">
              <div class="w-100">
                <div class="row justify-content-center">
                  <div class="col-lg-9">
                    <div>
                      <div class="text-center">
                        <div>
                          <router-link to="/" class="logo">
                            <img src="@/assets/images/logo-dark.png" height="20" alt="logo" />
                          </router-link>
                        </div>

                        <h4 class="font-size-18 mt-4">Register account</h4>
                        <p class="text-muted">Get your free Nazox account now.</p>
                      </div>

                      <div class="p-2 mt-5">
                        <b-alert v-model="registerSuccess" class="mt-3" variant="success" dismissible>Registration successfull.</b-alert>

                        <b-alert v-model="isRegisterError" class="mt-3" variant="danger" dismissible>{{ regError }}</b-alert>

                        <form class="form-horizontal" @submit.prevent="tryToRegisterIn">
                          <div class="form-group auth-form-group-custom mb-4">
                            <i class="ri-user-2-line auti-custom-input-icon"></i>
                            <label for="username">Username</label>
                            <input v-model="user.username" type="text" class="form-control" id="username" placeholder="Enter username" />
                          </div>

                          <div class="form-group auth-form-group-custom mb-4">
                            <i class="ri-mail-line auti-custom-input-icon"></i>
                            <label for="useremail">Email</label>
                            <input v-model="user.email" type="email" class="form-control" id="useremail" placeholder="Enter email" />
                          </div>

                          <div class="form-group auth-form-group-custom mb-4">
                            <i class="ri-lock-2-line auti-custom-input-icon"></i>
                            <label for="userpassword">Password</label>
                            <input v-model="user.password" type="password" class="form-control" id="userpassword" placeholder="Enter password" />
                          </div>

                          <div class="text-center">
                            <button class="btn btn-primary w-md waves-effect waves-light" type="submit">Register</button>
                          </div>

                          <div class="mt-4 text-center">
                            <p class="mb-0">
                              By registering you agree to the Nazox
                              <a href="#" class="text-primary">Terms of Use</a>
                            </p>
                          </div>
                        </form>
                      </div>

                      <div class="mt-5 text-center">
                        <p>
                          Already have an account ?
                          <router-link tag="a" to="/login" class="font-weight-medium text-primary">Login</router-link>
                        </p>
                        <p>
                          © 2024 Nazox. Crafted with
                          <i class="mdi mdi-heart text-danger"></i> by Themesdesign
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
            <div class="authentication-bg">
              <div class="bg-overlay"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>