import { createRouter, createWebHistory } from 'vue-router';
import MenuHome from './views/MenuHome.vue';
import CategoryView from './views/CategoryView.vue';
import ProductView from './views/ProductView.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: MenuHome,
    },
    {
        path: '/category/:id',
        name: 'category',
        component: CategoryView,
    },
    {
        path: '/product/:id',
        name: 'product',
        component: ProductView,
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        }
        const main = document.querySelector('main');
        if (main) {
            main.scrollTop = 0;
        }
        return { top: 0 };
    },
});

export default router;
