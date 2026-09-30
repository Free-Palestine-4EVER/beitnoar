import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { registerSW } from 'virtual:pwa-register';

// Register Service Worker immediately for PWA offline support and updates
registerSW({
    immediate: true,
});

const app = createApp(App);
app.use(router);
app.mount('#app');
