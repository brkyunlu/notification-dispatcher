import './bootstrap';
import { createApp } from 'vue';
import Dashboard from './components/Dashboard.vue';

// Create Vue app and mount Dashboard component
const app = createApp(Dashboard);
app.mount('#app');
