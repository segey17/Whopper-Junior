import Vue from 'vue';
import Vuetify from 'vuetify';
import App from './App.vue';
import router from './router/index.js';

Vue.use(Vuetify);
new Vue({
  el: '#app',
  render: h => h(App),
  router
});