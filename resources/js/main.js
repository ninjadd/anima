import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import router from './router';
import App from './App.vue';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

const mountEl = document.getElementById('app');
if (mountEl) {
  app.mount('#app');
}
